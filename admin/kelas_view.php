<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Detail Kelas';
$additional_css = [];

$id = null;

if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
} elseif (isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if ($id === false || $id === null || $id <= 0) {
    header('Location: /absensi-sekolah/admin/kelas.php?error=invalid_id');
    exit;
}

function kelas_view_hari(string $hari): string
{
    $badge = [
        'Senin'  => 'primary',
        'Selasa' => 'info',
        'Rabu'   => 'success',
        'Kamis'  => 'warning',
        'Jumat'  => 'danger',
        'Sabtu'  => 'secondary',
    ];

    $class = $badge[$hari] ?? 'secondary';

    return '<span class="badge text-bg-' . $class . '">' .
        htmlspecialchars($hari, ENT_QUOTES, 'UTF-8') .
        '</span>';
}

function kelas_view_status_badge(string $status): string
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
        htmlspecialchars($status, ENT_QUOTES, 'UTF-8') .
        '</span>';
}

function kelas_view_format_tanggal(?string $tanggal): string
{
    if (!$tanggal) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    if ($timestamp === false) {
        return htmlspecialchars($tanggal, ENT_QUOTES, 'UTF-8');
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

    $hariNama = $hari[date('l', $timestamp)] ?? date('l', $timestamp);
    $bulanNama = $bulan[(int) date('n', $timestamp)] ?? date('n', $timestamp);

    return $hariNama . ', ' . date('j', $timestamp) . ' ' .
        $bulanNama . ' ' . date('Y', $timestamp);
}

function kelas_view_format_jam(?string $jam): string
{
    if (!$jam) {
        return '-';
    }

    return date('H:i', strtotime($jam));
}

$stmt = $pdo->prepare("
    SELECT
        k.id,
        k.nama_kelas,
        k.guru_id,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,

        g.nip,
        g.nama AS nama_guru,
        g.email AS email_guru,
        g.no_hp AS no_hp_guru,

        u.username AS username_guru,

        (
            SELECT COUNT(*)
            FROM kelas_siswa ks2
            WHERE ks2.kelas_id = k.id
        ) AS total_siswa,

        (
            SELECT COUNT(*)
            FROM absensi a2
            WHERE a2.kelas_id = k.id
        ) AS total_absensi

    FROM kelas k
    INNER JOIN guru g
        ON g.id = k.guru_id
    INNER JOIN users u
        ON u.id = g.user_id
    WHERE k.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$kelas = $stmt->fetch();

if (!$kelas) {
    header('Location: /absensi-sekolah/admin/kelas.php?error=not_found');
    exit;
}

$stmtSiswa = $pdo->prepare("
    SELECT
        s.user_id,
        s.nis,
        s.nama,
        s.jenis_kelamin,
        s.email,
        s.no_hp,
        u.username,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = :kelas_id
                            AND a.siswa_id = s.id
        ) AS total_absensi,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = :kelas_id_hadir
                            AND a.siswa_id = s.id
              AND a.status = 'Hadir'
        ) AS total_hadir,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = :kelas_id_terlambat
                            AND a.siswa_id = s.id
              AND a.status = 'Terlambat'
        ) AS total_terlambat,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = :kelas_id_izin
                            AND a.siswa_id = s.id
              AND a.status = 'Izin'
        ) AS total_izin,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = :kelas_id_sakit
                            AND a.siswa_id = s.id
              AND a.status = 'Sakit'
        ) AS total_sakit,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = :kelas_id_alpa
                            AND a.siswa_id = s.id
              AND a.status = 'Alpa'
        ) AS total_alpa

    FROM kelas_siswa ks
    INNER JOIN siswa s
        ON s.id = ks.siswa_id
    INNER JOIN users u
        ON u.id = s.user_id

    WHERE ks.kelas_id = :kelas_id_main

    ORDER BY s.nama ASC
");

$stmtSiswa->execute([
    'kelas_id'             => $id,
    'kelas_id_hadir'       => $id,
    'kelas_id_terlambat'   => $id,
    'kelas_id_izin'        => $id,
    'kelas_id_sakit'       => $id,
    'kelas_id_alpa'        => $id,
    'kelas_id_main'        => $id,
]);

$siswaList = $stmtSiswa->fetchAll();

$stmtAbsensi = $pdo->prepare("
    SELECT
        status,
        COUNT(*) AS total
    FROM absensi
    WHERE kelas_id = :kelas_id
    GROUP BY status
");

$stmtAbsensi->execute(['kelas_id' => $id]);

$absensiSummary = [
    'Hadir'     => 0,
    'Terlambat' => 0,
    'Izin'      => 0,
    'Sakit'     => 0,
    'Alpa'      => 0,
];

foreach ($stmtAbsensi->fetchAll() as $row) {
    $status = $row['status'];

    if (isset($absensiSummary[$status])) {
        $absensiSummary[$status] = (int) $row['total'];
    }
}

$totalAbsensi = array_sum($absensiSummary);
$totalHadir = $absensiSummary['Hadir'] + $absensiSummary['Terlambat'];

$persentaseKehadiran = $totalAbsensi > 0
    ? round(($totalHadir / $totalAbsensi) * 100, 1)
    : 0;

$stmtRecent = $pdo->prepare("
    SELECT
        a.id,
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        s.nis,
        s.nama AS nama_siswa
    FROM absensi a
    INNER JOIN siswa s
        ON s.id = a.siswa_id
    WHERE a.kelas_id = :kelas_id
    ORDER BY a.tanggal DESC, a.waktu DESC, s.nama ASC
    LIMIT 10
");

$stmtRecent->execute(['kelas_id' => $id]);
$recentAbsensi = $stmtRecent->fetchAll();

$base_url = $base_url ?? '/absensi-sekolah';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="app-layout">

    <main class="main-content">

        <div class="dashboard-page">

            <div class="page-header mb-4">
                <div>
                    <h1 class="page-title mb-1">
                        <i class="bi bi-building me-2"></i>
                        Detail Kelas
                    </h1>

                    <p class="page-subtitle mb-0">
                        Informasi lengkap kelas dan data siswa.
                    </p>
                </div>
            </div>

            <div class="row g-4 mb-4">

                <div class="col-lg-8">
                    <div class="dashboard-card h-100">

                        <div class="dashboard-card-header">
                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-mortarboard me-2"></i>
                                    Informasi Kelas
                                </h5>
                                <p class="text-muted small mb-0">
                                    Data utama kelas.
                                </p>
                            </div>
                        </div>

                        <div class="dashboard-card-body">

                            <div class="row g-4">

                                <div class="col-md-6">
                                    <div class="profile-info">
                                        <div class="profile-info-label">
                                            Nama Kelas
                                        </div>
                                        <div class="profile-info-value fs-5 fw-semibold">
                                            <?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="profile-info">
                                        <div class="profile-info-label">
                                            Hari
                                        </div>
                                        <div class="profile-info-value">
                                            <?= kelas_view_hari($kelas['hari']) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="profile-info">
                                        <div class="profile-info-label">
                                            Jam Mulai
                                        </div>
                                        <div class="profile-info-value fw-semibold">
                                            <i class="bi bi-clock me-1 text-primary"></i>
                                            <?= htmlspecialchars(kelas_view_format_jam($kelas['jam_mulai']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="profile-info">
                                        <div class="profile-info-label">
                                            Jam Selesai
                                        </div>
                                        <div class="profile-info-value fw-semibold">
                                            <i class="bi bi-clock-history me-1 text-primary"></i>
                                            <?= htmlspecialchars(kelas_view_format_jam($kelas['jam_selesai']), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-primary d-flex align-items-start gap-3 mb-0">
                                        <i class="bi bi-info-circle fs-5"></i>
                                        <div>
                                            <strong>Jadwal Kelas</strong>
                                            <div class="small mt-1">
                                                Kelas
                                                <strong>
                                                    <?= htmlspecialchars($kelas['nama_kelas'], ENT_QUOTES, 'UTF-8') ?>
                                                </strong>
                                                berlangsung setiap
                                                <strong><?= htmlspecialchars($kelas['hari'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                pukul
                                                <strong>
                                                    <?= htmlspecialchars(kelas_view_format_jam($kelas['jam_mulai']), ENT_QUOTES, 'UTF-8') ?>
                                                    -
                                                    <?= htmlspecialchars(kelas_view_format_jam($kelas['jam_selesai']), ENT_QUOTES, 'UTF-8') ?>
                                                </strong>.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="dashboard-card h-100">

                        <div class="dashboard-card-header">
                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-person-workspace me-2"></i>
                                    Guru Pengajar
                                </h5>
                                <p class="text-muted small mb-0">
                                    Guru yang mengajar kelas ini.
                                </p>
                            </div>
                        </div>

                        <div class="dashboard-card-body">

                            <div class="text-center mb-4">
                                <div class="avatar avatar-xl mx-auto mb-3">
                                    <?= htmlspecialchars(
                                        strtoupper(substr($kelas['nama_guru'], 0, 1)),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <h5 class="mb-1">
                                    <?= htmlspecialchars($kelas['nama_guru'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>

                                <p class="text-muted mb-0">
                                    NIP:
                                    <?= htmlspecialchars($kelas['nip'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>

                            <div class="profile-info mb-3">
                                <div class="profile-info-label">
                                    Username
                                </div>
                                <div class="profile-info-value">
                                    <?= htmlspecialchars($kelas['username_guru'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="profile-info mb-3">
                                <div class="profile-info-label">
                                    Email
                                </div>
                                <div class="profile-info-value text-break">
                                    <?= htmlspecialchars($kelas['email_guru'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                            <div class="profile-info">
                                <div class="profile-info-label">
                                    No. HP
                                </div>
                                <div class="profile-info-value">
                                    <?= htmlspecialchars($kelas['no_hp_guru'] ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <div class="row g-4 mb-4">

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-primary-subtle text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">
                                Jumlah Siswa
                            </div>
                            <div class="stat-card-value">
                                <?= number_format((int) $kelas['total_siswa']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-success-subtle text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">
                                Hadir
                            </div>
                            <div class="stat-card-value">
                                <?= number_format($absensiSummary['Hadir']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-warning-subtle text-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">
                                Terlambat
                            </div>
                            <div class="stat-card-value">
                                <?= number_format($absensiSummary['Terlambat']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-info-subtle text-info">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">
                                Kehadiran
                            </div>
                            <div class="stat-card-value">
                                <?= number_format($persentaseKehadiran, 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="dashboard-card mb-4">

                <div class="dashboard-card-header">
                    <div>
                        <h5 class="dashboard-card-title mb-1">
                            <i class="bi bi-pie-chart me-2"></i>
                            Ringkasan Absensi
                        </h5>
                        <p class="text-muted small mb-0">
                            Rekap seluruh data absensi kelas.
                        </p>
                    </div>
                </div>

                <div class="dashboard-card-body">

                    <div class="row g-3">

                        <div class="col-6 col-md">
                            <div class="attendance-summary-item text-center">
                                <div class="attendance-summary-icon badge-hadir mx-auto mb-2">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <div class="fw-bold fs-5">
                                    <?= number_format($absensiSummary['Hadir']) ?>
                                </div>
                                <small class="text-muted">Hadir</small>
                            </div>
                        </div>

                        <div class="col-6 col-md">
                            <div class="attendance-summary-item text-center">
                                <div class="attendance-summary-icon badge-terlambat mx-auto mb-2">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="fw-bold fs-5">
                                    <?= number_format($absensiSummary['Terlambat']) ?>
                                </div>
                                <small class="text-muted">Terlambat</small>
                            </div>
                        </div>

                        <div class="col-6 col-md">
                            <div class="attendance-summary-item text-center">
                                <div class="attendance-summary-icon badge-izin mx-auto mb-2">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <div class="fw-bold fs-5">
                                    <?= number_format($absensiSummary['Izin']) ?>
                                </div>
                                <small class="text-muted">Izin</small>
                            </div>
                        </div>

                        <div class="col-6 col-md">
                            <div class="attendance-summary-item text-center">
                                <div class="attendance-summary-icon badge-sakit mx-auto mb-2">
                                    <i class="bi bi-bandaid"></i>
                                </div>
                                <div class="fw-bold fs-5">
                                    <?= number_format($absensiSummary['Sakit']) ?>
                                </div>
                                <small class="text-muted">Sakit</small>
                            </div>
                        </div>

                        <div class="col-6 col-md">
                            <div class="attendance-summary-item text-center">
                                <div class="attendance-summary-icon badge-alpa mx-auto mb-2">
                                    <i class="bi bi-x-lg"></i>
                                </div>
                                <div class="fw-bold fs-5">
                                    <?= number_format($absensiSummary['Alpa']) ?>
                                </div>
                                <small class="text-muted">Alpa</small>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            <div class="dashboard-card mb-4">

                <div class="dashboard-card-header">
                    <div>
                        <h5 class="dashboard-card-title mb-1">
                            <i class="bi bi-people me-2"></i>
                            Daftar Siswa
                        </h5>
                        <p class="text-muted small mb-0">
                            <?= number_format((int) $kelas['total_siswa']) ?> siswa terdaftar di kelas ini.
                        </p>
                    </div>
                </div>

                <div class="dashboard-card-body p-0">

                    <?php if (empty($siswaList)): ?>

                        <div class="empty-state py-5">
                            <div class="empty-state-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <h5>Belum Ada Siswa</h5>
                            <p class="text-muted mb-0">
                                Belum ada siswa yang ditambahkan ke kelas ini.
                            </p>
                        </div>

                    <?php else: ?>

                        <div class="table-responsive-custom">

                            <table class="table table-hover align-middle mb-0">

                                <thead>
                                    <tr>
                                        <th width="60">No</th>
                                        <th>Siswa</th>
                                        <th>NIS</th>
                                        <th>Jenis Kelamin</th>
                                        <th class="text-center">Hadir</th>
                                        <th class="text-center">Terlambat</th>
                                        <th class="text-center">Izin</th>
                                        <th class="text-center">Sakit</th>
                                        <th class="text-center">Alpa</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($siswaList as $index => $siswa): ?>

                                        <tr>

                                            <td>
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>
                                                <div class="d-flex align-items-center gap-2">

                                                    <div class="avatar avatar-sm">
                                                        <?= htmlspecialchars(
                                                            strtoupper(substr($siswa['nama'], 0, 1)),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
                                                    </div>

                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?= htmlspecialchars($siswa['nama'], ENT_QUOTES, 'UTF-8') ?>
                                                        </div>

                                                        <small class="text-muted">
                                                            @<?= htmlspecialchars($siswa['username'], ENT_QUOTES, 'UTF-8') ?>
                                                        </small>
                                                    </div>

                                                </div>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>

                                            <td>
                                                <?php if ($siswa['jenis_kelamin'] === 'L'): ?>
                                                    <span class="badge text-bg-primary">
                                                        Laki-laki
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-danger">
                                                        Perempuan
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge badge-hadir">
                                                    <?= number_format((int) $siswa['total_hadir']) ?>
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge badge-terlambat">
                                                    <?= number_format((int) $siswa['total_terlambat']) ?>
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge badge-izin">
                                                    <?= number_format((int) $siswa['total_izin']) ?>
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge badge-sakit">
                                                    <?= number_format((int) $siswa['total_sakit']) ?>
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge badge-alpa">
                                                    <?= number_format((int) $siswa['total_alpa']) ?>
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
                        <h5 class="dashboard-card-title mb-1">
                            <i class="bi bi-clock-history me-2"></i>
                            Absensi Terbaru
                        </h5>
                        <p class="text-muted small mb-0">
                            10 data absensi terakhir dari kelas ini.
                        </p>
                    </div>
                </div>

                <div class="dashboard-card-body p-0">

                    <?php if (empty($recentAbsensi)): ?>

                        <div class="empty-state py-5">
                            <div class="empty-state-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h5>Belum Ada Data Absensi</h5>
                            <p class="text-muted mb-0">
                                Belum ada data absensi untuk kelas ini.
                            </p>
                        </div>

                    <?php else: ?>

                        <div class="table-responsive-custom">

                            <table class="table table-hover align-middle mb-0">

                                <thead>
                                    <tr>
                                        <th width="60">No</th>
                                        <th>Tanggal</th>
                                        <th>Waktu</th>
                                        <th>Siswa</th>
                                        <th>NIS</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($recentAbsensi as $index => $absensi): ?>

                                        <tr>

                                            <td>
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars(
                                                    kelas_view_format_tanggal($absensi['tanggal']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <i class="bi bi-clock me-1 text-muted"></i>
                                                <?= htmlspecialchars(
                                                    kelas_view_format_jam($absensi['waktu']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </td>

                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($absensi['nama_siswa'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($absensi['nis'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>

                                            <td>
                                                <?= kelas_view_status_badge($absensi['status']) ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($absensi['keterangan'])): ?>
                                                    <span
                                                        class="text-muted"
                                                        title="<?= htmlspecialchars($absensi['keterangan'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars(
                                                            mb_strimwidth(
                                                                $absensi['keterangan'],
                                                                0,
                                                                45,
                                                                '...',
                                                                'UTF-8'
                                                            ),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>
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

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">

            <div class="page-actions d-flex gap-2">
                <a
                    href="<?= htmlspecialchars($base_url) ?>/admin/kelas.php"
                    class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Kembali
                </a>
                
                <a
                    href="<?= htmlspecialchars($base_url) ?>/admin/kelas_edit.php?id=<?= (int) $kelas['id'] ?>"
                    class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>
                    Edit Kelas
                </a>

                <a
                    href="<?= htmlspecialchars($base_url) ?>/admin/kelas_hapus.php?id=<?= (int) $kelas['id'] ?>"
                    class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>
                    Hapus
                </a>
            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>