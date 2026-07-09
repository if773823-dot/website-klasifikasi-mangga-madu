CREATE TABLE pengguna (
    id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    peran ENUM('Admin', 'Petani') NOT NULL DEFAULT 'Petani',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE citra_mangga_madu (
    id_citra INT AUTO_INCREMENT PRIMARY KEY,
    id_pengguna INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    nama_file_asli VARCHAR(255) NOT NULL,
    tanggal_unggah DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ukuran_citra VARCHAR(20) NOT NULL,
    CONSTRAINT fk_citra_pengguna
        FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ekstraksi_fitur (
    id_fitur INT AUTO_INCREMENT PRIMARY KEY,
    id_citra INT NOT NULL UNIQUE,
    mean_red FLOAT NOT NULL,
    mean_green FLOAT NOT NULL,
    mean_blue FLOAT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fitur_citra
        FOREIGN KEY (id_citra) REFERENCES citra_mangga_madu(id_citra)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hasil_klasifikasi (
    id_klasifikasi INT AUTO_INCREMENT PRIMARY KEY,
    id_citra INT NOT NULL,
    nilai_k INT NOT NULL,
    jarak_euclidean FLOAT NOT NULL,
    kelas_prediksi ENUM('Mentah', 'Setengah Matang', 'Matang') NOT NULL,
    label_asli ENUM('Mentah', 'Setengah Matang', 'Matang') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_klasifikasi_citra
        FOREIGN KEY (id_citra) REFERENCES citra_mangga_madu(id_citra)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dataset_latih (
    id_dataset INT AUTO_INCREMENT PRIMARY KEY,
    nama_file VARCHAR(255) NULL,
    mean_red FLOAT NOT NULL,
    mean_green FLOAT NOT NULL,
    mean_blue FLOAT NOT NULL,
    label_kelas ENUM('Mentah', 'Setengah Matang', 'Matang') NOT NULL,
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dataset_negatif (
    id_negatif INT AUTO_INCREMENT PRIMARY KEY,
    nama_file VARCHAR(255) NULL,
    mean_red FLOAT NOT NULL,
    mean_green FLOAT NOT NULL,
    mean_blue FLOAT NOT NULL,
    label_kelas VARCHAR(50) NOT NULL DEFAULT 'Bukan Mangga',
    catatan VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_dataset_latih_label ON dataset_latih(label_kelas);
CREATE INDEX idx_dataset_negatif_label ON dataset_negatif(label_kelas);
CREATE INDEX idx_hasil_klasifikasi_citra ON hasil_klasifikasi(id_citra);
