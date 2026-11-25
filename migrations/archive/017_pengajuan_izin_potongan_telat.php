<?php
return [
    'name' => 'Penanda potongan keterlambatan untuk izin setengah hari di atas batas bulanan (pengajuan_izin.kena_potongan_telat)',
    'up' => function ($conn, MigrationLog $log) {
        // Izin setengah hari di atas batas 3x/bulan tetap boleh diajukan, tapi admin/SPV
        // dapat menandainya saat review. Bila ditandai, proses_absen.php mencatat hari itu
        // 'Terlambat' pada batas maksimal (lihat keterlambatan_functions.php) sehingga slip
        // gaji menerapkan potongan keterlambatan bertingkat yang sudah ada - tanpa mengubah
        // rumus payroll.
        if (!kolomAda($conn, 'pengajuan_izin', 'kena_potongan_telat')) {
            $sql = "ALTER TABLE `pengajuan_izin` ADD `kena_potongan_telat` TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT '1 = ditandai reviewer: hari izin setengah hari ini dikenai potongan keterlambatan' AFTER `sesi_setengah_hari`";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>pengajuan_izin.kena_potongan_telat</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom pengajuan_izin.kena_potongan_telat: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>pengajuan_izin.kena_potongan_telat</code> sudah ada.");
        }
    },
];
