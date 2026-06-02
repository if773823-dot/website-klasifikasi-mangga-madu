import argparse
import io
import zipfile
from pathlib import Path

from PIL import Image, ImageStat


DEFAULT_CLASS_MAP = {
    "1": "Matang",
    "2": "Mentah",
}


def parse_args():
    parser = argparse.ArgumentParser(
        description="Convert Roboflow YOLOv8 mango dataset ZIP into dataset_latih SQL inserts."
    )
    parser.add_argument("zip_path", help="Path to YOLOv8 ZIP dataset.")
    parser.add_argument(
        "--output",
        default="database/import_dataset_latih_from_yolo.sql",
        help="Output SQL file path.",
    )
    parser.add_argument(
        "--include-split",
        action="append",
        choices=["train", "valid", "test"],
        default=None,
        help="Dataset split to include. Can be repeated. Defaults to train only.",
    )
    parser.add_argument(
        "--map",
        action="append",
        default=[],
        help="Class mapping in format class_id=Label, for example 0=Busuk.",
    )
    parser.add_argument(
        "--feature-source",
        choices=["crop", "full-image"],
        default="crop",
        help="Use YOLO bounding-box crop or the whole image for RGB features.",
    )
    return parser.parse_args()


def sql_string(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def class_map_from_args(raw_maps):
    class_map = DEFAULT_CLASS_MAP.copy()
    for raw_map in raw_maps:
        if "=" not in raw_map:
            raise ValueError("--map harus memakai format class_id=Label")
        class_id, label = raw_map.split("=", 1)
        class_map[class_id.strip()] = label.strip()
    return class_map


def find_image_name(zip_names, split, label_name):
    base = Path(label_name).stem
    image_prefix = f"{split}/images/{base}"
    for extension in [".jpg", ".jpeg", ".png", ".webp"]:
        candidate = image_prefix + extension
        if candidate in zip_names:
            return candidate
    return None


def crop_from_yolo_box(image, box):
    width, height = image.size
    _, x_center, y_center, box_width, box_height = box

    x_center *= width
    y_center *= height
    box_width *= width
    box_height *= height

    left = max(0, int(round(x_center - (box_width / 2))))
    top = max(0, int(round(y_center - (box_height / 2))))
    right = min(width, int(round(x_center + (box_width / 2))))
    bottom = min(height, int(round(y_center + (box_height / 2))))

    if right <= left or bottom <= top:
        return image

    return image.crop((left, top, right, bottom))


def mean_rgb(image):
    image = image.convert("RGB").resize((256, 256))
    red, green, blue = ImageStat.Stat(image).mean
    return round(red, 4), round(green, 4), round(blue, 4)


def main():
    args = parse_args()
    splits = args.include_split or ["train"]
    class_map = class_map_from_args(args.map)
    output_path = Path(args.output)

    rows = []
    skipped = 0

    with zipfile.ZipFile(args.zip_path) as archive:
        zip_names = set(archive.namelist())
        label_names = [
            name
            for name in archive.namelist()
            if any(name.startswith(f"{split}/labels/") for split in splits)
            and name.endswith(".txt")
        ]

        for label_name in label_names:
            split = label_name.split("/", 1)[0]
            image_name = find_image_name(zip_names, split, label_name)
            if image_name is None:
                skipped += 1
                continue

            label_text = archive.read(label_name).decode("utf-8").strip()
            if not label_text:
                skipped += 1
                continue

            image = Image.open(io.BytesIO(archive.read(image_name)))
            lines = [line for line in label_text.splitlines() if line.strip()]

            if args.feature_source == "full-image":
                class_votes = {}
                for line in lines:
                    class_id = line.split()[0]
                    if class_id in class_map:
                        class_votes[class_id] = class_votes.get(class_id, 0) + 1

                if not class_votes:
                    skipped += len(lines)
                    continue

                class_id = max(class_votes, key=class_votes.get)
                red, green, blue = mean_rgb(image)
                rows.append((image_name, red, green, blue, class_map[class_id]))
                skipped += len(lines) - class_votes[class_id]
                continue

            for index, line in enumerate(lines, start=1):
                parts = line.split()
                class_id = parts[0]
                if class_id not in class_map:
                    skipped += 1
                    continue

                box = [class_id] + [float(value) for value in parts[1:5]]
                cropped = crop_from_yolo_box(image, box)
                red, green, blue = mean_rgb(cropped)
                label = class_map[class_id]
                dataset_name = f"{image_name}#object-{index}"
                rows.append((dataset_name, red, green, blue, label))

    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", encoding="utf-8") as output:
        output.write("-- Generated from YOLOv8 dataset ZIP.\n")
        output.write(f"-- Feature source: {args.feature_source}.\n")
        output.write("-- Default mapping: 1=Matang, 2=Mentah. Class 0=Busuk is skipped unless mapped.\n\n")
        if rows:
            output.write(
                "INSERT INTO dataset_latih (nama_file, mean_red, mean_green, mean_blue, label_kelas, catatan)\nVALUES\n"
            )
            values = []
            for name, red, green, blue, label in rows:
                values.append(
                    f"({sql_string(name)}, {red}, {green}, {blue}, {sql_string(label)}, "
                    f"{sql_string('Diimpor dari dataset YOLOv8 Roboflow')})"
                )
            output.write(",\n".join(values))
            output.write(";\n")

    print(f"Generated rows: {len(rows)}")
    print(f"Skipped labels: {skipped}")
    print(f"Output: {output_path}")


if __name__ == "__main__":
    main()
