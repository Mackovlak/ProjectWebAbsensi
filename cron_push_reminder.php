<?php
/**
 * Pengingat absen via notifikasi PWA (Web Push). Jalankan lewat cron, CLI saja:
 *
 *   php cron_push_reminder.php masuk    # mis. 07:45 WIB - yang belum absen masuk
 *   php cron_push_reminder.php pulang   # mis. 17:15 WIB - yang sudah masuk tapi belum absen pulang
 *
 * Hanya mengirim di hari kerja non-libur, hanya ke akun aktif yang punya langganan
 * push. Karyawan yang cuti/sakit/izin yang sudah disetujui punya baris absensi
 * (materialisasi) sehingga otomatis tidak diingatkan; Dinas Luar yang disetujui
 * dikecualikan lewat getIzinDinasDisetujui().
 *
 * Ini TERPISAH dari cron_reminder_absensi.php (WhatsApp/Fonnte) - keduanya boleh
 * dijalankan bersamaan atau salah satunya saja.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/config.php';

$mode = $argv[1] ?? '';
if (!in_array($mode, ['masuk', 'pulang'], true)) {
    fwrite(STDERR, "Pemakaian: php cron_push_reminder.php masuk|pulang\n");
    exit(1);
}
if (!pushTersedia()) {
    fwrite(STDERR, "Kunci VAPID belum diisi di .env (lihat generate_vapid_keys.php).\n");
    exit(1);
}

$hari_ini = date('Y-m-d');
if (!isHariKerja($conn, $hari_ini)) {
    echo "Bukan hari kerja, tidak ada pengingat.\n";
    exit(0);
}

if ($mode === 'masuk') {
    $kondisi_absen = "NOT EXISTS (SELECT 1 FROM absensi a WHERE a.id_karyawan = k.id_karyawan AND a.tanggal = ?)";
} else {
    $kondisi_absen = "EXISTS (SELECT 1 FROM absensi a WHERE a.id_karyawan = k.id_karyawan AND a.tanggal = ?
                              AND a.jam_masuk IS NOT NULL AND a.jam_pulang IS NULL
                              AND a.keterangan IN ('Hadir','Dinas Luar'))";
}

$stmt = $conn->prepare("SELECT u.id AS user_id, k.id_karyawan, k.id_cabang
    FROM users u
    JOIN karyawan k ON k.id_karyawan = u.id_karyawan
    WHERE u.is_active = 1 AND k.status = 'aktif'
      AND u.role IN ('staff','supervisor','admin')
      AND EXISTS (SELECT 1 FROM push_subscriptions p WHERE p.user_id = u.id)
      AND $kondisi_absen");
$stmt->bind_param("s", $hari_ini);
$stmt->execute();
$kandidat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$libur_cabang = [];
$penerima = [];
foreach ($kandidat as $row) {
    $cabang = (int)$row['id_cabang'];
    if (!isset($libur_cabang[$cabang])) {
        $libur_cabang[$cabang] = !empty(getHariLibur($conn, $hari_ini, $hari_ini, $cabang));
    }
    if ($libur_cabang[$cabang]) {
        continue;
    }
    if ($mode === 'masuk' && getIzinDinasDisetujui($conn, $row['id_karyawan'], $hari_ini)) {
        continue;
    }
    $penerima[] = (int)$row['user_id'];
}

if (!$penerima) {
    echo "Tidak ada penerima pengingat.\n";
    exit(0);
}

$payload = $mode === 'masuk'
    ? ['title' => 'Jangan lupa absen masuk ⏰', 'body' => 'Anda belum absen masuk hari ini. Ketuk untuk membuka aplikasi.', 'tag' => 'pengingat-masuk']
    : ['title' => 'Jangan lupa absen pulang 🏠', 'body' => 'Anda belum absen pulang. Ketuk untuk membuka aplikasi.', 'tag' => 'pengingat-pulang'];
$payload['url'] = 'login.php';

$terkirim = pushKirimKeUsers($conn, $penerima, $payload);
echo "Pengingat {$mode}: " . count($penerima) . " akun, {$terkirim} perangkat berhasil.\n";
