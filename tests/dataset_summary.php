<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);

$statement = $pdo->query(
    'SELECT label_kelas, COUNT(*) AS total FROM dataset_latih GROUP BY label_kelas ORDER BY label_kelas'
);

foreach ($statement as $row) {
    echo $row['label_kelas'] . ': ' . $row['total'] . PHP_EOL;
}
