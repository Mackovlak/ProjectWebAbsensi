<?php

/**
 * Materialisasi ketidakhadiran karyawan pada akhir hari kerja.
 *
 * Fungsi ini sengaja tidak memakai CURDATE() agar dapat diuji dan, bila cron
 * sempat gagal, dapat dijalankan ulang untuk tanggal tertentu. Operasi insert
 * bersifat idempoten: baris hanya dibuat bila karyawan belum memiliki absensi
 * pada tanggal tersebut.
 */
function catatUnpaidAlphaOtomatis($conn, $tanggal) {
    $tanggal_obj = DateTime::createFromFormat('!Y-m-d', (string)$tanggal);
    if (!$tanggal_obj || $tanggal_obj->format('Y-m-d') !== $tanggal) {
        throw new InvalidArgumentException('Tanggal harus berformat Y-m-d.');
    }
    if ($tanggal > date('Y-m-d')) {
        throw new InvalidArgumentException('Tanggal yang akan ditutup tidak boleh berada di masa depan.');
    }

    $hasil = [
        'tanggal' => $tanggal,
        'hari_kerja' => isHariKerja($conn, $tanggal),
        'kandidat' => 0,
        'libur' => 0,
        'sudah_tercatat' => 0,
        'dibuat' => 0,
    ];

    if (!$hasil['hari_kerja']) {
        return $hasil;
    }

    $stmt_karyawan = $conn->prepare(
        "SELECT id_karyawan, id_cabang
         FROM karyawan
         WHERE status = 'aktif'
           AND DATE(created_at) <= ?
           AND (tanggal_resign IS NULL OR tanggal_resign > ?)"
    );
    if (!$stmt_karyawan) {
        throw new RuntimeException('Gagal menyiapkan daftar karyawan: ' . $conn->error);
    }
    $stmt_karyawan->bind_param('ss', $tanggal, $tanggal);
    if (!$stmt_karyawan->execute()) {
        $pesan = $stmt_karyawan->error;
        $stmt_karyawan->close();
        throw new RuntimeException('Gagal mengambil daftar karyawan: ' . $pesan);
    }
    $daftar_karyawan = $stmt_karyawan->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_karyawan->close();
    $hasil['kandidat'] = count($daftar_karyawan);

    $stmt_insert = $conn->prepare(
        "INSERT INTO absensi
            (id_karyawan, tanggal, keterangan, status_masuk, input_method,
             input_reason, is_manual_entry)
         SELECT ?, ?, 'UNPAID/Alpha', NULL, 'system_auto',
                'Otomatis: tidak ada absensi sampai penutupan hari kerja', 0
         FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM absensi
             WHERE id_karyawan = ? AND tanggal = ?
         )"
    );
    if (!$stmt_insert) {
        throw new RuntimeException(
            'Gagal menyiapkan pencatatan otomatis. Pastikan migration terbaru sudah dijalankan: ' . $conn->error
        );
    }

    $libur_per_cabang = [];
    $conn->begin_transaction();
    try {
        foreach ($daftar_karyawan as $karyawan) {
            $id_cabang = (int)$karyawan['id_cabang'];
            if (!array_key_exists($id_cabang, $libur_per_cabang)) {
                $libur_per_cabang[$id_cabang] = !empty(
                    getHariLibur($conn, $tanggal, $tanggal, $id_cabang)
                );
            }
            if ($libur_per_cabang[$id_cabang]) {
                $hasil['libur']++;
                continue;
            }

            $id_karyawan = $karyawan['id_karyawan'];
            $stmt_insert->bind_param('ssss', $id_karyawan, $tanggal, $id_karyawan, $tanggal);
            if (!$stmt_insert->execute()) {
                // Absensi dapat masuk tepat di antara pengecekan NOT EXISTS dan
                // INSERT. Unique key menjadikannya aman; anggap sudah tercatat.
                if ((int)$stmt_insert->errno === 1062) {
                    $hasil['sudah_tercatat']++;
                    continue;
                }
                throw new RuntimeException(
                    "Gagal mencatat UNPAID/Alpha untuk {$id_karyawan}: " . $stmt_insert->error
                );
            }
            if ($stmt_insert->affected_rows === 1) {
                $hasil['dibuat']++;
            } else {
                $hasil['sudah_tercatat']++;
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $stmt_insert->close();
        throw $e;
    }
    $stmt_insert->close();

    return $hasil;
}
