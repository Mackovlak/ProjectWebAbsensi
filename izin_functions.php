<?php
/**
 * ==========================================
 * Helper Pengajuan Izin / Cuti / Dinas Luar
 * ==========================================
 * Di-include otomatis lewat config.php sehingga tersedia di semua halaman.
 *
 * Aturan bisnis inti:
 * - Satu pengajuan = satu rentang tanggal (misal 2 s/d 5), bukan per hari.
 * - Baris `absensi` baru dibuat SAAT DISETUJUI, supaya seluruh laporan/rekap/
 *   slip gaji yang sudah ada tetap berjalan tanpa perubahan query.
 * - Hari Minggu dan tanggal yang sudah punya baris absensi (libur bersama,
 *   atau sudah terlanjur absen) tidak dihitung dan tidak dibuatkan baris.
 * - Kuota tahunan dipotong oleh Cuti, Sakit, dan Izin. Dinas Luar tidak.
 */

// Jenis pengajuan yang memotong jatah cuti tahunan.
// Dinas Luar sengaja dikecualikan: itu tugas kantor, bukan hak istirahat.
define('IZIN_JENIS_POTONG_KUOTA', ['Cuti', 'Sakit', 'Izin']);
define('IZIN_JENIS_VALID', ['Cuti', 'Sakit', 'Izin', 'Dinas Luar']);
define('IZIN_JATAH_DEFAULT', 12);

/**
 * Apakah jenis pengajuan ini memotong kuota tahunan?
 *
 * Pengecualian: Sakit yang dilampiri bukti (surat dokter) tidak memotong
 * kuota sama sekali - hanya jenis Sakit yang diberi pengecualian ini, sesuai
 * kebijakan bahwa sakit dengan bukti resmi bukan hak istirahat yang dijatah.
 * Cuti dan Izin tetap memotong kuota walau ada lampiran.
 */
function izinPotongKuota($jenis, $ada_bukti_sakit = false) {
    if ($jenis === 'Sakit' && $ada_bukti_sakit) {
        return false;
    }
    return in_array($jenis, IZIN_JENIS_POTONG_KUOTA, true);
}

/**
 * Keterangan absensi yang dipakai saat pengajuan disetujui.
 * Dinas Luar tidak dimaterialisasi (karyawan tetap absen manual di hari-H),
 * jadi fungsi ini mengembalikan null untuk jenis tersebut.
 */
function izinKeteranganAbsensi($jenis) {
    switch ($jenis) {
        case 'Cuti':  return 'Cuti';
        case 'Sakit': return 'Sakit';
        case 'Izin':  return 'Izin';
        default:      return null; // Dinas Luar
    }
}

/**
 * Hitung rincian hari untuk sebuah rentang pengajuan.
 *
 * Hanya hari kerja normal perusahaan (lihat `hari_kerja` di system_settings,
 * default Senin-Jumat) yang memotong kuota dan dibuatkan baris absensi.
 * Hari lembur (Sabtu), libur mingguan (Minggu), hari libur nasional, dan
 * tanggal yang sudah punya baris absensi semuanya dilewati.
 *
 * Mengembalikan:
 *  - hari_kalender     : jumlah hari total dalam rentang
 *  - hari_efektif      : jumlah hari yang dihitung kuota & dibuatkan absensi
 *  - tanggal_efektif   : daftar tanggal (Y-m-d) yang efektif
 *  - tanggal_dilewati  : daftar ['tanggal' => ..., 'alasan' => ...] yang dilewati
 */
function hitungHariIzin($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai) {
    $hasil = [
        'hari_kalender'    => 0,
        'hari_efektif'     => 0,
        'tanggal_efektif'  => [],
        'tanggal_dilewati' => [],
    ];

    $start_ts = strtotime($tanggal_mulai);
    $end_ts   = strtotime($tanggal_selesai);
    if ($start_ts === false || $end_ts === false || $start_ts > $end_ts) {
        return $hasil;
    }

    // Kebijakan hari kerja + hari libur diambil sekali di luar loop harian.
    $hari_kerja = getHariKerja($conn);

    $id_cabang = null;
    $stmt_cbg = $conn->prepare("SELECT id_cabang FROM karyawan WHERE id_karyawan = ?");
    if ($stmt_cbg) {
        $stmt_cbg->bind_param("s", $id_karyawan);
        $stmt_cbg->execute();
        $row_cbg = $stmt_cbg->get_result()->fetch_assoc();
        $stmt_cbg->close();
        if ($row_cbg) {
            $id_cabang = (int)$row_cbg['id_cabang'];
        }
    }
    $daftar_libur = getHariLibur($conn, $tanggal_mulai, $tanggal_selesai, $id_cabang);

    // Ambil sekali semua tanggal yang sudah punya baris absensi dalam rentang,
    // supaya tidak melakukan query di dalam loop harian.
    $sudah_terisi = [];
    $stmt = $conn->prepare("SELECT tanggal, keterangan FROM absensi
                            WHERE id_karyawan = ? AND tanggal BETWEEN ? AND ?");
    $stmt->bind_param("sss", $id_karyawan, $tanggal_mulai, $tanggal_selesai);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sudah_terisi[$row['tanggal']] = $row['keterangan'];
    }
    $stmt->close();

    for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
        $tgl = date('Y-m-d', $ts);
        $hasil['hari_kalender']++;

        // Bukan hari kerja normal (Sabtu = hari lembur, Minggu = libur mingguan).
        $nomor_hari = (int)date('N', $ts);
        if (!in_array($nomor_hari, $hari_kerja, true)) {
            $hasil['tanggal_dilewati'][] = [
                'tanggal' => $tgl,
                'alasan'  => 'Bukan hari kerja (' . KALENDER_NAMA_HARI[$nomor_hari] . ')',
            ];
            continue;
        }

        // Hari libur nasional / cuti bersama / libur perusahaan.
        if (isset($daftar_libur[$tgl])) {
            $hasil['tanggal_dilewati'][] = [
                'tanggal' => $tgl,
                'alasan'  => 'Hari libur: ' . $daftar_libur[$tgl]['nama'],
            ];
            continue;
        }

        // Sudah ada baris absensi (libur bersama, OFF, atau sudah absen).
        if (isset($sudah_terisi[$tgl])) {
            $ket = $sudah_terisi[$tgl] ?: 'sudah tercatat';
            $hasil['tanggal_dilewati'][] = ['tanggal' => $tgl, 'alasan' => 'Sudah ada absensi (' . $ket . ')'];
            continue;
        }

        $hasil['tanggal_efektif'][] = $tgl;
        $hasil['hari_efektif']++;
    }

    return $hasil;
}

/**
 * Jatah cuti tahunan seorang karyawan (default 12 bila kolom belum diisi).
 */
function getJatahCuti($conn, $id_karyawan) {
    $stmt = $conn->prepare("SELECT jatah_cuti FROM karyawan WHERE id_karyawan = ?");
    $stmt->bind_param("s", $id_karyawan);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['jatah_cuti'] === null) {
        return IZIN_JATAH_DEFAULT;
    }
    return (int)$row['jatah_cuti'];
}

/**
 * Ringkasan kuota izin seorang karyawan pada satu tahun.
 * Tahun kuota ditentukan oleh tanggal_mulai pengajuan.
 */
function getRingkasanKuotaIzin($conn, $id_karyawan, $tahun = null) {
    $tahun = $tahun ?: (int)date('Y');
    $jatah = getJatahCuti($conn, $id_karyawan);

    $stmt = $conn->prepare("SELECT
            COALESCE(SUM(CASE WHEN status = 'Disetujui' THEN jumlah_hari_kerja ELSE 0 END), 0) AS terpakai,
            COALESCE(SUM(CASE WHEN status = 'Pending'   THEN jumlah_hari_kerja ELSE 0 END), 0) AS tertahan
        FROM pengajuan_izin
        WHERE id_karyawan = ? AND potong_kuota = 1 AND YEAR(tanggal_mulai) = ?");
    $stmt->bind_param("si", $id_karyawan, $tahun);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $terpakai = (int)($row['terpakai'] ?? 0);
    $tertahan = (int)($row['tertahan'] ?? 0);

    return [
        'tahun'     => $tahun,
        'jatah'     => $jatah,
        'terpakai'  => $terpakai,
        'tertahan'  => $tertahan,               // masih menunggu persetujuan
        'sisa'      => max(0, $jatah - $terpakai),
        // Sisa yang benar-benar bisa diajukan lagi: pending ikut menahan kuota
        // supaya karyawan tidak mengajukan 12 hari dua kali sekaligus.
        'tersedia'  => max(0, $jatah - $terpakai - $tertahan),
    ];
}

/**
 * Cek pengajuan lain yang rentang tanggalnya bertabrakan.
 * Hanya status Pending & Disetujui yang dianggap memblokir.
 */
function cekTumpangTindihIzin($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai, $exclude_id = 0) {
    $stmt = $conn->prepare("SELECT id, jenis, tanggal_mulai, tanggal_selesai, status
                            FROM pengajuan_izin
                            WHERE id_karyawan = ?
                              AND status IN ('Pending','Disetujui')
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
 * Buat baris absensi untuk pengajuan yang disetujui.
 * Dipanggil di dalam transaksi milik pemanggil.
 *
 * Mengembalikan jumlah baris yang berhasil dibuat.
 * Melempar Exception bila ada insert yang gagal, supaya pemanggil bisa rollback.
 */
function materialisasiIzin($conn, $pengajuan) {
    $keterangan = izinKeteranganAbsensi($pengajuan['jenis']);
    if ($keterangan === null) {
        return 0; // Dinas Luar: absensi tetap dibuat oleh karyawan di hari-H
    }

    $rincian = hitungHariIzin(
        $conn,
        $pengajuan['id_karyawan'],
        $pengajuan['tanggal_mulai'],
        $pengajuan['tanggal_selesai']
    );

    if (empty($rincian['tanggal_efektif'])) {
        return 0;
    }

    $waktu_alasan = date('Y-m-d H:i:s');
    $alasan = $pengajuan['keperluan'];
    $id_pengajuan = (int)$pengajuan['id'];

    // is_manual_entry sengaja 0: baris ini lahir dari approval, bukan bulk entry
    // admin, sehingga tidak boleh ikut terhapus oleh hapus_libur_bersama.php.
    $stmt = $conn->prepare(
        "INSERT INTO absensi (id_karyawan, tanggal, keterangan, alasan, waktu_alasan, is_manual_entry, id_pengajuan)
         VALUES (?, ?, ?, ?, ?, 0, ?)"
    );

    $jumlah = 0;
    foreach ($rincian['tanggal_efektif'] as $tgl) {
        $stmt->bind_param("sssssi", $pengajuan['id_karyawan'], $tgl, $keterangan, $alasan, $waktu_alasan, $id_pengajuan);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Gagal membuat absensi tanggal {$tgl}: " . $conn->error);
        }
        $jumlah++;
    }
    $stmt->close();

    return $jumlah;
}

/**
 * Hapus baris absensi hasil approval sebuah pengajuan.
 * Hanya menghapus hari yang BELUM terpakai (belum ada jam masuk), supaya
 * riwayat kehadiran nyata tidak pernah hilang.
 */
function batalkanMaterialisasiIzin($conn, $id_pengajuan) {
    $stmt = $conn->prepare("DELETE FROM absensi
                            WHERE id_pengajuan = ? AND jam_masuk IS NULL");
    $stmt->bind_param("i", $id_pengajuan);
    $stmt->execute();
    $terhapus = $stmt->affected_rows;
    $stmt->close();
    return $terhapus;
}

/**
 * Ambil pengajuan Dinas Luar yang sudah disetujui dan mencakup tanggal tertentu.
 * Dipakai proses_absen.php untuk melewati validasi geofencing di hari-H.
 */
function getIzinDinasDisetujui($conn, $id_karyawan, $tanggal) {
    $stmt = $conn->prepare("SELECT id, keperluan, tanggal_mulai, tanggal_selesai
                            FROM pengajuan_izin
                            WHERE id_karyawan = ?
                              AND jenis = 'Dinas Luar'
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
 * Cabang yang boleh direview oleh user yang sedang login.
 * - admin & owner : null (artinya semua cabang)
 * - supervisor    : cabang karyawan yang tertaut (users.id_cabang hanya
 *                   fallback untuk akun lama yang belum tertaut)
 */
function getCabangReviewer($conn, $user_id, $role) {
    if ($role === 'admin' || $role === 'owner') {
        return null; // tanpa batasan
    }

    $stmt = $conn->prepare("SELECT u.id_cabang, k.id_cabang AS cabang_karyawan
                            FROM users u
                            LEFT JOIN karyawan k ON u.id_karyawan = k.id_karyawan
                            WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return 0; // tidak ada cabang => tidak melihat apa pun

    if (!empty($row['cabang_karyawan'])) return (int)$row['cabang_karyawan'];
    if (!empty($row['id_cabang']))       return (int)$row['id_cabang'];

    return 0;
}

/**
 * Hitung pengajuan Pending yang menjadi tanggung jawab reviewer ini.
 * Dipakai untuk badge notifikasi di header.
 */
function hitungPendingIzin($conn, $id_cabang = null) {
    if ($id_cabang === null) {
        $res = $conn->query("SELECT COUNT(*) AS jml FROM pengajuan_izin WHERE status = 'Pending'");
        return $res ? (int)$res->fetch_assoc()['jml'] : 0;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM pengajuan_izin p
                            JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                            WHERE p.status = 'Pending' AND k.id_cabang = ?");
    $stmt->bind_param("i", $id_cabang);
    $stmt->execute();
    $jml = (int)$stmt->get_result()->fetch_assoc()['jml'];
    $stmt->close();
    return $jml;
}

/**
 * Kelas badge Tailwind untuk status pengajuan.
 */
function badgeStatusIzin($status) {
    switch ($status) {
        case 'Disetujui':
            return 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50';
        case 'Ditolak':
            return 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50';
        case 'Dibatalkan':
            return 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700';
        default: // Pending
            return 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50';
    }
}

/**
 * Kelas badge Tailwind untuk jenis pengajuan.
 */
function badgeJenisIzin($jenis) {
    switch ($jenis) {
        case 'Cuti':
            return 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200 dark:bg-fuchsia-900/30 dark:text-fuchsia-400 dark:border-fuchsia-800/50';
        case 'Sakit':
            return 'bg-orange-100 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800/50';
        case 'Dinas Luar':
            return 'bg-sky-100 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-400 dark:border-sky-800/50';
        default: // Izin
            return 'bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-400 dark:border-indigo-800/50';
    }
}

/**
 * Format rentang tanggal jadi teks Indonesia ringkas.
 * Contoh: "2 - 5 Agustus 2026" atau "30 Juli - 2 Agustus 2026".
 */
function formatRentangTanggal($mulai, $selesai) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $m = strtotime($mulai);
    $s = strtotime($selesai);

    if (date('Y-m-d', $m) === date('Y-m-d', $s)) {
        return date('j', $m) . ' ' . $bulan[(int)date('n', $m)] . ' ' . date('Y', $m);
    }
    if (date('Y-n', $m) === date('Y-n', $s)) {
        return date('j', $m) . ' - ' . date('j', $s) . ' ' . $bulan[(int)date('n', $s)] . ' ' . date('Y', $s);
    }
    if (date('Y', $m) === date('Y', $s)) {
        return date('j', $m) . ' ' . $bulan[(int)date('n', $m)] . ' - ' .
               date('j', $s) . ' ' . $bulan[(int)date('n', $s)] . ' ' . date('Y', $s);
    }
    return date('j', $m) . ' ' . $bulan[(int)date('n', $m)] . ' ' . date('Y', $m) . ' - ' .
           date('j', $s) . ' ' . $bulan[(int)date('n', $s)] . ' ' . date('Y', $s);
}
?>
