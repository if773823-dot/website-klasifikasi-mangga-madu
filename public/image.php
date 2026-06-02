<?php

$config = require dirname(__DIR__) . '/config/config.php';

$file = basename($_GET['file'] ?? '');
$path = $config['upload']['directory'] . DIRECTORY_SEPARATOR . $file;
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

if ($file === '' || !is_file($path) || !isset($mimeTypes[$extension])) {
    http_response_code(404);
    echo 'Gambar tidak ditemukan.';
    exit;
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($path));
readfile($path);
