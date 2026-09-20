<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('admin');

$page_title = 'Edit Pengguna';

$base_url = '/absensi-sekolah';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: ' . $base_url . '/admin/users.php?status=danger&message=' . urlencode('ID pengguna tidak valid.'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

try {
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.username,
            u.nama,
            u.role,
            u.created_at,
            u.updated_at,

            g.nip,
            g.email AS guru_email,
            g.no_hp AS guru_no_hp,

            s.nis,
            s.jenis_kelamin,
            s.email AS siswa_email,
            s.no_hp AS siswa_no_hp

        FROM users u
        LEFT JOIN guru g
            ON g.user_id = u.id
        LEFT JOIN siswa s
            ON s.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ' . $base_url . '/admin/users.php?status=danger&message=' . urlencode('Pengguna tidak ditemukan.'));
        exit;
    }
} catch (PDOException $e) {
    error_log('Gagal mengambil data pengguna: ' . $e->getMessage());

    header('Location: ' . $base_url . '/admin/users.php?status=danger&message=' . urlencode('Terjadi kesalahan saat mengambil data pengguna.'));
    exit;
}

$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$form_username = $old['username'] ?? $user['username'];
$form_nama = $old['nama'] ?? $user['nama'];
$form_role = $old['role'] ?? $user['role'];

$form_nip = $old['nip'] ?? ($user['nip'] ?? '');
$form_nis = $old['nis'] ?? ($user['nis'] ?? '');
$form_jenis_kelamin = $old['jenis_kelamin'] ?? ($user['jenis_kelamin'] ?? '');

$form_email = $old['email'] ?? (
    $user['role'] === 'guru'
    ? ($user['guru_email'] ?? '')
    : ($user['siswa_email'] ?? '')
);

$form_no_hp = $old['no_hp'] ?? (
    $user['role'] === 'guru'
    ? ($user['guru_no_hp'] ?? '')
    : ($user['siswa_no_hp'] ?? '')
);

$current_user_id = current_user_id();
$is_self = ((int) $current_user_id === (int) $user['id']);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if (!function_exists('edit_user_role_label')) {
    function edit_user_role_label(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrator',
            'guru' => 'Guru',
            'siswa' => 'Siswa',
            default => ucfirst($role),
        };
    }
}

if (!function_exists('edit_user_role_badge')) {
    function edit_user_role_badge(string $role): string
    {
        return match ($role) {
            'admin' => 'bg-danger-subtle text-danger',
            'guru' => 'bg-primary-subtle text-primary',
            'siswa' => 'bg-success-subtle text-success',
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <div>

        <div class="page-header mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="page-title mb-0">
                        <i class="bi bi-person-gear me-2"></i>
                        Edit Pengguna
                    </h1>

                    <span class="badge <?= htmlspecialchars(edit_user_role_badge($user['role'])) ?>">
                        <?= htmlspecialchars(edit_user_role_label($user['role'])) ?>
                    </span>
                </div>

                <p class="page-subtitle mb-0">
                    Perbarui informasi akun dan profil pengguna.
                </p>
            </div>
        </div>

        <?php if ($flash): ?>
            <?php
            $flash_type = $flash['type'] ?? 'info';
            $flash_message = $flash['message'] ?? '';

            $alert_class = match ($flash_type) {
                'success' => 'alert-success',
                'danger', 'error' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
            ?>

            <div class="alert <?= htmlspecialchars($alert_class) ?> alert-dismissible fade show"
                role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <?= htmlspecialchars((string) $flash_message) ?>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($base_url) ?>/actions/user.php"
            method="POST"
            id="formEditUser"
            data-user-form
            novalidate>

            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="row g-4">

                <div class="col-lg-7">
                    <div class="dashboard-card h-100">

                        <div class="dashboard-card-header">
                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-person-badge me-2"></i>
                                    Informasi Akun
                                </h5>

                                <p class="text-muted small mb-0">
                                    Informasi dasar akun pengguna.
                                </p>
                            </div>
                        </div>

                        <div class="dashboard-card-body">

                            <div class="mb-4">
                                <label for="username" class="form-label">
                                    Username
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>

                                    <input type="text"
                                        class="form-control"
                                        id="username"
                                        name="username"
                                        value="<?= htmlspecialchars((string) $form_username) ?>"
                                        minlength="3"
                                        maxlength="50"
                                        autocomplete="username"
                                        required>
                                </div>

                                <div class="form-text">
                                    Username digunakan untuk masuk ke sistem dan harus unik.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    Password Baru
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>

                                    <input type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        minlength="6"
                                        maxlength="255"
                                        autocomplete="new-password"
                                        placeholder="Password baru">
                                </div>

                                <div class="form-text">
                                    Minimal 6 karakter. Kosongkan untuk mempertahankan password lama.
                                </div>

                                <div class="password-strength mt-2"
                                    id="passwordStrength"
                                    style="display: none;">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar"
                                            id="passwordStrengthBar"
                                            role="progressbar"
                                            style="width: 0%;">
                                        </div>
                                    </div>

                                    <small id="passwordStrengthText"
                                        class="text-muted"></small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="nama" class="form-label">
                                    Nama Lengkap
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-person-vcard"></i>
                                    </span>

                                    <input type="text"
                                        class="form-control"
                                        id="nama"
                                        name="nama"
                                        value="<?= htmlspecialchars((string) $form_nama) ?>"
                                        maxlength="100"
                                        autocomplete="name"
                                        required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Role Pengguna
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="row g-2">

                                    <div class="col-md-4">
                                        <label class="role-option">
                                            <input type="radio"
                                                name="role"
                                                value="admin"
                                                <?= $form_role === 'admin' ? 'checked' : '' ?>
                                                <?= $is_self ? 'disabled' : '' ?>
                                                required>

                                            <span class="role-option-content">
                                                <i class="bi bi-shield-lock"></i>
                                                <strong>Admin</strong>
                                                <small>Kelola sistem</small>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="role-option">
                                            <input type="radio"
                                                name="role"
                                                value="guru"
                                                <?= $form_role === 'guru' ? 'checked' : '' ?>
                                                <?= $is_self ? 'disabled' : '' ?>
                                                required>

                                            <span class="role-option-content">
                                                <i class="bi bi-person-workspace"></i>
                                                <strong>Guru</strong>
                                                <small>Kelola absensi</small>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="role-option">
                                            <input type="radio"
                                                name="role"
                                                value="siswa"
                                                <?= $form_role === 'siswa' ? 'checked' : '' ?>
                                                <?= $is_self ? 'disabled' : '' ?>
                                                required>

                                            <span class="role-option-content">
                                                <i class="bi bi-mortarboard"></i>
                                                <strong>Siswa</strong>
                                                <small>Lihat absensi</small>
                                            </span>
                                        </label>
                                    </div>

                                </div>

                                <?php if ($is_self): ?>
                                    <div class="alert alert-warning mt-3 mb-0 py-2">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Role akun admin yang sedang digunakan tidak dapat diubah.
                                    </div>

                                    <input type="hidden"
                                        name="role"
                                        value="<?= htmlspecialchars($form_role) ?>">
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-lg-5">

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">
                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-card-text me-2"></i>
                                    Informasi Profil
                                </h5>

                                <p class="text-muted small mb-0">
                                    Data tambahan berdasarkan role.
                                </p>
                            </div>
                        </div>

                        <div class="dashboard-card-body">

                            <div class="role-fields"
                                data-role-fields="guru">

                                <div class="mb-4">
                                    <label for="nip" class="form-label">
                                        NIP
                                        <span class="text-danger">*</span>
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person-vcard"></i>
                                        </span>

                                        <input type="text"
                                            class="form-control"
                                            id="nip"
                                            name="nip"
                                            value="<?= htmlspecialchars((string) $form_nip) ?>"
                                            maxlength="50"
                                            placeholder="Masukkan NIP">
                                    </div>
                                </div>

                            </div>

                            <div class="role-fields"
                                data-role-fields="siswa">

                                <div class="mb-4">
                                    <label for="nis" class="form-label">
                                        NIS
                                        <span class="text-danger">*</span>
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-card-heading"></i>
                                        </span>

                                        <input type="text"
                                            class="form-control"
                                            id="nis"
                                            name="nis"
                                            value="<?= htmlspecialchars((string) $form_nis) ?>"
                                            maxlength="50"
                                            placeholder="Masukkan NIS">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">
                                        Jenis Kelamin
                                        <span class="text-danger">*</span>
                                    </label>

                                    <div class="d-flex gap-3">

                                        <div class="form-check">
                                            <input class="form-check-input"
                                                type="radio"
                                                name="jenis_kelamin"
                                                id="jkL"
                                                value="L"
                                                <?= $form_jenis_kelamin === 'L' ? 'checked' : '' ?>>

                                            <label class="form-check-label" for="jkL">
                                                Laki-laki
                                            </label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input"
                                                type="radio"
                                                name="jenis_kelamin"
                                                id="jkP"
                                                value="P"
                                                <?= $form_jenis_kelamin === 'P' ? 'checked' : '' ?>>

                                            <label class="form-check-label" for="jkP">
                                                Perempuan
                                            </label>
                                        </div>

                                    </div>
                                </div>

                            </div>

                            <div class="mb-4">
                                <label for="email" class="form-label">
                                    Email
                                    <span class="text-muted small">(opsional)</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope"></i>
                                    </span>

                                    <input type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        value="<?= htmlspecialchars((string) $form_email) ?>"
                                        maxlength="100"
                                        autocomplete="email"
                                        placeholder="nama@email.com">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="no_hp" class="form-label">
                                    No. HP
                                    <span class="text-muted small">(opsional)</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-telephone"></i>
                                    </span>

                                    <input type="tel"
                                        class="form-control"
                                        id="no_hp"
                                        name="no_hp"
                                        value="<?= htmlspecialchars((string) $form_no_hp) ?>"
                                        maxlength="20"
                                        autocomplete="tel"
                                        inputmode="tel"
                                        placeholder="08xxxxxxxxxx">
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

            </div>

            <div class="dashboard-card mt-4">
                <div class="dashboard-card-body">

                    <div class="row g-3 small">

                        <div class="col-md-6">
                            <div class="profile-meta">
                                <span class="text-muted">
                                    <i class="bi bi-calendar-plus me-1"></i>
                                    Terdaftar
                                </span>

                                <strong>
                                    <?= $user['created_at']
                                        ? htmlspecialchars(date('d-m-Y H:i', strtotime($user['created_at'])))
                                        : '-'
                                    ?>
                                </strong>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="profile-meta">
                                <span class="text-muted">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Terakhir diperbarui
                                </span>

                                <strong>
                                    <?= $user['updated_at']
                                        ? htmlspecialchars(date('d-m-Y H:i', strtotime($user['updated_at'])))
                                        : '-'
                                    ?>
                                </strong>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            <div class="dashboard-card mt-4">
                <div class="dashboard-card-body">

                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">

                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Pastikan data pengguna sudah benar sebelum menyimpan perubahan.
                        </div>

                        <div class="d-flex gap-2">

                            <a href="<?= htmlspecialchars($base_url) ?>/admin/users.php"
                                class="btn btn-light border">
                                <i class="bi bi-x-lg me-1"></i>
                                Batal
                            </a>

                            <button type="submit"
                                class="btn btn-primary"
                                id="btnUpdateUser">

                                <span class="btn-text">
                                    <i class="bi bi-save me-1"></i>
                                    Simpan Perubahan
                                </span>

                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1"
                                        aria-hidden="true"></span>
                                    Menyimpan...
                                </span>

                            </button>

                        </div>

                    </div>

                </div>
            </div>

        </form>

    </div>
</main>

<style>
    .role-option {
        display: block;
        cursor: pointer;
        margin: 0;
    }

    .role-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .role-option-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-height: 105px;
        padding: 14px 8px;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        background: var(--white-color);
        text-align: center;
        transition: var(--transition);
    }

    .role-option-content i {
        font-size: 24px;
        color: var(--muted-color);
    }

    .role-option-content strong {
        font-size: 14px;
        color: var(--dark-color);
    }

    .role-option-content small {
        font-size: 11px;
        color: var(--muted-color);
    }

    .role-option:hover .role-option-content {
        border-color: var(--primary-color);
        background: var(--primary-light);
    }

    .role-option input:checked+.role-option-content {
        border-color: var(--primary-color);
        background: var(--primary-light);
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.08);
    }

    .role-option input:checked+.role-option-content i {
        color: var(--primary-color);
    }

    .role-option input:disabled+.role-option-content {
        cursor: not-allowed;
        opacity: 0.8;
    }

    .role-fields {
        display: none;
    }

    .role-fields.active {
        display: block;
    }

    .profile-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .profile-meta strong {
        color: var(--dark-color);
        text-align: right;
    }

    @media (max-width: 575.98px) {
        .role-option-content {
            min-height: 90px;
            padding: 10px 5px;
        }

        .role-option-content i {
            font-size: 20px;
        }

        .role-option-content strong {
            font-size: 12px;
        }

        .role-option-content small {
            display: none;
        }

        .profile-meta {
            align-items: flex-start;
            flex-direction: column;
            gap: 4px;
        }

        .profile-meta strong {
            text-align: left;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const roleInputs = document.querySelectorAll(
            'input[name="role"]:not([type="hidden"])'
        );

        const guruFields = document.querySelector('[data-role-fields="guru"]');
        const siswaFields = document.querySelector('[data-role-fields="siswa"]');

        const nipInput = document.getElementById('nip');
        const nisInput = document.getElementById('nis');

        const genderInputs = document.querySelectorAll(
            'input[name="jenis_kelamin"]'
        );

        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordStrengthBar = document.getElementById('passwordStrengthBar');
        const passwordStrengthText = document.getElementById('passwordStrengthText');

        const form = document.getElementById('formEditUser');
        const submitButton = document.getElementById('btnUpdateUser');

        function getSelectedRole() {
            const selected = document.querySelector(
                'input[name="role"]:checked:not([type="hidden"])'
            );

            if (selected) {
                return selected.value;
            }

            const hiddenRole = document.querySelector(
                'input[type="hidden"][name="role"]'
            );

            return hiddenRole ? hiddenRole.value : '';
        }

        function updateRoleFields() {
            const role = getSelectedRole();

            guruFields?.classList.remove('active');
            siswaFields?.classList.remove('active');

            nipInput?.removeAttribute('required');
            nisInput?.removeAttribute('required');

            genderInputs.forEach(function(input) {
                input.removeAttribute('required');
            });

            if (role === 'guru') {
                guruFields?.classList.add('active');

                nipInput?.setAttribute('required', 'required');

            } else if (role === 'siswa') {
                siswaFields?.classList.add('active');

                nisInput?.setAttribute('required', 'required');

                genderInputs.forEach(function(input) {
                    input.setAttribute('required', 'required');
                });
            }
        }

        roleInputs.forEach(function(input) {
            input.addEventListener('change', updateRoleFields);
        });

        updateRoleFields();

        function updatePasswordStrength() {
            if (
                !passwordInput ||
                !passwordStrength ||
                !passwordStrengthBar ||
                !passwordStrengthText
            ) {
                return;
            }

            const password = passwordInput.value;

            if (!password) {
                passwordStrength.style.display = 'none';
                return;
            }

            passwordStrength.style.display = 'block';

            let score = 0;

            if (password.length >= 6) score++;
            if (password.length >= 10) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            let percentage = 0;
            let text = 'Sangat lemah';

            if (score <= 2) {
                percentage = 25;
                text = 'Lemah';
            } else if (score <= 3) {
                percentage = 50;
                text = 'Cukup';
            } else if (score <= 4) {
                percentage = 75;
                text = 'Kuat';
            } else {
                percentage = 100;
                text = 'Sangat kuat';
            }

            passwordStrengthBar.style.width = percentage + '%';
            passwordStrengthText.textContent = text;
        }

        passwordInput?.addEventListener('input', updatePasswordStrength);

        form?.addEventListener('submit', function(event) {

            const role = getSelectedRole();

            if (!role) {
                event.preventDefault();
                alert('Role pengguna tidak valid.');
                return;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();

                form.classList.add('was-validated');
                return;
            }

            const password = passwordInput?.value.trim() || '';

            if (password !== '' && password.length < 6) {
                event.preventDefault();

                passwordInput?.focus();

                alert('Password baru harus memiliki minimal 6 karakter.');
                return;
            }

            const roleLabels = {
                admin: 'Administrator',
                guru: 'Guru',
                siswa: 'Siswa'
            };

            const nama = document.getElementById('nama')?.value.trim() || '';
            const roleLabel = roleLabels[role] || role;

            let message =
                `Simpan perubahan pengguna "${nama}" sebagai ${roleLabel}?`;

            if (password !== '') {
                message += '\n\nPassword juga akan diubah.';
            }

            const confirmed = window.confirm(message);

            if (!confirmed) {
                event.preventDefault();
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;

                const btnText = submitButton.querySelector('.btn-text');
                const btnLoading = submitButton.querySelector('.btn-loading');

                btnText?.classList.add('d-none');
                btnLoading?.classList.remove('d-none');
            }
        });

        const phoneInput = document.getElementById('no_hp');

        phoneInput?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>