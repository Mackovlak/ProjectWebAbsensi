<?php
require 'config.php';
requireLogin();

if (!isSupervisor()) {
    $_SESSION['error_message'] = 'Akses ditolak.';
    header('Location: ' . dashboardUntukRole($_SESSION['role'] ?? 'staff'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Permintaan hapus akun tidak valid.';
    header('Location: supervisor_dashboard.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'supervisor'");
$stmt->bind_param('i', $user_id);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    logActivity($conn, 'delete_account', "Supervisor '$username' menghapus akun sendiri", null);
    $stmt->close();
    session_destroy();
    header('Location: login.php?message=supervisor_deleted');
    exit();
}

$stmt->close();
$_SESSION['error_message'] = 'Gagal menghapus akun Supervisor.';
header('Location: supervisor_dashboard.php');
exit();
