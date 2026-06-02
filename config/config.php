<?php

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'klasifikasi_mangga_madu',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'upload' => [
        'directory' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
        'max_size' => 5 * 1024 * 1024,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'resize_width' => 256,
        'resize_height' => 256,
    ],
    'classification' => [
        'default_k' => 3,
    ],
];
