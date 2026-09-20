<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_login(): void
{
    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['username']) ||
        !isset($_SESSION['role'])
    ) {
        $_SESSION['login_error'] = 'Silakan login terlebih dahulu.';
        header('Location: ../login.php');
        exit;
    }
}

function check_role(string|array $allowed_roles): void
{
    check_login();

    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    if (!in_array($_SESSION['role'], $allowed_roles, true)) {

        redirect_to_dashboard();
    }
}

function redirect_to_dashboard(): void
{
    switch ($_SESSION['role'] ?? '') {

        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;

        case 'guru':
            header('Location: ../guru/dashboard.php');
            break;

        case 'siswa':
            header('Location: ../siswa/dashboard.php');
            break;

        default:
            session_unset();
            session_destroy();

            header('Location: ../login.php');
            break;
    }

    exit;
}

function current_user_id(): int
{
    check_login();

    return (int) $_SESSION['user_id'];
}

function current_user_role(): string
{
    check_login();

    return $_SESSION['role'];
}

function current_user_name(): string
{
    check_login();

    return $_SESSION['nama'] ?? '';
}