-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: db_absensi_qr
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `absensi`
--

DROP TABLE IF EXISTS `absensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `lokasi_masuk` text COLLATE utf8mb4_general_ci,
  `lokasi_pulang` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `input_method` enum('qr_scan','manual_admin','koreksi_staff') COLLATE utf8mb4_general_ci DEFAULT 'qr_scan',
  `input_by` int DEFAULT NULL COMMENT 'User ID yang input manual',
  `input_reason` text COLLATE utf8mb4_general_ci COMMENT 'Alasan input manual',
  `created_by_admin_at` timestamp NULL DEFAULT NULL,
  `keterangan` enum('Hadir','OFF','Sakit','Cuti','Alpha','Pending Dinas','Dinas Luar') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alasan` text COLLATE utf8mb4_general_ci,
  `foto_bukti` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waktu_alasan` datetime DEFAULT NULL,
  `is_manual_entry` tinyint(1) DEFAULT '0' COMMENT '0=Normal, 1=Manual Entry by Admin',
  `manual_entry_by` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'ID Karyawan Admin yang input manual',
  `status_masuk` enum('Tepat Waktu','Terlambat') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Tepat Waktu' COMMENT 'Status ketepatan waktu saat absen masuk',
  `face_verified` tinyint(1) DEFAULT '0' COMMENT '0=tidak pakai face, 1=verified',
  `face_confidence` decimal(5,2) DEFAULT NULL COMMENT 'Confidence score face matching (0-100)',
  `alasan_pulang` text COLLATE utf8mb4_general_ci,
  `foto_pulang` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_karyawan` (`id_karyawan`),
  KEY `idx_absensi_tanggal` (`tanggal`),
  KEY `idx_absensi_karyawan` (`id_karyawan`),
  KEY `idx_absensi_karyawan_tanggal` (`id_karyawan`,`tanggal`),
  KEY `idx_input_method` (`input_method`),
  KEY `idx_manual_entry` (`is_manual_entry`)
) ENGINE=InnoDB AUTO_INCREMENT=2452 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `absensi_archive`
--

DROP TABLE IF EXISTS `absensi_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensi_archive` (
  `id` int NOT NULL,
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_pulang` time DEFAULT NULL,
  `lokasi_masuk` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `lokasi_pulang` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `keterangan` enum('Hadir','OFF','Sakit','Cuti','Alpha') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Hadir',
  `archived_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_archive_karyawan` (`id_karyawan`),
  KEY `idx_archive_tanggal` (`tanggal`),
  KEY `idx_archive_tahun` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `absensi_summary_monthly`
--

DROP TABLE IF EXISTS `absensi_summary_monthly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `absensi_summary_monthly` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `tahun` year NOT NULL,
  `bulan` tinyint NOT NULL,
  `total_hadir` int DEFAULT '0',
  `total_izin` int DEFAULT '0',
  `total_sakit` int DEFAULT '0',
  `total_cuti` int DEFAULT '0',
  `total_alpha` int DEFAULT '0',
  `total_hari_kerja` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_karyawan_periode` (`id_karyawan`,`tahun`,`bulan`),
  KEY `idx_summary_periode` (`tahun`,`bulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_karyawan` (`id_karyawan`),
  KEY `idx_activity_date` (`created_at`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_month` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=303 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cabang`
--

DROP TABLE IF EXISTS `cabang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cabang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_cabang` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `alamat_cabang` text COLLATE utf8mb4_general_ci NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `radius_meter` int DEFAULT '100' COMMENT 'Radius area absen dalam meter',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `face_recognition_logs`
--

DROP TABLE IF EXISTS `face_recognition_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `face_recognition_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` varchar(20) NOT NULL,
  `attempt_type` enum('registration','verification') NOT NULL,
  `status` enum('success','failed','error') NOT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `error_message` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_karyawan` (`id_karyawan`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Log untuk tracking face recognition attempts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jabatan`
--

DROP TABLE IF EXISTS `jabatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jabatan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_jabatan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tunjangan_jabatan` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jam_kerja`
--

DROP TABLE IF EXISTS `jam_kerja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jam_kerja` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cabang` int NOT NULL,
  `nama_shift` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jam_masuk_akhir` time NOT NULL COMMENT 'Batas waktu maksimal untuk absen masuk',
  `jam_pulang` time NOT NULL COMMENT 'Jam pulang standar',
  PRIMARY KEY (`id`),
  KEY `id_cabang` (`id_cabang`),
  CONSTRAINT `jam_kerja_ibfk_1` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `karyawan`
--

DROP TABLE IF EXISTS `karyawan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `karyawan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_karyawan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_kelamin` enum('L','P') COLLATE utf8mb4_general_ci DEFAULT 'L',
  `status` enum('aktif','nonaktif') COLLATE utf8mb4_general_ci DEFAULT 'aktif',
  `foto` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tempat_lahir` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `agama` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_whatsapp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat_lengkap` text COLLATE utf8mb4_general_ci,
  `id_jabatan` int NOT NULL,
  `id_cabang` int NOT NULL,
  `rate_transport` decimal(15,2) DEFAULT '40000.00',
  `rate_overtime` decimal(15,2) DEFAULT '7500.00',
  `rate_insentif_minggu` decimal(15,2) DEFAULT '25000.00',
  `gaji_pokok` decimal(15,2) DEFAULT '0.00',
  `rate_keterlambatan` decimal(15,2) DEFAULT '20000.00',
  `tanggal_resign` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_karyawan` (`id_karyawan`),
  KEY `id_jabatan` (`id_jabatan`),
  KEY `id_cabang` (`id_cabang`),
  CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`id_jabatan`) REFERENCES `jabatan` (`id`),
  CONSTRAINT `karyawan_ibfk_2` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username_attempt` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `login_time` datetime NOT NULL,
  `status` enum('success','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `ip_address` (`ip_address`),
  KEY `login_time` (`login_time`),
  KEY `idx_login_logs_time` (`login_time`)
) ENGINE=InnoDB AUTO_INCREMENT=813 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `slip_gaji`
--

DROP TABLE IF EXISTS `slip_gaji`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slip_gaji` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `bulan` int NOT NULL COMMENT '1-12',
  `tahun` int NOT NULL,
  `tanggal_cetak` datetime DEFAULT CURRENT_TIMESTAMP,
  `gaji_pokok` decimal(15,2) DEFAULT '0.00',
  `tunjangan_cs` decimal(15,2) DEFAULT '0.00',
  `akomodasi` decimal(15,2) DEFAULT '0.00',
  `transport_nominal` decimal(15,2) DEFAULT '0.00',
  `transport_hari` decimal(5,1) DEFAULT '0.0',
  `transport_multiplier` decimal(5,1) DEFAULT '25.5',
  `transport_total` decimal(15,2) DEFAULT '0.00',
  `overtime_nominal` decimal(15,2) DEFAULT '0.00',
  `overtime_jam` decimal(5,1) DEFAULT '0.0',
  `overtime_multiplier` decimal(5,1) DEFAULT '6.0',
  `overtime_total` decimal(15,2) DEFAULT '0.00',
  `insentif_ahad_nominal` decimal(15,2) DEFAULT '0.00',
  `insentif_ahad_hari` decimal(5,1) DEFAULT '0.0',
  `insentif_ahad_multiplier` decimal(5,1) DEFAULT '4.0',
  `insentif_ahad_total` decimal(15,2) DEFAULT '0.00',
  `keterlambatan_nominal` decimal(15,2) DEFAULT '0.00',
  `keterlambatan_jumlah` int DEFAULT '0',
  `keterlambatan_multiplier` decimal(5,1) DEFAULT '2.0',
  `keterlambatan_total` decimal(15,2) DEFAULT '0.00',
  `total_penghasilan` decimal(15,2) DEFAULT '0.00',
  `total_potongan` decimal(15,2) DEFAULT '0.00',
  `digenapkan` decimal(15,2) DEFAULT '0.00',
  `gaji_bersih` decimal(15,2) DEFAULT '0.00',
  `dibuat_oleh` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `disetujui_oleh` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `diterima_oleh` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('draft','approved','paid') COLLATE utf8mb4_general_ci DEFAULT 'draft',
  `status_admin_acc` tinyint(1) DEFAULT '0',
  `admin_acc_at` datetime DEFAULT NULL,
  `status_owner_acc` tinyint(1) DEFAULT '0',
  `owner_acc_at` datetime DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `status_karyawan_acc` tinyint(1) DEFAULT '0',
  `karyawan_acc_at` datetime DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slip` (`id_karyawan`,`bulan`,`tahun`),
  KEY `idx_karyawan` (`id_karyawan`),
  KEY `idx_periode` (`bulan`,`tahun`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `slip_gaji_penghasilan`
--

DROP TABLE IF EXISTS `slip_gaji_penghasilan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slip_gaji_penghasilan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_slip_gaji` int NOT NULL,
  `keterangan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `rate` decimal(15,2) DEFAULT '0.00',
  `qty` decimal(5,1) DEFAULT '1.0',
  `nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_auto` tinyint(1) DEFAULT '0',
  `urutan` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_slip` (`id_slip_gaji`),
  CONSTRAINT `fk_penghasilan_slip` FOREIGN KEY (`id_slip_gaji`) REFERENCES `slip_gaji` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=186 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `slip_gaji_potongan`
--

DROP TABLE IF EXISTS `slip_gaji_potongan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slip_gaji_potongan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_slip_gaji` int NOT NULL,
  `keterangan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `rate` decimal(15,2) DEFAULT '0.00',
  `qty` decimal(5,1) DEFAULT '1.0',
  `nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_auto` tinyint(1) DEFAULT '0',
  `urutan` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_slip` (`id_slip_gaji`),
  CONSTRAINT `fk_potongan_slip` FOREIGN KEY (`id_slip_gaji`) REFERENCES `slip_gaji` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','staff','owner') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'staff',
  `jenis_kelamin` enum('L','P') COLLATE utf8mb4_general_ci DEFAULT 'L',
  `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `face_descriptor` text COLLATE utf8mb4_general_ci COMMENT 'JSON array untuk face recognition descriptor',
  `face_registered_at` datetime DEFAULT NULL COMMENT 'Tanggal registrasi wajah',
  `face_images_count` tinyint DEFAULT '0' COMMENT 'Jumlah foto wajah yang tersimpan',
  `face_reset_allowed` tinyint(1) DEFAULT '0' COMMENT '0=locked, 1=admin allowed reset',
  `ttd_path` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `wa_token` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stempel_path` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `foto_profil` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_face_reset` (`face_reset_allowed`)
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `view_slip_gaji_lengkap`
--

DROP TABLE IF EXISTS `view_slip_gaji_lengkap`;
/*!50001 DROP VIEW IF EXISTS `view_slip_gaji_lengkap`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `view_slip_gaji_lengkap` AS SELECT 
 1 AS `id`,
 1 AS `id_karyawan`,
 1 AS `bulan`,
 1 AS `tahun`,
 1 AS `tanggal_cetak`,
 1 AS `gaji_pokok`,
 1 AS `tunjangan_cs`,
 1 AS `akomodasi`,
 1 AS `transport_nominal`,
 1 AS `transport_hari`,
 1 AS `transport_multiplier`,
 1 AS `transport_total`,
 1 AS `overtime_nominal`,
 1 AS `overtime_jam`,
 1 AS `overtime_multiplier`,
 1 AS `overtime_total`,
 1 AS `insentif_ahad_nominal`,
 1 AS `insentif_ahad_hari`,
 1 AS `insentif_ahad_multiplier`,
 1 AS `insentif_ahad_total`,
 1 AS `keterlambatan_nominal`,
 1 AS `keterlambatan_jumlah`,
 1 AS `keterlambatan_multiplier`,
 1 AS `keterlambatan_total`,
 1 AS `total_penghasilan`,
 1 AS `total_potongan`,
 1 AS `digenapkan`,
 1 AS `gaji_bersih`,
 1 AS `dibuat_oleh`,
 1 AS `disetujui_oleh`,
 1 AS `diterima_oleh`,
 1 AS `created_by`,
 1 AS `created_at`,
 1 AS `updated_at`,
 1 AS `status`,
 1 AS `nama_karyawan`,
 1 AS `id_cabang`,
 1 AS `nama_cabang`,
 1 AS `nama_jabatan`,
 1 AS `total_penghasilan_tambahan`,
 1 AS `total_potongan_tambahan`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `view_slip_gaji_lengkap`
--

/*!50001 DROP VIEW IF EXISTS `view_slip_gaji_lengkap`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_slip_gaji_lengkap` AS select `sg`.`id` AS `id`,`sg`.`id_karyawan` AS `id_karyawan`,`sg`.`bulan` AS `bulan`,`sg`.`tahun` AS `tahun`,`sg`.`tanggal_cetak` AS `tanggal_cetak`,`sg`.`gaji_pokok` AS `gaji_pokok`,`sg`.`tunjangan_cs` AS `tunjangan_cs`,`sg`.`akomodasi` AS `akomodasi`,`sg`.`transport_nominal` AS `transport_nominal`,`sg`.`transport_hari` AS `transport_hari`,`sg`.`transport_multiplier` AS `transport_multiplier`,`sg`.`transport_total` AS `transport_total`,`sg`.`overtime_nominal` AS `overtime_nominal`,`sg`.`overtime_jam` AS `overtime_jam`,`sg`.`overtime_multiplier` AS `overtime_multiplier`,`sg`.`overtime_total` AS `overtime_total`,`sg`.`insentif_ahad_nominal` AS `insentif_ahad_nominal`,`sg`.`insentif_ahad_hari` AS `insentif_ahad_hari`,`sg`.`insentif_ahad_multiplier` AS `insentif_ahad_multiplier`,`sg`.`insentif_ahad_total` AS `insentif_ahad_total`,`sg`.`keterlambatan_nominal` AS `keterlambatan_nominal`,`sg`.`keterlambatan_jumlah` AS `keterlambatan_jumlah`,`sg`.`keterlambatan_multiplier` AS `keterlambatan_multiplier`,`sg`.`keterlambatan_total` AS `keterlambatan_total`,`sg`.`total_penghasilan` AS `total_penghasilan`,`sg`.`total_potongan` AS `total_potongan`,`sg`.`digenapkan` AS `digenapkan`,`sg`.`gaji_bersih` AS `gaji_bersih`,`sg`.`dibuat_oleh` AS `dibuat_oleh`,`sg`.`disetujui_oleh` AS `disetujui_oleh`,`sg`.`diterima_oleh` AS `diterima_oleh`,`sg`.`created_by` AS `created_by`,`sg`.`created_at` AS `created_at`,`sg`.`updated_at` AS `updated_at`,`sg`.`status` AS `status`,`k`.`nama_karyawan` AS `nama_karyawan`,`k`.`id_cabang` AS `id_cabang`,`c`.`nama_cabang` AS `nama_cabang`,`j`.`nama_jabatan` AS `nama_jabatan`,(select ifnull(sum(`slip_gaji_penghasilan`.`nominal`),0) from `slip_gaji_penghasilan` where (`slip_gaji_penghasilan`.`id_slip_gaji` = `sg`.`id`)) AS `total_penghasilan_tambahan`,(select ifnull(sum(`slip_gaji_potongan`.`nominal`),0) from `slip_gaji_potongan` where (`slip_gaji_potongan`.`id_slip_gaji` = `sg`.`id`)) AS `total_potongan_tambahan` from (((`slip_gaji` `sg` left join `karyawan` `k` on((`sg`.`id_karyawan` = `k`.`id_karyawan`))) left join `cabang` `c` on((`k`.`id_cabang` = `c`.`id`))) left join `jabatan` `j` on((`k`.`id_jabatan` = `j`.`id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-27 22:10:43
