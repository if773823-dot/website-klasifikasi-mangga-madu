<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$classes = ['Mentah', 'Setengah Matang', 'Matang'];
$matrix = [];
$classStats = [];

foreach ($classes as $actual) {
    $matrix[$actual] = array_fill_keys($classes, 0);
    $classStats[$actual] = [
        'total' => 0,
        'correct' => 0,
        'accuracy' => 0,
    ];
}

$totalEvaluated = 0;
$totalCorrect = 0;
$accuracy = 0;
$rows = [];
$dbError = null;

try {
    $pdo = Database::connect($config);
    $statement = $pdo->query(
        "SELECT
            h.id_klasifikasi,
            c.nama_file,
            c.nama_file_asli,
            h.nilai_k,
            h.kelas_prediksi,
            h.label_asli,
            h.jarak_euclidean,
            h.created_at
         FROM hasil_klasifikasi h
         JOIN citra_mangga_madu c ON c.id_citra = h.id_citra
         WHERE h.label_asli IS NOT NULL
         ORDER BY h.id_klasifikasi DESC"
    );
    $rows = $statement->fetchAll();

    foreach ($rows as $row) {
        $actual = $row['label_asli'];
        $predicted = $row['kelas_prediksi'];

        if (!isset($matrix[$actual][$predicted])) {
            continue;
        }

        $matrix[$actual][$predicted]++;
        $classStats[$actual]['total']++;
        $totalEvaluated++;

        if ($actual === $predicted) {
            $classStats[$actual]['correct']++;
            $totalCorrect++;
        }
    }

    $accuracy = $totalEvaluated > 0 ? round(($totalCorrect / $totalEvaluated) * 100, 2) : 0;

    foreach ($classes as $class) {
        $classStats[$class]['accuracy'] = $classStats[$class]['total'] > 0
            ? round(($classStats[$class]['correct'] / $classStats[$class]['total']) * 100, 2)
            : 0;
    }
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

function cell_intensity(int $value, int $max): string
{
    if ($max === 0 || $value === 0) {
        return 'background: #fff;';
    }

    $alpha = min(0.92, 0.18 + (($value / $max) * 0.58));
    return 'background: rgba(47, 111, 78, ' . $alpha . '); color: #fff;';
}

$maxMatrixValue = 0;
foreach ($matrix as $actualRow) {
    $maxMatrixValue = max($maxMatrixValue, max($actualRow));
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluasi Akurasi</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">M</div>
                <div>
                    <h1 class="brand-title">Evaluasi Akurasi</h1>
                    <p class="brand-subtitle">Perbandingan kelas prediksi dan label asli</p>
                </div>
            </div>
            <div class="nav-actions">
                <a class="button-link" href="index.php">Kembali ke Dashboard</a>
            </div>
        </header>

        <div class="section-heading">
            <div>
                <h2>Ringkasan Evaluasi</h2>
                <p>Akurasi dihitung dari data yang memiliki label asli.</p>
            </div>
        </div>

        <?php if ($dbError): ?>
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Database Belum Siap</h2>
                </div>
                <div class="panel-body">
                    <p><?= htmlspecialchars($dbError) ?></p>
                </div>
            </div>
        <?php else: ?>
            <section class="grid">
                <div class="metric-grid">
                    <div class="metric">
                        <p class="metric-label">Total Data Evaluasi</p>
                        <p class="metric-value"><?= htmlspecialchars((string) $totalEvaluated) ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-label">Prediksi Benar</p>
                        <p class="metric-value"><?= htmlspecialchars((string) $totalCorrect) ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-label">Akurasi</p>
                        <p class="metric-value"><?= htmlspecialchars((string) $accuracy) ?>%</p>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Confusion Matrix</h2>
                    </div>
                    <div class="panel-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>Label Asli \ Prediksi</th>
                                    <?php foreach ($classes as $class): ?>
                                        <th><?= htmlspecialchars($class) ?></th>
                                    <?php endforeach; ?>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $actual): ?>
                                    <tr>
                                        <th><?= htmlspecialchars($actual) ?></th>
                                        <?php foreach ($classes as $predicted): ?>
                                            <td style="<?= htmlspecialchars(cell_intensity($matrix[$actual][$predicted], $maxMatrixValue)) ?>">
                                                <?= htmlspecialchars((string) $matrix[$actual][$predicted]) ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><strong><?= htmlspecialchars((string) $classStats[$actual]['total']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Akurasi Per Kelas</h2>
                    </div>
                    <div class="panel-body chart">
                        <?php foreach ($classStats as $class => $stats): ?>
                            <div class="bar-row">
                                <div class="bar-label"><?= htmlspecialchars($class) ?></div>
                                <div class="bar-track">
                                    <div class="bar-fill fill-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $class))) ?>" style="width: <?= htmlspecialchars((string) $stats['accuracy']) ?>%"></div>
                                </div>
                                <div class="bar-value"><?= htmlspecialchars((string) $stats['accuracy']) ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Detail Evaluasi</h2>
                    </div>
                    <div class="panel-body">
                        <?php if (!$rows): ?>
                            <div class="empty-state">Belum ada data evaluasi. Isi label asli saat upload gambar agar akurasi bisa dihitung.</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Gambar</th>
                                        <th>Label Asli</th>
                                        <th>Prediksi</th>
                                        <th>K</th>
                                        <th>Jarak</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <?php $isCorrect = $row['label_asli'] === $row['kelas_prediksi']; ?>
                                        <tr>
                                            <td class="thumb-cell">
                                                <div class="thumb-card">
                                                    <img src="image.php?file=<?= urlencode($row['nama_file']) ?>" alt="Gambar evaluasi <?= htmlspecialchars($row['nama_file_asli']) ?>">
                                                    <div class="thumb-caption">
                                                        <span class="thumb-title">Citra evaluasi</span>
                                                        <span class="thumb-subtitle"><?= htmlspecialchars($row['nama_file_asli']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($row['label_asli']) ?></td>
                                            <td><?= htmlspecialchars($row['kelas_prediksi']) ?></td>
                                            <td><?= htmlspecialchars((string) $row['nilai_k']) ?></td>
                                            <td><?= htmlspecialchars((string) round((float) $row['jarak_euclidean'], 4)) ?></td>
                                            <td><strong><?= $isCorrect ? 'Benar' : 'Salah' ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
