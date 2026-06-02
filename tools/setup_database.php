<?php

$config = require dirname(__DIR__) . '/config/config.php';
$db = $config['db'];

$serverDsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    $db['host'],
    $db['port'],
    $db['charset']
);

$databaseName = $db['database'];
$databaseIdentifier = str_replace('`', '``', $databaseName);

$pdo = new PDO($serverDsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec(
    "CREATE DATABASE IF NOT EXISTS `{$databaseIdentifier}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
$pdo->exec("USE `{$databaseIdentifier}`");

$sqlFiles = [
    dirname(__DIR__) . '/database/schema.sql',
    dirname(__DIR__) . '/database/seed_dataset_latih.sql',
    dirname(__DIR__) . '/database/import_dataset_latih_from_yolo.sql',
    dirname(__DIR__) . '/database/import_dataset_setengah_matang.sql',
];

foreach ($sqlFiles as $sqlFile) {
    if (!is_file($sqlFile)) {
        echo 'Skip missing: ' . $sqlFile . PHP_EOL;
        continue;
    }

    $sql = trim(file_get_contents($sqlFile));
    if ($sql === '') {
        echo 'Skip empty: ' . $sqlFile . PHP_EOL;
        continue;
    }

    $pdo->exec($sql);
    echo 'Imported: ' . $sqlFile . PHP_EOL;
}

echo 'Database setup selesai: ' . $databaseName . PHP_EOL;
