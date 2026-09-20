<?php
$page_title = 'Absensi Sekolah - Sistem Absensi Digital';

$additional_css = [
    'landing.css'
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="landing-page">
    <nav class="navbar navbar-expand-lg landing-navbar" id="landingNavbar">
        <div class="container">

            <a href="<?= htmlspecialchars($base_url) ?>/index.php"
               class="landing-brand"
               aria-label="Absensi Sekolah">
                <span class="landing-brand-icon">
                    <i class="bi bi-calendar-check"></i>
                </span>
                <span>Absensi Sekolah</span>
            </a>

            <button class="navbar-toggler border-0 shadow-none"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#landingNav"
                    aria-controls="landingNav"
                    aria-expanded="false"
                    aria-label="Buka menu navigasi">
                <i class="bi bi-list fs-4"></i>
            </button>

            <div class="collapse navbar-collapse" id="landingNav">
                <div class="landing-nav ms-auto">

                    <a href="#beranda" class="nav-link active">
                        Beranda
                    </a>

                    <a href="#fitur" class="nav-link">
                        Fitur
                    </a>

                    <a href="#cara-kerja" class="nav-link">
                        Cara Kerja
                    </a>

                    <a href="#peran" class="nav-link">
                        Pengguna
                    </a>

                    <a href="<?= htmlspecialchars($base_url) ?>/login.php"
                       class="btn btn-primary ms-lg-2 px-4">
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Login
                    </a>

                </div>
            </div>

        </div>
    </nav>

    <main>

        <section class="landing-hero" id="beranda">
            <div class="container">

                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <div class="landing-hero-content landing-reveal">

                            <span class="landing-badge">
                                <i class="bi bi-stars"></i>
                                Sistem Absensi Digital Sekolah
                            </span>

                            <h1 class="landing-hero-title">
                                Kelola kehadiran sekolah
                                <span class="highlight">lebih mudah.</span>
                            </h1>

                            <p class="landing-hero-description">
                                Sistem absensi sekolah yang membantu admin, guru,
                                dan siswa mengelola kehadiran secara terstruktur,
                                cepat, dan mudah digunakan.
                            </p>

                            <div class="landing-hero-actions">

                                <a href="<?= htmlspecialchars($base_url) ?>/login.php"
                                   class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Mulai Sekarang
                                </a>

                                <a href="#fitur"
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-down-circle me-2"></i>
                                    Lihat Fitur
                                </a>

                            </div>

                            <div class="landing-hero-meta">

                                <span class="landing-hero-meta-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Mudah digunakan
                                </span>

                                <span class="landing-hero-meta-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Data terstruktur
                                </span>

                                <span class="landing-hero-meta-item">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Berbasis web
                                </span>

                            </div>

                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="landing-hero-visual landing-reveal">

                            <div class="landing-dashboard-preview">

                                <div class="landing-preview-header">

                                    <div class="landing-preview-dots">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>

                                    <span class="landing-preview-title">
                                        Dashboard Absensi
                                    </span>

                                </div>

                                <div class="landing-preview-body">

                                    <div class="landing-preview-welcome">
                                        <small>Selamat datang</small>
                                        <strong>Dashboard Sekolah</strong>
                                    </div>

                                    <div class="landing-preview-stats">

                                        <div class="landing-preview-stat">
                                            <div class="landing-preview-stat-icon">
                                                <i class="bi bi-people"></i>
                                            </div>

                                            <div>
                                                <small>Total Siswa</small>
                                                <strong>320</strong>
                                            </div>
                                        </div>

                                        <div class="landing-preview-stat">
                                            <div class="landing-preview-stat-icon">
                                                <i class="bi bi-person-check"></i>
                                            </div>

                                            <div>
                                                <small>Hadir</small>
                                                <strong>298</strong>
                                            </div>
                                        </div>

                                        <div class="landing-preview-stat">
                                            <div class="landing-preview-stat-icon">
                                                <i class="bi bi-person-x"></i>
                                            </div>

                                            <div>
                                                <small>Tidak Hadir</small>
                                                <strong>22</strong>
                                            </div>
                                        </div>

                                    </div>


                                    <div class="landing-preview-table">

                                        <div class="landing-preview-table-row header">
                                            <span>Nama Siswa</span>
                                            <span>Kelas</span>
                                            <span>Status</span>
                                        </div>

                                        <div class="landing-preview-table-row">
                                            <span>Ahmad Fauzan</span>
                                            <span>VII A</span>
                                            <span class="landing-preview-status">
                                                Hadir
                                            </span>
                                        </div>

                                        <div class="landing-preview-table-row">
                                            <span>Siti Rahma</span>
                                            <span>VII A</span>
                                            <span class="landing-preview-status">
                                                Hadir
                                            </span>
                                        </div>

                                        <div class="landing-preview-table-row">
                                            <span>Budi Santoso</span>
                                            <span>VII B</span>
                                            <span class="landing-preview-status">
                                                Hadir
                                            </span>
                                        </div>

                                        <div class="landing-preview-table-row">
                                            <span>Dewi Lestari</span>
                                            <span>VII B</span>
                                            <span class="landing-preview-status">
                                                Hadir
                                            </span>
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="landing-floating-card card-top">
                                <i class="bi bi-check-circle-fill"></i>

                                <div>
                                    <strong>Absensi Tercatat</strong>
                                    <small>Data tersimpan</small>
                                </div>
                            </div>

                            <div class="landing-floating-card card-bottom">
                                <i class="bi bi-graph-up-arrow"></i>

                                <div>
                                    <strong>Rekap Kehadiran</strong>
                                    <small>Mudah dipantau</small>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

            </div>
        </section>

        <section class="landing-section" id="fitur">
            <div class="container">

                <div class="landing-section-header landing-reveal">

                    <span class="landing-section-label">
                        Fitur Utama
                    </span>

                    <h2 class="landing-section-title">
                        Semua kebutuhan absensi dalam satu sistem
                    </h2>

                    <p class="landing-section-description">
                        Dirancang untuk membantu sekolah mengelola data
                        pengguna, kelas, dan kehadiran dengan lebih terorganisir.
                    </p>

                </div>


                <div class="row g-4">
                    <div class="col-md-6 col-lg-4">
                        <div class="landing-feature-card landing-reveal">

                            <div class="landing-feature-icon">
                                <i class="bi bi-person-badge"></i>
                            </div>

                            <h3>Manajemen Pengguna</h3>

                            <p>
                                Kelola akun admin, guru, dan siswa
                                berdasarkan peran masing-masing.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="landing-feature-card landing-reveal">

                            <div class="landing-feature-icon">
                                <i class="bi bi-building"></i>
                            </div>

                            <h3>Manajemen Kelas</h3>

                            <p>
                                Atur kelas, guru pengajar, jadwal,
                                serta daftar siswa dengan mudah.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="landing-feature-card landing-reveal">

                            <div class="landing-feature-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>

                            <h3>Pencatatan Absensi</h3>

                            <p>
                                Catat status kehadiran siswa seperti
                                hadir, terlambat, izin, sakit, dan alpa.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="landing-feature-card landing-reveal">

                            <div class="landing-feature-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>

                            <h3>Riwayat Absensi</h3>

                            <p>
                                Siswa dapat melihat riwayat kehadiran
                                mereka secara lebih teratur.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="landing-feature-card landing-reveal">

                            <div class="landing-feature-icon">
                                <i class="bi bi-bar-chart"></i>
                            </div>

                            <h3>Laporan Kehadiran</h3>

                            <p>
                                Guru dapat melihat rekap dan informasi
                                kehadiran siswa berdasarkan kelas.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <div class="landing-feature-card landing-reveal">

                            <div class="landing-feature-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>

                            <h3>Akses Berdasarkan Peran</h3>

                            <p>
                                Setiap pengguna mendapatkan menu dan
                                akses sesuai dengan perannya.
                            </p>

                        </div>
                    </div>

                </div>

            </div>
        </section>

        <section class="landing-section landing-section-primary">
            <div class="container">

                <div class="landing-section-header landing-reveal">

                    <span class="landing-section-label">
                        Dalam Satu Sistem
                    </span>

                    <h2 class="landing-section-title">
                        Pengelolaan absensi yang lebih terstruktur
                    </h2>

                    <p class="landing-section-description">
                        Satu platform untuk mendukung aktivitas administrasi
                        kehadiran di lingkungan sekolah.
                    </p>

                </div>


                <div class="row g-3">

                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card landing-reveal">

                            <div class="landing-stat-icon">
                                <i class="bi bi-people"></i>
                            </div>

                            <span class="landing-stat-value"
                                  data-counter="3">
                                3
                            </span>

                            <span class="landing-stat-label">
                                Jenis Pengguna
                            </span>

                        </div>
                    </div>


                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card landing-reveal">

                            <div class="landing-stat-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>

                            <span class="landing-stat-value"
                                  data-counter="5">
                                5
                            </span>

                            <span class="landing-stat-label">
                                Status Absensi
                            </span>

                        </div>
                    </div>


                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card landing-reveal">

                            <div class="landing-stat-icon">
                                <i class="bi bi-layout-text-window"></i>
                            </div>

                            <span class="landing-stat-value"
                                  data-counter="3">
                                3
                            </span>

                            <span class="landing-stat-label">
                                Area Dashboard
                            </span>

                        </div>
                    </div>


                    <div class="col-6 col-lg-3">
                        <div class="landing-stat-card landing-reveal">

                            <div class="landing-stat-icon">
                                <i class="bi bi-globe2"></i>
                            </div>

                            <span class="landing-stat-value"
                                  data-counter="100">
                                100%
                            </span>

                            <span class="landing-stat-label">
                                Berbasis Web
                            </span>

                        </div>
                    </div>

                </div>

            </div>
        </section>

        <section class="landing-section landing-section-light"
                 id="cara-kerja">

            <div class="container">

                <div class="landing-section-header landing-reveal">

                    <span class="landing-section-label">
                        Cara Kerja
                    </span>

                    <h2 class="landing-section-title">
                        Mulai menggunakan sistem dalam beberapa langkah
                    </h2>

                    <p class="landing-section-description">
                        Alur penggunaan dibuat sederhana agar setiap pengguna
                        dapat langsung memahami sistem.
                    </p>

                </div>


                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="landing-step landing-reveal">

                            <div class="landing-step-number">
                                1
                            </div>

                            <h3>Login</h3>

                            <p>
                                Masuk menggunakan akun yang telah diberikan
                                sesuai dengan peran pengguna.
                            </p>

                            <span class="landing-step-connector"></span>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="landing-step landing-reveal">

                            <div class="landing-step-number">
                                2
                            </div>

                            <h3>Kelola Absensi</h3>

                            <p>
                                Guru dapat mencatat kehadiran siswa
                                berdasarkan kelas dan jadwal.
                            </p>

                            <span class="landing-step-connector"></span>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="landing-step landing-reveal">

                            <div class="landing-step-number">
                                3
                            </div>

                            <h3>Pantau Riwayat</h3>

                            <p>
                                Data kehadiran dapat dipantau melalui
                                dashboard dan riwayat absensi.
                            </p>

                        </div>
                    </div>

                </div>

            </div>
        </section>

        <section class="landing-section" id="peran">

            <div class="container">

                <div class="landing-section-header landing-reveal">

                    <span class="landing-section-label">
                        Pengguna Sistem
                    </span>

                    <h2 class="landing-section-title">
                        Setiap pengguna memiliki akses yang sesuai
                    </h2>

                    <p class="landing-section-description">
                        Sistem membedakan fitur berdasarkan peran sehingga
                        setiap pengguna dapat fokus pada tugasnya.
                    </p>

                </div>


                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="landing-role-card landing-reveal">

                            <div class="landing-role-icon">
                                <i class="bi bi-person-gear"></i>
                            </div>

                            <h3>Administrator</h3>

                            <p>
                                Mengelola pengguna, data kelas, dan
                                konfigurasi dasar sistem.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="landing-role-card landing-reveal">

                            <div class="landing-role-icon">
                                <i class="bi bi-person-video3"></i>
                            </div>

                            <h3>Guru</h3>

                            <p>
                                Mengelola kelas yang diampu, mencatat
                                absensi, dan melihat laporan kehadiran.
                            </p>

                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="landing-role-card landing-reveal">

                            <div class="landing-role-icon">
                                <i class="bi bi-person"></i>
                            </div>

                            <h3>Siswa</h3>

                            <p>
                                Melihat kelas, mencatat informasi absensi,
                                dan memantau riwayat kehadiran.
                            </p>

                        </div>
                    </div>

                </div>

            </div>
        </section>

        <section class="landing-cta">

            <div class="container">

                <div class="landing-cta-content landing-reveal">

                    <h2 class="landing-cta-title">
                        Siap mengelola absensi sekolah?
                    </h2>

                    <p class="landing-cta-description">
                        Masuk ke sistem untuk mulai mengelola data
                        kehadiran sekolah.
                    </p>

                    <a href="<?= htmlspecialchars($base_url) ?>/login.php"
                       class="btn btn-light btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Masuk ke Sistem
                    </a>

                </div>

            </div>

        </section>

    </main>

    <footer class="landing-footer">

        <div class="container">

            <div class="row g-4">

                <div class="col-lg-6">

                    <a href="<?= htmlspecialchars($base_url) ?>/index.php"
                       class="landing-footer-brand">

                        <span class="landing-footer-brand-icon">
                            <i class="bi bi-calendar-check"></i>
                        </span>

                        <span>Absensi Sekolah</span>

                    </a>

                    <p>
                        Sistem informasi absensi sekolah berbasis web
                        untuk membantu pengelolaan kehadiran secara
                        lebih terstruktur.
                    </p>

                </div>


                <div class="col-6 col-lg-3">

                    <h3 class="landing-footer-title">
                        Navigasi
                    </h3>

                    <ul class="landing-footer-links">

                        <li>
                            <a href="#beranda">Beranda</a>
                        </li>

                        <li>
                            <a href="#fitur">Fitur</a>
                        </li>

                        <li>
                            <a href="#cara-kerja">Cara Kerja</a>
                        </li>

                        <li>
                            <a href="#peran">Pengguna</a>
                        </li>

                    </ul>

                </div>


                <div class="col-6 col-lg-3">

                    <h3 class="landing-footer-title">
                        Sistem
                    </h3>

                    <ul class="landing-footer-links">

                        <li>
                            <a href="<?= htmlspecialchars($base_url) ?>/login.php">
                                Login
                            </a>
                        </li>

                        <li>
                            <a href="#fitur">
                                Fitur
                            </a>
                        </li>

                        <li>
                            <a href="#peran">
                                Hak Akses
                            </a>
                        </li>

                    </ul>

                </div>

            </div>


            <div class="landing-footer-bottom">

                <div class="row align-items-center">

                    <div class="col-md-6">
                        <p>
                            &copy; <?= date('Y') ?> Absensi Sekolah.
                            Semua hak dilindungi.
                        </p>
                    </div>

                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                        <p>
                            Sistem Informasi Absensi Sekolah
                        </p>
                    </div>

                </div>

            </div>

        </div>

    </footer>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const navbar = document.getElementById('landingNavbar');
    const revealElements = document.querySelectorAll('.landing-reveal');
    const navLinks = document.querySelectorAll('.landing-nav .nav-link[href^="#"]');

    function updateNavbar() {
        if (!navbar) {
            return;
        }

        navbar.classList.toggle('scrolled', window.scrollY > 10);
    }

    updateNavbar();
    window.addEventListener('scroll', updateNavbar, { passive: true });

    if ('IntersectionObserver' in window) {

        const observer = new IntersectionObserver(
            function (entries, observerInstance) {

                entries.forEach(function (entry) {

                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observerInstance.unobserve(entry.target);
                    }

                });

            },
            {
                threshold: 0.12
            }
        );

        revealElements.forEach(function (element) {
            observer.observe(element);
        });

    } else {

        revealElements.forEach(function (element) {
            element.classList.add('is-visible');
        });

    }

    navLinks.forEach(function (link) {

        link.addEventListener('click', function (event) {

            const targetId = this.getAttribute('href');

            if (!targetId || targetId === '#') {
                return;
            }

            const target = document.querySelector(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();

            const navbarHeight = navbar
                ? navbar.offsetHeight
                : 0;

            const targetPosition =
                target.getBoundingClientRect().top +
                window.scrollY -
                navbarHeight +
                1;

            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });

            navLinks.forEach(function (navLink) {
                navLink.classList.remove('active');
            });

            this.classList.add('active');

            const landingNav = document.getElementById('landingNav');

            if (
                landingNav &&
                landingNav.classList.contains('show') &&
                window.bootstrap
            ) {
                const collapse =
                    bootstrap.Collapse.getInstance(landingNav) ||
                    bootstrap.Collapse.getOrCreateInstance(landingNav);

                collapse.hide();
            }

        });

    });

    const sections = document.querySelectorAll(
        'main section[id]'
    );

    if ('IntersectionObserver' in window && sections.length) {

        const sectionObserver = new IntersectionObserver(
            function (entries) {

                entries.forEach(function (entry) {

                    if (!entry.isIntersecting) {
                        return;
                    }

                    const id = entry.target.getAttribute('id');

                    navLinks.forEach(function (link) {

                        const href =
                            link.getAttribute('href');

                        link.classList.toggle(
                            'active',
                            href === '#' + id
                        );

                    });

                });

            },
            {
                rootMargin: '-35% 0px -55% 0px'
            }
        );

        sections.forEach(function (section) {
            sectionObserver.observe(section);
        });

    }

    const counters =
        document.querySelectorAll('[data-counter]');

    function animateCounter(element) {

        const target =
            parseFloat(element.getAttribute('data-counter'));

        if (Number.isNaN(target)) {
            return;
        }

        const suffix =
            element.textContent.trim().endsWith('%')
                ? '%'
                : '';

        const duration = 900;
        const startTime = performance.now();

        function updateCounter(currentTime) {

            const progress =
                Math.min(
                    (currentTime - startTime) / duration,
                    1
                );

            const eased =
                1 - Math.pow(1 - progress, 3);

            const currentValue =
                Math.round(target * eased);

            element.textContent =
                currentValue + suffix;

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            }

        }

        requestAnimationFrame(updateCounter);
    }


    if ('IntersectionObserver' in window && counters.length) {

        const counterObserver =
            new IntersectionObserver(
                function (entries, observerInstance) {

                    entries.forEach(function (entry) {

                        if (!entry.isIntersecting) {
                            return;
                        }

                        animateCounter(entry.target);
                        observerInstance.unobserve(entry.target);

                    });

                },
                {
                    threshold: 0.5
                }
            );

        counters.forEach(function (counter) {
            counterObserver.observe(counter);
        });

    }

});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>