<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Data Kelas';

$base_url = '/absensi-sekolah';

$additional_js = [
    'dashboard.js',
];

$per_page = 10;

$allowed_days = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

$search = trim($_GET['search'] ?? '');
$hari = trim($_GET['hari'] ?? '');

$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($page === false || $page < 1) {
    $page = 1;
}

if ($hari !== '' && !in_array($hari, $allowed_days, true)) {
    $hari = '';
}

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM kelas
");

$total_kelas = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT guru_id)
    FROM kelas
");

$total_guru_aktif = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT siswa_id)
    FROM kelas_siswa
");

$total_siswa_terdaftar = (int) $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM kelas_siswa
");

$total_keanggotaan = (int) $stmt->fetchColumn();

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "(
        k.nama_kelas LIKE :search
        OR g.nama LIKE :search
        OR g.nip LIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

if ($hari !== '') {

    $where[] = "k.hari = :hari";

    $params[':hari'] = $hari;
}

$where_sql = '';

if ($where) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$count_sql = "
    SELECT COUNT(*)
    FROM kelas k
    INNER JOIN guru g
        ON g.id = k.guru_id
    {$where_sql}
";

$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);

$total_filtered = (int) $stmt->fetchColumn();

$total_pages = max(
    1,
    (int) ceil($total_filtered / $per_page)
);

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

$sql = "
    SELECT
        k.id,
        k.nama_kelas,
        k.guru_id,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,

        g.nama AS nama_guru,
        g.nip,

        COUNT(ks.siswa_id) AS jumlah_siswa

    FROM kelas k

    INNER JOIN guru g
        ON g.id = k.guru_id

    LEFT JOIN kelas_siswa ks
        ON ks.kelas_id = k.id

    {$where_sql}

    GROUP BY
        k.id,
        k.nama_kelas,
        k.guru_id,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.nama,
        g.nip

    ORDER BY
        CASE k.hari
            WHEN 'Senin' THEN 1
            WHEN 'Selasa' THEN 2
            WHEN 'Rabu' THEN 3
            WHEN 'Kamis' THEN 4
            WHEN 'Jumat' THEN 5
            WHEN 'Sabtu' THEN 6
            ELSE 7
        END,
        k.jam_mulai ASC,
        k.nama_kelas ASC

    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(
    ':limit',
    $per_page,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$classes = $stmt->fetchAll();

function admin_kelas_format_time(?string $time): string
{
    if (!$time) {
        return '-';
    }

    $timestamp = strtotime($time);

    if (!$timestamp) {
        return '-';
    }

    return date('H:i', $timestamp);
}

function admin_kelas_day_badge(string $hari): string
{
    return match ($hari) {
        'Senin' => 'bg-primary-subtle text-primary',
        'Selasa' => 'bg-success-subtle text-success',
        'Rabu' => 'bg-info-subtle text-info',
        'Kamis' => 'bg-warning-subtle text-warning-emphasis',
        'Jumat' => 'bg-danger-subtle text-danger',
        'Sabtu' => 'bg-secondary-subtle text-secondary',
        default => 'bg-light text-dark',
    };
}

function admin_kelas_query(array $overrides = []): string
{
    $params = $_GET;

    foreach ($overrides as $key => $value) {

        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return http_build_query($params);
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

if (($_GET['status'] ?? '') === 'success') {
    $success = (string) ($_GET['message'] ?? $success);
} elseif (($_GET['status'] ?? '') === 'error') {
    $error = (string) ($_GET['message'] ?? $error);
}

unset(
    $_SESSION['error'],
    $_SESSION['success']
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="app-layout">

    <main class="main-content">

        <div>

            <div class="page-header dashboard-page-header">

                <div>
                    <h1 class="page-title">
                        <i class="bi bi-collection me-2"></i>
                        Data Kelas
                    </h1>

                    <p class="page-subtitle">
                        Kelola data kelas, guru pengampu, dan siswa
                    </p>
                </div>

                <div>

                    <a
                        href="<?= htmlspecialchars($base_url) ?>/admin/kelas_tambah.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus-lg me-1"></i>
                        Tambah Kelas
                    </a>

                </div>

            </div>

            <?php if ($error): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >
                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >
                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>

            <div class="row g-3 mb-4">

                <div class="col-6 col-xl-3">

                    <div class="stat-card">

                        <div class="stat-card-icon bg-primary-subtle text-primary">
                            <i class="bi bi-collection"></i>
                        </div>

                        <div class="stat-card-content">

                            <div class="stat-card-label">
                                Total Kelas
                            </div>

                            <div
                                class="stat-card-value"
                                data-counter="<?= $total_kelas ?>"
                            >
                                0
                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-6 col-xl-3">

                    <div class="stat-card">

                        <div class="stat-card-icon bg-success-subtle text-success">
                            <i class="bi bi-person-video3"></i>
                        </div>

                        <div class="stat-card-content">

                            <div class="stat-card-label">
                                Guru Mengajar
                            </div>

                            <div
                                class="stat-card-value"
                                data-counter="<?= $total_guru_aktif ?>"
                            >
                                0
                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-6 col-xl-3">

                    <div class="stat-card">

                        <div class="stat-card-icon bg-info-subtle text-info">
                            <i class="bi bi-people"></i>
                        </div>

                        <div class="stat-card-content">

                            <div class="stat-card-label">
                                Siswa Terdaftar
                            </div>

                            <div
                                class="stat-card-value"
                                data-counter="<?= $total_siswa_terdaftar ?>"
                            >
                                0
                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-6 col-xl-3">

                    <div class="stat-card">

                        <div class="stat-card-icon bg-warning-subtle text-warning">
                            <i class="bi bi-person-plus"></i>
                        </div>

                        <div class="stat-card-content">

                            <div class="stat-card-label">
                                Keanggotaan
                            </div>

                            <div
                                class="stat-card-value"
                                data-counter="<?= $total_keanggotaan ?>"
                            >
                                0
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="card shadow-sm">

                <div class="card-header bg-white">

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

                        <div>

                            <h5 class="mb-1">
                                <i class="bi bi-list-ul me-2"></i>
                                Daftar Kelas
                            </h5>

                            <small class="text-muted">
                                Menampilkan
                                <?= count($classes) ?>
                                dari
                                <?= $total_filtered ?>
                                kelas
                            </small>

                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <form
                        method="GET"
                        action="<?= htmlspecialchars($base_url) ?>/admin/kelas.php"
                        class="filter-bar mb-4"
                    >

                        <div class="row g-2">

                            <div class="col-md-6">

                                <label
                                    for="search"
                                    class="visually-hidden"
                                >
                                    Cari kelas
                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>

                                    <input
                                        type="search"
                                        class="form-control"
                                        id="search"
                                        name="search"
                                        value="<?= htmlspecialchars($search) ?>"
                                        placeholder="Cari nama kelas, guru, atau NIP..."
                                    >

                                </div>

                            </div>

                            <div class="col-md-3">

                                <label
                                    for="hari"
                                    class="visually-hidden"
                                >
                                    Filter hari
                                </label>

                                <select
                                    class="form-select"
                                    id="hari"
                                    name="hari"
                                >

                                    <option value="">
                                        Semua Hari
                                    </option>

                                    <?php foreach ($allowed_days as $day): ?>

                                        <option
                                            value="<?= htmlspecialchars($day) ?>"
                                            <?= $hari === $day ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars($day) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-3 d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >
                                    <i class="bi bi-search me-1"></i>
                                    Cari
                                </button>

                                <a
                                    href="<?= htmlspecialchars($base_url) ?>/admin/kelas.php"
                                    class="btn btn-outline-secondary"
                                    title="Reset filter"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>

                            </div>

                        </div>

                    </form>

                    <?php if (!$classes): ?>

                        <div class="empty-state py-5">

                            <div class="empty-state-icon">
                                <i class="bi bi-collection"></i>
                            </div>

                            <h5>
                                <?= $search || $hari
                                    ? 'Kelas Tidak Ditemukan'
                                    : 'Belum Ada Kelas' ?>
                            </h5>

                            <p class="text-muted mb-3">

                                <?php if ($search || $hari): ?>

                                    Tidak ada kelas yang sesuai
                                    dengan filter pencarian.

                                <?php else: ?>

                                    Belum ada data kelas.
                                    Silakan tambahkan kelas baru.

                                <?php endif; ?>

                            </p>

                            <?php if (!$search && !$hari): ?>

                                <a
                                    href="<?= htmlspecialchars($base_url) ?>/admin/kelas_tambah.php"
                                    class="btn btn-primary"
                                >
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Tambah Kelas
                                </a>

                            <?php else: ?>

                                <a
                                    href="<?= htmlspecialchars($base_url) ?>/admin/kelas.php"
                                    class="btn btn-outline-secondary"
                                >
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                    Reset Filter
                                </a>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <div class="table-responsive-custom">

                            <table
                                class="table table-hover align-middle mb-0"
                                id="tableKelas"
                            >

                                <thead class="table-light">

                                    <tr>

                                        <th style="width: 60px;">
                                            #
                                        </th>

                                        <th>
                                            Kelas
                                        </th>

                                        <th>
                                            Guru Pengampu
                                        </th>

                                        <th>
                                            Jadwal
                                        </th>

                                        <th class="text-center">
                                            Siswa
                                        </th>

                                        <th
                                            class="text-end"
                                            style="width: 150px;"
                                        >
                                            Aksi
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($classes as $index => $class): ?>

                                        <tr
                                            data-hari="<?= htmlspecialchars($class['hari']) ?>"
                                        >

                                            <td class="text-muted">
                                                <?= $offset + $index + 1 ?>
                                            </td>

                                            <td>

                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($class['nama_kelas']) ?>
                                                </div>

                                                <small class="text-muted">
                                                    ID #<?= (int) $class['id'] ?>
                                                </small>

                                            </td>

                                            <td>

                                                <div class="d-flex align-items-center">

                                                    <div
                                                        class="avatar bg-primary-subtle text-primary me-2"
                                                    >
                                                        <?= htmlspecialchars(
                                                            strtoupper(
                                                                substr(
                                                                    trim($class['nama_guru']),
                                                                    0,
                                                                    1
                                                                )
                                                            )
                                                        ) ?>
                                                    </div>

                                                    <div>

                                                        <div class="fw-semibold">
                                                            <?= htmlspecialchars($class['nama_guru']) ?>
                                                        </div>

                                                        <small class="text-muted">
                                                            NIP:
                                                            <?= htmlspecialchars($class['nip'] ?: '-') ?>
                                                        </small>

                                                    </div>

                                                </div>

                                            </td>

                                            <td>

                                                <div class="mb-1">

                                                    <span
                                                        class="badge <?= htmlspecialchars(admin_kelas_day_badge($class['hari'])) ?>"
                                                    >
                                                        <?= htmlspecialchars($class['hari']) ?>
                                                    </span>

                                                </div>

                                                <small class="text-muted">

                                                    <i class="bi bi-clock me-1"></i>

                                                    <?= htmlspecialchars(
                                                        admin_kelas_format_time(
                                                            $class['jam_mulai']
                                                        )
                                                    ) ?>

                                                    -

                                                    <?= htmlspecialchars(
                                                        admin_kelas_format_time(
                                                            $class['jam_selesai']
                                                        )
                                                    ) ?>

                                                </small>

                                            </td>

                                            <td class="text-center">

                                                <span class="badge bg-primary-subtle text-primary">

                                                    <i class="bi bi-people me-1"></i>

                                                    <?= (int) $class['jumlah_siswa'] ?>

                                                </span>

                                            </td>

                                            <td>

                                                <div class="d-flex justify-content-end gap-1">

                                                    <a
                                                        href="<?= htmlspecialchars($base_url) ?>/admin/kelas_view.php?id=<?= (int) $class['id'] ?>"
                                                        class="btn btn-sm btn-outline-info"
                                                        title="Lihat kelas"
                                                    >
                                                        <i class="bi bi-eye"></i>
                                                    </a>

                                                    <a
                                                        href="<?= htmlspecialchars($base_url) ?>/admin/kelas_edit.php?id=<?= (int) $class['id'] ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Edit kelas"
                                                    >
                                                        <i class="bi bi-pencil"></i>
                                                    </a>

                                                    <a
                                                        href="<?= htmlspecialchars($base_url) ?>/admin/kelas_hapus.php?id=<?= (int) $class['id'] ?>"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Hapus kelas"
                                                    >
                                                        <i class="bi bi-trash"></i>
                                                    </a>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <?php if ($total_pages > 1): ?>

                            <nav
                                class="mt-4"
                                aria-label="Navigasi halaman kelas"
                            >

                                <ul class="pagination justify-content-center mb-0">

                                    <?php
                                    $prev_page = $page - 1;

                                    $next_page = $page + 1;
                                    ?>

                                    <li
                                        class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"
                                    >

                                        <?php if ($page > 1): ?>

                                            <a
                                                class="page-link"
                                                href="?<?= htmlspecialchars(
                                                    admin_kelas_query([
                                                        'page' => $prev_page
                                                    ])
                                                ) ?>"
                                                aria-label="Sebelumnya"
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
                                    $start_page = max(1, $page - 2);
                                    $end_page = min(
                                        $total_pages,
                                        $page + 2
                                    );
                                    ?>

                                    <?php if ($start_page > 1): ?>

                                        <li class="page-item">

                                            <a
                                                class="page-link"
                                                href="?<?= htmlspecialchars(
                                                    admin_kelas_query([
                                                        'page' => 1
                                                    ])
                                                ) ?>"
                                            >
                                                1
                                            </a>

                                        </li>

                                        <?php if ($start_page > 2): ?>

                                            <li class="page-item disabled">
                                                <span class="page-link">
                                                    ...
                                                </span>
                                            </li>

                                        <?php endif; ?>

                                    <?php endif; ?>

                                    <?php for (
                                        $i = $start_page;
                                        $i <= $end_page;
                                        $i++
                                    ): ?>

                                        <li
                                            class="page-item <?= $i === $page ? 'active' : '' ?>"
                                        >

                                            <?php if ($i === $page): ?>

                                                <span class="page-link">
                                                    <?= $i ?>
                                                </span>

                                            <?php else: ?>

                                                <a
                                                    class="page-link"
                                                    href="?<?= htmlspecialchars(
                                                        admin_kelas_query([
                                                            'page' => $i
                                                        ])
                                                    ) ?>"
                                                >
                                                    <?= $i ?>
                                                </a>

                                            <?php endif; ?>

                                        </li>

                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>

                                        <?php if ($end_page < $total_pages - 1): ?>

                                            <li class="page-item disabled">

                                                <span class="page-link">
                                                    ...
                                                </span>

                                            </li>

                                        <?php endif; ?>

                                        <li class="page-item">

                                            <a
                                                class="page-link"
                                                href="?<?= htmlspecialchars(
                                                    admin_kelas_query([
                                                        'page' => $total_pages
                                                    ])
                                                ) ?>"
                                            >
                                                <?= $total_pages ?>
                                            </a>

                                        </li>

                                    <?php endif; ?>

                                    <li
                                        class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"
                                    >

                                        <?php if ($page < $total_pages): ?>

                                            <a
                                                class="page-link"
                                                href="?<?= htmlspecialchars(
                                                    admin_kelas_query([
                                                        'page' => $next_page
                                                    ])
                                                ) ?>"
                                                aria-label="Berikutnya"
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

                        <?php endif; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>