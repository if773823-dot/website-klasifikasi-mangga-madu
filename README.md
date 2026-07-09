# Website Klasifikasi Mangga Madu

Fondasi awal project untuk alur database dan klasifikasi gambar mangga madu memakai ekstraksi fitur RGB dan K-Nearest Neighbors (KNN).

## Struktur

- `database/schema.sql` berisi rancangan tabel MySQL.
- `database/seed_dataset_latih.sql` berisi contoh data latih awal.
- `config/config.php` berisi konfigurasi database dan upload.
- `src/Database.php` koneksi PDO.
- `src/ImageFeatureExtractor.php` ekstraksi `mean_red`, `mean_green`, `mean_blue`.
- `src/KnnClassifier.php` perhitungan jarak Euclidean dan prediksi kelas.
- `public/upload.php` endpoint awal upload gambar dan klasifikasi.
- `tools/yolo_zip_to_dataset_sql.py` konversi dataset YOLOv8 menjadi SQL `dataset_latih`.
- `tools/images_folder_to_dataset_sql.py` konversi folder gambar berlabel menjadi SQL `dataset_latih`.
- `public/index.php` form uji sederhana.

## Kebutuhan PHP

- PHP 8.2+
- Ekstensi `pdo_mysql`
- Ekstensi `gd` untuk membaca gambar JPG/PNG/WebP

## Setup Awal

1. Buat database MySQL, misalnya:

```sql
CREATE DATABASE klasifikasi_mangga_madu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import schema:

```powershell
mysql -u root -p klasifikasi_mangga_madu < database/schema.sql
mysql -u root -p klasifikasi_mangga_madu < database/seed_dataset_latih.sql
```

Tanpa `mysql` CLI, gunakan script setup:

```powershell
php tools\setup_database.php
```

3. Sesuaikan `config/config.php` dengan username/password database lokal.

4. Jalankan server:

```powershell
php -S 127.0.0.1:8080 -t public
```

5. Buka:

```text
http://127.0.0.1:8080
```

Catatan: data latih pada seed hanya contoh kecil. Untuk hasil skripsi yang valid, isi `dataset_latih` dari gambar mangga madu asli yang sudah diberi label.

## Import Dataset YOLOv8

Dataset YOLOv8 Roboflow bisa dipakai sebagai sumber data latih RGB/KNN. Script ini membaca file ZIP, mengambil bounding box mangga dari label YOLO, crop area mangga, lalu menghitung rata-rata RGB.

Default mapping:

- `1` -> `Matang`
- `2` -> `Mentah`
- `0` / `Busuk` dilewati karena schema awal belum punya kelas `Busuk`

Contoh:

```powershell
python tools\yolo_zip_to_dataset_sql.py "D:\Kuli ah\MAKALAH\Deteksi Kematangan Mangga.v1i.yolov8.zip"
```

Import hasilnya:

```powershell
mysql -u root -p klasifikasi_mangga_madu < database/import_dataset_latih_from_yolo.sql
```

Jika schema nanti diubah agar mendukung kelas `Busuk`, jalankan:

```powershell
python tools\yolo_zip_to_dataset_sql.py "D:\Kuli ah\MAKALAH\Deteksi Kematangan Mangga.v1i.yolov8.zip" --map 0=Busuk
```

Jika class `0` pada dataset ternyata adalah `Setengah Matang`, gunakan mapping ini:

```powershell
python tools\yolo_zip_to_dataset_sql.py "D:\Kuli ah\MAKALAH\Deteksi Kematangan Mangga.v1i.yolov8.zip" --map "0=Setengah Matang"
php tools\replace_dataset_latih.php
```

## Import Folder Gambar Setengah Matang

Jika sudah ada folder berisi gambar mangga yang semuanya berlabel `Setengah Matang`, buat SQL data latih dengan:

```powershell
python tools\images_folder_to_dataset_sql.py "D:\path\ke\folder_setengah_matang"
```

Import hasilnya:

```powershell
mysql -u root -p klasifikasi_mangga_madu < database/import_dataset_setengah_matang.sql
```

Untuk folder dengan subfolder:

```powershell
python tools\images_folder_to_dataset_sql.py "D:\path\ke\folder_setengah_matang" --recursive
```

## Import Dataset Bukan Mangga

Untuk mencegah sistem memaksa foto selain mangga menjadi `Mentah`, `Setengah Matang`, atau `Matang`, tambahkan contoh gambar bukan mangga ke `dataset_negatif`.

Contoh isi folder:

- apel
- jeruk
- daun
- tangan
- meja
- botol
- gambar kosong/background

Buat tabel negatif:

```powershell
php tools\import_sql.php database\create_dataset_negatif.sql
```

Buat SQL dari folder gambar bukan mangga:

```powershell
python tools\images_folder_to_negative_sql.py "D:\path\ke\folder_bukan_mangga"
```

Import ke database:

```powershell
php tools\import_sql.php database\import_dataset_negatif.sql
```

Setelah data negatif tersedia, proses upload akan menolak citra yang lebih dekat ke dataset `Bukan Mangga` daripada dataset mangga.
