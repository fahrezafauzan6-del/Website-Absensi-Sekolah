<?php
$page_title = 'Dashboard Guru - Absensi Sekolah';

require_once __DIR__ . '/../auth/check_auth.php';
check_role('guru');

require_once __DIR__ . '/../config/database.php';

$additional_js = ['dashboard.js'];
$additional_vendor_js = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

$guruUserId = current_user_id();
$stmt = $pdo->prepare("
    SELECT id, nip, nama, email, no_hp
    FROM guru
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$guruUserId]);
$guru = $stmt->fetch();

if (!$guru) {
    ?>
    <main class="main-content">
        <div>
            <div class="page-header">
                <div>
                    <h1 class="page-title">Dashboard Guru</h1>
                    <p class="page-subtitle">
                        Data profil guru belum ditemukan.
                    </p>
                </div>
            </div>

            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Profil guru untuk akun ini belum tersedia. Silakan hubungi administrator.
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    <?php
    exit;
}

$guruId = (int) $guru['id'];

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM kelas
    WHERE guru_id = ?
");
$stmt->execute([$guruId]);
$totalKelas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ks.siswa_id)
    FROM kelas_siswa ks
    INNER JOIN kelas k ON k.id = ks.kelas_id
    WHERE k.guru_id = ?
");
$stmt->execute([$guruId]);
$totalSiswa = (int) $stmt->fetchColumn();

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status = 'Alpa' THEN 1 ELSE 0 END) AS alpa
    FROM absensi a
    INNER JOIN kelas k ON k.id = a.kelas_id
    WHERE k.guru_id = ?
      AND a.tanggal = ?
");
$stmt->execute([$guruId, $today]);
$todayStats = $stmt->fetch();

$totalAbsensiHariIni = (int) ($todayStats['total'] ?? 0);
$totalHadir = (int) ($todayStats['hadir'] ?? 0);
$totalTerlambat = (int) ($todayStats['terlambat'] ?? 0);
$totalIzin = (int) ($todayStats['izin'] ?? 0);
$totalSakit = (int) ($todayStats['sakit'] ?? 0);
$totalAlpa = (int) ($todayStats['alpa'] ?? 0);

$persentaseHadir = $totalAbsensiHariIni > 0
    ? round(($totalHadir + $totalTerlambat) / $totalAbsensiHariIni * 100)
    : 0;

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        s.id AS siswa_id,
        s.nis,
        s.nama AS nama_siswa,
        k.id AS kelas_id,
        k.nama_kelas
    FROM absensi a
    INNER JOIN siswa s ON s.id = a.siswa_id
    INNER JOIN kelas k ON k.id = a.kelas_id
    WHERE k.guru_id = ?
    ORDER BY a.tanggal DESC, a.waktu DESC, a.id DESC
    LIMIT 8
");
$stmt->execute([$guruId]);
$recentAttendance = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        COUNT(DISTINCT ks.siswa_id) AS jumlah_siswa
    FROM kelas k
    LEFT JOIN kelas_siswa ks ON ks.kelas_id = k.id
    WHERE k.guru_id = ?
    GROUP BY
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai
    ORDER BY
        FIELD(k.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'),
        k.jam_mulai
");
$stmt->execute([$guruId]);
$kelasGuru = $stmt->fetchAll();

$weeklyLabels = [];
$weeklyHadir = [];
$weeklyTerlambat = [];
$weeklyIzin = [];
$weeklySakit = [];
$weeklyAlpa = [];

$hariIndonesia = [
    'Sunday'    => 'Min',
    'Monday'    => 'Sen',
    'Tuesday'   => 'Sel',
    'Wednesday' => 'Rab',
    'Thursday'  => 'Kam',
    'Friday'    => 'Jum',
    'Saturday'  => 'Sab',
];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));

    $weeklyLabels[] = $hariIndonesia[date('l', strtotime($date))] . ' ' . date('d/m', strtotime($date));

    $weeklyHadir[] = 0;
    $weeklyTerlambat[] = 0;
    $weeklyIzin[] = 0;
    $weeklySakit[] = 0;
    $weeklyAlpa[] = 0;
}

$startDate = date('Y-m-d', strtotime('-6 days'));

$stmt = $pdo->prepare("
    SELECT
        a.tanggal,
        a.status,
        COUNT(*) AS jumlah
    FROM absensi a
    INNER JOIN kelas k ON k.id = a.kelas_id
    WHERE k.guru_id = ?
      AND a.tanggal BETWEEN ? AND ?
    GROUP BY a.tanggal, a.status
    ORDER BY a.tanggal ASC
");
$stmt->execute([$guruId, $startDate, $today]);

$weeklyData = $stmt->fetchAll();

$dateIndex = [];

for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($startDate . " +{$i} days"));
    $dateIndex[$date] = $i;
}

foreach ($weeklyData as $row) {
    $index = $dateIndex[$row['tanggal']] ?? null;

    if ($index === null) {
        continue;
    }

    $jumlah = (int) $row['jumlah'];

    switch ($row['status']) {
        case 'Hadir':
            $weeklyHadir[$index] = $jumlah;
            break;

        case 'Terlambat':
            $weeklyTerlambat[$index] = $jumlah;
            break;

        case 'Izin':
            $weeklyIzin[$index] = $jumlah;
            break;

        case 'Sakit':
            $weeklySakit[$index] = $jumlah;
            break;

        case 'Alpa':
            $weeklyAlpa[$index] = $jumlah;
            break;
    }
}

function guru_status_badge(string $status): string
{
    $classes = [
        'Hadir'      => 'badge-hadir',
        'Terlambat'  => 'badge-terlambat',
        'Izin'       => 'badge-izin',
        'Sakit'      => 'badge-sakit',
        'Alpa'       => 'badge-alpa',
    ];

    $class = $classes[$status] ?? 'bg-secondary';

    return '<span class="badge ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

function guru_format_tanggal(string $tanggal): string
{
    $bulan = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    $timestamp = strtotime($tanggal);

    if (!$timestamp) {
        return $tanggal;
    }

    return date('d', $timestamp) . ' ' .
        $bulan[(int) date('n', $timestamp)] . ' ' .
        date('Y', $timestamp);
}
?>

<main class="main-content dashboard-page">
    <div>

        <div class="page-header dashboard-page-header">
            <div>
                <h1 class="page-title">
                    Dashboard Guru
                </h1>
                <p class="page-subtitle">
                    Selamat datang, <?= htmlspecialchars($guru['nama'], ENT_QUOTES, 'UTF-8'); ?>.
                    Pantau kelas dan absensi siswa Anda di sini.
                </p>
            </div>

            <div class="page-header-actions">
                <span class="badge bg-light text-dark border px-3 py-2">
                    <i class="bi bi-calendar3 me-1"></i>
                    <span data-live-date></span>
                </span>
            </div>
        </div>

        <div class="welcome-card mb-4">
            <div class="welcome-card-content">
                <div class="welcome-card-icon">
                    <i class="bi bi-person-workspace"></i>
                </div>

                <div>
                    <h3 style="color: white;">
                        Halo, <?= htmlspecialchars($guru['nama'], ENT_QUOTES, 'UTF-8'); ?>!
                    </h3>

                    <p>
                        Kelola kelas dan pantau kehadiran siswa dengan mudah melalui
                        sistem absensi sekolah.
                    </p>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon bg-primary-subtle text-primary">
                        <i class="bi bi-door-open"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Kelas Diampu
                        </div>

                        <div class="stat-card-value" data-counter="<?= $totalKelas; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            <i class="bi bi-journal-text me-1"></i>
                            Kelas aktif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon bg-success-subtle text-success">
                        <i class="bi bi-people"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Total Siswa
                        </div>

                        <div class="stat-card-value" data-counter="<?= $totalSiswa; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            <i class="bi bi-person-check me-1"></i>
                            Siswa terdaftar
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon bg-info-subtle text-info">
                        <i class="bi bi-clipboard-check"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Absensi Hari Ini
                        </div>

                        <div class="stat-card-value" data-counter="<?= $totalAbsensiHariIni; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            <i class="bi bi-calendar-day me-1"></i>
                            Data tercatat
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon bg-warning-subtle text-warning">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Kehadiran Hari Ini
                        </div>

                        <div class="stat-card-value">
                            <span data-counter="<?= $persentaseHadir; ?>">0</span>%
                        </div>

                        <div class="stat-card-meta">
                            Hadir + terlambat
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-xl-5">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                Absensi Hari Ini
                            </h2>

                            <p class="dashboard-card-subtitle">
                                <?= guru_format_tanggal($today); ?>
                            </p>
                        </div>

                        <span class="dashboard-card-icon">
                            <i class="bi bi-pie-chart"></i>
                        </span>
                    </div>

                    <div class="dashboard-card-body">

                        <div class="attendance-summary">

                            <div class="attendance-summary-item">
                                <div class="attendance-summary-icon text-success bg-success-subtle">
                                    <i class="bi bi-check-circle"></i>
                                </div>

                                <div>
                                    <span class="attendance-summary-label">Hadir</span>
                                    <strong><?= $totalHadir; ?></strong>
                                </div>
                            </div>

                            <div class="attendance-summary-item">
                                <div class="attendance-summary-icon text-warning bg-warning-subtle">
                                    <i class="bi bi-clock"></i>
                                </div>

                                <div>
                                    <span class="attendance-summary-label">Terlambat</span>
                                    <strong><?= $totalTerlambat; ?></strong>
                                </div>
                            </div>

                            <div class="attendance-summary-item">
                                <div class="attendance-summary-icon text-info bg-info-subtle">
                                    <i class="bi bi-envelope"></i>
                                </div>

                                <div>
                                    <span class="attendance-summary-label">Izin</span>
                                    <strong><?= $totalIzin; ?></strong>
                                </div>
                            </div>

                            <div class="attendance-summary-item">
                                <div class="attendance-summary-icon text-primary bg-primary-subtle">
                                    <i class="bi bi-bandaid"></i>
                                </div>

                                <div>
                                    <span class="attendance-summary-label">Sakit</span>
                                    <strong><?= $totalSakit; ?></strong>
                                </div>
                            </div>

                            <div class="attendance-summary-item">
                                <div class="attendance-summary-icon text-danger bg-danger-subtle">
                                    <i class="bi bi-x-circle"></i>
                                </div>

                                <div>
                                    <span class="attendance-summary-label">Alpa</span>
                                    <strong><?= $totalAlpa; ?></strong>
                                </div>
                            </div>

                        </div>

                        <?php if ($totalAbsensiHariIni > 0): ?>
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted">
                                        Tingkat kehadiran
                                    </span>

                                    <strong class="text-success">
                                        <?= $persentaseHadir; ?>%
                                    </strong>
                                </div>

                                <div class="progress" style="height: 8px;">
                                    <div
                                        class="progress-bar bg-success"
                                        role="progressbar"
                                        style="width: <?= $persentaseHadir; ?>%;"
                                        aria-valuenow="<?= $persentaseHadir; ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state py-4">
                                <div class="empty-state-icon">
                                    <i class="bi bi-clipboard-x"></i>
                                </div>

                                <h5>Belum ada absensi</h5>

                                <p>
                                    Belum terdapat data absensi untuk kelas Anda hari ini.
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                Aksi Cepat
                            </h2>

                            <p class="dashboard-card-subtitle">
                                Akses fitur yang sering digunakan
                            </p>
                        </div>

                        <span class="dashboard-card-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </span>
                    </div>

                    <div class="dashboard-card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/kelas.php"
                                   class="quick-action">
                                    <span class="quick-action-icon bg-success-subtle text-success">
                                        <i class="bi bi-door-open"></i>
                                    </span>

                                    <span class="quick-action-content">
                                        <strong>Kelas Saya</strong>
                                        <small>Lihat kelas dan siswa</small>
                                    </span>

                                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                                </a>
                            </div>

                            <div class="col-12 col-md-6">
                                <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/laporan.php"
                                   class="quick-action">
                                    <span class="quick-action-icon bg-info-subtle text-info">
                                        <i class="bi bi-file-earmark-bar-graph"></i>
                                    </span>

                                    <span class="quick-action-content">
                                        <strong>Laporan Absensi</strong>
                                        <small>Lihat rekap kehadiran</small>
                                    </span>

                                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                                </a>
                            </div>

                            <div class="col-12 col-md-6">
                                <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/dashboard.php"
                                   class="quick-action"
                                   data-dashboard-refresh>
                                    <span class="quick-action-icon bg-warning-subtle text-warning">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </span>

                                    <span class="quick-action-content">
                                        <strong>Refresh Data</strong>
                                        <small>Perbarui informasi dashboard</small>
                                    </span>

                                    <i class="bi bi-chevron-right quick-action-arrow"></i>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-4 mb-4">

            <div class="col-12 col-xl-8">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                Tren Absensi 7 Hari
                            </h2>

                            <p class="dashboard-card-subtitle">
                                Rekap absensi seluruh kelas yang Anda ampu
                            </p>
                        </div>

                        <span class="dashboard-card-icon">
                            <i class="bi bi-bar-chart"></i>
                        </span>
                    </div>

                    <div class="dashboard-card-body">
                        <div style="height: 320px;">
                            <canvas id="guruAttendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                Kelas Saya
                            </h2>

                            <p class="dashboard-card-subtitle">
                                <?= $totalKelas; ?> kelas diampu
                            </p>
                        </div>

                        <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/kelas.php"
                           class="btn btn-sm btn-outline-primary">
                            Lihat Semua
                        </a>
                    </div>

                    <div class="dashboard-card-body p-0">

                        <?php if (!empty($kelasGuru)): ?>

                            <div class="list-group list-group-flush">

                                <?php foreach (array_slice($kelasGuru, 0, 5) as $kelas): ?>

                                    <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/detail_kelas.php?id=<?= (int) $kelas['id']; ?>"
                                       class="list-group-item list-group-item-action px-4 py-3">

                                        <div class="d-flex align-items-center gap-3">

                                            <div class="avatar avatar-sm bg-primary-subtle text-primary">
                                                <i class="bi bi-door-open"></i>
                                            </div>

                                            <div class="flex-grow-1 min-width-0">
                                                <div class="fw-semibold text-truncate">
                                                    <?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>

                                                <small class="text-muted">
                                                    <?= htmlspecialchars($kelas['hari'], ENT_QUOTES, 'UTF-8'); ?>
                                                    ·
                                                    <?= htmlspecialchars(substr($kelas['jam_mulai'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>
                                                    -
                                                    <?= htmlspecialchars(substr($kelas['jam_selesai'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>
                                                </small>
                                            </div>

                                            <span class="badge bg-light text-dark border">
                                                <?= (int) $kelas['jumlah_siswa']; ?> siswa
                                            </span>

                                        </div>

                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-door-open"></i>
                                </div>

                                <h5>Belum ada kelas</h5>

                                <p>
                                    Anda belum memiliki kelas yang diampu.
                                </p>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>

        <div class="dashboard-card mb-4">

            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">
                        Absensi Terbaru
                    </h2>

                    <p class="dashboard-card-subtitle">
                        Aktivitas absensi terakhir dari kelas Anda
                    </p>
                </div>

                <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/laporan.php"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-text me-1"></i>
                    Lihat Laporan
                </a>
            </div>

            <div class="dashboard-card-body">

                <?php if (!empty($recentAttendance)): ?>

                    <div class="table-responsive-custom">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Kelas</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($recentAttendance as $attendance): ?>

                                    <tr
                                        data-status="<?= htmlspecialchars($attendance['status'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-date="<?= htmlspecialchars($attendance['tanggal'], ENT_QUOTES, 'UTF-8'); ?>">

                                        <td>
                                            <div class="d-flex align-items-center gap-3">

                                                <div class="avatar avatar-sm bg-primary-subtle text-primary">
                                                    <?= htmlspecialchars(
                                                        strtoupper(substr($attendance['nama_siswa'], 0, 1)),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </div>

                                                <div>
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars($attendance['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>

                                                    <small class="text-muted">
                                                        NIS:
                                                        <?= htmlspecialchars($attendance['nis'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="fw-medium">
                                                <?= htmlspecialchars($attendance['nama_kelas'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= guru_format_tanggal($attendance['tanggal']); ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                substr((string) $attendance['waktu'], 0, 5),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </td>

                                        <td>
                                            <?= guru_status_badge($attendance['status']); ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($attendance['keterangan'])): ?>
                                                <span class="text-muted">
                                                    <?= htmlspecialchars(
                                                        $attendance['keterangan'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                <?php else: ?>

                    <div class="empty-state py-5">
                        <div class="empty-state-icon">
                            <i class="bi bi-clipboard-data"></i>
                        </div>

                        <h5>Belum ada data absensi</h5>

                        <p>
                            Data absensi siswa yang Anda input akan muncul di sini.
                        </p>

                        <a href="<?= htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/guru/absensi.php"
                           class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>
                            Input Absensi
                        </a>
                    </div>

                <?php endif; ?>

            </div>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartCanvas = document.getElementById('guruAttendanceChart');

    if (!chartCanvas || typeof Chart === 'undefined') {
        return;
    }

    const labels = <?= json_encode(
        $weeklyLabels,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;

    const hadirData = <?= json_encode($weeklyHadir); ?>;
    const terlambatData = <?= json_encode($weeklyTerlambat); ?>;
    const izinData = <?= json_encode($weeklyIzin); ?>;
    const sakitData = <?= json_encode($weeklySakit); ?>;
    const alpaData = <?= json_encode($weeklyAlpa); ?>;

    new Chart(chartCanvas, {
        type: 'bar',

        data: {
            labels: labels,

            datasets: [
                {
                    label: 'Hadir',
                    data: hadirData,
                    backgroundColor: 'rgba(22, 163, 74, 0.75)',
                    borderColor: 'rgba(22, 163, 74, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                },
                {
                    label: 'Terlambat',
                    data: terlambatData,
                    backgroundColor: 'rgba(217, 119, 6, 0.75)',
                    borderColor: 'rgba(217, 119, 6, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                },
                {
                    label: 'Izin',
                    data: izinData,
                    backgroundColor: 'rgba(8, 145, 178, 0.75)',
                    borderColor: 'rgba(8, 145, 178, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                },
                {
                    label: 'Sakit',
                    data: sakitData,
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                },
                {
                    label: 'Alpa',
                    data: alpaData,
                    backgroundColor: 'rgba(220, 38, 38, 0.75)',
                    borderColor: 'rgba(220, 38, 38, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false
            },

            plugins: {
                legend: {
                    position: 'bottom',

                    labels: {
                        usePointStyle: true,
                        padding: 18
                    }
                },

                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            },

            scales: {
                x: {
                    stacked: false,

                    grid: {
                        display: false
                    }
                },

                y: {
                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>