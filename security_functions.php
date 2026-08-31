<?php
require __DIR__ . '/assets/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
/**
 * Security Helper Functions
 * Include this file in config.php
 */

// Function untuk validate password strength
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password minimal 8 karakter";
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        $errors[] = "Password harus mengandung huruf";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password harus mengandung angka";
    }
    
    return $errors;
}

//function get Registration Salt Key
function getRegistrationKey(string $role): string {
    $allowedRoles = ['admin', 'owner', 'supervisor'];

    if (!in_array($role, $allowedRoles, true)){
        throw new InvalidArgumentException('Role Registrasi tidak valid');
    }

    $masterKey = $_ENV["REGISTRATION_MASTER_KEY"];
    if($masterKey === false || strlen($masterKey) < 32){
        throw new RuntimeException(
            'REGISTRATION_MASTER_KEY belum dikonfigurasi dengan aman. (Minimal 32 Character)'
        );
    }
    return hash_hkdf(
        'sha256',
        $masterKey,
        32,
        'project-web-absensi:registration:' . $role
    );
}

function generateRegistrationToken(
    string $role,
    ?int $timestamp = null
): string {
    $timestamp ??=time();

    //15 minutes change
    $timeSlot = intdiv($timestamp, 900);
    $roleKey = getRegistrationKey($role);

    $hash = hash_hmac(
        'sha256',
        $role . '|' . $timeSlot,
        $roleKey
    );

    return strtoupper(substr($hash, 0, 6));
}

function verifyRegistrationToken(
    string $role,
    string $submittedToken 
): bool {
    $submittedToken = strtoupper(trim($submittedToken));
    //current and previous 15-minutes window
    foreach([time(), time()-900] as $timestamp){
        $expectedToken = generateRegistrationToken($role, $timestamp);

        if(hash_equals($expectedToken, $submittedToken)){
            return true;
        }
    }
    return false;
}
// Function untuk rate limiting
function checkRateLimit($action, $limit_seconds = 30) {
    $session_key = 'rate_limit_' . $action;
    
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = 0;
    }
    
    $time_passed = time() - $_SESSION[$session_key];
    
    if ($time_passed < $limit_seconds) {
        return [
            'allowed' => false,
            'remaining' => $limit_seconds - $time_passed
        ];
    }
    
    $_SESSION[$session_key] = time();
    return ['allowed' => true];
}

// Function untuk sanitize output
function safe_output($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Function untuk validate ID Karyawan format
function validateIDKaryawan($id) {
    // Format: YYYYMMDDXXX (11 karakter)
    return preg_match('/^[0-9]{11}$/', $id);
}

// Salt rahasia untuk kode registrasi supervisor, disimpan di system_settings
// (bukan hardcode di source) supaya tidak ikut ter-commit ke repo publik.
// Dibuat otomatis sekali lalu dipakai ulang lewat cache getPengaturan().
function getSupervisorSecretSalt($conn) {
    $salt = getPengaturan($conn, 'supervisor_secret_salt', null);
    if (empty($salt)) {
        $salt = bin2hex(random_bytes(32));
        setPengaturan($conn, 'supervisor_secret_salt', $salt, 'Salt rahasia kode registrasi supervisor (auto-generated)');
    }
    return $salt;
}

// Function untuk log aktivitas
function logActivity($conn, $action, $description, $user_id = null) {
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (id_karyawan, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $user_id, $action, $description, $ip, $user_agent);
    $stmt->execute();
    $stmt->close();
}

/**
 * Menghitung jarak antara 2 titik koordinat menggunakan Haversine Formula
 * @param float $lat1 Latitude titik 1
 * @param float $lon1 Longitude titik 1
 * @param float $lat2 Latitude titik 2
 * @param float $lon2 Longitude titik 2
 * @return float Jarak dalam meter
 */
if (!function_exists('calculateDistance')) {
    function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        // Radius bumi dalam meter
        $earthRadius = 6371000;
        
        // Konversi derajat ke radian
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);
        
        // Hitung perbedaan
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        // Haversine formula
        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));
        
        // Return jarak dalam meter
        return round($earthRadius * $angle, 2);
    }
}

/**
 * Validasi apakah lokasi karyawan dalam radius yang diizinkan
 * @param string $lokasi_karyawan Format: "latitude,longitude"
 * @param string $id_karyawan ID karyawan
 * @param mysqli $conn Database connection
 * @return array ['valid' => bool, 'message' => string, 'jarak' => float]
 */
if (!function_exists('validateLokasiAbsen')) {
    function validateLokasiAbsen($lokasi_karyawan, $id_karyawan, $conn) {
        // Cek apakah lokasi terdeteksi
        if (empty($lokasi_karyawan) || strpos($lokasi_karyawan, ',') === false) {
            return [
                'valid' => false,
                'message' => 'Lokasi GPS tidak terdeteksi. Pastikan GPS aktif dan izin lokasi diberikan.',
                'jarak' => 0,
                'radius' => 0
            ];
        }
        
        // Parse koordinat karyawan
        list($lat_karyawan, $lon_karyawan) = explode(',', $lokasi_karyawan);
        $lat_karyawan = floatval(trim($lat_karyawan));
        $lon_karyawan = floatval(trim($lon_karyawan));
        
        // Validasi format koordinat
        if ($lat_karyawan == 0 || $lon_karyawan == 0) {
            return [
                'valid' => false,
                'message' => 'Koordinat GPS tidak valid. Coba lagi.',
                'jarak' => 0,
                'radius' => 0
            ];
        }
        
        // Ambil data cabang karyawan
        $stmt = $conn->prepare("
            SELECT c.latitude, c.longitude, c.radius_meter, c.nama_cabang
            FROM cabang c
            JOIN karyawan k ON k.id_cabang = c.id
            WHERE k.id_karyawan = ?
        ");
        $stmt->bind_param("s", $id_karyawan);
        $stmt->execute();
        $cabang_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Cek apakah cabang punya koordinat
        if (!$cabang_data) {
            return [
                'valid' => false,
                'message' => 'Data cabang tidak ditemukan.',
                'jarak' => 0,
                'radius' => 0
            ];
        }
        
        // Jika cabang belum setting koordinat, izinkan absen (backward compatibility)
        if (empty($cabang_data['latitude']) || empty($cabang_data['longitude'])) {
            return [
                'valid' => true,
                'message' => 'Validasi lokasi dinonaktifkan untuk cabang ini.',
                'jarak' => 0,
                'radius' => 0,
                'bypass' => true
            ];
        }
        
        $lat_cabang = floatval($cabang_data['latitude']);
        $lon_cabang = floatval($cabang_data['longitude']);
        $radius_allowed = intval($cabang_data['radius_meter']);
        $nama_cabang = $cabang_data['nama_cabang'];
        
        // Hitung jarak
        $jarak = calculateDistance($lat_karyawan, $lon_karyawan, $lat_cabang, $lon_cabang);
        
        // Validasi jarak
        if ($jarak > $radius_allowed) {
            return [
                'valid' => false,
                'message' => "Anda berada " . round($jarak) . " meter dari " . $nama_cabang . ". Absensi hanya dapat dilakukan dalam radius " . $radius_allowed . " meter dari kantor.",
                'jarak' => $jarak,
                'radius' => $radius_allowed,
                'nama_cabang' => $nama_cabang
            ];
        }
        
        // Lokasi valid
        return [
            'valid' => true,
            'message' => 'Lokasi valid - ' . round($jarak) . ' meter dari kantor.',
            'jarak' => $jarak,
            'radius' => $radius_allowed,
            'nama_cabang' => $nama_cabang
        ];
    }
}
?>
