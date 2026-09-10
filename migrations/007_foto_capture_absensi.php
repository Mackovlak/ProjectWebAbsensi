<?php
return [
    'name' => 'Bukti capture kamera masuk dan pulang (penyimpanan privat)',
    'up' => function ($conn, MigrationLog $log) {
        if (!tabelAda($conn, 'absensi')) {
            $log->error('Tabel absensi belum tersedia.');
            return;
        }
        if (tabelAda($conn, 'absensi_capture')) {
            $log->skip('Tabel absensi_capture sudah ada.');
            return;
        }
        // Separate from absensi so SELECT a.* never loads image bytes.
        if (!$conn->query("CREATE TABLE absensi_capture (
            id_absensi INT NOT NULL,
            jenis ENUM('masuk', 'pulang') NOT NULL,
            foto MEDIUMBLOB NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_absensi, jenis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
            $log->error('Gagal membuat absensi_capture: ' . $conn->error);
            return;
        }
        $log->ok('Tabel absensi_capture berhasil dibuat; data absensi lama tidak diubah.');
    },
];
