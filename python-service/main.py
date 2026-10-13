"""Layanan AI Cam: deteksi objek (YOLO) dan deskripsi vision.

Deskripsi vision memakai Google Gemini (cloud) sebagai penyedia utama bila
dikonfigurasi, dengan Ollama (lokal) sebagai cadangan. Kunci API hanya dibaca
dari environment backend Python atau file .env di folder ini, dan tidak pernah
dikirim ke klien maupun dicatat ke log.
"""

import base64
import io
import json
import logging
import os
import re
import threading
import time
from collections import Counter
from contextlib import asynccontextmanager

import requests
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.concurrency import run_in_threadpool
from PIL import Image, ImageOps, UnidentifiedImageError
from PIL import ImageStat
from ultralytics import YOLO

try:  # SDK resmi Google Gen AI; opsional agar layanan tetap jalan tanpanya.
    from google import genai as genai_sdk
    from google.genai import errors as genai_errors
    from google.genai import types as genai_types

    GENAI_AVAILABLE = True
except Exception:  # pragma: no cover - bergantung lingkungan
    genai_sdk = None
    genai_errors = None
    genai_types = None
    GENAI_AVAILABLE = False


def load_env_file(path: str) -> None:
    """Muat variabel dari file .env tanpa menimpa environment yang sudah ada."""
    if not os.path.exists(path):
        return
    try:
        with open(path, encoding="utf-8") as handle:
            for raw in handle:
                line = raw.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, _, value = line.partition("=")
                key = key.strip()
                value = value.strip().strip('"').strip("'")
                if key and key not in os.environ:
                    os.environ[key] = value
    except OSError:
        logger.warning("tidak dapat membaca file .env")


def env_bool(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on")


logging.basicConfig(
    level=os.getenv("LOG_LEVEL", "INFO").upper(),
    format="%(asctime)s %(levelname)s [ai-cam] %(message)s",
)
logger = logging.getLogger("ai_cam")

load_env_file(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env"))


@asynccontextmanager
async def lifespan(_app: FastAPI):
    if env_bool("YOLO_PRELOAD", True):
        try:
            get_model()
            logger.info("model YOLO siap (preload %s)", os.getenv("YOLO_MODEL", "yolo11s.pt"))
        except Exception:  # pragma: no cover - bergantung file model
            logger.exception("gagal memuat model YOLO saat startup")
    yield


app = FastAPI(title="AI Cam Python Service", version="1.2.0", lifespan=lifespan)

MODEL_NAME = os.getenv("YOLO_MODEL", "yolo11s.pt")
# Ukuran gambar inferensi YOLO. Harus kelipatan 32. Lebih besar = lebih akurat
# (khususnya objek kecil/jauh) tetapi lebih lambat.
YOLO_IMGSZ = max(32, (int(os.getenv("YOLO_IMGSZ", "960")) // 32) * 32)
# Ambang keyakinan deteksi (false positive turun bila dinaikkan).
YOLO_CONF = float(os.getenv("YOLO_CONF", "0.35"))
# Ambang IoU untuk Non-Max Suppression. Turunkan (mis. 0.5) agar kotak yang
# saling menumpuk/duplikat ditekan; naikkan bila objek berdekatan justru hilang.
YOLO_IOU = float(os.getenv("YOLO_IOU", "0.7"))
# Batas jumlah kotak per gambar supaya tidak membanjiri overlay/suara.
YOLO_MAX_DET = int(os.getenv("YOLO_MAX_DET", "50"))
# NMS tanpa memperhatikan kelas (menekan kotak tumpang tindih antar kelas). 1/0.
YOLO_AGNOSTIC_NMS = env_bool("YOLO_AGNOSTIC_NMS", False)
# Thread CPU untuk inferensi torch. 0 = pakai bawaan torch (biasanya optimal).
YOLO_THREADS = int(os.getenv("YOLO_THREADS", "0"))
# Muat model lebih awal saat startup agar request /detect pertama tidak lambat.
YOLO_PRELOAD = env_bool("YOLO_PRELOAD", True)

if YOLO_THREADS > 0:
    try:
        import torch

        torch.set_num_threads(YOLO_THREADS)
        logger.info("thread torch diatur ke %d", YOLO_THREADS)
    except Exception:  # pragma: no cover - torch selalu ada via ultralytics
        logger.warning("tidak dapat mengatur thread torch")

OLLAMA_URL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434").rstrip("/")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen2.5vl:3b")
OLLAMA_TIMEOUT = int(os.getenv("OLLAMA_TIMEOUT", "120"))
OLLAMA_SEED = int(os.getenv("OLLAMA_SEED", "42"))
MAX_BYTES = 8 * 1024 * 1024

# Pemilihan penyedia vision: "auto" (Gemini bila siap, jika tidak Ollama),
# "gemini" (utamakan Gemini), atau "ollama".
AI_VISION_PROVIDER = os.getenv("AI_VISION_PROVIDER", "auto").strip().lower()
AI_VISION_FALLBACK = env_bool("AI_VISION_FALLBACK", True)
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "").strip()
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "").strip()
# Model Gemini cadangan (dipisah koma). Dipakai bila model utama kena kuota/error,
# mis. "gemini-3.5-flash-lite". Setelah semua model Gemini gagal barulah Ollama.
GEMINI_FALLBACK_MODELS = [
    name.strip() for name in os.getenv("GEMINI_FALLBACK_MODELS", "").split(",") if name.strip()
]
GEMINI_TIMEOUT = int(os.getenv("GEMINI_TIMEOUT", "30"))
GEMINI_MAX_OUTPUT_TOKENS = int(os.getenv("GEMINI_MAX_OUTPUT_TOKENS", "256"))
GEMINI_JSON_MODE = env_bool("GEMINI_JSON_MODE", True)
# Kendali "thinking" Gemini. Kosong = pakai bawaan model. Isi level LOW/MEDIUM/HIGH
# atau anggaran token (mis. 0) untuk menekan latensi pada mode realtime.
GEMINI_THINKING_LEVEL = os.getenv("GEMINI_THINKING_LEVEL", "").strip().upper()
GEMINI_THINKING_BUDGET = os.getenv("GEMINI_THINKING_BUDGET", "").strip()
# Coba ulang error Gemini yang bersifat sementara (5xx/kuota/timeout) sebelum
# beralih ke Ollama. Lonjakan beban Google biasanya pulih dalam hitungan detik.
GEMINI_RETRIES = int(os.getenv("GEMINI_RETRIES", "2"))
GEMINI_RETRY_DELAY = float(os.getenv("GEMINI_RETRY_DELAY", "1.5"))

# Resolusi & kualitas gambar yang dikirim ke model vision. Tidak memperkecil
# berlebihan; hanya menurunkan sisi terpanjang bila melebihi batas agar objek
# kecil tetap punya detail.
VISION_MAX_SIDE = int(os.getenv("VISION_MAX_SIDE", "1024"))
VISION_JPEG_QUALITY = int(os.getenv("VISION_JPEG_QUALITY", "85"))
LOW_LIGHT_THRESHOLD = float(os.getenv("LOW_LIGHT_THRESHOLD", "60"))
YOLO_HINT_CONF = float(os.getenv("YOLO_HINT_CONF", "0.35"))
MAX_HINTS = 5

VISION_HEADER = (
    "Anda adalah asisten penglihatan untuk pengguna tunanetra. "
    "Deskripsikan HANYA apa yang benar-benar terlihat pada gambar kamera ini. "
    "Jawab dalam bahasa Indonesia, maksimal 2 kalimat, singkat dan natural untuk dibacakan."
)

VISION_RULES = (
    "- Sebutkan hanya objek atau ciri yang punya bukti visual jelas. Jangan menebak.\n"
    "- Jika ada banyak benda, sebutkan paling banyak 3 benda yang paling jelas dan "
    "paling penting saja; jangan membaca daftar panjang.\n"
    "- Utamakan benda yang paling dekat atau paling besar di gambar.\n"
    "- Bila jelas dan membantu, sebutkan posisi objek utama secara singkat: "
    "kiri, tengah, atau kanan.\n"
    "- Jangan menyebut merek, tulisan, angka, warna isi, isi wadah, jarak, arah, "
    "atau kondisi (rusak/bersih/bocor) yang tidak bisa dipastikan dari gambar.\n"
    "- Bedakan benda dari bentuk yang terlihat: gelas plastik sekali pakai melebar "
    "di bagian atas dan tipis; botol air tinggi dengan leher sempit dan tutup; "
    "cangkir atau mug lebih pendek dan sering berpegangan; remote control berupa "
    "balok pipih memanjang dengan banyak tombol kecil.\n"
    "- Jika objek tidak jelas, katakan terus terang, contoh: "
    '"Saya melihat benda kecil, tetapi belum bisa memastikan jenisnya."\n'
    "- Jangan mengarang. Lebih baik menyatakan tidak yakin daripada memberi jawaban salah."
)

model = None
model_lock = threading.Lock()

gemini_client = None
gemini_lock = threading.Lock()


class VisionServiceError(Exception):
    """Kesalahan penyedia vision dengan pesan publik yang aman dan kode HTTP."""

    def __init__(
        self,
        status_code: int,
        public_detail: str,
        log_message: str,
        retryable: bool = False,
        api_code: int | None = None,
    ):
        super().__init__(log_message)
        self.status_code = status_code
        self.public_detail = public_detail
        self.log_message = log_message
        self.retryable = retryable
        self.api_code = api_code


def get_model():
    global model
    if model is None:
        with model_lock:
            if model is None:
                logger.info("memuat model YOLO %s", MODEL_NAME)
                model = YOLO(MODEL_NAME)
    return model


def gemini_configured() -> bool:
    return bool(GENAI_AVAILABLE and GEMINI_API_KEY and GEMINI_MODEL)


def get_gemini_client():
    global gemini_client
    if gemini_client is None:
        with gemini_lock:
            if gemini_client is None:
                options = genai_types.HttpOptions(timeout=GEMINI_TIMEOUT * 1000)
                gemini_client = genai_sdk.Client(api_key=GEMINI_API_KEY, http_options=options)
                logger.info("klien Gemini disiapkan (model=%s timeout=%ss)", GEMINI_MODEL, GEMINI_TIMEOUT)
    return gemini_client


def choose_provider() -> tuple[str, str | None]:
    """Pilih penyedia vision dan alasan bila menyimpang dari preferensi."""
    if AI_VISION_PROVIDER == "ollama":
        return "ollama", None
    if AI_VISION_PROVIDER == "gemini":
        if gemini_configured():
            return "gemini", None
        return "ollama", "Gemini belum dikonfigurasi; memakai Ollama."
    if gemini_configured():
        return "gemini", None
    return "ollama", None


async def read_image(upload: UploadFile) -> Image.Image:
    data = await upload.read()
    if not data:
        raise HTTPException(status_code=400, detail="File gambar kosong.")
    if len(data) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="Ukuran gambar maksimum 8 MB.")
    try:
        image = Image.open(io.BytesIO(data)).convert("RGB")
        image.load()
        return image
    except (UnidentifiedImageError, OSError):
        raise HTTPException(status_code=400, detail="File bukan gambar yang valid.")


def run_yolo(image: Image.Image, conf: float | None = None) -> list[dict]:
    """Jalankan YOLO dan kembalikan daftar kotak. Blocking; panggil via threadpool."""
    if conf is None:
        conf = YOLO_CONF
    result = get_model().predict(
        image,
        imgsz=YOLO_IMGSZ,
        conf=conf,
        iou=YOLO_IOU,
        max_det=YOLO_MAX_DET,
        agnostic_nms=YOLO_AGNOSTIC_NMS,
        verbose=False,
    )[0]
    boxes = []
    for box in result.boxes:
        class_id = int(box.cls[0].item())
        label = result.names.get(class_id, str(class_id))
        confidence = float(box.conf[0].item())
        x1, y1, x2, y2 = [round(float(v), 1) for v in box.xyxy[0].tolist()]
        boxes.append({
            "label": label,
            "confidence": round(confidence, 3),
            "box": [x1, y1, x2, y2],
        })
    return boxes


def yolo_hints(boxes: list[dict]) -> list[dict]:
    """Ringkas label YOLO jadi petunjuk lemah (label + confidence tertinggi)."""
    best: dict[str, float] = {}
    for box in boxes:
        if box["confidence"] < YOLO_HINT_CONF:
            continue
        label = box["label"]
        best[label] = max(best.get(label, 0.0), box["confidence"])
    ordered = sorted(best.items(), key=lambda item: item[1], reverse=True)[:MAX_HINTS]
    return [{"label": label, "confidence": round(conf, 2)} for label, conf in ordered]


def prepare_for_vision(image: Image.Image) -> tuple[bytes, dict]:
    """Siapkan JPEG untuk model vision tanpa merusak rasio atau memperkecil berlebihan."""
    working = image.convert("RGB")
    stats = ImageStat.Stat(working.convert("L"))
    brightness = float(stats.mean[0]) if stats.mean else 0.0

    enhanced = False
    if brightness < LOW_LIGHT_THRESHOLD:
        working = ImageOps.autocontrast(working, cutoff=1)
        enhanced = True

    if max(working.size) > VISION_MAX_SIDE:
        working.thumbnail((VISION_MAX_SIDE, VISION_MAX_SIDE), Image.LANCZOS)

    buffer = io.BytesIO()
    working.save(buffer, format="JPEG", quality=VISION_JPEG_QUALITY)

    meta = {
        "source_width": image.width,
        "source_height": image.height,
        "sent_width": working.width,
        "sent_height": working.height,
        "brightness": round(brightness, 1),
        "low_light_enhanced": enhanced,
    }
    return buffer.getvalue(), meta


def hints_block(hints: list[dict], meta: dict) -> str:
    """Bagian prompt berisi petunjuk YOLO (lemah) dan catatan cahaya rendah."""
    text = ""
    if hints:
        hint_text = ", ".join(f"{h['label']} (conf {h['confidence']})" for h in hints)
        text += (
            "\n\nPetunjuk dari pendeteksi objek (hanya dugaan lemah, bisa salah, "
            "jangan dipaksakan bila bertentangan dengan penglihatan Anda): " + hint_text + "."
        )
    if meta.get("low_light_enhanced"):
        text += "\n\nCatatan: gambar aslinya cukup gelap, jadi sebagian detail mungkin tidak terlihat."
    return text


def build_prompt(hints: list[dict], meta: dict) -> str:
    """Prompt teks biasa untuk Ollama."""
    prompt = f"{VISION_HEADER}\n\nAturan:\n{VISION_RULES}"
    prompt += hints_block(hints, meta)
    prompt += "\n\nBerikan deskripsi sekarang."
    return prompt


def build_gemini_system(json_mode: bool) -> str:
    system = f"{VISION_HEADER}\n\nAturan:\n{VISION_RULES}"
    if json_mode:
        system += (
            "\n\nBalas dengan JSON valid saja tanpa teks tambahan, dengan skema: "
            '{"description": string, "main_objects": [string], "other_objects": [string], '
            '"uncertain": boolean}. '
            "Isi description dengan maksimal 2 kalimat bahasa Indonesia. "
            "main_objects berisi paling banyak 3 objek yang paling jelas. "
            "uncertain bernilai true bila gambar gelap, buram, atau objek tidak dapat dipastikan."
        )
    return system


def build_gemini_user(hints: list[dict], meta: dict) -> str:
    return (
        "Analisis gambar kamera berikut untuk pengguna tunanetra."
        + hints_block(hints, meta)
    )


def clean_object_list(value, limit: int = 5) -> list[str]:
    """Normalisasi daftar objek dari JSON model jadi daftar string unik."""
    result: list[str] = []
    if isinstance(value, list):
        for item in value:
            if isinstance(item, dict):
                name = item.get("name") or item.get("label") or item.get("object")
            else:
                name = item
            if name is None:
                continue
            name = str(name).strip()
            if name and name.lower() not in [existing.lower() for existing in result]:
                result.append(name)
            if len(result) >= limit:
                break
    elif isinstance(value, str) and value.strip():
        result.append(value.strip())
    return result


def parse_vision_json(text: str) -> dict:
    """Ubah balasan model jadi struktur konsisten; jatuh ke teks biasa bila gagal."""
    fallback = {
        "description": (text or "").strip(),
        "main_objects": [],
        "other_objects": [],
        "uncertain": None,
        "structured": False,
    }
    cleaned = (text or "").strip()
    if not cleaned:
        return fallback
    if cleaned.startswith("```"):
        cleaned = re.sub(r"^```[a-zA-Z0-9]*\s*", "", cleaned)
        cleaned = re.sub(r"\s*```$", "", cleaned)
    try:
        data = json.loads(cleaned)
    except (ValueError, TypeError):
        return fallback
    if not isinstance(data, dict):
        return fallback
    description = str(data.get("description") or "").strip()
    if not description:
        return fallback
    uncertain = data.get("uncertain")
    return {
        "description": description,
        "main_objects": clean_object_list(data.get("main_objects")),
        "other_objects": clean_object_list(data.get("other_objects")),
        "uncertain": bool(uncertain) if uncertain is not None else None,
        "structured": True,
    }


def extract_gemini_text(response) -> tuple[str, str | None]:
    """Ambil teks balasan Gemini beserta finish_reason (bila ada)."""
    finish_reason = None
    candidates = getattr(response, "candidates", None) or []
    if candidates:
        reason = getattr(candidates[0], "finish_reason", None)
        if reason is not None:
            finish_reason = getattr(reason, "name", str(reason))

    text = getattr(response, "text", None)
    if not text:
        chunks = []
        for candidate in candidates:
            content = getattr(candidate, "content", None)
            for part in (getattr(content, "parts", None) or []):
                if getattr(part, "text", None):
                    chunks.append(part.text)
        text = "".join(chunks)
    return (text or "").strip(), finish_reason


def map_gemini_error(exc: Exception) -> VisionServiceError:
    code = int(getattr(exc, "code", 0) or 0)
    detail = ""
    response_json = getattr(exc, "response_json", None)
    if isinstance(response_json, dict):
        error_body = response_json.get("error")
        if isinstance(error_body, dict):
            detail = str(error_body.get("message") or "")
        detail = detail or str(response_json.get("message") or "")
    logger.warning("Gemini API error kode=%s pesan=%s", code, detail or getattr(exc, "message", ""))

    if code in (401, 403):
        return VisionServiceError(502, "Kunci GEMINI_API_KEY tidak valid atau tidak punya akses.", "gemini auth", api_code=code)
    if code == 429:
        return VisionServiceError(429, "Kuota Gemini tercapai. Coba lagi beberapa saat lagi.", "gemini quota", api_code=code)
    if code == 404:
        return VisionServiceError(502, "Model Gemini tidak ditemukan. Periksa GEMINI_MODEL.", "gemini model not found", api_code=code)
    if code == 400:
        return VisionServiceError(502, "Permintaan ke Gemini tidak valid. Periksa nama model dan input.", "gemini bad request", api_code=code)
    if code >= 500:
        return VisionServiceError(502, "Server Gemini sedang bermasalah. Coba lagi.", "gemini server error", retryable=True, api_code=code)
    return VisionServiceError(502, "Gagal menganalisis gambar dengan Gemini.", "gemini error", api_code=code)


def map_gemini_transport_error(exc: Exception) -> VisionServiceError:
    message = str(exc).lower()
    if "timeout" in message or "timed out" in message:
        return VisionServiceError(504, "Analisis Gemini melebihi batas waktu. Coba lagi.", "gemini timeout", retryable=True)
    return VisionServiceError(
        503,
        "Tidak dapat menghubungi Gemini. Periksa koneksi internet.",
        f"gemini transport {type(exc).__name__}",
        retryable=True,
    )


def gemini_generate(model: str, prompt: str, image_part, json_mode: bool):
    config_kwargs = {
        "system_instruction": build_gemini_system(json_mode),
        "temperature": 0.1,
        "top_p": 0.9,
        "max_output_tokens": GEMINI_MAX_OUTPUT_TOKENS,
    }
    if json_mode:
        config_kwargs["response_mime_type"] = "application/json"
    thinking = {}
    if GEMINI_THINKING_LEVEL:
        thinking["thinking_level"] = GEMINI_THINKING_LEVEL
    if GEMINI_THINKING_BUDGET != "":
        try:
            thinking["thinking_budget"] = int(GEMINI_THINKING_BUDGET)
        except ValueError:
            logger.warning("GEMINI_THINKING_BUDGET bukan angka; diabaikan")
    if thinking and hasattr(genai_types, "ThinkingConfig"):
        config_kwargs["thinking_config"] = genai_types.ThinkingConfig(**thinking)
    config = genai_types.GenerateContentConfig(**config_kwargs)
    return get_gemini_client().models.generate_content(
        model=model,
        contents=[prompt, image_part],
        config=config,
    )


def gemini_generate_with_retries(model: str, prompt: str, image_part, json_mode: bool):
    """Panggil Gemini dengan percobaan ulang terbatas untuk error sementara."""
    last_error: VisionServiceError | None = None
    for attempt in range(GEMINI_RETRIES + 1):
        try:
            return gemini_generate(model, prompt, image_part, json_mode)
        except genai_errors.APIError as exc:
            error = map_gemini_error(exc)
        except Exception as exc:
            error = map_gemini_transport_error(exc)
        last_error = error
        if error.retryable and attempt < GEMINI_RETRIES:
            delay = GEMINI_RETRY_DELAY * (attempt + 1)
            logger.warning(
                "Gemini error sementara (%s), coba ulang %d/%d dalam %.1fs",
                error.log_message, attempt + 1, GEMINI_RETRIES, delay,
            )
            time.sleep(delay)
            continue
        raise error
    raise last_error or VisionServiceError(502, "Gagal menganalisis gambar dengan Gemini.", "gemini unknown")


def call_gemini(encoded_bytes: bytes, hints: list[dict], meta: dict) -> dict:
    """Panggil Gemini Vision. Blocking; panggil via threadpool. Melempar VisionServiceError."""
    if not GENAI_AVAILABLE:
        raise VisionServiceError(503, "Pustaka google-genai belum terpasang di layanan Python.", "google-genai tidak tersedia")
    if not GEMINI_API_KEY:
        raise VisionServiceError(503, "GEMINI_API_KEY belum diisi.", "GEMINI_API_KEY kosong")
    if not GEMINI_MODEL:
        raise VisionServiceError(503, "GEMINI_MODEL belum diisi.", "GEMINI_MODEL kosong")

    prompt = build_gemini_user(hints, meta)
    image_part = genai_types.Part.from_bytes(data=encoded_bytes, mime_type="image/jpeg")

    models = [GEMINI_MODEL] + [name for name in GEMINI_FALLBACK_MODELS if name != GEMINI_MODEL]
    response = None
    used_model = GEMINI_MODEL
    last_error: VisionServiceError | None = None
    for index, model in enumerate(models):
        attempts = [True, False] if GEMINI_JSON_MODE else [False]
        for json_mode in attempts:
            try:
                response = gemini_generate_with_retries(model, prompt, image_part, json_mode)
                used_model = model
                break
            except VisionServiceError as error:
                last_error = error
                if json_mode and error.api_code == 400:
                    logger.warning("Model %s menolak mode JSON, mencoba mode teks biasa.", model)
                    continue
                break
        if response is not None:
            break
        if index < len(models) - 1:
            logger.warning(
                "Gemini model %s gagal (%s); mencoba model cadangan.",
                model, last_error.log_message if last_error else "unknown",
            )

    if response is None:
        raise last_error or VisionServiceError(502, "Gagal menganalisis gambar dengan Gemini.", "gemini unknown")

    text, finish_reason = extract_gemini_text(response)
    if not text:
        if finish_reason == "SAFETY":
            raise VisionServiceError(422, "Model menolak menganalisis gambar ini.", "gemini safety block")
        if finish_reason == "MAX_TOKENS":
            raise VisionServiceError(502, "Balasan Gemini terpotong. Coba lagi.", "gemini max tokens")
        raise VisionServiceError(502, "Gemini tidak mengembalikan deskripsi.", f"gemini empty finish={finish_reason}")

    parsed = parse_vision_json(text)
    parsed["model"] = used_model
    return parsed


def call_ollama(encoded: str, hints: list[dict], meta: dict) -> dict:
    """Panggil Ollama. Blocking; panggil via threadpool. Melempar VisionServiceError."""
    prompt = build_prompt(hints, meta)
    payload = {
        "model": OLLAMA_MODEL,
        "messages": [
            {
                "role": "user",
                "content": prompt,
                "images": [encoded],
            }
        ],
        "stream": False,
        "options": {
            "temperature": 0.1,
            "top_p": 0.9,
            "seed": OLLAMA_SEED,
            "repeat_penalty": 1.1,
            "num_predict": 120,
        },
    }

    try:
        response = requests.post(f"{OLLAMA_URL}/api/chat", json=payload, timeout=OLLAMA_TIMEOUT)
        response.raise_for_status()
        text = response.json().get("message", {}).get("content", "").strip()
        if not text:
            raise ValueError("Model tidak mengembalikan deskripsi.")
        return {
            "description": text,
            "main_objects": [],
            "other_objects": [],
            "uncertain": None,
            "structured": False,
        }
    except requests.exceptions.ConnectionError:
        raise VisionServiceError(
            503,
            "Ollama tidak berjalan. Jalankan aplikasi Ollama terlebih dahulu.",
            "ollama connection error",
        )
    except requests.exceptions.Timeout:
        raise VisionServiceError(
            504,
            "Analisis gambar terlalu lama. Coba lagi atau gunakan model lebih ringan.",
            "ollama timeout",
        )
    except (requests.RequestException, ValueError) as exc:
        raise VisionServiceError(502, f"Analisis vision gagal: {exc}", f"ollama error {exc}")


@app.get("/health")
def health():
    provider, reason = choose_provider()
    active_model = GEMINI_MODEL if provider == "gemini" else OLLAMA_MODEL
    return {
        "status": "ok",
        "vision_provider": provider,
        "vision_model": active_model,
        "provider_reason": reason,
        "yolo_model": MODEL_NAME,
        "yolo_imgsz": YOLO_IMGSZ,
        "yolo_conf": YOLO_CONF,
        "yolo_iou": YOLO_IOU,
        "yolo_max_det": YOLO_MAX_DET,
        "ollama_model": OLLAMA_MODEL,
        "gemini_model": GEMINI_MODEL or None,
        "gemini_fallback_models": GEMINI_FALLBACK_MODELS,
        "gemini_configured": gemini_configured(),
        "gemini_sdk_available": GENAI_AVAILABLE,
        "vision_max_side": VISION_MAX_SIDE,
    }


@app.post("/detect")
async def detect(file: UploadFile = File(...)):
    started = time.perf_counter()
    image = await read_image(file)
    try:
        boxes = await run_in_threadpool(run_yolo, image)
    except Exception as exc:
        logger.exception("detect gagal")
        raise HTTPException(status_code=500, detail=f"Deteksi gagal: {exc}")

    counts = Counter(box["label"] for box in boxes)
    elapsed = time.perf_counter() - started
    logger.info(
        "detect selesai durasi=%.3fs kotak=%d label=%s",
        elapsed, len(boxes), dict(counts),
    )

    return {
        "image_width": image.width,
        "image_height": image.height,
        "total": len(boxes),
        "boxes": boxes,
        "counts": dict(counts),
        "model": MODEL_NAME,
        "elapsed_ms": round(elapsed * 1000),
        "note": "Deteksi YOLO pretrained; kategori di luar kelas model mungkin tidak terdeteksi.",
    }


@app.post("/describe")
async def describe(file: UploadFile = File(...)):
    started = time.perf_counter()
    image = await read_image(file)

    # Petunjuk YOLO dipakai hanya sebagai bukti pendukung, bukan kebenaran mutlak.
    hints: list[dict] = []
    try:
        boxes = await run_in_threadpool(run_yolo, image)
        hints = yolo_hints(boxes)
    except Exception:
        logger.exception("YOLO gagal saat menyiapkan petunjuk describe")

    encoded_bytes, meta = await run_in_threadpool(prepare_for_vision, image)
    provider, provider_reason = choose_provider()

    logger.info(
        "describe mulai penyedia=%s gambar=%sx%s terkirim=%sx%s terang=%.1f enhanced=%s petunjuk=%s",
        provider, meta["source_width"], meta["source_height"],
        meta["sent_width"], meta["sent_height"],
        meta["brightness"], meta["low_light_enhanced"],
        [h["label"] for h in hints],
    )

    result: dict | None = None
    active_model = ""
    fallback_used = False
    fallback_reason = provider_reason

    if provider == "gemini":
        try:
            result = await run_in_threadpool(call_gemini, encoded_bytes, hints, meta)
            active_model = result.get("model") or GEMINI_MODEL
        except VisionServiceError as exc:
            logger.warning("describe: Gemini gagal (%s)", exc.log_message)
            if not AI_VISION_FALLBACK:
                raise HTTPException(status_code=exc.status_code, detail=exc.public_detail)
            logger.info("describe: beralih ke Ollama sebagai cadangan")
            fallback_used = True
            fallback_reason = exc.public_detail
            encoded = base64.b64encode(encoded_bytes).decode("ascii")
            try:
                result = await run_in_threadpool(call_ollama, encoded, hints, meta)
                provider = "ollama"
                active_model = OLLAMA_MODEL
            except VisionServiceError as ollama_exc:
                logger.error("describe: Ollama juga gagal (%s)", ollama_exc.log_message)
                raise HTTPException(
                    status_code=ollama_exc.status_code,
                    detail=f"{exc.public_detail} Cadangan Ollama juga gagal: {ollama_exc.public_detail}",
                )
    else:
        encoded = base64.b64encode(encoded_bytes).decode("ascii")
        try:
            result = await run_in_threadpool(call_ollama, encoded, hints, meta)
            active_model = OLLAMA_MODEL
        except VisionServiceError as exc:
            logger.error("describe: Ollama gagal (%s)", exc.log_message)
            raise HTTPException(status_code=exc.status_code, detail=exc.public_detail)

    elapsed = time.perf_counter() - started
    logger.info(
        "describe selesai penyedia=%s model=%s fallback=%s durasi=%.2fs panjang=%d",
        provider, active_model, fallback_used, elapsed, len(result.get("description", "")),
    )

    return {
        "description": result["description"],
        "main_objects": result.get("main_objects", []),
        "other_objects": result.get("other_objects", []),
        "uncertain": result.get("uncertain"),
        "structured": result.get("structured", False),
        "provider": provider,
        "model": active_model,
        "fallback_used": fallback_used,
        "fallback_reason": fallback_reason,
        "hints": hints,
        "elapsed_ms": round(elapsed * 1000),
    }
