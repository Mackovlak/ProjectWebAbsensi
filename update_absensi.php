<?php
require 'config.php';

// Cek apakah user sudah login dan adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    if (isset($_POST['is_ajax'])) {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Hanya admin yang dapat mengedit absensi.']);
        exit();
    }
    $_SESSION['error_message'] = "Akses ditolak. Hanya admin yang dapat mengedit absensi.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_absensi = intval($_POST['id_absensi']);
    $jam_masuk = sanitizeInput($_POST['jam_masuk']);
    $jam_keluar = sanitizeInput($_POST['jam_keluar']);
    $keterangan = sanitizeInput($_POST['keterangan']);
    $redirect_cabang = intval($_POST['redirect_cabang']);

    // Validasi input
    if (empty($jam_masuk)) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Jam masuk tidak boleh kosong.']);
            exit();
        }
        $_SESSION['error_message'] = "Jam masuk tidak boleh kosong.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    // ================== PERUBAHAN DIMULAI DI SINI ==================

    $status_masuk_log = ''; // Untuk logging

    // Logika baru: Pisahkan query berdasarkan keterangan
    if ($keterangan === 'Hadir') {
        // Jika "Hadir", ambil status dari form
        $status_masuk = isset($_POST['status_masuk']) ? sanitizeInput($_POST['status_masuk']) : 'Tepat Waktu';
        
        $sql = "UPDATE absensi 
                SET jam_masuk = ?, 
                    jam_pulang = ?, 
                    keterangan = ?,
                    status_masuk = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $jam_masuk, $jam_keluar, $keterangan, $status_masuk, $id_absensi);
        
        $status_masuk_log = $status_masuk; // set untuk log

    } else {
        // Jika bukan "Hadir", set status_masuk ke NULL langsung di query
        $sql = "UPDATE absensi 
                SET jam_masuk = ?, 
                    jam_pulang = ?, 
                    keterangan = ?,
                    status_masuk = NULL
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        // bind_param juga disesuaikan karena status_masuk tidak lagi jadi parameter
        $stmt->bind_param("sssi", $jam_masuk, $jam_keluar, $keterangan, $id_absensi);

        $status_masuk_log = 'N/A'; // set untuk log
    }

    // =================== PERUBAHAN SELESAI =====================

    if ($stmt->execute()) {
        logActivity($conn, 'edit_absensi', "Edit absensi ID $id_absensi - $keterangan ($status_masuk_log)", null);
        
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Data absensi berhasil diperbarui.']);
            exit();
        }
        
        $_SESSION['success_message'] = "Data absensi berhasil diperbarui.";
    } else {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "Gagal memperbarui data absensi: " . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Gagal memperbarui data absensi: " . $stmt->error;
    }
    
    $stmt->close();
}

if (isset($_POST['is_ajax'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit();
}

// Redirect kembali ke halaman histori absensi
header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
exit();
?>
