<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('guru');

$page_title = 'Kelas Saya';
$additional_js = ['dashboard.js'];

$base_url = '/absensi-sekolah';

function guru_kelas_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function guru_kelas_hari_badge(string $hari): string
{
    $classes = [
        'Senin'  => 'bg-primary-subtle text-primary',
        'Selasa' => 'bg-success-subtle text-success',
        'Rabu'   => 'bg-info-subtle text-info',
        'Kamis'  => 'bg-warning-subtle text-warning',
        'Jumat'  => 'bg-danger-subtle text-danger',
        'Sabtu'  => 'bg-secondary-subtle text-secondary',
    ];

    $class = $classes[$hari] ?? 'bg-secondary-subtle text-secondary';

    return '<span class="badge ' . $class . '">' .
        guru_kelas_escape($hari) .
        '</span>';
}

function guru_kelas_format_jam(?string $time): string
{
    if (!$time) {
        return '-';
    }

    return date('H:i', strtotime($time));
}

function guru_kelas_format_tanggal(?string $date): string
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

$guru_id = current_user_id();

if (!$guru_id) {
    redirect_to_dashboard();
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        g.id,
        g.user_id,
        g.nip,
        g.nama,
        g.email,
        g.no_hp
    FROM guru g
    WHERE g.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    ':user_id' => $guru_id,
]);

$guru = $stmt->fetch();

if (!$guru) {
    $_SESSION['flash_error'] = 'Data profil guru tidak ditemukan.';
    redirect_to_dashboard();
    exit;
}

$guru_id = (int) $guru['id'];

$search = trim((string) ($_GET['search'] ?? ''));
$hari = trim((string) ($_GET['hari'] ?? ''));

$allowed_days = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

if (!in_array($hari, $allowed_days, true)) {
    $hari = '';
}

$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 1,
            'min_range' => 1,
        ],
    ]
);

$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM kelas
    WHERE guru_id = :guru_id
");

$stmt->execute([
    ':guru_id' => $guru_id,
]);

$total_kelas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ks.siswa_id)
    FROM kelas_siswa ks
    INNER JOIN kelas k ON k.id = ks.kelas_id
    WHERE k.guru_id = :guru_id
");

$stmt->execute([
    ':guru_id' => $guru_id,
]);

$total_siswa = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM absensi a
    INNER JOIN kelas k ON k.id = a.kelas_id
    WHERE k.guru_id = :guru_id
");

$stmt->execute([
    ':guru_id' => $guru_id,
]);

$total_absensi = (int) $stmt->fetchColumn();

$where = [
    'k.guru_id = :guru_id',
];

$params = [
    ':guru_id' => $guru_id,
];

if ($search !== '') {
    $where[] = "(
        k.nama_kelas LIKE :search
        OR k.hari LIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

if ($hari !== '') {
    $where[] = 'k.hari = :hari';
    $params[':hari'] = $hari;
}

$where_sql = implode(' AND ', $where);

$count_sql = "
    SELECT COUNT(*)
    FROM kelas k
    WHERE {$where_sql}
";

$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);

$total_filtered = (int) $stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_filtered / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$sql = "
    SELECT
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        COUNT(DISTINCT ks.siswa_id) AS jumlah_siswa,
        COUNT(DISTINCT a.id) AS jumlah_absensi,
        MAX(a.tanggal) AS absensi_terakhir
    FROM kelas k
    LEFT JOIN kelas_siswa ks
        ON ks.kelas_id = k.id
    LEFT JOIN absensi a
        ON a.kelas_id = k.id
    WHERE {$where_sql}
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
        k.jam_mulai ASC,
        k.nama_kelas ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$kelas_list = $stmt->fetchAll();

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
                <h1 class="page-title">
                    <i class="bi bi-journal-bookmark me-2"></i>
                    Kelas Saya
                </h1>
                <p class="page-subtitle mb-0">
                    Kelola dan lihat informasi kelas yang Anda ampu.
                </p>
            </div>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= guru_kelas_escape($flash_success) ?>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?= guru_kelas_escape($flash_error) ?>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card h-100">
                    <div class="stat-card-icon primary">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Kelas</span>
                        <strong class="stat-card-value">
                            <?= number_format($total_kelas) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card h-100">
                    <div class="stat-card-icon success">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Siswa</span>
                        <strong class="stat-card-value">
                            <?= number_format($total_siswa) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-4">
                <div class="stat-card h-100">
                    <div class="stat-card-icon info">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Data Absensi</span>
                        <strong class="stat-card-value">
                            <?= number_format($total_absensi) ?>
                        </strong>
                    </div>
                </div>
            </div>

        </div>

        <div class="dashboard-card">

            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title mb-1">
                        Daftar Kelas
                    </h2>
                    <p class="text-muted small mb-0">
                        Menampilkan <?= number_format($total_filtered) ?> kelas
                        sesuai filter.
                    </p>
                </div>

                <div class="text-muted small">
                    <i class="bi bi-person-badge me-1"></i>
                    <?= guru_kelas_escape($guru['nama']) ?>
                </div>
            </div>

            <div class="dashboard-card-body">

                <form
                    method="get"
                    action="<?= guru_kelas_escape($base_url) ?>/guru/kelas.php"
                    class="filter-bar mb-4"
                >
                    <div class="row g-2 align-items-end">

                        <div class="col-12 col-md-5">
                            <label for="search" class="form-label">
                                Cari Kelas
                            </label>

                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>

                                <input
                                    type="search"
                                    id="search"
                                    name="search"
                                    class="form-control"
                                    placeholder="Nama kelas..."
                                    value="<?= guru_kelas_escape($search) ?>"
                                    autocomplete="off"
                                >
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="hari" class="form-label">
                                Hari
                            </label>

                            <select
                                id="hari"
                                name="hari"
                                class="form-select"
                            >
                                <option value="">Semua Hari</option>

                                <?php foreach ($allowed_days as $day): ?>
                                    <option
                                        value="<?= guru_kelas_escape($day) ?>"
                                        <?= $hari === $day ? 'selected' : '' ?>
                                    >
                                        <?= guru_kelas_escape($day) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button
                                type="submit"
                                class="btn btn-primary flex-fill"
                            >
                                <i class="bi bi-search me-1"></i>
                                Cari
                            </button>

                            <?php if ($search !== '' || $hari !== ''): ?>
                                <a
                                    href="<?= guru_kelas_escape($base_url) ?>/guru/kelas.php"
                                    class="btn btn-outline-secondary"
                                    title="Reset filter"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </form>

                <?php if (empty($kelas_list)): ?>

                    <div class="empty-state py-5">
                        <div class="empty-state-icon">
                            <i class="bi bi-journal-x"></i>
                        </div>

                        <?php if ($search !== '' || $hari !== ''): ?>
                            <h3 class="empty-state-title">
                                Kelas Tidak Ditemukan
                            </h3>

                            <p class="empty-state-text">
                                Tidak ada kelas yang sesuai dengan filter yang
                                dipilih.
                            </p>

                            <a
                                href="<?= guru_kelas_escape($base_url) ?>/guru/kelas.php"
                                class="btn btn-outline-primary"
                            >
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Reset Filter
                            </a>
                        <?php else: ?>
                            <h3 class="empty-state-title">
                                Belum Ada Kelas
                            </h3>

                            <p class="empty-state-text">
                                Saat ini belum ada kelas yang ditugaskan kepada
                                Anda.
                            </p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>

                    <div class="table-responsive-custom">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Kelas</th>
                                    <th>Jadwal</th>
                                    <th class="text-center">Siswa</th>
                                    <th class="text-center">Absensi</th>
                                    <th>Absensi Terakhir</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($kelas_list as $index => $kelas): ?>
                                    <?php
                                    $nomor = $offset + $index + 1;

                                    $detail_url =
                                        $base_url .
                                        '/guru/detail_kelas.php?id=' .
                                        (int) $kelas['id'];

                                    $absensi_url =
                                        $base_url .
                                        '/guru/absensi.php?kelas_id=' .
                                        (int) $kelas['id'];
                                    ?>

                                    <tr
                                        data-search-row
                                        data-status="<?= guru_kelas_escape($kelas['hari']) ?>"
                                    >
                                        <td class="text-muted">
                                            <?= $nomor ?>
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar avatar-primary">
                                                    <i class="bi bi-journal-text"></i>
                                                </div>

                                                <div>
                                                    <div class="fw-semibold">
                                                        <?= guru_kelas_escape($kelas['nama_kelas']) ?>
                                                    </div>

                                                    <div class="small text-muted">
                                                        ID Kelas #<?= (int) $kelas['id'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <?= guru_kelas_hari_badge((string) $kelas['hari']) ?>
                                            </div>

                                            <div class="small text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= guru_kelas_format_jam($kelas['jam_mulai']) ?>
                                                -
                                                <?= guru_kelas_format_jam($kelas['jam_selesai']) ?>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge bg-primary-subtle text-primary">
                                                <i class="bi bi-people me-1"></i>
                                                <?= number_format((int) $kelas['jumlah_siswa']) ?>
                                            </span>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge bg-info-subtle text-info">
                                                <?= number_format((int) $kelas['jumlah_absensi']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if (!empty($kelas['absensi_terakhir'])): ?>
                                                <span class="small">
                                                    <?= guru_kelas_format_tanggal($kelas['absensi_terakhir']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    Belum ada
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a
                                                    href="<?= guru_kelas_escape($detail_url) ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Lihat detail kelas"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">

                            <div class="small text-muted">
                                Menampilkan
                                <strong>
                                    <?= number_format($offset + 1) ?>
                                </strong>
                                -
                                <strong>
                                    <?= number_format(min($offset + count($kelas_list), $total_filtered)) ?>
                                </strong>
                                dari
                                <strong>
                                    <?= number_format($total_filtered) ?>
                                </strong>
                                kelas
                            </div>

                            <nav aria-label="Navigasi halaman kelas">
                                <ul class="pagination pagination-sm mb-0">

                                    <?php
                                    $query_params = [];

                                    if ($search !== '') {
                                        $query_params['search'] = $search;
                                    }

                                    if ($hari !== '') {
                                        $query_params['hari'] = $hari;
                                    }

                                    $build_page_url = static function (
                                        int $target_page
                                    ) use (
                                        $base_url,
                                        $query_params
                                    ): string {
                                        $params = $query_params;
                                        $params['page'] = $target_page;

                                        return $base_url .
                                            '/guru/kelas.php?' .
                                            http_build_query($params);
                                    };
                                    ?>

                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= $page > 1 ? guru_kelas_escape($build_page_url($page - 1)) : '#' ?>"
                                            aria-label="Sebelumnya"
                                        >
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    ?>

                                    <?php if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                href="<?= guru_kelas_escape($build_page_url(1)) ?>"
                                            >
                                                1
                                            </a>
                                        </li>

                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                            <a
                                                class="page-link"
                                                href="<?= guru_kelas_escape($build_page_url($p)) ?>"
                                            >
                                                <?= $p ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>

                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                href="<?= guru_kelas_escape($build_page_url($total_pages)) ?>"
                                            >
                                                <?= $total_pages ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= $page < $total_pages ? guru_kelas_escape($build_page_url($page + 1)) : '#' ?>"
                                            aria-label="Berikutnya"
                                        >
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>

                                </ul>
                            </nav>

                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>
        </div>

    </div>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>