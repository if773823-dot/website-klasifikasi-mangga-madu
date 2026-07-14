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
    <main class="app-frame">
        <aside class="app-sidebar" aria-label="Navigasi utama">
            <div class="sidebar-logo">M</div>
            <nav class="sidebar-nav">
                <a class="sidebar-item active" href="index.php" title="Dashboard">D</a>
                <a class="sidebar-item" href="evaluation.php" title="Evaluasi">E</a>
                
            </nav>
            <div class="sidebar-user">IF</div>
        </aside>

        <section class="dashboard-main">
            <header class="dashboard-header">
                <div class="profile-block">
                    <div class="profile-avatar">IF</div>
                    <div>
                        <h1>Iqbal Firdaus</h1>
                        <p>Website klasifikasi kematangan mangga madu</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a class="soft-button" href="evaluation.php">Evaluasi Akurasi</a>
                    <span class="status-pill"><?= htmlspecialchars($dbStatus) ?></span>
                </div>
            </header>

            <section class="dashboard-grid">
                <article class="dashboard-card upload-card">
                    <div class="card-head">
                        <div>
                            <p class="eyebrow">Uji Citra</p>
                            <h2>Upload Mangga Madu</h2>
                        </div>
                        <span class="mini-badge">KNN</span>
                    </div>

                    <div class="upload-hero">
                        <div class="upload-orb">RGB</div>
                        <div>
                            <p class="upload-title">Ekstraksi warna</p>
                            <p class="upload-subtitle">Mean Red, Green, Blue</p>
                        </div>
                    </div>

                    <form class="upload-form compact-form" action="upload.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_pengguna" value="1">

                        <label for="gambar">
                            Gambar mangga
                            <span class="upload-drop">
                                <input id="gambar" name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp" required>
                            </span>
                        </label>

                        <div class="form-row">
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
                        </div>

                        <button type="submit">Klasifikasikan</button>
                    </form>
                </article>

                <section class="right-stack">
                    <div class="stat-strip">
                        <div class="stat-card">
                            <p>Dataset Latih</p>
                            <strong><?= htmlspecialchars((string) $datasetTotal) ?></strong>
                        </div>
                        <div class="stat-card">
                            <p>Riwayat Uji</p>
                            <strong><?= htmlspecialchars((string) $classificationTotal) ?></strong>
                        </div>
                        <div class="stat-card">
                            <p>Kelas Aktif</p>
                            <strong><?= htmlspecialchars((string) count(array_filter($datasetCounts))) ?></strong>
                        </div>
                    </div>

                    <article class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <p class="eyebrow">Dataset</p>
                                <h2>Budget Overview</h2>
                            </div>
                            <span class="mini-badge"><?= htmlspecialchars((string) $datasetTotal) ?> data</span>
                        </div>
                        <div class="dataset-overview">
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
                                        <strong><?= htmlspecialchars((string) $percentage) ?>%</strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <p class="eyebrow">Komposisi</p>
                                <h2>Spending Summary</h2>
                            </div>
                            <span class="mini-badge">Kelas</span>
                        </div>
                        <div class="chart">
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
                    </article>
                </section>
            </section>

            <?php if ($latestResults): ?>
                <section id="riwayat" class="dashboard-card history-card">
                    <div class="card-head">
                        <div>
                            <p class="eyebrow">Recent Transactions</p>
                            <h2>Riwayat Terbaru</h2>
                        </div>
                        <a class="soft-button" href="evaluation.php">Lihat evaluasi</a>
                    </div>
                    <div class="history-list">
                        <?php foreach ($latestResults as $row): ?>
                            <div class="history-item">
                                <div class="thumb-card">
                                    <img src="image.php?file=<?= urlencode($row['nama_file']) ?>" alt="Gambar uji <?= htmlspecialchars($row['nama_file_asli']) ?>">
                                    <div class="thumb-caption">
                                        <span class="thumb-title"><?= htmlspecialchars($row['kelas_prediksi']) ?></span>
                                        <span class="thumb-subtitle"><?= htmlspecialchars($row['nama_file_asli']) ?></span>
                                    </div>
                                </div>
                                <div class="history-meta">
                                    <strong>K=<?= htmlspecialchars((string) $row['nilai_k']) ?></strong>
                                    <span>Jarak <?= htmlspecialchars((string) round((float) $row['jarak_euclidean'], 4)) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
