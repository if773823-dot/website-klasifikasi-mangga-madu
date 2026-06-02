<?php

require dirname(__DIR__) . '/src/KnnClassifier.php';

$classifier = new KnnClassifier();
$feature = [
    'mean_red' => 200,
    'mean_green' => 165,
    'mean_blue' => 72,
];

$dataset = [
    [
        'id_dataset' => 1,
        'mean_red' => 80,
        'mean_green' => 125,
        'mean_blue' => 55,
        'label_kelas' => 'Mentah',
    ],
    [
        'id_dataset' => 2,
        'mean_red' => 145,
        'mean_green' => 148,
        'mean_blue' => 65,
        'label_kelas' => 'Setengah Matang',
    ],
    [
        'id_dataset' => 3,
        'mean_red' => 205,
        'mean_green' => 171,
        'mean_blue' => 75,
        'label_kelas' => 'Matang',
    ],
];

$result = $classifier->classify($feature, $dataset, 1);

if ($result['kelas_prediksi'] !== 'Matang') {
    fwrite(STDERR, 'Expected Matang, got ' . $result['kelas_prediksi'] . PHP_EOL);
    exit(1);
}

echo 'KNN smoke test passed: ' . $result['kelas_prediksi'] . PHP_EOL;
