<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_name = $_SESSION['nama'] ?? 'Pengguna';
$user_role = $_SESSION['role'] ?? '';

$role_label = match ($user_role) {
    'admin' => 'Administrator',
    'guru'  => 'Guru',
    'siswa' => 'Siswa',
    default => 'Pengguna'
};

$name_parts = preg_split('/\s+/', trim($user_name));

if (count($name_parts) >= 2) {
    $user_initials =
        mb_substr($name_parts[0], 0, 1) .
        mb_substr($name_parts[1], 0, 1);
} else {
    $user_initials = mb_substr($user_name, 0, 2);
}

$user_initials = strtoupper($user_initials);

?>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">

    <div class="container-fluid">

        <button
            class="btn btn-light d-lg-none me-2"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#sidebar"
            aria-controls="sidebar"
            aria-label="Buka menu"
        >
            <i class="bi bi-list fs-4"></i>
        </button>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="mainNavbar"
        >
            <div class="me-auto"></div>

            <div class="dropdown">

                <button
                    class="btn btn-link text-decoration-none text-dark d-flex align-items-center gap-2"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <span class="navbar-user-avatar">
                        <?= htmlspecialchars($user_initials) ?>
                    </span>

                    <span class="d-none d-md-block text-start">

                        <span class="d-block fw-semibold">
                            <?= htmlspecialchars($user_name) ?>
                        </span>

                        <small class="text-muted">
                            <?= htmlspecialchars($role_label) ?>
                        </small>

                    </span>


                    <i class="bi bi-chevron-down small"></i>

                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                    <li>

                        <div class="dropdown-header">

                            <div class="fw-semibold">
                                <?= htmlspecialchars($user_name) ?>
                            </div>

                            <small class="text-muted">
                                <?= htmlspecialchars($role_label) ?>
                            </small>

                        </div>

                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>

                        <?php

                        $dashboard_url = match ($user_role) {
                            'admin' => $base_url . '/admin/dashboard.php',
                            'guru'  => $base_url . '/guru/dashboard.php',
                            'siswa' => $base_url . '/siswa/dashboard.php',
                            default => $base_url . '/login.php'
                        };

                        ?>

                        <a
                            class="dropdown-item"
                            href="<?= $dashboard_url ?>"
                        >
                            <i class="bi bi-speedometer2 me-2"></i>
                            Dashboard
                        </a>

                    </li>

                    <li>

                        <a
                            class="dropdown-item text-danger"
                            href="<?= $base_url ?? '/absensi-sekolah' ?>/logout.php"
                            onclick="return confirm('Apakah Anda yakin ingin keluar?');"
                        >
                            <i class="bi bi-box-arrow-right me-2"></i>
                            Keluar
                        </a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>
