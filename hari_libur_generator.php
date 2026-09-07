<?php
/**
 * ==========================================
 * GENERATOR HARI LIBUR NASIONAL (kalender math)
 * ==========================================
 * Port PHP dari alat Node.js di id-holidays/ (lihat id-holidays/README.md).
 * Tidak dipanggil otomatis lewat config.php - hanya di-require oleh halaman
 * yang butuh (data_hari_libur.php, master_process.php), karena sifatnya
 * fitur admin yang jarang dipakai (generate per tahun), bukan helper
 * per-request seperti kalender_functions.php.
 *
 * Kenapa port manual, bukan panggil Node atau ekstensi PHP `calendar`:
 * proyek ini sengaja tanpa runtime tambahan (lihat CLAUDE.md - "no build
 * step, no dependency manager"), dan ekstensi `calendar` PHP tidak selalu
 * aktif di semua hosting (persis seperti masalah ekstensi `zip` yang pernah
 * ketemu). Jadi ini murni aritmatika Julian Day Number, tanpa dependency.
 *
 * Empat kalender berbeda, empat tingkat kepercayaan berbeda:
 *  - Tanggal Masehi tetap (Tahun Baru, dst.)     -> EXACT, tidak perlu verifikasi
 *  - Paskah/Jumat Agung/Kenaikan Isa (algoritma Meeus/Jones/Butcher) -> EXACT
 *  - Hijriah (Idul Fitri, Idul Adha, dst.)       -> perkiraan tabular ±0-1 hari,
 *    Kemenag menetapkan lewat rukyat (lihat bulan), jadi WAJIB diverifikasi
 *  - Imlek/Nyepi/Waisak                          -> tabel referensi manual
 *    (lihat HL_LUNISOLAR di bawah) - butuh top-up tahunan begitu SKB terbit
 *  - Cuti bersama                                -> murni kebijakan
 *    pemerintah (SKB 3 Menteri), tidak bisa dihitung sama sekali - hanya
 *    tabel referensi (HL_CUTI_BERSAMA)
 *
 * Lihat id-holidays/validate.js untuk hasil pengecekan akurasi terhadap
 * tanggal resmi 2025/2026 - port ini memakai rumus yang identik.
 */

// ---------- Julian Day Number <-> Gregorian ----------
// Aman pakai intdiv (truncate-toward-zero) alih-alih emulasi floor: semua
// operand di rentang tahun yang realistis (2020-an ke atas) selalu positif,
// jadi intdiv == floor untuk kasus ini.

function hlGregorianToJDN($year, $month, $day) {
    $a = intdiv(14 - $month, 12);
    $y = $year + 4800 - $a;
    $m = $month + 12 * $a - 3;
    return $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
}

function hlJdnToGregorian($jdn) {
    $a = $jdn + 32044;
    $b = intdiv(4 * $a + 3, 146097);
    $c = $a - intdiv(146097 * $b, 4);
    $d = intdiv(4 * $c + 3, 1461);
    $e = $c - intdiv(1461 * $d, 4);
    $m = intdiv(5 * $e + 2, 153);

    $day = $e - intdiv(153 * $m + 2, 5) + 1;
    $month = $m + 3 - 12 * intdiv($m, 10);
    $year = 100 * $b + $d - 4800 + intdiv($m, 10);

    return ['year' => $year, 'month' => $month, 'day' => $day];
}

function hlAddDays($year, $month, $day, $offset) {
    return hlJdnToGregorian(hlGregorianToJDN($year, $month, $day) + $offset);
}

function hlToISODate($ymd) {
    return sprintf('%04d-%02d-%02d', $ymd['year'], $ymd['month'], $ymd['day']);
}

// ---------- Kalender Masehi/Kristen (exact) ----------

function hlEasterSunday($year) {
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = ($h + $l - 7 * $m + 114) % 31 + 1;
    return ['year' => $year, 'month' => $month, 'day' => $day];
}

function hlGoodFriday($year) {
    $e = hlEasterSunday($year);
    return hlAddDays($e['year'], $e['month'], $e['day'], -2);
}

function hlAscensionDay($year) {
    $e = hlEasterSunday($year);
    return hlAddDays($e['year'], $e['month'], $e['day'], 39);
}

// ---------- Kalender Hijriah (tabular, perkiraan ±0-1 hari) ----------

define('HL_HIJRI_EPOCH_JDN', 1948439); // JDN - 1 dari 1 Muharram 1 H (epoch tabular/sipil)

function hlHijriToJDN($year, $month, $day) {
    return HL_HIJRI_EPOCH_JDN
        + ($year - 1) * 354
        + intdiv(3 + 11 * $year, 30)
        + (int)ceil(29.5 * ($month - 1))
        + $day;
}

function hlJdnToHijri($jdn) {
    $year = intdiv(30 * ($jdn - HL_HIJRI_EPOCH_JDN) + 10646, 10631);
    while (hlHijriToJDN($year, 1, 1) > $jdn) $year -= 1;
    while (hlHijriToJDN($year + 1, 1, 1) <= $jdn) $year += 1;

    $month = 1;
    while (hlHijriToJDN($year, $month + 1, 1) <= $jdn) $month += 1;

    $day = $jdn - hlHijriToJDN($year, $month, 1) + 1;
    return ['year' => $year, 'month' => $month, 'day' => $day];
}

function hlHijriToGregorian($year, $month, $day) {
    return hlJdnToGregorian(hlHijriToJDN($year, $month, $day));
}

/**
 * Cari tanggal Masehi tempat jatuhnya (bulan, tanggal) Hijriah tertentu dalam
 * satu tahun Masehi target. Tahun Hijriah ~11 hari lebih pendek dari Masehi,
 * jadi dicari dalam jendela kecil di sekitar perkiraan, bukan formula tunggal.
 */
function hlFindHijriEventInGregorianYear($targetGregorianYear, $hijriMonth, $hijriDay) {
    $approxHijriYear = intdiv(($targetGregorianYear - 622) * 33, 32);
    $candidates = [];
    for ($offset = -1; $offset <= 1; $offset++) {
        $hy = $approxHijriYear + $offset;
        $g = hlHijriToGregorian($hy, $hijriMonth, $hijriDay);
        if ($g['year'] === $targetGregorianYear) {
            $candidates[] = ['hijriYear' => $hy, 'gregorian' => $g];
        }
    }
    return $candidates;
}

// ---------- Imlek / Nyepi / Waisak (tabel referensi, bukan hitungan) ----------
// Diseed dari SKB 3 Menteri 2025/2026 asli. Tahun lain WAJIB ditambah manual
// dari sumber resmi begitu diumumkan - lihat id-holidays/lib/lunisolar.js
// untuk sumber per jenis (Imlek: kalender Tionghoa, Nyepi: PHDI, Waisak: WALUBI/Kemenag).
define('HL_LUNISOLAR', [
    2025 => [
        'imlek'  => ['date' => '2025-01-29', 'label' => 'Tahun Baru Imlek 2576 Kongzili'],
        'nyepi'  => ['date' => '2025-03-29', 'label' => 'Hari Suci Nyepi (Tahun Baru Saka 1947)'],
        'waisak' => ['date' => '2025-05-12', 'label' => 'Hari Raya Waisak 2569 BE'],
    ],
    2026 => [
        'imlek'  => ['date' => '2026-02-17', 'label' => 'Tahun Baru Imlek 2577 Kongzili'],
        'nyepi'  => ['date' => '2026-03-19', 'label' => 'Hari Suci Nyepi (Tahun Baru Saka 1948)'],
        'waisak' => ['date' => '2026-05-31', 'label' => 'Hari Raya Waisak 2570 BE'],
    ],
]);

// ---------- Cuti Bersama (murni kebijakan, tabel referensi) ----------
// SKB 3 Menteri diterbitkan ~3-4 bulan sebelum tahun berjalan; tahun di luar
// daftar ini akan tampil kosong pada preview (bukan error), admin tinggal
// menambah manual lewat form "Tambah Hari Libur" begitu SKB terbit.
define('HL_CUTI_BERSAMA', [
    2025 => [
        ['date' => '2025-01-28', 'label' => 'Cuti Bersama Tahun Baru Imlek 2576 Kongzili'],
        ['date' => '2025-03-28', 'label' => 'Cuti Bersama Hari Suci Nyepi (Tahun Baru Saka 1947)'],
        ['date' => '2025-04-02', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1446 Hijriah'],
        ['date' => '2025-04-03', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1446 Hijriah'],
        ['date' => '2025-04-04', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1446 Hijriah'],
        ['date' => '2025-04-07', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1446 Hijriah'],
        ['date' => '2025-05-13', 'label' => 'Cuti Bersama Hari Raya Waisak'],
        ['date' => '2025-05-30', 'label' => 'Cuti Bersama Kenaikan Yesus Kristus'],
        ['date' => '2025-06-09', 'label' => 'Cuti Bersama Hari Raya Idul Adha 1446 Hijriah'],
        ['date' => '2025-12-26', 'label' => 'Cuti Bersama Hari Raya Natal'],
    ],
    2026 => [
        ['date' => '2026-02-16', 'label' => 'Cuti Bersama Tahun Baru Imlek 2577 Kongzili'],
        ['date' => '2026-03-18', 'label' => 'Cuti Bersama Hari Suci Nyepi (Tahun Baru Saka 1948)'],
        ['date' => '2026-03-20', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1447 H'],
        ['date' => '2026-03-23', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1447 H'],
        ['date' => '2026-03-24', 'label' => 'Cuti Bersama Hari Raya Idul Fitri 1447 H'],
        ['date' => '2026-05-15', 'label' => 'Cuti Bersama Kenaikan Yesus Kristus'],
        ['date' => '2026-05-28', 'label' => 'Cuti Bersama Hari Raya Idul Adha 1447 H'],
        ['date' => '2026-12-24', 'label' => 'Cuti Bersama Hari Raya Natal'],
    ],
]);

function hlMakeEntry($ymd, $nama, $jenisHitung, $perluVerifikasi) {
    return [
        'tanggal' => hlToISODate($ymd),
        'nama' => $nama,
        'jenis_hitung' => $jenisHitung, // fixed|christian|hijri|lunisolar - info saja, bukan kolom hari_libur.jenis
        'jenis' => 'Nasional',
        'perlu_verifikasi' => $perluVerifikasi,
        'missing' => false,
    ];
}

/**
 * Bangun daftar hari libur nasional untuk satu tahun Masehi.
 * Mengembalikan array of ['tanggal','nama','jenis_hitung','jenis','perlu_verifikasi','missing'],
 * terurut menurut tanggal. Baris lunisolar yang datanya belum ada di
 * HL_LUNISOLAR muncul dengan missing=true, tanggal=null - bukan ditebak.
 */
function hlGenerateNationalHolidays($year) {
    $holidays = [];

    $fixed = [
        [1, 1, 'Tahun Baru Masehi'],
        [5, 1, 'Hari Buruh Internasional'],
        [6, 1, 'Hari Lahir Pancasila'],
        [8, 17, 'Hari Proklamasi Kemerdekaan Republik Indonesia'],
        [12, 25, 'Hari Raya Natal'],
    ];
    foreach ($fixed as [$m, $d, $label]) {
        $holidays[] = hlMakeEntry(['year' => $year, 'month' => $m, 'day' => $d], $label, 'fixed', 0);
    }

    $holidays[] = hlMakeEntry(hlGoodFriday($year), 'Wafat Yesus Kristus (Jumat Agung)', 'christian', 0);
    $holidays[] = hlMakeEntry(hlEasterSunday($year), 'Kebangkitan Yesus Kristus (Paskah)', 'christian', 0);
    $holidays[] = hlMakeEntry(hlAscensionDay($year), 'Kenaikan Yesus Kristus', 'christian', 0);

    $hijriHolidays = [
        [7, 27, "Isra Mi'raj Nabi Muhammad SAW"],
        [10, 1, 'Hari Raya Idul Fitri (hari ke-1)'],
        [10, 2, 'Hari Raya Idul Fitri (hari ke-2)'],
        [12, 10, 'Hari Raya Idul Adha'],
        [1, 1, 'Tahun Baru Islam'],
        [3, 12, 'Maulid Nabi Muhammad SAW'],
    ];
    foreach ($hijriHolidays as [$hm, $hd, $label]) {
        foreach (hlFindHijriEventInGregorianYear($year, $hm, $hd) as $c) {
            $holidays[] = hlMakeEntry($c['gregorian'], $label, 'hijri', 1);
        }
    }

    if (isset(HL_LUNISOLAR[$year])) {
        foreach (['imlek', 'nyepi', 'waisak'] as $key) {
            $entry = HL_LUNISOLAR[$year][$key];
            [$y, $m, $d] = array_map('intval', explode('-', $entry['date']));
            $holidays[] = hlMakeEntry(['year' => $y, 'month' => $m, 'day' => $d], $entry['label'], 'lunisolar', 1);
        }
    } else {
        $holidays[] = [
            'tanggal' => null,
            'nama' => 'Tahun Baru Imlek / Hari Suci Nyepi / Hari Raya Waisak',
            'jenis_hitung' => 'lunisolar',
            'jenis' => 'Nasional',
            'perlu_verifikasi' => 1,
            'missing' => true,
        ];
    }

    usort($holidays, function ($a, $b) {
        return strcmp($a['tanggal'] ?? '9999-99-99', $b['tanggal'] ?? '9999-99-99');
    });

    return $holidays;
}

/** Cuti bersama tahun tertentu, atau array kosong bila belum ada SKB-nya. */
function hlGetCutiBersama($year) {
    $hasil = [];
    foreach ((HL_CUTI_BERSAMA[$year] ?? []) as $c) {
        $hasil[] = [
            'tanggal' => $c['date'],
            'nama' => $c['label'],
            'jenis_hitung' => 'cuti_bersama',
            'jenis' => 'Cuti Bersama',
            'perlu_verifikasi' => 0, // sudah dari SKB resmi, bukan hasil hitungan
            'missing' => false,
        ];
    }
    return $hasil;
}
