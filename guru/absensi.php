<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('guru');

$page_title = 'Absensi Siswa';
$additional_js = ['absensi.js'];

$base_url = '/absensi-sekolah';

function guru_absensi_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guru_absensi_status_badge(string $status): string
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
        guru_absensi_escape($status) .
        '</span>';
}

function guru_absensi_format_tanggal(?string $tanggal): string
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

    return $namaHari . ', ' . date('d', $timestamp) . ' ' .
        $namaBulan . ' ' . date('Y', $timestamp);
}

function guru_absensi_format_jam(?string $jam): string
{
    if (!$jam) {
        return '-';
    }

    return date('H:i', strtotime($jam));
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
    $_SESSION['flash_error'] = 'Data profil guru tidak ditemukan.';
    redirect_to_dashboard();
    exit;
}

$kelasId = filter_input(INPUT_GET, 'kelas_id', FILTER_VALIDATE_INT);
if (!$kelasId || $kelasId <= 0) {
    $kelasId = filter_input(INPUT_POST, 'kelas_id', FILTER_VALIDATE_INT);
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
        FIELD(k.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'),
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
          AND k.guru_id = :guru_id
        LIMIT 1
    ");

    $stmt->execute([
        'kelas_id' => $kelasId,
        'guru_id'  => $guruId,
    ]);

    $kelas = $stmt->fetch();

    if (!$kelas) {
        $kelasId = null;
    }
}

$siswaList = [];

if ($kelasId && $kelas) {
    $stmt = $pdo->prepare("
        SELECT
            s.id AS siswa_id,
            s.nis,
            s.nama,
            s.jenis_kelamin,
            s.email,
            a.id AS absensi_id,
            a.waktu,
            a.status,
            a.keterangan
        FROM kelas_siswa ks
        INNER JOIN siswa s
            ON s.id = ks.siswa_id
        LEFT JOIN absensi a
            ON a.kelas_id = ks.kelas_id
           AND a.siswa_id = ks.siswa_id
           AND a.tanggal = :tanggal
        WHERE ks.kelas_id = :kelas_id
        ORDER BY
            s.nama ASC
    ");

    $stmt->execute([
        'tanggal' => $tanggal,
        'kelas_id' => $kelasId,
    ]);

    $siswaList = $stmt->fetchAll();
}

$summary = [
    'Hadir'     => 0,
    'Terlambat' => 0,
    'Izin'      => 0,
    'Sakit'     => 0,
    'Alpa'      => 0,
];

$totalSiswa = count($siswaList);
$totalSudahDiabsen = 0;

foreach ($siswaList as $siswa) {
    $status = $siswa['status'] ?? '';

    if (isset($summary[$status])) {
        $summary[$status]++;
        $totalSudahDiabsen++;
    }
}

$belumDiabsen = max(0, $totalSiswa - $totalSudahDiabsen);

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
            <div class="container-fluid">

                <div class="page-header mb-4">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Absensi Siswa
                        </h1>
                        <p class="page-subtitle mb-0">
                            Catat kehadiran siswa berdasarkan kelas dan tanggal.
                        </p>
                    </div>

                    <div class="page-header-actions">
                        <a
                            href="<?= guru_absensi_escape($base_url) ?>/guru/kelas.php"
                            class="btn btn-outline-secondary"
                        >
                            <i class="bi bi-arrow-left me-1"></i>
                            Kembali ke Kelas
                        </a>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                    <div
                        class="alert alert-success alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-check-circle me-2"></i>
                        <?= guru_absensi_escape($successMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div
                        class="alert alert-danger alert-dismissible fade show"
                        role="alert"
                    >
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= guru_absensi_escape($errorMessage) ?>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Tutup"
                        ></button>
                    </div>
                <?php endif; ?>

                <div class="dashboard-card mb-4">
                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title">
                                <i class="bi bi-funnel me-2"></i>
                                Pilih Kelas dan Tanggal
                            </h2>
                            <p class="text-muted mb-0">
                                Pilih kelas yang Anda ajar dan tanggal absensi.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body">
                        <form
                            method="get"
                            action="<?= guru_absensi_escape($base_url) ?>/guru/absensi.php"
                            class="row g-3 align-items-end"
                        >
                            <div class="col-lg-7">
                                <label for="kelas_id" class="form-label">
                                    Kelas <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="kelas_id"
                                    id="kelas_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Pilih Kelas --</option>

                                    <?php foreach ($kelasList as $item): ?>
                                        <option
                                            value="<?= (int) $item['id'] ?>"
                                            <?= $kelasId === (int) $item['id'] ? 'selected' : '' ?>
                                        >
                                            <?= guru_absensi_escape($item['nama_kelas']) ?>
                                            —
                                            <?= guru_absensi_escape($item['hari']) ?>
                                            <?= guru_absensi_format_jam($item['jam_mulai']) ?>
                                            -
                                            <?= guru_absensi_format_jam($item['jam_selesai']) ?>
                                            (<?= (int) $item['jumlah_siswa'] ?> siswa)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <?php if (!$kelasList): ?>
                                    <div class="form-text text-warning">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Anda belum memiliki kelas yang diampu.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-lg-3">
                                <label for="tanggal" class="form-label">
                                    Tanggal <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="date"
                                    name="tanggal"
                                    id="tanggal"
                                    class="form-control"
                                    value="<?= guru_absensi_escape($tanggal) ?>"
                                    max="<?= date('Y-m-d') ?>"
                                    required
                                    data-absensi-date
                                >
                            </div>

                            <div class="col-lg-2">
                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >
                                    <i class="bi bi-search me-1"></i>
                                    Tampilkan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($kelas): ?>

                    <div class="welcome-card mb-4">
                        <div class="welcome-card-content">
                            <div class="welcome-card-icon">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>

                            <div>
                                <h2 class="welcome-card-title mb-1">
                                    <?= guru_absensi_escape($kelas['nama_kelas']) ?>
                                </h2>

                                <p class="welcome-card-text mb-1">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= guru_absensi_escape(guru_absensi_format_tanggal($tanggal)) ?>
                                </p>

                                <p class="welcome-card-text mb-0">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= guru_absensi_format_jam($kelas['jam_mulai']) ?>
                                    -
                                    <?= guru_absensi_format_jam($kelas['jam_selesai']) ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-4 col-lg">
                            <div class="stat-card">
                                <div class="stat-card-icon primary">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="stat-card-content">
                                    <span class="stat-card-label">Total Siswa</span>
                                    <strong class="stat-card-value">
                                        <?= $totalSiswa ?>
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
                                        <?= $summary['Hadir'] ?>
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
                                        <?= $summary['Terlambat'] ?>
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
                                    <span class="stat-card-label">Izin / Sakit</span>
                                    <strong class="stat-card-value">
                                        <?= $summary['Izin'] + $summary['Sakit'] ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-4 col-lg">
                            <div class="stat-card">
                                <div class="stat-card-icon danger">
                                    <i class="bi bi-exclamation-circle"></i>
                                </div>
                                <div class="stat-card-content">
                                    <span class="stat-card-label">Belum Diabsen</span>
                                    <strong class="stat-card-value">
                                        <?= $belumDiabsen ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <h2 class="dashboard-card-title">
                                    Daftar Absensi
                                </h2>
                                <p class="text-muted mb-0">
                                    Atur status kehadiran setiap siswa.
                                </p>
                            </div>

                            <?php if ($totalSiswa > 0): ?>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success"
                                        data-set-all-status="Hadir"
                                    >
                                        <i class="bi bi-check-all me-1"></i>
                                        Semua Hadir
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="dashboard-card-body p-0">

                            <?php if (!$siswaList): ?>

                                <div class="empty-state py-5">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-people"></i>
                                    </div>

                                    <h3 class="empty-state-title">
                                        Belum Ada Siswa
                                    </h3>

                                    <p class="empty-state-text">
                                        Belum ada siswa yang terdaftar pada kelas ini.
                                    </p>

                                    <a
                                        href="<?= guru_absensi_escape($base_url) ?>/guru/kelas.php"
                                        class="btn btn-outline-primary"
                                    >
                                        <i class="bi bi-arrow-left me-1"></i>
                                        Kembali ke Kelas
                                    </a>
                                </div>

                            <?php else: ?>

                                <form
                                    method="post"
                                    action="<?= guru_absensi_escape($base_url) ?>/actions/absensi.php"
                                    data-absensi-form
                                    data-confirm-absensi
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="simpan_massal"
                                    >

                                    <input
                                        type="hidden"
                                        name="kelas_id"
                                        value="<?= (int) $kelas['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="tanggal"
                                        value="<?= guru_absensi_escape($tanggal) ?>"
                                    >

                                    <?php if ($csrfToken): ?>
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= guru_absensi_escape($csrfToken) ?>"
                                        >
                                    <?php endif; ?>

                                    <div class="table-responsive-custom">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 60px;">No</th>
                                                    <th>Siswa</th>
                                                    <th style="width: 130px;">NIS</th>
                                                    <th style="min-width: 420px;">
                                                        Status Kehadiran
                                                    </th>
                                                    <th style="width: 180px;">
                                                        Keterangan
                                                    </th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($siswaList as $index => $siswa): ?>

                                                    <?php
                                                    $siswaId = (int) $siswa['siswa_id'];
                                                    $currentStatus = $siswa['status'] ?? 'Hadir';
                                                    $currentKeterangan = $siswa['keterangan'] ?? '';
                                                    ?>

                                                    <tr
                                                        data-attendance-row
                                                        data-status="<?= guru_absensi_escape($currentStatus) ?>"
                                                    >
                                                        <td>
                                                            <span class="text-muted">
                                                                <?= $index + 1 ?>
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="avatar avatar-sm">
                                                                    <?= guru_absensi_escape(
                                                                        strtoupper(
                                                                            substr(
                                                                                trim($siswa['nama']),
                                                                                0,
                                                                                1
                                                                            )
                                                                        )
                                                                    ) ?>
                                                                </div>

                                                                <div>
                                                                    <div class="fw-semibold">
                                                                        <?= guru_absensi_escape($siswa['nama']) ?>
                                                                    </div>

                                                                    <?php if (!empty($siswa['email'])): ?>
                                                                        <small class="text-muted">
                                                                            <?= guru_absensi_escape($siswa['email']) ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="font-monospace">
                                                                <?= guru_absensi_escape($siswa['nis']) ?>
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div
                                                                class="attendance-status-options"
                                                                data-status-option-group
                                                            >
                                                                <?php
                                                                $statusOptions = [
                                                                    'Hadir' => [
                                                                        'icon'  => 'bi-check-circle',
                                                                        'class' => 'success',
                                                                    ],
                                                                    'Terlambat' => [
                                                                        'icon'  => 'bi-clock-history',
                                                                        'class' => 'warning',
                                                                    ],
                                                                    'Izin' => [
                                                                        'icon'  => 'bi-envelope',
                                                                        'class' => 'info',
                                                                    ],
                                                                    'Sakit' => [
                                                                        'icon'  => 'bi-thermometer-half',
                                                                        'class' => 'danger',
                                                                    ],
                                                                    'Alpa' => [
                                                                        'icon'  => 'bi-x-circle',
                                                                        'class' => 'secondary',
                                                                    ],
                                                                ];
                                                                ?>

                                                                <?php foreach ($statusOptions as $status => $option): ?>
                                                                    <label
                                                                        class="attendance-status-option <?= $currentStatus === $status ? 'selected' : '' ?>"
                                                                        data-status-option
                                                                        data-status="<?= guru_absensi_escape($status) ?>"
                                                                    >
                                                                        <input
                                                                            type="radio"
                                                                            name="status[<?= $siswaId ?>]"
                                                                            value="<?= guru_absensi_escape($status) ?>"
                                                                            <?= $currentStatus === $status ? 'checked' : '' ?>
                                                                            data-absensi-status
                                                                            class="visually-hidden"
                                                                        >

                                                                        <span class="attendance-status-option-icon <?= guru_absensi_escape($option['class']) ?>">
                                                                            <i class="bi <?= guru_absensi_escape($option['icon']) ?>"></i>
                                                                        </span>

                                                                        <span>
                                                                            <?= guru_absensi_escape($status) ?>
                                                                        </span>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>

                                                            <div class="mt-2">
                                                                <input
                                                                    type="time"
                                                                    name="waktu[<?= $siswaId ?>]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?= guru_absensi_escape($siswa['waktu'] ?? date('H:i')) ?>"
                                                                    aria-label="Waktu absensi <?= guru_absensi_escape($siswa['nama']) ?>"
                                                                    style="max-width: 150px;"
                                                                >
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <input
                                                                type="text"
                                                                name="keterangan[<?= $siswaId ?>]"
                                                                class="form-control form-control-sm"
                                                                value="<?= guru_absensi_escape($currentKeterangan) ?>"
                                                                placeholder="Opsional"
                                                                maxlength="255"
                                                            >
                                                        </td>
                                                    </tr>

                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="p-3 border-top bg-light">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                            <div class="text-muted small">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Data absensi pada tanggal
                                                <strong>
                                                    <?= guru_absensi_escape(guru_absensi_format_tanggal($tanggal)) ?>
                                                </strong>
                                                akan dibuat atau diperbarui.
                                            </div>

                                            <div class="d-flex gap-2">
                                                <a
                                                    href="<?= guru_absensi_escape($base_url) ?>/guru/detail_kelas.php?id=<?= (int) $kelas['id'] ?>"
                                                    class="btn btn-outline-secondary"
                                                >
                                                    Batal
                                                </a>

                                                <button
                                                    type="submit"
                                                    class="btn btn-primary"
                                                    data-submit-absensi
                                                >
                                                    <span
                                                        class="spinner-border spinner-border-sm me-1 d-none"
                                                        data-submit-spinner
                                                        aria-hidden="true"
                                                    ></span>

                                                    <i class="bi bi-save me-1"></i>
                                                    Simpan Absensi
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

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
                                Pilih kelas dan tanggal di atas untuk mulai mencatat absensi siswa.
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
                                Anda belum memiliki kelas yang diampu.
                            </p>

                            <a
                                href="<?= guru_absensi_escape($base_url) ?>/guru/dashboard.php"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-speedometer2 me-1"></i>
                                Kembali ke Dashboard
                            </a>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </main>

        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>