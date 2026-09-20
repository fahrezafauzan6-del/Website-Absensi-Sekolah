<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('guru');

$page_title = 'Detail Kelas';

$base_url = '/absensi-sekolah';

function guru_detail_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guru_detail_format_jam(?string $time): string
{
    if (!$time) {
        return '-';
    }

    $timestamp = strtotime($time);

    return $timestamp
        ? date('H:i', $timestamp)
        : '-';
}

function guru_detail_format_tanggal(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '-';
    }

    return date('d', $timestamp) . ' ' .
        $bulan[(int) date('n', $timestamp)] . ' ' .
        date('Y', $timestamp);
}

function guru_detail_status_badge(?string $status): string
{
    $status = (string) $status;

    $map = [
        'Hadir' => [
            'class' => 'badge-hadir',
            'icon' => 'bi-check-circle',
        ],
        'Terlambat' => [
            'class' => 'badge-terlambat',
            'icon' => 'bi-clock-history',
        ],
        'Izin' => [
            'class' => 'badge-izin',
            'icon' => 'bi-envelope-paper',
        ],
        'Sakit' => [
            'class' => 'badge-sakit',
            'icon' => 'bi-heart-pulse',
        ],
        'Alpa' => [
            'class' => 'badge-alpa',
            'icon' => 'bi-x-circle',
        ],
    ];

    $item = $map[$status] ?? [
        'class' => 'bg-secondary-subtle text-secondary',
        'icon' => 'bi-question-circle',
    ];

    return '<span class="badge ' . $item['class'] . '">' .
        '<i class="bi ' . $item['icon'] . ' me-1"></i>' .
        guru_detail_escape($status ?: 'Tidak ada') .
        '</span>';
}

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($id === false || $id === null) {
    header(
        'Location: ' .
            $base_url .
            '/guru/kelas.php?error=invalid_id'
    );
    exit;
}

$guru_user_id = current_user_id();

if (!$guru_user_id) {
    redirect_to_dashboard();
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        k.id,
        k.nama_kelas,
        k.guru_id,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.nip AS guru_nip,
        g.nama AS guru_nama,
        g.email AS guru_email,
        g.no_hp AS guru_no_hp
    FROM kelas k
    INNER JOIN guru g
        ON g.id = k.guru_id
    WHERE k.id = :kelas_id
            AND g.user_id = :guru_user_id
    LIMIT 1
");

$stmt->execute([
    ':kelas_id' => $id,
    ':guru_user_id' => $guru_user_id,
]);

$kelas = $stmt->fetch();

if (!$kelas) {
    $_SESSION['flash_error'] =
        'Kelas tidak ditemukan atau Anda tidak memiliki akses ke kelas tersebut.';

    header(
        'Location: ' .
            $base_url .
            '/guru/kelas.php'
    );
    exit;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM kelas_siswa
    WHERE kelas_id = :kelas_id
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$total_siswa = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM absensi
    WHERE kelas_id = :kelas_id
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$total_absensi = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare("
    SELECT
        status,
        COUNT(*) AS jumlah
    FROM absensi
    WHERE kelas_id = :kelas_id
    GROUP BY status
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$status_rows = $stmt->fetchAll();

$status_counts = [
    'Hadir' => 0,
    'Terlambat' => 0,
    'Izin' => 0,
    'Sakit' => 0,
    'Alpa' => 0,
];

foreach ($status_rows as $row) {
    if (array_key_exists($row['status'], $status_counts)) {
        $status_counts[$row['status']] =
            (int) $row['jumlah'];
    }
}

$total_hadir = $status_counts['Hadir'];
$total_terlambat = $status_counts['Terlambat'];

$attendance_percentage = $total_absensi > 0
    ? (($total_hadir + $total_terlambat) / $total_absensi) * 100
    : 0;

$stmt = $pdo->prepare("
    SELECT
        s.id AS siswa_id,
        s.nis,
        s.nama,
        s.jenis_kelamin,
        s.email,
        s.no_hp,

        COUNT(a.id) AS total_absensi,

        SUM(
            CASE
                WHEN a.status = 'Hadir' THEN 1
                ELSE 0
            END
        ) AS jumlah_hadir,

        SUM(
            CASE
                WHEN a.status = 'Terlambat' THEN 1
                ELSE 0
            END
        ) AS jumlah_terlambat,

        SUM(
            CASE
                WHEN a.status = 'Izin' THEN 1
                ELSE 0
            END
        ) AS jumlah_izin,

        SUM(
            CASE
                WHEN a.status = 'Sakit' THEN 1
                ELSE 0
            END
        ) AS jumlah_sakit,

        SUM(
            CASE
                WHEN a.status = 'Alpa' THEN 1
                ELSE 0
            END
        ) AS jumlah_alpa

    FROM kelas_siswa ks

    INNER JOIN siswa s
        ON s.id = ks.siswa_id

    LEFT JOIN absensi a
        ON a.kelas_id = ks.kelas_id
       AND a.siswa_id = ks.siswa_id

    WHERE ks.kelas_id = :kelas_id

    GROUP BY
        s.id,
        s.nis,
        s.nama,
        s.jenis_kelamin,
        s.email,
        s.no_hp

    ORDER BY s.nama ASC
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$siswa_list = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        s.id AS siswa_id,
        s.nis,
        s.nama AS siswa_nama
    FROM absensi a
    INNER JOIN siswa s
        ON s.id = a.siswa_id
    WHERE a.kelas_id = :kelas_id
    ORDER BY
        a.tanggal DESC,
        a.waktu DESC,
        a.id DESC
    LIMIT 10
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$recent_attendance = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT MAX(tanggal)
    FROM absensi
    WHERE kelas_id = :kelas_id
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$last_attendance_date = $stmt->fetchColumn();

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';

unset(
    $_SESSION['flash_success'],
    $_SESSION['flash_error']
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="dashboard-page">
        <div class="page-header mb-4">
            <div>
                <h1 class="page-title mb-1">
                    <i class="bi bi-journal-bookmark me-2"></i>
                    <?= guru_detail_escape($kelas['nama_kelas']) ?>
                </h1>

                <p class="page-subtitle mb-0">
                    Detail kelas dan rekapitulasi kehadiran siswa.
                </p>
            </div>

            <div class="page-header-actions d-flex gap-2">
            </div>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= guru_detail_escape($flash_success) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?= guru_detail_escape($flash_error) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <div class="dashboard-card mb-4">

            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title mb-1">
                        Informasi Kelas
                    </h2>

                    <p class="text-muted small mb-0">
                        Informasi jadwal dan guru pengampu.
                    </p>
                </div>

                <span class="badge bg-primary-subtle text-primary">
                    Kelas #<?= (int) $kelas['id'] ?>
                </span>
            </div>

            <div class="dashboard-card-body">

                <div class="row g-4">

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="profile-info">
                            <span class="profile-info-label">
                                <i class="bi bi-journal-text me-1"></i>
                                Nama Kelas
                            </span>

                            <strong class="profile-info-value">
                                <?= guru_detail_escape($kelas['nama_kelas']) ?>
                            </strong>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="profile-info">
                            <span class="profile-info-label">
                                <i class="bi bi-calendar3 me-1"></i>
                                Hari
                            </span>

                            <strong class="profile-info-value">
                                <?= guru_detail_escape($kelas['hari']) ?>
                            </strong>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="profile-info">
                            <span class="profile-info-label">
                                <i class="bi bi-clock me-1"></i>
                                Jam
                            </span>

                            <strong class="profile-info-value">
                                <?= guru_detail_format_jam($kelas['jam_mulai']) ?>
                                -
                                <?= guru_detail_format_jam($kelas['jam_selesai']) ?>
                            </strong>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="profile-info">
                            <span class="profile-info-label">
                                <i class="bi bi-person-badge me-1"></i>
                                Guru
                            </span>

                            <strong class="profile-info-value">
                                <?= guru_detail_escape($kelas['guru_nama']) ?>
                            </strong>

                            <?php if (!empty($kelas['guru_nip'])): ?>
                                <span class="small text-muted">
                                    NIP <?= guru_detail_escape($kelas['guru_nip']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon primary">
                        <i class="bi bi-people"></i>
                    </div>

                    <div class="stat-card-content">
                        <span class="stat-card-label">
                            Total Siswa
                        </span>

                        <strong class="stat-card-value">
                            <?= number_format($total_siswa) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon info">
                        <i class="bi bi-clipboard-check"></i>
                    </div>

                    <div class="stat-card-content">
                        <span class="stat-card-label">
                            Total Absensi
                        </span>

                        <strong class="stat-card-value">
                            <?= number_format($total_absensi) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon success">
                        <i class="bi bi-check-circle"></i>
                    </div>

                    <div class="stat-card-content">
                        <span class="stat-card-label">
                            Hadir
                        </span>

                        <strong class="stat-card-value">
                            <?= number_format($total_hadir) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card h-100">
                    <div class="stat-card-icon warning">
                        <i class="bi bi-percent"></i>
                    </div>

                    <div class="stat-card-content">
                        <span class="stat-card-label">
                            Kehadiran
                        </span>

                        <strong class="stat-card-value">
                            <?= number_format($attendance_percentage, 1) ?>%
                        </strong>
                    </div>
                </div>
            </div>

        </div>

        <div class="dashboard-card mb-4">

            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title mb-1">
                        Ringkasan Kehadiran
                    </h2>

                    <p class="text-muted small mb-0">
                        Distribusi status absensi seluruh siswa di kelas.
                    </p>
                </div>

                <?php if ($last_attendance_date): ?>
                    <span class="small text-muted">
                        Terakhir:
                        <?= guru_detail_format_tanggal($last_attendance_date) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="dashboard-card-body">

                <div class="row g-3">

                    <div class="col-6 col-md">
                        <div class="attendance-summary">
                            <div class="attendance-summary-icon hadir">
                                <i class="bi bi-check-circle"></i>
                            </div>

                            <div>
                                <span class="attendance-summary-label">
                                    Hadir
                                </span>

                                <strong class="attendance-summary-value">
                                    <?= number_format($status_counts['Hadir']) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md">
                        <div class="attendance-summary">
                            <div class="attendance-summary-icon terlambat">
                                <i class="bi bi-clock-history"></i>
                            </div>

                            <div>
                                <span class="attendance-summary-label">
                                    Terlambat
                                </span>

                                <strong class="attendance-summary-value">
                                    <?= number_format($status_counts['Terlambat']) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md">
                        <div class="attendance-summary">
                            <div class="attendance-summary-icon izin">
                                <i class="bi bi-envelope-paper"></i>
                            </div>

                            <div>
                                <span class="attendance-summary-label">
                                    Izin
                                </span>

                                <strong class="attendance-summary-value">
                                    <?= number_format($status_counts['Izin']) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md">
                        <div class="attendance-summary">
                            <div class="attendance-summary-icon sakit">
                                <i class="bi bi-heart-pulse"></i>
                            </div>

                            <div>
                                <span class="attendance-summary-label">
                                    Sakit
                                </span>

                                <strong class="attendance-summary-value">
                                    <?= number_format($status_counts['Sakit']) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md">
                        <div class="attendance-summary">
                            <div class="attendance-summary-icon alpa">
                                <i class="bi bi-x-circle"></i>
                            </div>

                            <div>
                                <span class="attendance-summary-label">
                                    Alpa
                                </span>

                                <strong class="attendance-summary-value">
                                    <?= number_format($status_counts['Alpa']) ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="dashboard-card h-100">

                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title mb-1">
                                Daftar Siswa
                            </h2>

                            <p class="text-muted small mb-0">
                                <?= number_format($total_siswa) ?> siswa terdaftar.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body">

                        <?php if (empty($siswa_list)): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-people"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Belum Ada Siswa
                                </h3>

                                <p class="empty-state-text">
                                    Belum ada siswa yang terdaftar di kelas ini.
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="table-responsive-custom">
                                <table class="table align-middle mb-0">

                                    <thead>
                                        <tr>
                                            <th>Siswa</th>
                                            <th class="text-center">Hadir</th>
                                            <th class="text-center">Izin</th>
                                            <th class="text-center">Sakit</th>
                                            <th class="text-center">Alpa</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php foreach ($siswa_list as $siswa): ?>

                                            <?php
                                            $siswa_hadir =
                                                (int) $siswa['jumlah_hadir'];

                                            $siswa_terlambat =
                                                (int) $siswa['jumlah_terlambat'];

                                            $siswa_total =
                                                (int) $siswa['total_absensi'];

                                            $siswa_percentage =
                                                $siswa_total > 0
                                                ? (($siswa_hadir + $siswa_terlambat) / $siswa_total) * 100
                                                : 0;
                                            ?>

                                            <tr>

                                                <td>
                                                    <div class="d-flex align-items-center gap-3">

                                                        <div class="avatar avatar-primary">
                                                            <?= guru_detail_escape(
                                                                strtoupper(
                                                                    mb_substr(
                                                                        $siswa['nama'],
                                                                        0,
                                                                        1
                                                                    )
                                                                )
                                                            ) ?>
                                                        </div>

                                                        <div>
                                                            <div class="fw-semibold">
                                                                <?= guru_detail_escape($siswa['nama']) ?>
                                                            </div>

                                                            <div class="small text-muted">
                                                                NIS:
                                                                <?= guru_detail_escape($siswa['nis']) ?>
                                                            </div>

                                                            <?php if ($siswa_total > 0): ?>
                                                                <div class="small text-muted">
                                                                    Kehadiran:
                                                                    <?= number_format($siswa_percentage, 1) ?>%
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>

                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-hadir">
                                                        <?= number_format($siswa_hadir) ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-izin">
                                                        <?= number_format((int) $siswa['jumlah_izin']) ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-sakit">
                                                        <?= number_format((int) $siswa['jumlah_sakit']) ?>
                                                    </span>
                                                </td>

                                                <td class="text-center">
                                                    <span class="badge badge-alpa">
                                                        <?= number_format((int) $siswa['jumlah_alpa']) ?>
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
            </div>

            <div class="col-12 col-xl-5">
                <div class="dashboard-card h-100">

                    <div class="dashboard-card-header">
                        <div>
                            <h2 class="dashboard-card-title mb-1">
                                Absensi Terbaru
                            </h2>

                            <p class="text-muted small mb-0">
                                10 data absensi terakhir.
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card-body p-0">

                        <?php if (empty($recent_attendance)): ?>

                            <div class="empty-state py-5">
                                <div class="empty-state-icon">
                                    <i class="bi bi-clipboard-x"></i>
                                </div>

                                <h3 class="empty-state-title">
                                    Belum Ada Absensi
                                </h3>

                                <p class="empty-state-text">
                                    Belum ada data absensi untuk kelas ini.
                                </p>
                            </div>

                        <?php else: ?>

                            <div class="list-group list-group-flush">

                                <?php foreach ($recent_attendance as $attendance): ?>

                                    <div class="list-group-item px-3 py-3">

                                        <div class="d-flex justify-content-between align-items-start gap-3">

                                            <div class="min-w-0">

                                                <div class="fw-semibold text-truncate">
                                                    <?= guru_detail_escape(
                                                        $attendance['siswa_nama']
                                                    ) ?>
                                                </div>

                                                <div class="small text-muted">
                                                    NIS:
                                                    <?= guru_detail_escape(
                                                        $attendance['nis']
                                                    ) ?>
                                                </div>

                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?= guru_detail_format_tanggal(
                                                        $attendance['tanggal']
                                                    ) ?>

                                                    <?php if (!empty($attendance['waktu'])): ?>
                                                        ·
                                                        <?= guru_detail_format_jam(
                                                            $attendance['waktu']
                                                        ) ?>
                                                    <?php endif; ?>
                                                </div>

                                            </div>

                                            <div class="text-end flex-shrink-0">

                                                <?= guru_detail_status_badge(
                                                    $attendance['status']
                                                ) ?>

                                                <?php if (!empty($attendance['keterangan'])): ?>
                                                    <div
                                                        class="small text-muted mt-1"
                                                        title="<?= guru_detail_escape($attendance['keterangan']) ?>">
                                                        <i class="bi bi-chat-left-text me-1"></i>
                                                        <?= guru_detail_escape(
                                                            mb_strimwidth(
                                                                $attendance['keterangan'],
                                                                0,
                                                                35,
                                                                '...'
                                                            )
                                                        ) ?>
                                                    </div>
                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-4">

                <a
                    href="<?= htmlspecialchars($base_url) ?>/guru/kelas.php"
                    class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Kembali
                </a>

                <a
                    href="<?= guru_detail_escape($base_url) ?>/guru/laporan.php?kelas_id=<?= (int) $id ?>"
                    class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i>
                    Laporan
                </a>

            </div>
        </div>

    </div>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>