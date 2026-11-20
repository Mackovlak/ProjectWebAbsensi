<?php
return [
    'name' => 'Izin/Cuti setengah hari lewat pengajuan_izin (kolom setengah_hari, sesi, kuota desimal)',
    'up' => function ($conn, MigrationLog $log) {
        // 1. Penanda pengajuan setengah hari. Satu pengajuan setengah hari
        //    selalu satu tanggal (tanggal_mulai = tanggal_selesai) dan hanya
        //    berlaku untuk Cuti/Izin - divalidasi di proses_pengajuan_izin.php.
        if (!kolomAda($conn, 'pengajuan_izin', 'setengah_hari')) {
            $sql = "ALTER TABLE `pengajuan_izin` ADD `setengah_hari` TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT '1 = pengajuan setengah hari (memotong 0,5 kuota)' AFTER `potong_kuota`";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>pengajuan_izin.setengah_hari</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom pengajuan_izin.setengah_hari: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Kolom <code>pengajuan_izin.setengah_hari</code> sudah ada.");
        }

        // 2. Bagian hari yang diizinkan: masuk siang (pagi libur) atau
        //    pulang awal (siang libur). Dipakai proses_absen.php untuk
        //    membebaskan Terlambat / izin pulang cepat di hari-H.
        if (!kolomAda($conn, 'pengajuan_izin', 'sesi_setengah_hari')) {
            $sql = "ALTER TABLE `pengajuan_izin` ADD `sesi_setengah_hari` ENUM('Masuk Siang','Pulang Awal') DEFAULT NULL
                    COMMENT 'Hanya terisi bila setengah_hari = 1' AFTER `setengah_hari`";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>pengajuan_izin.sesi_setengah_hari</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom pengajuan_izin.sesi_setengah_hari: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Kolom <code>pengajuan_izin.sesi_setengah_hari</code> sudah ada.");
        }

        // 3. Kuota harus bisa menampung 0,5 hari. Data lama (integer) aman
        //    dikonversi: 3 -> 3.0.
        $tipe = definisiKolom($conn, 'pengajuan_izin', 'jumlah_hari_kerja');
        if ($tipe !== null && stripos($tipe, 'decimal') === false) {
            $sql = "ALTER TABLE `pengajuan_izin` MODIFY `jumlah_hari_kerja` DECIMAL(4,1) NOT NULL DEFAULT 0.0
                    COMMENT 'Hari efektif yang memotong kuota (bisa 0,5 untuk setengah hari)'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>pengajuan_izin.jumlah_hari_kerja</code> diubah ke DECIMAL(4,1).");
            } else {
                $log->error("Gagal mengubah pengajuan_izin.jumlah_hari_kerja: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Kolom <code>pengajuan_izin.jumlah_hari_kerja</code> sudah desimal.");
        }
    },
];
