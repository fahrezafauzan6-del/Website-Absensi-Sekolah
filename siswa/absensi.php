<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('siswa');

$page_title = 'Absensi Saya';
$additional_js = ['absensi.js'];

$base_url = '/absensi-sekolah';

function siswa_absensi_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function siswa_absensi_status_badge(string $status): string
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
        siswa_absensi_escape($status) .
        '</span>';
}

function siswa_absensi_format_jam(?string $jam): string
{
    if (!$jam) {
        return '-';
    }

    $timestamp = strtotime($jam);

    return $timestamp !== false
        ? date('H:i', $timestamp)
        : $jam;
}

function siswa_absensi_format_tanggal(?string $tanggal): string
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

function siswa_absensi_hari_ini(): string
{
    return date('Y-m-d');
}

$siswaUserId = current_user_id();

if (!$siswaUserId) {
    redirect_to_dashboard();
    exit;
}

$kelasId = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);

if (!$kelasId || $kelasId <= 0) {
    $kelasId = filter_input(INPUT_POST, 'kelas_id', FILTER_VALIDATE_INT);
}

if (!$kelasId || $kelasId <= 0) {
    $kelasId = null;
}

$tanggal = $_GET['tanggal'] ?? $_POST['tanggal'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal)) {
    $tanggal = date('Y-m-d');
}

if ($tanggal > date('Y-m-d')) {
    $tanggal = date('Y-m-d');
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
        g.nama AS nama_guru,
        g.nip
    FROM kelas_siswa ks
    INNER JOIN kelas k
        ON k.id = ks.kelas_id
    INNER JOIN guru g
        ON g.id = k.guru_id
    WHERE ks.siswa_id = :siswa_id
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

$kelas = null;

if ($kelasId) {
    $stmt = $pdo->prepare("
        SELECT
            k.id,
            k.nama_kelas,
            k.hari,
            k.jam_mulai,
            k.jam_selesai,
            g.id AS guru_id,
            g.nama AS nama_guru,
            g.nip
        FROM kelas_siswa ks
        INNER JOIN kelas k
            ON k.id = ks.kelas_id
        INNER JOIN guru g
            ON g.id = k.guru_id
        WHERE ks.kelas_id = :kelas_id
          AND ks.siswa_id = :siswa_id
        LIMIT 1
    ");

    $stmt->execute([
        'kelas_id' => $kelasId,
        'siswa_id' => $siswaId,
    ]);

    $kelas = $stmt->fetch();

    if (!$kelas) {
        $kelasId = null;
    }
}

$absensi = null;

if ($kelasId && $kelas) {
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.kelas_id,
            a.siswa_id,
            a.tanggal,
            a.waktu,
            a.status,
            a.keterangan
        FROM absensi a
        WHERE a.kelas_id = :kelas_id
          AND a.siswa_id = :siswa_id
          AND a.tanggal = :tanggal
        LIMIT 1
    ");

    $stmt->execute([
        'kelas_id' => $kelasId,
        'siswa_id' => $siswaId,
        'tanggal' => $tanggal,
    ]);

    $absensi = $stmt->fetch();
}

$riwayat = [];

if ($kelasId && $kelas) {
    $stmt = $pdo->prepare("
        SELECT
            a.tanggal,
            a.waktu,
            a.status,
            a.keterangan
        FROM absensi a
        WHERE a.kelas_id = :kelas_id
          AND a.siswa_id = :siswa_id
        ORDER BY
            a.tanggal DESC,
            a.waktu DESC
        LIMIT 7
    ");

    $stmt->execute([
        'kelas_id' => $kelasId,
        'siswa_id' => $siswaId,
    ]);

    $riwayat = $stmt->fetchAll();
}

$statistik = [
    'total'      => 0,
    'hadir'      => 0,
    'terlambat'  => 0,
    'izin'       => 0,
    'sakit'      => 0,
    'alpa'       => 0,
];

if ($kelasId && $kelas) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN status = 'Alpa' THEN 1 ELSE 0 END) AS alpa
        FROM absensi
        WHERE kelas_id = :kelas_id
          AND siswa_id = :siswa_id
    ");

    $stmt->execute([
        'kelas_id' => $kelasId,
        'siswa_id' => $siswaId,
    ]);

    $result = $stmt->fetch();

    if ($result) {
        $statistik = [
            'total'     => (int) ($result['total'] ?? 0),
            'hadir'     => (int) ($result['hadir'] ?? 0),
            'terlambat' => (int) ($result['terlambat'] ?? 0),
            'izin'      => (int) ($result['izin'] ?? 0),
            'sakit'     => (int) ($result['sakit'] ?? 0),
            'alpa'      => (int) ($result['alpa'] ?? 0),
        ];
    }
}

$kehadiranEfektif = $statistik['hadir'] + $statistik['terlambat'];

$persentaseKehadiran = $statistik['total'] > 0
    ? round(
        ($kehadiranEfektif / $statistik['total']) * 100,
        1
    )
    : 0;

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

unset($_SESSION['success'], $_SESSION['error']);

$csrfToken = $_SESSION['csrf_token'] ?? '';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="app-layout">

    <div class="main-wrapper">

        <main class="main-content">
            <div class="mb-4">

                <div class="page-header mb-4">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Absensi Saya
                        </h1>

                        <p class="page-subtitle mb-0">
                            Lihat dan ajukan status absensi Anda pada kelas yang diikuti.
                        </p>
                    </div>

                    <div class="page-header-actions">
                        <a
                            href="<?= siswa_absensi_escape($base_url) ?>/siswa/dashboard.php"
                            class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>
                            Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= siswa_absensi_escape($successMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= siswa_absensi_escape($errorMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <?php if ($kelas): ?>

                    <div class="welcome-card mb-4">
                        <div class="welcome-card-content">
                            <div class="welcome-card-icon">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>

                            <div>
                                <h2 class="welcome-card-title mb-1">
                                    <?= siswa_absensi_escape($kelas['nama_kelas']) ?>
                                </h2>

                                <p class="welcome-card-text mb-1">
                                    <i class="bi bi-person me-1"></i>
                                    Guru:
                                    <strong>
                                        <?= siswa_absensi_escape($kelas['nama_guru']) ?>
                                    </strong>
                                </p>

                                <p class="welcome-card-text mb-0">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= siswa_absensi_escape(
                                        siswa_absensi_format_tanggal($tanggal)
                                    ) ?>

                                    <span class="mx-2">•</span>

                                    <i class="bi bi-clock me-1"></i>
                                    <?= siswa_absensi_format_jam($kelas['jam_mulai']) ?>
                                    -
                                    <?= siswa_absensi_format_jam($kelas['jam_selesai']) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">

                        <div class="col-6 col-md-3">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="bi bi-clipboard-data"></i>
                                </div>

                                <div class="stat-card-content">
                                    <span class="stat-card-label">
                                        Total Absensi
                                    </span>

                                    <strong class="stat-card-value">
                                        <?= $statistik['total'] ?>
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
                                        <?= $statistik['hadir'] ?>
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
                                        <?= $statistik['terlambat'] ?>
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
                                    <i class="bi bi-calendar-check me-2"></i>
                                    Absensi <?= siswa_absensi_escape(
                                                siswa_absensi_format_tanggal($tanggal)
                                            ) ?>
                                </h2>

                                <p class="text-muted mb-0">
                                    Status absensi Anda untuk kelas ini.
                                </p>
                            </div>

                            <?php if ($absensi): ?>
                                <?= siswa_absensi_status_badge($absensi['status']) ?>
                            <?php endif; ?>
                        </div>

                        <div class="dashboard-card-body">

                            <?php if ($absensi): ?>

                                <div class="row g-4">

                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded h-100">
                                            <div class="text-muted small mb-1">
                                                Status
                                            </div>

                                            <div class="fs-5">
                                                <?= siswa_absensi_status_badge($absensi['status']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded h-100">
                                            <div class="text-muted small mb-1">
                                                Waktu
                                            </div>

                                            <div class="fw-semibold">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= siswa_absensi_format_jam($absensi['waktu']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="p-3 bg-light rounded h-100">
                                            <div class="text-muted small mb-1">
                                                Keterangan
                                            </div>

                                            <div class="fw-semibold">
                                                <?= !empty($absensi['keterangan'])
                                                    ? siswa_absensi_escape($absensi['keterangan'])
                                                    : '-'
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="alert alert-info mt-4 mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Absensi untuk tanggal ini sudah tercatat.
                                    Jika perlu perubahan, hubungi guru pengampu.
                                </div>

                            <?php else: ?>

                                <div class="text-center py-3 mb-4">
                                    <div class="empty-state-icon mx-auto mb-3">
                                        <i class="bi bi-clipboard-plus"></i>
                                    </div>

                                    <h3 class="h5">
                                        Belum Ada Absensi
                                    </h3>

                                    <p class="text-muted mb-0">
                                        Belum ada catatan absensi Anda pada tanggal ini.
                                    </p>
                                </div>

                                <form
                                    method="post"
                                    action="<?= siswa_absensi_escape($base_url) ?>/actions/absensi.php"
                                    data-absensi-form>
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="tambah">

                                    <input
                                        type="hidden"
                                        name="kelas_id"
                                        value="<?= (int) $kelas['id'] ?>">

                                    <input
                                        type="hidden"
                                        name="siswa_id"
                                        value="<?= (int) $siswaId ?>">

                                    <input
                                        type="hidden"
                                        name="tanggal"
                                        value="<?= siswa_absensi_escape($tanggal) ?>">

                                    <?php if ($csrfToken): ?>
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= siswa_absensi_escape($csrfToken) ?>">
                                    <?php endif; ?>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">
                                            Status Kehadiran
                                            <span class="text-danger">*</span>
                                        </label>

                                        <div
                                            class="attendance-status-options"
                                            data-absensi-status-container>

                                            <?php
                                            $statusOptions = [
                                                'Hadir' => ['icon' => 'bi-check-circle-fill', 'label' => 'Hadir'],
                                                'Terlambat' => ['icon' => 'bi-clock-fill', 'label' => 'Terlambat'],
                                                'Izin' => ['icon' => 'bi-envelope-paper-fill', 'label' => 'Izin'],
                                                'Sakit' => ['icon' => 'bi-bandaid-fill', 'label' => 'Sakit'],
                                                'Alpa' => ['icon' => 'bi-x-circle-fill', 'label' => 'Alpa'],
                                            ];
                                            ?>

                                            <?php foreach ($statusOptions as $status => $option): ?>
                                                <div class="attendance-status-option" data-status-option="<?= htmlspecialchars($status) ?>" role="button" tabindex="0" aria-pressed="false"> <input type="radio" name="status" value="<?= htmlspecialchars($status) ?>" data-absensi-status <?= (($attendance['status'] ?? '') === $status) ? 'checked' : '' ?>>
                                                    <div class="status-option-content"> <i class="bi <?= htmlspecialchars($option['icon']) ?> status-option-icon"></i> <span class="status-option-label"> <?= htmlspecialchars($option['label']) ?> </span> </div>
                                                </div>
                                            <?php endforeach; ?>

                                        </div>

                                        <div
                                            class="invalid-feedback d-block d-none"
                                            data-status-error>
                                            Silakan pilih status kehadiran.
                                        </div>
                                    </div>

                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label
                                                for="waktu"
                                                class="form-label">
                                                Waktu
                                            </label>

                                            <input
                                                type="time"
                                                name="waktu"
                                                id="waktu"
                                                class="form-control"
                                                value="<?= date('H:i') ?>"
                                                data-absensi-time>

                                            <div class="form-text">
                                                Waktu pencatatan absensi.
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label
                                                for="keterangan"
                                                class="form-label">
                                                Keterangan
                                            </label>

                                            <input
                                                type="text"
                                                name="keterangan"
                                                id="keterangan"
                                                class="form-control"
                                                maxlength="255"
                                                placeholder="Opsional">

                                            <div class="form-text">
                                                Wajib diisi untuk status Izin atau Sakit.
                                            </div>
                                        </div>

                                    </div>

                                    <div class="alert alert-warning mt-4">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Pastikan status absensi yang Anda masukkan sudah benar.
                                        Data yang telah tercatat dapat diperiksa oleh guru.
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-4">
                                        <a
                                            href="<?= siswa_absensi_escape($base_url) ?>/siswa/dashboard.php"
                                            class="btn btn-outline-secondary">
                                            Batal
                                        </a>

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            data-submit-absensi>
                                            <span
                                                class="spinner-border spinner-border-sm me-1 d-none"
                                                data-submit-spinner
                                                aria-hidden="true"></span>

                                            <i class="bi bi-save me-1"></i>
                                            Simpan Absensi
                                        </button>
                                    </div>
                                </form>

                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h2 class="dashboard-card-title">
                                    <i class="bi bi-clock-history me-2"></i>
                                    Riwayat Absensi Kelas
                                </h2>

                                <p class="text-muted mb-0">
                                    Tujuh catatan absensi terakhir Anda pada kelas ini.
                                </p>
                            </div>

                            <a
                                href="<?= siswa_absensi_escape($base_url) ?>/siswa/riwayat.php?kelas_id=<?= (int) $kelas['id'] ?>"
                                class="btn btn-sm btn-outline-primary">
                                Lihat Semua
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>

                        <div class="dashboard-card-body p-0">

                            <?php if (!$riwayat): ?>

                                <div class="empty-state py-5">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-clock-history"></i>
                                    </div>

                                    <h3 class="empty-state-title">
                                        Belum Ada Riwayat
                                    </h3>

                                    <p class="empty-state-text">
                                        Belum ada data absensi untuk kelas ini.
                                    </p>
                                </div>

                            <?php else: ?>

                                <div class="table-responsive-custom">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Waktu</th>
                                                <th>Status</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($riwayat as $row): ?>
                                                <tr>
                                                    <td>
                                                        <?= siswa_absensi_escape(
                                                            siswa_absensi_format_tanggal(
                                                                $row['tanggal']
                                                            )
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= siswa_absensi_format_jam($row['waktu']) ?>
                                                    </td>

                                                    <td>
                                                        <?= siswa_absensi_status_badge($row['status']) ?>
                                                    </td>

                                                    <td>
                                                        <?php if (!empty($row['keterangan'])): ?>
                                                            <?= siswa_absensi_escape($row['keterangan']) ?>
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

                <?php elseif ($kelasList): ?>

                    <div class="dashboard-card">
                        <div class="empty-state py-5">
                            <div class="empty-state-icon">
                                <i class="bi bi-clipboard-check"></i>
                            </div>

                            <h3 class="empty-state-title">
                                Pilih Kelas
                            </h3>

                            <p class="empty-state-text">
                                Pilih kelas dan tanggal di atas untuk melihat absensi Anda.
                            </p>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="dashboard-card">
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
                                href="<?= siswa_absensi_escape($base_url) ?>/siswa/dashboard.php"
                                class="btn btn-primary">
                                <i class="bi bi-speedometer2 me-1"></i>
                                Kembali ke Dashboard
                            </a>
                        </div>
                    </div>

                <?php endif; ?>

            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">

                <a
                    href="<?= htmlspecialchars($base_url) ?>/siswa/kelas.php"
                    class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Kembali
                </a>
            </div>
        </main>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>