<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);

$sql = "
    SELECT
        c.nama_file_asli,
        f.mean_red,
        f.mean_green,
        f.mean_blue,
        h.nilai_k,
        h.kelas_prediksi,
        h.jarak_euclidean,
        h.created_at
    FROM hasil_klasifikasi h
    JOIN citra_mangga_madu c ON c.id_citra = h.id_citra
    JOIN ekstraksi_fitur f ON f.id_citra = c.id_citra
    ORDER BY h.id_klasifikasi DESC
    LIMIT 10
";

foreach ($pdo->query($sql) as $row) {
    echo $row['created_at'] . ' | ' . $row['nama_file_asli'] . PHP_EOL;
    echo '  RGB: ' . round((float) $row['mean_red'], 2) . ', ' . round((float) $row['mean_green'], 2) . ', ' . round((float) $row['mean_blue'], 2) . PHP_EOL;
    echo '  K=' . $row['nilai_k'] . ' => ' . $row['kelas_prediksi'] . ' | jarak=' . round((float) $row['jarak_euclidean'], 4) . PHP_EOL;
}
