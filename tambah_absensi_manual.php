<?php
/**
 * ==========================================
 * TAMBAH ABSENSI MANUAL - Admin Only
 * Dinia Team - Manual Attendance Entry System
 * ==========================================
 * 
 * VERSION: FIXED - Notifikasi diperbaiki
 */

require 'config.php';

// SECURITY: Hanya admin yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error_message'] = "⛔ Akses ditolak. Hanya admin yang dapat menambahkan absensi manual.";
    header("Location: login.php");
    exit();
}

// Proses form hanya jika method POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Verify CSRF Token
    verifyCSRFToken($_POST['csrf_token']);
    
    // Ambil data dari form
    $id_karyawan = sanitizeInput($_POST['id_karyawan']);
    $tanggal = sanitizeInput($_POST['tanggal']);
    $keterangan = sanitizeInput($_POST['keterangan']);
    $redirect_cabang = intval($_POST['redirect_cabang']);
    $admin_id = isset($_SESSION['id_karyawan']) ? $_SESSION['id_karyawan'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'admin');
    
    // ========================================
    // VALIDASI INPUT
    // ========================================
    
    // 1. Validasi keterangan hanya boleh OFF, Sakit, atau Cuti
    $allowed_keterangan = ['OFF', 'Sakit', 'Cuti'];
    if (!in_array($keterangan, $allowed_keterangan)) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "❌ Keterangan tidak valid. Hanya boleh OFF, Sakit, atau Cuti."]);
            exit();
        }
        $_SESSION['error_message'] = "❌ Keterangan tidak valid. Hanya boleh OFF, Sakit, atau Cuti.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    // 2. Validasi tanggal tidak boleh kosong
    if (empty($tanggal)) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "❌ Tanggal tidak boleh kosong."]);
            exit();
        }
        $_SESSION['error_message'] = "❌ Tanggal tidak boleh kosong.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    // 3. Validasi tanggal tidak boleh masa depan
    $today = date('Y-m-d');
    if ($tanggal > $today) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "❌ Tidak dapat menambahkan absensi untuk tanggal masa depan."]);
            exit();
        }
        $_SESSION['error_message'] = "❌ Tidak dapat menambahkan absensi untuk tanggal masa depan.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    // 4. Validasi ID Karyawan exists
    $check_karyawan = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
    $check_karyawan->bind_param("s", $id_karyawan);
    $check_karyawan->execute();
    $result_karyawan = $check_karyawan->get_result();
    
    if ($result_karyawan->num_rows === 0) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "❌ Karyawan tidak ditemukan."]);
            exit();
        }
        $_SESSION['error_message'] = "❌ Karyawan tidak ditemukan.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    $nama_karyawan = $result_karyawan->fetch_assoc()['nama_karyawan'];
    $check_karyawan->close();
    
    // ========================================
    // CEK DUPLIKASI
    // ========================================
    
    $check_duplicate = $conn->prepare(
        "SELECT id, keterangan, is_manual_entry 
         FROM absensi 
         WHERE id_karyawan = ? AND tanggal = ?"
    );
    $check_duplicate->bind_param("ss", $id_karyawan, $tanggal);
    $check_duplicate->execute();
    $result_duplicate = $check_duplicate->get_result();
    
    if ($result_duplicate->num_rows > 0) {
        $existing = $result_duplicate->fetch_assoc();
        $type = $existing['is_manual_entry'] == 1 ? 'manual' : 'otomatis';
        
        $msg = "⚠️ Absensi sudah ada untuk tanggal ini! Status: " . $existing['keterangan'] . " (Entry " . $type . "). Gunakan fitur Edit jika ingin mengubah.";
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        }
        $_SESSION['error_message'] = $msg;
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    $check_duplicate->close();
    
    // ========================================
    // INSERT DATA MANUAL ENTRY
    // ========================================
    
    $conn->begin_transaction();
    
    try {
        // PERBAIKAN: Query simplified
        $stmt = $conn->prepare(
            "INSERT INTO absensi 
             (id_karyawan, tanggal, keterangan, is_manual_entry, manual_entry_by) 
             VALUES 
             (?, ?, ?, 1, ?)"
        );
        
        $stmt->bind_param("ssss", $id_karyawan, $tanggal, $keterangan, $admin_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan data: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Log aktivitas admin
        $log_description = "Manual entry: {$keterangan} untuk {$nama_karyawan} ({$id_karyawan}) tanggal {$tanggal}";
        logActivity($conn, 'manual_attendance_entry', $log_description, $admin_id);
        
        $conn->commit();
        
        // PERBAIKAN: Success message tanpa HTML tags
        $tanggal_formatted = date('d-m-Y', strtotime($tanggal));
        $msg = "✅ Absensi manual berhasil ditambahkan! " . $nama_karyawan . " - " . $keterangan . " pada tanggal " . $tanggal_formatted;
        
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => $msg]);
            exit();
        }
        $_SESSION['success_message'] = $msg;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Manual Entry Error: " . $e->getMessage());
        
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "❌ Terjadi kesalahan: " . $e->getMessage()]);
            exit();
        }
        $_SESSION['error_message'] = "❌ Terjadi kesalahan: " . $e->getMessage();
    }
    
} else {
    if (isset($_POST['is_ajax'])) {
        echo json_encode(['status' => 'error', 'message' => "❌ Method tidak valid."]);
        exit();
    }
    $_SESSION['error_message'] = "❌ Method tidak valid.";
}

header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
exit();
?>
