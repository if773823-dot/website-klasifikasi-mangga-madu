<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);

$statement = $pdo->query("SHOW TABLES LIKE 'dataset_negatif'");
if (!$statement->fetchColumn()) {
    echo 'dataset_negatif: tabel belum ada' . PHP_EOL;
    exit;
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM dataset_negatif')->fetchColumn();
echo 'dataset_negatif: ' . $total . PHP_EOL;
