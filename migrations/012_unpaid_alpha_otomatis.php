<?php
return [
    'name' => 'Status UNPAID/Alpha dan sumber absensi otomatis',
    'up' => function ($conn, MigrationLog $log) {
        $ubahEnum = function ($tabel, $kolom, $nilaiLama, $nilaiBaru, $default = 'DEFAULT NULL') use ($conn, $log) {
            if (!tabelAda($conn, $tabel) || !kolomAda($conn, $tabel, $kolom)) {
                $log->skip("Kolom <code>{$tabel}.{$kolom}</code> tidak tersedia.");
                return true;
            }

            $tipe = definisiKolom($conn, $tabel, $kolom);
            preg_match_all("/'((?:''|[^'])*)'/", (string)$tipe, $matches);
            $nilai = array_map(function ($item) {
                return str_replace("''", "'", $item);
            }, $matches[1]);

            if (!in_array($nilaiBaru, $nilai, true)) {
                $nilai[] = $nilaiBaru;
            }
            $enumSementara = implode(',', array_map(function ($item) use ($conn) {
                return "'" . $conn->real_escape_string($item) . "'";
            }, $nilai));
            $sql = "ALTER TABLE `{$tabel}` MODIFY `{$kolom}` enum({$enumSementara}) COLLATE utf8mb4_general_ci {$default}";
            if (!$conn->query($sql)) {
                $log->error("Gagal menambah nilai {$nilaiBaru} ke {$tabel}.{$kolom}: " . $conn->error);
                return false;
            }

            $stmt = $conn->prepare("UPDATE `{$tabel}` SET `{$kolom}` = ? WHERE `{$kolom}` = ?");
            $stmt->bind_param('ss', $nilaiBaru, $nilaiLama);
            if (!$stmt->execute()) {
                $log->error("Gagal mengubah data {$nilaiLama} pada {$tabel}: " . $stmt->error);
                $stmt->close();
                return false;
            }
            $jumlah = $stmt->affected_rows;
            $stmt->close();

            $nilai = array_values(array_filter($nilai, function ($item) use ($nilaiLama) {
                return $item !== $nilaiLama;
            }));
            $enumFinal = implode(',', array_map(function ($item) use ($conn) {
                return "'" . $conn->real_escape_string($item) . "'";
            }, $nilai));
            $sql = "ALTER TABLE `{$tabel}` MODIFY `{$kolom}` enum({$enumFinal}) COLLATE utf8mb4_general_ci {$default}";
            if (!$conn->query($sql)) {
                $log->error("Gagal merapikan enum {$tabel}.{$kolom}: " . $conn->error);
                return false;
            }

            $log->ok("Status <code>{$nilaiLama}</code> pada {$tabel} diubah menjadi <code>{$nilaiBaru}</code> ({$jumlah} baris).");
            return true;
        };

        if (!$ubahEnum('absensi', 'keterangan', 'Alpha', 'UNPAID/Alpha')) {
            return;
        }
        if (tabelAda($conn, 'absensi_archive')
            && !$ubahEnum('absensi_archive', 'keterangan', 'Alpha', 'UNPAID/Alpha', "NOT NULL DEFAULT 'Hadir'")) {
            return;
        }

        if (!enumMengandung($conn, 'absensi', 'input_method', 'system_auto')) {
            $tipe = definisiKolom($conn, 'absensi', 'input_method');
            preg_match_all("/'((?:''|[^'])*)'/", (string)$tipe, $matches);
            $nilai = $matches[1];
            $nilai[] = 'system_auto';
            $enum = implode(',', array_map(function ($item) use ($conn) {
                return "'" . $conn->real_escape_string($item) . "'";
            }, array_unique($nilai)));
            $sql = "ALTER TABLE `absensi` MODIFY `input_method` enum({$enum}) COLLATE utf8mb4_general_ci DEFAULT 'qr_scan'";
            if (!$conn->query($sql)) {
                $log->error('Gagal menambah sumber system_auto: ' . $conn->error);
                return;
            }
            $log->ok('Sumber absensi <code>system_auto</code> berhasil ditambahkan.');
        } else {
            $log->skip('Sumber absensi <code>system_auto</code> sudah tersedia.');
        }

        if (!indexAda($conn, 'absensi', 'uniq_absensi_karyawan_tanggal')) {
            $duplikat = $conn->query(
                "SELECT COUNT(*) AS jumlah FROM (
                    SELECT id_karyawan, tanggal
                    FROM absensi
                    GROUP BY id_karyawan, tanggal
                    HAVING COUNT(*) > 1
                ) data_duplikat"
            );
            $jumlah_duplikat = $duplikat ? (int)$duplikat->fetch_assoc()['jumlah'] : -1;
            if ($jumlah_duplikat !== 0) {
                $log->error(
                    $jumlah_duplikat > 0
                        ? "Ditemukan {$jumlah_duplikat} pasangan karyawan/tanggal duplikat. Rapikan data tersebut sebelum menambah unique key."
                        : 'Gagal memeriksa duplikat absensi: ' . $conn->error
                );
                return;
            }

            if (!$conn->query(
                "ALTER TABLE `absensi`
                 ADD UNIQUE KEY `uniq_absensi_karyawan_tanggal` (`id_karyawan`, `tanggal`)"
            )) {
                $log->error('Gagal menambah unique key absensi harian: ' . $conn->error);
                return;
            }
            $log->ok('Unique key satu absensi per karyawan per tanggal berhasil ditambahkan.');
        } else {
            $log->skip('Unique key absensi harian sudah tersedia.');
        }
    },
];
