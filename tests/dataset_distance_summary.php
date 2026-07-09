<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);
$dataset = $pdo->query(
    'SELECT id_dataset, mean_red, mean_green, mean_blue, label_kelas FROM dataset_latih'
)->fetchAll();

$distances = [];

foreach ($dataset as $index => $row) {
    $nearest = null;

    foreach ($dataset as $otherIndex => $other) {
        if ($index === $otherIndex) {
            continue;
        }

        $distance = sqrt(
            (($row['mean_red'] - $other['mean_red']) ** 2) +
            (($row['mean_green'] - $other['mean_green']) ** 2) +
            (($row['mean_blue'] - $other['mean_blue']) ** 2)
        );

        if ($nearest === null || $distance < $nearest) {
            $nearest = $distance;
        }
    }

    if ($nearest !== null) {
        $distances[] = $nearest;
    }
}

sort($distances);
$count = count($distances);

foreach ([50, 75, 90, 95, 97, 99] as $percentile) {
    $index = min($count - 1, (int) floor(($percentile / 100) * $count));
    echo 'p' . $percentile . ': ' . round($distances[$index], 4) . PHP_EOL;
}

echo 'max: ' . round(max($distances), 4) . PHP_EOL;
