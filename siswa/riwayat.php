<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('siswa');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$base_url = '/absensi-sekolah';
$page_title = 'Riwayat Absensi';

$userId = current_user_id();

function riwayat_status_badge(string $status): string
{
    $map = [
        'Hadir' => 'badge-hadir',
        'Terlambat' => 'badge-terlambat',
        'Izin' => 'badge-izin',
        'Sakit' => 'badge-sakit',
        'Alpa' => 'badge-alpa',
    ];

    $class = $map[$status] ?? 'bg-secondary';

    return '<span class="badge ' . htmlspecialchars($class) . '">' .
        htmlspecialchars($status) .
        '</span>';
}

function riwayat_format_tanggal(?string $tanggal): string
{
    if (!$tanggal) {
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

    $timestamp = strtotime($tanggal);

    if (!$timestamp) {
        return htmlspecialchars($tanggal);
    }

    return date('d', $timestamp) . ' ' .
        $bulan[(int) date('n', $timestamp)] . ' ' .
        date('Y', $timestamp);
}

function riwayat_format_waktu(?string $waktu): string
{
    if (!$waktu) {
        return '-';
    }

    return date('H:i', strtotime($waktu));
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.username,
        s.id AS siswa_id,
        s.nis,
        s.nama,
        s.jenis_kelamin,
        s.email,
        s.no_hp
    FROM users u
    INNER JOIN siswa s
        ON s.user_id = u.id
    WHERE u.id = :user_id
      AND u.role = 'siswa'
    LIMIT 1
");

$stmt->execute([
    ':user_id' => $userId
]);

$siswa = $stmt->fetch();

if (!$siswa) {
    $_SESSION['error'] = 'Data siswa tidak ditemukan.';
    header('Location: ' . $base_url . '/siswa/dashboard.php');
    exit;
}

$siswaId = (int) $siswa['siswa_id'];

$kelasId = isset($_GET['kelas_id'])
    ? filter_var($_GET['kelas_id'], FILTER_VALIDATE_INT)
    : null;

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$tanggalMulai = isset($_GET['tanggal_mulai'])
    ? trim($_GET['tanggal_mulai'])
    : '';

$tanggalSelesai = isset($_GET['tanggal_selesai'])
    ? trim($_GET['tanggal_selesai'])
    : '';

$allowedStatuses = [
    'Hadir',
    'Terlambat',
    'Izin',
    'Sakit',
    'Alpa'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

if ($kelasId !== null && $kelasId <= 0) {
    $kelasId = null;
}

if (
    $tanggalMulai !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMulai)
) {
    $tanggalMulai = '';
}

if (
    $tanggalSelesai !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSelesai)
) {
    $tanggalSelesai = '';
}

if (
    $tanggalMulai !== '' &&
    $tanggalSelesai !== '' &&
    $tanggalMulai > $tanggalSelesai
) {
    [$tanggalMulai, $tanggalSelesai] = [
        $tanggalSelesai,
        $tanggalMulai
    ];
}

$stmt = $pdo->prepare("
    SELECT
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.nama AS nama_guru
    FROM kelas_siswa ks
    INNER JOIN kelas k
        ON k.id = ks.kelas_id
    INNER JOIN guru g
        ON g.id = k.guru_id
    WHERE ks.siswa_id = :siswa_id
    ORDER BY k.nama_kelas ASC
");

$stmt->execute([
    ':siswa_id' => $siswaId
]);

$kelasList = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status = 'Alpa' THEN 1 ELSE 0 END) AS alpa
    FROM absensi a
    WHERE a.siswa_id = :siswa_id
");

$stmt->execute([
    ':siswa_id' => $siswaId
]);

$statistik = $stmt->fetch() ?: [];

$totalAbsensi = (int) ($statistik['total'] ?? 0);
$totalHadir = (int) ($statistik['hadir'] ?? 0);
$totalTerlambat = (int) ($statistik['terlambat'] ?? 0);
$totalIzin = (int) ($statistik['izin'] ?? 0);
$totalSakit = (int) ($statistik['sakit'] ?? 0);
$totalAlpa = (int) ($statistik['alpa'] ?? 0);

$persentaseKehadiran = $totalAbsensi > 0
    ? round((($totalHadir + $totalTerlambat) / $totalAbsensi) * 100, 1)
    : 0;

$perPage = 15;

$page = isset($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT)
    : 1;

if (!$page || $page < 1) {
    $page = 1;
}

$where = [
    'a.siswa_id = :siswa_id'
];

$params = [
    ':siswa_id' => $siswaId
];

if ($kelasId !== null) {
    $where[] = 'a.kelas_id = :kelas_id';
    $params[':kelas_id'] = $kelasId;
}

if ($status !== '') {
    $where[] = 'a.status = :status';
    $params[':status'] = $status;
}

if ($tanggalMulai !== '') {
    $where[] = 'a.tanggal >= :tanggal_mulai';
    $params[':tanggal_mulai'] = $tanggalMulai;
}

if ($tanggalSelesai !== '') {
    $where[] = 'a.tanggal <= :tanggal_selesai';
    $params[':tanggal_selesai'] = $tanggalSelesai;
}

$whereSql = implode(' AND ', $where);

$countSql = "
    SELECT COUNT(*)
    FROM absensi a
    WHERE {$whereSql}
";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);

$totalRows = (int) $stmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalRows / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        a.id,
        a.kelas_id,
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.nama AS nama_guru
    FROM absensi a
    INNER JOIN kelas k
        ON k.id = a.kelas_id
    INNER JOIN guru g
        ON g.id = k.guru_id
    WHERE {$whereSql}
    ORDER BY a.tanggal DESC, a.waktu DESC, k.nama_kelas ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(
        $key,
        $value,
        is_int($value)
            ? PDO::PARAM_INT
            : PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$riwayat = $stmt->fetchAll();

$paginationParams = [];

if ($kelasId !== null) {
    $paginationParams['kelas_id'] = $kelasId;
}

if ($status !== '') {
    $paginationParams['status'] = $status;
}

if ($tanggalMulai !== '') {
    $paginationParams['tanggal_mulai'] = $tanggalMulai;
}

if ($tanggalSelesai !== '') {
    $paginationParams['tanggal_selesai'] = $tanggalSelesai;
}

function riwayat_page_url(
    string $baseUrl,
    array $params,
    int $page
): string {
    $params['page'] = $page;

    return $baseUrl . '/siswa/riwayat.php?' .
        http_build_query($params);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="page-header mb-4">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-clock-history me-2"></i>
                    Riwayat Absensi
                </h1>

                <p class="page-subtitle">
                    Lihat seluruh riwayat kehadiran Anda.
                </p>
            </div>
        </div>

        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">

                    <div class="avatar avatar-lg">
                        <?= htmlspecialchars(
                            strtoupper(
                                substr(
                                    $siswa['nama'] ?: $siswa['username'],
                                    0,
                                    1
                                )
                            )
                        ) ?>
                    </div>

                    <div>
                        <h5 class="mb-1">
                            <?= htmlspecialchars($siswa['nama']) ?>
                        </h5>

                        <div class="text-muted small">
                            NIS:
                            <strong>
                                <?= htmlspecialchars($siswa['nis']) ?>
                            </strong>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">

            <div class="col-6 col-xl">
                <div class="stat-card">
                    <div class="stat-card-icon primary">
                        <i class="bi bi-calendar-check"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Total
                        </div>

                        <div class="stat-card-value">
                            <?= number_format($totalAbsensi) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl">
                <div class="stat-card">
                    <div class="stat-card-icon success">
                        <i class="bi bi-check-circle"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Hadir
                        </div>

                        <div class="stat-card-value">
                            <?= number_format($totalHadir) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl">
                <div class="stat-card">
                    <div class="stat-card-icon warning">
                        <i class="bi bi-clock"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Terlambat
                        </div>

                        <div class="stat-card-value">
                            <?= number_format($totalTerlambat) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl">
                <div class="stat-card">
                    <div class="stat-card-icon info">
                        <i class="bi bi-envelope"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Izin
                        </div>

                        <div class="stat-card-value">
                            <?= number_format($totalIzin) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl">
                <div class="stat-card">
                    <div class="stat-card-icon danger">
                        <i class="bi bi-x-circle"></i>
                    </div>

                    <div class="stat-card-content">
                        <div class="stat-card-label">
                            Alpa
                        </div>

                        <div class="stat-card-value">
                            <?= number_format($totalAlpa) ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="card dashboard-card mb-4">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>
                            Persentase Kehadiran
                        </strong>

                        <div class="text-muted small">
                            Hadir dan terlambat dibandingkan seluruh absensi.
                        </div>
                    </div>

                    <strong class="fs-5">
                        <?= number_format($persentaseKehadiran, 1) ?>%
                    </strong>
                </div>

                <div
                    class="progress"
                    style="height: 10px;"
                >
                    <div
                        class="progress-bar"
                        role="progressbar"
                        style="width: <?= min(100, max(0, $persentaseKehadiran)) ?>%;"
                        aria-valuenow="<?= $persentaseKehadiran ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>

            </div>
        </div>

        <div class="card dashboard-card mb-4">

            <div class="dashboard-card-header">
                <div>
                    <h5 class="dashboard-card-title mb-1">
                        Filter Riwayat
                    </h5>

                    <p class="text-muted small mb-0">
                        Gunakan filter untuk menemukan data absensi tertentu.
                    </p>
                </div>
            </div>

            <div class="dashboard-card-body">

                <form
                    method="get"
                    action="<?= htmlspecialchars($base_url) ?>/siswa/riwayat.php"
                >

                    <div class="row g-3">

                        <div class="col-md-6 col-lg-3">
                            <label
                                for="kelas_id"
                                class="form-label"
                            >
                                Kelas
                            </label>

                            <select
                                name="kelas_id"
                                id="kelas_id"
                                class="form-select"
                            >
                                <option value="">
                                    Semua kelas
                                </option>

                                <?php foreach ($kelasList as $kelas): ?>
                                    <option
                                        value="<?= (int) $kelas['id'] ?>"
                                        <?= $kelasId === (int) $kelas['id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <label
                                for="status"
                                class="form-label"
                            >
                                Status
                            </label>

                            <select
                                name="status"
                                id="status"
                                class="form-select"
                            >
                                <option value="">
                                    Semua status
                                </option>

                                <?php foreach ($allowedStatuses as $statusOption): ?>
                                    <option
                                        value="<?= htmlspecialchars($statusOption) ?>"
                                        <?= $status === $statusOption
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars($statusOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 col-lg-2">
                            <label
                                for="tanggal_mulai"
                                class="form-label"
                            >
                                Dari
                            </label>

                            <input
                                type="date"
                                name="tanggal_mulai"
                                id="tanggal_mulai"
                                class="form-control"
                                value="<?= htmlspecialchars($tanggalMulai) ?>"
                            >
                        </div>

                        <div class="col-md-6 col-lg-2">
                            <label
                                for="tanggal_selesai"
                                class="form-label"
                            >
                                Sampai
                            </label>

                            <input
                                type="date"
                                name="tanggal_selesai"
                                id="tanggal_selesai"
                                class="form-control"
                                value="<?= htmlspecialchars($tanggalSelesai) ?>"
                            >
                        </div>

                        <div class="col-lg-2 d-flex align-items-end gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary flex-grow-1"
                            >
                                <i class="bi bi-funnel me-1"></i>
                                Filter
                            </button>

                            <a
                                href="<?= htmlspecialchars($base_url) ?>/siswa/riwayat.php"
                                class="btn btn-outline-secondary"
                                title="Reset filter"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>

                        </div>

                    </div>

                </form>

            </div>
        </div>

        <div class="card dashboard-card">

            <div class="dashboard-card-header">

                <div>
                    <h5 class="dashboard-card-title mb-1">
                        Daftar Riwayat
                    </h5>

                    <p class="text-muted small mb-0">
                        Menampilkan
                        <?= number_format($totalRows) ?>
                        data absensi.
                    </p>
                </div>

                <?php if ($totalRows > 0): ?>
                    <span class="badge bg-light text-dark">
                        Halaman <?= $page ?> dari <?= $totalPages ?>
                    </span>
                <?php endif; ?>

            </div>

            <div class="dashboard-card-body p-0">

                <?php if (!empty($riwayat)): ?>

                    <div class="table-responsive-custom">

                        <table class="table table-hover align-middle mb-0">

                            <thead>
                                <tr>
                                    <th class="ps-4">
                                        #
                                    </th>

                                    <th>
                                        Tanggal
                                    </th>

                                    <th>
                                        Kelas
                                    </th>

                                    <th>
                                        Guru
                                    </th>

                                    <th>
                                        Waktu
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Keterangan
                                    </th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($riwayat as $index => $item): ?>

                                    <tr>

                                        <td class="ps-4 text-muted">
                                            <?= $offset + $index + 1 ?>
                                        </td>

                                        <td>
                                            <div class="fw-semibold">
                                                <?= riwayat_format_tanggal($item['tanggal']) ?>
                                            </div>

                                            <div class="text-muted small">
                                                <?= htmlspecialchars($item['hari']) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars($item['nama_kelas']) ?>
                                            </div>

                                            <div class="text-muted small">
                                                <?= htmlspecialchars(
                                                    substr($item['jam_mulai'], 0, 5)
                                                ) ?>
                                                -
                                                <?= htmlspecialchars(
                                                    substr($item['jam_selesai'], 0, 5)
                                                ) ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($item['nama_guru']) ?>
                                        </td>

                                        <td>
                                            <?= riwayat_format_waktu($item['waktu']) ?>
                                        </td>

                                        <td>
                                            <?= riwayat_status_badge($item['status']) ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($item['keterangan'])): ?>
                                                <span
                                                    title="<?= htmlspecialchars($item['keterangan']) ?>"
                                                >
                                                    <?= htmlspecialchars(
                                                        strlen($item['keterangan']) > 50
                                                            ? substr($item['keterangan'], 0, 50) . '...'
                                                            : $item['keterangan']
                                                    ) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    -
                                                </span>
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
                            <i class="bi bi-calendar-x"></i>
                        </div>

                        <h5 class="mt-3">
                            Tidak ada riwayat absensi
                        </h5>

                        <p class="text-muted mb-0">
                            Belum ada data yang sesuai dengan filter yang dipilih.
                        </p>

                        <?php if (
                            $kelasId !== null ||
                            $status !== '' ||
                            $tanggalMulai !== '' ||
                            $tanggalSelesai !== ''
                        ): ?>

                            <a
                                href="<?= htmlspecialchars($base_url) ?>/siswa/riwayat.php"
                                class="btn btn-outline-primary mt-3"
                            >
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Reset Filter
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

            <?php if ($totalPages > 1): ?>

                <div class="card-footer bg-white border-top">

                    <nav aria-label="Navigasi halaman">

                        <ul class="pagination justify-content-center mb-0">
                            <li
                                class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"
                            >
                                <?php if ($page > 1): ?>
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            riwayat_page_url(
                                                $base_url,
                                                $paginationParams,
                                                $page - 1
                                            )
                                        ) ?>"
                                    >
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="page-link">
                                        <i class="bi bi-chevron-left"></i>
                                    </span>
                                <?php endif; ?>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            ?>

                            <?php if ($startPage > 1): ?>

                                <li class="page-item">
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            riwayat_page_url(
                                                $base_url,
                                                $paginationParams,
                                                1
                                            )
                                        ) ?>"
                                    >
                                        1
                                    </a>
                                </li>

                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            ...
                                        </span>
                                    </li>
                                <?php endif; ?>

                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>

                                <li
                                    class="page-item <?= $i === $page ? 'active' : '' ?>"
                                >
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            riwayat_page_url(
                                                $base_url,
                                                $paginationParams,
                                                $i
                                            )
                                        ) ?>"
                                    >
                                        <?= $i ?>
                                    </a>
                                </li>

                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>

                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            ...
                                        </span>
                                    </li>
                                <?php endif; ?>

                                <li class="page-item">
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            riwayat_page_url(
                                                $base_url,
                                                $paginationParams,
                                                $totalPages
                                            )
                                        ) ?>"
                                    >
                                        <?= $totalPages ?>
                                    </a>
                                </li>

                            <?php endif; ?>

                            <li
                                class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"
                            >
                                <?php if ($page < $totalPages): ?>
                                    <a
                                        class="page-link"
                                        href="<?= htmlspecialchars(
                                            riwayat_page_url(
                                                $base_url,
                                                $paginationParams,
                                                $page + 1
                                            )
                                        ) ?>"
                                    >
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="page-link">
                                        <i class="bi bi-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </li>

                        </ul>

                    </nav>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>