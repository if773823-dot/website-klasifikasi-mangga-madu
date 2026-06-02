<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$sqlFile = $argv[1] ?? dirname(__DIR__) . '/database/import_dataset_latih_from_yolo.sql';

if (!is_file($sqlFile)) {
    fwrite(STDERR, 'File SQL tidak ditemukan: ' . $sqlFile . PHP_EOL);
    exit(1);
}

$sql = trim(file_get_contents($sqlFile));
if ($sql === '') {
    fwrite(STDERR, 'File SQL kosong: ' . $sqlFile . PHP_EOL);
    exit(1);
}

$pdo = Database::connect($config);

try {
    $pdo->exec('DELETE FROM dataset_latih');
    $pdo->exec('ALTER TABLE dataset_latih AUTO_INCREMENT = 1');
    $pdo->exec($sql);
} catch (Throwable $exception) {
    throw $exception;
}

echo 'Dataset latih berhasil diganti dari: ' . $sqlFile . PHP_EOL;
