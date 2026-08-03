<?php
require 'config.php';

// Cek apakah request POST dan user sudah login
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$upload_dir = 'assets/uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($action === 'upload_ttd') {
    if (isset($_FILES['ttd']) && $_FILES['ttd']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ttd'];
        
        // Validasi ekstensi
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            echo json_encode(['success' => false, 'message' => 'Hanya file PNG yang diperbolehkan.']);
            exit();
        }

        // Validasi ukuran (Max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB.']);
            exit();
        }

        // Ambil info ttd lama
        $stmt_old = $conn->prepare("SELECT ttd_path FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $user_id);
        $stmt_old->execute();
        $old_ttd = $stmt_old->get_result()->fetch_assoc()['ttd_path'];
        $stmt_old->close();

        // Nama file unik
        $new_filename = 'ttd_' . $user_id . '_' . time() . '.png';
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Hapus file lama jika ada
            if ($old_ttd && file_exists($upload_dir . $old_ttd)) {
                unlink($upload_dir . $old_ttd);
            }

            // Update database
            $stmt = $conn->prepare("UPDATE users SET ttd_path = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Tanda tangan berhasil diupload.', 'ttd' => $new_filename]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi kesalahan saat upload.']);
    }
} elseif ($action === 'hapus_ttd') {
    $stmt_old = $conn->prepare("SELECT ttd_path FROM users WHERE id = ?");
    $stmt_old->bind_param("i", $user_id);
    $stmt_old->execute();
    $old_ttd = $stmt_old->get_result()->fetch_assoc()['ttd_path'];
    $stmt_old->close();

    if ($old_ttd && file_exists($upload_dir . $old_ttd)) {
        unlink($upload_dir . $old_ttd);
    }

    $stmt = $conn->prepare("UPDATE users SET ttd_path = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Tanda tangan berhasil dihapus.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus dari database.']);
    }
    $stmt->close();
} elseif ($action === 'upload_stempel') {
    if (isset($_FILES['stempel']) && $_FILES['stempel']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['stempel'];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            echo json_encode(['success' => false, 'message' => 'Hanya file PNG yang diperbolehkan.']);
            exit();
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB.']);
            exit();
        }

        $stmt_old = $conn->prepare("SELECT stempel_path FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $user_id);
        $stmt_old->execute();
        $old_stempel = $stmt_old->get_result()->fetch_assoc()['stempel_path'];
        $stmt_old->close();

        $new_filename = 'stempel_' . $user_id . '_' . time() . '.png';
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            if ($old_stempel && file_exists($upload_dir . $old_stempel)) {
                unlink($upload_dir . $old_stempel);
            }

            $stmt = $conn->prepare("UPDATE users SET stempel_path = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Stempel berhasil diupload.', 'stempel' => $new_filename]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi kesalahan saat upload.']);
    }
} elseif ($action === 'hapus_stempel') {
    $stmt_old = $conn->prepare("SELECT stempel_path FROM users WHERE id = ?");
    $stmt_old->bind_param("i", $user_id);
    $stmt_old->execute();
    $old_stempel = $stmt_old->get_result()->fetch_assoc()['stempel_path'];
    $stmt_old->close();

    if ($old_stempel && file_exists($upload_dir . $old_stempel)) {
        unlink($upload_dir . $old_stempel);
    }

    $stmt = $conn->prepare("UPDATE users SET stempel_path = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Stempel berhasil dihapus.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus dari database.']);
    }
    $stmt->close();
} elseif ($action === 'upload_foto_profil') {
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_profil'];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg'])) {
            echo json_encode(['success' => false, 'message' => 'Hanya file PNG/JPG yang diperbolehkan.']);
            exit();
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 5MB.']);
            exit();
        }

        $stmt_old = $conn->prepare("SELECT foto_profil FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $user_id);
        $stmt_old->execute();
        $old_foto = $stmt_old->get_result()->fetch_assoc()['foto_profil'];
        $stmt_old->close();

        $new_filename = 'profil_' . $user_id . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            if ($old_foto && file_exists($upload_dir . $old_foto)) {
                unlink($upload_dir . $old_foto);
            }

            $stmt = $conn->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            if ($stmt->execute()) {
                $_SESSION['foto_profil'] = $new_filename; // Update session if needed
                echo json_encode(['success' => true, 'message' => 'Foto profil berhasil diupload.', 'foto' => $new_filename]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi kesalahan saat upload.']);
    }
} elseif ($action === 'hapus_foto_profil') {
    $stmt_old = $conn->prepare("SELECT foto_profil FROM users WHERE id = ?");
    $stmt_old->bind_param("i", $user_id);
    $stmt_old->execute();
    $old_foto = $stmt_old->get_result()->fetch_assoc()['foto_profil'];
    $stmt_old->close();

    if ($old_foto && file_exists($upload_dir . $old_foto)) {
        unlink($upload_dir . $old_foto);
    }

    $stmt = $conn->prepare("UPDATE users SET foto_profil = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        unset($_SESSION['foto_profil']);
        echo json_encode(['success' => true, 'message' => 'Foto profil berhasil dihapus.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus dari database.']);
    }
    $stmt->close();
} elseif ($action === 'update_profil') {
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $wa_token = $_POST['wa_token'] ?? '';
    
    if (in_array($jenis_kelamin, ['L', 'P'])) {
        $stmt = $conn->prepare("UPDATE users SET jenis_kelamin = ?, wa_token = ? WHERE id = ?");
        $stmt->bind_param("ssi", $jenis_kelamin, $wa_token, $user_id);
        if ($stmt->execute()) {
            $_SESSION['jenis_kelamin'] = $jenis_kelamin;
            echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate profil.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
}
