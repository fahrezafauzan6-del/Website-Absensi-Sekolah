-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Sep 2026 pada 18.46
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi_sekolah`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `kelas_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `status` enum('Hadir','Terlambat','Izin','Sakit','Alpa') NOT NULL DEFAULT 'Hadir',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `kelas_id`, `siswa_id`, `tanggal`, `waktu`, `status`, `keterangan`, `created_at`) VALUES
(1, 5, 1, '2026-09-19', '12:53:15', 'Hadir', NULL, '2026-09-19 10:53:15'),
(2, 1, 3, '2026-09-19', '18:19:31', 'Hadir', NULL, '2026-09-19 11:19:31'),
(3, 5, 3, '2026-09-19', '18:22:21', 'Hadir', NULL, '2026-09-19 11:22:21'),
(4, 6, 3, '2026-09-19', '17:57:00', 'Alpa', NULL, '2026-09-19 15:57:30'),
(5, 9, 3, '2026-09-19', '18:05:00', 'Hadir', NULL, '2026-09-19 16:05:34'),
(6, 9, 1, '2026-09-19', '18:09:00', 'Hadir', NULL, '2026-09-19 16:09:44'),
(7, 5, 4, '2026-09-19', '18:22:00', 'Hadir', NULL, '2026-09-19 16:23:11'),
(8, 1, 4, '2026-09-19', '18:24:00', 'Hadir', NULL, '2026-09-19 16:24:10'),
(9, 5, 3, '2026-09-20', '18:24:00', 'Hadir', NULL, '2026-09-20 16:24:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nip` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id`, `user_id`, `nip`, `nama`, `email`, `no_hp`, `created_at`) VALUES
(2, 2, '57437', 'wahyu', 'wahyu@gmail.com', '05732835467', '2026-09-18 14:32:25'),
(3, 8, '4573254', 'guru', NULL, NULL, '2026-09-19 10:26:18');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama_kelas` varchar(100) NOT NULL,
  `guru_id` int(10) UNSIGNED NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id`, `nama_kelas`, `guru_id`, `hari`, `jam_mulai`, `jam_selesai`, `created_at`) VALUES
(1, 'X IPA 12', 2, 'Selasa', '08:30:00', '10:00:00', '2026-09-18 14:36:11'),
(5, 'X IPA 3 - Sejarah', 2, 'Senin', '13:27:00', '19:27:00', '2026-09-19 10:27:31'),
(6, 'X IPA 1 Matematika', 3, 'Kamis', '16:22:00', '22:22:00', '2026-09-19 14:22:33'),
(9, 'sahdah', 3, 'Selasa', '23:14:00', '23:59:00', '2026-09-19 15:14:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas_siswa`
--

CREATE TABLE `kelas_siswa` (
  `id` int(10) UNSIGNED NOT NULL,
  `kelas_id` int(10) UNSIGNED NOT NULL,
  `siswa_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kelas_siswa`
--

INSERT INTO `kelas_siswa` (`id`, `kelas_id`, `siswa_id`, `created_at`) VALUES
(14, 5, 4, '2026-09-19 16:22:28'),
(15, 5, 1, '2026-09-19 16:22:28'),
(16, 5, 3, '2026-09-19 16:22:28'),
(17, 1, 4, '2026-09-19 16:22:34'),
(18, 1, 1, '2026-09-19 16:22:34'),
(19, 1, 3, '2026-09-19 16:22:34'),
(20, 9, 4, '2026-09-19 16:22:38'),
(21, 9, 1, '2026-09-19 16:22:38'),
(22, 9, 3, '2026-09-19 16:22:38'),
(23, 6, 4, '2026-09-19 16:22:41'),
(24, 6, 1, '2026-09-19 16:22:41'),
(25, 6, 3, '2026-09-19 16:22:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nis` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id`, `user_id`, `nis`, `nama`, `jenis_kelamin`, `email`, `no_hp`, `created_at`) VALUES
(1, 3, '212149', 'Grant', 'L', 'grant@gmail.com', '0888880292897', '2026-09-18 13:37:46'),
(3, 9, '355543', 'siswa', 'L', NULL, NULL, '2026-09-19 11:01:47'),
(4, 18, '4545', 'bambang', 'L', 'bambang@gmail.com', '3725378265', '2026-09-19 16:22:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `role` enum('admin','guru','siswa') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$0vzNVfKLmkJcYwpOwaGX1OIMz2dJB8x1ItDKg0A3jGLstdd7jiVjO', 'fauzan', 'admin', '2026-09-18 11:26:03', '2026-09-18 11:26:03'),
(2, 'wahyu', '$2y$10$GKbll/hF26HKkIW1ylTl6O9vZIlLZeNY.H9dNgbh3NnJeI7gzS4xi', 'wahyu', 'guru', '2026-09-18 12:39:18', '2026-09-18 12:39:18'),
(3, '212149', '$2y$10$78CEn1UV3P0jXrBe31XZJeczw.VWonTZ7ne.BsCTYqMOPFVgmEJka', 'Grant', 'siswa', '2026-09-18 13:37:46', '2026-09-19 10:56:24'),
(8, 'guru', '$2y$10$FdlwM8m/um8u2nDh8/moyekNja2GhOuonN2OWpv/gD1G0yHQnOh3e', 'guru', 'guru', '2026-09-19 10:26:18', '2026-09-19 10:26:18'),
(9, 'siswa', '$2y$10$eO08VpyLmVHfwf3OCycAcOI1O9mk3Ls2FFiLgo80ObxqGQxiplGnO', 'siswa', 'siswa', '2026-09-19 11:01:46', '2026-09-19 11:01:46'),
(18, 'bambang', '$2y$10$rBGVCee9hM.yZ/7Y5upWb.RFq4VSSVzJpe0SmRb5VQ64KIO2qgpxG', 'bambang', 'siswa', '2026-09-19 16:22:19', '2026-09-19 16:22:19');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_absensi` (`kelas_id`,`siswa_id`,`tanggal`),
  ADD KEY `fk_absensi_siswa` (`siswa_id`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `nip` (`nip`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_kelas_guru` (`guru_id`);

--
-- Indeks untuk tabel `kelas_siswa`
--
ALTER TABLE `kelas_siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kelas_siswa` (`kelas_id`,`siswa_id`),
  ADD KEY `fk_kelas_siswa_siswa` (`siswa_id`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `nis` (`nis`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `kelas`
--
ALTER TABLE `kelas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `kelas_siswa`
--
ALTER TABLE `kelas_siswa`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD CONSTRAINT `fk_guru_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD CONSTRAINT `fk_kelas_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kelas_siswa`
--
ALTER TABLE `kelas_siswa`
  ADD CONSTRAINT `fk_kelas_siswa_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kelas_siswa_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
