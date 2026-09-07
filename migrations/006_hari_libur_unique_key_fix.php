<?php
return [
    'name' => 'Perbaikan unique key hari_libur (NULL id_cabang tidak pernah dianggap duplikat oleh MySQL)',
    'up' => function ($conn, MigrationLog $log) {
        if (!tabelAda($conn, 'hari_libur')) {
            $log->error("Tabel <code>hari_libur</code> belum ada - migrasi 004 seharusnya sudah membuatnya.");
            return;
        }

        // 1. Bersihkan duplikat (tanggal, id_cabang) yang mungkin sudah
        //    terlanjur masuk sebelum unique key ini diperbaiki - simpan baris
        //    dengan id terkecil, hapus sisanya.
        $sql_cek_dup = "SELECT tanggal, id_cabang, COUNT(*) AS jml, MIN(id) AS id_simpan
                        FROM hari_libur
                        GROUP BY tanggal, id_cabang
                        HAVING COUNT(*) > 1";
        $res_dup = $conn->query($sql_cek_dup);
        $total_dihapus = 0;
        if ($res_dup && $res_dup->num_rows > 0) {
            while ($d = $res_dup->fetch_assoc()) {
                if ($d['id_cabang'] === null) {
                    $stmt = $conn->prepare("DELETE FROM hari_libur WHERE tanggal = ? AND id_cabang IS NULL AND id <> ?");
                    $stmt->bind_param("si", $d['tanggal'], $d['id_simpan']);
                } else {
                    $stmt = $conn->prepare("DELETE FROM hari_libur WHERE tanggal = ? AND id_cabang = ? AND id <> ?");
                    $stmt->bind_param("sii", $d['tanggal'], $d['id_cabang'], $d['id_simpan']);
                }
                $stmt->execute();
                $total_dihapus += $stmt->affected_rows;
                $stmt->close();
            }
            $log->warn("Ditemukan &amp; dibersihkan <b>{$total_dihapus}</b> baris duplikat hari libur "
                . "(baris tertua per tanggal dipertahankan).");
        } else {
            $log->skip("Tidak ada duplikat hari libur yang perlu dibersihkan.");
        }

        // 2. Tambah kolom generated id_cabang_key = COALESCE(id_cabang, 0).
        //    0 aman dipakai sebagai penanda "semua cabang" karena cabang.id
        //    AUTO_INCREMENT mulai dari 1, tidak pernah 0.
        if (!kolomAda($conn, 'hari_libur', 'id_cabang_key')) {
            $sql = "ALTER TABLE `hari_libur`
                    ADD COLUMN `id_cabang_key` INT GENERATED ALWAYS AS (COALESCE(`id_cabang`, 0)) STORED
                    COMMENT 'Salinan id_cabang dgn NULL diganti 0, dipakai sbg unique key krn MySQL menganggap NULL != NULL'
                    AFTER `id_cabang`";
            if ($conn->query($sql)) {
                $log->ok("Kolom <code>hari_libur.id_cabang_key</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom id_cabang_key: " . $conn->error);
                return;
            }
        } else {
            $log->skip("Kolom <code>hari_libur.id_cabang_key</code> sudah ada.");
        }

        // 3. Pindahkan unique key dari (tanggal, id_cabang) ke (tanggal, id_cabang_key)
        if (indexAda($conn, 'hari_libur', 'uniq_libur_tanggal_cabang', 'id_cabang_key')) {
            $log->skip("Unique key sudah memakai <code>id_cabang_key</code>.");
        } else {
            $conn->query("ALTER TABLE `hari_libur` DROP INDEX `uniq_libur_tanggal_cabang`");
            $sql = "ALTER TABLE `hari_libur` ADD UNIQUE KEY `uniq_libur_tanggal_cabang` (`tanggal`, `id_cabang_key`)";
            if ($conn->query($sql)) {
                $log->ok("Unique key berhasil dipindah ke <code>(tanggal, id_cabang_key)</code> - "
                    . "duplikat hari libur nasional kini benar-benar dicegah oleh database.");
            } else {
                $log->error("Gagal membuat unique key baru: " . $conn->error);
            }
        }
    },
];
