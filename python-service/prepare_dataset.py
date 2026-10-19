"""Siapkan dataset YOLO dari anotasi 'Ajar Objek'.

Membaca anotasi mentah dari kamera:
    datasets/objek/raw/images/*.jpg
    datasets/objek/raw/labels/*.txt
    datasets/objek/classes.txt

Lalu memecahnya menjadi train/val dan menulis dataset.yaml untuk Ultralytics:
    datasets/objek/images/train/*.jpg
    datasets/objek/images/val/*.jpg
    datasets/objek/labels/train/*.txt
    datasets/objek/labels/val/*.txt
    datasets/objek/data.yaml

Pakai:
    .\\.venv\\Scripts\\python.exe prepare_dataset.py                 # val 20%
    .\\.venv\\Scripts\\python.exe prepare_dataset.py --val-frac 0.15
    .\\.venv\\Scripts\\python.exe prepare_dataset.py --clean          # hapus split lama

Setelah ini, latih model (lihat train_yolo.py atau latih di Google Colab).
"""

import argparse
import glob
import os
import random
import shutil

SERVICE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_DIR = os.getenv("DATASET_DIR", os.path.join(SERVICE_DIR, "datasets"))
ANNOTATE_DIR = os.path.join(DATASET_DIR, "objek")
RAW_IMAGES = os.path.join(ANNOTATE_DIR, "raw", "images")
RAW_LABELS = os.path.join(ANNOTATE_DIR, "raw", "labels")
CLASSES_FILE = os.path.join(ANNOTATE_DIR, "classes.txt")
IMAGE_EXTS = (".jpg", ".jpeg", ".png", ".webp")


def read_classes() -> list[str]:
    if not os.path.exists(CLASSES_FILE):
        return []
    with open(CLASSES_FILE, encoding="utf-8") as handle:
        return [line.strip() for line in handle if line.strip()]


def collect_pairs() -> list[tuple[str, str]]:
    pairs = []
    for path in sorted(glob.glob(os.path.join(RAW_IMAGES, "*"))):
        stem, ext = os.path.splitext(os.path.basename(path))
        if ext.lower() not in IMAGE_EXTS:
            continue
        label = os.path.join(RAW_LABELS, stem + ".txt")
        if os.path.exists(label) and os.path.getsize(label) > 0:
            pairs.append((path, label))
    return pairs


def reset_split() -> None:
    for kind in ("images", "labels"):
        for part in ("train", "val"):
            target = os.path.join(ANNOTATE_DIR, kind, part)
            if os.path.isdir(target):
                shutil.rmtree(target)


def ensure_dirs() -> None:
    for kind in ("images", "labels"):
        for part in ("train", "val"):
            os.makedirs(os.path.join(ANNOTATE_DIR, kind, part), exist_ok=True)


def copy_split(pairs: list[tuple[str, str]], part: str) -> None:
    for image_path, label_path in pairs:
        image_name = os.path.basename(image_path)
        stem = os.path.splitext(image_name)[0]
        shutil.copy2(image_path, os.path.join(ANNOTATE_DIR, "images", part, image_name))
        shutil.copy2(label_path, os.path.join(ANNOTATE_DIR, "labels", part, stem + ".txt"))


def write_yaml(classes: list[str]) -> str:
    lines = [
        f"path: {ANNOTATE_DIR}",
        "train: images/train",
        "val: images/val",
        f"nc: {len(classes)}",
        "names:",
    ]
    lines.extend(f"  - {name}" for name in classes)
    yaml_path = os.path.join(ANNOTATE_DIR, "data.yaml")
    with open(yaml_path, "w", encoding="utf-8") as handle:
        handle.write("\n".join(lines) + "\n")
    return yaml_path


def main() -> int:
    parser = argparse.ArgumentParser(description="Siapkan dataset YOLO dari anotasi Ajar Objek.")
    parser.add_argument("--val-frac", type=float, default=0.2, help="Proporsi data validasi (0..1).")
    parser.add_argument("--seed", type=int, default=42, help="Benih acak untuk pemecahan.")
    parser.add_argument("--clean", action="store_true", help="Hapus folder split lama lebih dulu.")
    args = parser.parse_args()

    classes = read_classes()
    if not classes:
        print("Belum ada kelas. Tandai objek di aplikasi (tombol 'Ajar Objek') dulu.")
        return 1

    pairs = collect_pairs()
    if not pairs:
        print(f"Belum ada pasangan gambar+label di {RAW_IMAGES}.")
        return 1

    if args.clean:
        reset_split()
    ensure_dirs()

    random.Random(args.seed).shuffle(pairs)
    val_count = 1 if len(pairs) == 1 else max(1, round(len(pairs) * args.val_frac))
    val_count = min(val_count, len(pairs) - 1) if len(pairs) > 1 else 1
    val_pairs = pairs[:val_count]
    train_pairs = pairs[val_count:]

    copy_split(train_pairs, "train")
    copy_split(val_pairs, "val")
    yaml_path = write_yaml(classes)

    print(f"Kelas ({len(classes)}): {', '.join(classes)}")
    print(f"Total contoh: {len(pairs)}  ->  train {len(train_pairs)} / val {len(val_pairs)}")
    print(f"data.yaml: {yaml_path}")
    print("\nLatih model, mis.:")
    print(f"  python train_yolo.py --epochs 100")
    print("atau di Google Colab:")
    print(f"  from ultralytics import YOLO")
    print(f"  YOLO('yolo11n.pt').train(data='{yaml_path}', epochs=100, imgsz=640, batch=8)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
