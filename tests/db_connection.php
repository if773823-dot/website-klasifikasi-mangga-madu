<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';

try {
    Database::connect($config);
    echo 'DB OK' . PHP_EOL;
} catch (Throwable $exception) {
    echo 'DB ERROR: ' . $exception->getMessage() . PHP_EOL;
    exit(1);
}
