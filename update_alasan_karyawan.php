<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function outputJSON($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        outputJSON(['success' => false, 'message' => 'Invalid request method']);
    }

    $id_absensi = isset($_POST['id_absensi']) ? intval($_POST['id_absensi']) : 0;
    $alasan = isset($_POST['alasan']) ? sanitizeInput($_POST['alasan']) : '';

    if (empty($id_absensi)) {
        outputJSON(['success' => false, 'message' => 'ID Absensi tidak valid.']);
    }

    if (empty($alasan)) {
        outputJSON(['success' => false, 'message' => 'Alasan wajib diisi.']);
    }

    // Get current data
    $stmt = $conn->prepare("SELECT id_karyawan, waktu_alasan, foto_bukti FROM absensi WHERE id = ?");
    $stmt->bind_param("i", $id_absensi);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $stmt->close();
        outputJSON(['success' => false, 'message' => 'Data absensi tidak ditemukan.']);
    }

    $data = $result->fetch_assoc();
    $stmt->close();
    
    $waktu_alasan = $data['waktu_alasan'];
    $id_karyawan = $data['id_karyawan'];
    $old_foto = $data['foto_bukti'];

    if (empty($waktu_alasan)) {
        // Should not happen normally if they used the new form
        $waktu_alasan = date('Y-m-d H:i:s');
    }

    // Cek batas waktu 2 jam
    $submitTime = strtotime($waktu_alasan);
    $now = time();
    $diffHours = ($now - $submitTime) / 3600;

    if ($diffHours >= 2) {
        outputJSON(['success' => false, 'message' => 'Batas waktu edit (2 jam) telah habis.']);
    }

    // Handle foto baru jika ada
    $foto_bukti_name = $old_foto;
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
        $upload_dir = __DIR__ . '/assets/uploads/absensi/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        if (in_array($ext, $allowed_ext) && $_FILES['foto_bukti']['size'] <= 6 * 1024 * 1024) {
            $foto_bukti_name = $id_karyawan . '_' . date('Ymd_His') . '_edit_' . uniqid() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $foto_bukti_name)) {
                // Hapus foto lama
                if (!empty($old_foto) && file_exists($upload_dir . $old_foto)) {
                    unlink($upload_dir . $old_foto);
                }
            } else {
                $foto_bukti_name = $old_foto; // revert if upload fails
            }
        } else {
            outputJSON(['success' => false, 'message' => 'Format file tidak didukung atau ukuran melebihi 6MB.']);
        }
    }

    // Update data
    $stmt_update = $conn->prepare("UPDATE absensi SET alasan = ?, foto_bukti = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $alasan, $foto_bukti_name, $id_absensi);
    
    if ($stmt_update->execute()) {
        $stmt_update->close();
        outputJSON([
            'success' => true,
            'message' => 'Alasan berhasil diperbarui.'
        ]);
    } else {
        $stmt_update->close();
        outputJSON(['success' => false, 'message' => 'Gagal memperbarui alasan: ' . $conn->error]);
    }

} catch (Exception $e) {
    error_log("Error in update_alasan_karyawan.php: " . $e->getMessage());
    outputJSON(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
}
?>

