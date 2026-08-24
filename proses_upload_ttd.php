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

// Profil personal akun yang tertaut disimpan di master karyawan.
$stmt_link = $conn->prepare("SELECT u.id_karyawan, u.foto_profil, k.foto AS foto_karyawan
                             FROM users u
                             LEFT JOIN karyawan k ON k.id_karyawan = u.id_karyawan
                             WHERE u.id = ?");
$stmt_link->bind_param("i", $user_id);
$stmt_link->execute();
$profile_link = $stmt_link->get_result()->fetch_assoc() ?: [];
$stmt_link->close();
$linked_id_karyawan = $profile_link['id_karyawan'] ?? '';
$is_linked_karyawan = $linked_id_karyawan !== '';
$employee_photo_dir = 'assets/images/foto_karyawan/';

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

        $old_user_foto = $profile_link['foto_profil'] ?? null;
        $old_karyawan_foto = $profile_link['foto_karyawan'] ?? null;
        $target_dir = $is_linked_karyawan ? $employee_photo_dir : $upload_dir;
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $safe_employee_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $linked_id_karyawan);
        $new_filename = $is_linked_karyawan
            ? $safe_employee_id . '_' . time() . '.' . $ext
            : 'profil_' . $user_id . '_' . time() . '.' . $ext;
        $destination = $target_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            if ($is_linked_karyawan) {
                $stmt = $conn->prepare("UPDATE karyawan SET foto = ? WHERE id_karyawan = ?");
                $stmt->bind_param("ss", $new_filename, $linked_id_karyawan);
            } else {
                $stmt = $conn->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
                $stmt->bind_param("si", $new_filename, $user_id);
            }
            if ($stmt->execute()) {
                if ($is_linked_karyawan) {
                    if ($old_karyawan_foto && basename($old_karyawan_foto) === $old_karyawan_foto && file_exists($employee_photo_dir . $old_karyawan_foto)) {
                        unlink($employee_photo_dir . $old_karyawan_foto);
                    }
                    // Bersihkan foto akun lama setelah sumber profil pindah ke master karyawan.
                    if ($old_user_foto && basename($old_user_foto) === $old_user_foto && file_exists($upload_dir . $old_user_foto)) {
                        unlink($upload_dir . $old_user_foto);
                    }
                    $stmt_clear = $conn->prepare("UPDATE users SET foto_profil = NULL WHERE id = ?");
                    $stmt_clear->bind_param("i", $user_id);
                    $stmt_clear->execute();
                    $stmt_clear->close();
                    unset($_SESSION['foto_profil']);
                } else {
                    if ($old_user_foto && basename($old_user_foto) === $old_user_foto && file_exists($upload_dir . $old_user_foto)) {
                        unlink($upload_dir . $old_user_foto);
                    }
                    $_SESSION['foto_profil'] = $new_filename;
                }
                echo json_encode([
                    'success' => true,
                    'message' => $is_linked_karyawan ? 'Foto profil dan Master Data Karyawan berhasil diperbarui.' : 'Foto profil berhasil diupload.',
                    'foto' => $new_filename,
                    'foto_url' => $target_dir . $new_filename
                ]);
            } else {
                if (file_exists($destination)) unlink($destination);
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
    $old_user_foto = $profile_link['foto_profil'] ?? null;
    $old_karyawan_foto = $profile_link['foto_karyawan'] ?? null;
    if ($is_linked_karyawan) {
        $stmt = $conn->prepare("UPDATE karyawan SET foto = NULL WHERE id_karyawan = ?");
        $stmt->bind_param("s", $linked_id_karyawan);
    } else {
        $stmt = $conn->prepare("UPDATE users SET foto_profil = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    }
    if ($stmt->execute()) {
        if ($old_karyawan_foto && basename($old_karyawan_foto) === $old_karyawan_foto && file_exists($employee_photo_dir . $old_karyawan_foto)) {
            unlink($employee_photo_dir . $old_karyawan_foto);
        }
        if ($old_user_foto && basename($old_user_foto) === $old_user_foto && file_exists($upload_dir . $old_user_foto)) {
            unlink($upload_dir . $old_user_foto);
        }
        if ($is_linked_karyawan) {
            $stmt_clear = $conn->prepare("UPDATE users SET foto_profil = NULL WHERE id = ?");
            $stmt_clear->bind_param("i", $user_id);
            $stmt_clear->execute();
            $stmt_clear->close();
        }
        unset($_SESSION['foto_profil']);
        echo json_encode(['success' => true, 'message' => $is_linked_karyawan ? 'Foto profil pada Master Data Karyawan berhasil dihapus.' : 'Foto profil berhasil dihapus.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus dari database.']);
    }
    $stmt->close();
} elseif ($action === 'update_profil') {
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $wa_token = $_POST['wa_token'] ?? '';
    
    if (in_array($jenis_kelamin, ['L', 'P'], true)) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET jenis_kelamin = ?, wa_token = ? WHERE id = ?");
            $stmt->bind_param("ssi", $jenis_kelamin, $wa_token, $user_id);
            if (!$stmt->execute()) throw new Exception('Gagal mengupdate profil akun.');
            $stmt->close();

            if ($is_linked_karyawan) {
                $stmt_karyawan = $conn->prepare("UPDATE karyawan SET jenis_kelamin = ? WHERE id_karyawan = ?");
                $stmt_karyawan->bind_param("ss", $jenis_kelamin, $linked_id_karyawan);
                if (!$stmt_karyawan->execute()) throw new Exception('Gagal mengupdate Master Data Karyawan.');
                $stmt_karyawan->close();
            }
            $conn->commit();
            $_SESSION['jenis_kelamin'] = $jenis_kelamin;
            echo json_encode(['success' => true, 'message' => $is_linked_karyawan ? 'Profil dan Master Data Karyawan berhasil diperbarui.' : 'Profil berhasil diperbarui.']);
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
}
