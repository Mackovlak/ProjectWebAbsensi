<?php
/**
 * Login tetap tersimpan per perangkat ("Tetap masuk di perangkat ini").
 *
 * Sesi PHP sengaja pendek (lihat config.php). Supaya karyawan tidak harus login
 * ulang tiap jam di HP, perangkat yang memilih "Tetap masuk" menerima cookie
 * HttpOnly berisi "selector:validator":
 *   - selector  : 12 byte acak (hex) - mencari baris di user_remember_tokens.
 *   - validator : 32 byte acak (hex) - hanya SHA-256-nya yang disimpan di DB,
 *                 dibandingkan dengan hash_equals().
 * Saat sesi habis, config.php memanggil rememberPulihkanSesi() yang mengisi
 * ulang sesi dari baris tersebut, dengan pengecekan akun yang SAMA seperti
 * login.php (akun aktif + karyawan aktif) dan data peran yang terbaru dari DB.
 *
 * Validator sengaja TIDAK dirotasi tiap pemakaian: rotasi menimbulkan race saat
 * beberapa request paralel membawa cookie lama (tab dipulihkan, aplikasi dibuka
 * dari notifikasi) dan berakhir dengan logout palsu. Sebagai gantinya masa
 * berlaku digeser (sliding) paling sering sekali sehari, dan token dicabut saat
 * logout, ganti password, akun dinonaktifkan, atau akun dihapus (FK CASCADE).
 *
 * Semua fungsi aman dipanggil sebelum migrasi 019 dijalankan: prepare() gagal
 * dan fungsi diam-diam tidak melakukan apa-apa.
 *
 * Dimuat oleh config.php.
 */

const REMEMBER_COOKIE = 'absenkita_ingat';
const REMEMBER_HARI = 30;
const REMEMBER_MAKS_PER_USER = 10;

function rememberIsHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
}

function rememberSetCookie($nilai, $expires) {
    if (headers_sent()) {
        return;
    }
    setcookie(REMEMBER_COOKIE, $nilai, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => rememberIsHttps(),
        'httponly' => true,
        // Lax, bukan Strict: aplikasi sering dibuka dari tautan WhatsApp/SMS
        // atau notifikasi; dengan Strict cookie tidak terkirim pada navigasi
        // itu dan pengguna tampak ter-logout.
        'samesite' => 'Lax',
    ]);
}

function rememberHapusCookie() {
    unset($_COOKIE[REMEMBER_COOKIE]);
    rememberSetCookie('', time() - 42000);
}

/** @return array{0:string,1:string}|null */
function rememberBacaCookie() {
    $raw = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!is_string($raw) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    return [$m[1], $m[2]];
}

/** Apakah request ini datang dari aplikasi Android/iOS (cangkang Capacitor di mobile/). */
function isAplikasiNative() {
    return strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'AbsenKitaApp/') !== false;
}

/**
 * Isi $_SESSION untuk user yang baru terautentikasi. Dipakai login.php dan
 * pemulihan sesi supaya keduanya selalu menghasilkan sesi yang identik.
 */
function isiSesiLogin(array $user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['jenis_kelamin'] = $user['jenis_kelamin'] ?? 'L';
    $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;
    $_SESSION['login_time'] = time();

    // Semua akun yang tertaut karyawan (staff/admin/supervisor)
    // membawa ID karyawan untuk biodata dan pembatasan akses.
    if (!empty($user['id_karyawan'])) {
        $_SESSION['id_karyawan'] = $user['id_karyawan'];
    } else {
        unset($_SESSION['id_karyawan']);
    }
}

/** Buat token untuk perangkat ini dan kirim cookie-nya. Dipanggil setelah login sukses. */
function rememberBuatToken($conn, $user_id) {
    $user_id = (int)$user_id;

    // Bersihkan token kedaluwarsa milik user ini dan batasi jumlah perangkat.
    $stmt = $conn->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? AND expires_at < NOW()");
    if (!$stmt) {
        return false; // migrasi 019 belum dijalankan
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    // Sisakan ruang untuk token baru: buang yang paling lama tidak dipakai.
    $stmt = $conn->prepare("SELECT id FROM user_remember_tokens WHERE user_id = ? ORDER BY last_used_at DESC, id DESC");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $stmt->close();
        $lama = array_slice($ids, REMEMBER_MAKS_PER_USER - 1);
        foreach ($lama as $id_lama) {
            $del = $conn->prepare("DELETE FROM user_remember_tokens WHERE id = ?");
            $del->bind_param('i', $id_lama);
            $del->execute();
            $del->close();
        }
    }

    // Cookie lama dari akun lain di perangkat yang sama tidak boleh tertinggal.
    rememberCabutTokenIni($conn);

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $expires = time() + REMEMBER_HARI * 86400;
    $expires_sql = date('Y-m-d H:i:s', $expires);

    $stmt = $conn->prepare("INSERT INTO user_remember_tokens (user_id, selector, validator_hash, user_agent, ip_address, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isssss', $user_id, $selector, $hash, $ua, $ip, $expires_sql);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        rememberSetCookie($selector . ':' . $validator, $expires);
        $_COOKIE[REMEMBER_COOKIE] = $selector . ':' . $validator;
    }
    return $ok;
}

/**
 * Pulihkan sesi dari cookie bila belum ada sesi login. Mengembalikan true bila
 * sesi berhasil dipulihkan. Dipanggil sekali oleh config.php.
 */
function rememberPulihkanSesi($conn) {
    if (!empty($_SESSION['user_id'])) {
        return false;
    }
    $cookie = rememberBacaCookie();
    if ($cookie === null) {
        if (isset($_COOKIE[REMEMBER_COOKIE])) {
            rememberHapusCookie(); // format rusak
        }
        return false;
    }
    [$selector, $validator] = $cookie;

    $stmt = $conn->prepare("SELECT t.id AS token_id, t.validator_hash, t.last_used_at, t.expires_at,
                                   u.id, u.nama, u.username, u.role, u.is_active, u.id_karyawan,
                                   u.jenis_kelamin, u.foto_profil, k.status AS status_karyawan
                            FROM user_remember_tokens t
                            JOIN users u ON u.id = t.user_id
                            LEFT JOIN karyawan k ON u.id_karyawan = k.id_karyawan
                            WHERE t.selector = ? LIMIT 1");
    if (!$stmt) {
        return false; // migrasi 019 belum dijalankan
    }
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $hapusToken = function () use ($conn, $selector) {
        $del = $conn->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
        $del->bind_param('s', $selector);
        $del->execute();
        $del->close();
        rememberHapusCookie();
    };

    if (!$row) {
        rememberHapusCookie();
        return false;
    }
    // Selector cocok tapi validator salah = cookie dipalsukan/dicuri: buang tokennya.
    if (!hash_equals($row['validator_hash'], hash('sha256', $validator))
        || strtotime($row['expires_at']) < time()
        || !(bool)$row['is_active']
        || (!empty($row['id_karyawan']) && $row['status_karyawan'] !== 'aktif')) {
        $hapusToken();
        return false;
    }

    // Sesi baru, ID baru (mencegah session fixation), lalu isi seperti login.php.
    session_regenerate_id(true);
    isiSesiLogin($row);
    $_SESSION['login_via_remember'] = true;

    // Geser masa berlaku paling sering sekali sehari.
    if (strtotime($row['last_used_at']) < time() - 86400) {
        $expires = time() + REMEMBER_HARI * 86400;
        $expires_sql = date('Y-m-d H:i:s', $expires);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $token_id = (int)$row['token_id'];
        $upd = $conn->prepare("UPDATE user_remember_tokens SET last_used_at = NOW(), expires_at = ?, ip_address = ? WHERE id = ?");
        $upd->bind_param('ssi', $expires_sql, $ip, $token_id);
        $upd->execute();
        $upd->close();
        rememberSetCookie($selector . ':' . $validator, $expires);
    }

    $uid = (int)$row['id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $log = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, login_time, status) VALUES (?, ?, NOW(), 'success')");
    if ($log) {
        $log->bind_param('is', $uid, $ip);
        $log->execute();
        $log->close();
    }
    return true;
}

/** Cabut token perangkat ini (logout). */
function rememberCabutTokenIni($conn) {
    $cookie = rememberBacaCookie();
    if ($cookie !== null) {
        $stmt = $conn->prepare("DELETE FROM user_remember_tokens WHERE selector = ?");
        if ($stmt) {
            $stmt->bind_param('s', $cookie[0]);
            $stmt->execute();
            $stmt->close();
        }
    }
    if (isset($_COOKIE[REMEMBER_COOKIE])) {
        rememberHapusCookie();
    }
}

/**
 * Cabut semua token user (ganti/reset password, akun dinonaktifkan).
 * $kecualiPerangkatIni = true mempertahankan perangkat yang sedang dipakai,
 * dipakai saat user mengganti password-nya sendiri.
 */
function rememberCabutSemuaToken($conn, $user_id, $kecualiPerangkatIni = false) {
    $user_id = (int)$user_id;
    $cookie = $kecualiPerangkatIni ? rememberBacaCookie() : null;
    if ($cookie !== null) {
        $stmt = $conn->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? AND selector <> ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('is', $user_id, $cookie[0]);
    } else {
        $stmt = $conn->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $stmt->close();
}
