<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Hapus Pengguna';

$base_url = $base_url ?? '/absensi-sekolah';

function user_hapus_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function user_hapus_role_label(string $role): string
{
    return match ($role) {
        'admin'  => 'Administrator',
        'guru'   => 'Guru',
        'siswa'  => 'Siswa',
        default  => ucfirst($role),
    };
}

function user_hapus_role_badge(string $role): string
{
    $classes = [
        'admin' => 'text-bg-danger',
        'guru'  => 'text-bg-primary',
        'siswa' => 'text-bg-success',
    ];

    $class = $classes[$role] ?? 'text-bg-secondary';

    return '<span class="badge ' . $class . '">' .
        user_hapus_escape(user_hapus_role_label($role)) .
        '</span>';
}

function user_hapus_format_tanggal(?string $tanggal): string
{
    if (!$tanggal) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    if ($timestamp === false) {
        return user_hapus_escape($tanggal);
    }

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

    return date('j', $timestamp) . ' ' .
        ($bulan[(int) date('n', $timestamp)] ?? date('F', $timestamp)) .
        ' ' . date('Y', $timestamp);
}

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

$currentUserId = current_user_id();

if ($currentUserId !== null && (int) $currentUserId === (int) $id) {
    $_SESSION['error'] = 'Anda tidak dapat menghapus akun yang sedang digunakan.';
    header('Location: ' . $base_url . '/admin/users.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.username,
        u.nama,
        u.role,
        u.created_at,
        u.updated_at,

        g.nip,
        g.email AS email_guru,
        g.no_hp AS no_hp_guru,

        s.nis,
        s.jenis_kelamin,
        s.email AS email_siswa,
        s.no_hp AS no_hp_siswa

    FROM users u

    LEFT JOIN guru g
        ON g.user_id = u.id

    LEFT JOIN siswa s
        ON s.user_id = u.id

    WHERE u.id = :id

    LIMIT 1
");

$stmt->execute([
    'id' => $id,
]);

$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = 'Data pengguna tidak ditemukan.';
    header('Location: ' . $base_url . '/admin/users.php');
    exit;
}

$email = '-';
$noHp = '-';
$identifierLabel = '-';
$identifier = '-';

if ($user['role'] === 'guru') {
    $email = $user['email_guru'] ?: '-';
    $noHp = $user['no_hp_guru'] ?: '-';
    $identifierLabel = 'NIP';
    $identifier = $user['nip'] ?: '-';
} elseif ($user['role'] === 'siswa') {
    $email = $user['email_siswa'] ?: '-';
    $noHp = $user['no_hp_siswa'] ?: '-';
    $identifierLabel = 'NIS';
    $identifier = $user['nis'] ?: '-';
}

$totalKelasGuru = 0;
$totalKelasSiswa = 0;
$totalAbsensi = 0;

if ($user['role'] === 'guru') {

    $stmtRelated = $pdo->prepare("
        SELECT COUNT(*)
        FROM kelas
        WHERE guru_id = :user_id
    ");

    $stmtRelated->execute([
        'user_id' => $id,
    ]);

    $totalKelasGuru = (int) $stmtRelated->fetchColumn();

} elseif ($user['role'] === 'siswa') {

    $stmtRelated = $pdo->prepare("
        SELECT COUNT(*)
        FROM kelas_siswa
        WHERE siswa_id = :user_id
    ");

    $stmtRelated->execute([
        'user_id' => $id,
    ]);

    $totalKelasSiswa = (int) $stmtRelated->fetchColumn();

    $stmtRelated = $pdo->prepare("
        SELECT COUNT(*)
        FROM absensi
        WHERE siswa_id = :user_id
    ");

    $stmtRelated->execute([
        'user_id' => $id,
    ]);

    $totalAbsensi = (int) $stmtRelated->fetchColumn();
}

$canDelete = true;
$deleteBlockReason = '';

if ($user['role'] === 'guru' && $totalKelasGuru > 0) {

    $canDelete = false;

    $deleteBlockReason =
        'Pengguna ini masih terdaftar sebagai guru pada ' .
        number_format($totalKelasGuru) .
        ' kelas. Hapus atau pindahkan kelas tersebut terlebih dahulu.';

} elseif ($user['role'] === 'siswa' && ($totalKelasSiswa > 0 || $totalAbsensi > 0)) {

    $canDelete = false;

    $reasons = [];

    if ($totalKelasSiswa > 0) {
        $reasons[] =
            number_format($totalKelasSiswa) .
            ' kelas yang diikuti';
    }

    if ($totalAbsensi > 0) {
        $reasons[] =
            number_format($totalAbsensi) .
            ' data absensi';
    }

    $deleteBlockReason =
        'Pengguna ini masih memiliki ' .
        implode(' dan ', $reasons) .
        '. Hapus data terkait terlebih dahulu.';
}

$csrfToken = $_SESSION['csrf_token'] ?? '';

$flashError = $_SESSION['error'] ?? '';
$flashSuccess = $_SESSION['success'] ?? '';

unset($_SESSION['error'], $_SESSION['success']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="app-layout">

    <main class="main-content">

        <div class="dashboard-page">

            <div class="page-header mb-4">

                <div>

                    <div class="d-flex align-items-center gap-2 mb-2">

                        <a
                            href="<?= user_hapus_escape($base_url) ?>/admin/users.php"
                            class="btn btn-sm btn-outline-secondary"
                        >
                            <i class="bi bi-arrow-left me-1"></i>
                            Kembali
                        </a>

                    </div>

                    <h1 class="page-title mb-1">
                        Hapus Pengguna
                    </h1>

                    <p class="page-subtitle mb-0">
                        Periksa informasi pengguna sebelum melakukan penghapusan.
                    </p>

                </div>

            </div>

            <?php if (!empty($flashError)): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >
                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= user_hapus_escape($flashError) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php endif; ?>

            <?php if (!empty($flashSuccess)): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >
                    <i class="bi bi-check-circle me-2"></i>

                    <?= user_hapus_escape($flashSuccess) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"
                    ></button>
                </div>

            <?php endif; ?>

            <div class="row g-4">

                <div class="col-lg-8">

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-trash3 me-2 text-danger"></i>
                                    Konfirmasi Penghapusan
                                </h5>

                                <p class="text-muted small mb-0">
                                    Pastikan pengguna yang dipilih sudah benar.
                                </p>
                            </div>

                        </div>

                        <div class="dashboard-card-body">

                            <?php if ($canDelete): ?>

                                <div class="alert alert-warning d-flex align-items-start gap-3">

                                    <i class="bi bi-exclamation-triangle fs-4"></i>

                                    <div>
                                        <strong>Apakah Anda yakin?</strong>

                                        <div class="small mt-1">
                                            Akun pengguna
                                            <strong>
                                                <?= user_hapus_escape($user['nama']) ?>
                                            </strong>
                                            dengan username
                                            <strong>
                                                @<?= user_hapus_escape($user['username']) ?>
                                            </strong>
                                            akan dihapus secara permanen.
                                        </div>
                                    </div>

                                </div>

                                <form
                                    action="<?= user_hapus_escape($base_url) ?>/actions/user.php"
                                    method="POST"
                                    id="formHapusUser"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="hapus"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $user['id'] ?>"
                                    >

                                    <?php if (!empty($csrfToken)): ?>

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= user_hapus_escape($csrfToken) ?>"
                                        >

                                    <?php endif; ?>

                                    <div class="form-check mb-4">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="confirmDelete"
                                            name="confirm_delete"
                                            value="1"
                                            required
                                        >

                                        <label
                                            class="form-check-label"
                                            for="confirmDelete"
                                        >
                                            Saya memahami bahwa akun pengguna ini akan
                                            dihapus secara permanen dan tindakan ini
                                            tidak dapat dibatalkan.
                                        </label>

                                    </div>

                                    <div class="d-flex flex-wrap gap-2">

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                            id="btnDelete"
                                            disabled
                                        >
                                            <i class="bi bi-trash3 me-1"></i>
                                            Hapus Pengguna
                                        </button>

                                        <a
                                            href="<?= user_hapus_escape($base_url) ?>/admin/user_view.php?id=<?= (int) $user['id'] ?>"
                                            class="btn btn-outline-secondary"
                                        >
                                            Batal
                                        </a>

                                    </div>

                                </form>

                            <?php else: ?>

                                <div class="alert alert-danger d-flex align-items-start gap-3 mb-4">

                                    <i class="bi bi-shield-exclamation fs-4"></i>

                                    <div>

                                        <strong>Pengguna tidak dapat dihapus</strong>

                                        <div class="small mt-1">
                                            <?= user_hapus_escape($deleteBlockReason) ?>
                                        </div>

                                    </div>

                                </div>

                                <?php if ($user['role'] === 'guru'): ?>

                                    <div class="row g-3 mb-4">

                                        <div class="col-sm-6">

                                            <div class="border rounded p-3 h-100">

                                                <div class="d-flex align-items-center gap-3">

                                                    <div class="stat-card-icon bg-primary-subtle text-primary">
                                                        <i class="bi bi-mortarboard"></i>
                                                    </div>

                                                    <div>
                                                        <div class="text-muted small">
                                                            Kelas yang Diajar
                                                        </div>

                                                        <div class="fs-4 fw-bold">
                                                            <?= number_format($totalKelasGuru) ?>
                                                        </div>
                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                <?php elseif ($user['role'] === 'siswa'): ?>

                                    <div class="row g-3 mb-4">

                                        <div class="col-sm-6">

                                            <div class="border rounded p-3 h-100">

                                                <div class="d-flex align-items-center gap-3">

                                                    <div class="stat-card-icon bg-primary-subtle text-primary">
                                                        <i class="bi bi-mortarboard"></i>
                                                    </div>

                                                    <div>
                                                        <div class="text-muted small">
                                                            Kelas Diikuti
                                                        </div>

                                                        <div class="fs-4 fw-bold">
                                                            <?= number_format($totalKelasSiswa) ?>
                                                        </div>
                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                        <div class="col-sm-6">

                                            <div class="border rounded p-3 h-100">

                                                <div class="d-flex align-items-center gap-3">

                                                    <div class="stat-card-icon bg-danger-subtle text-danger">
                                                        <i class="bi bi-calendar-check"></i>
                                                    </div>

                                                    <div>
                                                        <div class="text-muted small">
                                                            Data Absensi
                                                        </div>

                                                        <div class="fs-4 fw-bold">
                                                            <?= number_format($totalAbsensi) ?>
                                                        </div>
                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <div class="alert alert-info d-flex align-items-start gap-3 mb-0">

                                    <i class="bi bi-info-circle fs-5"></i>

                                    <div class="small">
                                        Sistem mencegah penghapusan pengguna yang masih
                                        memiliki data yang berhubungan dengannya agar
                                        riwayat sekolah tidak hilang atau menjadi tidak
                                        konsisten.
                                    </div>

                                </div>

                                <div class="mt-4">

                                    <a
                                        href="<?= user_hapus_escape($base_url) ?>/admin/user_view.php?id=<?= (int) $user['id'] ?>"
                                        class="btn btn-outline-primary"
                                    >
                                        <i class="bi bi-arrow-left me-1"></i>
                                        Kembali ke Detail Pengguna
                                    </a>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <div class="col-lg-4">

                    <div class="dashboard-card mb-4">

                        <div class="dashboard-card-header">

                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-person me-2"></i>
                                    Detail Pengguna
                                </h5>

                                <p class="text-muted small mb-0">
                                    Data akun yang akan diproses.
                                </p>
                            </div>

                        </div>

                        <div class="dashboard-card-body">

                            <div class="text-center mb-4">

                                <div class="avatar avatar-xl mx-auto mb-3">

                                    <?= user_hapus_escape(
                                        strtoupper(
                                            substr(
                                                trim($user['nama']),
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>

                                </div>

                                <h5 class="mb-1">
                                    <?= user_hapus_escape($user['nama']) ?>
                                </h5>

                                <p class="text-muted small mb-2">
                                    @<?= user_hapus_escape($user['username']) ?>
                                </p>

                                <?= user_hapus_role_badge($user['role']) ?>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    ID Pengguna
                                </div>

                                <div class="profile-info-value">
                                    #<?= (int) $user['id'] ?>
                                </div>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    <?= user_hapus_escape($identifierLabel) ?>
                                </div>

                                <div class="profile-info-value">
                                    <?= user_hapus_escape($identifier) ?>
                                </div>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    Email
                                </div>

                                <div class="profile-info-value text-break">
                                    <?= user_hapus_escape($email) ?>
                                </div>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    No. HP
                                </div>

                                <div class="profile-info-value">
                                    <?= user_hapus_escape($noHp) ?>
                                </div>

                            </div>

                            <div class="profile-info">

                                <div class="profile-info-label">
                                    Terdaftar Sejak
                                </div>

                                <div class="profile-info-value">
                                    <?= user_hapus_format_tanggal($user['created_at']) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-exclamation-circle me-2"></i>
                                    Perhatian
                                </h5>
                            </div>

                        </div>

                        <div class="dashboard-card-body">

                            <ul class="text-muted small ps-3 mb-0">

                                <li class="mb-2">
                                    Penghapusan akun bersifat permanen.
                                </li>

                                <li class="mb-2">
                                    Pastikan pengguna yang dipilih sudah benar.
                                </li>

                                <li class="mb-2">
                                    Akun guru yang masih digunakan oleh kelas tidak
                                    dapat dihapus.
                                </li>

                                <li>
                                    Akun siswa yang masih mempunyai kelas atau riwayat
                                    absensi tidak dapat dihapus.
                                </li>

                            </ul>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const checkbox = document.getElementById('confirmDelete');
    const button = document.getElementById('btnDelete');
    const form = document.getElementById('formHapusUser');

    if (!checkbox || !button || !form) {
        return;
    }

    checkbox.addEventListener('change', function () {
        button.disabled = !checkbox.checked;
    });

    form.addEventListener('submit', function (event) {

        if (!checkbox.checked) {
            event.preventDefault();
            return;
        }

        const confirmed = window.confirm(
            'Apakah Anda benar-benar yakin ingin menghapus pengguna ini? Data yang sudah dihapus tidak dapat dikembalikan.'
        );

        if (!confirmed) {
            event.preventDefault();
            return;
        }

        button.disabled = true;

        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            'Menghapus...';
    });

});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>