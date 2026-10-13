"""Benchmark YOLO untuk memilih model & ukuran inferensi yang pas di mesin ini.

Pakai:
    .\\.venv\\Scripts\\python.exe bench_yolo.py                 # gambar sintetis
    .\\.venv\\Scripts\\python.exe bench_yolo.py foto.jpg        # gambar nyata
    .\\.venv\\Scripts\\python.exe bench_yolo.py foto.jpg 5        # 5 kali per konfigurasi

Hasilnya: waktu inferensi (ms) per kombinasi model x imgsz. Pilih nilai terbesar
yang masih nyaman; interval deteksi aplikasi default 1200 ms (mode sibuk 3000 ms).
"""

import os
import sys
import time

import torch
from PIL import Image
from ultralytics import YOLO

MODELS = ["yolo11n.pt", "yolo11s.pt", "yolo11m.pt"]
IMGSZ_LIST = [640, 960]


def load_image(path: str | None) -> Image.Image:
    if path:
        return Image.open(path).convert("RGB")
    # Gambar sintetis 1280x720 (isi tidak memengaruhi waktu inferensi).
    return Image.fromarray((torch.rand(720, 1280, 3) * 255).byte().numpy())


def main() -> None:
    here = os.path.dirname(os.path.abspath(__file__))
    os.chdir(here)
    path = sys.argv[1] if len(sys.argv) > 1 else None
    runs = int(sys.argv[2]) if len(sys.argv) > 2 else 3
    if path and not os.path.exists(path):
        print(f"Gambar tidak ditemukan: {path}")
        raise SystemExit(1)

    torch.set_num_threads(os.cpu_count() or 4)
    image = load_image(path)
    source = path or "sintetis 1280x720"
    print(f"CPU={os.cpu_count()} thread={torch.get_num_threads()} gambar={source} runs={runs}\n")

    print(f"{'model':<12}{'imgsz':>7}{'terbaik':>10}{'rata2':>10}")
    for name in MODELS:
        if not os.path.exists(name):
            print(f"{name:<12}{'-':>7}{'(tidak ada)':>20}")
            continue
        model = YOLO(name)
        for imgsz in IMGSZ_LIST:
            model.predict(image, imgsz=imgsz, conf=0.35, verbose=False)  # warmup
            times = []
            for _ in range(runs):
                t = time.perf_counter()
                model.predict(image, imgsz=imgsz, conf=0.35, verbose=False)
                times.append((time.perf_counter() - t) * 1000)
            print(f"{name:<12}{imgsz:>7}{min(times):>9.0f}ms{sum(times) / len(times):>9.0f}ms")


if __name__ == "__main__":
    main()
