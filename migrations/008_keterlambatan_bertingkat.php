<?php
return [
    'name' => 'Potongan keterlambatan bertingkat (tiered) - pengaturan global + kolom menit_terlambat',
    'up' => function ($conn, MigrationLog $log) {
        // 1. absensi.menit_terlambat - fakta historis mentah (menit lewat
        // jam_masuk_akhir shift), independen dari tarif. Tarif bisa berubah
        // di kemudian hari tanpa mengubah fakta "hari itu telat berapa menit".
        if (!kolomAda($conn, 'absensi', 'menit_terlambat')) {
            $sql = "ALTER TABLE `absensi` ADD `menit_terlambat` INT UNSIGNED DEFAULT NULL
                    COMMENT 'Menit keterlambatan mentah (lewat jam_masuk_akhir shift) - NULL kalau tidak relevan/Tepat Waktu'";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>absensi.menit_terlambat</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom absensi.menit_terlambat: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Kolom <code>absensi.menit_terlambat</code> sudah ada.");
        }

        // 2. Pengaturan global potongan keterlambatan bertingkat (system_settings)
        // Kebijakan Javag: dispensasi 10 menit dari jam_masuk_akhir shift,
        // lalu potongan flat Rp15.000 untuk 10 menit berikutnya, lalu
        // Rp10.000 tambahan setiap 5 menit sesudahnya, dibekukan (tidak naik
        // lagi) setelah keterlambatan mencapai 3 jam.
        $pengaturan_default = [
            ['keterlambatan_grace_menit', '10', 'Dispensasi (menit) setelah jam_masuk_akhir shift sebelum dianggap Terlambat'],
            ['keterlambatan_tier1_durasi_menit', '10', 'Lama (menit) jendela tarif flat pertama, dihitung setelah masa dispensasi'],
            ['keterlambatan_tier1_rate', '15000', 'Potongan flat (Rp) untuk jendela tarif pertama'],
            ['keterlambatan_tier2_interval_menit', '5', 'Interval (menit) kelipatan potongan tarif kedua'],
            ['keterlambatan_tier2_rate', '10000', 'Potongan (Rp) per kelipatan interval tarif kedua'],
            ['keterlambatan_maks_jam', '3', 'Batas maksimal jam keterlambatan yang dihitung - potongan berhenti naik setelah ini'],
        ];

        if (!tabelAda($conn, 'system_settings')) {
            $log->error("Tabel <code>system_settings</code> belum ada - migrasi 004 seharusnya sudah membuatnya.");
            return;
        }

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
            $log->ok("Pengaturan potongan keterlambatan bertingkat ditambahkan ({$set_baru} key baru): "
                . "dispensasi 10 menit, Rp15.000 flat 10 menit pertama, +Rp10.000/5 menit setelahnya, dibekukan di 3 jam.");
        } else {
            $log->skip("Pengaturan keterlambatan bertingkat sudah ada.");
        }
    },
];
