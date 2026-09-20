<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Detail Pengguna';

$base_url = '/absensi-sekolah';

$id = null;

if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
} elseif (isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if ($id === false || $id === null || $id <= 0) {
    header('Location: ' . $base_url . '/admin/users.php?error=invalid_id');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.username,
        u.nama,
        u.role,
        u.created_at,

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

    WHERE u.id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . $base_url . '/admin/users.php?error=user_not_found');
    exit;
}

function user_view_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'guru'  => 'Guru',
        'siswa' => 'Siswa',
        default => ucfirst($role),
    };
}

function user_view_role_badge(string $role): string
{
    return match ($role) {
        'admin' => 'bg-danger',
        'guru'  => 'bg-primary',
        'siswa' => 'bg-success',
        default => 'bg-secondary',
    };
}

function user_view_format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '-';
    }

    $months = [
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

    return date('d', $timestamp)
        . ' '
        . $months[(int) date('n', $timestamp)]
        . ' '
        . date('Y', $timestamp);
}

$role = $user['role'] ?? '';
$profile_id = match ($role) {
    'guru' => (int) ($user['guru_id'] ?? 0),
    'siswa' => (int) ($user['siswa_id'] ?? 0),
    default => 0,
};

$profile_role_label = user_view_role_label($role);
$role_badge = user_view_role_badge($role);

$email = '-';
$no_hp = '-';
$identifier_label = '';
$identifier = '-';

if ($role === 'guru') {
    $email = $user['guru_email'] ?: '-';
    $no_hp = $user['guru_no_hp'] ?: '-';
    $identifier_label = 'NIP';
    $identifier = $user['nip'] ?: '-';
} elseif ($role === 'siswa') {
    $email = $user['siswa_email'] ?: '-';
    $no_hp = $user['siswa_no_hp'] ?: '-';
    $identifier_label = 'NIS';
    $identifier = $user['nis'] ?: '-';
}

$jenis_kelamin = match ($user['jenis_kelamin'] ?? '') {
    'L' => 'Laki-laki',
    'P' => 'Perempuan',
    default => '-',
};

$total_kelas = 0;
$total_siswa = 0;
$total_absensi = 0;

if ($role === 'guru') {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM kelas
        WHERE guru_id = :guru_id
    ");

    $stmt->execute([
        ':guru_id' => $profile_id
    ]);

    $total_kelas = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ks.siswa_id)
        FROM kelas_siswa ks
        INNER JOIN kelas k
            ON k.id = ks.kelas_id
        WHERE k.guru_id = :guru_id
    ");

    $stmt->execute([
        ':guru_id' => $profile_id
    ]);

    $total_siswa = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM absensi a
        INNER JOIN kelas k
            ON k.id = a.kelas_id
        WHERE k.guru_id = :guru_id
    ");

    $stmt->execute([
        ':guru_id' => $profile_id
    ]);

    $total_absensi = (int) $stmt->fetchColumn();
} elseif ($role === 'siswa') {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM kelas_siswa
        WHERE siswa_id = :siswa_id
    ");

    $stmt->execute([
        ':siswa_id' => $profile_id
    ]);

    $total_kelas = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM absensi
        WHERE siswa_id = :siswa_id
    ");

    $stmt->execute([
        ':siswa_id' => $profile_id
    ]);

    $total_absensi = (int) $stmt->fetchColumn();
}

$attendance_stats = [
    'Hadir' => 0,
    'Terlambat' => 0,
    'Izin' => 0,
    'Sakit' => 0,
    'Alpa' => 0,
];

if ($role === 'siswa') {

    $stmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS jumlah
        FROM absensi
        WHERE siswa_id = :siswa_id
        GROUP BY status
    ");

    $stmt->execute([
        ':siswa_id' => $profile_id
    ]);

    foreach ($stmt->fetchAll() as $attendance) {
        if (isset($attendance_stats[$attendance['status']])) {
            $attendance_stats[$attendance['status']]
                = (int) $attendance['jumlah'];
        }
    }
}

$guru_classes = [];

if ($role === 'guru') {

    $stmt = $pdo->prepare("
        SELECT
            k.id,
            k.nama_kelas,
            k.hari,
            k.jam_mulai,
            k.jam_selesai,
            COUNT(ks.siswa_id) AS jumlah_siswa
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
        ORDER BY k.hari ASC, k.jam_mulai ASC
    ");

    $stmt->execute([
        ':guru_id' => $profile_id
    ]);

    $guru_classes = $stmt->fetchAll();
}

$siswa_classes = [];

if ($role === 'siswa') {

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
        ORDER BY k.hari ASC, k.jam_mulai ASC
    ");

    $stmt->execute([
        ':siswa_id' => $profile_id
    ]);

    $siswa_classes = $stmt->fetchAll();
}

$display_name = trim($user['nama'] ?: $user['username']);

$initial = strtoupper(
    function_exists('mb_substr')
        ? mb_substr($display_name, 0, 1, 'UTF-8')
        : substr($display_name, 0, 1)
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<div class="app-layout">

    <main class="main-content">

        <div>

            <div class="page-header mb-4">

                <div>
                    <h1 class="page-title">
                        <i class="bi bi-person-vcard me-2"></i>
                        Detail Pengguna
                    </h1>

                    <p class="page-subtitle">
                        Informasi lengkap akun dan profil pengguna
                    </p>
                </div>
            </div>

            <div class="row g-4">

                <div class="col-lg-4 mb-4">

                    <div class="card shadow-sm h-100">

                        <div class="card-body text-center">

                            <div
                                class="avatar avatar-lg bg-primary text-white mx-auto mb-3"
                                style="width: 90px; height: 90px; font-size: 32px;">
                                <?= htmlspecialchars($initial) ?>
                            </div>

                            <h3 class="mb-1">
                                <?= htmlspecialchars($user['nama']) ?>
                            </h3>

                            <p class="text-muted mb-2">
                                @<?= htmlspecialchars($user['username']) ?>
                            </p>

                            <span class="badge <?= htmlspecialchars($role_badge) ?>">
                                <?= htmlspecialchars($profile_role_label) ?>
                            </span>

                            <hr class="my-4">

                            <div class="text-start">

                                <div class="mb-3">

                                    <div class="small text-muted mb-1">
                                        ID Pengguna
                                    </div>

                                    <div class="fw-semibold">
                                        #<?= (int) $user['id'] ?>
                                    </div>

                                </div>

                                <div class="mb-3">

                                    <div class="small text-muted mb-1">
                                        Username
                                    </div>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </div>

                                </div>

                                <div>

                                    <div class="small text-muted mb-1">
                                        Terdaftar
                                    </div>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars(
                                            user_view_format_date($user['created_at'])
                                        ) ?>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-8">

                    <div class="card shadow-sm mb-4">

                        <div class="card-header bg-white">

                            <h5 class="mb-0">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                Informasi Profil
                            </h5>

                        </div>

                        <div class="card-body">

                            <div class="row g-4">

                                <div class="col-md-6">

                                    <div class="small text-muted mb-1">
                                        Nama Lengkap
                                    </div>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($user['nama']) ?>
                                    </div>

                                </div>

                                <div class="col-md-6">

                                    <div class="small text-muted mb-1">
                                        Role
                                    </div>

                                    <div>
                                        <span class="badge <?= htmlspecialchars($role_badge) ?>">
                                            <?= htmlspecialchars($profile_role_label) ?>
                                        </span>
                                    </div>

                                </div>

                                <?php if ($identifier_label): ?>

                                    <div class="col-md-6">

                                        <div class="small text-muted mb-1">
                                            <?= htmlspecialchars($identifier_label) ?>
                                        </div>

                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($identifier) ?>
                                        </div>

                                    </div>

                                <?php endif; ?>

                                <?php if ($role === 'siswa'): ?>

                                    <div class="col-md-6">

                                        <div class="small text-muted mb-1">
                                            Jenis Kelamin
                                        </div>

                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($jenis_kelamin) ?>
                                        </div>

                                    </div>

                                <?php endif; ?>

                                <div class="col-md-6">

                                    <div class="small text-muted mb-1">
                                        Email
                                    </div>

                                    <div class="fw-semibold text-break">
                                        <?= htmlspecialchars($email) ?>
                                    </div>

                                </div>

                                <div class="col-md-6">

                                    <div class="small text-muted mb-1">
                                        Nomor HP
                                    </div>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($no_hp) ?>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="row g-3 mb-4">

                        <div class="col-md-4">

                            <div class="card shadow-sm h-100">

                                <div class="card-body">

                                    <div class="d-flex align-items-center">

                                        <div class="stat-card-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-collection"></i>
                                        </div>

                                        <div class="ms-3">

                                            <div class="small text-muted">
                                                <?= $role === 'guru'
                                                    ? 'Kelas Diampu'
                                                    : 'Kelas Diikuti' ?>
                                            </div>

                                            <div class="fs-4 fw-bold">
                                                <?= $total_kelas ?>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <?php if ($role === 'guru'): ?>

                            <div class="col-md-4">

                                <div class="card shadow-sm h-100">

                                    <div class="card-body">

                                        <div class="d-flex align-items-center">

                                            <div class="stat-card-icon bg-success-subtle text-success">
                                                <i class="bi bi-people"></i>
                                            </div>

                                            <div class="ms-3">

                                                <div class="small text-muted">
                                                    Siswa
                                                </div>

                                                <div class="fs-4 fw-bold">
                                                    <?= $total_siswa ?>
                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>

                        <div class="col-md-4">

                            <div class="card shadow-sm h-100">

                                <div class="card-body">

                                    <div class="d-flex align-items-center">

                                        <div class="stat-card-icon bg-info-subtle text-info">
                                            <i class="bi bi-clipboard-check"></i>
                                        </div>

                                        <div class="ms-3">

                                            <div class="small text-muted">
                                                Data Absensi
                                            </div>

                                            <div class="fs-4 fw-bold">
                                                <?= $total_absensi ?>
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <?php if ($role === 'siswa'): ?>

                <div class="card shadow-sm mb-4">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart me-2"></i>
                            Statistik Absensi
                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="row g-3">

                            <div class="col-6 col-md">

                                <div class="border rounded p-3 text-center">

                                    <div class="text-success fs-4 mb-1">
                                        <i class="bi bi-check-circle"></i>
                                    </div>

                                    <div class="small text-muted">
                                        Hadir
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= $attendance_stats['Hadir'] ?>
                                    </div>

                                </div>

                            </div>

                            <div class="col-6 col-md">

                                <div class="border rounded p-3 text-center">

                                    <div class="text-warning fs-4 mb-1">
                                        <i class="bi bi-clock"></i>
                                    </div>

                                    <div class="small text-muted">
                                        Terlambat
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= $attendance_stats['Terlambat'] ?>
                                    </div>

                                </div>

                            </div>

                            <div class="col-6 col-md">

                                <div class="border rounded p-3 text-center">

                                    <div class="text-info fs-4 mb-1">
                                        <i class="bi bi-envelope"></i>
                                    </div>

                                    <div class="small text-muted">
                                        Izin
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= $attendance_stats['Izin'] ?>
                                    </div>

                                </div>

                            </div>

                            <div class="col-6 col-md">

                                <div class="border rounded p-3 text-center">

                                    <div class="text-secondary fs-4 mb-1">
                                        <i class="bi bi-bandaid"></i>
                                    </div>

                                    <div class="small text-muted">
                                        Sakit
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= $attendance_stats['Sakit'] ?>
                                    </div>

                                </div>

                            </div>

                            <div class="col-6 col-md">

                                <div class="border rounded p-3 text-center">

                                    <div class="text-danger fs-4 mb-1">
                                        <i class="bi bi-x-circle"></i>
                                    </div>

                                    <div class="small text-muted">
                                        Alpa
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= $attendance_stats['Alpa'] ?>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="card shadow-sm mb-4">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">
                            <i class="bi bi-collection me-2"></i>
                            Kelas yang Diikuti
                        </h5>

                    </div>

                    <div class="card-body p-0">

                        <?php if (!$siswa_classes): ?>

                            <div class="empty-state py-5 text-center">

                                <i class="bi bi-inbox fs-1 text-muted"></i>

                                <h6 class="mt-3">
                                    Belum Ada Kelas
                                </h6>

                                <p class="text-muted mb-0">
                                    Siswa belum terdaftar pada kelas mana pun.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-hover mb-0 align-middle">

                                    <thead class="table-light">

                                        <tr>
                                            <th>Kelas</th>
                                            <th>Guru</th>
                                            <th>Hari</th>
                                            <th>Jam</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($siswa_classes as $class): ?>

                                            <tr>

                                                <td class="fw-semibold">
                                                    <?= htmlspecialchars($class['nama_kelas']) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars($class['nama_guru']) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars($class['hari']) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'H:i',
                                                            strtotime($class['jam_mulai'])
                                                        )
                                                    ) ?>
                                                    -
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'H:i',
                                                            strtotime($class['jam_selesai'])
                                                        )
                                                    ) ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php elseif ($role === 'guru'): ?>

                <div class="card shadow-sm mb-4">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">
                            <i class="bi bi-collection me-2"></i>
                            Kelas yang Diampu
                        </h5>

                    </div>

                    <div class="card-body p-0">

                        <?php if (!$guru_classes): ?>

                            <div class="empty-state py-5 text-center">

                                <i class="bi bi-inbox fs-1 text-muted"></i>

                                <h6 class="mt-3">
                                    Belum Ada Kelas
                                </h6>

                                <p class="text-muted mb-0">
                                    Guru belum memiliki kelas yang diampu.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-hover mb-0 align-middle">

                                    <thead class="table-light">

                                        <tr>
                                            <th>Kelas</th>
                                            <th>Hari</th>
                                            <th>Jam</th>
                                            <th>Jumlah Siswa</th>
                                            <th></th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($guru_classes as $class): ?>

                                            <tr>

                                                <td class="fw-semibold">
                                                    <?= htmlspecialchars($class['nama_kelas']) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars($class['hari']) ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'H:i',
                                                            strtotime($class['jam_mulai'])
                                                        )
                                                    ) ?>
                                                    -
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'H:i',
                                                            strtotime($class['jam_selesai'])
                                                        )
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary">
                                                        <?= (int) $class['jumlah_siswa'] ?>
                                                        siswa
                                                    </span>
                                                </td>

                                                <td class="text-end">

                                                    <a
                                                        href="<?= htmlspecialchars($base_url) ?>/admin/kelas_view.php?id=<?= (int) $class['id'] ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="Lihat kelas">
                                                        <i class="bi bi-eye"></i>
                                                    </a>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 mb-4">

                <a
                    href="<?= htmlspecialchars($base_url) ?>/admin/users.php"
                    class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Kembali
                </a>

                <a
                    href="<?= htmlspecialchars($base_url) ?>/admin/user_edit.php?id=<?= (int) $user['id'] ?>"
                    class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>
                    Edit Pengguna
                </a>

                <?php if ((int) $user['id'] !== (int) current_user_id()): ?>

                    <a
                        href="<?= htmlspecialchars($base_url) ?>/admin/user_hapus.php?id=<?= (int) $user['id'] ?>"
                        class="btn btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>
                        Hapus Pengguna
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>