<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$sqlFile = $argv[1] ?? null;

if ($sqlFile === null || !is_file($sqlFile)) {
    fwrite(STDERR, 'Gunakan: php tools\\import_sql.php path\\file.sql' . PHP_EOL);
    exit(1);
}

$sql = trim(file_get_contents($sqlFile));
if ($sql === '') {
    fwrite(STDERR, 'File SQL kosong: ' . $sqlFile . PHP_EOL);
    exit(1);
}

$pdo = Database::connect($config);
$pdo->exec($sql);

echo 'Imported: ' . $sqlFile . PHP_EOL;
