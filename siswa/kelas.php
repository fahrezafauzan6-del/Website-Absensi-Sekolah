<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('siswa');

$page_title = 'Kelas Saya';
$additional_js = ['dashboard.js'];

$base_url = '/absensi-sekolah';

function siswa_kelas_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function siswa_kelas_format_jam(?string $jam): string
{
    if (!$jam) {
        return '-';
    }

    $timestamp = strtotime($jam);

    return $timestamp !== false
        ? date('H:i', $timestamp)
        : $jam;
}

function siswa_kelas_status_badge(string $status): string
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
        siswa_kelas_escape($status) .
        '</span>';
}

function siswa_kelas_percentage(int $hadir, int $total): float
{
    if ($total <= 0) {
        return 0;
    }

    return round(($hadir / $total) * 100, 1);
}

$siswaUserId = current_user_id();

if (!$siswaUserId) {
    redirect_to_dashboard();
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.user_id,
        s.nis,
        s.nama,
        s.jenis_kelamin,
        s.email,
        s.no_hp
    FROM siswa s
    WHERE s.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $siswaUserId,
]);

$siswa = $stmt->fetch();

if (!$siswa) {
    $_SESSION['error'] = 'Data siswa tidak ditemukan.';
    redirect_to_dashboard();
    exit;
}

$siswaId = (int) $siswa['id'];

$stmt = $pdo->prepare("
    SELECT
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.id AS guru_id,
        g.nip,
        g.nama AS nama_guru,

        COUNT(a.id) AS total_absensi,

        SUM(
            CASE
                WHEN a.status = 'Hadir' THEN 1
                ELSE 0
            END
        ) AS total_hadir,

        SUM(
            CASE
                WHEN a.status = 'Terlambat' THEN 1
                ELSE 0
            END
        ) AS total_terlambat,

        SUM(
            CASE
                WHEN a.status = 'Izin' THEN 1
                ELSE 0
            END
        ) AS total_izin,

        SUM(
            CASE
                WHEN a.status = 'Sakit' THEN 1
                ELSE 0
            END
        ) AS total_sakit,

        SUM(
            CASE
                WHEN a.status = 'Alpa' THEN 1
                ELSE 0
            END
        ) AS total_alpa,

        MAX(a.tanggal) AS tanggal_absensi_terakhir

    FROM kelas_siswa ks

    INNER JOIN kelas k
        ON k.id = ks.kelas_id

    INNER JOIN guru g
        ON g.id = k.guru_id

    LEFT JOIN absensi a
        ON a.kelas_id = k.id
       AND a.siswa_id = ks.siswa_id

    WHERE ks.siswa_id = :siswa_id

    GROUP BY
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.id,
        g.nip,
        g.nama

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
    'siswa_id' => $siswaId,
]);

$kelasList = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Statistik
|--------------------------------------------------------------------------
*/

$totalKelas = count($kelasList);
$totalAbsensi = 0;
$totalHadir = 0;
$totalTerlambat = 0;
$totalIzin = 0;
$totalSakit = 0;
$totalAlpa = 0;

foreach ($kelasList as $kelas) {
    $totalAbsensi += (int) $kelas['total_absensi'];
    $totalHadir += (int) $kelas['total_hadir'];
    $totalTerlambat += (int) $kelas['total_terlambat'];
    $totalIzin += (int) $kelas['total_izin'];
    $totalSakit += (int) $kelas['total_sakit'];
    $totalAlpa += (int) $kelas['total_alpa'];
}

$totalKehadiranEfektif = $totalHadir + $totalTerlambat;

$persentaseKehadiran = $totalAbsensi > 0
    ? round(($totalKehadiranEfektif / $totalAbsensi) * 100, 1)
    : 0;

$stmt = $pdo->prepare("
    SELECT
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        k.id AS kelas_id,
        k.nama_kelas
    FROM absensi a
    INNER JOIN kelas k
        ON k.id = a.kelas_id
    INNER JOIN kelas_siswa ks
        ON ks.kelas_id = a.kelas_id
       AND ks.siswa_id = a.siswa_id
    WHERE a.siswa_id = :siswa_id
    ORDER BY
        a.tanggal DESC,
        a.waktu DESC
    LIMIT 5
");

$stmt->execute([
    'siswa_id' => $siswaId,
]);

$absensiTerbaru = $stmt->fetchAll();

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

unset($_SESSION['success'], $_SESSION['error']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="app-layout">

    <div class="main-wrapper">

        <main class="main-content">
            <div>
                <div class="page-header mb-4">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-mortarboard me-2"></i>
                            Kelas Saya
                        </h1>

                        <p class="page-subtitle mb-0">
                            Daftar kelas yang sedang Anda ikuti.
                        </p>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= siswa_kelas_escape($successMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= siswa_kelas_escape($errorMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>
                <?php endif; ?>

                <div class="welcome-card mb-4">
                    <div class="welcome-card-content">
                        <div class="welcome-card-icon">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>

                        <div>
                            <h2 class="welcome-card-title mb-1">
                                <?= siswa_kelas_escape($siswa['nama']) ?>
                            </h2>

                            <p class="welcome-card-text mb-0">
                                <span class="me-3">
                                    <i class="bi bi-person-vcard me-1"></i>
                                    NIS:
                                    <strong>
                                        <?= siswa_kelas_escape($siswa['nis']) ?>
                                    </strong>
                                </span>

                                <?php if (!empty($siswa['email'])): ?>
                                    <span>
                                        <i class="bi bi-envelope me-1"></i>
                                        <?= siswa_kelas_escape($siswa['email']) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">

                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-card-icon primary">
                                <i class="bi bi-mortarboard"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">
                                    Total Kelas
                                </span>

                                <strong class="stat-card-value">
                                    <?= $totalKelas ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-card-icon success">
                                <i class="bi bi-check-circle"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">
                                    Hadir
                                </span>

                                <strong class="stat-card-value">
                                    <?= $totalHadir ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-card-icon warning">
                                <i class="bi bi-clock-history"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">
                                    Terlambat
                                </span>

                                <strong class="stat-card-value">
                                    <?= $totalTerlambat ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-card-icon info">
                                <i class="bi bi-bar-chart"></i>
                            </div>

                            <div class="stat-card-content">
                                <span class="stat-card-label">
                                    Kehadiran
                                </span>

                                <strong class="stat-card-value">
                                    <?= number_format($persentaseKehadiran, 1) ?>%
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-grid me-2"></i>
                                Daftar Kelas
                            </h2>

                            <p class="text-muted mb-0">
                                Klik kelas untuk melihat detail dan riwayat absensi.
                            </p>
                        </div>

                        <span class="badge bg-primary">
                            <?= $totalKelas ?> Kelas
                        </span>
                    </div>

                    <div class="dashboard-card-body">

                        <?php if (!$kelasList): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-mortarboard"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Belum Ada Kelas
                                </h3>

                                <p class="empty-state-text">
                                    Anda belum terdaftar pada kelas mana pun.
                                </p>

                                <a
                                    href="<?= siswa_kelas_escape($base_url) ?>/siswa/dashboard.php"
                                    class="btn btn-primary"
                                >
                                    <i class="bi bi-speedometer2 me-1"></i>
                                    Kembali ke Dashboard
                                </a>
                            </div>

                        <?php else: ?>

                            <div class="row g-4">

                                <?php foreach ($kelasList as $kelas): ?>

                                    <?php
                                    $kelasTotal = (int) $kelas['total_absensi'];
                                    $kelasHadir = (int) $kelas['total_hadir'];
                                    $kelasTerlambat = (int) $kelas['total_terlambat'];
                                    $kelasIzin = (int) $kelas['total_izin'];
                                    $kelasSakit = (int) $kelas['total_sakit'];
                                    $kelasAlpa = (int) $kelas['total_alpa'];

                                    $kelasKehadiran =
                                        $kelasHadir + $kelasTerlambat;

                                    $kelasPersentase = siswa_kelas_percentage(
                                        $kelasKehadiran,
                                        $kelasTotal
                                    );
                                    ?>

                                    <div class="col-12 col-md-6 col-xl-4">
                                        <div class="card h-100 border shadow-sm">

                                            <div class="card-body">

                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="avatar">
                                                            <i class="bi bi-mortarboard-fill"></i>
                                                        </div>

                                                        <div>
                                                            <h3 class="h5 mb-1">
                                                                <?= siswa_kelas_escape($kelas['nama_kelas']) ?>
                                                            </h3>

                                                            <div class="text-muted small">
                                                                <?= siswa_kelas_escape($kelas['nama_guru']) ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <span class="badge bg-primary">
                                                        <?= siswa_kelas_escape($kelas['hari']) ?>
                                                    </span>
                                                </div>

                                                <div class="p-3 bg-light rounded mb-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-clock text-primary"></i>

                                                        <span>
                                                            <?= siswa_kelas_format_jam($kelas['jam_mulai']) ?>
                                                            -
                                                            <?= siswa_kelas_format_jam($kelas['jam_selesai']) ?>
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($kelas['nip'])): ?>
                                                        <div class="small text-muted mt-2">
                                                            <i class="bi bi-person-vcard me-1"></i>
                                                            NIP:
                                                            <?= siswa_kelas_escape($kelas['nip']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="small text-muted">
                                                            Persentase Kehadiran
                                                        </span>

                                                        <strong>
                                                            <?= number_format($kelasPersentase, 1) ?>%
                                                        </strong>
                                                    </div>

                                                    <div
                                                        class="progress"
                                                        style="height: 8px;"
                                                    >
                                                        <div
                                                            class="progress-bar bg-success"
                                                            role="progressbar"
                                                            style="width: <?= min(100, $kelasPersentase) ?>%"
                                                            aria-valuenow="<?= $kelasPersentase ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100"
                                                        ></div>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">

                                                    <div class="col-4">
                                                        <div class="text-center p-2 border rounded">
                                                            <div class="small text-muted">
                                                                Hadir
                                                            </div>

                                                            <strong class="text-success">
                                                                <?= $kelasHadir ?>
                                                            </strong>
                                                        </div>
                                                    </div>

                                                    <div class="col-4">
                                                        <div class="text-center p-2 border rounded">
                                                            <div class="small text-muted">
                                                                Terlambat
                                                            </div>

                                                            <strong class="text-warning">
                                                                <?= $kelasTerlambat ?>
                                                            </strong>
                                                        </div>
                                                    </div>

                                                    <div class="col-4">
                                                        <div class="text-center p-2 border rounded">
                                                            <div class="small text-muted">
                                                                Alpa
                                                            </div>

                                                            <strong class="text-danger">
                                                                <?= $kelasAlpa ?>
                                                            </strong>
                                                        </div>
                                                    </div>

                                                </div>

                                                <div class="d-flex flex-wrap gap-2 mb-3">

                                                    <?php if ($kelasIzin > 0): ?>
                                                        <?= siswa_kelas_status_badge('Izin') ?>
                                                        <small class="text-muted">
                                                            <?= $kelasIzin ?>
                                                        </small>
                                                    <?php endif; ?>

                                                    <?php if ($kelasSakit > 0): ?>
                                                        <?= siswa_kelas_status_badge('Sakit') ?>
                                                        <small class="text-muted">
                                                            <?= $kelasSakit ?>
                                                        </small>
                                                    <?php endif; ?>

                                                </div>

                                                <?php if (!empty($kelas['tanggal_absensi_terakhir'])): ?>
                                                    <div class="small text-muted mb-3">
                                                        <i class="bi bi-calendar-check me-1"></i>
                                                        Absensi terakhir:
                                                        <?= siswa_kelas_escape(
                                                            $kelas['tanggal_absensi_terakhir']
                                                        ) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="small text-muted mb-3">
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        Belum ada data absensi.
                                                    </div>
                                                <?php endif; ?>

                                            </div>

                                            <div class="card-footer bg-transparent border-top">
                                                <div class="d-flex gap-2">

                                                    <a
                                                        href="<?= siswa_kelas_escape($base_url) ?>/siswa/absensi.php?kelas_id=<?= (int) $kelas['id'] ?>"
                                                        class="btn btn-primary btn-sm flex-grow-1"
                                                    >
                                                        <i class="bi bi-clipboard-check me-1"></i>
                                                        Absensi
                                                    </a>

                                                    <a
                                                        href="<?= siswa_kelas_escape($base_url) ?>/siswa/riwayat.php?kelas_id=<?= (int) $kelas['id'] ?>"
                                                        class="btn btn-outline-primary btn-sm flex-grow-1"
                                                    >
                                                        <i class="bi bi-clock-history me-1"></i>
                                                        Riwayat
                                                    </a>

                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-clock-history me-2"></i>
                                Absensi Terbaru
                            </h2>

                            <p class="text-muted mb-0">
                                Lima catatan absensi terakhir Anda.
                            </p>
                        </div>

                        <a
                            href="<?= siswa_kelas_escape($base_url) ?>/siswa/riwayat.php"
                            class="btn btn-sm btn-outline-primary"
                        >
                            Lihat Semua
                            <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <div class="dashboard-card-body p-0">

                        <?php if (!$absensiTerbaru): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-clipboard-x"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Belum Ada Riwayat
                                </h3>

                                <p class="empty-state-text">
                                    Belum ada data absensi yang tercatat.
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive-custom">
                                <table class="table table-hover align-middle mb-0">

                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Kelas</th>
                                            <th>Waktu</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($absensiTerbaru as $absensi): ?>

                                            <tr>
                                                <td>
                                                    <span class="fw-semibold">
                                                        <?= siswa_kelas_escape($absensi['tanggal']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?= siswa_kelas_escape($absensi['nama_kelas']) ?>
                                                </td>

                                                <td>
                                                    <?= siswa_kelas_format_jam($absensi['waktu']) ?>
                                                </td>

                                                <td>
                                                    <?= siswa_kelas_status_badge($absensi['status']) ?>
                                                </td>

                                                <td>
                                                    <?php if (!empty($absensi['keterangan'])): ?>
                                                        <?= siswa_kelas_escape($absensi['keterangan']) ?>
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
        </main>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>