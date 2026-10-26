<?php
/**
 * ==========================================
 * Helper Pengajuan Izin Lembur (hari kerja)
 * ==========================================
 * Di-include otomatis lewat config.php.
 *
 * Beda dari pengajuan_izin: ini bukan hak istirahat (tidak memotong
 * jatah_cuti), cuma izin dari SPV/admin untuk boleh lembur di luar jam
 * pulang shift pada hari kerja normal (Senin-Jumat, bukan hari lembur
 * mingguan seperti Sabtu yang sudah punya mekanisme sendiri lewat
 * getLemburHariSabtu()). Jam lembur DIHITUNG, bukan diajukan di muka -
 * karyawan/SPV cuma mengajukan/menyetujui IZINNYA (rentang tanggal +
 * keperluan), jam sebenarnya dihitung nanti dari selisih jam_pulang aktual
 * vs jam pulang shift saat slip gaji dibuat.
 */

define('LEMBUR_STATUS_VALID', ['Pending', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Menunggu Karyawan', 'Ditolak Karyawan', 'Kedaluwarsa']);

/*
 * Selain diajukan karyawan (sumber = 'Pengajuan', alur Pending -> Disetujui/
 * Ditolak oleh atasan), lembur juga bisa DITUGASKAN atasan (sumber =
 * 'Penugasan'): SPV/admin membuat baris berstatus 'Menunggu Karyawan', lalu
 * karyawan menerima (-> 'Disetujui') atau menolak dengan alasan (-> 'Ditolak
 * Karyawan'). Penugasan yang diterima sengaja berstatus 'Disetujui' yang sama
 * supaya slip gaji/kalender/laporan tidak perlu jalur baru. Penugasan yang
 * belum direspon sampai tanggal mulainya lewat menjadi 'Kedaluwarsa'.
 */

/**
 * Hitung pengajuan lembur Pending yang menjadi tanggung jawab reviewer.
 * null berarti semua cabang (admin/owner); id cabang membatasi supervisor.
 */
function hitungPendingLembur($conn, $id_cabang = null) {
    if ($id_cabang === null) {
        $res = $conn->query("SELECT COUNT(*) AS jml FROM pengajuan_lembur WHERE status = 'Pending'");
        return $res ? (int)$res->fetch_assoc()['jml'] : 0;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM pengajuan_lembur p
                            JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                            WHERE p.status = 'Pending' AND k.id_cabang = ?");
    $stmt->bind_param("i", $id_cabang);
    $stmt->execute();
    $jml = (int)$stmt->get_result()->fetch_assoc()['jml'];
    $stmt->close();
    return $jml;
}

/**
 * Tandai penugasan yang tidak direspon karyawan sampai tanggal mulainya lewat
 * sebagai 'Kedaluwarsa'. Dipanggil malas (saat halaman lembur dibuka / aksi
 * diproses) karena aplikasi tidak punya scheduler; hari H sendiri masih boleh
 * diterima karena lembur biasanya baru mulai sore hari.
 */
function kedaluwarsakanPenugasanLembur($conn) {
    $conn->query("UPDATE pengajuan_lembur SET status = 'Kedaluwarsa'
                  WHERE sumber = 'Penugasan' AND status = 'Menunggu Karyawan'
                    AND tanggal_mulai < CURDATE()");
}

/**
 * Jumlah penugasan lembur yang masih menunggu jawaban karyawan ini - untuk
 * badge di menu "Pengajuan Lembur" / "Lembur Saya".
 */
function hitungPenugasanMenunggu($conn, $id_karyawan) {
    if (empty($id_karyawan)) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM pengajuan_lembur
                            WHERE id_karyawan = ? AND sumber = 'Penugasan'
                              AND status = 'Menunggu Karyawan' AND tanggal_mulai >= CURDATE()");
    $stmt->bind_param("s", $id_karyawan);
    $stmt->execute();
    $jml = (int)$stmt->get_result()->fetch_assoc()['jml'];
    $stmt->close();
    return $jml;
}

function badgeStatusLembur($status) {
    switch ($status) {
        case 'Disetujui':         return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50';
        case 'Ditolak':
        case 'Ditolak Karyawan':  return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50';
        case 'Dibatalkan':
        case 'Kedaluwarsa':       return 'bg-slate-100 text-slate-600 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600';
        case 'Menunggu Karyawan': return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-400 dark:border-sky-800/50';
        default:                  return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50';
    }
}

/**
 * Teks "17:00 - 20:00" untuk jendela jam penugasan, atau '' bila tidak ada.
 */
function formatJendelaJamLembur($jam_mulai, $jam_selesai) {
    if (empty($jam_mulai) || empty($jam_selesai)) {
        return '';
    }
    return substr($jam_mulai, 0, 5) . ' - ' . substr($jam_selesai, 0, 5);
}

/**
 * Cek pengajuan lembur lain yang rentang tanggalnya bertabrakan (Pending/
 * Disetujui/Menunggu Karyawan dianggap memblokir) - sama seperti
 * cekTumpangTindihIzin().
 */
function cekTumpangTindihLembur($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai, $exclude_id = 0) {
    $stmt = $conn->prepare("SELECT id, tanggal_mulai, tanggal_selesai, status
                            FROM pengajuan_lembur
                            WHERE id_karyawan = ?
                              AND status IN ('Pending','Disetujui','Menunggu Karyawan')
                              AND id <> ?
                              AND tanggal_mulai <= ?
                              AND tanggal_selesai >= ?
                            LIMIT 1");
    $stmt->bind_param("siss", $id_karyawan, $exclude_id, $tanggal_selesai, $tanggal_mulai);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Ambil pengajuan lembur yang sudah disetujui dan mencakup tanggal tertentu.
 * Dipakai untuk menandai di UI (histori/kalender) bahwa hari itu punya izin
 * lembur aktif, mirip getIzinDinasDisetujui().
 */
function getLemburDisetujui($conn, $id_karyawan, $tanggal) {
    $stmt = $conn->prepare("SELECT id, keperluan, tanggal_mulai, tanggal_selesai
                            FROM pengajuan_lembur
                            WHERE id_karyawan = ?
                              AND status = 'Disetujui'
                              AND ? BETWEEN tanggal_mulai AND tanggal_selesai
                            LIMIT 1");
    $stmt->bind_param("ss", $id_karyawan, $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Jam lembur hari kerja yang berhak ditambahkan ke slip gaji periode ini -
 * HANYA untuk tanggal yang (a) tercakup pengajuan_lembur berstatus Disetujui,
 * (b) hari kerja normal (bukan Sabtu/hari lembur mingguan - itu jalur
 * terpisah lewat getLemburHariSabtu()), dan (c) jam_pulang aktualnya
 * melewati jam pulang shift. Jamnya = selisih jam_pulang aktual - jam
 * pulang shift, dibulatkan ke 0,5 jam terdekat (konsisten dengan
 * hitungJamKerja()). Untuk penugasan yang punya jendela jam (jam_mulai/
 * jam_selesai), hitungan dimulai paling cepat dari jam_mulai dan DIPOTONG di
 * jam_selesai - pulang lebih larut dari yang ditugaskan tidak ikut dibayar.
 *
 * Cuma SARAN (dipasang tombol "Tambahkan" di slip_gaji_form.php, sama
 * seperti lembur Sabtu) - tidak pernah otomatis menimpa overtime_jam yang
 * sudah tersimpan.
 */
function hitungJamLemburDisetujui($conn, $id_karyawan, $bulan, $tahun) {
    $hasil = ['total_jam' => 0.0, 'rincian' => []];

    $hari_kerja = getHariKerja($conn);

    $stmt = $conn->prepare("SELECT DISTINCT a.id, a.tanggal, a.jam_masuk, a.jam_pulang, k.id_cabang,
                                   p.jam_mulai AS lembur_jam_mulai, p.jam_selesai AS lembur_jam_selesai
                            FROM absensi a
                            JOIN karyawan k ON k.id_karyawan = a.id_karyawan
                            JOIN pengajuan_lembur p ON p.id_karyawan = a.id_karyawan
                                AND a.tanggal BETWEEN p.tanggal_mulai AND p.tanggal_selesai
                                AND p.status = 'Disetujui'
                            WHERE a.id_karyawan = ?
                              AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
                              AND a.keterangan IN ('Hadir', 'Dinas Luar')
                              AND a.jam_masuk IS NOT NULL
                              AND a.jam_pulang IS NOT NULL AND a.jam_pulang <> '00:00:00'
                            ORDER BY a.tanggal ASC");
    $stmt->bind_param("sii", $id_karyawan, $bulan, $tahun);
    $stmt->execute();
    $res = $stmt->get_result();
    $baris = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($baris as $a) {
        $nomor_hari = (int)date('N', strtotime($a['tanggal']));
        if (!in_array($nomor_hari, $hari_kerja, true)) {
            continue; // Sabtu/hari lembur mingguan: sudah dihitung terpisah
        }

        $stmt_shift = $conn->prepare("SELECT jam_pulang FROM jam_kerja WHERE id_cabang = ?
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, ?, jam_masuk_akhir)) ASC LIMIT 1");
        $stmt_shift->bind_param("is", $a['id_cabang'], $a['jam_masuk']);
        $stmt_shift->execute();
        $shift = $stmt_shift->get_result()->fetch_assoc();
        $stmt_shift->close();
        if (!$shift) {
            continue;
        }

        $awal_lembur  = $shift['jam_pulang'];
        $akhir_lembur = $a['jam_pulang'];
        if (!empty($a['lembur_jam_mulai']) && $a['lembur_jam_mulai'] > $awal_lembur) {
            $awal_lembur = $a['lembur_jam_mulai'];
        }
        if (!empty($a['lembur_jam_selesai']) && $a['lembur_jam_selesai'] < $akhir_lembur) {
            $akhir_lembur = $a['lembur_jam_selesai'];
        }

        $selisih_menit = (int)round((strtotime($akhir_lembur) - strtotime($awal_lembur)) / 60);
        if ($selisih_menit <= 0) {
            continue;
        }

        $jam = round(($selisih_menit / 60) * 2) / 2;
        if ($jam <= 0) {
            continue;
        }

        $hasil['total_jam'] += $jam;
        $hasil['rincian'][] = [
            'tanggal'    => $a['tanggal'],
            'hari'       => KALENDER_NAMA_HARI[$nomor_hari],
            'jam_masuk'  => $a['jam_masuk'],
            'jam_pulang' => $a['jam_pulang'],
            'jam_pulang_shift' => $shift['jam_pulang'],
            'jam_lembur_dihitung' => substr($awal_lembur, 0, 5) . '-' . substr($akhir_lembur, 0, 5),
            'jam'        => $jam,
        ];
    }

    return $hasil;
}
