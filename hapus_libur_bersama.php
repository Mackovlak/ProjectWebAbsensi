<?php
/**
 * ==========================================
 * BATALKAN LIBUR BERSAMA - Admin Only
 * Dinia Team - Bulk Manual Attendance Deletion System
 * ==========================================
 */

require 'config.php';

// SECURITY: Hanya admin yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    $_SESSION['error_message'] = "⛔ Akses ditolak. Hanya admin yang dapat menghapus absensi.";
    header("Location: login.php");
    exit();
}

// Proses form hanya jika method POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Verify CSRF Token
    verifyCSRFToken($_POST['csrf_token']);
    
    // Ambil data dari form
    $id_cabang_target = sanitizeInput($_POST['id_cabang_target']); // 'all' atau ID cabang
    $tanggal_mulai = sanitizeInput($_POST['tanggal_mulai']);
    $tanggal_selesai = sanitizeInput($_POST['tanggal_selesai']);
    $redirect_cabang = intval($_POST['redirect_cabang']);
    $admin_id = isset($_SESSION['id_karyawan']) ? $_SESSION['id_karyawan'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'admin');
    
    // ========================================
    // VALIDASI INPUT
    // ========================================
    
    if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
        $_SESSION['error_message'] = "❌ Tanggal Mulai dan Selesai tidak boleh kosong.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    if ($tanggal_mulai > $tanggal_selesai) {
        $_SESSION['error_message'] = "❌ Tanggal Mulai tidak boleh lebih besar dari Tanggal Selesai.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    // ========================================
    // PROSES DELETE BULK
    // ========================================
    $conn->begin_transaction();
    
    try {
        if ($id_cabang_target === 'all' || empty($id_cabang_target)) {
            // Hapus untuk semua cabang (semua karyawan)
            $stmt_delete = $conn->prepare(
                "DELETE FROM absensi 
                 WHERE (keterangan = 'OFF' OR keterangan = 'Cuti') 
                 AND is_manual_entry = 1 
                 AND tanggal >= ? AND tanggal <= ?"
            );
            $stmt_delete->bind_param("ss", $tanggal_mulai, $tanggal_selesai);
        } else {
            // Hapus hanya untuk cabang tertentu (join dengan tabel karyawan)
            $stmt_delete = $conn->prepare(
                "DELETE a FROM absensi a
                 INNER JOIN karyawan k ON a.id_karyawan = k.id_karyawan
                 WHERE (a.keterangan = 'OFF' OR a.keterangan = 'Cuti') 
                 AND a.is_manual_entry = 1 
                 AND a.tanggal >= ? AND a.tanggal <= ?
                 AND k.id_cabang = ?"
            );
            $stmt_delete->bind_param("ssi", $tanggal_mulai, $tanggal_selesai, $id_cabang_target);
        }
        
        $stmt_delete->execute();
        $deleted_count = $stmt_delete->affected_rows;
        $stmt_delete->close();
        
        // Log aktivitas admin
        $log_description = "Bulk Delete Libur Bersama dari {$tanggal_mulai} s/d {$tanggal_selesai}. (Total dihapus: {$deleted_count} data)";
        logActivity($conn, 'manual_attendance_delete_bulk', $log_description, $admin_id);
        
        $conn->commit();
        
        if ($deleted_count > 0) {
            $_SESSION['success_message'] = "✅ Berhasil membatalkan Cuti Bersama! Sebanyak $deleted_count data absensi OFF/Cuti telah dihapus dari sistem.";
        } else {
            $_SESSION['error_message'] = "ℹ️ Tidak ada data Cuti Bersama (manual entry) yang ditemukan pada rentang tanggal dan cabang tersebut.";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Bulk Delete Error: " . $e->getMessage());
        $_SESSION['error_message'] = "❌ Terjadi kesalahan saat memproses pembatalan massal: " . $e->getMessage();
    }
    
} else {
    $_SESSION['error_message'] = "❌ Method tidak valid.";
}

header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
exit();
?>
