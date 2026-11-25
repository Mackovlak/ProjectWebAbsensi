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
// Menikah / Menikahkan Anak / Melahirkan / Duka Cita juga dikecualikan: cuti
// khusus di luar jatah tahunan (bukan hak istirahat biasa), lama pengajuan
// diserahkan ke keputusan admin/supervisor - tidak ada batas hari otomatis.
define('IZIN_JENIS_POTONG_KUOTA', ['Cuti', 'Sakit', 'Izin']);
define('IZIN_JENIS_KHUSUS', ['Menikah', 'Menikahkan Anak', 'Melahirkan', 'Duka Cita']);
define('IZIN_JENIS_VALID', ['Cuti', 'Sakit', 'Izin', 'Dinas Luar', 'Menikah', 'Menikahkan Anak', 'Melahirkan', 'Duka Cita']);
define('IZIN_JATAH_DEFAULT', 12);

// Jenis yang boleh diajukan setengah hari. Hanya Cuti & Izin: keduanya wajib
// diajukan SEBELUM hari-H, jadi setengah hari selalu terencana (tidak ada
// kasus mundur). Sakit/Dinas/cuti khusus sengaja tidak termasuk.
// Setengah hari TIDAK memotong jatah tahunan (potong_kuota = 0).
define('IZIN_JENIS_SETENGAH_HARI', ['Cuti', 'Izin']);
define('IZIN_SESI_SETENGAH_HARI', ['Masuk Siang', 'Pulang Awal']);

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
        case 'Cuti':            return 'Cuti';
        case 'Sakit':           return 'Sakit';
        case 'Izin':            return 'Izin';
        case 'Menikah':         return 'Menikah';
        case 'Menikahkan Anak': return 'Menikahkan Anak';
        case 'Melahirkan':      return 'Melahirkan';
        case 'Duka Cita':       return 'Duka Cita';
        default:                return null; // Dinas Luar
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

    // float, bukan int: jumlah_hari_kerja berupa DECIMAL (0,5 untuk setengah hari).
    // Setengah hari tidak pernah masuk sini (potong_kuota = 0).
    $terpakai = (float)($row['terpakai'] ?? 0);
    $tertahan = (float)($row['tertahan'] ?? 0);

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
    if (!empty($pengajuan['setengah_hari'])) {
        // Setengah hari: karyawan tetap masuk separuh hari, jadi baris absensi
        // dibuat oleh absen biasa di hari-H (proses_absen.php lalu menandainya
        // setengah hari). Membuat baris 'Cuti' di sini akan menghitung hari
        // itu libur penuh dan memblokir absen.
        return 0;
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
 * Batas WAJAR izin setengah hari per karyawan per bulan kalender (kebijakan
 * perusahaan). Batas ini TIDAK pernah memblokir dan TIDAK ada potongan gaji -
 * yang melewatinya hanya DITANDAI untuk atasan (admin/SPV/owner):
 *  - Pengajuan setengah hari oleh karyawan: ke-4 dst. (urutan: lihat
 *    urutanPengajuanSetengahHariBulan) diberi tanda "melebihi batas" di
 *    kelola_pengajuan_izin.php dan di notifikasi push ke atasan.
 *  - Konversi "Tidak Absen Masuk" oleh admin/SPV: konversi ke-4 dst. tetap
 *    dijalankan, pesannya memberi tanda (konversiTidakAbsenMasukKeSetengahHari).
 * Pulang cepat punya batas sendiri yang terpisah: PULANG_CEPAT_MAKS_PER_BULAN.
 * Keduanya TIDAK memotong jatah_cuti tahunan (12 hari): setengah hari adalah
 * jatah tersendiri, terpisah dari Cuti/Izin/Sakit satu hari penuh.
 */
define('IZIN_SETENGAH_HARI_MAKS_PER_BULAN', 3);

/**
 * Batas WAJAR pulang cepat per karyawan per bulan kalender - terpisah dari
 * batas setengah hari di atas (3 setengah hari + 3 pulang cepat). Sama:
 * hanya tanda untuk atasan, tidak memblokir dan tanpa potongan.
 */
define('PULANG_CEPAT_MAKS_PER_BULAN', 3);

/**
 * Urutan pulang cepat pada tanggal ini di bulannya (1, 2, 3, ...): pulang cepat
 * karyawan yang sama pada tanggal-tanggal SEBELUMNYA di bulan yang sama + 1.
 * Satu tanggal = satu baris absensi, jadi urutan tanggal = urutan kejadian.
 *
 * "Pulang cepat" = izin_pulang_cepat terisi (Pending/Disetujui/Ditolak) DAN
 * jam_pulang sebelum jam pulang shift - atau belum absen pulang sama sekali
 * (pemberitahuan di muka). Baris yang akhirnya pulang normal tidak dihitung.
 * Dipakai untuk menandai yang ke-(PULANG_CEPAT_MAKS_PER_BULAN + 1) dst.
 */
function urutanPulangCepatBulan($conn, $id_karyawan, $tanggal) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS jumlah
        FROM absensi a
        JOIN karyawan k ON a.id_karyawan = k.id_karyawan
        WHERE a.id_karyawan = ?
          AND a.izin_pulang_cepat IS NOT NULL
          AND a.tanggal < ? AND a.tanggal >= DATE_FORMAT(?, '%Y-%m-01')
          AND (
              a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00'
              OR a.jam_pulang < (
                  SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang
                  ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1
              )
          )");
    $stmt->bind_param("sss", $id_karyawan, $tanggal, $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['jumlah'] ?? 0) + 1;
}

/**
 * Berapa kali karyawan ini sudah mengonversi Tidak Absen Masuk jadi izin
 * setengah hari pada bulan/tahun tertentu. Baris absensi hasil pengajuan
 * (id_pengajuan terisi) tidak dihitung - itu jalur pengajuan, bukan konversi.
 */
function hitungKonversiSetengahHariBulanIni($conn, $id_karyawan, $bulan, $tahun) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS jumlah FROM absensi
        WHERE id_karyawan = ? AND dikonversi_izin_setengah_hari = 1 AND id_pengajuan IS NULL
          AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt->bind_param("sii", $id_karyawan, $bulan, $tahun);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['jumlah'] ?? 0);
}

/**
 * Berapa pengajuan setengah hari (Pending/Disetujui) karyawan ini pada
 * bulan/tahun tanggal pelaksanaan. $exclude_id = pengajuan yang sedang
 * ditampilkan, supaya tidak menghitung dirinya sendiri. Hanya informasi -
 * tidak pernah dipakai untuk menolak pengajuan.
 */
function hitungPengajuanSetengahHariBulan($conn, $id_karyawan, $bulan, $tahun, $exclude_id = 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS jumlah FROM pengajuan_izin
        WHERE id_karyawan = ? AND setengah_hari = 1 AND status IN ('Pending','Disetujui')
          AND id <> ? AND MONTH(tanggal_mulai) = ? AND YEAR(tanggal_mulai) = ?");
    $stmt->bind_param("siii", $id_karyawan, $exclude_id, $bulan, $tahun);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['jumlah'] ?? 0);
}

/**
 * Urutan pengajuan setengah hari ini di bulannya (1, 2, 3, ...), berdasarkan
 * urutan pengajuan (id): hanya pengajuan Pending/Disetujui milik karyawan yang
 * sama pada bulan tanggal pelaksanaan yang sama dan diajukan LEBIH DULU yang
 * dihitung. Dipakai untuk menentukan mana yang melewati batas
 * IZIN_SETENGAH_HARI_MAKS_PER_BULAN - bukan jumlah total, supaya lima
 * pengajuan yang masih menunggu tidak semuanya dianggap "melewati batas".
 */
function urutanPengajuanSetengahHariBulan($conn, $id_karyawan, $tanggal_mulai, $id_pengajuan) {
    $bulan = (int)date('n', strtotime($tanggal_mulai));
    $tahun = (int)date('Y', strtotime($tanggal_mulai));
    $stmt = $conn->prepare("SELECT COUNT(*) AS jumlah FROM pengajuan_izin
        WHERE id_karyawan = ? AND setengah_hari = 1 AND status IN ('Pending','Disetujui')
          AND id < ? AND MONTH(tanggal_mulai) = ? AND YEAR(tanggal_mulai) = ?");
    $stmt->bind_param("siii", $id_karyawan, $id_pengajuan, $bulan, $tahun);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['jumlah'] ?? 0) + 1;
}

/**
 * Pengajuan setengah hari yang sudah disetujui dan jatuh pada tanggal ini.
 * Dipakai proses_absen.php di hari-H: bebas Terlambat (Masuk Siang) atau bebas
 * syarat izin pulang cepat (Pulang Awal).
 */
function getIzinSetengahHariDisetujui($conn, $id_karyawan, $tanggal) {
    $stmt = $conn->prepare("SELECT id, jenis, sesi_setengah_hari FROM pengajuan_izin
                            WHERE id_karyawan = ? AND setengah_hari = 1 AND status = 'Disetujui'
                              AND tanggal_mulai = ? LIMIT 1");
    $stmt->bind_param("ss", $id_karyawan, $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Jam pulang shift yang berlaku untuk satu baris absensi: aturan jam_kerja
 * cabang yang jam_masuk_akhir-nya paling dekat ke jam_masuk aktual (pola yang
 * sama dengan perhitungan Terlambat/lembur). null bila cabang tanpa aturan.
 */
function getJamPulangShift($conn, $id_cabang, $jam_masuk) {
    $stmt = $conn->prepare("SELECT jam_pulang FROM jam_kerja WHERE id_cabang = ?
                            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, ?, jam_masuk_akhir)) ASC LIMIT 1");
    $stmt->bind_param("is", $id_cabang, $jam_masuk);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['jam_pulang'] ?? null;
}

/**
 * Pulang cepat = absen pulang sebelum jam pulang shift. TIDAK menahan absen
 * pulang (atasan tidak selalu sempat ACC di tempat): absen pulang langsung
 * tercatat, karyawan cukup mengisi alasan, dan baris absensinya masuk antrean
 * tinjau (izin_pulang_cepat = 'Pending') di kelola_pengajuan_izin.php untuk
 * disetujui/ditolak belakangan. Ditolak untuk saat ini hanya catatan - belum
 * ada potongan gaji (slip gaji tetap memakai aturan durasi < 5,5 jam yang ada).
 *
 * Mengembalikan jam pulang shift (string 'H:i:s') bila absen pulang pada
 * $waktu ini butuh alasan + tinjauan, atau null bila tidak. Tidak berlaku
 * untuk: keterangan selain Hadir, hari lembur (Sabtu - tanpa jam pulang
 * tetap), izin setengah hari 'Pulang Awal' yang sudah disetujui, dan pulang
 * cepat yang sudah Disetujui sebelumnya. Dipakai bersama oleh absen.php
 * (minta alasan SEBELUM verifikasi wajah) dan proses_absen.php (validasi).
 *
 * $absen wajib memuat keterangan, jam_masuk, izin_pulang_cepat.
 */
function jamPulangCepatPerluTinjau($conn, array $absen, $id_cabang, $tanggal, $waktu, $izin_setengah_hari = null) {
    if (($absen['keterangan'] ?? null) !== 'Hadir' || empty($absen['jam_masuk'])) return null;
    if (($absen['izin_pulang_cepat'] ?? null) === 'Disetujui') return null;
    if ($izin_setengah_hari && ($izin_setengah_hari['sesi_setengah_hari'] ?? null) === 'Pulang Awal') return null;
    if (isHariOvertime($conn, $tanggal)) return null;
    $target = getJamPulangShift($conn, (int)$id_cabang, $absen['jam_masuk']);
    if ($target === null || $waktu >= $target) return null;
    return $target;
}

/**
 * Format jumlah hari kuota: 3 -> "3", 0.5 -> "0,5", 2.5 -> "2,5".
 */
function formatHariIzin($angka) {
    $angka = (float)$angka;
    return rtrim(rtrim(number_format($angka, 1, ',', ''), '0'), ',');
}

/**
 * Konversi satu baris absensi "Tidak Absen Masuk" (Hadir tanpa jam_masuk)
 * jadi izin setengah hari. Dipanggil oleh admin/SPV setelah menagih alasan
 * ke karyawan lewat proses_pengajuan_izin.php.
 *
 * Tidak membuat baris pengajuan_izin baru maupun memotong jatah_cuti -
 * cuma menandai baris yang sudah ada supaya slip_gaji_process.php ikut
 * menghitungnya sebagai Setengah Hari (bukan Hadir penuh) dan supaya
 * riwayatnya kelihatan di histori_absensi.php.
 */
function konversiTidakAbsenMasukKeSetengahHari($conn, $id_absensi, $id_reviewer) {
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_absensi);
    $stmt->execute();
    $absen = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$absen) {
        return ['sukses' => false, 'pesan' => 'Data absensi tidak ditemukan.'];
    }
    if ($absen['keterangan'] !== 'Hadir' || !empty($absen['jam_masuk'])) {
        return ['sukses' => false, 'pesan' => 'Baris ini bukan kasus Tidak Absen Masuk.'];
    }
    if ((int)$absen['dikonversi_izin_setengah_hari'] === 1) {
        return ['sukses' => false, 'pesan' => 'Baris ini sudah pernah dikonversi sebelumnya.'];
    }

    $bulan = (int)date('n', strtotime($absen['tanggal']));
    $tahun = (int)date('Y', strtotime($absen['tanggal']));
    // Batas bulanan hanya tanda, bukan blokir (lihat IZIN_SETENGAH_HARI_MAKS_PER_BULAN).
    $konversi_ke = hitungKonversiSetengahHariBulanIni($conn, $absen['id_karyawan'], $bulan, $tahun) + 1;
    $lewat_batas = $konversi_ke > IZIN_SETENGAH_HARI_MAKS_PER_BULAN;

    $stmt = $conn->prepare("UPDATE absensi
        SET dikonversi_izin_setengah_hari = 1, dikonversi_oleh = ?, dikonversi_at = NOW()
        WHERE id = ?");
    $stmt->bind_param("ii", $id_reviewer, $id_absensi);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['sukses' => false, 'pesan' => 'Gagal menyimpan konversi: ' . $conn->error];
    }
    $stmt->close();

    $pesan = 'Berhasil dikonversi menjadi izin setengah hari (konversi ke-' . $konversi_ke . ' bulan ini, batas wajar '
        . IZIN_SETENGAH_HARI_MAKS_PER_BULAN . 'x).';
    if ($lewat_batas) {
        $pesan .= ' <b>⚠ Melebihi batas wajar bulanan</b> - tetap dikonversi, tanpa potongan.';
    }
    return [
        'sukses'      => true,
        'pesan'       => $pesan,
        'absen'       => $absen,
        'konversi_ke' => $konversi_ke,
        'lewat_batas' => $lewat_batas,
    ];
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
        case 'Menikah':
        case 'Menikahkan Anak':
            return 'bg-pink-100 text-pink-700 border-pink-200 dark:bg-pink-900/30 dark:text-pink-400 dark:border-pink-800/50';
        case 'Melahirkan':
            return 'bg-teal-100 text-teal-700 border-teal-200 dark:bg-teal-900/30 dark:text-teal-400 dark:border-teal-800/50';
        case 'Duka Cita':
            return 'bg-slate-200 text-slate-700 border-slate-300 dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600';
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
