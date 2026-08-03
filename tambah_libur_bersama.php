<?php
/**
 * ==========================================
 * TAMBAH LIBUR BERSAMA - Admin Only
 * Dinia Team - Bulk Manual Attendance Entry System
 * ==========================================
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
    $id_cabang_target = sanitizeInput($_POST['id_cabang_target']); // 'all' atau ID cabang
    $tanggal_mulai = sanitizeInput($_POST['tanggal_mulai']);
    $tanggal_selesai = sanitizeInput($_POST['tanggal_selesai']);
    $keterangan = sanitizeInput($_POST['keterangan']);
    $catatan_bebas = sanitizeInput($_POST['catatan_bebas']);
    $redirect_cabang = intval($_POST['redirect_cabang']);
    $admin_id = isset($_SESSION['id_karyawan']) ? $_SESSION['id_karyawan'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'admin');
    
    // ========================================
    // VALIDASI INPUT
    // ========================================
    
    // 1. Validasi keterangan
    $allowed_keterangan = ['OFF', 'Cuti'];
    if (!in_array($keterangan, $allowed_keterangan)) {
        $_SESSION['error_message'] = "❌ Keterangan tidak valid. Hanya boleh OFF atau Cuti.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }
    
    // 2. Validasi tanggal
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
    // AMBIL DATA KARYAWAN
    // ========================================
    $karyawan_list = [];
    if ($id_cabang_target === 'all' || empty($id_cabang_target)) {
        $stmt_karyawan = $conn->prepare("SELECT id_karyawan, nama_karyawan FROM karyawan WHERE status = 'aktif'");
        $stmt_karyawan->execute();
        $result_karyawan = $stmt_karyawan->get_result();
    } else {
        $stmt_karyawan = $conn->prepare("SELECT id_karyawan, nama_karyawan FROM karyawan WHERE id_cabang = ? AND status = 'aktif'");
        $stmt_karyawan->bind_param("i", $id_cabang_target);
        $stmt_karyawan->execute();
        $result_karyawan = $stmt_karyawan->get_result();
    }
    
    while ($row = $result_karyawan->fetch_assoc()) {
        $karyawan_list[] = $row;
    }
    
    if (isset($stmt_karyawan)) $stmt_karyawan->close();
    
    if (empty($karyawan_list)) {
        $_SESSION['error_message'] = "❌ Tidak ada karyawan aktif ditemukan pada target cabang tersebut.";
        header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
        exit();
    }

    // ========================================
    // PROSES INSERT BULK
    // ========================================
    $conn->begin_transaction();
    
    try {
        $insert_count = 0;
        $skip_count = 0;
        
        $stmt_check = $conn->prepare("SELECT id FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
        
        // Prepare insert dengan tambahan alasan
        $waktu_alasan = date('Y-m-d H:i:s');
        $stmt_insert = $conn->prepare(
            "INSERT INTO absensi 
             (id_karyawan, tanggal, keterangan, alasan, waktu_alasan, is_manual_entry, manual_entry_by) 
             VALUES (?, ?, ?, ?, ?, 1, ?)"
        );

        $start_ts = strtotime($tanggal_mulai);
        $end_ts = strtotime($tanggal_selesai);
        
        foreach ($karyawan_list as $karyawan) {
            $id_kar = $karyawan['id_karyawan'];
            
            // Loop setiap hari dalam rentang tanggal
            for ($current_ts = $start_ts; $current_ts <= $end_ts; $current_ts = strtotime('+1 day', $current_ts)) {
                $tgl_insert = date('Y-m-d', $current_ts);
                
                // Cek duplikasi
                $stmt_check->bind_param("ss", $id_kar, $tgl_insert);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows > 0) {
                    // Skip jika sudah ada absen (hadir/off/lainnya) di tanggal tersebut
                    $skip_count++;
                } else {
                    // Insert baru
                    $stmt_insert->bind_param("ssssss", $id_kar, $tgl_insert, $keterangan, $catatan_bebas, $waktu_alasan, $admin_id);
                    if ($stmt_insert->execute()) {
                        $insert_count++;
                    } else {
                        throw new Exception("Gagal menyimpan data untuk karyawan {$id_kar} tgl {$tgl_insert}");
                    }
                }
            }
        }
        
        $stmt_check->close();
        $stmt_insert->close();
        
        // Log aktivitas admin
        $log_description = "Bulk entry Libur Bersama: {$keterangan} ({$catatan_bebas}) dari {$tanggal_mulai} s/d {$tanggal_selesai}. (Berhasil: {$insert_count}, Dilewati: {$skip_count})";
        logActivity($conn, 'manual_attendance_bulk', $log_description, $admin_id);
        
        $conn->commit();
        
        $_SESSION['success_message'] = "✅ Libur Bersama berhasil ditambahkan! $insert_count data tersimpan. $skip_count data dilewati karena bentrok (sudah memiliki absen).";
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Bulk Entry Error: " . $e->getMessage());
        $_SESSION['error_message'] = "❌ Terjadi kesalahan saat memproses bulk insert: " . $e->getMessage();
    }
    
} else {
    $_SESSION['error_message'] = "❌ Method tidak valid.";
}

header("Location: histori_absensi.php?cabang=" . $redirect_cabang);
exit();
?>
