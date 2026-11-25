<?php
/**
 * Endpoint langganan Web Push (JSON). Dipanggil assets/js/pwa.js.
 *
 *   GET  ?action=key        -> { publicKey }                (kunci VAPID publik)
 *   POST action=subscribe   -> simpan/pindahkan langganan perangkat ke akun ini
 *   POST action=unsubscribe -> hapus langganan perangkat ini
 *   POST action=detach      -> sama dengan unsubscribe (dipanggil sebelum logout)
 *   POST action=test        -> kirim notifikasi uji ke SEMUA perangkat akun ini
 *
 * POST berbadan JSON dan wajib membawa header X-CSRF-Token.
 */
require 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function keluarJson(array $data, int $kode = 200): void {
    http_response_code($kode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    keluarJson(['success' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'], 401);
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'key') {
        keluarJson(['success' => false, 'message' => 'Aksi tidak dikenal.'], 400);
    }
    $config = pushKonfigurasi();
    if (!$config) {
        keluarJson(['success' => false, 'message' => 'Notifikasi push belum dikonfigurasi di server.'], 503);
    }
    keluarJson(['success' => true, 'publicKey' => $config['public']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    keluarJson(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

// verifyCSRFToken() memakai die() dengan teks biasa; endpoint JSON ini perlu respons JSON.
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    keluarJson(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 403);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    keluarJson(['success' => false, 'message' => 'Permintaan tidak valid.'], 400);
}
$action = (string)($body['action'] ?? '');

if ($action === 'test') {
    if (!pushTersedia()) {
        keluarJson(['success' => false, 'message' => 'Notifikasi push belum dikonfigurasi di server.'], 503);
    }
    $rate = checkRateLimit('push_test', 10);
    if (!$rate['allowed']) {
        keluarJson(['success' => false, 'message' => "Terlalu sering. Coba lagi dalam {$rate['remaining']} detik."], 429);
    }
    $terkirim = pushKirimKeUsers($conn, [$user_id], [
        'title' => 'Notifikasi aktif ✅',
        'body'  => 'Ini notifikasi uji dari AbsenKita Javag.',
        'url'   => 'index.php',
        'tag'   => 'push-test',
    ]);
    keluarJson($terkirim > 0
        ? ['success' => true, 'message' => "Notifikasi uji dikirim ke {$terkirim} perangkat."]
        : ['success' => false, 'message' => 'Belum ada perangkat aktif untuk akun ini. Aktifkan notifikasi terlebih dahulu.']);
}

$endpoint = (string)($body['endpoint'] ?? '');
// Endpoint push service selalu HTTPS; menolak yang lain mencegah request keluar ke host sembarang (SSRF).
if ($endpoint === '' || strlen($endpoint) > 2048 || strpos($endpoint, 'https://') !== 0) {
    keluarJson(['success' => false, 'message' => 'Endpoint langganan tidak valid.'], 400);
}
$hash = hash('sha256', $endpoint);

if ($action === 'unsubscribe' || $action === 'detach') {
    $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?");
    $stmt->bind_param("si", $hash, $user_id);
    $stmt->execute();
    $stmt->close();
    keluarJson(['success' => true]);
}

if ($action === 'subscribe') {
    $p256dh = (string)($body['keys']['p256dh'] ?? '');
    $auth   = (string)($body['keys']['auth'] ?? '');
    if ($p256dh === '' || $auth === '' || strlen($p256dh) > 255 || strlen($auth) > 255) {
        keluarJson(['success' => false, 'message' => 'Kunci langganan tidak valid.'], 400);
    }
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    // Perangkat yang sama bisa dipakai bergantian beberapa akun: kepemilikan dipindah ke akun yang login sekarang.
    $stmt = $conn->prepare("INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh),
                                auth = VALUES(auth), user_agent = VALUES(user_agent)");
    $stmt->bind_param("isssss", $user_id, $endpoint, $hash, $p256dh, $auth, $ua);
    if (!$stmt->execute()) {
        $stmt->close();
        keluarJson(['success' => false, 'message' => 'Gagal menyimpan langganan.'], 500);
    }
    $stmt->close();
    keluarJson(['success' => true]);
}

keluarJson(['success' => false, 'message' => 'Aksi tidak dikenal.'], 400);
