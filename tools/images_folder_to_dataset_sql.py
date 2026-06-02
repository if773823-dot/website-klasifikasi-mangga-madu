import argparse
from pathlib import Path

from PIL import Image, ImageStat


ALLOWED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
ALLOWED_LABELS = {"Mentah", "Setengah Matang", "Matang"}


def parse_args():
    parser = argparse.ArgumentParser(
        description="Convert a folder of labeled mango images into dataset_latih SQL inserts."
    )
    parser.add_argument("folder", help="Folder containing mango images.")
    parser.add_argument(
        "--label",
        default="Setengah Matang",
        choices=sorted(ALLOWED_LABELS),
        help="Label assigned to all images in the folder.",
    )
    parser.add_argument(
        "--output",
        default="database/import_dataset_setengah_matang.sql",
        help="Output SQL file path.",
    )
    parser.add_argument(
        "--recursive",
        action="store_true",
        help="Read images recursively from subfolders.",
    )
    return parser.parse_args()


def sql_string(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def mean_rgb(path):
    with Image.open(path) as image:
        image = image.convert("RGB").resize((256, 256))
        red, green, blue = ImageStat.Stat(image).mean
        return round(red, 4), round(green, 4), round(blue, 4)


def iter_images(folder, recursive):
    pattern = "**/*" if recursive else "*"
    for path in sorted(folder.glob(pattern)):
        if path.is_file() and path.suffix.lower() in ALLOWED_EXTENSIONS:
            yield path


def main():
    args = parse_args()
    folder = Path(args.folder)
    output_path = Path(args.output)

    if not folder.exists() or not folder.is_dir():
        raise SystemExit(f"Folder tidak ditemukan: {folder}")

    rows = []
    failed = []

    for image_path in iter_images(folder, args.recursive):
        try:
            red, green, blue = mean_rgb(image_path)
            rows.append((image_path.name, red, green, blue, args.label))
        except Exception as exc:
            failed.append((str(image_path), str(exc)))

    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", encoding="utf-8") as output:
        output.write("-- Generated from labeled image folder.\n\n")
        if rows:
            output.write(
                "INSERT INTO dataset_latih (nama_file, mean_red, mean_green, mean_blue, label_kelas, catatan)\nVALUES\n"
            )
            values = []
            for name, red, green, blue, label in rows:
                values.append(
                    f"({sql_string(name)}, {red}, {green}, {blue}, {sql_string(label)}, "
                    f"{sql_string('Diimpor dari folder gambar berlabel')})"
                )
            output.write(",\n".join(values))
            output.write(";\n")

    print(f"Generated rows: {len(rows)}")
    print(f"Failed images: {len(failed)}")
    print(f"Output: {output_path}")

    for path, reason in failed[:10]:
        print(f"Failed: {path} | {reason}")


if __name__ == "__main__":
    main()
