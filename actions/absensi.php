<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_login();

$allowed_status = [
    'Hadir',
    'Terlambat',
    'Izin',
    'Sakit',
    'Alpa',
];

function redirect_absensi(string $url, string $type = '', string $message = ''): never
{
    if ($type !== '' && $message !== '') {
        $_SESSION[$type === 'success' ? 'success' : 'error'] = $message;

        $separator = str_contains($url, '?') ? '&' : '?';

        $url .= $separator . http_build_query([
            'status'  => $type,
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

function current_role(): string
{
    return current_user_role();
}

function is_valid_date(string $date): bool
{
    $date_object = DateTime::createFromFormat('Y-m-d', $date);

    return $date_object !== false
        && $date_object->format('Y-m-d') === $date;
}

function is_valid_time(string $time): bool
{
    $time_object = DateTime::createFromFormat('H:i', $time);

    return $time_object !== false
        && $time_object->format('H:i') === $time;
}

function validate_status(string $status, array $allowed_status): bool
{
    return in_array($status, $allowed_status, true);
}

if (isset($_SESSION['csrf_token'])) {

    $csrf_token = post_string('csrf_token');

    if (
        $csrf_token === ''
        || !hash_equals((string) $_SESSION['csrf_token'], $csrf_token)
    ) {
        redirect_absensi(
            '/absensi-sekolah/login.php',
            'error',
            'Token keamanan tidak valid.'
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_absensi(
        '/absensi-sekolah/login.php',
        'error',
        'Metode request tidak valid.'
    );
}

$action = post_string('action');

if ($action === '') {
    redirect_absensi(
        '/absensi-sekolah/login.php',
        'error',
        'Aksi absensi tidak ditemukan.'
    );
}

$role = current_role();

$default_redirect = match ($role) {
    'admin' => '/absensi-sekolah/admin/dashboard.php',
    'guru'  => '/absensi-sekolah/guru/absensi.php',
    'siswa' => '/absensi-sekolah/siswa/absensi.php',
    default => '/absensi-sekolah/login.php',
};

if ($action === 'tambah' || $action === 'create' || $action === 'simpan') {

    $kelas_id  = (int) ($_POST['kelas_id'] ?? 0);
    $siswa_id  = (int) ($_POST['siswa_id'] ?? 0);
    $tanggal   = post_string('tanggal');
    $waktu     = post_string('waktu');
    $status    = post_string('status');
    $keterangan = post_string('keterangan');

    if ($kelas_id <= 0 || $siswa_id <= 0) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Kelas atau siswa tidak valid.'
        );
    }

    if ($tanggal === '') {
        $tanggal = date('Y-m-d');
    }

    if (!is_valid_date($tanggal)) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Format tanggal tidak valid.'
        );
    }

    if ($tanggal > date('Y-m-d')) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Tanggal absensi tidak boleh melebihi hari ini.'
        );
    }

    if ($waktu === '') {
        $waktu = date('H:i:s');
    } elseif (preg_match('/^\d{2}:\d{2}$/', $waktu)) {
        $waktu .= ':00';
    } elseif (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $waktu)) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Format waktu tidak valid.'
        );
    }

    if (!validate_status($status, $allowed_status)) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Status absensi tidak valid.'
        );
    }

    if (
        in_array($status, ['Izin', 'Sakit'], true)
        && $keterangan === ''
    ) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Keterangan wajib diisi untuk status Izin atau Sakit.'
        );
    }

    if (strlen($keterangan) > 500) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Keterangan maksimal 500 karakter.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            k.id,
            k.nama_kelas,
            k.guru_id,
            g.user_id AS guru_user_id
         FROM kelas k
         INNER JOIN guru g ON g.id = k.guru_id
         WHERE k.id = :kelas_id
         LIMIT 1'
    );

    $stmt->execute([
        'kelas_id' => $kelas_id,
    ]);

    $kelas = $stmt->fetch();

    if (!$kelas) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Kelas tidak ditemukan.'
        );
    }

    if ($role === 'guru') {

        if ((int) $kelas['guru_user_id'] !== current_user_id()) {
            redirect_absensi(
                '/absensi-sekolah/guru/absensi.php',
                'error',
                'Anda tidak memiliki akses ke kelas tersebut.'
            );
        }
    }

    $stmt = $pdo->prepare(
        'SELECT
            s.id,
            s.user_id,
            s.nis,
            s.nama
         FROM siswa s
         INNER JOIN kelas_siswa ks
            ON ks.siswa_id = s.id
         WHERE ks.kelas_id = :kelas_id
           AND s.id = :siswa_id
         LIMIT 1'
    );

    $stmt->execute([
        'kelas_id' => $kelas_id,
        'siswa_id' => $siswa_id,
    ]);

    $siswa = $stmt->fetch();

    if (!$siswa) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Siswa tidak terdaftar pada kelas tersebut.'
        );
    }

    if ($role === 'siswa') {

        if ((int) $siswa['user_id'] !== current_user_id()) {
            redirect_absensi(
                '/absensi-sekolah/siswa/absensi.php',
                'error',
                'Anda hanya dapat mengisi absensi untuk akun sendiri.'
            );
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM absensi
         WHERE kelas_id = :kelas_id
           AND siswa_id = :siswa_id
           AND tanggal = :tanggal
         LIMIT 1'
    );

    $stmt->execute([
        'kelas_id' => $kelas_id,
        'siswa_id' => $siswa_id,
        'tanggal'  => $tanggal,
    ]);

    if ($stmt->fetch()) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Absensi siswa tersebut untuk tanggal ini sudah ada.'
        );
    }

    try {

        $stmt = $pdo->prepare(
            'INSERT INTO absensi
                (kelas_id, siswa_id, tanggal, waktu, status, keterangan)
             VALUES
                (:kelas_id, :siswa_id, :tanggal, :waktu, :status, :keterangan)'
        );

        $stmt->execute([
            'kelas_id'   => $kelas_id,
            'siswa_id'   => $siswa_id,
            'tanggal'    => $tanggal,
            'waktu'      => $waktu,
            'status'     => $status,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
        ]);

        if ($role === 'siswa') {
            redirect_absensi(
                '/absensi-sekolah/siswa/absensi.php',
                'success',
                'Absensi berhasil disimpan.'
            );
        }

        if ($role === 'guru') {
            redirect_absensi(
                '/absensi-sekolah/guru/absensi.php?kelas_id=' . $kelas_id,
                'success',
                'Absensi siswa berhasil disimpan.'
            );
        }

        redirect_absensi(
            '/absensi-sekolah/admin/dashboard.php',
            'success',
            'Absensi berhasil disimpan.'
        );

    } catch (PDOException $e) {

        error_log('Tambah absensi error: ' . $e->getMessage());

        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            redirect_absensi(
                $default_redirect,
                'error',
                'Absensi untuk siswa dan tanggal tersebut sudah ada.'
            );
        }

        redirect_absensi(
            $default_redirect,
            'error',
            'Absensi gagal disimpan. Silakan coba lagi.'
        );
    }
}

if ($action === 'edit' || $action === 'update') {

    $absensi_id = (int) ($_POST['absensi_id'] ?? $_POST['id'] ?? 0);
    $status     = post_string('status');
    $keterangan = post_string('keterangan');
    $tanggal    = post_string('tanggal');
    $waktu      = post_string('waktu');

    if ($absensi_id <= 0) {
        redirect_absensi(
            $default_redirect,
            'error',
            'ID absensi tidak valid.'
        );
    }

    if (!validate_status($status, $allowed_status)) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Status absensi tidak valid.'
        );
    }

    if ($tanggal !== '' && !is_valid_date($tanggal)) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Format tanggal tidak valid.'
        );
    }

    if ($tanggal !== '' && $tanggal > date('Y-m-d')) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Tanggal absensi tidak boleh melebihi hari ini.'
        );
    }

    if ($waktu !== '') {

        if (preg_match('/^\d{2}:\d{2}$/', $waktu)) {
            $waktu .= ':00';
        }

        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $waktu)) {
            redirect_absensi(
                $default_redirect,
                'error',
                'Format waktu tidak valid.'
            );
        }
    }

    if (
        in_array($status, ['Izin', 'Sakit'], true)
        && $keterangan === ''
    ) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Keterangan wajib diisi untuk status Izin atau Sakit.'
        );
    }

    if (strlen($keterangan) > 500) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Keterangan maksimal 500 karakter.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            a.id,
            a.kelas_id,
            a.siswa_id,
            a.tanggal,
            a.waktu,
            a.status,
            a.keterangan,
            k.nama_kelas,
            k.guru_id,
            g.user_id AS guru_user_id,
            s.user_id AS siswa_user_id
         FROM absensi a
         INNER JOIN kelas k
            ON k.id = a.kelas_id
         INNER JOIN guru g
            ON g.id = k.guru_id
         INNER JOIN siswa s
            ON s.id = a.siswa_id
         WHERE a.id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $absensi_id,
    ]);

    $absensi = $stmt->fetch();

    if (!$absensi) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Data absensi tidak ditemukan.'
        );
    }

    if ($role === 'guru') {

        if ((int) $absensi['guru_user_id'] !== current_user_id()) {
            redirect_absensi(
                '/absensi-sekolah/guru/absensi.php',
                'error',
                'Anda tidak memiliki akses untuk mengubah absensi ini.'
            );
        }
    }

    if ($role === 'siswa') {

        if ((int) $absensi['siswa_user_id'] !== current_user_id()) {
            redirect_absensi(
                '/absensi-sekolah/siswa/absensi.php',
                'error',
                'Anda tidak dapat mengubah absensi siswa lain.'
            );
        }
    }

    if ($tanggal === '') {
        $tanggal = $absensi['tanggal'];
    }

    if ($waktu === '') {
        $waktu = $absensi['waktu'];
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM absensi
         WHERE kelas_id = :kelas_id
           AND siswa_id = :siswa_id
           AND tanggal = :tanggal
           AND id != :id
         LIMIT 1'
    );

    $stmt->execute([
        'kelas_id' => $absensi['kelas_id'],
        'siswa_id' => $absensi['siswa_id'],
        'tanggal'  => $tanggal,
        'id'       => $absensi_id,
    ]);

    if ($stmt->fetch()) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Sudah ada absensi lain untuk siswa dan tanggal tersebut.'
        );
    }

    try {

        $stmt = $pdo->prepare(
            'UPDATE absensi
             SET tanggal = :tanggal,
                 waktu = :waktu,
                 status = :status,
                 keterangan = :keterangan
             WHERE id = :id'
        );

        $stmt->execute([
            'tanggal'    => $tanggal,
            'waktu'      => $waktu,
            'status'     => $status,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'id'         => $absensi_id,
        ]);

        if ($role === 'guru') {
            redirect_absensi(
                '/absensi-sekolah/guru/absensi.php?kelas_id=' . (int) $absensi['kelas_id'],
                'success',
                'Absensi berhasil diperbarui.'
            );
        }

        if ($role === 'siswa') {
            redirect_absensi(
                '/absensi-sekolah/siswa/riwayat.php',
                'success',
                'Absensi berhasil diperbarui.'
            );
        }

        redirect_absensi(
            '/absensi-sekolah/admin/dashboard.php',
            'success',
            'Absensi berhasil diperbarui.'
        );

    } catch (PDOException $e) {

        error_log('Update absensi error: ' . $e->getMessage());

        redirect_absensi(
            $default_redirect,
            'error',
            'Absensi gagal diperbarui. Silakan coba lagi.'
        );
    }
}

if ($action === 'hapus' || $action === 'delete') {

    $absensi_id = (int) ($_POST['absensi_id'] ?? $_POST['id'] ?? 0);

    if ($absensi_id <= 0) {
        redirect_absensi(
            $default_redirect,
            'error',
            'ID absensi tidak valid.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            a.id,
            a.kelas_id,
            a.siswa_id,
            a.tanggal,
            k.nama_kelas,
            g.user_id AS guru_user_id,
            s.user_id AS siswa_user_id
         FROM absensi a
         INNER JOIN kelas k
            ON k.id = a.kelas_id
         INNER JOIN guru g
            ON g.id = k.guru_id
         INNER JOIN siswa s
            ON s.id = a.siswa_id
         WHERE a.id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $absensi_id,
    ]);

    $absensi = $stmt->fetch();

    if (!$absensi) {
        redirect_absensi(
            $default_redirect,
            'error',
            'Data absensi tidak ditemukan.'
        );
    }

    if ($role === 'guru') {

        if ((int) $absensi['guru_user_id'] !== current_user_id()) {
            redirect_absensi(
                '/absensi-sekolah/guru/absensi.php',
                'error',
                'Anda tidak memiliki akses untuk menghapus absensi ini.'
            );
        }
    }

    if ($role === 'siswa') {

        if ((int) $absensi['siswa_user_id'] !== current_user_id()) {
            redirect_absensi(
                '/absensi-sekolah/siswa/riwayat.php',
                'error',
                'Anda tidak dapat menghapus absensi siswa lain.'
            );
        }
    }

    try {

        $stmt = $pdo->prepare(
            'DELETE FROM absensi WHERE id = :id'
        );

        $stmt->execute([
            'id' => $absensi_id,
        ]);

        if ($stmt->rowCount() === 0) {
            redirect_absensi(
                $default_redirect,
                'error',
                'Absensi gagal dihapus.'
            );
        }

        if ($role === 'guru') {
            redirect_absensi(
                '/absensi-sekolah/guru/absensi.php?kelas_id=' . (int) $absensi['kelas_id'],
                'success',
                'Absensi berhasil dihapus.'
            );
        }

        if ($role === 'siswa') {
            redirect_absensi(
                '/absensi-sekolah/siswa/riwayat.php',
                'success',
                'Absensi berhasil dihapus.'
            );
        }

        redirect_absensi(
            '/absensi-sekolah/admin/dashboard.php',
            'success',
            'Absensi berhasil dihapus.'
        );

    } catch (PDOException $e) {

        error_log('Hapus absensi error: ' . $e->getMessage());

        redirect_absensi(
            $default_redirect,
            'error',
            'Absensi gagal dihapus. Silakan coba lagi.'
        );
    }
}

redirect_absensi(
    $default_redirect,
    'error',
    'Aksi absensi tidak dikenali.'
);