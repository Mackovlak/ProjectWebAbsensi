<?php
return [
    'name' => 'Fitur Pengajuan Izin/Cuti/Dinas Luar (tabel pengajuan_izin + kolom pendukung)',
    'up' => function ($conn, MigrationLog $log) {
        // 1. Tabel pengajuan_izin (satu baris = satu pengajuan/rentang)
        if (!tabelAda($conn, 'pengajuan_izin')) {
            $sql = "CREATE TABLE `pengajuan_izin` (
                `id` int NOT NULL AUTO_INCREMENT,
                `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
                `jenis` enum('Cuti','Sakit','Izin','Dinas Luar') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Cuti',
                `tanggal_mulai` date NOT NULL,
                `tanggal_selesai` date NOT NULL,
                `jumlah_hari` int NOT NULL DEFAULT '0' COMMENT 'Total hari kalender dalam rentang',
                `jumlah_hari_kerja` int NOT NULL DEFAULT '0' COMMENT 'Hari efektif yang memotong kuota (tanpa Minggu/libur)',
                `keperluan` text COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Catatan/alasan dari karyawan',
                `lampiran` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nama file bukti (surat dokter dll)',
                `status` enum('Pending','Disetujui','Ditolak','Dibatalkan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
                `potong_kuota` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=memotong jatah cuti tahunan',
                `id_cabang` int DEFAULT NULL COMMENT 'Snapshot cabang saat pengajuan (untuk scoping supervisor)',
                `reviewed_by` int DEFAULT NULL COMMENT 'users.id yang menyetujui/menolak',
                `reviewed_at` datetime DEFAULT NULL,
                `catatan_reviewer` text COLLATE utf8mb4_general_ci,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_izin_karyawan` (`id_karyawan`),
                KEY `idx_izin_status` (`status`),
                KEY `idx_izin_cabang` (`id_cabang`),
                KEY `idx_izin_rentang` (`tanggal_mulai`,`tanggal_selesai`),
                KEY `idx_izin_karyawan_status` (`id_karyawan`,`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if ($conn->query($sql)) {
                $log->ok("Tabel <code>pengajuan_izin</code> berhasil dibuat.");
            } else {
                $log->error("Gagal membuat tabel pengajuan_izin: " . $conn->error);
                return; // semua langkah berikutnya butuh tabel ini
            }
        } else {
            $log->skip("Tabel <code>pengajuan_izin</code> sudah ada.");
        }

        // 2. users.role: tambah 'supervisor'
        if (!enumMengandung($conn, 'users', 'role', 'supervisor')) {
            $sql = "ALTER TABLE `users` MODIFY `role`
                    enum('admin','staff','owner','supervisor')
                    COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'staff'";
            if ($conn->query($sql)) {
                $log->ok("Enum <code>users.role</code> diperluas dengan nilai <b>supervisor</b>.");
            } else {
                $log->error("Gagal memperluas enum users.role: " . $conn->error);
            }
        } else {
            $log->skip("Enum <code>users.role</code> sudah mendukung supervisor.");
        }

        // 3. users.id_cabang: cakupan cabang untuk supervisor
        if (!kolomAda($conn, 'users', 'id_cabang')) {
            $sql = "ALTER TABLE `users` ADD `id_cabang` int DEFAULT NULL
                    COMMENT 'Cabang yang disupervisi (khusus role supervisor)' AFTER `id_karyawan`";
            if ($conn->query($sql)) {
                $conn->query("ALTER TABLE `users` ADD KEY `idx_users_cabang` (`id_cabang`)");
                $log->ok("Kolom <code>users.id_cabang</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom users.id_cabang: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>users.id_cabang</code> sudah ada.");
        }

        // 4. karyawan.jatah_cuti: kuota tahunan per karyawan (default 12)
        if (!kolomAda($conn, 'karyawan', 'jatah_cuti')) {
            $sql = "ALTER TABLE `karyawan` ADD `jatah_cuti` int NOT NULL DEFAULT '12'
                    COMMENT 'Jatah izin/cuti per tahun' AFTER `tanggal_resign`";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>karyawan.jatah_cuti</code> berhasil ditambahkan (default 12).");
            } else {
                $log->error("Gagal menambah kolom karyawan.jatah_cuti: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>karyawan.jatah_cuti</code> sudah ada.");
        }

        // 5. absensi.id_pengajuan: penanda baris hasil approval
        if (!kolomAda($conn, 'absensi', 'id_pengajuan')) {
            $sql = "ALTER TABLE `absensi` ADD `id_pengajuan` int DEFAULT NULL
                    COMMENT 'Referensi pengajuan_izin.id bila baris ini dibuat dari approval izin'";
            if ($conn->query($sql)) {
                $conn->query("ALTER TABLE `absensi` ADD KEY `idx_absensi_pengajuan` (`id_pengajuan`)");
                $log->ok("Kolom <code>absensi.id_pengajuan</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom absensi.id_pengajuan: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>absensi.id_pengajuan</code> sudah ada.");
        }

        // 6. absensi.keterangan: tambah nilai 'Izin'
        if (!enumMengandung($conn, 'absensi', 'keterangan', 'Izin')) {
            $sql = "ALTER TABLE `absensi` MODIFY `keterangan`
                    enum('Hadir','OFF','Sakit','Cuti','Izin','Alpha','Pending Dinas','Dinas Luar')
                    COLLATE utf8mb4_general_ci DEFAULT NULL";
            if ($conn->query($sql)) {
                $log->ok("Enum <code>absensi.keterangan</code> diperluas dengan nilai <b>Izin</b>.");
            } else {
                $log->error("Gagal memperluas enum absensi.keterangan: " . $conn->error);
            }
        } else {
            $log->skip("Enum <code>absensi.keterangan</code> sudah mendukung Izin.");
        }
    },
];
