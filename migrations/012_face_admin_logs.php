<?php
return [
    'name' => 'Tabel face_admin_logs (audit aksi admin atas face recognition)',
    'up' => function ($conn, MigrationLog $log) {
        // toggle_face_reset_permission.php menulis ke tabel ini di setiap
        // aksi admin (allow reset / delete face / lock face) tanpa
        // penanganan error, di dalam transaksi - tabel ini absen dari dump
        // produksi (db_absensi_qr_schema.sql), jadi tanpa migrasi ini setiap
        // aksi reset-wajah admin throw dan rollback. Bentuk kolom mengikuti
        // persis apa yang di-INSERT oleh file itu (lihat juga
        // docker/mysql-init/01-schema.sql, yang sebelumnya menambal gap ini
        // secara manual hanya untuk lingkungan Docker dev).
        if (!tabelAda($conn, 'face_admin_logs')) {
            $sql = "CREATE TABLE `face_admin_logs` (
                `id` int NOT NULL AUTO_INCREMENT,
                `admin_id` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
                `target_id_karyawan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
                `action_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
                `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_face_admin_logs_target` (`target_id_karyawan`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            if ($conn->query($sql)) {
                $log->ok("Tabel <code>face_admin_logs</code> berhasil dibuat.");
            } else {
                $log->error("Gagal membuat tabel face_admin_logs: " . $conn->error);
            }
        } else {
            $log->skip("Tabel <code>face_admin_logs</code> sudah ada.");
        }
    },
];
