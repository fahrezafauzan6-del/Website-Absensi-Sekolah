<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Username dan password wajib diisi.';
    header('Location: ../login.php');
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            password,
            nama,
            role
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);

    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['login_error'] = 'Username atau password salah.';
        header('Location: ../login.php');
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        $_SESSION['login_error'] = 'Username atau password salah.';
        header('Location: ../login.php');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama']     = $user['nama'];
    $_SESSION['role']     = $user['role'];

    unset($_SESSION['login_error']);

    switch ($user['role']) {

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

            session_start();
            $_SESSION['login_error'] = 'Role pengguna tidak valid.';
            header('Location: ../login.php');
            break;
    }

    exit;

} catch (PDOException $e) {

    error_log('Login error: ' . $e->getMessage());

    $_SESSION['login_error'] = 'Terjadi kesalahan saat login. Silakan coba lagi.';
    header('Location: ../login.php');
    exit;
}