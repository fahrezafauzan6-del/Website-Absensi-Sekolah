<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/database.php';

check_role('admin');

$page_title = 'Edit Kelas';

$base_url = '/absensi-sekolah';

$allowed_days = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

function kelas_edit_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function kelas_edit_time(?string $time): string
{
    if (!$time) {
        return '';
    }

    return date('H:i', strtotime($time));
}

$id = null;

if (isset($_GET['id'])) {
    $id = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );
} elseif (isset($_POST['id'])) {
    $id = filter_var(
        $_POST['id'],
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1,
            ],
        ]
    );
}

if ($id === false || $id === null || $id <= 0) {
    header(
        'Location: ' .
        $base_url .
        '/admin/kelas.php?error=invalid_id'
    );
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
        g.nama AS guru_nama
    FROM kelas k
    INNER JOIN guru g
        ON g.id = k.guru_id
    WHERE k.id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id,
]);

$kelas = $stmt->fetch();

if (!$kelas) {
    header(
        'Location: ' .
        $base_url .
        '/admin/kelas.php?error=not_found'
    );
    exit;
}

$stmt = $pdo->query("
    SELECT
        g.id,
        g.user_id,
        g.nip,
        g.nama
    FROM guru g
    ORDER BY g.nama ASC
");

$guru_list = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        s.id,
        s.user_id,
        s.nis,
        s.nama,
        s.jenis_kelamin
    FROM siswa s
    ORDER BY s.nama ASC
");

$siswa_list = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT siswa_id
    FROM kelas_siswa
    WHERE kelas_id = :kelas_id
");

$stmt->execute([
    ':kelas_id' => $id,
]);

$current_siswa_ids = array_map(
    'intval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';

$old = $_SESSION['old'] ?? [];

unset(
    $_SESSION['flash_success'],
    $_SESSION['flash_error'],
    $_SESSION['old']
);

$nama_kelas = (string) (
    $old['nama_kelas'] ??
    $kelas['nama_kelas']
);

$guru_id = (string) (
    $old['guru_id'] ??
    $kelas['guru_id']
);

$hari = (string) (
    $old['hari'] ??
    $kelas['hari']
);

$jam_mulai = (string) (
    $old['jam_mulai'] ??
    kelas_edit_time($kelas['jam_mulai'])
);

$jam_selesai = (string) (
    $old['jam_selesai'] ??
    kelas_edit_time($kelas['jam_selesai'])
);

if (array_key_exists('siswa_ids', $old)) {
    $selected_siswa = $old['siswa_ids'];

    if (!is_array($selected_siswa)) {
        $selected_siswa = [];
    }

    $selected_siswa = array_values(
        array_filter(
            array_map(
                static fn($studentId): int => (int) $studentId,
                $selected_siswa
            ),
            static fn(int $studentId): bool => $studentId > 0
        )
    );
} else {
    $selected_siswa = $current_siswa_ids;
}

$csrf_token = $_SESSION['csrf_token'] ?? '';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div class="dashboard-page">

        <div class="page-header mb-4">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Kelas
                </h1>
                <p class="page-subtitle mb-0">
                    Ubah informasi kelas, guru pengampu, jadwal, dan daftar siswa.
                </p>
            </div>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= kelas_edit_escape($flash_success) ?>

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
                <?= kelas_edit_escape($flash_error) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"
                ></button>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= kelas_edit_escape($base_url) ?>/actions/kelas.php"
            id="formEditKelas"
            novalidate
        >
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <?php if ($csrf_token): ?>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= kelas_edit_escape($csrf_token) ?>"
                >
            <?php endif; ?>

            <div class="row g-4">

                <div class="col-12 col-xl-7">
                    <div class="dashboard-card h-100">

                        <div class="dashboard-card-header">
                            <div>
                                <h2 class="dashboard-card-title mb-1">
                                    <i class="bi bi-journal-text me-2"></i>
                                    Informasi Kelas
                                </h2>

                                <p class="text-muted small mb-0">
                                    Kelas #<?= (int) $id ?>
                                </p>
                            </div>
                        </div>

                        <div class="dashboard-card-body">

                            <div class="mb-3">
                                <label for="nama_kelas" class="form-label">
                                    Nama Kelas
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="nama_kelas"
                                    name="nama_kelas"
                                    class="form-control"
                                    value="<?= kelas_edit_escape($nama_kelas) ?>"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label for="guru_id" class="form-label">
                                    Guru Pengampu
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    id="guru_id"
                                    name="guru_id"
                                    class="form-select"
                                    required
                                >
                                    <option value="">-- Pilih Guru --</option>

                                    <?php foreach ($guru_list as $guru): ?>
                                        <option
                                            value="<?= (int) $guru['id'] ?>"
                                            <?= $guru_id === (string) $guru['id'] ? 'selected' : '' ?>
                                        >
                                            <?= kelas_edit_escape($guru['nama']) ?>

                                            <?php if (!empty($guru['nip'])): ?>
                                                — NIP <?= kelas_edit_escape($guru['nip']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label for="hari" class="form-label">
                                        Hari
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select
                                        id="hari"
                                        name="hari"
                                        class="form-select"
                                        required
                                    >
                                        <?php foreach ($allowed_days as $day): ?>
                                            <option
                                                value="<?= kelas_edit_escape($day) ?>"
                                                <?= $hari === $day ? 'selected' : '' ?>
                                            >
                                                <?= kelas_edit_escape($day) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="jam_mulai" class="form-label">
                                        Jam Mulai
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="time"
                                        id="jam_mulai"
                                        name="jam_mulai"
                                        class="form-control"
                                        value="<?= kelas_edit_escape($jam_mulai) ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-3">
                                    <label for="jam_selesai" class="form-label">
                                        Jam Selesai
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="time"
                                        id="jam_selesai"
                                        name="jam_selesai"
                                        class="form-control"
                                        value="<?= kelas_edit_escape($jam_selesai) ?>"
                                        required
                                    >
                                </div>

                            </div>

                            <div
                                id="schedulePreview"
                                class="alert alert-primary mt-4 d-none"
                            >
                                <i class="bi bi-calendar-event me-2"></i>
                                <span id="schedulePreviewText"></span>
                            </div>

                            <div class="alert alert-info mt-4 mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Jika siswa yang sudah memiliki riwayat absensi
                                dikeluarkan dari kelas, perubahan tersebut akan
                                ditolak agar data absensinya tetap aman.
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="dashboard-card h-100">

                        <div class="dashboard-card-header">
                            <div>
                                <h2 class="dashboard-card-title mb-1">
                                    <i class="bi bi-people me-2"></i>
                                    Siswa Kelas
                                </h2>

                                <p class="text-muted small mb-0">
                                    Kelola anggota kelas.
                                </p>
                            </div>

                            <span
                                class="badge bg-primary-subtle text-primary"
                                id="selectedStudentCount"
                            >
                                0 siswa
                            </span>
                        </div>

                        <div class="dashboard-card-body">

                            <?php if (empty($siswa_list)): ?>

                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Belum ada data siswa.
                                </div>

                            <?php else: ?>

                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-search"></i>
                                        </span>

                                        <input
                                            type="search"
                                            id="searchSiswa"
                                            class="form-control"
                                            placeholder="Cari nama atau NIS..."
                                            autocomplete="off"
                                        >
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        id="selectAllSiswa"
                                    >
                                        <i class="bi bi-check2-square me-1"></i>
                                        Pilih Semua
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        id="clearAllSiswa"
                                    >
                                        <i class="bi bi-x-square me-1"></i>
                                        Kosongkan
                                    </button>
                                </div>

                                <div
                                    id="siswaList"
                                    class="border rounded p-2"
                                    style="max-height: 420px; overflow-y: auto;"
                                >
                                    <?php foreach ($siswa_list as $siswa): ?>
                                        <?php
                                        $siswa_id = (int) $siswa['id'];

                                        $is_selected = in_array(
                                            $siswa_id,
                                            $selected_siswa,
                                            true
                                        );

                                        $search_text = strtolower(
                                            (string) $siswa['nama'] .
                                            ' ' .
                                            (string) $siswa['nis']
                                        );
                                        ?>

                                        <label
                                            class="d-flex align-items-center gap-3 p-2 rounded border-bottom siswa-option"
                                            data-siswa-search="<?= kelas_edit_escape($search_text) ?>"
                                            style="cursor: pointer;"
                                        >
                                            <input
                                                type="checkbox"
                                                name="siswa_ids[]"
                                                value="<?= $siswa_id ?>"
                                                class="form-check-input siswa-checkbox flex-shrink-0"
                                                <?= $is_selected ? 'checked' : '' ?>
                                            >

                                            <span class="flex-grow-1">
                                                <span class="d-block fw-semibold">
                                                    <?= kelas_edit_escape($siswa['nama']) ?>
                                                </span>

                                                <span class="d-block small text-muted">
                                                    NIS:
                                                    <?= kelas_edit_escape($siswa['nis']) ?>

                                                    <?php if (!empty($siswa['jenis_kelamin'])): ?>
                                                        ·
                                                        <?= $siswa['jenis_kelamin'] === 'L'
                                                            ? 'Laki-laki'
                                                            : 'Perempuan' ?>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div class="form-text mt-2">
                                    Centang siswa untuk menambahkannya ke kelas.
                                    Hapus centang untuk mengeluarkan siswa.
                                </div>

                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mt-4">

                <a
                    href="<?= kelas_edit_escape($base_url) ?>/admin/kelas_view.php?id=<?= (int) $id ?>"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-eye me-1"></i>
                    Lihat Detail
                </a>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a
                        href="<?= kelas_edit_escape($base_url) ?>/admin/kelas.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-x-lg me-1"></i>
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="btnSubmit"
                    >
                        <i class="bi bi-save me-1"></i>
                        Simpan Perubahan
                    </button>
                </div>

            </div>
        </form>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formEditKelas');

    const namaKelas = document.getElementById('nama_kelas');
    const guruId = document.getElementById('guru_id');
    const hari = document.getElementById('hari');
    const jamMulai = document.getElementById('jam_mulai');
    const jamSelesai = document.getElementById('jam_selesai');

    const preview = document.getElementById('schedulePreview');
    const previewText = document.getElementById('schedulePreviewText');

    const checkboxes = Array.from(
        document.querySelectorAll('.siswa-checkbox')
    );

    const countElement = document.getElementById('selectedStudentCount');
    const searchSiswa = document.getElementById('searchSiswa');
    const selectAll = document.getElementById('selectAllSiswa');
    const clearAll = document.getElementById('clearAllSiswa');

    function updateStudentCount() {
        const selected = checkboxes.filter(
            checkbox => checkbox.checked
        ).length;

        if (countElement) {
            countElement.textContent = selected + ' siswa';
        }
    }

    function updatePreview() {
        if (!preview || !previewText) {
            return;
        }

        if (
            hari.value &&
            jamMulai.value &&
            jamSelesai.value
        ) {
            if (jamMulai.value >= jamSelesai.value) {
                preview.classList.remove('d-none', 'alert-primary');
                preview.classList.add('alert-danger');

                previewText.textContent =
                    'Jam selesai harus lebih besar dari jam mulai.';

                return;
            }

            preview.classList.remove('d-none', 'alert-danger');
            preview.classList.add('alert-primary');

            previewText.textContent =
                hari.value +
                ', pukul ' +
                jamMulai.value +
                ' - ' +
                jamSelesai.value;
        } else {
            preview.classList.add('d-none');
        }
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateStudentCount);
    });

    if (selectAll) {
        selectAll.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                const option = checkbox.closest('.siswa-option');

                if (
                    !option ||
                    option.style.display !== 'none'
                ) {
                    checkbox.checked = true;
                }
            });

            updateStudentCount();
        });
    }

    if (clearAll) {
        clearAll.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });

            updateStudentCount();
        });
    }

    if (searchSiswa) {
        searchSiswa.addEventListener('input', function () {
            const keyword = this.value.trim().toLowerCase();

            document.querySelectorAll('.siswa-option').forEach(function (option) {
                const text =
                    option.getAttribute('data-siswa-search') || '';

                option.style.display =
                    !keyword || text.includes(keyword)
                        ? ''
                        : 'none';
            });
        });
    }

    [hari, jamMulai, jamSelesai].forEach(function (element) {
        if (element) {
            element.addEventListener('change', updatePreview);
        }
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            if (
                !namaKelas.value.trim() ||
                !guruId.value ||
                !hari.value ||
                !jamMulai.value ||
                !jamSelesai.value
            ) {
                event.preventDefault();

                alert('Lengkapi semua informasi kelas terlebih dahulu.');
                return;
            }

            if (jamMulai.value >= jamSelesai.value) {
                event.preventDefault();

                alert('Jam selesai harus lebih besar dari jam mulai.');
                jamSelesai.focus();
                return;
            }

            const submitButton = document.getElementById('btnSubmit');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
            }
        });
    }

    updateStudentCount();
    updatePreview();
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>