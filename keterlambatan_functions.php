<?php
/**
 * ==========================================
 * Helper Potongan Keterlambatan Bertingkat
 * ==========================================
 * Di-include otomatis lewat config.php.
 *
 * Kebijakan (system_settings, lihat migrations/007_keterlambatan_bertingkat.php):
 * - Dispensasi (grace) N menit setelah jam_masuk_akhir shift - dalam masa
 *   ini status masih 'Tepat Waktu', tidak ada potongan.
 * - Begitu lewat dispensasi: potongan flat (tier 1) untuk durasi tertentu.
 * - Setelah tier 1: potongan tambahan setiap interval menit tertentu (tier 2).
 * - Dibekukan (tidak naik lagi) setelah keterlambatan mencapai batas jam
 *   tertentu - dihitung seolah keterlambatannya persis di batas itu.
 *
 * Rate disimpan sebagai pengaturan GLOBAL (bukan per-karyawan, beda dari
 * rate_transport/rate_overtime dkk. di tabel karyawan) - ini kebijakan
 * perusahaan yang sama untuk semua orang, bukan sesuatu yang dinegosiasikan
 * per individu.
 *
 * absensi.menit_terlambat menyimpan FAKTA MENTAH (berapa menit telat hari
 * itu), bukan nominal potongannya - supaya kalau tarif berubah di kemudian
 * hari, riwayat lama tetap bisa dihitung ulang dengan tarif yang berlaku
 * SAAT slip gaji dibuat (persis seperti rate_keterlambatan per-karyawan
 * yang sudah ada, bukan tarif yang dibekukan di tanggal absen).
 */

function getPengaturanKeterlambatan($conn) {
    return [
        'grace_menit' => (int)getPengaturan($conn, 'keterlambatan_grace_menit', '10'),
        'tier1_durasi_menit' => (int)getPengaturan($conn, 'keterlambatan_tier1_durasi_menit', '10'),
        'tier1_rate' => (float)getPengaturan($conn, 'keterlambatan_tier1_rate', '15000'),
        'tier2_interval_menit' => (int)getPengaturan($conn, 'keterlambatan_tier2_interval_menit', '5'),
        'tier2_rate' => (float)getPengaturan($conn, 'keterlambatan_tier2_rate', '10000'),
        'maks_jam' => (float)getPengaturan($conn, 'keterlambatan_maks_jam', '3'),
    ];
}

/**
 * Apakah sejumlah menit keterlambatan ini sudah melewati masa dispensasi
 * (sehingga harus berstatus 'Terlambat')?
 */
function apakahTerlambat($menitTerlambat, array $pengaturan = null, $conn = null) {
    $p = $pengaturan ?? getPengaturanKeterlambatan($conn);
    return $menitTerlambat > $p['grace_menit'];
}

/**
 * Hitung potongan (Rupiah) untuk sejumlah menit keterlambatan, mengikuti
 * kebijakan bertingkat. $menitTerlambat <= grace -> 0. Dibekukan begitu
 * mencapai maks_jam (tidak dihitung lebih jauh dari itu).
 */
function hitungPotonganKeterlambatan($menitTerlambat, array $pengaturan = null, $conn = null) {
    $p = $pengaturan ?? getPengaturanKeterlambatan($conn);

    if ($menitTerlambat <= $p['grace_menit']) {
        return 0.0;
    }

    // Bekukan di batas maksimal - keterlambatan yang lebih parah dari ini
    // dihitung seolah persis di batasnya, tidak terus bertambah.
    $batasMenit = $p['maks_jam'] * 60;
    $menitEfektif = min($menitTerlambat, $batasMenit);

    $menitSetelahGrace = $menitEfektif - $p['grace_menit'];

    if ($menitSetelahGrace <= $p['tier1_durasi_menit']) {
        return $p['tier1_rate'];
    }

    $menitSetelahTier1 = $menitSetelahGrace - $p['tier1_durasi_menit'];
    $jumlahInterval = (int)ceil($menitSetelahTier1 / $p['tier2_interval_menit']);

    return $p['tier1_rate'] + ($jumlahInterval * $p['tier2_rate']);
}

/**
 * Total potongan keterlambatan satu periode (bulan/tahun) untuk satu
 * karyawan, dijumlahkan PER HARI dari menit_terlambat mentah - bukan lagi
 * rate flat x jumlah hari seperti model lama.
 *
 * Baris absensi lama (sebelum migrasi 007) tidak punya menit_terlambat
 * (NULL) walau status_masuk sudah 'Terlambat' - untuk hari macam ini kita
 * tidak bisa menghitung tarif bertingkat (tidak ada fakta mentahnya lagi),
 * jadi jatuh ke $rateFallbackLegacy (rate flat per-karyawan lama) sebagai
 * perkiraan terbaik yang tersedia, dan ditandai 'legacy_flat' di rincian
 * supaya admin tahu hari mana yang dihitung dengan cara lama.
 */
function hitungTotalPotonganKeterlambatanPeriode($conn, $id_karyawan, $bulan, $tahun, $rateFallbackLegacy = 0) {
    $sql = "SELECT tanggal, menit_terlambat FROM absensi
            WHERE id_karyawan = ? AND status_masuk = 'Terlambat'
              AND keterangan IN ('Hadir', 'Dinas Luar')
              AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
            ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $id_karyawan, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();

    $pengaturan = getPengaturanKeterlambatan($conn);
    $total = 0.0;
    $rincian = [];
    $jumlah_legacy = 0;

    while ($row = $result->fetch_assoc()) {
        if ($row['menit_terlambat'] !== null) {
            $potongan = hitungPotonganKeterlambatan((int)$row['menit_terlambat'], $pengaturan);
            $sumber = 'tiered';
        } else {
            $potongan = (float)$rateFallbackLegacy;
            $sumber = 'legacy_flat';
            $jumlah_legacy++;
        }
        $total += $potongan;
        $rincian[] = [
            'tanggal' => $row['tanggal'],
            'menit_terlambat' => $row['menit_terlambat'] !== null ? (int)$row['menit_terlambat'] : null,
            'potongan' => $potongan,
            'sumber' => $sumber,
        ];
    }
    $stmt->close();

    return [
        'total' => $total,
        'jumlah_hari' => count($rincian),
        'jumlah_legacy' => $jumlah_legacy,
        'rincian' => $rincian,
    ];
}
