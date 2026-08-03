<?php
require 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_karyawan = sanitizeInput($_POST['id_karyawan'] ?? '');
    $field = sanitizeInput($_POST['field'] ?? '');
    $value = sanitizeInput($_POST['value'] ?? 0);
    
    // Validasi field yang diizinkan untuk diupdate
    $allowed_fields = ['rate_transport', 'rate_overtime', 'rate_insentif_minggu', 'gaji_pokok', 'rate_keterlambatan'];
    
    if (empty($id_karyawan) || !in_array($field, $allowed_fields)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit;
    }
    
    // Update ke database
    $stmt = $conn->prepare("UPDATE karyawan SET $field = ? WHERE id_karyawan = ?");
    $stmt->bind_param("ds", $value, $id_karyawan);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Rate berhasil disimpan sebagai default.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan rate.']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>

