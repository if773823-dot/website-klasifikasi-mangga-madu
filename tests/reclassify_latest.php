<?php

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/KnnClassifier.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);
$classifier = new KnnClassifier();
$dataset = $pdo->query(
    'SELECT id_dataset, mean_red, mean_green, mean_blue, label_kelas FROM dataset_latih'
)->fetchAll();

$sql = "
    SELECT
        c.nama_file_asli,
        f.mean_red,
        f.mean_green,
        f.mean_blue
    FROM citra_mangga_madu c
    JOIN ekstraksi_fitur f ON f.id_citra = c.id_citra
    ORDER BY c.id_citra DESC
    LIMIT 5
";

foreach ($pdo->query($sql) as $row) {
    $feature = [
        'mean_red' => (float) $row['mean_red'],
        'mean_green' => (float) $row['mean_green'],
        'mean_blue' => (float) $row['mean_blue'],
    ];
    $result = $classifier->classify($feature, $dataset, 3);
    echo $row['nama_file_asli'] . ' => ' . $result['kelas_prediksi'] . PHP_EOL;
}
