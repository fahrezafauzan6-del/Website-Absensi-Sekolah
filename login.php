<?php
session_start();

require_once __DIR__ . '/auth/check_auth.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect_to_dashboard();
}

$page_title = 'Login - Absensi Sekolah';

$additional_css = [
    'login.css'
];

$additional_js = [
    'absensi.js'
];

require_once __DIR__ . '/includes/header.php';

$error_message = $_SESSION['login_error'] ?? '';
$success_message = $_SESSION['login_success'] ?? '';

unset($_SESSION['login_error'], $_SESSION['login_success']);
?>

<div class="login-page">

    <div class="login-container">

        <div class="login-wrapper">

            <div class="login-card">

                <div class="login-card-inner">

                    <section class="login-info">

                        <div class="login-info-content">

                            <a href="<?= htmlspecialchars($base_url) ?>/index.php"
                               class="login-brand"
                               aria-label="Kembali ke halaman utama">

                                <span class="login-brand-icon">
                                    <i class="bi bi-calendar-check"></i>
                                </span>

                                <span class="login-brand-text">
                                    Absensi Sekolah
                                </span>

                            </a>


                            <h1>
                                Selamat Datang Kembali
                            </h1>

                            <p class="login-info-description">
                                Masuk ke sistem untuk mengelola dan memantau
                                kehadiran siswa dengan mudah dan terstruktur.
                            </p>


                            <ul class="login-benefits">

                                <li class="login-benefit">
                                    <span class="login-benefit-icon">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                    <span>Data absensi terstruktur</span>
                                </li>

                                <li class="login-benefit">
                                    <span class="login-benefit-icon">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                    <span>Dashboard sesuai peran</span>
                                </li>

                                <li class="login-benefit">
                                    <span class="login-benefit-icon">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                    <span>Riwayat kehadiran</span>
                                </li>

                                <li class="login-benefit">
                                    <span class="login-benefit-icon">
                                        <i class="bi bi-check-lg"></i>
                                    </span>
                                    <span>Laporan absensi</span>
                                </li>

                            </ul>

                        </div>


                        <div class="login-info-grid"
                             aria-hidden="true">

                            <?php for ($i = 0; $i < 18; $i++): ?>
                                <span></span>
                            <?php endfor; ?>

                        </div>

                    </section>

                    <section class="login-form-panel">

                        <a href="<?= htmlspecialchars($base_url) ?>/index.php"
                           class="login-back">
                            <i class="bi bi-arrow-left"></i>
                            Kembali ke halaman utama
                        </a>


                        <div class="login-form-header">

                            <h2>
                                Login
                            </h2>

                            <p>
                                Masukkan username dan password Anda
                                untuk melanjutkan.
                            </p>

                        </div>

                        <?php if ($error_message): ?>

                            <div class="login-alert alert alert-danger"
                                 role="alert">

                                <i class="bi bi-exclamation-circle-fill"></i>

                                <div>
                                    <?= htmlspecialchars($error_message) ?>
                                </div>

                            </div>

                        <?php endif; ?>

                        <?php if ($success_message): ?>

                            <div class="login-alert alert alert-success"
                                 role="alert">

                                <i class="bi bi-check-circle-fill"></i>

                                <div>
                                    <?= htmlspecialchars($success_message) ?>
                                </div>

                            </div>

                        <?php endif; ?>


                        <form action="<?= htmlspecialchars($base_url) ?>/auth/login_process.php"
                              method="POST"
                              class="login-form"
                              id="loginForm"
                              novalidate>

                            <div class="mb-3">

                                <label for="username"
                                       class="form-label">
                                    Username
                                </label>

                                <div class="input-group login-input-group">

                                    <span class="input-group-text">
                                        <i class="bi bi-person"></i>
                                    </span>

                                    <input type="text"
                                           class="form-control"
                                           id="username"
                                           name="username"
                                           placeholder="Masukkan username"
                                           autocomplete="username"
                                           maxlength="50"
                                           required
                                           autofocus>

                                </div>

                                <div class="invalid-feedback">
                                    Username wajib diisi.
                                </div>

                            </div>

                            <div class="mb-3">

                                <label for="password"
                                       class="form-label">
                                    Password
                                </label>

                                <div class="login-password-wrapper">

                                    <input type="password"
                                           class="form-control"
                                           id="password"
                                           name="password"
                                           placeholder="Masukkan password"
                                           autocomplete="current-password"
                                           maxlength="255"
                                           required>

                                    <button type="button"
                                            class="login-password-toggle"
                                            id="loginPasswordToggle"
                                            aria-label="Tampilkan password"
                                            title="Tampilkan password">

                                        <i class="bi bi-eye"
                                           id="loginPasswordIcon"></i>

                                    </button>

                                </div>

                                <div class="invalid-feedback">
                                    Password wajib diisi.
                                </div>

                                <div class="login-caps-warning"
                                     id="loginCapsWarning">

                                    <i class="bi bi-capslock-fill"></i>

                                    <span>
                                        Caps Lock sedang aktif.
                                    </span>

                                </div>

                            </div>

                            <div class="login-form-options">

                                <div class="form-check">

                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="remember"
                                           name="remember"
                                           value="1">

                                    <label class="form-check-label"
                                           for="remember">
                                        Ingat saya
                                    </label>

                                </div>

                                <a href="#"
                                   class="login-forgot-link"
                                   id="forgotPasswordLink">
                                    Lupa password?
                                </a>

                            </div>

                            <button type="submit"
                                    class="btn btn-primary login-submit"
                                    id="loginSubmit">

                                <span class="login-submit-text">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>
                                    Masuk
                                </span>

                                <span class="login-submit-loading d-none">
                                    <span class="spinner-border"
                                          role="status"
                                          aria-hidden="true"></span>
                                    Memproses...
                                </span>

                            </button>

                        </form>

                        <div class="login-demo">

                            <div class="login-demo-title">
                                <i class="bi bi-info-circle-fill"></i>
                                Informasi Login
                            </div>

                            <p>
                                Gunakan akun yang telah dibuat oleh
                                administrator untuk mengakses sistem.
                            </p>

                        </div>


                        <div class="login-footer">

                            <p>
                                &copy; <?= date('Y') ?>
                                Absensi Sekolah.
                                Semua hak dilindungi.
                            </p>

                        </div>

                    </section>

                </div>

            </div>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('loginForm');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const submitButton = document.getElementById('loginSubmit');

    const passwordToggle =
        document.getElementById('loginPasswordToggle');

    const passwordIcon =
        document.getElementById('loginPasswordIcon');

    const capsWarning =
        document.getElementById('loginCapsWarning');

    const forgotPasswordLink =
        document.getElementById('forgotPasswordLink');

    if (passwordToggle && password && passwordIcon) {

        passwordToggle.addEventListener('click', function () {

            const isPassword =
                password.type === 'password';

            password.type =
                isPassword ? 'text' : 'password';

            passwordIcon.classList.toggle(
                'bi-eye',
                !isPassword
            );

            passwordIcon.classList.toggle(
                'bi-eye-slash',
                isPassword
            );

            passwordToggle.setAttribute(
                'aria-label',
                isPassword
                    ? 'Sembunyikan password'
                    : 'Tampilkan password'
            );

            passwordToggle.setAttribute(
                'title',
                isPassword
                    ? 'Sembunyikan password'
                    : 'Tampilkan password'
            );

        });

    }

    if (password && capsWarning) {

        function checkCapsLock(event) {

            if (typeof event.getModifierState !== 'function') {
                return;
            }

            capsWarning.classList.toggle(
                'show',
                event.getModifierState('CapsLock')
            );

        }

        password.addEventListener('keydown', checkCapsLock);
        password.addEventListener('keyup', checkCapsLock);
        password.addEventListener('focus', checkCapsLock);

        password.addEventListener('blur', function () {
            capsWarning.classList.remove('show');
        });

    }

    if (form) {

        form.addEventListener('submit', function (event) {

            let isValid = true;

            if (!username.value.trim()) {

                username.classList.add('is-invalid');
                isValid = false;

            } else {

                username.classList.remove('is-invalid');

            }

            if (!password.value) {

                password.classList.add('is-invalid');
                isValid = false;

            } else {

                password.classList.remove('is-invalid');

            }


            if (!isValid) {

                event.preventDefault();

                const firstInvalid =
                    form.querySelector('.is-invalid');

                if (firstInvalid) {
                    firstInvalid.focus();
                }

                return;
            }

            if (submitButton) {

                submitButton.disabled = true;

                const submitText =
                    submitButton.querySelector(
                        '.login-submit-text'
                    );

                const submitLoading =
                    submitButton.querySelector(
                        '.login-submit-loading'
                    );

                if (submitText) {
                    submitText.classList.add('d-none');
                }

                if (submitLoading) {
                    submitLoading.classList.remove('d-none');
                }

            }

        });

        if (username) {

            username.addEventListener('input', function () {

                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }

            });

        }


        if (password) {

            password.addEventListener('input', function () {

                if (this.value) {
                    this.classList.remove('is-invalid');
                }

            });

        }

    }

    if (forgotPasswordLink) {

        forgotPasswordLink.addEventListener('click', function (event) {

            event.preventDefault();

            const existingAlert =
                document.getElementById('forgotPasswordAlert');

            if (existingAlert) {
                existingAlert.remove();
            }

            const alert =
                document.createElement('div');

            alert.id = 'forgotPasswordAlert';

            alert.className =
                'login-alert alert alert-info';

            alert.setAttribute('role', 'alert');

            alert.innerHTML = `
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Silakan hubungi administrator sekolah
                    untuk mendapatkan atau mengatur ulang
                    password Anda.
                </div>
            `;

            const loginForm =
                document.getElementById('loginForm');

            if (loginForm) {
                loginForm.parentNode.insertBefore(
                    alert,
                    loginForm
                );
            }

        });

    }

});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>