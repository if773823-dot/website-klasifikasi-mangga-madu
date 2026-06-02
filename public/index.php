<?php

require dirname(__DIR__) . '/src/Database.php';

$config = require dirname(__DIR__) . '/config/config.php';
$datasetCounts = [
    'Mentah' => 0,
    'Setengah Matang' => 0,
    'Matang' => 0,
];
$classificationTotal = 0;
$latestResults = [];
$dbStatus = 'Database siap';

try {
    $pdo = Database::connect($config);

    $statement = $pdo->query(
        'SELECT label_kelas, COUNT(*) AS total FROM dataset_latih GROUP BY label_kelas'
    );

    foreach ($statement as $row) {
        if (array_key_exists($row['label_kelas'], $datasetCounts)) {
            $datasetCounts[$row['label_kelas']] = (int) $row['total'];
        }
    }

    $classificationTotal = (int) $pdo->query('SELECT COUNT(*) FROM hasil_klasifikasi')->fetchColumn();
    $latestResults = $pdo->query(
        'SELECT c.nama_file, c.nama_file_asli, h.kelas_prediksi, h.nilai_k, h.jarak_euclidean, h.created_at
         FROM hasil_klasifikasi h
         JOIN citra_mangga_madu c ON c.id_citra = h.id_citra
         ORDER BY id_klasifikasi DESC
         LIMIT 5'
    )->fetchAll();
} catch (Throwable $exception) {
    $dbStatus = 'Database belum siap';
}

$datasetTotal = array_sum($datasetCounts);
$maxDatasetCount = max(1, max($datasetCounts));
$mentahEnd = $datasetTotal > 0 ? ($datasetCounts['Mentah'] / $datasetTotal) * 100 : 0;
$setengahEnd = $datasetTotal > 0
    ? (($datasetCounts['Mentah'] + $datasetCounts['Setengah Matang']) / $datasetTotal) * 100
    : 0;

function class_slug(string $label): string
{
    return strtolower(str_replace(' ', '-', $label));
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Klasifikasi Mangga Madu</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">M</div>
                <div>
                    <h1 class="brand-title">Klasifikasi Mangga Madu</h1>
                    <p class="brand-subtitle">Ekstraksi RGB dan K-Nearest Neighbors</p>
                </div>
            </div>
            <div class="nav-actions">
                <a class="ghost-link active-rgb" href="evaluation.php">Evaluasi Akurasi</a>
                <div class="status-pill"><?= htmlspecialchars($dbStatus) ?></div>
            </div>
        </header>

        <div class="section-heading">
            <div>
                <h2>Dashboard Klasifikasi</h2>
                <p>Unggah citra, lihat distribusi dataset, dan pantau riwayat hasil uji.</p>
            </div>
        </div>

        <section class="layout">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Uji Citra</h2>
                </div>
                <div class="panel-body">
                    <form class="upload-form" action="upload.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_pengguna" value="1">

                        <label for="gambar">
                            Gambar mangga
                            <span class="upload-drop">
                                <input id="gambar" name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp" required>
                            </span>
                        </label>

                        <label for="nilai_k">
                            Nilai K
                            <select id="nilai_k" name="nilai_k">
                                <option value="<?= htmlspecialchars((string) $config['classification']['default_k']) ?>">3</option>
                                <option value="1">1</option>
                                <option value="5">5</option>
                                <option value="7">7</option>
                            </select>
                        </label>

                        <label for="label_asli">
                            Label asli
                            <select id="label_asli" name="label_asli">
                                <option value="">Belum diketahui</option>
                                <option value="Mentah">Mentah</option>
                                <option value="Setengah Matang">Setengah Matang</option>
                                <option value="Matang">Matang</option>
                            </select>
                        </label>

                        <button type="submit">Klasifikasikan</button>
                    </form>
                </div>
            </div>

            <div class="grid">
                <div class="metric-grid">
                    <div class="metric">
                        <p class="metric-label">Dataset Latih</p>
                        <p class="metric-value"><?= htmlspecialchars((string) $datasetTotal) ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-label">Riwayat Uji</p>
                        <p class="metric-value"><?= htmlspecialchars((string) $classificationTotal) ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-label">Kelas Aktif</p>
                        <p class="metric-value"><?= htmlspecialchars((string) count(array_filter($datasetCounts))) ?></p>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Distribusi Dataset</h2>
                    </div>
                    <div class="panel-body donut-wrap">
                        <div
                            class="donut"
                            style="--mentah: <?= htmlspecialchars((string) $mentahEnd) ?>%; --setengah: <?= htmlspecialchars((string) $setengahEnd) ?>%;"
                            aria-label="Grafik distribusi dataset"
                        ></div>
                        <div class="legend">
                            <?php foreach ($datasetCounts as $label => $count): ?>
                                <?php $percentage = $datasetTotal > 0 ? round(($count / $datasetTotal) * 100, 1) : 0; ?>
                                <div class="legend-item">
                                    <span>
                                        <span class="swatch fill-<?= htmlspecialchars(class_slug($label)) ?>"></span>
                                        <?= htmlspecialchars($label) ?>
                                    </span>
                                    <strong><?= htmlspecialchars((string) $count) ?> data / <?= htmlspecialchars((string) $percentage) ?>%</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="panel-body" style="padding-top: 0;">
                        <div class="stack-chart" aria-label="Grafik batang distribusi dataset">
                            <?php foreach ($datasetCounts as $label => $count): ?>
                                <?php $percentage = $datasetTotal > 0 ? round(($count / $datasetTotal) * 100, 2) : 0; ?>
                                <div
                                    class="stack-segment fill-<?= htmlspecialchars(class_slug($label)) ?>"
                                    style="width: <?= htmlspecialchars((string) $percentage) ?>%;"
                                    title="<?= htmlspecialchars($label . ': ' . $count . ' data') ?>"
                                >
                                    <?= $percentage >= 8 ? htmlspecialchars((string) round($percentage)) . '%' : '' ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Perbandingan Kelas</h2>
                    </div>
                    <div class="panel-body chart">
                        <?php foreach ($datasetCounts as $label => $count): ?>
                            <?php $width = round(($count / $maxDatasetCount) * 100, 2); ?>
                            <div class="bar-row">
                                <div class="bar-label"><?= htmlspecialchars($label) ?></div>
                                <div class="bar-track">
                                    <div class="bar-fill fill-<?= htmlspecialchars(class_slug($label)) ?>" style="width: <?= htmlspecialchars((string) $width) ?>%"></div>
                                </div>
                                <div class="bar-value"><?= htmlspecialchars((string) $count) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($latestResults): ?>
                    <div class="panel">
                        <div class="panel-header">
                            <h2 class="panel-title">Riwayat Terbaru</h2>
                        </div>
                        <div class="panel-body">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Gambar</th>
                                        <th>Kelas</th>
                                        <th>K</th>
                                        <th>Jarak</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latestResults as $row): ?>
                                        <tr>
                                            <td class="thumb-cell">
                                                <div class="thumb-card">
                                                    <img src="image.php?file=<?= urlencode($row['nama_file']) ?>" alt="Gambar uji <?= htmlspecialchars($row['nama_file_asli']) ?>">
                                                    <div class="thumb-caption">
                                                        <span class="thumb-title">Citra uji</span>
                                                        <span class="thumb-subtitle"><?= htmlspecialchars($row['nama_file_asli']) ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($row['kelas_prediksi']) ?></td>
                                            <td><?= htmlspecialchars((string) $row['nilai_k']) ?></td>
                                            <td><?= htmlspecialchars((string) round((float) $row['jarak_euclidean'], 4)) ?></td>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
