<?php
// PERBAIKAN: Start output buffering di awal sekali
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Deteksi environment (localhost atau production)
    $isLocalhost = (
        $_SERVER['SERVER_NAME'] === 'localhost' || 
        $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
        strpos($_SERVER['SERVER_NAME'], '192.168.') === 0 ||
        strpos($_SERVER['SERVER_NAME'], '10.') === 0
    );
    
    $isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        $_SERVER['SERVER_PORT'] == 443;
    
    // Set session parameters berdasarkan environment
    if (!$isLocalhost && $isHTTPS) {
        // Production dengan HTTPS
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 1);
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    } else {
        // Localhost atau HTTP (development)
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    session_start();
}

// Database configuration
// Falls back to the original hardcoded XAMPP/Laragon-style defaults when no
// env vars are set, so non-Docker local setups are unaffected. Docker Compose
// injects DB_HOST/DB_USER/DB_PASS/DB_NAME to point at the `db` service.
$host = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "";
$database = getenv('DB_NAME') ?: "db_absensi.kry";

// Create connection with error handling
try {
    $conn = new mysqli($host, $username, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
    // Set timezone
    date_default_timezone_set('Asia/Jakarta');
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Error reporting settings - MATIKAN saat production
if ($isLocalhost) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Security: Prevent direct access to config file
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die("Direct access not permitted.");
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Function to check if user is owner
function isOwner() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'owner';
}

// Function to check if user is staff
function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'staff';
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to check login and redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error_message_login'] = "Silakan login terlebih dahulu.";
        redirect("login.php");
    }
}

// Function to check admin access
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error_message'] = "Akses ditolak. Anda tidak memiliki izin admin.";
        redirect("staff_dashboard.php");
    }
}

// Function to check admin or owner access
function requireAdminOrOwner() {
    requireLogin();
    if (!isAdmin() && !isOwner()) {
        $_SESSION['error_message'] = "Akses ditolak. Halaman ini khusus untuk Admin atau Owner.";
        redirect("staff_dashboard.php");
    }
}

// Function to check staff access
function requireStaff() {
    requireLogin();
    if (!isStaff()) {
        $_SESSION['error_message'] = "Akses ditolak. Halaman ini khusus untuk staff.";
        redirect("admin_dashboard.php");
    }
}

// Include security functions
require_once 'security_functions.php';

// Function untuk generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function untuk verify CSRF token
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
}

// Function untuk regenerate session ID (gunakan saat login)
function regenerateSession() {
    session_regenerate_id(true);
}
?>
