<?php
ob_start(); // Tangkap semua output (termasuk warning) agar tidak merusak JSON
session_start();
require_once 'config.php';

function sendJson($data) {
    ob_clean(); // Bersihkan semua output yang tertangkap sebelum ini
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    sendJson(['success' => false, 'message' => 'Unauthorized access.']);
}

$id_karyawan = $_SESSION['id_karyawan'] ?? '';
if (empty($id_karyawan)) {
    sendJson(['success' => false, 'message' => 'ID Karyawan tidak ditemukan di sesi.']);
}

$action = $_POST['action'] ?? '';

if ($action === 'upload_foto') {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        
        if (!in_array($ext, $allowed)) {
            sendJson(['success' => false, 'message' => 'Hanya format JPG, JPEG, dan PNG yang diizinkan.']);
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            sendJson(['success' => false, 'message' => 'Ukuran foto maksimal 5MB.']);
        }
        
        // Cek foto lama
        $sql_cek = "SELECT foto FROM karyawan WHERE id_karyawan = ?";
        $stmt_cek = $conn->prepare($sql_cek);
        $stmt_cek->bind_param("s", $id_karyawan);
        $stmt_cek->execute();
        $res = $stmt_cek->get_result();
        if ($row = $res->fetch_assoc()) {
            if ($row['foto'] && file_exists('assets/images/foto_karyawan/' . $row['foto'])) {
                unlink('assets/images/foto_karyawan/' . $row['foto']);
            }
        }
        $stmt_cek->close();
        
        $new_filename = $id_karyawan . '_' . time() . '.' . $ext;
        $target_dir = 'assets/images/foto_karyawan/';
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
            $sql = "UPDATE karyawan SET foto = ? WHERE id_karyawan = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $new_filename, $id_karyawan);
            if ($stmt->execute()) {
                sendJson(['success' => true, 'message' => 'Foto berhasil diunggah.', 'foto' => $new_filename]);
            } else {
                sendJson(['success' => false, 'message' => 'Gagal menyimpan ke database.']);
            }
            $stmt->close();
        } else {
            sendJson(['success' => false, 'message' => 'Gagal memindahkan file yang diunggah.']);
        }
    } else {
        sendJson(['success' => false, 'message' => 'Tidak ada file yang diunggah atau terjadi error.']);
    }
} elseif ($action === 'hapus_foto') {
    // Cari foto lama dan jenis kelamin untuk default avatar
    $sql_cek = "SELECT foto, jenis_kelamin FROM karyawan WHERE id_karyawan = ?";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("s", $id_karyawan);
    $stmt_cek->execute();
    $res = $stmt_cek->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if ($row['foto'] && file_exists('assets/images/foto_karyawan/' . $row['foto'])) {
            unlink('assets/images/foto_karyawan/' . $row['foto']);
        }
        
        $sql_update = "UPDATE karyawan SET foto = NULL WHERE id_karyawan = ?";
        $stmt_upd = $conn->prepare($sql_update);
        $stmt_upd->bind_param("s", $id_karyawan);
        if ($stmt_upd->execute()) {
            $default_avatar = ($row['jenis_kelamin'] == 'P') ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2';
            sendJson(['success' => true, 'message' => 'Foto berhasil dihapus.', 'default_avatar' => $default_avatar]);
        } else {
            sendJson(['success' => false, 'message' => 'Gagal menghapus foto dari database.']);
        }
        $stmt_upd->close();
    } else {
        sendJson(['success' => false, 'message' => 'Data karyawan tidak ditemukan.']);
    }
    $stmt_cek->close();
} elseif ($action === 'update_biodata') {
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? NULL;
    if (empty($tanggal_lahir)) $tanggal_lahir = NULL;
    $agama = $_POST['agama'] ?? '';
    $no_whatsapp = $_POST['no_whatsapp'] ?? '';
    $alamat_lengkap = $_POST['alamat_lengkap'] ?? '';

    $sql = "UPDATE karyawan SET tempat_lahir = ?, tanggal_lahir = ?, agama = ?, no_whatsapp = ?, alamat_lengkap = ? WHERE id_karyawan = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $tempat_lahir, $tanggal_lahir, $agama, $no_whatsapp, $alamat_lengkap, $id_karyawan);
    
    if ($stmt->execute()) {
        sendJson(['success' => true, 'message' => 'Biodata berhasil diperbarui!']);
    } else {
        sendJson(['success' => false, 'message' => 'Gagal memperbarui biodata.']);
    }
    $stmt->close();
} else {
    sendJson(['success' => false, 'message' => 'Invalid action.']);
}
$conn->close();
?>
