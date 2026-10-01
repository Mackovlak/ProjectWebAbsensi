<?php
return [
    'name' => 'Penanda & review dinas luar dadakan saat pulang',
    'up' => function ($conn, MigrationLog $log) {
        // Terpisah dari alasan_pulang/foto_pulang (dipakai bersama oleh alur
        // lembur) supaya "apakah baris ini dinas luar dadakan saat pulang"
        // tidak perlu ditebak dari isi alasan_pulang - lihat proses_absen.php.
        $columns = [
            'dinas_pulang_dadakan' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Absen pulang direkam di luar radius kantor karena dinas dadakan, lihat proses_absen.php'",
            'dinas_pulang_ditinjau_oleh' => "INT DEFAULT NULL COMMENT 'users.id admin/supervisor yang menandai sudah ditinjau'",
            'dinas_pulang_ditinjau_at' => "DATETIME DEFAULT NULL",
        ];
        foreach ($columns as $name => $definition) {
            if (kolomAda($conn, 'absensi', $name)) {
                $log->skip("Kolom <code>absensi.$name</code> sudah ada.");
                continue;
            }
            if (!$conn->query("ALTER TABLE `absensi` ADD COLUMN `$name` $definition")) {
                $log->error("Gagal menambah absensi.$name: " . $conn->error);
                return;
            }
            $log->ok("Kolom <code>absensi.$name</code> berhasil ditambahkan.");
        }
    },
];
