<?php
return [
    'name' => 'Fitur Pengajuan Izin Lembur (approval SPV sebelum overtime hari kerja)',
    'up' => function ($conn, MigrationLog $log) {
        if (!tabelAda($conn, 'pengajuan_lembur')) {
            $sql = "CREATE TABLE `pengajuan_lembur` (
                `id` int NOT NULL AUTO_INCREMENT,
                `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
                `tanggal_mulai` date NOT NULL,
                `tanggal_selesai` date NOT NULL,
                `keperluan` text COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Alasan/proyek yang perlu lembur',
                `status` enum('Pending','Disetujui','Ditolak','Dibatalkan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
                `id_cabang` int DEFAULT NULL COMMENT 'Snapshot cabang saat pengajuan (untuk scoping supervisor)',
                `reviewed_by` int DEFAULT NULL COMMENT 'users.id yang menyetujui/menolak',
                `reviewed_at` datetime DEFAULT NULL,
                `catatan_reviewer` text COLLATE utf8mb4_general_ci,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_lembur_karyawan` (`id_karyawan`),
                KEY `idx_lembur_status` (`status`),
                KEY `idx_lembur_cabang` (`id_cabang`),
                KEY `idx_lembur_rentang` (`tanggal_mulai`,`tanggal_selesai`),
                KEY `idx_lembur_karyawan_status` (`id_karyawan`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if ($conn->query($sql)) {
                $log->ok("Tabel <code>pengajuan_lembur</code> berhasil dibuat.");
            } else {
                $log->error("Gagal membuat tabel pengajuan_lembur: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Tabel <code>pengajuan_lembur</code> sudah ada.");
        }
    },
];
