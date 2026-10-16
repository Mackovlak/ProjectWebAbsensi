<?php
/**
 * Simpan kebijakan keterlambatan bertingkat (system_settings) dari panel
 * "Cara Hitung & Kebijakan Keterlambatan" di slip_gaji_form.php.
 *
 * Beda dari ajax_save_rate.php (rate per-karyawan) - ini pengaturan level
 * CABANG (key "c{id}_keterlambatan_*", sama persis dengan yang disimpan
 * admin_detail_cabang.php lewat master_process.php), jadi berlaku untuk semua
 * karyawan di cabang tsb. Tanpa id_cabang (karyawan tanpa cabang) jatuh ke
 * key global. Divalidasi ketat dan wajib CSRF.
 */
require 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

verifyCSRFToken($_POST['csrf_token'] ?? '');

$id_cabang = (int)($_POST['id_cabang'] ?? 0);
if ($id_cabang > 0) {
    $stmt_cabang = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
    $stmt_cabang->bind_param('i', $id_cabang);
    $stmt_cabang->execute();
    $cabang_row = $stmt_cabang->get_result()->fetch_assoc();
    $stmt_cabang->close();
    if (!$cabang_row) {
        echo json_encode(['status' => 'error', 'message' => 'Cabang tidak ditemukan.']);
        exit;
    }
}

$fields = [
    'grace_menit' => [
        'key' => 'keterlambatan_grace_menit', 'min' => 0, 'max' => 180,
        'desc' => 'Masa dispensasi (menit) sebelum status Terlambat berlaku',
    ],
    'tier1_durasi_menit' => [
        'key' => 'keterlambatan_tier1_durasi_menit', 'min' => 1, 'max' => 180,
        'desc' => 'Durasi tier 1 (menit) setelah masa dispensasi',
    ],
    'tier1_rate' => [
        'key' => 'keterlambatan_tier1_rate', 'min' => 0, 'max' => 10000000,
        'desc' => 'Potongan flat tier 1 (Rupiah)',
    ],
    'tier2_interval_menit' => [
        'key' => 'keterlambatan_tier2_interval_menit', 'min' => 1, 'max' => 180,
        'desc' => 'Interval tier 2 (menit) setelah tier 1',
    ],
    'tier2_rate' => [
        'key' => 'keterlambatan_tier2_rate', 'min' => 0, 'max' => 10000000,
        'desc' => 'Potongan tambahan tier 2 per interval (Rupiah)',
    ],
    'maks_jam' => [
        'key' => 'keterlambatan_maks_jam', 'min' => 0.5, 'max' => 24,
        'desc' => 'Batas pembekuan keterlambatan (jam)',
    ],
];

$nilai = [];
foreach ($fields as $name => $meta) {
    if (!isset($_POST[$name]) || !is_numeric($_POST[$name])) {
        echo json_encode(['status' => 'error', 'message' => "Nilai '$name' tidak valid."]);
        exit;
    }
    $v = (float)$_POST[$name];
    if ($v < $meta['min'] || $v > $meta['max']) {
        echo json_encode(['status' => 'error', 'message' => "Nilai '$name' harus antara {$meta['min']} dan {$meta['max']}."]);
        exit;
    }
    $nilai[$name] = $v;
}

if (($nilai['maks_jam'] * 60) <= $nilai['grace_menit']) {
    echo json_encode(['status' => 'error', 'message' => 'Batas maksimal (jam) harus lebih besar dari dispensasi.']);
    exit;
}

$ok = true;
foreach ($fields as $name => $meta) {
    // Simpan sebagai integer utuh kecuali maks_jam yang memang boleh pecahan
    // (mis. "1.5 jam"), lihat getPengaturanKeterlambatan() di
    // keterlambatan_functions.php.
    $simpan = ($name === 'maks_jam') ? $nilai[$name] : (int)$nilai[$name];
    $key = $id_cabang > 0 ? getKunciPengaturanKeterlambatanCabang($id_cabang, $meta['key']) : $meta['key'];
    $ok = $ok && setPengaturan($conn, $key, (string)$simpan, $meta['desc']);
}

if ($ok) {
    logActivity(
        $conn,
        'ubah_kebijakan_keterlambatan',
        ($id_cabang > 0 ? "[Cabang {$cabang_row['nama_cabang']}] " : '[Global] ')
            . "Mengubah kebijakan keterlambatan: dispensasi {$nilai['grace_menit']} menit, "
            . "tier1 {$nilai['tier1_durasi_menit']} menit/Rp{$nilai['tier1_rate']}, "
            . "tier2 tiap {$nilai['tier2_interval_menit']} menit/Rp{$nilai['tier2_rate']}, "
            . "maks {$nilai['maks_jam']} jam",
        $_SESSION['user_id'] ?? null
    );
    echo json_encode(['status' => 'success', 'message' => 'Kebijakan keterlambatan berhasil disimpan.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan kebijakan keterlambatan.']);
}
