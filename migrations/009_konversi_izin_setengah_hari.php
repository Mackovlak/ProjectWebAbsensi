<?php
return [
    'name' => 'Konversi Tidak Absen Masuk jadi izin setengah hari - kolom penanda di absensi',
    'up' => function ($conn, MigrationLog $log) {
        // Kolom penanda bahwa satu baris "Tidak Absen Masuk" (Hadir tanpa
        // jam_masuk) sudah dikonversi admin/SPV jadi izin setengah hari,
        // dipakai juga untuk membatasi 3x konversi per bulan per karyawan
        // (lihat izin_functions.php). Sengaja jadi kolom di absensi, bukan
        // baris pengajuan_izin baru - baris absensi hari itu sudah ada
        // (karyawan tetap absen pulang), ini cuma reklasifikasi status
        // hari yang sudah tercatat, bukan pengajuan cuti baru.
        $kolom_baru = [
            ['dikonversi_izin_setengah_hari', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Tidak Absen Masuk yang sudah dikonversi jadi izin setengah hari oleh admin/SPV'"],
            ['dikonversi_oleh', "INT UNSIGNED DEFAULT NULL COMMENT 'users.id admin/SPV yang melakukan konversi'"],
            ['dikonversi_at', "DATETIME DEFAULT NULL"],
        ];

        foreach ($kolom_baru as [$nama, $definisi]) {
            if (!kolomAda($conn, 'absensi', $nama)) {
                $sql = "ALTER TABLE `absensi` ADD `{$nama}` {$definisi}";
                if ($conn->query($sql)) {
                    $log->ok("Kolom <code>absensi.{$nama}</code> berhasil ditambahkan.");
                } else {
                    $log->error("Gagal menambah kolom absensi.{$nama}: " . $conn->error);
                    return;
                }
            } else {
                $log->skip("Kolom <code>absensi.{$nama}</code> sudah ada.");
            }
        }
    },
];
