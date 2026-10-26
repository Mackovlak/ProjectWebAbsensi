<?php
return [
    'name' => 'Penugasan lembur oleh atasan (SPV/admin menugaskan, karyawan menerima/menolak)',
    'up' => function ($conn, MigrationLog $log) {
        // Penugasan disimpan di tabel yang sama dengan pengajuan karyawan supaya
        // penugasan yang diterima cukup berstatus 'Disetujui' dan otomatis ikut
        // terhitung di hitungJamLemburDisetujui() / slip gaji tanpa jalur baru.
        $columns = [
            'sumber' => "ENUM('Pengajuan','Penugasan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pengajuan' COMMENT 'Pengajuan = diajukan karyawan, Penugasan = ditugaskan atasan' AFTER `id_karyawan`",
            'ditugaskan_oleh' => "INT DEFAULT NULL COMMENT 'users.id SPV/admin yang menugaskan (sumber = Penugasan)' AFTER `sumber`",
            'jam_mulai' => "TIME DEFAULT NULL COMMENT 'Jendela waktu lembur yang ditugaskan' AFTER `tanggal_selesai`",
            'jam_selesai' => "TIME DEFAULT NULL COMMENT 'Batas jam lembur yang dihitung ke slip gaji' AFTER `jam_mulai`",
            'alasan_karyawan' => "TEXT COLLATE utf8mb4_general_ci COMMENT 'Tanggapan karyawan atas penugasan (wajib bila menolak)' AFTER `catatan_reviewer`",
            'direspon_at' => "DATETIME DEFAULT NULL COMMENT 'Waktu karyawan menerima/menolak penugasan' AFTER `alasan_karyawan`",
        ];
        foreach ($columns as $name => $definition) {
            if (kolomAda($conn, 'pengajuan_lembur', $name)) {
                $log->skip("Kolom <code>pengajuan_lembur.$name</code> sudah ada.");
                continue;
            }
            if (!$conn->query("ALTER TABLE `pengajuan_lembur` ADD COLUMN `$name` $definition")) {
                $log->error("Gagal menambah pengajuan_lembur.$name: " . $conn->error);
                return;
            }
            $log->ok("Kolom <code>pengajuan_lembur.$name</code> berhasil ditambahkan.");
        }

        if (enumMengandung($conn, 'pengajuan_lembur', 'status', 'Menunggu Karyawan')
            && enumMengandung($conn, 'pengajuan_lembur', 'status', 'Ditolak Karyawan')
            && enumMengandung($conn, 'pengajuan_lembur', 'status', 'Kedaluwarsa')) {
            $log->skip("Enum <code>pengajuan_lembur.status</code> sudah memuat status penugasan.");
        } else {
            $sql = "ALTER TABLE `pengajuan_lembur` MODIFY `status`
                    ENUM('Pending','Disetujui','Ditolak','Dibatalkan','Menunggu Karyawan','Ditolak Karyawan','Kedaluwarsa')
                    COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending'";
            if (!$conn->query($sql)) {
                $log->error("Gagal memperluas enum pengajuan_lembur.status: " . $conn->error);
                return;
            }
            $log->ok("Enum <code>pengajuan_lembur.status</code> ditambah 'Menunggu Karyawan', 'Ditolak Karyawan', 'Kedaluwarsa'.");
        }

        if (indexAda($conn, 'pengajuan_lembur', 'idx_lembur_sumber_status')) {
            $log->skip("Index <code>idx_lembur_sumber_status</code> sudah ada.");
        } elseif ($conn->query("ALTER TABLE `pengajuan_lembur` ADD KEY `idx_lembur_sumber_status` (`sumber`,`status`)")) {
            $log->ok("Index <code>idx_lembur_sumber_status</code> berhasil dibuat.");
        } else {
            $log->error("Gagal membuat index idx_lembur_sumber_status: " . $conn->error);
        }
    },
];
