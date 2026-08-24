<?php
/**
 * ==========================================
 * Helper Kalender, Hari Libur & Hari Kerja
 * ==========================================
 * Di-include otomatis lewat config.php sehingga tersedia di semua halaman.
 *
 * Kebijakan hari kerja perusahaan disimpan di `system_settings` (bukan
 * hardcode) agar bisa diubah tanpa menyentuh kode:
 *  - hari_kerja     : hari kerja normal, default '1,2,3,4,5' (Senin-Jumat)
 *  - hari_overtime  : hari lembur, default '6' (Sabtu)
 *  - sisanya (Minggu) dianggap libur mingguan
 *
 * Nomor hari memakai ISO-8601: 1 = Senin ... 7 = Minggu (date('N')).
 */

define('KALENDER_NAMA_HARI', [
    1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
    5 => 'Jumat', 6 => 'Sabtu',  7 => 'Minggu',
]);

define('KALENDER_NAMA_BULAN', [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
]);

/**
 * Baca satu pengaturan dari system_settings, dengan cache per-request.
 * Aman dipanggil walau tabel/baris belum ada (mengembalikan $default).
 */
function getPengaturan($conn, $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $nilai = $default;
    // Tabel system_settings bisa belum ada pada database lama; jangan sampai
    // seluruh halaman ikut mati karenanya.
    $stmt = @$conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        if ($stmt->execute()) {
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && $row['setting_value'] !== '') {
                $nilai = $row['setting_value'];
            }
        }
        $stmt->close();
    }

    $cache[$key] = $nilai;
    return $nilai;
}

/**
 * Simpan/ubah satu pengaturan.
 */
function setPengaturan($conn, $key, $value, $deskripsi = null) {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("sss", $key, $value, $deskripsi);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Ubah string '1,2,3' menjadi array int [1,2,3] yang tervalidasi (1-7).
 */
function parseDaftarHari($nilai, $fallback) {
    $hasil = [];
    foreach (explode(',', (string)$nilai) as $bagian) {
        $n = (int)trim($bagian);
        if ($n >= 1 && $n <= 7 && !in_array($n, $hasil, true)) {
            $hasil[] = $n;
        }
    }
    sort($hasil);
    return empty($hasil) ? $fallback : $hasil;
}

/**
 * Hari kerja normal perusahaan, default Senin-Jumat.
 */
function getHariKerja($conn) {
    return parseDaftarHari(getPengaturan($conn, 'hari_kerja', '1,2,3,4,5'), [1, 2, 3, 4, 5]);
}

/**
 * Hari lembur (bukan hari kerja normal), default Sabtu.
 */
function getHariOvertime($conn) {
    return parseDaftarHari(getPengaturan($conn, 'hari_overtime', '6'), [6]);
}

/**
 * Apakah tanggal ini hari kerja normal (belum memperhitungkan hari libur)?
 */
function isHariKerja($conn, $tanggal) {
    return in_array((int)date('N', strtotime($tanggal)), getHariKerja($conn), true);
}

/**
 * Apakah tanggal ini hari lembur (mis. Sabtu)?
 */
function isHariOvertime($conn, $tanggal) {
    return in_array((int)date('N', strtotime($tanggal)), getHariOvertime($conn), true);
}

/**
 * Teks ringkas kebijakan hari kerja, untuk ditampilkan di UI.
 * Contoh: "Senin - Jumat" atau "Senin, Rabu, Jumat".
 */
function labelHariKerja($conn) {
    $hari = getHariKerja($conn);
    $nama = array_map(function ($n) { return KALENDER_NAMA_HARI[$n]; }, $hari);

    // Deteksi rentang berurutan supaya tampil "Senin - Jumat"
    $berurutan = true;
    for ($i = 1; $i < count($hari); $i++) {
        if ($hari[$i] !== $hari[$i - 1] + 1) { $berurutan = false; break; }
    }
    if ($berurutan && count($hari) > 2) {
        return $nama[0] . ' - ' . $nama[count($nama) - 1];
    }
    return implode(', ', $nama);
}

/**
 * Ambil hari libur dalam satu rentang tanggal.
 *
 * Mengembalikan map 'Y-m-d' => ['nama' => ..., 'jenis' => ..., 'perlu_verifikasi' => ...].
 * Libur global (id_cabang NULL) berlaku untuk semua cabang; libur bercabang
 * hanya muncul untuk cabang tersebut dan menang atas libur global bila bentrok.
 */
function getHariLibur($conn, $tanggal_mulai, $tanggal_selesai, $id_cabang = null) {
    $hasil = [];

    $sql = "SELECT tanggal, nama, jenis, id_cabang, perlu_verifikasi
            FROM hari_libur
            WHERE tanggal BETWEEN ? AND ?
              AND (id_cabang IS NULL" . ($id_cabang !== null ? " OR id_cabang = ?" : "") . ")
            ORDER BY id_cabang IS NULL DESC, tanggal ASC";

    $stmt = @$conn->prepare($sql);
    if (!$stmt) {
        return $hasil; // tabel belum ada (migrasi belum dijalankan)
    }

    if ($id_cabang !== null) {
        $stmt->bind_param("ssi", $tanggal_mulai, $tanggal_selesai, $id_cabang);
    } else {
        $stmt->bind_param("ss", $tanggal_mulai, $tanggal_selesai);
    }

    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // Urutan ORDER BY menaruh libur global lebih dulu, sehingga libur
            // khusus cabang yang datang setelahnya menimpa (lebih spesifik).
            $hasil[$row['tanggal']] = [
                'nama'             => $row['nama'],
                'jenis'            => $row['jenis'],
                'khusus_cabang'    => $row['id_cabang'] !== null,
                'perlu_verifikasi' => (int)$row['perlu_verifikasi'] === 1,
            ];
        }
    }
    $stmt->close();

    return $hasil;
}

/**
 * Klasifikasi satu tanggal: 'kerja', 'overtime', 'libur' (mingguan), atau
 * 'libur_nasional' bila terdaftar di hari_libur.
 */
function klasifikasiHari($conn, $tanggal, $daftar_libur = null) {
    if ($daftar_libur === null) {
        $daftar_libur = getHariLibur($conn, $tanggal, $tanggal);
    }
    if (isset($daftar_libur[$tanggal])) {
        return 'libur_nasional';
    }
    if (isHariKerja($conn, $tanggal))    return 'kerja';
    if (isHariOvertime($conn, $tanggal)) return 'overtime';
    return 'libur';
}

/**
 * Durasi kerja (jam) dari satu baris absensi, dibulatkan ke 0,5 jam terdekat.
 * Dipakai untuk lembur Sabtu yang jam masuk/pulangnya tidak tetap, sehingga
 * tidak bisa diukur dengan membandingkan jam_pulang shift seperti hari kerja.
 */
function hitungJamKerja($jam_masuk, $jam_pulang) {
    if (empty($jam_masuk) || empty($jam_pulang) || $jam_pulang === '00:00:00') {
        return 0.0;
    }
    $menit = (strtotime($jam_pulang) - strtotime($jam_masuk)) / 60;
    if ($menit <= 0) {
        return 0.0;
    }
    return round(($menit / 60) * 2) / 2;
}

/**
 * Rekap lembur hari-lembur (Sabtu) seorang karyawan pada satu bulan.
 *
 * Karena jam kerja Sabtu bervariasi (mis. masuk 10:00 pulang 14:00), lembur
 * dihitung dari durasi kerja sebenarnya, bukan dari selisih terhadap
 * jam_pulang shift. Hanya jabatan dengan `overtime_sabtu = 1` yang dihitung.
 *
 * Mengembalikan ['berhak' => bool, 'total_jam' => float, 'rincian' => [...]].
 */
function getLemburHariSabtu($conn, $id_karyawan, $bulan, $tahun) {
    $hasil = ['berhak' => false, 'total_jam' => 0.0, 'rincian' => []];

    // Cek kelayakan jabatan
    $stmt = $conn->prepare("SELECT COALESCE(j.overtime_sabtu, 0) AS overtime_sabtu
                            FROM karyawan k
                            LEFT JOIN jabatan j ON k.id_jabatan = j.id
                            WHERE k.id_karyawan = ?");
    if (!$stmt) {
        return $hasil;
    }
    $stmt->bind_param("s", $id_karyawan);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (int)$row['overtime_sabtu'] !== 1) {
        return $hasil; // jabatan ini tidak punya lembur Sabtu
    }
    $hasil['berhak'] = true;

    $hari_overtime = getHariOvertime($conn);

    $stmt = $conn->prepare("SELECT tanggal, jam_masuk, jam_pulang
                            FROM absensi
                            WHERE id_karyawan = ?
                              AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
                              AND keterangan IN ('Hadir', 'Dinas Luar')
                              AND jam_masuk IS NOT NULL
                            ORDER BY tanggal ASC");
    $stmt->bind_param("sii", $id_karyawan, $bulan, $tahun);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($a = $res->fetch_assoc()) {
        if (!in_array((int)date('N', strtotime($a['tanggal'])), $hari_overtime, true)) {
            continue;
        }
        $jam = hitungJamKerja($a['jam_masuk'], $a['jam_pulang']);
        if ($jam <= 0) {
            continue;
        }
        $hasil['total_jam'] += $jam;
        $hasil['rincian'][] = [
            'tanggal'    => $a['tanggal'],
            'hari'       => KALENDER_NAMA_HARI[(int)date('N', strtotime($a['tanggal']))],
            'jam_masuk'  => $a['jam_masuk'],
            'jam_pulang' => $a['jam_pulang'],
            'jam'        => $jam,
        ];
    }
    $stmt->close();

    return $hasil;
}

/**
 * Bangun struktur data kalender satu bulan.
 *
 * $opsi:
 *  - id_karyawan : tampilkan absensi & izin milik karyawan ini (mode pribadi)
 *  - id_cabang   : batasi hari libur & pengajuan pada cabang ini
 *  - global      : true = kumpulkan pengajuan izin disetujui SEMUA karyawan
 *                  dalam cakupan (mode supervisor/admin/owner)
 *
 * Mengembalikan:
 *  - bulan, tahun, label
 *  - minggu[] : array baris, tiap baris 7 sel (Senin-Minggu)
 *      sel = null (padding) atau [
 *          'tanggal', 'hari_ke', 'jenis' (kerja|overtime|libur|libur_nasional),
 *          'hari_ini' => bool, 'libur' => info|null,
 *          'absensi' => row|null, 'izin' => [ ... ]
 *      ]
 *  - agenda[] : daftar peristiwa penting bulan itu untuk ditampilkan sebagai list
 */
function bangunKalenderBulan($conn, $bulan, $tahun, $opsi = []) {
    $bulan = max(1, min(12, (int)$bulan));
    $tahun = (int)$tahun;

    $id_karyawan = $opsi['id_karyawan'] ?? null;
    $id_cabang   = $opsi['id_cabang'] ?? null;
    $global      = !empty($opsi['global']);

    $awal  = sprintf('%04d-%02d-01', $tahun, $bulan);
    $akhir = date('Y-m-t', strtotime($awal));

    $daftar_libur = getHariLibur($conn, $awal, $akhir, $id_cabang);
    $hari_kerja     = getHariKerja($conn);
    $hari_overtime   = getHariOvertime($conn);

    // ---------- Absensi (mode pribadi) ----------
    $absensi_per_tanggal = [];
    if ($id_karyawan) {
        $stmt = $conn->prepare("SELECT tanggal, jam_masuk, jam_pulang, keterangan, status_masuk
                                FROM absensi
                                WHERE id_karyawan = ? AND tanggal BETWEEN ? AND ?");
        $stmt->bind_param("sss", $id_karyawan, $awal, $akhir);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $absensi_per_tanggal[$row['tanggal']] = $row;
        }
        $stmt->close();
    }

    // ---------- Pengajuan izin ----------
    // Rentang yang beririsan dengan bulan ini, bukan hanya yang mulai di bulan ini.
    $izin_per_tanggal = [];
    $agenda_izin = [];

    $sql_izin = "SELECT p.id, p.jenis, p.status, p.tanggal_mulai, p.tanggal_selesai,
                        p.keperluan, k.nama_karyawan, k.id_karyawan
                 FROM pengajuan_izin p
                 JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                 WHERE p.tanggal_mulai <= ? AND p.tanggal_selesai >= ?";
    $params = [$akhir, $awal];
    $types  = 'ss';

    if ($global) {
        // Reviewer hanya melihat yang sudah disetujui - antrean Pending punya
        // halamannya sendiri (kelola_pengajuan_izin.php).
        $sql_izin .= " AND p.status = 'Disetujui'";
        if ($id_cabang !== null) {
            $sql_izin .= " AND k.id_cabang = ?";
            $params[] = $id_cabang;
            $types   .= 'i';
        }
    } else {
        // Mode pribadi: tampilkan juga yang masih menunggu, supaya karyawan
        // melihat rencana izinnya sendiri.
        $sql_izin .= " AND p.id_karyawan = ? AND p.status IN ('Pending','Disetujui')";
        $params[] = $id_karyawan;
        $types   .= 's';
    }
    $sql_izin .= " ORDER BY p.tanggal_mulai ASC";

    $stmt = @$conn->prepare($sql_izin);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $agenda_izin[] = $row;

                // Sebar rentang ke tiap tanggal dalam bulan ini
                $mulai_ts = max(strtotime($row['tanggal_mulai']), strtotime($awal));
                $akhir_ts = min(strtotime($row['tanggal_selesai']), strtotime($akhir));
                for ($ts = $mulai_ts; $ts <= $akhir_ts; $ts = strtotime('+1 day', $ts)) {
                    $tgl = date('Y-m-d', $ts);
                    $izin_per_tanggal[$tgl][] = [
                        'id'            => $row['id'],
                        'jenis'         => $row['jenis'],
                        'status'        => $row['status'],
                        'nama_karyawan' => $row['nama_karyawan'],
                        'keperluan'     => $row['keperluan'],
                        'awal_rentang'  => $tgl === $row['tanggal_mulai'],
                        'akhir_rentang' => $tgl === $row['tanggal_selesai'],
                    ];
                }
            }
        }
        $stmt->close();
    }

    // ---------- Susun grid ----------
    $jumlah_hari  = (int)date('t', strtotime($awal));
    $offset_awal  = (int)date('N', strtotime($awal)) - 1; // 0 = Senin
    $hari_ini     = date('Y-m-d');

    $sel = array_fill(0, $offset_awal, null);
    for ($d = 1; $d <= $jumlah_hari; $d++) {
        $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
        $n   = (int)date('N', strtotime($tgl));

        if (isset($daftar_libur[$tgl]))            $jenis = 'libur_nasional';
        elseif (in_array($n, $hari_kerja, true))   $jenis = 'kerja';
        elseif (in_array($n, $hari_overtime, true)) $jenis = 'overtime';
        else                                       $jenis = 'libur';

        $sel[] = [
            'tanggal'  => $tgl,
            'hari_ke'  => $d,
            'jenis'    => $jenis,
            'hari_ini' => $tgl === $hari_ini,
            'libur'    => $daftar_libur[$tgl] ?? null,
            'absensi'  => $absensi_per_tanggal[$tgl] ?? null,
            'izin'     => $izin_per_tanggal[$tgl] ?? [],
        ];
    }
    while (count($sel) % 7 !== 0) {
        $sel[] = null;
    }

    $minggu = array_chunk($sel, 7);

    // ---------- Agenda ----------
    $agenda = [];
    foreach ($daftar_libur as $tgl => $info) {
        $agenda[] = [
            'tipe'    => 'libur',
            'tanggal' => $tgl,
            'judul'   => $info['nama'],
            'jenis'   => $info['jenis'],
            'catatan' => $info['perlu_verifikasi'] ? 'Perlu verifikasi SKB' : null,
        ];
    }
    foreach ($agenda_izin as $izin) {
        $agenda[] = [
            'tipe'    => 'izin',
            'tanggal' => $izin['tanggal_mulai'],
            'judul'   => $global
                            ? $izin['nama_karyawan'] . ' - ' . $izin['jenis']
                            : $izin['jenis'],
            'jenis'   => $izin['jenis'],
            'status'  => $izin['status'],
            'rentang' => formatRentangTanggal($izin['tanggal_mulai'], $izin['tanggal_selesai']),
            'catatan' => $izin['keperluan'],
        ];
    }
    usort($agenda, function ($a, $b) {
        return strcmp($a['tanggal'], $b['tanggal']);
    });

    return [
        'bulan'  => $bulan,
        'tahun'  => $tahun,
        'label'  => KALENDER_NAMA_BULAN[$bulan] . ' ' . $tahun,
        'minggu' => $minggu,
        'agenda' => $agenda,
        'jumlah_libur' => count($daftar_libur),
    ];
}

/**
 * Warna sel kalender berdasarkan klasifikasi hari.
 */
function warnaSelKalender($jenis) {
    switch ($jenis) {
        case 'libur_nasional':
            return 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800/40';
        case 'overtime':
            return 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/40';
        case 'libur':
            return 'bg-slate-100 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700';
        default: // kerja
            return 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700';
    }
}
?>
