<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('admin');

function redirect_users(string $type = '', string $message = ''): never
{
    $url = '/absensi-sekolah/admin/users.php';

    if ($type !== '' && $message !== '') {
        $url .= '?' . http_build_query([
            'status' => $type,
            'message' => $message,
        ]);
    }

    header('Location: ' . $url);
    exit;
}

function post_string(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function valid_role(string $role): bool
{
    return in_array($role, ['admin', 'guru', 'siswa'], true);
}

function valid_gender(string $gender): bool
{
    return in_array($gender, ['L', 'P'], true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_users('error', 'Metode request tidak valid.');
}

$action = post_string('action');

if ($action === '') {
    redirect_users('error', 'Aksi pengguna tidak ditemukan.');
}

if (isset($_SESSION['csrf_token'])) {
    $csrf_token = post_string('csrf_token');

    if (
        $csrf_token === '' ||
        !hash_equals((string) $_SESSION['csrf_token'], $csrf_token)
    ) {
        redirect_users('error', 'Token keamanan tidak valid.');
    }
}

if ($action === 'tambah' || $action === 'create') {

    $username       = post_string('username');
    $password       = (string) ($_POST['password'] ?? '');
    $nama           = post_string('nama');
    $role           = post_string('role');

    $nip            = post_string('nip');
    $nis            = post_string('nis');
    $jenis_kelamin  = post_string('jenis_kelamin');
    $email          = post_string('email');
    $no_hp          = post_string('no_hp');

    if ($username === '' || $password === '' || $nama === '' || $role === '') {
        redirect_users('error', 'Username, password, nama, dan role wajib diisi.');
    }

    if (!valid_role($role)) {
        redirect_users('error', 'Role pengguna tidak valid.');
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        redirect_users('error', 'Username harus terdiri dari 3-50 karakter.');
    }

    if (strlen($password) < 6) {
        redirect_users('error', 'Password minimal 6 karakter.');
    }

    if (strlen($nama) > 100) {
        redirect_users('error', 'Nama maksimal 100 karakter.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_users('error', 'Format email tidak valid.');
    }

    if ($role === 'guru' && $nip === '') {
        redirect_users('error', 'NIP wajib diisi untuk pengguna dengan role guru.');
    }

    if ($role === 'siswa') {
        if ($nis === '') {
            redirect_users('error', 'NIS wajib diisi untuk pengguna dengan role siswa.');
        }

        if (!valid_gender($jenis_kelamin)) {
            redirect_users('error', 'Jenis kelamin siswa tidak valid.');
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE username = :username LIMIT 1'
    );

    $stmt->execute([
        'username' => $username,
    ]);

    if ($stmt->fetch()) {
        redirect_users('error', 'Username sudah digunakan.');
    }

    if ($role === 'guru') {
        $stmt = $pdo->prepare(
            'SELECT user_id FROM guru WHERE nip = :nip LIMIT 1'
        );

        $stmt->execute([
            'nip' => $nip,
        ]);

        if ($stmt->fetch()) {
            redirect_users('error', 'NIP sudah digunakan.');
        }
    }

    if ($role === 'siswa') {
        $stmt = $pdo->prepare(
            'SELECT user_id FROM siswa WHERE nis = :nis LIMIT 1'
        );

        $stmt->execute([
            'nis' => $nis,
        ]);

        if ($stmt->fetch()) {
            redirect_users('error', 'NIS sudah digunakan.');
        }
    }

    try {
        $pdo->beginTransaction();

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password, nama, role)
             VALUES (:username, :password, :nama, :role)'
        );

        $stmt->execute([
            'username' => $username,
            'password' => $password_hash,
            'nama'     => $nama,
            'role'     => $role,
        ]);

        $user_id = (int) $pdo->lastInsertId();

        if ($role === 'guru') {
            $stmt = $pdo->prepare(
                'INSERT INTO guru (user_id, nip, nama, email, no_hp)
                 VALUES (:user_id, :nip, :nama, :email, :no_hp)'
            );

            $stmt->execute([
                'user_id' => $user_id,
                'nip'     => $nip,
                'nama'    => $nama,
                'email'   => $email !== '' ? $email : null,
                'no_hp'   => $no_hp !== '' ? $no_hp : null,
            ]);
        }

        if ($role === 'siswa') {
            $stmt = $pdo->prepare(
                'INSERT INTO siswa
                    (user_id, nis, nama, jenis_kelamin, email, no_hp)
                 VALUES
                    (:user_id, :nis, :nama, :jenis_kelamin, :email, :no_hp)'
            );

            $stmt->execute([
                'user_id'       => $user_id,
                'nis'           => $nis,
                'nama'          => $nama,
                'jenis_kelamin' => $jenis_kelamin,
                'email'         => $email !== '' ? $email : null,
                'no_hp'         => $no_hp !== '' ? $no_hp : null,
            ]);
        }

        $pdo->commit();

        redirect_users('success', 'Pengguna berhasil ditambahkan.');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Tambah pengguna error: ' . $e->getMessage());

        redirect_users(
            'error',
            'Pengguna gagal ditambahkan. Silakan coba lagi.'
        );
    }
}

if ($action === 'edit' || $action === 'update') {

    $user_id        = (int) ($_POST['user_id'] ?? 0);

    $username       = post_string('username');
    $password       = (string) ($_POST['password'] ?? '');
    $nama           = post_string('nama');
    $role           = post_string('role');

    $nip            = post_string('nip');
    $nis            = post_string('nis');
    $jenis_kelamin  = post_string('jenis_kelamin');
    $email          = post_string('email');
    $no_hp          = post_string('no_hp');

    if ($user_id <= 0) {
        redirect_users('error', 'ID pengguna tidak valid.');
    }

    if ($username === '' || $nama === '' || $role === '') {
        redirect_users('error', 'Username, nama, dan role wajib diisi.');
    }

    if (!valid_role($role)) {
        redirect_users('error', 'Role pengguna tidak valid.');
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        redirect_users('error', 'Username harus terdiri dari 3-50 karakter.');
    }

    if ($password !== '' && strlen($password) < 6) {
        redirect_users('error', 'Password baru minimal 6 karakter.');
    }

    if (strlen($nama) > 100) {
        redirect_users('error', 'Nama maksimal 100 karakter.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect_users('error', 'Format email tidak valid.');
    }

    if ($role === 'guru' && $nip === '') {
        redirect_users('error', 'NIP wajib diisi untuk pengguna dengan role guru.');
    }

    if ($role === 'siswa') {
        if ($nis === '') {
            redirect_users('error', 'NIS wajib diisi untuk pengguna dengan role siswa.');
        }

        if (!valid_gender($jenis_kelamin)) {
            redirect_users('error', 'Jenis kelamin siswa tidak valid.');
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, role
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $user_id,
    ]);

    $existing_user = $stmt->fetch();

    if (!$existing_user) {
        redirect_users('error', 'Pengguna tidak ditemukan.');
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE username = :username
           AND id != :id
         LIMIT 1'
    );

    $stmt->execute([
        'username' => $username,
        'id'       => $user_id,
    ]);

    if ($stmt->fetch()) {
        redirect_users('error', 'Username sudah digunakan oleh pengguna lain.');
    }

    if ($role === 'guru') {
        $stmt = $pdo->prepare(
            'SELECT user_id
             FROM guru
             WHERE nip = :nip
               AND user_id != :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'nip'     => $nip,
            'user_id' => $user_id,
        ]);

        if ($stmt->fetch()) {
            redirect_users('error', 'NIP sudah digunakan oleh pengguna lain.');
        }
    }

    if ($role === 'siswa') {
        $stmt = $pdo->prepare(
            'SELECT user_id
             FROM siswa
             WHERE nis = :nis
               AND user_id != :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'nis'     => $nis,
            'user_id' => $user_id,
        ]);

        if ($stmt->fetch()) {
            redirect_users('error', 'NIS sudah digunakan oleh pengguna lain.');
        }
    }

    try {
        $pdo->beginTransaction();

        if ($password !== '') {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET username = :username,
                     password = :password,
                     nama = :nama,
                     role = :role
                 WHERE id = :id'
            );

            $stmt->execute([
                'username' => $username,
                'password' => $password_hash,
                'nama'     => $nama,
                'role'     => $role,
                'id'       => $user_id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET username = :username,
                     nama = :nama,
                     role = :role
                 WHERE id = :id'
            );

            $stmt->execute([
                'username' => $username,
                'nama'     => $nama,
                'role'     => $role,
                'id'       => $user_id,
            ]);
        }

        if ($existing_user['role'] !== $role) {

            if ($existing_user['role'] === 'guru') {
                $stmt = $pdo->prepare(
                    'DELETE FROM guru WHERE user_id = :user_id'
                );

                $stmt->execute([
                    'user_id' => $user_id,
                ]);
            }

            if ($existing_user['role'] === 'siswa') {
                $stmt = $pdo->prepare(
                    'DELETE FROM siswa WHERE user_id = :user_id'
                );

                $stmt->execute([
                    'user_id' => $user_id,
                ]);
            }
        }

        if ($role === 'guru') {

            $stmt = $pdo->prepare(
                'SELECT user_id
                 FROM guru
                 WHERE user_id = :user_id
                 LIMIT 1'
            );

            $stmt->execute([
                'user_id' => $user_id,
            ]);

            if ($stmt->fetch()) {

                $stmt = $pdo->prepare(
                    'UPDATE guru
                     SET nip = :nip,
                         nama = :nama,
                         email = :email,
                         no_hp = :no_hp
                     WHERE user_id = :user_id'
                );

                $stmt->execute([
                    'nip'     => $nip,
                    'nama'    => $nama,
                    'email'   => $email !== '' ? $email : null,
                    'no_hp'   => $no_hp !== '' ? $no_hp : null,
                    'user_id' => $user_id,
                ]);

            } else {

                $stmt = $pdo->prepare(
                    'INSERT INTO guru
                        (user_id, nip, nama, email, no_hp)
                     VALUES
                        (:user_id, :nip, :nama, :email, :no_hp)'
                );

                $stmt->execute([
                    'user_id' => $user_id,
                    'nip'     => $nip,
                    'nama'    => $nama,
                    'email'   => $email !== '' ? $email : null,
                    'no_hp'   => $no_hp !== '' ? $no_hp : null,
                ]);
            }
        }

        if ($role === 'siswa') {

            $stmt = $pdo->prepare(
                'SELECT user_id
                 FROM siswa
                 WHERE user_id = :user_id
                 LIMIT 1'
            );

            $stmt->execute([
                'user_id' => $user_id,
            ]);

            if ($stmt->fetch()) {

                $stmt = $pdo->prepare(
                    'UPDATE siswa
                     SET nis = :nis,
                         nama = :nama,
                         jenis_kelamin = :jenis_kelamin,
                         email = :email,
                         no_hp = :no_hp
                     WHERE user_id = :user_id'
                );

                $stmt->execute([
                    'nis'           => $nis,
                    'nama'          => $nama,
                    'jenis_kelamin' => $jenis_kelamin,
                    'email'         => $email !== '' ? $email : null,
                    'no_hp'         => $no_hp !== '' ? $no_hp : null,
                    'user_id'       => $user_id,
                ]);

            } else {

                $stmt = $pdo->prepare(
                    'INSERT INTO siswa
                        (user_id, nis, nama, jenis_kelamin, email, no_hp)
                     VALUES
                        (:user_id, :nis, :nama, :jenis_kelamin, :email, :no_hp)'
                );

                $stmt->execute([
                    'user_id'       => $user_id,
                    'nis'           => $nis,
                    'nama'          => $nama,
                    'jenis_kelamin' => $jenis_kelamin,
                    'email'         => $email !== '' ? $email : null,
                    'no_hp'         => $no_hp !== '' ? $no_hp : null,
                ]);
            }
        }

        $pdo->commit();

        redirect_users('success', 'Data pengguna berhasil diperbarui.');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Edit pengguna error: ' . $e->getMessage());

        redirect_users(
            'error',
            'Data pengguna gagal diperbarui. Silakan coba lagi.'
        );
    }
}

if ($action === 'hapus' || $action === 'delete') {

    $user_id = (int) ($_POST['user_id'] ?? $_POST['id'] ?? 0);

    if ($user_id <= 0) {
        redirect_users('error', 'ID pengguna tidak valid.');
    }

    if ($user_id === current_user_id()) {
        redirect_users('error', 'Anda tidak dapat menghapus akun sendiri.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, nama, role
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $user_id,
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        redirect_users('error', 'Pengguna tidak ditemukan.');
    }

    try {

        $stmt = $pdo->prepare(
            'DELETE FROM users WHERE id = :id'
        );

        $stmt->execute([
            'id' => $user_id,
        ]);

        if ($stmt->rowCount() === 0) {
            redirect_users('error', 'Pengguna gagal dihapus.');
        }

        redirect_users(
            'success',
            'Pengguna "' . $user['nama'] . '" berhasil dihapus.'
        );

    } catch (PDOException $e) {

        error_log('Hapus pengguna error: ' . $e->getMessage());

        if ((int) $e->errorInfo[1] === 1451) {
            redirect_users(
                'error',
                'Pengguna tidak dapat dihapus karena masih memiliki data yang terkait.'
            );
        }

        redirect_users(
            'error',
            'Pengguna gagal dihapus. Silakan coba lagi.'
        );
    }
}

redirect_users('error', 'Aksi pengguna tidak dikenali.');