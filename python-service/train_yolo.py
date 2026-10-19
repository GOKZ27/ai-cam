"""Latih ulang model deteksi dari dataset 'Ajar Objek'.

PERINGATAN: mesin ini CPU-only (tanpa CUDA) dan RAM-nya terbatas, jadi training
lokal bisa sangat lambat. Untuk hasil wajar, latih di Google Colab (GPU gratis),
lalu unduh best.pt ke folder python-service/ dan set di .env:
    YOLO_CUSTOM_MODEL=custom.pt

Jalankan prepare_dataset.py dulu agar datasets/objek/data.yaml tersedia.
Butuh juga model dasar (mis. yolo11n.pt) yang diunduh otomatis.

Pakai:
    .\\.venv\\Scripts\\python.exe train_yolo.py --epochs 100
    .\\.venv\\Scripts\\python.exe train_yolo.py --model yolo11s.pt --imgsz 640 --batch 4 --deploy

--deploy menyalin hasil best.pt ke python-service/custom.pt.
"""

import argparse
import os
import shutil

SERVICE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_YAML = os.path.join(SERVICE_DIR, "datasets", "objek", "data.yaml")


def main() -> int:
    parser = argparse.ArgumentParser(description="Latih model YOLO custom dari anotasi Ajar Objek.")
    parser.add_argument("--data", default=DATASET_YAML, help="Path data.yaml.")
    parser.add_argument("--model", default="yolo11n.pt", help="Model dasar (pretrained).")
    parser.add_argument("--epochs", type=int, default=100)
    parser.add_argument("--imgsz", type=int, default=640)
    parser.add_argument("--batch", type=int, default=4)
    parser.add_argument("--device", default="cpu", help="'cpu', '0' (GPU), atau 'cuda'.")
    parser.add_argument("--name", default="objek", help="Nama folder hasil training.")
    parser.add_argument("--deploy", action="store_true", help="Salin best.pt ke python-service/custom.pt.")
    args = parser.parse_args()

    if not os.path.exists(args.data):
        print(f"data.yaml tidak ditemukan: {args.data}")
        print("Jalankan dulu: python prepare_dataset.py")
        return 1

    # Impor di sini agar pesan bantuan tetap muncul tanpa ultralytics terpasang.
    from ultralytics import YOLO

    print(f"Training: model={args.model} data={args.data} epochs={args.epochs} "
          f"imgsz={args.imgsz} batch={args.batch} device={args.device}")
    model = YOLO(args.model)
    results = model.train(
        data=args.data,
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        project=os.path.join(SERVICE_DIR, "runs"),
        name=args.name,
    )

    save_dir = getattr(results, "save_dir", None)
    best = os.path.join(str(save_dir), "weights", "best.pt") if save_dir else None
    if best and os.path.exists(best):
        print(f"\nModel terbaik: {best}")
        if args.deploy:
            target = os.path.join(SERVICE_DIR, "custom.pt")
            shutil.copy2(best, target)
            print(f"Disalin ke: {target}")
            print("Set di python-service/.env:  YOLO_CUSTOM_MODEL=custom.pt")
            print("Lalu restart layanan Python (uvicorn).")
    else:
        print("best.pt tidak ditemukan; periksa output training di atas.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
