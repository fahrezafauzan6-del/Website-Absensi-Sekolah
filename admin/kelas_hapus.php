<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Hapus Kelas';

$base_url = $base_url ?? '/absensi-sekolah';

function kelas_hapus_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function kelas_hapus_time(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return substr($value, 0, 5);
    }

    return date('H:i', $timestamp);
}

function kelas_hapus_hari(string $hari): string
{
    $badge = [
        'Senin'  => 'primary',
        'Selasa' => 'info',
        'Rabu'   => 'success',
        'Kamis'  => 'warning',
        'Jumat'  => 'danger',
        'Sabtu'  => 'secondary',
    ];

    $class = $badge[$hari] ?? 'secondary';

    return '<span class="badge text-bg-' . $class . '">' .
        kelas_hapus_escape($hari) .
        '</span>';
}

$id = null;

if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
} elseif (isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
}

if ($id === false || $id === null || $id <= 0) {
    header('Location: ' . $base_url . '/admin/kelas.php?error=invalid_id');
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

        g.nip,
        g.nama AS nama_guru,
        g.email AS email_guru,
        g.no_hp AS no_hp_guru,

        (
            SELECT COUNT(*)
            FROM kelas_siswa ks
            WHERE ks.kelas_id = k.id
        ) AS total_siswa,

        (
            SELECT COUNT(*)
            FROM absensi a
            WHERE a.kelas_id = k.id
        ) AS total_absensi

    FROM kelas k

    INNER JOIN guru g
        ON g.id = k.guru_id

    WHERE k.id = :id

    LIMIT 1
");

$stmt->execute([
    'id' => $id,
]);

$kelas = $stmt->fetch();

if (!$kelas) {
    header('Location: ' . $base_url . '/admin/kelas.php?error=not_found');
    exit;
}

$totalSiswa = (int) $kelas['total_siswa'];
$totalAbsensi = (int) $kelas['total_absensi'];

$canDelete = ($totalSiswa === 0 && $totalAbsensi === 0);

$flash_error = $_SESSION['error'] ?? '';
$flash_success = $_SESSION['success'] ?? '';

if (($_GET['status'] ?? '') === 'success') {
    $flash_success = (string) ($_GET['message'] ?? $flash_success);
} elseif (($_GET['status'] ?? '') === 'error') {
    $flash_error = (string) ($_GET['message'] ?? $flash_error);
}

unset($_SESSION['error'], $_SESSION['success']);

$csrf_token = $_SESSION['csrf_token'] ?? '';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="app-layout">

    <main class="main-content">

        <div class="dashboard-page">

            <div class="page-header mb-4">

                <div>
                    <h1 class="page-title mb-1">
                        Hapus Kelas
                    </h1>

                    <p class="page-subtitle mb-0">
                        Periksa data kelas sebelum melakukan penghapusan.
                    </p>

                </div>

            </div>

            <?php if (!empty($flash_error)): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= kelas_hapus_escape($flash_error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"></button>

                </div>

            <?php endif; ?>

            <?php if (!empty($flash_success)): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert">
                    <i class="bi bi-check-circle me-2"></i>

                    <?= kelas_hapus_escape($flash_success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Tutup"></button>

                </div>

            <?php endif; ?>

            <div class="row g-2">

                <div class="col-lg-9">

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-trash3 me-2 text-danger"></i>
                                    Konfirmasi Penghapusan
                                </h5>

                                <p class="text-muted small mb-0">
                                    Tindakan ini perlu dikonfirmasi sebelum diproses.
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
                                            Kelas
                                            <strong>
                                                <?= kelas_hapus_escape($kelas['nama_kelas']) ?>
                                            </strong>
                                            akan dihapus secara permanen dari sistem.
                                        </div>
                                    </div>

                                </div>

                                <form
                                    action="<?= kelas_hapus_escape($base_url) ?>/actions/kelas.php"
                                    method="POST"
                                    id="formHapusKelas">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="hapus">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $kelas['id'] ?>">

                                    <?php if (!empty($csrf_token)): ?>

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= kelas_hapus_escape($csrf_token) ?>">

                                    <?php endif; ?>

                                    <div class="form-check mb-4">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="confirmDelete"
                                            name="confirm_delete"
                                            value="1"
                                            required>

                                        <label
                                            class="form-check-label"
                                            for="confirmDelete">
                                            Saya memahami bahwa data kelas ini akan dihapus
                                            dan tindakan ini tidak dapat dibatalkan.
                                        </label>

                                    </div>

                                    <div class="d-flex flex-wrap gap-2">

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                            id="btnDelete"
                                            disabled>
                                            <i class="bi bi-trash3 me-1"></i>
                                            Hapus Kelas
                                        </button>

                                        <a
                                            href="<?= kelas_hapus_escape($base_url) ?>/admin/kelas_view.php?id=<?= (int) $kelas['id'] ?>"
                                            class="btn btn-outline-secondary">
                                            Batal
                                        </a>

                                    </div>

                                </form>

                            <?php else: ?>

                                <div class="alert alert-danger d-flex align-items-start gap-3 mb-4">

                                    <i class="bi bi-shield-exclamation fs-4"></i>

                                    <div>
                                        <strong>Kelas tidak dapat dihapus</strong>

                                        <div class="small mt-1">
                                            Kelas ini masih memiliki data yang
                                            berhubungan dengannya. Hapus atau pindahkan
                                            data terkait terlebih dahulu.
                                        </div>
                                    </div>

                                </div>

                                <div class="row g-3 mb-4">

                                    <div class="col-sm-6">

                                        <div class="border rounded p-3 h-100">

                                            <div class="d-flex align-items-center gap-3">

                                                <div class="stat-card-icon bg-primary-subtle text-primary">
                                                    <i class="bi bi-people"></i>
                                                </div>

                                                <div>
                                                    <div class="text-muted small">
                                                        Siswa Terdaftar
                                                    </div>

                                                    <div class="fs-4 fw-bold">
                                                        <?= number_format($totalSiswa) ?>
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

                                <div class="alert alert-info d-flex align-items-start gap-3 mb-0">

                                    <i class="bi bi-info-circle fs-5"></i>

                                    <div class="small">
                                        Sistem mencegah penghapusan kelas yang masih
                                        mempunyai siswa atau riwayat absensi agar data
                                        sekolah tidak hilang secara tidak sengaja.
                                    </div>

                                </div>

                                <div class="mt-4">

                                    <a
                                        href="<?= kelas_hapus_escape($base_url) ?>/admin/kelas_view.php?id=<?= (int) $kelas['id'] ?>"
                                        class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-left me-1"></i>
                                        Kembali ke Detail Kelas
                                    </a>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

                <div class="col-lg-3">

                    <div class="dashboard-card mb-4">

                        <div class="dashboard-card-header">

                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-mortarboard me-2"></i>
                                    Detail Kelas
                                </h5>

                                <p class="text-muted small mb-0">
                                    Data kelas yang akan diproses.
                                </p>
                            </div>

                        </div>

                        <div class="dashboard-card-body">

                            <div class="text-center mb-4">

                                <div class="avatar avatar-xl mx-auto mb-3">
                                    <i class="bi bi-mortarboard"></i>
                                </div>

                                <h5 class="mb-1">
                                    <?= kelas_hapus_escape($kelas['nama_kelas']) ?>
                                </h5>

                                <p class="text-muted small mb-0">
                                    ID Kelas #<?= (int) $kelas['id'] ?>
                                </p>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    Guru Pengajar :
                                </div>

                                <div class="profile-info-value fw-semibold">
                                    <?= kelas_hapus_escape($kelas['nama_guru']) ?>
                                </div>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    NIP Guru :
                                </div>

                                <div class="profile-info-value">
                                    <?= kelas_hapus_escape($kelas['nip']) ?>
                                </div>

                            </div>

                            <div class="profile-info mb-3">

                                <div class="profile-info-label">
                                    Jadwal :
                                </div>

                                <div class="profile-info-value">

                                    <?= kelas_hapus_hari($kelas['hari']) ?>

                                    <div class="mt-1 text-muted small">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= kelas_hapus_escape(kelas_hapus_time($kelas['jam_mulai'])) ?>
                                        -
                                        <?= kelas_hapus_escape(kelas_hapus_time($kelas['jam_selesai'])) ?>
                                    </div>

                                </div>

                            </div>

                            <div class="profile-info">

                                <div class="profile-info-label">
                                    Data Terkait
                                </div>

                                <div class="profile-info-value">

                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Siswa :</span>
                                        <strong>
                                            <?= number_format($totalSiswa) ?>
                                        </strong>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Absensi :</span>
                                        <strong>
                                            <?= number_format($totalAbsensi) ?>
                                        </strong>
                                    </div>

                                </div>

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
                                Penghapusan kelas bersifat permanen.
                            </li>

                            <li class="mb-2">
                                Data kelas yang masih memiliki siswa tidak dapat
                                dihapus.
                            </li>

                            <li>
                                Data kelas yang masih memiliki riwayat absensi juga
                                tidak dapat dihapus.
                            </li>

                        </ul>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const checkbox = document.getElementById('confirmDelete');
        const button = document.getElementById('btnDelete');
        const form = document.getElementById('formHapusKelas');

        if (!checkbox || !button || !form) {
            return;
        }

        checkbox.addEventListener('change', function() {
            button.disabled = !checkbox.checked;
        });

        form.addEventListener('submit', function(event) {

            if (!checkbox.checked) {
                event.preventDefault();
                return;
            }

            const confirmed = window.confirm(
                'Apakah Anda benar-benar yakin ingin menghapus kelas ini? Data yang sudah dihapus tidak dapat dikembalikan.'
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