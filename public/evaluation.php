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
    <main class="app-frame">
        <aside class="app-sidebar" aria-label="Navigasi utama">
            <div class="sidebar-logo">M</div>
            <nav class="sidebar-nav">
                <a class="sidebar-item" href="index.php" title="Dashboard">D</a>
                <a class="sidebar-item active" href="evaluation.php" title="Evaluasi">E</a>
                
            </nav>
            <div class="sidebar-user">IF</div>
        </aside>

        <section class="dashboard-main">
            <header class="dashboard-header">
                <div class="profile-block">
                    <div class="profile-avatar">IF</div>
                    <div>
                        <h1>Iqbal Firdaus</h1>
                        <p>Evaluasi akurasi klasifikasi mangga madu</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a class="soft-button" href="index.php">Kembali ke Dashboard</a>
                    <span class="status-pill">Evaluasi</span>
                </div>
            </header>

            <div class="section-heading">
                <div>
                    <h2>Ringkasan Evaluasi</h2>
                    <p>Akurasi dihitung dari data yang memiliki label asli.</p>
                </div>
            </div>

            <?php if ($dbError): ?>
                <article class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <p class="eyebrow">Status</p>
                            <h2>Database Belum Siap</h2>
                        </div>
                    </div>
                    <div class="panel-body">
                        <p><?= htmlspecialchars($dbError) ?></p>
                    </div>
                </article>
            <?php else: ?>
                <section class="dashboard-grid">
                    <div class="right-stack">
                        <div class="stat-strip">
                            <div class="stat-card">
                                <p>Total Data Evaluasi</p>
                                <strong><?= htmlspecialchars((string) $totalEvaluated) ?></strong>
                            </div>
                            <div class="stat-card">
                                <p>Prediksi Benar</p>
                                <strong><?= htmlspecialchars((string) $totalCorrect) ?></strong>
                            </div>
                            <div class="stat-card">
                                <p>Akurasi</p>
                                <strong><?= htmlspecialchars((string) $accuracy) ?>%</strong>
                            </div>
                        </div>

                        <article class="dashboard-card">
                            <div class="card-head">
                                <div>
                                    <p class="eyebrow">Matrix</p>
                                    <h2>Confusion Matrix</h2>
                                </div>
                                <span class="mini-badge">Kelas</span>
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
                        </article>

                        <article class="dashboard-card">
                            <div class="card-head">
                                <div>
                                    <p class="eyebrow">Kinerja</p>
                                    <h2>Akurasi Per Kelas</h2>
                                </div>
                                <span class="mini-badge">Per kelas</span>
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
                        </article>
                    </div>

                    <article class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <p class="eyebrow">Detail</p>
                                <h2>Detail Evaluasi</h2>
                            </div>
                            <span class="mini-badge"><?= htmlspecialchars((string) count($rows)) ?> data</span>
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
                    </article>
                </section>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
