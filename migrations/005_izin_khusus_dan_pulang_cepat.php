<?php
return [
    'name' => 'Cuti khusus (Menikah/Menikahkan Anak/Melahirkan/Duka Cita) + izin pulang cepat',
    'up' => function ($conn, MigrationLog $log) {
        // 1. pengajuan_izin.jenis: tambah 4 jenis cuti khusus
        if (!enumMengandung($conn, 'pengajuan_izin', 'jenis', 'Duka Cita')) {
            $sql = "ALTER TABLE `pengajuan_izin` MODIFY `jenis`
                    enum('Cuti','Sakit','Izin','Dinas Luar','Menikah','Menikahkan Anak','Melahirkan','Duka Cita')
                    COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Cuti'";
            if ($conn->query($sql)) {
                $log->ok("Enum <code>pengajuan_izin.jenis</code> diperluas dengan 4 jenis cuti khusus "
                    . "(Menikah, Menikahkan Anak, Melahirkan, Duka Cita).");
            } else {
                $log->error("Gagal memperluas enum pengajuan_izin.jenis: " . $conn->error);
            }
        } else {
            $log->skip("Enum <code>pengajuan_izin.jenis</code> sudah mendukung cuti khusus.");
        }

        // 2. absensi.keterangan: tambah 4 nilai cuti khusus
        if (!enumMengandung($conn, 'absensi', 'keterangan', 'Duka Cita')) {
            $sql = "ALTER TABLE `absensi` MODIFY `keterangan`
                    enum('Hadir','OFF','Sakit','Cuti','Izin','Alpha','Pending Dinas','Dinas Luar','Menikah','Menikahkan Anak','Melahirkan','Duka Cita')
                    COLLATE utf8mb4_general_ci DEFAULT NULL";
            if ($conn->query($sql)) {
                $log->ok("Enum <code>absensi.keterangan</code> diperluas dengan 4 jenis cuti khusus.");
            } else {
                $log->error("Gagal memperluas enum absensi.keterangan: " . $conn->error);
            }
        } else {
            $log->skip("Enum <code>absensi.keterangan</code> sudah mendukung cuti khusus.");
        }

        // 3. absensi.izin_pulang_cepat + alasan_pulang_cepat
        if (!kolomAda($conn, 'absensi', 'izin_pulang_cepat')) {
            $sql = "ALTER TABLE `absensi` ADD `izin_pulang_cepat`
                    enum('Pending','Disetujui','Ditolak') COLLATE utf8mb4_general_ci DEFAULT NULL
                    COMMENT 'Status izin pulang lebih awal hari ini, wajib Disetujui sebelum absen pulang diterima sebelum jam pulang shift'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>absensi.izin_pulang_cepat</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom absensi.izin_pulang_cepat: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>absensi.izin_pulang_cepat</code> sudah ada.");
        }

        if (!kolomAda($conn, 'absensi', 'alasan_pulang_cepat')) {
            $sql = "ALTER TABLE `absensi` ADD `alasan_pulang_cepat` varchar(255)
                    COLLATE utf8mb4_general_ci DEFAULT NULL
                    COMMENT 'Alasan izin pulang lebih awal hari ini'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>absensi.alasan_pulang_cepat</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom absensi.alasan_pulang_cepat: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>absensi.alasan_pulang_cepat</code> sudah ada.");
        }
    },
];
