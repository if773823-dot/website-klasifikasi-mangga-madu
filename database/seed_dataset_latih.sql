INSERT INTO pengguna (nama_lengkap, username, password, peran)
VALUES
('Administrator', 'admin', '$2y$10$examplehashgantisaatbuatlogin', 'Admin');

INSERT INTO dataset_latih (nama_file, mean_red, mean_green, mean_blue, label_kelas, catatan)
VALUES
('contoh_mentah_01.jpg', 78.4, 122.9, 54.2, 'Mentah', 'Contoh sementara, ganti dengan data asli.'),
('contoh_mentah_02.jpg', 84.1, 130.5, 58.7, 'Mentah', 'Contoh sementara, ganti dengan data asli.'),
('contoh_setengah_matang_01.jpg', 135.6, 142.8, 62.1, 'Setengah Matang', 'Contoh sementara, ganti dengan data asli.'),
('contoh_setengah_matang_02.jpg', 148.3, 150.2, 66.4, 'Setengah Matang', 'Contoh sementara, ganti dengan data asli.'),
('contoh_matang_01.jpg', 190.2, 162.5, 70.9, 'Matang', 'Contoh sementara, ganti dengan data asli.'),
('contoh_matang_02.jpg', 205.8, 171.3, 75.5, 'Matang', 'Contoh sementara, ganti dengan data asli.');
