<?php

$page_title = 'Dashboard Admin - Absensi Sekolah';

require_once __DIR__ . '/../auth/check_auth.php';
check_role('admin');

require_once __DIR__ . '/../config/database.php';

$additional_js = ['dashboard.js'];

$additional_vendor_js = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(role = 'admin') AS admin,
        SUM(role = 'guru') AS guru,
        SUM(role = 'siswa') AS siswa
     FROM users"
);

$user_stats = $stmt->fetch();

$total_users  = (int) ($user_stats['total'] ?? 0);
$total_admin  = (int) ($user_stats['admin'] ?? 0);
$total_guru   = (int) ($user_stats['guru'] ?? 0);
$total_siswa  = (int) ($user_stats['siswa'] ?? 0);

$stmt = $pdo->query(
    "SELECT COUNT(*) AS total
     FROM kelas"
);

$total_kelas = (int) $stmt->fetchColumn();

$stmt = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Hadir') AS hadir,
        SUM(status = 'Terlambat') AS terlambat,
        SUM(status = 'Izin') AS izin,
        SUM(status = 'Sakit') AS sakit,
        SUM(status = 'Alpa') AS alpa
     FROM absensi
     WHERE tanggal = CURDATE()"
);

$today_stats = $stmt->fetch();

$total_absensi_hari_ini = (int) ($today_stats['total'] ?? 0);
$total_hadir            = (int) ($today_stats['hadir'] ?? 0);
$total_terlambat        = (int) ($today_stats['terlambat'] ?? 0);
$total_izin             = (int) ($today_stats['izin'] ?? 0);
$total_sakit            = (int) ($today_stats['sakit'] ?? 0);
$total_alpa             = (int) ($today_stats['alpa'] ?? 0);

$persentase_kehadiran = $total_absensi_hari_ini > 0
    ? round(($total_hadir / $total_absensi_hari_ini) * 100)
    : 0;

$stmt = $pdo->query(
    "SELECT
        a.id,
        a.tanggal,
        a.waktu,
        a.status,
        a.keterangan,
        s.nis,
        s.nama AS nama_siswa,
        k.nama_kelas
     FROM absensi a
     INNER JOIN siswa s
        ON s.id = a.siswa_id
     INNER JOIN kelas k
        ON k.id = a.kelas_id
     ORDER BY a.tanggal DESC, a.waktu DESC, a.id DESC
     LIMIT 8"
);

$recent_attendance = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.nip,
        g.nama AS nama_guru,
        COUNT(ks.siswa_id) AS total_siswa
     FROM kelas k
     INNER JOIN guru g
        ON g.id = k.guru_id
     LEFT JOIN kelas_siswa ks
        ON ks.kelas_id = k.id
     GROUP BY
        k.id,
        k.nama_kelas,
        k.hari,
        k.jam_mulai,
        k.jam_selesai,
        g.nip,
        g.nama
     ORDER BY k.id DESC
     LIMIT 5"
);

$recent_classes = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT
        tanggal,
        COUNT(*) AS total,
        SUM(status = 'Hadir') AS hadir,
        SUM(status = 'Terlambat') AS terlambat,
        SUM(status = 'Izin') AS izin,
        SUM(status = 'Sakit') AS sakit,
        SUM(status = 'Alpa') AS alpa
     FROM absensi
     WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND tanggal <= CURDATE()
     GROUP BY tanggal
     ORDER BY tanggal ASC"
);

$weekly_attendance = $stmt->fetchAll();

$weekly_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));

    $weekly_data[$date] = [
        'tanggal'    => $date,
        'total'      => 0,
        'hadir'      => 0,
        'terlambat'  => 0,
        'izin'       => 0,
        'sakit'      => 0,
        'alpa'       => 0,
    ];
}

foreach ($weekly_attendance as $row) {

    $date = $row['tanggal'];

    if (isset($weekly_data[$date])) {
        $weekly_data[$date] = [
            'tanggal'    => $date,
            'total'      => (int) $row['total'],
            'hadir'      => (int) $row['hadir'],
            'terlambat'  => (int) $row['terlambat'],
            'izin'       => (int) $row['izin'],
            'sakit'      => (int) $row['sakit'],
            'alpa'       => (int) $row['alpa'],
        ];
    }
}

$weekly_data_json = json_encode(
    array_values($weekly_data),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$alert_type = $_GET['status'] ?? '';
$alert_message = $_GET['message'] ?? '';

?>

<main class="main-content dashboard-page">

    <div class="page-header dashboard-page-header">

        <div>
            <span class="page-header-label">
                <i class="bi bi-grid-1x2-fill"></i>
                Administrator
            </span>

            <h1 class="page-title">
                Dashboard
            </h1>

            <p class="page-subtitle">
                Kelola dan pantau aktivitas sistem absensi sekolah.
            </p>
        </div>
    </div>

    <?php if ($alert_message !== ''): ?>

        <div
            class="alert alert-<?=
                                $alert_type === 'success' ? 'success' : 'danger'
                                ?> alert-dismissible fade show"
            role="alert">
            <i class="bi bi-<?=
                            $alert_type === 'success'
                                ? 'check-circle-fill'
                                : 'exclamation-triangle-fill'
                            ?>"></i>

            <span><?= e($alert_message) ?></span>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Tutup"></button>
        </div>

    <?php endif; ?>

    <section class="welcome-card mb-4">

        <div class="welcome-card-content">

            <div class="welcome-card-icon">
                <i class="bi bi-shield-check"></i>
            </div>

            <div>
                <h2>
                    Selamat datang, <?= e(current_user_name()) ?>!
                </h2>

                <p>
                    Pantau pengguna, kelas, dan aktivitas absensi
                    sekolah dari satu tempat.
                </p>
            </div>

        </div>

        <div class="welcome-card-date">
            <span data-live-date>
                <?= e(date('d/m/Y')) ?>
            </span>

            <strong data-live-clock>
                <?= e(date('H:i:s')) ?>
            </strong>
        </div>

    </section>

    <section class="row g-4 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-card-icon primary">
                    <i class="bi bi-people-fill"></i>
                </div>

                <div class="stat-card-content">

                    <span class="stat-card-label">
                        Total Pengguna
                    </span>

                    <strong
                        class="stat-card-value"
                        data-counter="<?= $total_users ?>">
                        0
                    </strong>

                    <small>
                        <?= $total_guru ?> guru ·
                        <?= $total_siswa ?> siswa
                    </small>

                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-card-icon info">
                    <i class="bi bi-building-fill"></i>
                </div>

                <div class="stat-card-content">

                    <span class="stat-card-label">
                        Total Kelas
                    </span>

                    <strong
                        class="stat-card-value"
                        data-counter="<?= $total_kelas ?>">
                        0
                    </strong>

                    <small>
                        Kelas aktif dalam sistem
                    </small>

                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-card-icon success">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>

                <div class="stat-card-content">

                    <span class="stat-card-label">
                        Absensi Hari Ini
                    </span>

                    <strong
                        class="stat-card-value"
                        data-counter="<?= $total_absensi_hari_ini ?>">
                        0
                    </strong>

                    <small>
                        <?= $total_hadir ?> hadir ·
                        <?= $total_terlambat ?> terlambat
                    </small>

                </div>

            </div>

        </div>

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-card-icon warning">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>

                <div class="stat-card-content">

                    <span class="stat-card-label">
                        Kehadiran Hari Ini
                    </span>

                    <strong
                        class="stat-card-value"
                        data-counter="<?= $persentase_kehadiran ?>"
                        data-counter-suffix="%">
                        0%
                    </strong>

                    <small>
                        Berdasarkan <?= $total_absensi_hari_ini ?> absensi
                    </small>

                </div>

            </div>

        </div>

    </section>

    <section class="dashboard-card mb-4">

        <div class="dashboard-card-header">

            <div>
                <h2 class="dashboard-card-title">
                    Ringkasan Absensi Hari Ini
                </h2>

                <p class="dashboard-card-subtitle">
                    Statistik status absensi pada tanggal
                    <?= e(date('d/m/Y')) ?>.
                </p>
            </div>
        </div>

        <div class="dashboard-card-body">

            <div class="attendance-summary">

                <div class="attendance-summary-item">
                    <span class="attendance-summary-icon hadir">
                        <i class="bi bi-check-circle-fill"></i>
                    </span>

                    <div>
                        <strong><?= $total_hadir ?></strong>
                        <span>Hadir</span>
                    </div>
                </div>

                <div class="attendance-summary-item">
                    <span class="attendance-summary-icon terlambat">
                        <i class="bi bi-clock-fill"></i>
                    </span>

                    <div>
                        <strong><?= $total_terlambat ?></strong>
                        <span>Terlambat</span>
                    </div>
                </div>

                <div class="attendance-summary-item">
                    <span class="attendance-summary-icon izin">
                        <i class="bi bi-envelope-fill"></i>
                    </span>

                    <div>
                        <strong><?= $total_izin ?></strong>
                        <span>Izin</span>
                    </div>
                </div>

                <div class="attendance-summary-item">
                    <span class="attendance-summary-icon sakit">
                        <i class="bi bi-bandaid-fill"></i>
                    </span>

                    <div>
                        <strong><?= $total_sakit ?></strong>
                        <span>Sakit</span>
                    </div>
                </div>

                <div class="attendance-summary-item">
                    <span class="attendance-summary-icon alpa">
                        <i class="bi bi-x-circle-fill"></i>
                    </span>

                    <div>
                        <strong><?= $total_alpa ?></strong>
                        <span>Alpa</span>
                    </div>
                </div>

            </div>

        </div>

    </section>

    <section class="mb-4">

        <div class="section-heading mb-3">
            <div>
                <h2 class="section-title">
                    Aksi Cepat
                </h2>

                <p class="section-subtitle">
                    Akses fitur administrasi yang sering digunakan.
                </p>
            </div>
        </div>

        <div class="row g-3">

            <div class="col-12 col-sm-6 col-lg-3">

                <a
                    href="/absensi-sekolah/admin/user_tambah.php"
                    class="quick-action">
                    <span class="quick-action-icon primary">
                        <i class="bi bi-person-plus-fill"></i>
                    </span>

                    <span class="quick-action-content">
                        <strong>Tambah Pengguna</strong>
                        <small>Buat akun guru atau siswa</small>
                    </span>

                    <i class="bi bi-chevron-right"></i>
                </a>

            </div>

            <div class="col-12 col-sm-6 col-lg-3">

                <a
                    href="/absensi-sekolah/admin/kelas_tambah.php"
                    class="quick-action">
                    <span class="quick-action-icon info">
                        <i class="bi bi-building-add"></i>
                    </span>

                    <span class="quick-action-content">
                        <strong>Tambah Kelas</strong>
                        <small>Buat kelas dan jadwal baru</small>
                    </span>

                    <i class="bi bi-chevron-right"></i>
                </a>

            </div>

            <div class="col-12 col-sm-6 col-lg-3">

                <a
                    href="/absensi-sekolah/admin/users.php"
                    class="quick-action">
                    <span class="quick-action-icon success">
                        <i class="bi bi-people-fill"></i>
                    </span>

                    <span class="quick-action-content">
                        <strong>Kelola Pengguna</strong>
                        <small>Lihat dan kelola akun</small>
                    </span>

                    <i class="bi bi-chevron-right"></i>
                </a>

            </div>

            <div class="col-12 col-sm-6 col-lg-3">

                <a
                    href="/absensi-sekolah/admin/kelas.php"
                    class="quick-action">
                    <span class="quick-action-icon warning">
                        <i class="bi bi-diagram-3-fill"></i>
                    </span>

                    <span class="quick-action-content">
                        <strong>Kelola Kelas</strong>
                        <small>Atur kelas dan guru</small>
                    </span>

                    <i class="bi bi-chevron-right"></i>
                </a>

            </div>

        </div>

    </section>

    <div class="row g-4">

        <div class="col-12 col-xl-7">

            <section class="dashboard-card h-100">

                <div class="dashboard-card-header">

                    <div>
                        <h2 class="dashboard-card-title">
                            Absensi Terbaru
                        </h2>

                        <p class="dashboard-card-subtitle">
                            Aktivitas absensi terakhir dalam sistem.
                        </p>
                    </div>
                </div>

                <div class="dashboard-card-body p-0">

                    <div class="table-responsive-custom">

                        <table class="table table-hover align-middle mb-0">

                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Kelas</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($recent_attendance)): ?>

                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state py-5">

                                                <div class="empty-state-icon">
                                                    <i class="bi bi-calendar-x"></i>
                                                </div>

                                                <h3>
                                                    Belum ada data absensi
                                                </h3>

                                                <p>
                                                    Data absensi akan muncul
                                                    setelah siswa melakukan
                                                    absensi.
                                                </p>

                                            </div>
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($recent_attendance as $attendance): ?>

                                        <?php
                                        $status_class = match ($attendance['status']) {
                                            'Hadir'      => 'badge-hadir',
                                            'Terlambat'  => 'badge-terlambat',
                                            'Izin'       => 'badge-izin',
                                            'Sakit'      => 'badge-sakit',
                                            'Alpa'       => 'badge-alpa',
                                            default      => 'bg-secondary',
                                        };
                                        ?>

                                        <tr>

                                            <td>
                                                <div class="d-flex align-items-center gap-2">

                                                    <div class="avatar avatar-sm">
                                                        <?= e(
                                                            strtoupper(
                                                                substr(
                                                                    $attendance['nama_siswa'],
                                                                    0,
                                                                    1
                                                                )
                                                            )
                                                        ) ?>
                                                    </div>

                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?= e($attendance['nama_siswa']) ?>
                                                        </div>

                                                        <small class="text-muted">
                                                            <?= e($attendance['nis']) ?>
                                                        </small>
                                                    </div>

                                                </div>
                                            </td>

                                            <td>
                                                <span class="text-secondary">
                                                    <?= e($attendance['nama_kelas']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="small fw-medium">
                                                    <?= e(
                                                        date(
                                                            'd/m/Y',
                                                            strtotime($attendance['tanggal'])
                                                        )
                                                    ) ?>
                                                </div>

                                                <small class="text-muted">
                                                    <?= e(
                                                        date(
                                                            'H:i',
                                                            strtotime($attendance['waktu'])
                                                        )
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <span class="badge <?= e($status_class) ?>">
                                                    <?= e($attendance['status']) ?>
                                                </span>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </section>

        </div>

        <div class="col-12 col-xl-5">

            <section class="dashboard-card h-100">

                <div class="dashboard-card-header">

                    <div>
                        <h2 class="dashboard-card-title">
                            Kelas
                        </h2>

                        <p class="dashboard-card-subtitle">
                            Daftar kelas terbaru.
                        </p>
                    </div>
                </div>

                <div class="dashboard-card-body">

                    <?php if (empty($recent_classes)): ?>

                        <div class="empty-state py-4">

                            <div class="empty-state-icon">
                                <i class="bi bi-building"></i>
                            </div>

                            <h3>
                                Belum ada kelas
                            </h3>

                            <p>
                                Tambahkan kelas untuk mulai menggunakan
                                sistem.
                            </p>

                            <a
                                href="/absensi-sekolah/admin/kelas_tambah.php"
                                class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i>
                                Tambah Kelas
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="list-group list-group-flush">

                            <?php foreach ($recent_classes as $class): ?>

                                <a
                                    href="/absensi-sekolah/admin/kelas_view.php?id=<?= (int) $class['id'] ?>"
                                    class="list-group-item list-group-item-action px-0">

                                    <div class="d-flex align-items-center gap-3">

                                        <div class="avatar avatar-md bg-primary-subtle text-primary">
                                            <i class="bi bi-building-fill"></i>
                                        </div>

                                        <div class="flex-grow-1 min-width-0">

                                            <div class="fw-semibold text-truncate">
                                                <?= e($class['nama_kelas']) ?>
                                            </div>

                                            <div class="small text-muted">
                                                <?= e($class['nama_guru']) ?>
                                            </div>

                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-calendar3"></i>
                                                <?= e($class['hari']) ?>

                                                <span class="mx-1">•</span>

                                                <i class="bi bi-clock"></i>
                                                <?= e(
                                                    date(
                                                        'H:i',
                                                        strtotime($class['jam_mulai'])
                                                    )
                                                ) ?>
                                                -
                                                <?= e(
                                                    date(
                                                        'H:i',
                                                        strtotime($class['jam_selesai'])
                                                    )
                                                ) ?>
                                            </div>

                                        </div>

                                        <div class="text-end">

                                            <strong class="d-block">
                                                <?= (int) $class['total_siswa'] ?>
                                            </strong>

                                            <small class="text-muted">
                                                siswa
                                            </small>

                                        </div>

                                    </div>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </div>

    <section class="dashboard-card mt-4">

        <div class="dashboard-card-header">

            <div>
                <h2 class="dashboard-card-title">
                    Aktivitas Absensi 7 Hari Terakhir
                </h2>

                <p class="dashboard-card-subtitle">
                    Jumlah absensi berdasarkan status selama tujuh hari.
                </p>
            </div>

        </div>

        <div class="dashboard-card-body">

            <div
                class="attendance-chart"
                data-weekly-attendance
                style="height: 300px;">
                <canvas id="weeklyAttendanceChart"></canvas>
            </div>

        </div>

    </section>

</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const weeklyData = <?= $weekly_data_json ?: '[]' ?>;
        const canvas = document.getElementById('weeklyAttendanceChart');

        if (!canvas || !weeklyData.length) {
            return;
        }

        if (typeof Chart !== 'undefined') {

            const labels = weeklyData.map(function(item) {

                const date = new Date(item.tanggal + 'T00:00:00');

                return date.toLocaleDateString('id-ID', {
                    day: '2-digit',
                    month: 'short'
                });
            });

            new Chart(canvas, {
                type: 'line',

                data: {
                    labels: labels,

                    datasets: [{
                            label: 'Hadir',
                            data: weeklyData.map(item => item.hadir),
                            borderWidth: 2,
                            tension: 0.35,
                            fill: false
                        },
                        {
                            label: 'Terlambat',
                            data: weeklyData.map(item => item.terlambat),
                            borderWidth: 2,
                            tension: 0.35,
                            fill: false
                        },
                        {
                            label: 'Izin',
                            data: weeklyData.map(item => item.izin),
                            borderWidth: 2,
                            tension: 0.35,
                            fill: false
                        },
                        {
                            label: 'Sakit',
                            data: weeklyData.map(item => item.sakit),
                            borderWidth: 2,
                            tension: 0.35,
                            fill: false
                        },
                        {
                            label: 'Alpa',
                            data: weeklyData.map(item => item.alpa),
                            borderWidth: 2,
                            tension: 0.35,
                            fill: false
                        }
                    ]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },

                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },

                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            return;
        }

        const wrapper = canvas.parentElement;

        if (!wrapper) {
            return;
        }

        const total = weeklyData.reduce(function(sum, item) {
            return sum + Number(item.total || 0);
        }, 0);

        wrapper.innerHTML = `
        <div class="empty-state py-4">
            <div class="empty-state-icon">
                <i class="bi bi-bar-chart-line"></i>
            </div>

            <h3>${total} absensi</h3>

            <p>
                Chart.js belum dimuat.
                Data absensi tetap tersimpan dan dapat dilihat melalui laporan.
            </p>
        </div>
    `;
    });
</script>

<?php

require_once __DIR__ . '/../includes/footer.php';
