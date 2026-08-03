<?php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

$id_karyawan = isset($_POST['id_karyawan']) ? sanitizeInput($_POST['id_karyawan']) : '';
$username_custom = isset($_POST['username_custom']) ? sanitizeInput($_POST['username_custom']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$konfirmasi_password = isset($_POST['konfirmasi_password']) ? $_POST['konfirmasi_password'] : '';

if (empty($id_karyawan) || empty($password) || empty($konfirmasi_password)) {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
    exit();
}

if ($password !== $konfirmasi_password) {
    echo json_encode(['success' => false, 'message' => 'Password dan konfirmasi password tidak cocok!']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']);
    exit();
}

// Format username
$username_custom = trim(strtolower($username_custom));
// Pastikan tidak ada spasi jika diisi
if (!empty($username_custom) && preg_match('/\s/', $username_custom)) {
    echo json_encode(['success' => false, 'message' => 'Username tidak boleh mengandung spasi.']);
    exit();
}

$username_to_insert = !empty($username_custom) ? $username_custom : $id_karyawan;

// Cek apakah karyawan valid
$stmt = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("s", $id_karyawan);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Data Karyawan tidak ditemukan.']);
    exit();
}
$karyawan = $result->fetch_assoc();
$nama_karyawan = $karyawan['nama_karyawan'];
$stmt->close();

// Cek apakah akun sudah ada (id_karyawan nya)
$stmt = $conn->prepare("SELECT id FROM users WHERE id_karyawan = ?");
$stmt->bind_param("s", $id_karyawan);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Akun untuk karyawan ini sudah ada!']);
    exit();
}
$stmt->close();

// Cek apakah username custom sudah dipakai
if (!empty($username_custom)) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username_custom);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username tersebut sudah terpakai. Silakan pilih username lain.']);
        exit();
    }
    $stmt->close();
}

// Buat akun baru
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = 'staff';

$stmt = $conn->prepare("INSERT INTO users (nama, username, password, role, id_karyawan) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $nama_karyawan, $username_to_insert, $hashed_password, $role, $id_karyawan);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Akun karyawan berhasil dibuat! Silakan login.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal membuat akun: ' . $conn->error]);
}
$stmt->close();
?>

