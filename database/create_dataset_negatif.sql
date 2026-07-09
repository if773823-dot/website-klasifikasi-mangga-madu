CREATE TABLE IF NOT EXISTS dataset_negatif (
    id_negatif INT AUTO_INCREMENT PRIMARY KEY,
    nama_file VARCHAR(255) NULL,
    mean_red FLOAT NOT NULL,
    mean_green FLOAT NOT NULL,
    mean_blue FLOAT NOT NULL,
    label_kelas VARCHAR(50) NOT NULL DEFAULT 'Bukan Mangga',
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_dataset_negatif_label ON dataset_negatif(label_kelas);
