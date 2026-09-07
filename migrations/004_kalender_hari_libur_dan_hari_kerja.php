<?php
return [
    'name' => 'Kalender: tabel hari_libur, seed hari libur nasional 2026, pengaturan hari kerja/lembur, jabatan.overtime_sabtu',
    'up' => function ($conn, MigrationLog $log) {
        // 1. Tabel hari_libur
        if (!tabelAda($conn, 'hari_libur')) {
            $sql = "CREATE TABLE `hari_libur` (
                `id` int NOT NULL AUTO_INCREMENT,
                `tanggal` date NOT NULL,
                `nama` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
                `jenis` enum('Nasional','Cuti Bersama','Perusahaan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Nasional',
                `id_cabang` int DEFAULT NULL COMMENT 'NULL = berlaku untuk semua cabang',
                `perlu_verifikasi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = tanggal hasil seed otomatis, wajib dicek ke SKB resmi',
                `created_by` int DEFAULT NULL COMMENT 'users.id yang menambahkan',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_libur_tanggal_cabang` (`tanggal`,`id_cabang`),
                KEY `idx_libur_tanggal` (`tanggal`),
                KEY `idx_libur_cabang` (`id_cabang`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if ($conn->query($sql)) {
                $log->ok("Tabel <code>hari_libur</code> berhasil dibuat.");
            } else {
                $log->error("Gagal membuat tabel hari_libur: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Tabel <code>hari_libur</code> sudah ada.");
        }

        // 2. Seed hari libur nasional 2026
        // PENTING: tanggal yang mengikuti kalender Hijriah/Imlek/Saka baru pasti
        // setelah SKB 3 Menteri diterbitkan - semua baris di bawah ditandai
        // perlu_verifikasi = 1 dan bisa diedit/dihapus dari halaman Hari Libur.
        //
        // Catatan: unique key (tanggal, id_cabang) TIDAK mencegah duplikat untuk
        // hari libur nasional (id_cabang selalu NULL, dan MySQL menganggap NULL
        // tidak pernah sama dengan NULL lain) sampai migrasi 006 memperbaikinya.
        // INSERT IGNORE di sini masih dipertahankan sesuai perilaku aslinya -
        // migrasi 006 membersihkan duplikat yang mungkin sempat masuk sebelum
        // unique key-nya diperbaiki.
        $libur_2026 = [
            ['2026-01-01', 'Tahun Baru 2026',                        'Nasional', 0],
            ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW',          'Nasional', 1],
            ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili',         'Nasional', 1],
            ['2026-03-19', 'Hari Suci Nyepi Tahun Baru Saka 1948',   'Nasional', 1],
            ['2026-03-20', 'Hari Raya Idul Fitri 1447 Hijriah',      'Nasional', 1],
            ['2026-03-21', 'Hari Raya Idul Fitri 1447 Hijriah',      'Nasional', 1],
            ['2026-04-03', 'Wafat Isa Al Masih',                     'Nasional', 0],
            ['2026-04-05', 'Kebangkitan Isa Al Masih (Paskah)',      'Nasional', 0],
            ['2026-05-01', 'Hari Buruh Internasional',               'Nasional', 0],
            ['2026-05-14', 'Kenaikan Isa Al Masih',                  'Nasional', 0],
            ['2026-05-27', 'Hari Raya Idul Adha 1447 Hijriah',       'Nasional', 1],
            ['2026-05-31', 'Hari Raya Waisak 2570 BE',               'Nasional', 1],
            ['2026-06-01', 'Hari Lahir Pancasila',                   'Nasional', 0],
            ['2026-06-16', 'Tahun Baru Islam 1448 Hijriah',          'Nasional', 1],
            ['2026-08-17', 'Hari Kemerdekaan Republik Indonesia',    'Nasional', 0],
            ['2026-08-25', 'Maulid Nabi Muhammad SAW',               'Nasional', 1],
            ['2026-12-25', 'Hari Raya Natal',                        'Nasional', 0],
        ];

        if (tabelAda($conn, 'hari_libur')) {
            $stmt = $conn->prepare("INSERT IGNORE INTO hari_libur (tanggal, nama, jenis, id_cabang, perlu_verifikasi)
                                    VALUES (?, ?, ?, NULL, ?)");
            $ditambah = 0;
            foreach ($libur_2026 as $l) {
                $stmt->bind_param("sssi", $l[0], $l[1], $l[2], $l[3]);
                if ($stmt->execute() && $conn->affected_rows > 0) {
                    $ditambah++;
                }
            }
            $stmt->close();

            if ($ditambah > 0) {
                $log->warn("Seed hari libur nasional 2026: <b>{$ditambah}</b> tanggal ditambahkan. "
                    . "Tanggal Hijriah/Imlek/Saka wajib diverifikasi dengan SKB 3 Menteri resmi "
                    . "(lencana <i>Perlu Verifikasi</i> di halaman Hari Libur).");
            } else {
                $log->skip("Seed hari libur 2026 sudah pernah dijalankan, tidak ada tanggal baru.");
            }
        }

        // 3. Pengaturan hari kerja & hari lembur
        $pengaturan_default = [
            ['hari_kerja',     '1,2,3,4,5', 'Hari kerja normal perusahaan (1=Senin ... 7=Minggu)'],
            ['hari_overtime',  '6',         'Hari yang dihitung sebagai hari lembur/overtime, bukan hari kerja normal'],
        ];

        if (!tabelAda($conn, 'system_settings')) {
            $sql = "CREATE TABLE `system_settings` (
                `id` int NOT NULL AUTO_INCREMENT,
                `setting_key` varchar(50) NOT NULL,
                `setting_value` text NOT NULL,
                `description` text,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            if ($conn->query($sql)) {
                $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                foreach ($pengaturan_default as $s) {
                    $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
                    $stmt->execute();
                }
                $stmt->close();
                $log->ok("Tabel <code>system_settings</code> dibuat beserta pengaturan hari kerja Senin&ndash;Jumat.");
            } else {
                $log->error("Gagal membuat tabel system_settings: " . $conn->error);
            }
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            $set_baru = 0;
            foreach ($pengaturan_default as $s) {
                $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
                if ($stmt->execute() && $conn->affected_rows > 0) {
                    $set_baru++;
                }
            }
            $stmt->close();

            if ($set_baru > 0) {
                $log->ok("Pengaturan hari kerja ditambahkan: Senin&ndash;Jumat hari kerja, Sabtu hari lembur, Minggu libur.");
            } else {
                $log->skip("Pengaturan <code>hari_kerja</code>/<code>hari_overtime</code> sudah ada.");
            }
        }

        // 4. jabatan.overtime_sabtu
        if (!kolomAda($conn, 'jabatan', 'overtime_sabtu')) {
            $sql = "ALTER TABLE `jabatan` ADD `overtime_sabtu` tinyint(1) NOT NULL DEFAULT '0'
                    COMMENT '1 = jabatan ini bisa ditugaskan lembur hari Sabtu'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>jabatan.overtime_sabtu</code> ditambahkan (default nonaktif untuk semua jabatan).");
            } else {
                $log->error("Gagal menambah kolom jabatan.overtime_sabtu: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>jabatan.overtime_sabtu</code> sudah ada.");
        }
    },
];
