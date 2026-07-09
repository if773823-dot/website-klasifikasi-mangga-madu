<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$pdo = Database::connect($config);

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DELETE FROM hasil_klasifikasi');
$pdo->exec('DELETE FROM ekstraksi_fitur');
$pdo->exec('DELETE FROM citra_mangga_madu');
$pdo->exec('ALTER TABLE hasil_klasifikasi AUTO_INCREMENT = 1');
$pdo->exec('ALTER TABLE ekstraksi_fitur AUTO_INCREMENT = 1');
$pdo->exec('ALTER TABLE citra_mangga_madu AUTO_INCREMENT = 1');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo 'Riwayat uji berhasil dihapus.' . PHP_EOL;
