"""Prosedur pengujian deskripsi vision AI Cam.

Tujuan: membandingkan keluaran /detect (YOLO) dan /describe (vision AI) pada
berbagai gambar uji yang berbeda, terutama objek yang sering tertukar.

Cara pakai:
  1. Jalankan layanan Python:  uvicorn main:app --port 8001
     dan pastikan Ollama aktif dengan model qwen2.5vl:3b (untuk cadangan),
     atau isi GEMINI_API_KEY dan GEMINI_MODEL di python-service/.env untuk
     memakai Gemini. Gunakan `python list_gemini_models.py` untuk memilih model.
     Perhatian: bila Gemini aktif, gambar uji dikirim ke cloud Google.
  2. Siapkan gambar uji di folder  python-service/test-images/
        aqua-gelas.jpg   (gelas plastik sekali pakai)
        botol-air.jpg    (botol air mineral)
        gelas-kosong.jpg (gelas kaca/cangkir kosong)
        remote.jpg       (remote control)
        kecil.jpg        (objek kecil / jauh)
        buram.jpg        (gambar blur / gelap / objek jauh)
      Format .jpg/.jpeg/.png/.webp didukung; semua gambar di folder akan diuji.
  3. Jalankan:
        python test_describe.py                 # uji semua gambar di folder
        python test_describe.py remote.jpg      # uji gambar tertentu


Catatan penting:
  - Skrip ini TIDAK menyimpan gambar dan tidak mencatat isi gambar.
  - Skrip ini tidak mengklaim peningkatan akurasi. Penilaian benar/salah harus
    dilakukan manusia dengan membandingkan deskripsi terhadap objek aslinya.
  - Jika gambar uji belum tersedia, skrip berhenti dengan instruksi, bukan
    menghasilkan klaim palsu.
"""

import glob
import os
import sys
import time

import requests

BASE_URL = os.getenv("AI_CAM_PYTHON_URL", "http://127.0.0.1:8001").rstrip("/")
IMAGE_EXTS = (".jpg", ".jpeg", ".png", ".webp")
HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_DIR = os.path.join(HERE, "test-images")


def collect(paths):
    if not paths:
        paths = [DEFAULT_DIR]
    files = []
    for path in paths:
        if os.path.isdir(path):
            for ext in IMAGE_EXTS:
                files.extend(sorted(glob.glob(os.path.join(path, "*" + ext))))
                files.extend(sorted(glob.glob(os.path.join(path, "*" + ext.upper()))))
        elif os.path.isfile(path):
            files.append(path)
    seen = set()
    ordered = []
    for path in files:
        if path not in seen:
            seen.add(path)
            ordered.append(path)
    return ordered


def post(endpoint, path):
    with open(path, "rb") as handle:
        started = time.perf_counter()
        response = requests.post(
            f"{BASE_URL}{endpoint}",
            files={"file": (os.path.basename(path), handle, "application/octet-stream")},
            timeout=180,
        )
        elapsed = time.perf_counter() - started
    try:
        data = response.json()
    except ValueError:
        data = {"detail": response.text}
    return response.status_code, data, elapsed


def main():
    files = collect(sys.argv[1:])
    if not files:
        print("Tidak ada gambar uji ditemukan.")
        print(f"Letakkan gambar di: {DEFAULT_DIR}")
        print("Nama yang disarankan: aqua-gelas.jpg, botol-air.jpg, gelas-kosong.jpg, remote.jpg, buram.jpg")
        return 1

    print(f"Layanan: {BASE_URL}")
    print(f"Menguji {len(files)} gambar\n")

    for path in files:
        name = os.path.basename(path)
        print("=" * 70)
        print(f"GAMBAR: {name}")

        try:
            status, data, elapsed = post("/detect", path)
            labels = ", ".join(f"{b['label']}:{b['confidence']}" for b in data.get("boxes", [])) or "(tidak ada)"
            print(f"  YOLO    status={status} durasi={elapsed:.2f}s -> {labels}")
        except requests.exceptions.RequestException as exc:
            print(f"  YOLO    GAGAL: {exc}")

        try:
            status, data, elapsed = post("/describe", path)
            description = data.get("description") or data.get("detail") or "(kosong)"
            hints = ", ".join(h["label"] for h in data.get("hints", [])) or "(tidak ada)"
            provider = data.get("provider", "?")
            model = data.get("model", "?")
            fallback = " (cadangan Ollama)" if data.get("fallback_used") else ""
            print(f"  VISION  status={status} durasi={elapsed:.2f}s penyedia={provider}{fallback} model={model} petunjuk=[{hints}]")
            print(f"  DESKRIPSI: {description}")
        except requests.exceptions.RequestException as exc:
            print(f"  VISION  GAGAL: {exc}")
        print()

    print("Catatan: nilai di atas adalah keluaran model, bukan jaminan benar.")
    print("Kesimpulan akurasi hanya boleh dibuat lewat penilaian manusia terhadap objek asli.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
