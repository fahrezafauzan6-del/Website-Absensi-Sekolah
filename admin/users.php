<?php
$page_title = 'Data Pengguna - Absensi Sekolah';

require_once __DIR__ . '/../auth/check_auth.php';
check_role('admin');

require_once __DIR__ . '/../config/database.php';

$additional_js = ['dashboard.js'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

$baseUrl = $base_url ?? '/absensi-sekolah';

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$allowedRoles = ['admin', 'guru', 'siswa'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin,
        SUM(CASE WHEN role = 'guru' THEN 1 ELSE 0 END) AS guru,
        SUM(CASE WHEN role = 'siswa' THEN 1 ELSE 0 END) AS siswa
    FROM users
");

$userStats = $stmt->fetch();

$totalUsers = (int) ($userStats['total'] ?? 0);
$totalAdmin = (int) ($userStats['admin'] ?? 0);
$totalGuru = (int) ($userStats['guru'] ?? 0);
$totalSiswa = (int) ($userStats['siswa'] ?? 0);

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        u.username LIKE ?
        OR u.nama LIKE ?
        OR COALESCE(g.nip, '') LIKE ?
        OR COALESCE(s.nis, '') LIKE ?
        OR COALESCE(g.email, '') LIKE ?
        OR COALESCE(s.email, '') LIKE ?
    )";

    $searchParam = '%' . $search . '%';

    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchParam;
    }
}

if ($roleFilter !== '') {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$countSql = "
    SELECT COUNT(*)
    FROM users u
    LEFT JOIN guru g ON g.user_id = u.id
    LEFT JOIN siswa s ON s.user_id = u.id
    {$whereSql}
";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);

$totalFiltered = (int) $stmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalFiltered / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        u.id,
        u.username,
        u.nama,
        u.role,
        u.created_at,
        u.updated_at,

        g.id AS guru_id,
        g.nip,
        g.email AS guru_email,
        g.no_hp AS guru_no_hp,

        s.id AS siswa_id,
        s.nis,
        s.jenis_kelamin,
        s.email AS siswa_email,
        s.no_hp AS siswa_no_hp

    FROM users u

    LEFT JOIN guru g
        ON g.user_id = u.id

    LEFT JOIN siswa s
        ON s.user_id = u.id

    {$whereSql}

    ORDER BY
        CASE u.role
            WHEN 'admin' THEN 1
            WHEN 'guru' THEN 2
            WHEN 'siswa' THEN 3
            ELSE 4
        END,
        u.nama ASC

    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll();

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';

$alertType = 'info';

if ($status === 'success') {
    $alertType = 'success';
} elseif ($status === 'error') {
    $alertType = 'danger';
}

function admin_user_role_badge(string $role): string
{
    $config = [
        'admin' => [
            'class' => 'bg-danger-subtle text-danger',
            'icon' => 'bi-shield-lock',
            'label' => 'Administrator',
        ],

        'guru' => [
            'class' => 'bg-primary-subtle text-primary',
            'icon' => 'bi-person-workspace',
            'label' => 'Guru',
        ],

        'siswa' => [
            'class' => 'bg-success-subtle text-success',
            'icon' => 'bi-mortarboard',
            'label' => 'Siswa',
        ],
    ];

    $item = $config[$role] ?? [
        'class' => 'bg-secondary-subtle text-secondary',
        'icon' => 'bi-person',
        'label' => ucfirst($role),
    ];

    return '<span class="badge ' .
        htmlspecialchars($item['class'], ENT_QUOTES, 'UTF-8') .
        '">' .
        '<i class="bi ' .
        htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') .
        ' me-1"></i>' .
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') .
        '</span>';
}

function admin_user_identifier(array $user): string
{
    if ($user['role'] === 'guru') {
        return $user['nip'] ?: '-';
    }

    if ($user['role'] === 'siswa') {
        return $user['nis'] ?: '-';
    }

    return '-';
}

function admin_user_identifier_label(string $role): string
{
    if ($role === 'guru') {
        return 'NIP';
    }

    if ($role === 'siswa') {
        return 'NIS';
    }

    return 'ID';
}

function admin_user_email(array $user): string
{
    if ($user['role'] === 'guru') {
        return $user['guru_email'] ?? '';
    }

    if ($user['role'] === 'siswa') {
        return $user['siswa_email'] ?? '';
    }

    return '';
}

function admin_user_phone(array $user): string
{
    if ($user['role'] === 'guru') {
        return $user['guru_no_hp'] ?? '';
    }

    if ($user['role'] === 'siswa') {
        return $user['siswa_no_hp'] ?? '';
    }

    return '';
}

function admin_format_date(string $date): string
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

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date('d', $timestamp) .
        ' ' .
        $bulan[(int) date('n', $timestamp)] .
        ' ' .
        date('Y', $timestamp);
}

function admin_users_page_url(
    string $baseUrl,
    int $page,
    string $search,
    string $role
): string {
    $params = [
        'page' => $page,
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($role !== '') {
        $params['role'] = $role;
    }

    return $baseUrl .
        '/admin/users.php?' .
        http_build_query($params);
}
?>

<main class="main-content dashboard-page">

    <div>

        <div class="page-header dashboard-page-header">

            <div>
                <h1 class="page-title">
                    <i class="bi bi-people-fill me-2"></i>
                    Data Pengguna
                </h1>

                <p class="page-subtitle">
                    Kelola akun administrator, guru, dan siswa.
                </p>
            </div>

            <div class="page-header-actions">

                <a
                    href="<?= htmlspecialchars(
                        $baseUrl,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>/admin/user_tambah.php"
                    class="btn btn-primary">

                    <i class="bi bi-person-plus me-1"></i>
                    Tambah Pengguna

                </a>

            </div>

        </div>

        <?php if ($message !== ''): ?>

            <div
                class="alert alert-<?= htmlspecialchars(
                    $alertType,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?> alert-dismissible fade show"
                role="alert">

                <i class="bi
                    <?= $alertType === 'success'
                        ? 'bi-check-circle'
                        : 'bi-exclamation-triangle'; ?>
                    me-2"></i>

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup">
                </button>

            </div>

        <?php endif; ?>

        <div class="row g-4 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card h-100">

                    <div class="stat-card-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people"></i>
                    </div>

                    <div class="stat-card-content">

                        <div class="stat-card-label">
                            Total Pengguna
                        </div>

                        <div
                            class="stat-card-value"
                            data-counter="<?= $totalUsers; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            Semua akun
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card h-100">

                    <div class="stat-card-icon bg-danger-subtle text-danger">
                        <i class="bi bi-shield-lock"></i>
                    </div>

                    <div class="stat-card-content">

                        <div class="stat-card-label">
                            Administrator
                        </div>

                        <div
                            class="stat-card-value"
                            data-counter="<?= $totalAdmin; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            Akun admin
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card h-100">

                    <div class="stat-card-icon bg-info-subtle text-info">
                        <i class="bi bi-person-workspace"></i>
                    </div>

                    <div class="stat-card-content">

                        <div class="stat-card-label">
                            Guru
                        </div>

                        <div
                            class="stat-card-value"
                            data-counter="<?= $totalGuru; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            Akun guru
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card h-100">

                    <div class="stat-card-icon bg-success-subtle text-success">
                        <i class="bi bi-mortarboard"></i>
                    </div>

                    <div class="stat-card-content">

                        <div class="stat-card-label">
                            Siswa
                        </div>

                        <div
                            class="stat-card-value"
                            data-counter="<?= $totalSiswa; ?>">
                            0
                        </div>

                        <div class="stat-card-meta">
                            Akun siswa
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="dashboard-card">

            <div class="dashboard-card-header">

                <div>
                    <h2 class="dashboard-card-title">
                        Daftar Pengguna
                    </h2>

                    <p class="dashboard-card-subtitle">
                        Menampilkan
                        <?= $totalFiltered; ?>
                        pengguna
                        <?php if ($search !== '' || $roleFilter !== ''): ?>
                            dari total <?= $totalUsers; ?> pengguna.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="dashboard-card-body">
                <form
                    method="get"
                    action="<?= htmlspecialchars(
                        $baseUrl,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>/admin/users.php"
                    class="filter-bar mb-4">

                    <div class="row g-3 align-items-end">

                        <div class="col-12 col-lg-6">

                            <label
                                for="search"
                                class="form-label">
                                Cari Pengguna
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
                                    value="<?= htmlspecialchars(
                                        $search,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    placeholder="Username, nama, NIP, NIS, atau email...">

                            </div>

                        </div>

                        <div class="col-12 col-md-6 col-lg-3">

                            <label
                                for="role"
                                class="form-label">
                                Role
                            </label>

                            <select
                                class="form-select"
                                id="role"
                                name="role">

                                <option value="">
                                    Semua Role
                                </option>

                                <option
                                    value="admin"
                                    <?= $roleFilter === 'admin'
                                        ? 'selected'
                                        : ''; ?>>
                                    Administrator
                                </option>

                                <option
                                    value="guru"
                                    <?= $roleFilter === 'guru'
                                        ? 'selected'
                                        : ''; ?>>
                                    Guru
                                </option>

                                <option
                                    value="siswa"
                                    <?= $roleFilter === 'siswa'
                                        ? 'selected'
                                        : ''; ?>>
                                    Siswa
                                </option>

                            </select>

                        </div>

                        <div class="col-12 col-md-6 col-lg-3">

                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1">

                                    <i class="bi bi-search me-1"></i>
                                    Cari

                                </button>

                                <?php if (
                                    $search !== '' ||
                                    $roleFilter !== ''
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $baseUrl,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>/admin/users.php"
                                        class="btn btn-outline-secondary"
                                        title="Reset filter">

                                        <i class="bi bi-x-lg"></i>

                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </form>

                <?php if (!empty($users)): ?>

                    <div class="table-responsive-custom">

                        <table class="table align-middle mb-0">

                            <thead>
                                <tr>
                                    <th style="width: 60px;">
                                        #
                                    </th>

                                    <th>
                                        Pengguna
                                    </th>

                                    <th>
                                        Username
                                    </th>

                                    <th>
                                        Role
                                    </th>

                                    <th>
                                        Identitas
                                    </th>

                                    <th>
                                        Kontak
                                    </th>

                                    <th>
                                        Terdaftar
                                    </th>

                                    <th
                                        class="text-end"
                                        style="width: 150px;">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $users as $index => $user
                                ): ?>

                                    <?php
                                    $isCurrentUser =
                                        (int) $user['id'] ===
                                        (int) current_user_id();

                                    $identifier =
                                        admin_user_identifier($user);

                                    $identifierLabel =
                                        admin_user_identifier_label(
                                            $user['role']
                                        );

                                    $email =
                                        admin_user_email($user);

                                    $phone =
                                        admin_user_phone($user);

                                    $initial =
                                        strtoupper(
                                            substr(
                                                trim($user['nama']),
                                                0,
                                                1
                                            )
                                        );

                                    $rowNumber =
                                        $offset + $index + 1;
                                    ?>

                                    <tr>

                                        <td class="text-muted">
                                            <?= $rowNumber; ?>
                                        </td>

                                        <td>

                                            <div class="d-flex align-items-center gap-3">

                                                <div class="avatar avatar-sm
                                                    <?= $user['role'] === 'admin'
                                                        ? 'bg-danger-subtle text-danger'
                                                        : ($user['role'] === 'guru'
                                                            ? 'bg-primary-subtle text-primary'
                                                            : 'bg-success-subtle text-success'); ?>">

                                                    <?php if ($user['role'] === 'admin'): ?>

                                                        <i class="bi bi-shield-lock"></i>

                                                    <?php else: ?>

                                                        <?= htmlspecialchars(
                                                            $initial ?: '?',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                    <?php endif; ?>

                                                </div>

                                                <div class="min-width-0">

                                                    <div class="fw-semibold text-truncate">
                                                        <?= htmlspecialchars(
                                                            $user['nama'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>

                                                        <?php if ($isCurrentUser): ?>

                                                            <span class="badge bg-light text-dark border ms-1">
                                                                Anda
                                                            </span>

                                                        <?php endif; ?>

                                                    </div>

                                                    <small class="text-muted">
                                                        ID #<?= (int) $user['id']; ?>
                                                    </small>

                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <code>
                                                <?= htmlspecialchars(
                                                    $user['username'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>
                                            </code>

                                        </td>

                                        <td>
                                            <?= admin_user_role_badge(
                                                $user['role']
                                            ); ?>
                                        </td>

                                        <td>

                                            <?php if (
                                                $user['role'] !== 'admin'
                                            ): ?>

                                                <div class="small fw-medium">
                                                    <?= htmlspecialchars(
                                                        $identifierLabel,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>:
                                                    <?= htmlspecialchars(
                                                        $identifier,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </div>

                                                <?php if (
                                                    $user['role'] === 'siswa' &&
                                                    !empty($user['jenis_kelamin'])
                                                ): ?>

                                                    <small class="text-muted">
                                                        <?= $user['jenis_kelamin'] === 'L'
                                                            ? 'Laki-laki'
                                                            : 'Perempuan'; ?>
                                                    </small>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    -
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if ($email !== ''): ?>

                                                <div
                                                    class="small text-truncate"
                                                    style="max-width: 200px;"
                                                    title="<?= htmlspecialchars(
                                                        $email,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>">

                                                    <i class="bi bi-envelope me-1 text-muted"></i>

                                                    <?= htmlspecialchars(
                                                        $email,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>

                                                </div>

                                            <?php endif; ?>

                                            <?php if ($phone !== ''): ?>

                                                <div class="small text-muted mt-1">
                                                    <i class="bi bi-telephone me-1"></i>
                                                    <?= htmlspecialchars(
                                                        $phone,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </div>

                                            <?php endif; ?>

                                            <?php if (
                                                $email === '' &&
                                                $phone === ''
                                            ): ?>

                                                <span class="text-muted">
                                                    -
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <span class="small">
                                                <?= admin_format_date(
                                                    $user['created_at']
                                                ); ?>
                                            </span>
                                        </td>

                                        <td>

                                            <div class="d-flex justify-content-end gap-1">

                                                <a
                                                    href="<?= htmlspecialchars(
                                                        $baseUrl,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>/admin/user_view.php?id=<?= (int) $user['id']; ?>"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Lihat detail">

                                                    <i class="bi bi-eye"></i>

                                                    <span class="visually-hidden">
                                                        Lihat
                                                    </span>

                                                </a>

                                                <a
                                                   href="<?= htmlspecialchars($base_url) ?>/admin/user_edit.php?id=<?= (int) $user['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Edit pengguna">

                                                    <i class="bi bi-pencil"></i>

                                                    <span class="visually-hidden">
                                                        Edit
                                                    </span>

                                                </a>

                                                <?php if (!$isCurrentUser): ?>

                                                    <form
                                                        method="post"
                                                        action="<?= htmlspecialchars(
                                                            $baseUrl,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>/actions/user.php"
                                                        class="d-inline"
                                                        data-confirm-form
                                                        data-confirm-title="Hapus Pengguna?"
                                                        data-confirm-message="Pengguna <?= htmlspecialchars(
                                                            $user['nama'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?> akan dihapus. Tindakan ini tidak dapat dibatalkan."
                                                        data-confirm-button="Hapus">

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="hapus">

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?= (int) $user['id']; ?>">

                                                        <?php if (
                                                            isset($_SESSION['csrf_token'])
                                                        ): ?>

                                                            <input
                                                                type="hidden"
                                                                name="csrf_token"
                                                                value="<?= htmlspecialchars(
                                                                    $_SESSION['csrf_token'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ); ?>">

                                                        <?php endif; ?>

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="Hapus pengguna">

                                                            <i class="bi bi-trash"></i>

                                                            <span class="visually-hidden">
                                                                Hapus
                                                            </span>

                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        disabled
                                                        title="Akun yang sedang digunakan tidak dapat dihapus">

                                                        <i class="bi bi-trash"></i>

                                                    </button>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-state py-5">

                        <div class="empty-state-icon">
                            <i class="bi bi-people"></i>
                        </div>

                        <h5>
                            Pengguna tidak ditemukan
                        </h5>

                        <p>
                            <?php if (
                                $search !== '' ||
                                $roleFilter !== ''
                            ): ?>

                                Tidak ada pengguna yang sesuai dengan
                                filter yang dipilih.

                            <?php else: ?>

                                Belum ada data pengguna.

                            <?php endif; ?>
                        </p>

                        <?php if (
                            $search !== '' ||
                            $roleFilter !== ''
                        ): ?>

                            <a
                                href="<?= htmlspecialchars(
                                    $baseUrl,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>/admin/users.php"
                                class="btn btn-outline-primary">

                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Reset Filter

                            </a>

                        <?php else: ?>

                            <a
                                href="<?= htmlspecialchars(
                                    $baseUrl,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>/admin/user_tambah.php"
                                class="btn btn-primary">

                                <i class="bi bi-person-plus me-1"></i>
                                Tambah Pengguna

                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <?php if ($totalPages > 1): ?>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">

                        <div class="small text-muted">

                            Menampilkan
                            <?= $totalFiltered > 0
                                ? $offset + 1
                                : 0; ?>
                            -
                            <?= min(
                                $offset + $perPage,
                                $totalFiltered
                            ); ?>
                            dari
                            <?= $totalFiltered; ?>
                            pengguna

                        </div>

                        <nav aria-label="Navigasi halaman">

                            <ul class="pagination pagination-sm mb-0">

                                <li
                                    class="page-item
                                        <?= $page <= 1
                                            ? 'disabled'
                                            : ''; ?>">

                                    <?php if ($page > 1): ?>

                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(
                                                admin_users_page_url(
                                                    $baseUrl,
                                                    $page - 1,
                                                    $search,
                                                    $roleFilter
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            aria-label="Sebelumnya">

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
                                $endPage = min(
                                    $totalPages,
                                    $page + 2
                                );

                                if ($startPage > 1):
                                ?>

                                    <li class="page-item">

                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(
                                                admin_users_page_url(
                                                    $baseUrl,
                                                    1,
                                                    $search,
                                                    $roleFilter
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>">
                                            1
                                        </a>

                                    </li>

                                    <?php if ($startPage > 2): ?>

                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>

                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php for (
                                    $i = $startPage;
                                    $i <= $endPage;
                                    $i++
                                ): ?>

                                    <li
                                        class="page-item
                                            <?= $i === $page
                                                ? 'active'
                                                : ''; ?>">

                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(
                                                admin_users_page_url(
                                                    $baseUrl,
                                                    $i,
                                                    $search,
                                                    $roleFilter
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>">

                                            <?= $i; ?>

                                        </a>

                                    </li>

                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>

                                    <?php if (
                                        $endPage < $totalPages - 1
                                    ): ?>

                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>

                                    <?php endif; ?>

                                    <li class="page-item">

                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(
                                                admin_users_page_url(
                                                    $baseUrl,
                                                    $totalPages,
                                                    $search,
                                                    $roleFilter
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>">

                                            <?= $totalPages; ?>

                                        </a>

                                    </li>

                                <?php endif; ?>

                                <li
                                    class="page-item
                                        <?= $page >= $totalPages
                                            ? 'disabled'
                                            : ''; ?>">

                                    <?php if ($page < $totalPages): ?>

                                        <a
                                            class="page-link"
                                            href="<?= htmlspecialchars(
                                                admin_users_page_url(
                                                    $baseUrl,
                                                    $page + 1,
                                                    $search,
                                                    $roleFilter
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                            aria-label="Berikutnya">

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

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>