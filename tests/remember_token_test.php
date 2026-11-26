<?php
// Uji alur "Tetap masuk di perangkat ini" (remember_functions.php) terhadap
// database sungguhan. Semua perubahan di dalam satu transaksi lalu di-ROLLBACK.
// Butuh migrasi 019. Jalankan di container: docker compose exec web php tests/remember_token_test.php
require __DIR__ . '/../assets/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
require __DIR__ . '/../remember_functions.php';

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new Exception('FAIL: ' . $label);
    $checks++;
}

mysqli_report(MYSQLI_REPORT_OFF);
$env = fn($k, $d) => getenv($k) ?: ($_ENV[$k] ?? '') ?: $d;
$conn = new mysqli($env('DB_HOST', 'localhost'), $env('DB_USER', 'root'), $env('DB_PASS', ''), $env('DB_NAME', 'db_absensi.kry'));
$conn->set_charset('utf8mb4');
if (!$conn->query("SELECT 1 FROM user_remember_tokens LIMIT 1")) {
    fwrite(STDERR, "SKIP: tabel user_remember_tokens belum ada (jalankan migrasi 019).\n");
    exit(0);
}

session_start();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 14) AbsenKitaApp/1.0';

$conn->begin_transaction();
try {
    $uname = 'zz_remember_' . bin2hex(random_bytes(4));
    $hash = password_hash('x', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role, is_active) VALUES ('Uji Remember', ?, ?, 'admin', 1)");
    $stmt->bind_param('ss', $uname, $hash);
    check($stmt->execute(), 'buat user uji');
    $uid = $conn->insert_id;

    $count = function () use ($conn, $uid) {
        return (int)$conn->query("SELECT COUNT(*) c FROM user_remember_tokens WHERE user_id = $uid")->fetch_assoc()['c'];
    };

    check(isAplikasiNative(), 'UA aplikasi terdeteksi');

    // Login + "Tetap masuk" -> satu token, cookie terisi, validator tidak tersimpan mentah.
    check(rememberBuatToken($conn, $uid), 'buat token');
    check($count() === 1, 'satu token tersimpan');
    [$sel, $val] = rememberBacaCookie();
    $row = $conn->query("SELECT validator_hash FROM user_remember_tokens WHERE selector = '$sel'")->fetch_assoc();
    check($row['validator_hash'] === hash('sha256', $val) && $row['validator_hash'] !== $val, 'hanya hash validator di DB');

    // Sesi habis -> dipulihkan dari cookie dengan data akun terbaru.
    $_SESSION = [];
    check(rememberPulihkanSesi($conn) === true, 'sesi dipulihkan');
    check((int)$_SESSION['user_id'] === $uid && $_SESSION['role'] === 'admin' && $_SESSION['username'] === $uname, 'isi sesi benar');
    check(rememberPulihkanSesi($conn) === false, 'tidak memulihkan bila sesi sudah ada');

    // Validator dipalsukan -> ditolak dan token dibuang.
    $_SESSION = [];
    $_COOKIE[REMEMBER_COOKIE] = $sel . ':' . str_repeat('0', 64);
    check(rememberPulihkanSesi($conn) === false && empty($_SESSION['user_id']), 'validator palsu ditolak');
    check($count() === 0, 'token dibuang setelah validator palsu');

    // Akun dinonaktifkan -> token tidak bisa dipakai.
    rememberBuatToken($conn, $uid);
    $conn->query("UPDATE users SET is_active = 0 WHERE id = $uid");
    $_SESSION = [];
    check(rememberPulihkanSesi($conn) === false && empty($_SESSION['user_id']), 'akun nonaktif ditolak');
    $conn->query("UPDATE users SET is_active = 1 WHERE id = $uid");

    // Kedaluwarsa -> ditolak.
    rememberBuatToken($conn, $uid);
    $conn->query("UPDATE user_remember_tokens SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE user_id = $uid");
    $_SESSION = [];
    check(rememberPulihkanSesi($conn) === false, 'token kedaluwarsa ditolak');

    // Batas jumlah perangkat.
    for ($i = 0; $i < REMEMBER_MAKS_PER_USER + 3; $i++) {
        unset($_COOKIE[REMEMBER_COOKIE]); // perangkat berbeda
        rememberBuatToken($conn, $uid);
    }
    check($count() === REMEMBER_MAKS_PER_USER, 'jumlah token dibatasi');

    // Ganti password sendiri: perangkat lain dicabut, perangkat ini tetap.
    rememberCabutSemuaToken($conn, $uid, true);
    check($count() === 1, 'hanya perangkat ini yang tersisa');
    $_SESSION = [];
    check(rememberPulihkanSesi($conn) === true, 'perangkat ini masih bisa masuk');

    // Logout: token perangkat ini dicabut.
    rememberCabutTokenIni($conn);
    check($count() === 0 && !isset($_COOKIE[REMEMBER_COOKIE]), 'logout mencabut token');

    // Cookie rusak diabaikan.
    $_SESSION = [];
    $_COOKIE[REMEMBER_COOKIE] = "x' OR 1=1 --";
    check(rememberPulihkanSesi($conn) === false, 'cookie rusak diabaikan');

    // Akun dihapus -> token ikut terhapus (FK CASCADE).
    rememberBuatToken($conn, $uid);
    $conn->query("DELETE FROM users WHERE id = $uid");
    check($count() === 0, 'hapus akun menghapus token');
} finally {
    $conn->rollback();
}

echo "OK ($checks checks)\n";
