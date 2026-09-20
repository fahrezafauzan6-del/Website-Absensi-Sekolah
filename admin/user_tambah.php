<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('admin');

$page_title = 'Tambah Pengguna';

$additional_js = [];
$csrf_token = $_SESSION['csrf_token'] ?? null;

if ($csrf_token === null) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

$gurus = [];
try {
    $stmt = $pdo->query("
        SELECT
            g.user_id,
            g.nip,
            g.nama
        FROM guru g
        ORDER BY g.nama ASC
    ");
    $gurus = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Gagal mengambil data guru: ' . $e->getMessage());
}

$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);
?>

<main class="main-content">
    <div>

        <div class="page-header mb-4">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-person-plus me-2"></i>
                    Tambah Pengguna
                </h1>
                <p class="page-subtitle mb-0">
                    Tambahkan akun pengguna baru ke sistem.
                </p>
            </div>
        </div>

        <?php if (!empty($_SESSION['flash'])): ?>
            <?php
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);

            $flash_type = $flash['type'] ?? 'info';
            $flash_message = $flash['message'] ?? '';

            $alert_class = match ($flash_type) {
                'success' => 'alert-success',
                'danger', 'error' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
            ?>

            <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
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
              id="formTambahUser"
              data-user-form
              novalidate>

            <input type="hidden" name="action" value="tambah">
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
                                    Data login pengguna.
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
                                           value="<?= htmlspecialchars((string) ($old['username'] ?? '')) ?>"
                                           placeholder="Contoh: ahmad123"
                                           minlength="3"
                                           maxlength="50"
                                           autocomplete="username"
                                           required>
                                </div>

                                <div class="form-text">
                                    Minimal 3 karakter dan harus unik.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    Password
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-lock"></i>
                                    </span>

                                    <input type="password"
                                           class="form-control"
                                           id="password"
                                           name="password"
                                           placeholder="Masukkan password"
                                           minlength="6"
                                           maxlength="255"
                                           autocomplete="new-password"
                                           required>
                                </div>

                                <div class="form-text">
                                    Gunakan minimal 6 karakter.
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
                                           value="<?= htmlspecialchars((string) ($old['nama'] ?? '')) ?>"
                                           placeholder="Contoh: Ahmad Fauzan"
                                           maxlength="100"
                                           autocomplete="name"
                                           required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="role" class="form-label">
                                    Role Pengguna
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="row g-2">

                                    <div class="col-md-4">
                                        <label class="role-option">
                                            <input type="radio"
                                                   name="role"
                                                   value="admin"
                                                   <?= (($old['role'] ?? '') === 'admin') ? 'checked' : '' ?>
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
                                                   <?= (($old['role'] ?? '') === 'guru') ? 'checked' : '' ?>
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
                                                   <?= (($old['role'] ?? '') === 'siswa') ? 'checked' : '' ?>
                                                   required>

                                            <span class="role-option-content">
                                                <i class="bi bi-mortarboard"></i>
                                                <strong>Siswa</strong>
                                                <small>Lihat absensi</small>
                                            </span>
                                        </label>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="dashboard-card h-100">
                        <div class="dashboard-card-header">
                            <div>
                                <h5 class="dashboard-card-title mb-1">
                                    <i class="bi bi-card-text me-2"></i>
                                    Informasi Tambahan
                                </h5>
                                <p class="text-muted small mb-0">
                                    Data profil sesuai role pengguna.
                                </p>
                            </div>
                        </div>

                        <div class="dashboard-card-body">

                            <div class="role-fields" data-role-fields="guru">
                                <div class="mb-4">
                                    <label for="nip" class="form-label">
                                        NIP
                                        <span class="text-danger role-required-guru">*</span>
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person-vcard"></i>
                                        </span>

                                        <input type="text"
                                               class="form-control"
                                               id="nip"
                                               name="nip"
                                               value="<?= htmlspecialchars((string) ($old['nip'] ?? '')) ?>"
                                               placeholder="Masukkan NIP"
                                               maxlength="50">
                                    </div>
                                </div>
                            </div>

                            <div class="role-fields" data-role-fields="siswa">
                                <div class="mb-4">
                                    <label for="nis" class="form-label">
                                        NIS
                                        <span class="text-danger role-required-siswa">*</span>
                                    </label>

                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-card-heading"></i>
                                        </span>

                                        <input type="text"
                                               class="form-control"
                                               id="nis"
                                               name="nis"
                                               value="<?= htmlspecialchars((string) ($old['nis'] ?? '')) ?>"
                                               placeholder="Masukkan NIS"
                                               maxlength="50">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">
                                        Jenis Kelamin
                                        <span class="text-danger role-required-siswa">*</span>
                                    </label>

                                    <div class="d-flex gap-3">

                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="jenis_kelamin"
                                                   id="jkL"
                                                   value="L"
                                                   <?= (($old['jenis_kelamin'] ?? '') === 'L') ? 'checked' : '' ?>>

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
                                                   <?= (($old['jenis_kelamin'] ?? '') === 'P') ? 'checked' : '' ?>>

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
                                           value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>"
                                           placeholder="nama@email.com"
                                           maxlength="100"
                                           autocomplete="email">
                                </div>
                            </div>

                            <div class="mb-4">
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
                                           value="<?= htmlspecialchars((string) ($old['no_hp'] ?? '')) ?>"
                                           placeholder="08xxxxxxxxxx"
                                           maxlength="20"
                                           autocomplete="tel"
                                           inputmode="tel">
                                </div>
                            </div>

                            <div class="alert alert-primary mb-0" id="roleInfo">
                                <div class="d-flex">
                                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                                    <div>
                                        <strong>Pilih role pengguna</strong>
                                        <div class="small mt-1">
                                            Field tambahan akan menyesuaikan role yang dipilih.
                                        </div>
                                    </div>
                                </div>
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
                            Field bertanda <span class="text-danger">*</span> wajib diisi.
                        </div>

                        <div class="d-flex gap-2">
                            <a href="<?= htmlspecialchars($base_url) ?>/admin/users.php"
                               class="btn btn-light border">
                                Batal
                            </a>

                            <button type="submit"
                                    class="btn btn-primary"
                                    id="btnSimpanUser">
                                <span class="btn-text">
                                    <i class="bi bi-save me-1"></i>
                                    Simpan Pengguna
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

    .role-option input:checked + .role-option-content {
        border-color: var(--primary-color);
        background: var(--primary-light);
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.08);
    }

    .role-option input:checked + .role-option-content i {
        color: var(--primary-color);
    }

    .role-fields {
        display: none;
    }

    .role-fields.active {
        display: block;
    }

    .password-strength .progress {
        background: var(--border-color);
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
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleInputs = document.querySelectorAll('input[name="role"]');
    const guruFields = document.querySelector('[data-role-fields="guru"]');
    const siswaFields = document.querySelector('[data-role-fields="siswa"]');

    const nipInput = document.getElementById('nip');
    const nisInput = document.getElementById('nis');

    const genderInputs = document.querySelectorAll('input[name="jenis_kelamin"]');
    const roleInfo = document.getElementById('roleInfo');

    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar');
    const passwordStrengthText = document.getElementById('passwordStrengthText');

    function getSelectedRole() {
        const selected = document.querySelector('input[name="role"]:checked');
        return selected ? selected.value : '';
    }

    function updateRoleFields() {
        const role = getSelectedRole();

        guruFields?.classList.remove('active');
        siswaFields?.classList.remove('active');

        nipInput?.removeAttribute('required');
        nisInput?.removeAttribute('required');

        genderInputs.forEach(input => {
            input.removeAttribute('required');
        });

        if (role === 'guru') {
            guruFields?.classList.add('active');

            if (nipInput) {
                nipInput.setAttribute('required', 'required');
            }

            if (roleInfo) {
                roleInfo.className = 'alert alert-success mb-0';
                roleInfo.innerHTML = `
                    <div class="d-flex">
                        <i class="bi bi-person-workspace me-2 mt-1"></i>
                        <div>
                            <strong>Akun Guru</strong>
                            <div class="small mt-1">
                                Guru dapat mengelola kelas dan melakukan absensi siswa.
                            </div>
                        </div>
                    </div>
                `;
            }
        } else if (role === 'siswa') {
            siswaFields?.classList.add('active');

            if (nisInput) {
                nisInput.setAttribute('required', 'required');
            }

            genderInputs.forEach(input => {
                input.setAttribute('required', 'required');
            });

            if (roleInfo) {
                roleInfo.className = 'alert alert-info mb-0';
                roleInfo.innerHTML = `
                    <div class="d-flex">
                        <i class="bi bi-mortarboard me-2 mt-1"></i>
                        <div>
                            <strong>Akun Siswa</strong>
                            <div class="small mt-1">
                                Siswa dapat melihat kelas, absensi, dan riwayat kehadiran.
                            </div>
                        </div>
                    </div>
                `;
            }
        } else if (role === 'admin') {
            if (roleInfo) {
                roleInfo.className = 'alert alert-warning mb-0';
                roleInfo.innerHTML = `
                    <div class="d-flex">
                        <i class="bi bi-shield-lock me-2 mt-1"></i>
                        <div>
                            <strong>Akun Administrator</strong>
                            <div class="small mt-1">
                                Administrator memiliki akses untuk mengelola seluruh sistem.
                            </div>
                        </div>
                    </div>
                `;
            }
        }
    }

    roleInputs.forEach(input => {
        input.addEventListener('change', updateRoleFields);
    });

    updateRoleFields();

    function updatePasswordStrength() {
        if (!passwordInput || !passwordStrength || !passwordStrengthBar || !passwordStrengthText) {
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

    const form = document.getElementById('formTambahUser');
    const submitButton = document.getElementById('btnSimpanUser');

    form?.addEventListener('submit', function (event) {
        const role = getSelectedRole();

        if (!role) {
            event.preventDefault();
            alert('Silakan pilih role pengguna.');
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
            return;
        }

        const roleLabels = {
            admin: 'Administrator',
            guru: 'Guru',
            siswa: 'Siswa'
        };

        const nama = document.getElementById('nama')?.value.trim() || '';

        const confirmed = window.confirm(
            `Tambahkan pengguna "${nama}" sebagai ${roleLabels[role]}?`
        );

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

    phoneInput?.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>