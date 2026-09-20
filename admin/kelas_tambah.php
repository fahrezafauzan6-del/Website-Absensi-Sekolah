<?php
require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Tambah Kelas';

$base_url = '/absensi-sekolah';

$allowed_days = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

$stmt = $pdo->query("
    SELECT
        id,
        user_id,
        nip,
        nama
    FROM guru
    ORDER BY nama ASC
");

$teachers = $stmt->fetchAll();

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

unset(
    $_SESSION['error'],
    $_SESSION['success']
);

$nama_kelas = $_SESSION['old']['nama_kelas'] ?? '';
$guru_id = $_SESSION['old']['guru_id'] ?? '';
$hari = $_SESSION['old']['hari'] ?? '';
$jam_mulai = $_SESSION['old']['jam_mulai'] ?? '';
$jam_selesai = $_SESSION['old']['jam_selesai'] ?? '';

unset($_SESSION['old']);

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
                        <i class="bi bi-collection me-2"></i>
                        Tambah Kelas
                    </h1>

                    <p class="page-subtitle">
                        Tambahkan kelas baru ke dalam sistem
                    </p>
                </div>
            </div>

            <?php if ($error): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert">

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= htmlspecialchars($error) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"></button>

                </div>

            <?php endif; ?>

            <?php if ($success): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert">

                    <i class="bi bi-check-circle me-2"></i>

                    <?= htmlspecialchars($success) ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"></button>

                </div>

            <?php endif; ?>

            <div class="card shadow-sm mt-4">
                <div>

                    <div>

                        <div>

                            <div class="card-header bg-white">

                                <h5 class="contoh2.php">
                                    <i class="bi bi-pencil-square me-2"></i>
                                    Informasi Kelas
                                </h5>

                                <small class="text-muted">
                                    Lengkapi informasi kelas di bawah ini.
                                </small>

                            </div>

                            <div class="card-body">

                                <?php if (!$teachers): ?>

                                    <div class="alert alert-warning">

                                        <div class="d-flex gap-3">

                                            <i class="bi bi-exclamation-circle-fill fs-4"></i>

                                            <div>

                                                <h6 class="alert-heading">
                                                    Belum ada guru
                                                </h6>

                                                <p class="mb-2">
                                                    Kelas membutuhkan guru pengampu.
                                                    Tambahkan akun guru terlebih dahulu.
                                                </p>

                                                <a
                                                    href="<?= htmlspecialchars($base_url) ?>/admin/user_tambah.php"
                                                    class="btn btn-sm btn-warning">
                                                    <i class="bi bi-person-plus me-1"></i>
                                                    Tambah Guru
                                                </a>

                                            </div>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <form
                                    action="<?= htmlspecialchars($base_url) ?>/actions/kelas.php"
                                    method="POST"
                                    id="formTambahKelas"
                                    autocomplete="off">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="tambah">

                                    <?php if (!empty($_SESSION['csrf_token'])): ?>

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                    <?php endif; ?>

                                    <div class="row g-4">

                                        <div class="col-12">

                                            <label
                                                for="nama_kelas"
                                                class="form-label">
                                                Nama Kelas
                                                <span class="text-danger">*</span>
                                            </label>

                                            <div class="input-group">

                                                <span class="input-group-text">
                                                    <i class="bi bi-collection"></i>
                                                </span>

                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="nama_kelas"
                                                    name="nama_kelas"
                                                    value="<?= htmlspecialchars($nama_kelas) ?>"
                                                    placeholder="Contoh: X IPA 1"
                                                    maxlength="100"
                                                    required
                                                    autofocus>

                                            </div>

                                            <div class="form-text">
                                                Masukkan nama kelas, misalnya X IPA 1,
                                                XI IPS 2, atau XII TKJ 1.
                                            </div>

                                        </div>

                                        <div class="col-12">

                                            <label
                                                for="guru_id"
                                                class="form-label">
                                                Guru Pengampu
                                                <span class="text-danger">*</span>
                                            </label>

                                            <div class="input-group">

                                                <span class="input-group-text">
                                                    <i class="bi bi-person-video3"></i>
                                                </span>

                                                <select
                                                    class="form-select"
                                                    id="guru_id"
                                                    name="guru_id"
                                                    required
                                                    <?= !$teachers ? 'disabled' : '' ?>>

                                                    <option value="">
                                                        -- Pilih Guru Pengampu --
                                                    </option>

                                                    <?php foreach ($teachers as $teacher): ?>

                                                        <option
                                                            value="<?= (int) $teacher['id'] ?>"
                                                            <?= (string) $guru_id === (string) $teacher['id']
                                                                ? 'selected'
                                                                : '' ?>>
                                                            <?= htmlspecialchars($teacher['nama']) ?>

                                                            <?php if (!empty($teacher['nip'])): ?>
                                                                — NIP:
                                                                <?= htmlspecialchars($teacher['nip']) ?>
                                                            <?php endif; ?>

                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                            </div>

                                            <div class="form-text">
                                                Pilih guru yang akan mengampu kelas ini.
                                            </div>

                                        </div>

                                        <div class="col-md-4">

                                            <label
                                                for="hari"
                                                class="form-label">
                                                Hari
                                                <span class="text-danger">*</span>
                                            </label>

                                            <select
                                                class="form-select"
                                                id="hari"
                                                name="hari"
                                                required>

                                                <option value="">
                                                    -- Pilih Hari --
                                                </option>

                                                <?php foreach ($allowed_days as $day): ?>

                                                    <option
                                                        value="<?= htmlspecialchars($day) ?>"
                                                        <?= $hari === $day ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($day) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                        <div class="col-md-4">

                                            <label
                                                for="jam_mulai"
                                                class="form-label">
                                                Jam Mulai
                                                <span class="text-danger">*</span>
                                            </label>

                                            <div class="input-group">

                                                <span class="input-group-text">
                                                    <i class="bi bi-clock"></i>
                                                </span>

                                                <input
                                                    type="time"
                                                    class="form-control"
                                                    id="jam_mulai"
                                                    name="jam_mulai"
                                                    value="<?= htmlspecialchars($jam_mulai) ?>"
                                                    required>

                                            </div>

                                        </div>

                                        <div class="col-md-4">

                                            <label
                                                for="jam_selesai"
                                                class="form-label">
                                                Jam Selesai
                                                <span class="text-danger">*</span>
                                            </label>

                                            <div class="input-group">

                                                <span class="input-group-text">
                                                    <i class="bi bi-clock-history"></i>
                                                </span>

                                                <input
                                                    type="time"
                                                    class="form-control"
                                                    id="jam_selesai"
                                                    name="jam_selesai"
                                                    value="<?= htmlspecialchars($jam_selesai) ?>"
                                                    required>

                                            </div>

                                        </div>

                                    </div>

                                    <hr class="my-4">

                                    <div
                                        class="alert alert-light border"
                                        id="schedulePreview">

                                        <div class="d-flex gap-3">

                                            <div class="text-primary fs-4">
                                                <i class="bi bi-calendar3"></i>
                                            </div>

                                            <div>

                                                <h6 class="mb-1">
                                                    Ringkasan Jadwal
                                                </h6>

                                                <div
                                                    class="text-muted"
                                                    id="schedulePreviewText">
                                                    Pilih hari dan jam untuk melihat
                                                    ringkasan jadwal.
                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="d-flex justify-content-end gap-2">

                                        <a
                                            href="<?= htmlspecialchars($base_url) ?>/admin/kelas.php"
                                            class="btn btn-light">
                                            <i class="bi bi-x-lg me-1"></i>
                                            Batal
                                        </a>

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            <?= !$teachers ? 'disabled' : '' ?>>
                                            <i class="bi bi-save me-1"></i>
                                            Simpan Kelas
                                        </button>

                                    </div>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>
            </div>

            <div class="card shadow-sm mt-4">

                <div class="card-body">

                    <div class="d-flex gap-3">

                        <div class="text-info fs-4">
                            <i class="bi bi-info-circle"></i>
                        </div>

                        <div>

                            <h6 class="mb-2">
                                Informasi
                            </h6>

                            <ul class="text-muted mb-0 ps-3">

                                <li>
                                    Nama kelas wajib diisi.
                                </li>

                                <li>
                                    Setiap kelas harus memiliki
                                    guru pengampu.
                                </li>

                                <li>
                                    Jam selesai harus lebih besar
                                    daripada jam mulai.
                                </li>

                                <li>
                                    Jadwal guru yang bertabrakan
                                    dengan kelas lain akan ditolak
                                    oleh sistem.
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
    document.addEventListener('DOMContentLoaded', function() {

        const hari = document.getElementById('hari');
        const jamMulai = document.getElementById('jam_mulai');
        const jamSelesai = document.getElementById('jam_selesai');
        const previewText = document.getElementById('schedulePreviewText');
        const form = document.getElementById('formTambahKelas');

        function formatTime(value) {

            if (!value) {
                return '';
            }

            const parts = value.split(':');

            if (parts.length < 2) {
                return value;
            }

            return parts[0] + ':' + parts[1];
        }

        function updatePreview() {

            const selectedDay =
                hari.options[hari.selectedIndex]?.text || '';

            const start =
                formatTime(jamMulai.value);

            const end =
                formatTime(jamSelesai.value);

            if (
                !hari.value &&
                !start &&
                !end
            ) {

                previewText.textContent =
                    'Pilih hari dan jam untuk melihat ringkasan jadwal.';

                return;
            }

            let text = '';

            if (hari.value) {
                text += selectedDay;
            }

            if (start) {
                text += text ? ' • ' : '';
                text += start;
            }

            if (end) {
                text += start ? ' - ' + end : end;
            }

            previewText.textContent = text;
        }

        function validateTime() {

            if (
                jamMulai.value &&
                jamSelesai.value &&
                jamSelesai.value <= jamMulai.value
            ) {

                jamSelesai.setCustomValidity(
                    'Jam selesai harus lebih besar daripada jam mulai.'
                );

            } else {

                jamSelesai.setCustomValidity('');
            }
        }

        if (hari) {
            hari.addEventListener('change', updatePreview);
        }

        if (jamMulai) {
            jamMulai.addEventListener('change', function() {
                validateTime();
                updatePreview();
            });
        }

        if (jamSelesai) {
            jamSelesai.addEventListener('change', function() {
                validateTime();
                updatePreview();
            });
        }

        if (form) {

            form.addEventListener('submit', function(event) {

                validateTime();

                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            });

        }

        updatePreview();

    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>