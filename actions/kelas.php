<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/check_auth.php';

check_role('admin');

$allowed_days = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
];

function redirect_kelas(
    string $type = '',
    string $message = ''
): never {
    $url = '/absensi-sekolah/admin/kelas.php';

    if ($type !== '' && $message !== '') {
        $_SESSION[$type === 'success' ? 'success' : 'error'] = $message;

        $url .= '?' . http_build_query([
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

function valid_time(string $time): bool
{
    $time_object = DateTime::createFromFormat('H:i', $time);

    return $time_object !== false
        && $time_object->format('H:i') === $time;
}

function valid_day(string $day, array $allowed_days): bool
{
    return in_array($day, $allowed_days, true);
}

if (isset($_SESSION['csrf_token'])) {

    $csrf_token = post_string('csrf_token');

    if (
        $csrf_token === ''
        || !hash_equals((string) $_SESSION['csrf_token'], $csrf_token)
    ) {
        redirect_kelas(
            'error',
            'Token keamanan tidak valid.'
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_kelas(
        'error',
        'Metode request tidak valid.'
    );
}

$action = post_string('action');

if ($action === '') {
    redirect_kelas(
        'error',
        'Aksi kelas tidak ditemukan.'
    );
}

if (
    $action === 'tambah'
    || $action === 'create'
    || $action === 'simpan'
) {

    $nama_kelas = post_string('nama_kelas');
    $guru_id    = (int) ($_POST['guru_id'] ?? 0);
    $hari       = post_string('hari');
    $jam_mulai  = post_string('jam_mulai');
    $jam_selesai = post_string('jam_selesai');

    if (
        $nama_kelas === ''
        || $guru_id <= 0
        || $hari === ''
        || $jam_mulai === ''
        || $jam_selesai === ''
    ) {
        redirect_kelas(
            'error',
            'Nama kelas, guru, hari, jam mulai, dan jam selesai wajib diisi.'
        );
    }

    if (strlen($nama_kelas) > 100) {
        redirect_kelas(
            'error',
            'Nama kelas maksimal 100 karakter.'
        );
    }

    if (!valid_day($hari, $allowed_days)) {
        redirect_kelas(
            'error',
            'Hari tidak valid.'
        );
    }

    if (!valid_time($jam_mulai) || !valid_time($jam_selesai)) {
        redirect_kelas(
            'error',
            'Format jam tidak valid.'
        );
    }

    if ($jam_mulai >= $jam_selesai) {
        redirect_kelas(
            'error',
            'Jam selesai harus lebih besar dari jam mulai.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            g.id,
            g.user_id,
            g.nip,
            g.nama
         FROM guru g
         INNER JOIN users u
            ON u.id = g.user_id
         WHERE g.id = :guru_id
           AND u.role = :role
         LIMIT 1'
    );

    $stmt->execute([
        'guru_id' => $guru_id,
        'role'    => 'guru',
    ]);

    $guru = $stmt->fetch();

    if (!$guru) {
        redirect_kelas(
            'error',
            'Guru yang dipilih tidak ditemukan.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT id, nama_kelas
         FROM kelas
         WHERE guru_id = :guru_id
           AND hari = :hari
           AND jam_mulai < :jam_selesai
           AND jam_selesai > :jam_mulai
         LIMIT 1'
    );

    $stmt->execute([
        'guru_id'     => $guru_id,
        'hari'        => $hari,
        'jam_mulai'   => $jam_mulai,
        'jam_selesai' => $jam_selesai,
    ]);

    $bentrok = $stmt->fetch();

    if ($bentrok) {
        redirect_kelas(
            'error',
            'Jadwal guru bentrok dengan kelas "' . $bentrok['nama_kelas'] . '".'
        );
    }

    try {

        $stmt = $pdo->prepare(
            'INSERT INTO kelas
                (nama_kelas, guru_id, hari, jam_mulai, jam_selesai)
             VALUES
                (:nama_kelas, :guru_id, :hari, :jam_mulai, :jam_selesai)'
        );

        $stmt->execute([
            'nama_kelas'  => $nama_kelas,
            'guru_id'     => $guru_id,
            'hari'        => $hari,
            'jam_mulai'   => $jam_mulai,
            'jam_selesai' => $jam_selesai,
        ]);

        redirect_kelas(
            'success',
            'Kelas berhasil ditambahkan.'
        );

    } catch (PDOException $e) {

        error_log('Tambah kelas error: ' . $e->getMessage());

        redirect_kelas(
            'error',
            'Kelas gagal ditambahkan. Silakan coba lagi.'
        );
    }
}

if (
    $action === 'edit'
    || $action === 'update'
) {

    $kelas_id   = (int) ($_POST['kelas_id'] ?? $_POST['id'] ?? 0);
    $nama_kelas = post_string('nama_kelas');
    $guru_id    = (int) ($_POST['guru_id'] ?? 0);
    $hari       = post_string('hari');
    $jam_mulai  = post_string('jam_mulai');
    $jam_selesai = post_string('jam_selesai');
    $siswa_ids  = $_POST['siswa_ids'] ?? [];

    if (!is_array($siswa_ids)) {
        $siswa_ids = [];
    }

    $siswa_ids = array_values(array_unique(array_filter(
        array_map('intval', $siswa_ids),
        static fn(int $siswa_id): bool => $siswa_id > 0
    )));

    if ($kelas_id <= 0) {
        redirect_kelas(
            'error',
            'ID kelas tidak valid.'
        );
    }

    if (
        $nama_kelas === ''
        || $guru_id <= 0
        || $hari === ''
        || $jam_mulai === ''
        || $jam_selesai === ''
    ) {
        redirect_kelas(
            'error',
            'Nama kelas, guru, hari, jam mulai, dan jam selesai wajib diisi.'
        );
    }

    if (strlen($nama_kelas) > 100) {
        redirect_kelas(
            'error',
            'Nama kelas maksimal 100 karakter.'
        );
    }

    if (!valid_day($hari, $allowed_days)) {
        redirect_kelas(
            'error',
            'Hari tidak valid.'
        );
    }

    if (!valid_time($jam_mulai) || !valid_time($jam_selesai)) {
        redirect_kelas(
            'error',
            'Format jam tidak valid.'
        );
    }

    if ($jam_mulai >= $jam_selesai) {
        redirect_kelas(
            'error',
            'Jam selesai harus lebih besar dari jam mulai.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            id,
            nama_kelas,
            guru_id,
            hari,
            jam_mulai,
            jam_selesai
         FROM kelas
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $kelas_id,
    ]);

    $kelas_lama = $stmt->fetch();

    if (!$kelas_lama) {
        redirect_kelas(
            'error',
            'Kelas tidak ditemukan.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            g.id,
            g.user_id,
            g.nip,
            g.nama
         FROM guru g
         INNER JOIN users u
            ON u.id = g.user_id
         WHERE g.id = :guru_id
           AND u.role = :role
         LIMIT 1'
    );

    $stmt->execute([
        'guru_id' => $guru_id,
        'role'    => 'guru',
    ]);

    $guru = $stmt->fetch();

    if (!$guru) {
        redirect_kelas(
            'error',
            'Guru yang dipilih tidak ditemukan.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            id,
            nama_kelas
         FROM kelas
         WHERE guru_id = :guru_id
           AND hari = :hari
           AND jam_mulai < :jam_selesai
           AND jam_selesai > :jam_mulai
           AND id != :id
         LIMIT 1'
    );

    $stmt->execute([
        'guru_id'     => $guru_id,
        'hari'        => $hari,
        'jam_mulai'   => $jam_mulai,
        'jam_selesai' => $jam_selesai,
        'id'          => $kelas_id,
    ]);

    $bentrok = $stmt->fetch();

    if ($bentrok) {
        redirect_kelas(
            'error',
            'Jadwal guru bentrok dengan kelas "' . $bentrok['nama_kelas'] . '".'
        );
    }

    if ($siswa_ids) {
        $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
        $stmt = $pdo->prepare(
            'SELECT id
             FROM siswa
             WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($siswa_ids);

        $valid_siswa_ids = array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if (count($valid_siswa_ids) !== count($siswa_ids)) {
            redirect_kelas(
                'error',
                'Data siswa yang dipilih tidak valid.'
            );
        }
    }

    $stmt = $pdo->prepare(
        'SELECT siswa_id
         FROM kelas_siswa
         WHERE kelas_id = :kelas_id'
    );
    $stmt->execute([
        'kelas_id' => $kelas_id,
    ]);

    $current_siswa_ids = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    $removed_siswa_ids = array_diff($current_siswa_ids, $siswa_ids);

    if ($removed_siswa_ids) {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT siswa_id
             FROM absensi
             WHERE kelas_id = :kelas_id'
        );
        $stmt->execute([
            'kelas_id' => $kelas_id,
        ]);

        $attendance_siswa_ids = array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if (array_intersect($removed_siswa_ids, $attendance_siswa_ids)) {
            redirect_kelas(
                'error',
                'Siswa yang sudah memiliki riwayat absensi tidak dapat dikeluarkan dari kelas.'
            );
        }
    }

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE kelas
             SET nama_kelas = :nama_kelas,
                 guru_id = :guru_id,
                 hari = :hari,
                 jam_mulai = :jam_mulai,
                 jam_selesai = :jam_selesai
             WHERE id = :id'
        );

        $stmt->execute([
            'nama_kelas'  => $nama_kelas,
            'guru_id'     => $guru_id,
            'hari'        => $hari,
            'jam_mulai'   => $jam_mulai,
            'jam_selesai' => $jam_selesai,
            'id'          => $kelas_id,
        ]);

        $stmt = $pdo->prepare(
            'DELETE FROM kelas_siswa
             WHERE kelas_id = :kelas_id'
        );
        $stmt->execute([
            'kelas_id' => $kelas_id,
        ]);

        if ($siswa_ids) {
            $stmt = $pdo->prepare(
                'INSERT INTO kelas_siswa (kelas_id, siswa_id)
                 VALUES (:kelas_id, :siswa_id)'
            );

            foreach ($siswa_ids as $siswa_id) {
                $stmt->execute([
                    'kelas_id' => $kelas_id,
                    'siswa_id' => $siswa_id,
                ]);
            }
        }

        $pdo->commit();

        redirect_kelas(
            'success',
            'Data kelas berhasil diperbarui.'
        );

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Edit kelas error: ' . $e->getMessage());

        redirect_kelas(
            'error',
            'Data kelas gagal diperbarui. Silakan coba lagi.'
        );
    }
}

if (
    $action === 'hapus'
    || $action === 'delete'
) {

    $kelas_id = (int) ($_POST['kelas_id'] ?? $_POST['id'] ?? 0);

    if ($kelas_id <= 0) {
        redirect_kelas(
            'error',
            'ID kelas tidak valid.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT
            id,
            nama_kelas
         FROM kelas
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => $kelas_id,
    ]);

    $kelas = $stmt->fetch();

    if (!$kelas) {
        redirect_kelas(
            'error',
            'Kelas tidak ditemukan.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM kelas_siswa
         WHERE kelas_id = :kelas_id'
    );

    $stmt->execute([
        'kelas_id' => $kelas_id,
    ]);

    $total_siswa = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM absensi
         WHERE kelas_id = :kelas_id'
    );

    $stmt->execute([
        'kelas_id' => $kelas_id,
    ]);

    $total_absensi = (int) $stmt->fetchColumn();

    if ($total_siswa > 0 || $total_absensi > 0) {

        $detail = [];

        if ($total_siswa > 0) {
            $detail[] = $total_siswa . ' siswa terdaftar';
        }

        if ($total_absensi > 0) {
            $detail[] = $total_absensi . ' data absensi';
        }

        redirect_kelas(
            'error',
            'Kelas tidak dapat dihapus karena masih memiliki ' .
            implode(' dan ', $detail) .
            '.'
        );
    }

    try {

        $stmt = $pdo->prepare(
            'DELETE FROM kelas
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $kelas_id,
        ]);

        if ($stmt->rowCount() === 0) {
            redirect_kelas(
                'error',
                'Kelas gagal dihapus.'
            );
        }

        redirect_kelas(
            'success',
            'Kelas "' . $kelas['nama_kelas'] . '" berhasil dihapus.'
        );

    } catch (PDOException $e) {

        error_log('Hapus kelas error: ' . $e->getMessage());

        if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
            redirect_kelas(
                'error',
                'Kelas tidak dapat dihapus karena masih memiliki data terkait.'
            );
        }

        redirect_kelas(
            'error',
            'Kelas gagal dihapus. Silakan coba lagi.'
        );
    }
}

redirect_kelas(
    'error',
    'Aksi kelas tidak dikenali.'
);