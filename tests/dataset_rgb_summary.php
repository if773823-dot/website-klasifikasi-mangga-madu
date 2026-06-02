<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);

$sql = "
    SELECT
        label_kelas,
        COUNT(*) AS total,
        ROUND(AVG(mean_red), 2) AS avg_r,
        ROUND(AVG(mean_green), 2) AS avg_g,
        ROUND(AVG(mean_blue), 2) AS avg_b,
        ROUND(MIN(mean_red), 2) AS min_r,
        ROUND(MAX(mean_red), 2) AS max_r,
        ROUND(MIN(mean_green), 2) AS min_g,
        ROUND(MAX(mean_green), 2) AS max_g,
        ROUND(MIN(mean_blue), 2) AS min_b,
        ROUND(MAX(mean_blue), 2) AS max_b
    FROM dataset_latih
    GROUP BY label_kelas
    ORDER BY label_kelas
";

foreach ($pdo->query($sql) as $row) {
    echo $row['label_kelas'] . PHP_EOL;
    echo '  total: ' . $row['total'] . PHP_EOL;
    echo '  avg RGB: ' . $row['avg_r'] . ', ' . $row['avg_g'] . ', ' . $row['avg_b'] . PHP_EOL;
    echo '  red range: ' . $row['min_r'] . ' - ' . $row['max_r'] . PHP_EOL;
    echo '  green range: ' . $row['min_g'] . ' - ' . $row['max_g'] . PHP_EOL;
    echo '  blue range: ' . $row['min_b'] . ' - ' . $row['max_b'] . PHP_EOL;
}
