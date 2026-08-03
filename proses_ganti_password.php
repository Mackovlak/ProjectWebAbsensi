<?php
require 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] != 'staff') {
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$username_baru = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
$password_baru = isset($_POST['password_baru']) ? $_POST['password_baru'] : '';
$konfirmasi_password = isset($_POST['konfirmasi_password']) ? $_POST['konfirmasi_password'] : '';

// Validasi username
if (!empty($username_baru) && $username_baru !== $_SESSION['username']) {
    $username_baru = trim(strtolower($username_baru));
    if (preg_match('/\s/', $username_baru)) {
        echo json_encode(['success' => false, 'message' => 'Username tidak boleh mengandung spasi.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username_baru, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username tersebut sudah terpakai.']);
        exit();
    }
    $stmt->close();
}

$update_parts = [];
$params = [];
$types = "";

if (!empty($username_baru) && $username_baru !== $_SESSION['username']) {
    $update_parts[] = "username = ?";
    $params[] = $username_baru;
    $types .= "s";
}

if (!empty($password_baru)) {
    if ($password_baru !== $konfirmasi_password) {
        echo json_encode(['success' => false, 'message' => 'Password baru dan konfirmasi tidak cocok!']);
        exit();
    }
    if (strlen($password_baru) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']);
        exit();
    }
    $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
    $update_parts[] = "password = ?";
    $params[] = $hashed_password;
    $types .= "s";
}

if (empty($update_parts)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada perubahan yang disimpan.']);
    exit();
}

$sql = "UPDATE users SET " . implode(", ", $update_parts) . " WHERE id = ?";
$params[] = $user_id;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if (!empty($username_baru) && $username_baru !== $_SESSION['username']) {
        $_SESSION['username'] = $username_baru;
    }
    echo json_encode(['success' => true, 'message' => 'Pengaturan akun berhasil diperbarui.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui pengaturan: ' . $conn->error]);
}

$stmt->close();
?>

