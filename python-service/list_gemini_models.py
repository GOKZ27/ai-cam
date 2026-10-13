"""Diagnostik: daftar model Gemini yang tersedia untuk kunci Anda.

Tujuan: membantu memilih `GEMINI_MODEL` yang benar-benar tersedia untuk kunci
GEMINI_API_KEY Anda, sehingga aplikasi tidak memakai nama model yang dikarang.

Cara pakai:
  1. Pastikan `google-genai` terpasang di venv layanan Python.
  2. Setel kunci salah satu cara:
       - environment variable:  GEMINI_API_KEY=...
       - atau file .env di folder ini:  GEMINI_API_KEY=...
  3. Jalankan:
       python list_gemini_models.py
     Lalu salin salah satu nama model yang mendukung gambar ke GEMINI_MODEL.

Catatan keamanan:
  - Skrip ini TIDAK pernah mencetak nilai kunci API.
  - Skrip ini tidak mengunggah gambar dan tidak menyimpan hasil ke file.
  - Rekomendasi di bawah hanya dari metadata yang dikembalikan API, bukan
    jaminan kualitas. Penilaian akhir tetap milik Anda.
"""

import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))


def load_env_file(path):
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
    except OSError as exc:
        print(f"Peringatan: tidak dapat membaca {path}: {exc}")


def supported_actions(model):
    actions = getattr(model, "supported_actions", None)
    if not actions:
        return []
    return [str(action) for action in actions]


def can_generate(actions):
    return any(action.lower() == "generatecontent" for action in actions)


def looks_multimodal(name):
    lowered = name.lower()
    blocked = ("embedding", "aqa", "imagen", "tts", "audio", "live", "veo")
    return not any(word in lowered for word in blocked)


def recommended(name):
    lowered = name.lower()
    score = 0
    if "flash" in lowered:
        score += 3
    if any(tag in lowered for tag in ("2.5", "2.0", "3.")):
        score += 2
    if "thinking" in lowered or "exp" in lowered or "preview" in lowered:
        score -= 2
    if "pro" in lowered:
        score -= 1
    return score


def main():
    load_env_file(os.path.join(HERE, ".env"))

    api_key = os.getenv("GEMINI_API_KEY", "").strip()
    if not api_key:
        print("GEMINI_API_KEY belum diisi.")
        print("Setel lewat environment variable atau file .env di folder python-service.")
        print("Skrip ini tidak dapat melanjutkan tanpa kunci, dan tidak akan menebak model.")
        return 2

    try:
        from google import genai  # noqa: WPS433 (import lokal agar pesan jelas)
    except Exception as exc:  # pragma: no cover - tergantung lingkungan
        print("Pustaka google-genai belum terpasang.")
        print("Jalankan di venv layanan Python:")
        print(r"  .\.venv\Scripts\python.exe -m pip install ""google-genai>=1.0""")
        print(f"Detail: {exc}")
        return 3

    try:
        client = genai.Client(api_key=api_key)
        models = list(client.models.list())
    except Exception as exc:  # pragma: no cover - perlu kunci/jaringan nyata
        print("Gagal mengambil daftar model dari Gemini.")
        print("Periksa kunci API dan koneksi internet Anda.")
        print(f"Detail: {type(exc).__name__}: {exc}")
        return 4

    if not models:
        print("Tidak ada model yang dikembalikan untuk kunci ini.")
        return 5

    print(f"Ditemukan {len(models)} model untuk kunci Anda.\n")
    rows = []
    for model in models:
        name = getattr(model, "name", "") or ""
        display = getattr(model, "display_name", "") or ""
        actions = supported_actions(model)
        if not can_generate(actions):
            continue
        rows.append((name, display, actions, model))

    if not rows:
        print("Tidak ada model yang mendukung generateContent.")
        return 5

    rows.sort(key=lambda row: row[0])
    print(f"{'Nama model':<44} {'Aksi':<28} {'Token masuk':>11}")
    print("-" * 90)
    for name, _display, actions, model in rows:
        token_limit = getattr(model, "input_token_limit", None) or "-"
        print(f"{name:<44} {','.join(actions):<28} {str(token_limit):>11}")

    print("\nKandidat (metadata saja; pilih yang mendukung gambar di konsol Google):")
    candidates = [row[0] for row in rows if looks_multimodal(row[0])]
    candidates.sort(key=lambda name: recommended(name), reverse=True)
    for name in candidates[:5]:
        print(f"  - {name}")
    print(
        "\nModel yang mendukung gambar biasanya bernama '*vision*' atau keluarga "
        "'flash'/'2.x'. Verifikasi di dokumentasi Google, lalu setel GEMINI_MODEL."
    )
    print("Skrip ini tidak menyimpan hasil dan tidak mencetak kunci API.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
