<?php

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/ImageFeatureExtractor.php';
require dirname(__DIR__) . '/src/KnnClassifier.php';

$config = require dirname(__DIR__) . '/config/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method tidak didukung.');
    }

    if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gambar gagal.');
    }

    $uploadConfig = $config['upload'];
    $file = $_FILES['gambar'];

    if ($file['size'] > $uploadConfig['max_size']) {
        throw new RuntimeException('Ukuran gambar terlalu besar.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $uploadConfig['allowed_extensions'], true)) {
        throw new RuntimeException('Format file tidak didukung.');
    }

    if (!is_dir($uploadConfig['directory'])) {
        mkdir($uploadConfig['directory'], 0775, true);
    }

    $storedName = 'mangga_madu_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadConfig['directory'] . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Gagal menyimpan gambar.');
    }

    $extractor = new ImageFeatureExtractor();
    $feature = $extractor->extract(
        $targetPath,
        $uploadConfig['resize_width'],
        $uploadConfig['resize_height']
    );

    $pdo = Database::connect($config);
    $pdo->beginTransaction();

    $idPengguna = (int) ($_POST['id_pengguna'] ?? 1);
    $nilaiK = (int) ($_POST['nilai_k'] ?? $config['classification']['default_k']);
    $labelAsli = $_POST['label_asli'] !== '' ? $_POST['label_asli'] : null;

    $stmt = $pdo->prepare(
        'INSERT INTO citra_mangga_madu (id_pengguna, nama_file, nama_file_asli, ukuran_citra)
         VALUES (:id_pengguna, :nama_file, :nama_file_asli, :ukuran_citra)'
    );
    $stmt->execute([
        'id_pengguna' => $idPengguna,
        'nama_file' => $storedName,
        'nama_file_asli' => $file['name'],
        'ukuran_citra' => $feature['ukuran_citra'],
    ]);
    $idCitra = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO ekstraksi_fitur (id_citra, mean_red, mean_green, mean_blue)
         VALUES (:id_citra, :mean_red, :mean_green, :mean_blue)'
    );
    $stmt->execute([
        'id_citra' => $idCitra,
        'mean_red' => $feature['mean_red'],
        'mean_green' => $feature['mean_green'],
        'mean_blue' => $feature['mean_blue'],
    ]);

    $dataset = $pdo->query(
        'SELECT id_dataset, mean_red, mean_green, mean_blue, label_kelas FROM dataset_latih'
    )->fetchAll();
    $negativeDataset = table_exists($pdo, 'dataset_negatif')
        ? $pdo->query('SELECT id_negatif, mean_red, mean_green, mean_blue, label_kelas FROM dataset_negatif')->fetchAll()
        : [];

    $classifier = new KnnClassifier();
    $result = $classifier->classify($feature, $dataset, $nilaiK);
    $nearestNegativeDistance = $negativeDataset ? nearest_feature_distance($feature, $negativeDataset) : null;
    $isWithinMangoDistance = $result['jarak_terdekat'] <= $config['classification']['max_nearest_distance'];
    $isCloserToNegative = $nearestNegativeDistance !== null && $nearestNegativeDistance < $result['jarak_terdekat'];
    $isMangoDetected = $isWithinMangoDistance && !$isCloserToNegative;

    if ($isMangoDetected) {
        $stmt = $pdo->prepare(
            'INSERT INTO hasil_klasifikasi (id_citra, nilai_k, jarak_euclidean, kelas_prediksi, label_asli)
             VALUES (:id_citra, :nilai_k, :jarak_euclidean, :kelas_prediksi, :label_asli)'
        );
        $stmt->execute([
            'id_citra' => $idCitra,
            'nilai_k' => $nilaiK,
            'jarak_euclidean' => $result['jarak_euclidean'],
            'kelas_prediksi' => $result['kelas_prediksi'],
            'label_asli' => $labelAsli,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Gagal Memproses</title><link rel="stylesheet" href="assets/app.css"></head><body>';
    echo '<main class="app-frame"><aside class="app-sidebar" aria-label="Navigasi utama"><div class="sidebar-logo">M</div><nav class="sidebar-nav"><a class="sidebar-item" href="index.php" title="Dashboard">D</a><a class="sidebar-item" href="evaluation.php" title="Evaluasi">E</a><a class="sidebar-item" href="#riwayat" title="Riwayat">R</a></nav><div class="sidebar-user">IF</div></aside><section class="dashboard-main"><header class="dashboard-header"><div class="profile-block"><div class="profile-avatar">IF</div><div><h1>Iqbal Firdaus</h1><p>Gagal memproses gambar</p></div></div><div class="header-actions"><a class="soft-button" href="index.php">Kembali ke dashboard</a></div></header><article class="dashboard-card"><div class="card-head"><div><p class="eyebrow">Error</p><h2>Gagal Memproses Gambar</h2></div></div><div class="panel-body"><p>' . htmlspecialchars($exception->getMessage()) . '</p><a class="button-link" href="index.php">Kembali</a></div></article></section></main></body></html>';
    exit;
}

$rgbBars = [
    'Mean Red' => ['value' => $feature['mean_red'], 'class' => 'fill-r'],
    'Mean Green' => ['value' => $feature['mean_green'], 'class' => 'fill-g'],
    'Mean Blue' => ['value' => $feature['mean_blue'], 'class' => 'fill-b'],
];

$graphWidth = 360;
$graphHeight = 250;
$plot = [
    'left' => 34,
    'right' => 342,
    'top' => 28,
    'bottom' => 218,
];

function graph_x(float $value, array $plot): float
{
    return $plot['left'] + (($value / 255) * ($plot['right'] - $plot['left']));
}

function graph_y(float $value, array $plot): float
{
    return $plot['bottom'] - (($value / 255) * ($plot['bottom'] - $plot['top']));
}

function label_class(string $label): string
{
    return strtolower(str_replace(' ', '-', $label));
}

function table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $statement->execute(['table_name' => $table]);

    return (bool) $statement->fetchColumn();
}

function nearest_feature_distance(array $feature, array $dataset): ?float
{
    $nearest = null;

    foreach ($dataset as $row) {
        $distance = sqrt(
            (($feature['mean_red'] - $row['mean_red']) ** 2) +
            (($feature['mean_green'] - $row['mean_green']) ** 2) +
            (($feature['mean_blue'] - $row['mean_blue']) ** 2)
        );

        if ($nearest === null || $distance < $nearest) {
            $nearest = $distance;
        }
    }

    return $nearest === null ? null : round($nearest, 4);
}

$newPoint = [
    'x' => graph_x((float) $feature['mean_red'], $plot),
    'y' => graph_y((float) $feature['mean_green'], $plot),
];
$displayPrediction = $isMangoDetected ? $result['kelas_prediksi'] : 'Bukan Mangga';

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hasil Klasifikasi</title>
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
                        <p>Hasil klasifikasi mangga madu</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a class="soft-button" href="evaluation.php">Evaluasi Akurasi</a>
                    <a class="soft-button" href="index.php">Uji Citra Baru</a>
                </div>
            </header>

            <div class="section-heading">
                <div>
                    <h2>Rincian Prediksi</h2>
                    <p>Gambar uji, fitur warna, tetangga terdekat, dan visualisasi KNN.</p>
                </div>
            </div>

            <section class="dashboard-grid">
                <article class="dashboard-card upload-card">
                    <div class="card-head">
                        <div>
                            <p class="eyebrow">Citra</p>
                            <h2>Citra Yang Diuji</h2>
                        </div>
                        <span class="mini-badge"><?= htmlspecialchars($displayPrediction) ?></span>
                    </div>
                    <div class="panel-body">
                        <div class="preview">
                            <img src="image.php?file=<?= urlencode($storedName) ?>" alt="Gambar mangga yang diklasifikasikan">
                            <div class="preview-caption">
                                <span><?= htmlspecialchars($file['name']) ?></span>
                                <span><?= htmlspecialchars($feature['ukuran_citra']) ?></span>
                            </div>
                        </div>

                        <div class="result-band" style="margin-top: 16px;">
                            <p class="result-label"><?= $isMangoDetected ? 'Kelas Prediksi' : 'Status Deteksi' ?></p>
                            <p class="result-value"><?= htmlspecialchars($displayPrediction) ?></p>
                        </div>

                        <div class="summary-grid">
                            <div class="summary-card">
                                <span class="summary-label">Nilai K</span>
                                <strong class="summary-value"><?= htmlspecialchars((string) $nilaiK) ?></strong>
                            </div>
                            <div class="summary-card">
                                <span class="summary-label">Jarak terdekat</span>
                                <strong class="summary-value"><?= htmlspecialchars((string) $result['jarak_terdekat']) ?></strong>
                            </div>
                            <div class="summary-card">
                                <span class="summary-label">Jarak negatif</span>
                                <strong class="summary-value"><?= $nearestNegativeDistance === null ? 'Belum ada dataset negatif' : htmlspecialchars((string) $nearestNegativeDistance) ?></strong>
                            </div>
                            <div class="summary-card">
                                <span class="summary-label">Ambang deteksi</span>
                                <strong class="summary-value"><?= htmlspecialchars((string) $config['classification']['max_nearest_distance']) ?></strong>
                            </div>
                        </div>

                        <dl class="detail-list">
                            <div class="detail-row">
                                <dt>Rata-rata jarak kelas terpilih</dt>
                                <dd><?= htmlspecialchars((string) $result['jarak_euclidean']) ?></dd>
                            </div>
                            <div class="detail-row">
                                <dt>File tersimpan</dt>
                                <dd><?= htmlspecialchars($storedName) ?></dd>
                            </div>
                        </dl>
                        <?php if (!$isMangoDetected): ?>
                            <div class="empty-state" style="margin-top: 14px;">
                                Citra tidak masuk ke pola dataset mangga madu atau lebih dekat ke dataset bukan mangga, sehingga tidak diklasifikasikan sebagai Mentah, Setengah Matang, atau Matang.
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <div class="right-stack">
                    <article class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <p class="eyebrow">RGB</p>
                                <h2>Komposisi RGB</h2>
                            </div>
                            <span class="mini-badge">Warna</span>
                        </div>
                        <div class="panel-body">
                            <div class="rgb-card" aria-label="Grafik kolom komposisi RGB">
                                <?php foreach ($rgbBars as $label => $bar): ?>
                                    <?php $height = round(((float) $bar['value'] / 255) * 100, 2); ?>
                                    <div class="rgb-column">
                                        <div class="rgb-track">
                                            <div class="rgb-fill <?= htmlspecialchars($bar['class']) ?>" style="--rgb-level: <?= htmlspecialchars((string) $height) ?>%;"></div>
                                        </div>
                                        <div class="rgb-name"><?= htmlspecialchars(str_replace('Mean ', '', $label)) ?></div>
                                        <div class="rgb-number"><?= htmlspecialchars((string) $bar['value']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="panel-body chart" style="padding-top: 0;">
                            <?php foreach ($rgbBars as $label => $bar): ?>
                                <?php $width = round(((float) $bar['value'] / 255) * 100, 2); ?>
                                <div class="bar-row">
                                    <div class="bar-label"><?= htmlspecialchars($label) ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill <?= htmlspecialchars($bar['class']) ?>" style="width: <?= htmlspecialchars((string) $width) ?>%"></div>
                                    </div>
                                    <div class="bar-value"><?= htmlspecialchars((string) $bar['value']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <p class="eyebrow">Visual</p>
                                <h2>Visualisasi KNN</h2>
                            </div>
                            <span class="mini-badge">KNN</span>
                        </div>
                        <div class="panel-body">
                            <div class="knn-visual">
                                <div class="knn-frame">
                                    <div class="knn-title">Before KNN</div>
                                    <svg class="knn-svg" viewBox="0 0 <?= htmlspecialchars((string) $graphWidth) ?> <?= htmlspecialchars((string) $graphHeight) ?>" role="img" aria-label="Grafik sebelum KNN">
                                        <line class="axis" x1="<?= htmlspecialchars((string) $plot['left']) ?>" y1="<?= htmlspecialchars((string) $plot['bottom']) ?>" x2="<?= htmlspecialchars((string) $plot['right']) ?>" y2="<?= htmlspecialchars((string) $plot['bottom']) ?>"></line>
                                        <line class="axis" x1="<?= htmlspecialchars((string) $plot['left']) ?>" y1="<?= htmlspecialchars((string) $plot['bottom']) ?>" x2="<?= htmlspecialchars((string) $plot['left']) ?>" y2="<?= htmlspecialchars((string) $plot['top']) ?>"></line>
                                        <text class="axis-text" x="322" y="238">Mean Red</text>
                                        <text class="axis-text" x="5" y="24">Mean Green</text>

                                        <?php foreach ($dataset as $row): ?>
                                            <circle
                                                class="knn-point point-<?= htmlspecialchars(label_class($row['label_kelas'])) ?>"
                                                cx="<?= htmlspecialchars((string) graph_x((float) $row['mean_red'], $plot)) ?>"
                                                cy="<?= htmlspecialchars((string) graph_y((float) $row['mean_green'], $plot)) ?>"
                                                r="3"
                                            ></circle>
                                        <?php endforeach; ?>

                                        <circle class="point-new" cx="<?= htmlspecialchars((string) $newPoint['x']) ?>" cy="<?= htmlspecialchars((string) $newPoint['y']) ?>" r="5"></circle>
                                        <text class="knn-annotation" x="<?= htmlspecialchars((string) min($newPoint['x'] + 10, 238)) ?>" y="<?= htmlspecialchars((string) max($newPoint['y'] - 8, 18)) ?>">Data baru</text>
                                    </svg>
                                </div>

                                <div class="knn-frame">
                                    <div class="knn-title">After KNN</div>
                                    <svg class="knn-svg" viewBox="0 0 <?= htmlspecialchars((string) $graphWidth) ?> <?= htmlspecialchars((string) $graphHeight) ?>" role="img" aria-label="Grafik setelah KNN">
                                        <line class="axis" x1="<?= htmlspecialchars((string) $plot['left']) ?>" y1="<?= htmlspecialchars((string) $plot['bottom']) ?>" x2="<?= htmlspecialchars((string) $plot['right']) ?>" y2="<?= htmlspecialchars((string) $plot['bottom']) ?>"></line>
                                        <line class="axis" x1="<?= htmlspecialchars((string) $plot['left']) ?>" y1="<?= htmlspecialchars((string) $plot['bottom']) ?>" x2="<?= htmlspecialchars((string) $plot['left']) ?>" y2="<?= htmlspecialchars((string) $plot['top']) ?>"></line>
                                        <text class="axis-text" x="322" y="238">Mean Red</text>
                                        <text class="axis-text" x="5" y="24">Mean Green</text>

                                        <?php foreach ($dataset as $row): ?>
                                            <circle
                                                class="knn-point point-<?= htmlspecialchars(label_class($row['label_kelas'])) ?>"
                                                cx="<?= htmlspecialchars((string) graph_x((float) $row['mean_red'], $plot)) ?>"
                                                cy="<?= htmlspecialchars((string) graph_y((float) $row['mean_green'], $plot)) ?>"
                                                r="3"
                                            ></circle>
                                        <?php endforeach; ?>

                                        <?php foreach ($result['nearest_neighbors'] as $neighbor): ?>
                                            <line
                                                class="neighbor-line"
                                                x1="<?= htmlspecialchars((string) $newPoint['x']) ?>"
                                                y1="<?= htmlspecialchars((string) $newPoint['y']) ?>"
                                                x2="<?= htmlspecialchars((string) graph_x((float) $neighbor['mean_red'], $plot)) ?>"
                                                y2="<?= htmlspecialchars((string) graph_y((float) $neighbor['mean_green'], $plot)) ?>"
                                            ></line>
                                        <?php endforeach; ?>

                                        <circle class="point-new" cx="<?= htmlspecialchars((string) $newPoint['x']) ?>" cy="<?= htmlspecialchars((string) $newPoint['y']) ?>" r="5"></circle>
                                        <text class="knn-annotation" x="<?= htmlspecialchars((string) min($newPoint['x'] + 10, 210)) ?>" y="<?= htmlspecialchars((string) max($newPoint['y'] - 8, 18)) ?>"><?= $isMangoDetected ? 'Prediksi:' : 'Status:' ?> <?= htmlspecialchars($displayPrediction) ?></text>
                                    </svg>
                                </div>
                            </div>
                            <p class="knn-note">Sumbu X memakai mean red dan sumbu Y memakai mean green. Garis putus-putus menunjukkan tetangga terdekat yang dipakai oleh nilai K.</p>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <p class="eyebrow">Data</p>
                                <h2>Tetangga Terdekat</h2>
                            </div>
                            <span class="mini-badge">K=<?= htmlspecialchars((string) $nilaiK) ?></span>
                        </div>
                        <div class="panel-body">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID Dataset</th>
                                        <th>Label</th>
                                        <th>Jarak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result['nearest_neighbors'] as $neighbor): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) $neighbor['id_dataset']) ?></td>
                                            <td><?= htmlspecialchars($neighbor['label_kelas']) ?></td>
                                            <td><?= htmlspecialchars((string) round($neighbor['distance'], 4)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>
        </section>
    </main>
</body>
</html>
