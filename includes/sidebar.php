<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['nama'] ?? 'Pengguna';

$base_url = $base_url ?? '/absensi-sekolah';

$current_page = basename($_SERVER['PHP_SELF']);

function sidebar_active(string|array $pages): string
{
    global $current_page;

    if (is_string($pages)) {
        $pages = [$pages];
    }

    return in_array($current_page, $pages, true)
        ? 'active'
        : '';
}

?>

<aside
    id="sidebar"
    class="sidebar offcanvas-lg offcanvas-start"
    tabindex="-1"
    aria-labelledby="sidebarLabel"
>
    <div class="sidebar-header">

        <div class="sidebar-logo">
            <i class="bi bi-calendar-check-fill"></i>
        </div>

        <div class="sidebar-brand">

            <div
                id="sidebarLabel"
                class="fw-bold"
            >
                Absensi Sekolah
            </div>

            <small>
                Sistem Informasi Sekolah
            </small>

        </div>

        <button
            type="button"
            class="btn-close d-lg-none ms-auto"
            data-bs-dismiss="offcanvas"
            aria-label="Tutup menu"
        ></button>

    </div>

    <div class="sidebar-user">

        <div class="sidebar-user-avatar">
            <?php

            $name_parts = preg_split(
                '/\s+/',
                trim($user_name)
            );

            if (count($name_parts) >= 2) {

                $initials =
                    mb_substr($name_parts[0], 0, 1) .
                    mb_substr($name_parts[1], 0, 1);

            } else {

                $initials =
                    mb_substr($user_name, 0, 2);
            }

            echo htmlspecialchars(
                strtoupper($initials)
            );

            ?>
        </div>

        <div class="sidebar-user-info">

            <div class="sidebar-user-name">
                <?= htmlspecialchars($user_name) ?>
            </div>

            <div class="sidebar-user-role">

                <?php

                echo match ($user_role) {
                    'admin' => 'Administrator',
                    'guru'  => 'Guru',
                    'siswa' => 'Siswa',
                    default => 'Pengguna'
                };

                ?>

            </div>

        </div>

    </div>

    <nav class="sidebar-nav">

        <?php if ($user_role === 'admin'): ?>

            <div class="sidebar-section-title">
                MENU UTAMA
            </div>

            <a
                href="<?= $base_url ?>/admin/dashboard.php"
                class="sidebar-link <?= sidebar_active('dashboard.php') ?>"
            >

                <i class="bi bi-speedometer2"></i>

                <span>
                    Dashboard
                </span>

            </a>

            <a
                href="<?= $base_url ?>/admin/users.php"
                class="sidebar-link <?= sidebar_active([
                    'users.php',
                    'user_tambah.php',
                    'user_edit.php'
                ]) ?>"
            >

                <i class="bi bi-people-fill"></i>

                <span>
                    Pengguna
                </span>

            </a>

            <a
                href="<?= $base_url ?>/admin/kelas.php"
                class="sidebar-link <?= sidebar_active([
                    'kelas.php',
                    'kelas_tambah.php',
                    'kelas_edit.php'
                ]) ?>"
            >

                <i class="bi bi-building"></i>

                <span>
                    Kelas
                </span>

            </a>


            <div class="sidebar-section-title mt-3">
                SISTEM
            </div>

            <a
                href="<?= $base_url ?>/logout.php"
                class="sidebar-link sidebar-link-danger"
                onclick="return confirm('Apakah Anda yakin ingin keluar?');"
            >

                <i class="bi bi-box-arrow-right"></i>

                <span>
                    Keluar
                </span>

            </a>

        <?php elseif ($user_role === 'guru'): ?>

            <div class="sidebar-section-title">
                MENU UTAMA
            </div>

            <a
                href="<?= $base_url ?>/guru/dashboard.php"
                class="sidebar-link <?= sidebar_active('dashboard.php') ?>"
            >

                <i class="bi bi-speedometer2"></i>

                <span>
                    Dashboard
                </span>

            </a>

            <a
                href="<?= $base_url ?>/guru/kelas.php"
                class="sidebar-link <?= sidebar_active([
                    'kelas.php',
                    'detail_kelas.php'
                ]) ?>"
            >

                <i class="bi bi-building"></i>

                <span>
                    Kelas Saya
                </span>

            </a>

            <a
                href="<?= $base_url ?>/guru/laporan.php"
                class="sidebar-link <?= sidebar_active('laporan.php') ?>"
            >

                <i class="bi bi-file-earmark-bar-graph"></i>

                <span>
                    Laporan
                </span>

            </a>


            <div class="sidebar-section-title mt-3">
                SISTEM
            </div>

            <a
                href="<?= $base_url ?>/logout.php"
                class="sidebar-link sidebar-link-danger"
                onclick="return confirm('Apakah Anda yakin ingin keluar?');"
            >

                <i class="bi bi-box-arrow-right"></i>

                <span>
                    Keluar
                </span>

            </a>

        <?php elseif ($user_role === 'siswa'): ?>

            <div class="sidebar-section-title">
                MENU UTAMA
            </div>

            <a
                href="<?= $base_url ?>/siswa/dashboard.php"
                class="sidebar-link <?= sidebar_active('dashboard.php') ?>"
            >

                <i class="bi bi-speedometer2"></i>

                <span>
                    Dashboard
                </span>

            </a>

            <a
                href="<?= $base_url ?>/siswa/kelas.php"
                class="sidebar-link <?= sidebar_active('kelas.php') ?>"
            >

                <i class="bi bi-building"></i>

                <span>
                    Kelas Saya
                </span>

            </a>

            <a
                href="<?= $base_url ?>/siswa/riwayat.php"
                class="sidebar-link <?= sidebar_active('riwayat.php') ?>"
            >

                <i class="bi bi-clock-history"></i>

                <span>
                    Riwayat Absensi
                </span>

            </a>


            <div class="sidebar-section-title mt-3">
                SISTEM
            </div>

            <a
                href="<?= $base_url ?>/logout.php"
                class="sidebar-link sidebar-link-danger"
                onclick="return confirm('Apakah Anda yakin ingin keluar?');"
            >

                <i class="bi bi-box-arrow-right"></i>

                <span>
                    Keluar
                </span>

            </a>

        <?php else: ?>

            <div class="sidebar-section-title">
                AKSES
            </div>

            <a
                href="<?= $base_url ?>/login.php"
                class="sidebar-link"
            >

                <i class="bi bi-box-arrow-in-right"></i>

                <span>
                    Login
                </span>

            </a>

        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">

        <small>
            &copy; <?= date('Y') ?> Absensi Sekolah
        </small>

    </div>

</aside>