<?php
return [
    'name' => 'Fitur absen onsite (karyawan lapangan tanpa validasi lokasi/GPS)',
    'up' => function ($conn, MigrationLog $log) {
        // 1. karyawan.mode_absen: penanda permanen per-karyawan, diatur admin
        //    lewat data_karyawan.php - bukan per-pengajuan seperti Dinas Luar,
        //    karena karyawan onsite berpindah lokasi setiap hari tanpa pola
        //    yang bisa diajukan lewat pengajuan_izin.
        if (!kolomAda($conn, 'karyawan', 'mode_absen')) {
            $sql = "ALTER TABLE `karyawan` ADD `mode_absen`
                    enum('tetap','onsite') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'tetap'
                    COMMENT 'tetap = absen Hadir wajib divalidasi lokasi cabang; onsite = lolos validasi lokasi (kerja lapangan/berpindah)'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>karyawan.mode_absen</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom karyawan.mode_absen: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Kolom <code>karyawan.mode_absen</code> sudah ada.");
        }

        // 2. absensi.is_onsite: snapshot per-baris, bukan dibaca ulang dari
        //    karyawan.mode_absen - supaya histori tetap benar walau status
        //    onsite karyawan diubah admin di kemudian hari. keterangan tetap
        //    'Hadir' (bukan nilai enum baru) supaya seluruh kode lain yang
        //    membandingkan keterangan = 'Hadir' (keterlambatan, lembur,
        //    payroll, statistik, export) tidak perlu diubah sama sekali.
        if (!kolomAda($conn, 'absensi', 'is_onsite')) {
            $sql = "ALTER TABLE `absensi` ADD `is_onsite` TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Absen Hadir ini melewati validasi lokasi cabang karena karyawan berstatus onsite saat check-in'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>absensi.is_onsite</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom absensi.is_onsite: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>absensi.is_onsite</code> sudah ada.");
        }
    },
];
