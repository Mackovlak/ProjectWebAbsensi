<?php
require 'config.php';

// Check owner access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'owner') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Hapus akun owner
$stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'owner'");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    // Log activity sebelum destroy session
    logActivity($conn, 'delete_account', "Owner '$username' menghapus akun sendiri", null);
    
    // Destroy session dan redirect
    session_destroy();
    header("Location: login.php?message=owner_deleted");
    exit();
} else {
    $_SESSION['error_message'] = "Gagal menghapus akun.";
    header("Location: owner_dashboard.php");
    exit();
}
?> 
