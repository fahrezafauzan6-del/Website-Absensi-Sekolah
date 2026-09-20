<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('guru');

$page_title = 'Laporan Absensi';
$base_url = '/absensi-sekolah';

function guru_laporan_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guru_laporan_status_badge(string $status): string
{
    $classes = [
        'Hadir'     => 'badge-hadir',
        'Terlambat' => 'badge-terlambat',
        'Izin'      => 'badge-izin',
        'Sakit'     => 'badge-sakit',
        'Alpa'      => 'badge-alpa',
    ];

    $class = $classes[$status] ?? 'bg-secondary';

    return '<span class="badge ' . $class . '">' .
        guru_laporan_escape($status) .
        '</span>';
}

function guru_laporan_format_tanggal(?string $tanggal): string
{
    if (!$tanggal) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    if ($timestamp === false) {
        return $tanggal;
    }

    $hari = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
    ];

    $bulan = [
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $namaHari = $hari[date('l', $timestamp)] ?? date('l', $timestamp);
    $namaBulan = $bulan[(int) date('n', $timestamp)] ?? date('F', $timestamp);

    return $namaHari . ', ' .
        date('d', $timestamp) . ' ' .
        $namaBulan . ' ' .
        date('Y', $timestamp);
}

function guru_laporan_format_jam(?string $jam): string
{
    if (!$jam) {
        return '-';
    }

    $timestamp = strtotime($jam);

    return $timestamp !== false
        ? date('H:i', $timestamp)
        : $jam;
}

function guru_laporan_percentage(int $value, int $total): float
{
    if ($total <= 0) {
        return 0;
    }

    return round(($value / $total) * 100, 1);
}

$guruUserId = current_user_id();

if (!$guruUserId) {
    redirect_to_dashboard();
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id
     FROM guru
     WHERE user_id = :user_id
     LIMIT 1'
);
$stmt->execute([
    'user_id' => $guruUserId,
]);

$guruId = (int) $stmt->fetchColumn();

if (!$guruId) {
    $_SESSION['error'] = 'Data profil guru tidak ditemukan.';
    redirect_to_dashboard();
    exit;
}

/*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

$kelasId = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);
$kelasId = ($kelasId && $kelasId > 0) ? $kelasId : null;

$tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

$statusFilter = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = [
    'Hadir',
    'Terlambat',
    'Izin',
    'Sakit',
    'Alpa',
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMulai)) {
    $tanggalMulai = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSelesai)) {
    $tanggalSelesai = date('Y-m-d');
}

if ($tanggalMulai > $tanggalSelesai) {
    [$tanggalMulai, $tanggalSelesai] = [
        $tanggalSelesai,
        $tanggalMulai,
    ];
}

if ($tanggalMulai > date('Y-m-d')) {
    $tanggalMulai = date('Y-m-d');
}

if ($tanggalSelesai > date('Y-m-d')) {
    $tanggalSelesai = date('Y-m-d');
}

$stmt = $pdo->prepare("
    SELECT
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        COUNT(DISTINCT ks.siswa_id) AS jumlah_siswa
    FROM kelas k
    LEFT JOIN kelas_siswa ks
        ON ks.kelas_id = k.id
    WHERE k.guru_id = :guru_id
    GROUP BY
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai
    ORDER BY
        FIELD(
            k.hari,
            'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu'
        ),
        k.jam_mulai,
        k.nama_kelas
");

$stmt->execute([
    'guru_id' => $guruId,
]);

$kelasList = $stmt->fetchAll();

$kelas = null;

if ($kelasId) {
    $stmt = $pdo->prepare("
        SELECT
            k.id,
            k.nama_kelas,
            k.hari,
            k.jam_mulai,
            k.jam_selesai,
            g.nip,
            g.nama AS nama_guru
        FROM kelas k
        INNER JOIN guru g
                        ON g.id = k.guru_id
        WHERE k.id = :kelas_id
                    AND g.user_id = :guru_user_id
        LIMIT 1
    ");

    $stmt->execute([
        'kelas_id' => $kelasId,
        'guru_user_id' => $guruUserId,
    ]);

    $kelas = $stmt->fetch();

    if (!$kelas) {
        $kelasId = null;
    }
}

$where = [
    'a.tanggal BETWEEN :tanggal_mulai AND :tanggal_selesai',
    'k.guru_id = :guru_id',
];

$params = [
    'tanggal_mulai'   => $tanggalMulai,
    'tanggal_selesai' => $tanggalSelesai,
    'guru_id'         => $guruId,
];

if ($kelasId) {
    $where[] = 'a.kelas_id = :kelas_id';
    $params['kelas_id'] = $kelasId;
}

if ($statusFilter !== '') {
    $where[] = 'a.status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_absensi,
        SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status = 'Alpa' THEN 1 ELSE 0 END) AS alpa
    FROM absensi a
    INNER JOIN kelas k
        ON k.id = a.kelas_id
    WHERE {$whereSql}
");

$stmt->execute($params);

$summary = $stmt->fetch() ?: [];

$totalAbsensi = (int) ($summary['total_absensi'] ?? 0);
$totalHadir = (int) ($summary['hadir'] ?? 0);
$totalTerlambat = (int) ($summary['terlambat'] ?? 0);
$totalIzin = (int) ($summary['izin'] ?? 0);
$totalSakit = (int) ($summary['sakit'] ?? 0);
$totalAlpa = (int) ($summary['alpa'] ?? 0);

$totalEfektif = $totalHadir + $totalTerlambat;
$persentaseKehadiran = guru_laporan_percentage(
    $totalEfektif,
    $totalAbsensi
);

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        k.id AS kelas_id,
        k.nama_kelas,
        s.id AS siswa_id,
        s.nis,
        s.nama AS nama_siswa
    FROM absensi a
    INNER JOIN kelas k
        ON k.id = a.kelas_id
    INNER JOIN siswa s
        ON s.id = a.siswa_id
    WHERE {$whereSql}
    ORDER BY
        a.tanggal DESC,
        k.nama_kelas ASC,
        s.nama ASC,
        a.waktu DESC
");

$stmt->execute($params);

$laporan = $stmt->fetchAll();

$whereSiswa = [
    'a.tanggal BETWEEN :tanggal_mulai_siswa AND :tanggal_selesai_siswa',
    'k.guru_id = :guru_id_siswa',
];

$paramsSiswa = [
    'tanggal_mulai_siswa'   => $tanggalMulai,
    'tanggal_selesai_siswa' => $tanggalSelesai,
    'guru_id_siswa'         => $guruId,
];

if ($kelasId) {
    $whereSiswa[] = 'a.kelas_id = :kelas_id_siswa';
    $paramsSiswa['kelas_id_siswa'] = $kelasId;
}

$whereSiswaSql = implode(' AND ', $whereSiswa);

$stmt = $pdo->prepare("
    SELECT
        s.id AS siswa_id,
        s.nis,
        s.nama AS nama_siswa,
        k.id AS kelas_id,
        k.nama_kelas,
        COUNT(a.id) AS total,
        SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status = 'Alpa' THEN 1 ELSE 0 END) AS alpa
    FROM absensi a
    INNER JOIN kelas k
        ON k.id = a.kelas_id
    INNER JOIN siswa s
        ON s.id = a.siswa_id
    WHERE {$whereSiswaSql}
    GROUP BY
        s.id,
        s.nis,
        s.nama,
        k.id,
        k.nama_kelas
    ORDER BY
        k.nama_kelas ASC,
        s.nama ASC
");

$stmt->execute($paramsSiswa);

$ringkasanSiswa = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        a.tanggal,
        COUNT(*) AS total,
        SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status = 'Alpa' THEN 1 ELSE 0 END) AS alpa
    FROM absensi a
    INNER JOIN kelas k
        ON k.id = a.kelas_id
    WHERE {$whereSql}
    GROUP BY a.tanggal
    ORDER BY a.tanggal DESC
");

$stmt->execute($params);

$ringkasanTanggal = $stmt->fetchAll();

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    @media print {
        body {
            background: #fff !important;
        }

        .navbar,
        .sidebar,
        .page-header-actions,
        .filter-card,
        .no-print,
        .btn,
        .main-content>.container-fluid>.alert {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }

        .dashboard-card,
        .stat-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }

        .print-header {
            display: block !important;
        }

        .table {
            font-size: 11px;
        }
    }

    .print-header {
        display: none;
    }

    .report-filter-label {
        font-size: .82rem;
        font-weight: 600;
        color: var(--text-color);
    }

    .report-summary-progress {
        height: 8px;
        border-radius: 999px;
    }

    .report-summary-item {
        padding: 12px 14px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-sm);
        background: var(--white-color);
    }

    .report-summary-item+.report-summary-item {
        margin-top: 10px;
    }
</style>

<div class="app-layout">

    <div class="main-wrapper">

        <main class="main-content">
            <div class="mb-4">

                <!-- Header -->
                <div class="page-header mb-4">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i>
                            Laporan Absensi
                        </h1>

                        <p class="page-subtitle mb-0">
                            Lihat dan cetak rekap kehadiran siswa dari kelas yang Anda ajar.
                        </p>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= guru_laporan_escape($successMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= guru_laporan_escape($errorMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <div class="print-header mb-4">
                    <div class="text-center">
                        <h2 class="mb-1">LAPORAN ABSENSI SISWA</h2>

                        <?php if ($kelas): ?>
                            <h4 class="mb-1">
                                Kelas <?= guru_laporan_escape($kelas['nama_kelas']) ?>
                            </h4>
                        <?php else: ?>
                            <h4 class="mb-1">Seluruh Kelas</h4>
                        <?php endif; ?>

                        <div>
                            Periode
                            <?= guru_laporan_escape(guru_laporan_format_tanggal($tanggalMulai)) ?>
                            s/d
                            <?= guru_laporan_escape(guru_laporan_format_tanggal($tanggalSelesai)) ?>
                        </div>

                        <hr>
                    </div>
                </div>

                <div class="dashboard-card mb-4 filter-card no-print">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-funnel me-2"></i>
                                Filter Laporan
                            </h2>

                            <p class="text-muted mb-0">
                                Tentukan kelas, periode, dan status absensi.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body">
                        <form
                            method="get"
                            action="<?= guru_laporan_escape($base_url) ?>/guru/laporan.php"
                            class="row g-3">
                            <div class="col-lg-3">
                                <label
                                    for="kelas_id"
                                    class="form-label report-filter-label">
                                    Kelas
                                </label>

                                <select
                                    name="kelas_id"
                                    id="kelas_id"
                                    class="form-select">
                                    <option value="">Semua Kelas</option>

                                    <?php foreach ($kelasList as $item): ?>
                                        <option
                                            value="<?= (int) $item['id'] ?>"
                                            <?= $kelasId === (int) $item['id'] ? 'selected' : '' ?>>
                                            <?= guru_laporan_escape($item['nama_kelas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-lg-3">
                                <label
                                    for="tanggal_mulai"
                                    class="form-label report-filter-label">
                                    Tanggal Mulai
                                </label>

                                <input
                                    type="date"
                                    name="tanggal_mulai"
                                    id="tanggal_mulai"
                                    class="form-control"
                                    value="<?= guru_laporan_escape($tanggalMulai) ?>"
                                    max="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-lg-3">
                                <label
                                    for="tanggal_selesai"
                                    class="form-label report-filter-label">
                                    Tanggal Selesai
                                </label>

                                <input
                                    type="date"
                                    name="tanggal_selesai"
                                    id="tanggal_selesai"
                                    class="form-control"
                                    value="<?= guru_laporan_escape($tanggalSelesai) ?>"
                                    max="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-lg-2">
                                <label
                                    for="status"
                                    class="form-label report-filter-label">
                                    Status
                                </label>

                                <select
                                    name="status"
                                    id="status"
                                    class="form-select">
                                    <option value="">Semua Status</option>

                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option
                                            value="<?= guru_laporan_escape($status) ?>"
                                            <?= $statusFilter === $status ? 'selected' : '' ?>>
                                            <?= guru_laporan_escape($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-lg-1 d-flex align-items-end">
                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                    title="Terapkan filter">
                                    <i class="bi bi-search"></i>
                                    <span class="d-lg-none ms-1">Terapkan</span>
                                </button>
                            </div>

                            <div class="col-12">
                                <a
                                    href="<?= guru_laporan_escape($base_url) ?>/guru/laporan.php"
                                    class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    Reset Filter
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-light border mb-4">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong>
                            <i class="bi bi-funnel-fill me-1"></i>
                            Filter aktif:
                        </strong>

                        <span class="badge bg-primary">
                            <?= $kelas
                                ? guru_laporan_escape($kelas['nama_kelas'])
                                : 'Semua Kelas'
                            ?>
                        </span>

                        <span class="badge bg-secondary">
                            <?= guru_laporan_escape(guru_laporan_format_tanggal($tanggalMulai)) ?>
                            s/d
                            <?= guru_laporan_escape(guru_laporan_format_tanggal($tanggalSelesai)) ?>
                        </span>

                        <?php if ($statusFilter): ?>
                            <?= guru_laporan_status_badge($statusFilter) ?>
                        <?php else: ?>
                            <span class="badge bg-secondary">
                                Semua Status
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Summary -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-card">
                            <div class="stat-card-icon primary">
                                <i class="bi bi-clipboard-data"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">
                                    Total Absensi
                                </span>

                                <strong class="stat-card-value">
                                    <?= number_format($totalAbsensi) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-card">
                            <div class="stat-card-icon success">
                                <i class="bi bi-check-circle"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">Hadir</span>

                                <strong class="stat-card-value">
                                    <?= number_format($totalHadir) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-card">
                            <div class="stat-card-icon warning">
                                <i class="bi bi-clock-history"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">Terlambat</span>

                                <strong class="stat-card-value">
                                    <?= number_format($totalTerlambat) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-card">
                            <div class="stat-card-icon info">
                                <i class="bi bi-envelope"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">Izin</span>

                                <strong class="stat-card-value">
                                    <?= number_format($totalIzin) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-card">
                            <div class="stat-card-icon danger">
                                <i class="bi bi-thermometer-half"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">Sakit</span>

                                <strong class="stat-card-value">
                                    <?= number_format($totalSakit) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4 col-lg">
                        <div class="stat-card">
                            <div class="stat-card-icon danger">
                                <i class="bi bi-x-circle"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">Alpa</span>

                                <strong class="stat-card-value">
                                    <?= number_format($totalAlpa) ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Percentage -->
                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                Persentase Kehadiran
                            </h2>

                            <p class="text-muted mb-0">
                                Hadir dan terlambat dihitung sebagai kehadiran efektif.
                            </p>
                        </div>

                        <strong class="fs-4">
                            <?= number_format($persentaseKehadiran, 1) ?>%
                        </strong>
                    </div>

                    <div class="dashboard-card-body">
                        <div class="progress report-summary-progress">
                            <div
                                class="progress-bar bg-success"
                                role="progressbar"
                                style="width: <?= min(100, $persentaseKehadiran) ?>%"
                                aria-valuenow="<?= $persentaseKehadiran ?>"
                                aria-valuemin="0"
                                aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-calendar3 me-2"></i>
                                Rekap Per Hari
                            </h2>

                            <p class="text-muted mb-0">
                                Ringkasan absensi berdasarkan tanggal.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body p-0">
                        <?php if (!$ringkasanTanggal): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-calendar-x"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Belum Ada Data
                                </h3>

                                <p class="empty-state-text">
                                    Tidak ada data absensi pada periode yang dipilih.
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive-custom">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Hadir</th>
                                            <th class="text-center">Terlambat</th>
                                            <th class="text-center">Izin</th>
                                            <th class="text-center">Sakit</th>
                                            <th class="text-center">Alpa</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($ringkasanTanggal as $row): ?>
                                            <tr>
                                                <td>
                                                    <?= guru_laporan_escape(
                                                        guru_laporan_format_tanggal($row['tanggal'])
                                                    ) ?>
                                                </td>

                                                <td class="text-center fw-semibold">
                                                    <?= (int) $row['total'] ?>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-hadir">
                                                        <?= (int) $row['hadir'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-terlambat">
                                                        <?= (int) $row['terlambat'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-izin">
                                                        <?= (int) $row['izin'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-sakit">
                                                        <?= (int) $row['sakit'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-alpa">
                                                        <?= (int) $row['alpa'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-people me-2"></i>
                                Rekap Per Siswa
                            </h2>

                            <p class="text-muted mb-0">
                                Rekap status absensi masing-masing siswa.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body p-0">
                        <?php if (!$ringkasanSiswa): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-people"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Belum Ada Data Siswa
                                </h3>

                                <p class="empty-state-text">
                                    Tidak ada data absensi siswa pada filter yang dipilih.
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive-custom">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Siswa</th>
                                            <th>Kelas</th>
                                            <th class="text-center">Total</th>
                                            <th class="text-center">Hadir</th>
                                            <th class="text-center">Terlambat</th>
                                            <th class="text-center">Izin</th>
                                            <th class="text-center">Sakit</th>
                                            <th class="text-center">Alpa</th>
                                            <th class="text-center">Kehadiran</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($ringkasanSiswa as $index => $row): ?>

                                            <?php
                                            $total = (int) $row['total'];
                                            $hadir = (int) $row['hadir'];
                                            $terlambat = (int) $row['terlambat'];
                                            $efektif = $hadir + $terlambat;

                                            $persen = guru_laporan_percentage(
                                                $efektif,
                                                $total
                                            );
                                            ?>

                                            <tr>
                                                <td>
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td>
                                                    <div class="fw-semibold">
                                                        <?= guru_laporan_escape($row['nama_siswa']) ?>
                                                    </div>

                                                    <small class="text-muted font-monospace">
                                                        NIS:
                                                        <?= guru_laporan_escape($row['nis']) ?>
                                                    </small>
                                                </td>

                                                <td>
                                                    <?= guru_laporan_escape($row['nama_kelas']) ?>
                                                </td>

                                                <td class="text-center fw-semibold">
                                                    <?= $total ?>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-hadir">
                                                        <?= $hadir ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-terlambat">
                                                        <?= $terlambat ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-izin">
                                                        <?= (int) $row['izin'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-sakit">
                                                        <?= (int) $row['sakit'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-alpa">
                                                        <?= (int) $row['alpa'] ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <strong>
                                                        <?= number_format($persen, 1) ?>%
                                                    </strong>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-list-check me-2"></i>
                                Detail Absensi
                            </h2>

                            <p class="text-muted mb-0">
                                Data absensi berdasarkan filter yang dipilih.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body p-0">
                        <?php if (!$laporan): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-file-earmark-x"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Tidak Ada Data
                                </h3>

                                <p class="empty-state-text">
                                    Tidak ditemukan data absensi sesuai filter.
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive-custom">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Tanggal</th>
                                            <th>Siswa</th>
                                            <th>Kelas</th>
                                            <th>Waktu</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($laporan as $index => $row): ?>
                                            <tr>
                                                <td>
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td>
                                                    <span class="text-nowrap">
                                                        <?= guru_laporan_escape(
                                                            guru_laporan_format_tanggal($row['tanggal'])
                                                        ) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <div class="fw-semibold">
                                                        <?= guru_laporan_escape($row['nama_siswa']) ?>
                                                    </div>

                                                    <small class="text-muted font-monospace">
                                                        <?= guru_laporan_escape($row['nis']) ?>
                                                    </small>
                                                </td>

                                                <td>
                                                    <?= guru_laporan_escape($row['nama_kelas']) ?>
                                                </td>

                                                <td>
                                                    <?= guru_laporan_format_jam($row['waktu']) ?>
                                                </td>

                                                <td>
                                                    <?= guru_laporan_status_badge($row['status']) ?>
                                                </td>

                                                <td>
                                                    <?php if (!empty($row['keterangan'])): ?>
                                                        <?= guru_laporan_escape($row['keterangan']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-3 border-top text-muted small">
                                Menampilkan
                                <strong><?= number_format(count($laporan)) ?></strong>
                                data absensi.
                            </div>

                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mb-4">

                    <div class="page-header-actions no-print">
                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>
                            Cetak Laporan
                        </button>
                    </div>
                </div>

                <div class="print-header mt-4">
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-1">
                                Guru:
                                <strong>
                                    <?= guru_laporan_escape($kelas['nama_guru'] ?? current_user_name()) ?>
                                </strong>
                            </p>

                            <?php if ($kelas): ?>
                                <p class="mb-0">
                                    Kelas:
                                    <strong>
                                        <?= guru_laporan_escape($kelas['nama_kelas']) ?>
                                    </strong>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="col-6 text-end">
                            <p class="mb-5">
                                Dicetak pada:
                                <?= guru_laporan_escape(
                                    guru_laporan_format_tanggal(date('Y-m-d'))
                                ) ?>
                            </p>

                            <p class="mb-0">
                                __________________________
                            </p>

                            <p class="mb-0">
                                Guru
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>