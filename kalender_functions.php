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

// Semua jenis pengajuan izin yang dikenal sistem (lihat migrations/003, 005).
define('KALENDER_SEMUA_JENIS_IZIN', ['Cuti', 'Sakit', 'Izin', 'Dinas Luar', 'Menikah', 'Menikahkan Anak', 'Melahirkan', 'Duka Cita']);

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
 * Format daftar nomor hari ISO jadi teks ringkas untuk UI.
 * Contoh: "Senin - Jumat" atau "Senin, Rabu, Jumat".
 */
function formatLabelDaftarHari($hari) {
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
 * Teks ringkas kebijakan hari kerja, untuk ditampilkan di UI.
 * Contoh: "Senin - Jumat" atau "Senin, Rabu, Jumat".
 */
function labelHariKerja($conn) {
    return formatLabelDaftarHari(getHariKerja($conn));
}

/**
 * Teks ringkas hari lembur (mis. "Sabtu"), untuk dipakai di label/kolom
 * laporan yang dulu hardcode "Ahad"/"Minggu" - supaya ikut berubah otomatis
 * kalau pengaturan hari_overtime diubah, bukan sekadar ganti satu hardcode
 * dengan hardcode lain.
 */
function labelHariOvertime($conn) {
    return formatLabelDaftarHari(getHariOvertime($conn));
}

/**
 * Daftar nomor hari lembur (ISO) dikonversi ke penomoran DAYOFWEEK() MySQL
 * (1=Minggu...7=Sabtu), siap dipakai dalam klausa "DAYOFWEEK(tanggal) IN (...)".
 * Nilai bersumber dari parseDaftarHari (tervalidasi 1-7), aman diinterpolasi
 * langsung ke SQL tanpa parameter binding.
 */
function daftarHariOvertimeMysqlDow($conn) {
    return implode(',', array_map(function ($iso) {
        return ($iso % 7) + 1;
    }, getHariOvertime($conn)));
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
 * Pengaturan visibilitas "Kalender Tim" (satu pengaturan berlaku untuk semua
 * role yang melihat mode global - admin/owner/supervisor/staff): jenis izin
 * apa saja dan apakah status Pending ikut ditampilkan untuk rekan kerja lain
 * (bukan milik sendiri, yang selalu tampil terlepas dari pengaturan ini).
 */
function getPengaturanKalenderTim($conn) {
    $jenis_tersimpan = explode(',', getPengaturan($conn, 'kalender_tim_jenis_tampil', implode(',', KALENDER_SEMUA_JENIS_IZIN)));
    $jenis_tampil = array_values(array_intersect(KALENDER_SEMUA_JENIS_IZIN, $jenis_tersimpan));

    return [
        'tampilkan_pending' => getPengaturan($conn, 'kalender_tim_tampilkan_pending', '1') === '1',
        'jenis_tampil'      => $jenis_tampil,
    ];
}

/**
 * Apakah ulang tahun karyawan ditampilkan di kalender (default aktif).
 */
function tampilkanUlangTahunKalender($conn) {
    return getPengaturan($conn, 'kalender_tampilkan_ultah', '1') === '1';
}

/**
 * Ulang tahun karyawan aktif pada satu bulan, dikelompokkan per tanggal
 * (hari-ke, 1-31), dibatasi ke satu cabang bila $id_cabang diisi.
 */
function getUlangTahunBulan($conn, $bulan, $id_cabang = null) {
    $hasil = [];

    $sql = "SELECT id_karyawan, nama_karyawan, tanggal_lahir
            FROM karyawan
            WHERE status = 'aktif' AND tanggal_lahir IS NOT NULL
              AND MONTH(tanggal_lahir) = ?" . ($id_cabang !== null ? " AND id_cabang = ?" : "");

    $stmt = @$conn->prepare($sql);
    if (!$stmt) {
        return $hasil;
    }

    if ($id_cabang !== null) {
        $stmt->bind_param("ii", $bulan, $id_cabang);
    } else {
        $stmt->bind_param("i", $bulan);
    }

    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $hari_ke = (int)date('j', strtotime($row['tanggal_lahir']));
            $hasil[$hari_ke][] = [
                'id_karyawan'   => $row['id_karyawan'],
                'nama_karyawan' => $row['nama_karyawan'],
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
 *  - id_karyawan : tampilkan absensi & izin milik karyawan ini. Izin milik
 *                  sendiri (Pending & Disetujui, semua jenis) selalu ikut
 *                  tampil terlepas dari pengaturan kalender tim di bawah.
 *  - id_cabang   : batasi hari libur, ulang tahun & pengajuan tim pada cabang ini
 *  - global      : true = kalender tim - selain milik sendiri, ikut tampilkan
 *                  pengajuan izin karyawan LAIN dalam cakupan, tunduk pada
 *                  getPengaturanKalenderTim() (jenis & status apa yang admin
 *                  izinkan tampil ke tim). Dipakai staff (digabung id_karyawan,
 *                  dibatasi cabangnya sendiri) maupun admin/owner/supervisor.
 *
 * Mengembalikan:
 *  - bulan, tahun, label
 *  - minggu[] : array baris, tiap baris 7 sel (Senin-Minggu)
 *      sel = null (padding) atau [
 *          'tanggal', 'hari_ke', 'jenis' (kerja|overtime|libur|libur_nasional),
 *          'hari_ini' => bool, 'libur' => info|null,
 *          'absensi' => row|null, 'izin' => [ ... ], 'ulang_tahun' => [ ... ]
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
    // Digabung dari dua sumber lalu dedupe per id: (1) milik sendiri - selalu
    // tampil apapun pengaturannya, (2) milik tim - tunduk pengaturan admin.
    $izin_per_tanggal = [];
    $izin_rows = []; // keyed by id, dedupe kalau dua query sama-sama mengembalikannya

    if ($id_karyawan) {
        $stmt = @$conn->prepare("SELECT p.id, p.jenis, p.status, p.tanggal_mulai, p.tanggal_selesai,
                                        p.keperluan, k.nama_karyawan, k.id_karyawan
                                 FROM pengajuan_izin p
                                 JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                                 WHERE p.tanggal_mulai <= ? AND p.tanggal_selesai >= ?
                                   AND p.id_karyawan = ? AND p.status IN ('Pending','Disetujui')");
        if ($stmt) {
            $stmt->bind_param("sss", $akhir, $awal, $id_karyawan);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $row['milik_sendiri'] = true;
                    $izin_rows[$row['id']] = $row;
                }
            }
            $stmt->close();
        }
    }

    if ($global) {
        $pengaturan_tim = getPengaturanKalenderTim($conn);
        $jenis_tampil   = $pengaturan_tim['jenis_tampil'];

        if (!empty($jenis_tampil)) {
            $status_diizinkan = $pengaturan_tim['tampilkan_pending'] ? "('Pending','Disetujui')" : "('Disetujui')";
            $jenis_placeholder = implode(',', array_fill(0, count($jenis_tampil), '?'));

            // Klausa & parameter ditambahkan berpasangan dan berurutan supaya posisi
            // placeholder "?" di teks SQL selalu cocok dengan urutan $params - bind_param
            // mencocokkan berdasarkan posisi, bukan berdasarkan nama/maksud parameter.
            $sql_tim = "SELECT p.id, p.jenis, p.status, p.tanggal_mulai, p.tanggal_selesai,
                               p.keperluan, k.nama_karyawan, k.id_karyawan
                        FROM pengajuan_izin p
                        JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                        WHERE p.tanggal_mulai <= ? AND p.tanggal_selesai >= ?
                          AND p.status IN $status_diizinkan";
            $params = [$akhir, $awal];
            $types  = 'ss';

            if ($id_karyawan) {
                // Sudah tercakup query "milik sendiri" di atas - jangan dobel.
                $sql_tim .= " AND p.id_karyawan != ?";
                $params[] = $id_karyawan;
                $types   .= 's';
            }
            if ($id_cabang !== null) {
                $sql_tim .= " AND k.id_cabang = ?";
                $params[] = $id_cabang;
                $types   .= 'i';
            }
            $sql_tim .= " AND p.jenis IN ($jenis_placeholder)";
            foreach ($jenis_tampil as $j) {
                $params[] = $j;
                $types   .= 's';
            }

            $stmt = @$conn->prepare($sql_tim);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $row['milik_sendiri'] = false;
                        $izin_rows[$row['id']] = $row;
                    }
                }
                $stmt->close();
            }
        }
    }

    $agenda_izin = array_values($izin_rows);
    usort($agenda_izin, function ($a, $b) {
        return strcmp($a['tanggal_mulai'], $b['tanggal_mulai']);
    });

    foreach ($agenda_izin as $row) {
        // Sebar rentang ke tiap tanggal dalam bulan ini
        $mulai_ts = max(strtotime($row['tanggal_mulai']), strtotime($awal));
        $akhir_ts = min(strtotime($row['tanggal_selesai']), strtotime($akhir));
        for ($ts = $mulai_ts; $ts <= $akhir_ts; $ts = strtotime('+1 day', $ts)) {
            $tgl = date('Y-m-d', $ts);
            $izin_per_tanggal[$tgl][] = [
                'id'             => $row['id'],
                'jenis'          => $row['jenis'],
                'status'         => $row['status'],
                'nama_karyawan'  => $row['nama_karyawan'],
                'keperluan'      => $row['keperluan'],
                'milik_sendiri'  => $row['milik_sendiri'],
                'awal_rentang'   => $tgl === $row['tanggal_mulai'],
                'akhir_rentang'  => $tgl === $row['tanggal_selesai'],
            ];
        }
    }

    // ---------- Ulang tahun ----------
    $ultah_per_hari_ke = tampilkanUlangTahunKalender($conn)
        ? getUlangTahunBulan($conn, $bulan, $id_cabang)
        : [];

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
            'tanggal'     => $tgl,
            'hari_ke'     => $d,
            'jenis'       => $jenis,
            'hari_ini'    => $tgl === $hari_ini,
            'libur'       => $daftar_libur[$tgl] ?? null,
            'absensi'     => $absensi_per_tanggal[$tgl] ?? null,
            'izin'        => $izin_per_tanggal[$tgl] ?? [],
            'ulang_tahun' => $ultah_per_hari_ke[$d] ?? [],
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
        $siapa = $izin['milik_sendiri'] ? 'Anda' : $izin['nama_karyawan'];
        $agenda[] = [
            'tipe'    => 'izin',
            'tanggal' => $izin['tanggal_mulai'],
            'judul'   => $global ? $siapa . ' - ' . $izin['jenis'] : $izin['jenis'],
            'jenis'   => $izin['jenis'],
            'status'  => $izin['status'],
            'rentang' => formatRentangTanggal($izin['tanggal_mulai'], $izin['tanggal_selesai']),
            'catatan' => $izin['keperluan'],
        ];
    }
    foreach ($ultah_per_hari_ke as $hari_ke => $daftar_ultah) {
        $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari_ke);
        foreach ($daftar_ultah as $org) {
            $agenda[] = [
                'tipe'    => 'ulang_tahun',
                'tanggal' => $tgl,
                'judul'   => $org['nama_karyawan'],
                'jenis'   => null,
                'catatan' => null,
            ];
        }
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
