<?php
require 'config.php';

// Cek login dan admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Aktifkan error reporting untuk debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- PROSES JAM KERJA ---
if (isset($_POST['tambah_jam_kerja'])) {
    $id_cabang = intval($_POST['id_cabang']);
    $nama_shift = $conn->real_escape_string($_POST['nama_shift']);
    $jam_masuk_akhir = $conn->real_escape_string($_POST['jam_masuk_akhir']);
    $jam_pulang = $conn->real_escape_string($_POST['jam_pulang']);

    $stmt = $conn->prepare("INSERT INTO jam_kerja (id_cabang, nama_shift, jam_masuk_akhir, jam_pulang) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $id_cabang, $nama_shift, $jam_masuk_akhir, $jam_pulang);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Aturan jam kerja berhasil ditambahkan!";
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: jam_kerja.php");
    exit();
}

if (isset($_POST['edit_jam_kerja'])) {
    $id = intval($_POST['id_jam_kerja']);
    $id_cabang = intval($_POST['id_cabang']);
    $nama_shift = $conn->real_escape_string($_POST['nama_shift']);
    $jam_masuk_akhir = $conn->real_escape_string($_POST['jam_masuk_akhir']);
    $jam_pulang = $conn->real_escape_string($_POST['jam_pulang']);

    $stmt = $conn->prepare("UPDATE jam_kerja SET id_cabang=?, nama_shift=?, jam_masuk_akhir=?, jam_pulang=? WHERE id=?");
    $stmt->bind_param("isssi", $id_cabang, $nama_shift, $jam_masuk_akhir, $jam_pulang, $id);
    if ($stmt->execute()) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Aturan jam kerja berhasil diperbarui!']);
            exit();
        }
        $_SESSION['success_message'] = "Aturan jam kerja berhasil diperbarui!";
    } else {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: jam_kerja.php");
    exit();
}

if (isset($_GET['hapus_jam_kerja'])) {
    $id = intval($_GET['hapus_jam_kerja']);
    $stmt = $conn->prepare("DELETE FROM jam_kerja WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Aturan jam kerja berhasil dihapus!']);
            exit();
        }
        $_SESSION['success_message'] = "Aturan jam kerja berhasil dihapus!";
    } else {
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: jam_kerja.php");
    exit();
}


// --- PROSES JABATAN ---
if (isset($_POST['tambah_jabatan'])) {
    $nama_jabatan = $conn->real_escape_string($_POST['nama_jabatan']);
    
    // Hilangkan titik dari input rupiah
    $tunjangan_str = $_POST['tunjangan_jabatan'] ?? '0';
    $tunjangan_str = str_replace('.', '', $tunjangan_str);
    $tunjangan_jabatan = floatval($tunjangan_str);
    
    $stmt = $conn->prepare("INSERT INTO jabatan (nama_jabatan, tunjangan_jabatan) VALUES (?, ?)");
    $stmt->bind_param("sd", $nama_jabatan, $tunjangan_jabatan);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Jabatan berhasil ditambahkan!";
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data_jabatan.php");
    exit();
}

if (isset($_POST['edit_jabatan'])) {
    $id = intval($_POST['id_jabatan']);
    $nama_jabatan = $conn->real_escape_string($_POST['nama_jabatan']);
    
    // Hilangkan titik dari input rupiah
    $tunjangan_str = $_POST['tunjangan_jabatan'] ?? '0';
    $tunjangan_str = str_replace('.', '', $tunjangan_str);
    $tunjangan_jabatan = floatval($tunjangan_str);
    
    $stmt = $conn->prepare("UPDATE jabatan SET nama_jabatan = ?, tunjangan_jabatan = ? WHERE id = ?");
    $stmt->bind_param("sdi", $nama_jabatan, $tunjangan_jabatan, $id);
    if ($stmt->execute()) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Jabatan berhasil diperbarui!']);
            exit();
        }
        $_SESSION['success_message'] = "Jabatan berhasil diperbarui!";
    } else {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data_jabatan.php");
    exit();
}

if (isset($_GET['hapus_jabatan'])) {
    $id = intval($_GET['hapus_jabatan']);
    try {
        $stmt = $conn->prepare("DELETE FROM jabatan WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Jabatan berhasil dihapus!']);
            exit();
        }
        $_SESSION['success_message'] = "Jabatan berhasil dihapus!";
    } catch (mysqli_sql_exception $e) {
        $msg = "Terjadi kesalahan pada database.";
        if ($e->getCode() == 1451) {
            $msg = "Gagal menghapus! Jabatan ini masih digunakan oleh karyawan.";
        }
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        }
        $_SESSION['error_message'] = $msg;
    }
    header("Location: data_jabatan.php");
    exit();
}

// --- PROSES CABANG ---
// Tambah Cabang
if (isset($_POST['tambah_cabang'])) {
    $nama_cabang = $conn->real_escape_string($_POST['nama_cabang']);
    $alamat_cabang = $conn->real_escape_string($_POST['alamat_cabang']);
    
    // TAMBAHAN BARU - Ambil koordinat & radius
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : NULL;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : NULL;
    $radius_meter = !empty($_POST['radius_meter']) ? intval($_POST['radius_meter']) : 100;
    
    // Query INSERT dengan prepared statement
    $stmt = $conn->prepare("INSERT INTO cabang (nama_cabang, alamat_cabang, latitude, longitude, radius_meter) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddi", $nama_cabang, $alamat_cabang, $latitude, $longitude, $radius_meter);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Cabang berhasil ditambahkan dengan koordinat lokasi!";
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data_cabang.php");
    exit();
}

if (isset($_POST['edit_cabang'])) {
    $id = intval($_POST['id_cabang']);
    $nama_cabang = $conn->real_escape_string($_POST['nama_cabang']);
    $alamat_cabang = $conn->real_escape_string($_POST['alamat_cabang']);
    
    // TAMBAHAN BARU - Ambil koordinat & radius
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : NULL;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : NULL;
    $radius_meter = !empty($_POST['radius_meter']) ? intval($_POST['radius_meter']) : 100;
    
    // Query UPDATE dengan prepared statement
    $stmt = $conn->prepare("UPDATE cabang SET nama_cabang = ?, alamat_cabang = ?, latitude = ?, longitude = ?, radius_meter = ? WHERE id = ?");
    $stmt->bind_param("ssddii", $nama_cabang, $alamat_cabang, $latitude, $longitude, $radius_meter, $id);
    
    if ($stmt->execute()) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Cabang berhasil diperbarui!']);
            exit();
        }
        $_SESSION['success_message'] = "Cabang berhasil diperbarui!";
    } else {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data_cabang.php");
    exit();
}

if (isset($_GET['hapus_cabang'])) {
    $id = intval($_GET['hapus_cabang']);
    try {
        $stmt = $conn->prepare("DELETE FROM cabang WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Cabang berhasil dihapus!']);
            exit();
        }
        $_SESSION['success_message'] = "Cabang berhasil dihapus!";
    } catch (mysqli_sql_exception $e) {
        $msg = "Terjadi kesalahan pada database.";
        if ($e->getCode() == 1451) {
            $msg = "Gagal menghapus! Cabang ini masih memiliki karyawan.";
        }
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        }
        $_SESSION['error_message'] = $msg;
    }
    header("Location: data_cabang.php");
    exit();
}

// --- PROSES KARYAWAN (DENGAN CASCADE DELETE) ---
if (isset($_POST['tambah_karyawan'])) {
    $id_karyawan = $conn->real_escape_string($_POST['id_karyawan']);
    $nama_karyawan = $conn->real_escape_string($_POST['nama_karyawan']);
    $id_jabatan = intval($_POST['id_jabatan']);
    $id_cabang = intval($_POST['id_cabang']);
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin'] ?? 'L');
    $stmt = $conn->prepare("INSERT INTO karyawan (id_karyawan, nama_karyawan, id_jabatan, id_cabang, jenis_kelamin) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiis", $id_karyawan, $nama_karyawan, $id_jabatan, $id_cabang, $jenis_kelamin);
    if ($stmt->execute()) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Karyawan berhasil ditambahkan!']);
            exit();
        }
        $_SESSION['success_message'] = "Karyawan berhasil ditambahkan!";
    } else {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data_karyawan.php");
    exit();
}

if (isset($_POST['edit_karyawan'])) {
    $id = intval($_POST['id_karyawan_pk']);
    $nama_karyawan = $conn->real_escape_string($_POST['nama_karyawan']);
    $id_jabatan = intval($_POST['id_jabatan']);
    $id_cabang = intval($_POST['id_cabang']);
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin'] ?? 'L');
    $stmt = $conn->prepare("UPDATE karyawan SET nama_karyawan = ?, id_jabatan = ?, id_cabang = ?, jenis_kelamin = ? WHERE id = ?");
    $stmt->bind_param("siisi", $nama_karyawan, $id_jabatan, $id_cabang, $jenis_kelamin, $id);
    if ($stmt->execute()) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => 'Data karyawan berhasil diperbarui!']);
            exit();
        }
        $_SESSION['success_message'] = "Data karyawan berhasil diperbarui!";
    } else {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $stmt->error]);
            exit();
        }
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: data_karyawan.php");
    exit();
}

if (isset($_GET['nonaktifkan_karyawan'])) {
    $id = intval($_GET['nonaktifkan_karyawan']);
    
    // Ambil id_karyawan
    $stmt_karyawan = $conn->prepare("SELECT id_karyawan, nama_karyawan FROM karyawan WHERE id = ?");
    $stmt_karyawan->bind_param("i", $id);
    $stmt_karyawan->execute();
    $result_karyawan = $stmt_karyawan->get_result();
    
    if ($result_karyawan->num_rows > 0) {
        $row = $result_karyawan->fetch_assoc();
        $id_karyawan_string = $row['id_karyawan'];
        $nama_karyawan = $row['nama_karyawan'];
        
        // Hapus user terkait agar tidak bisa login lagi
        $stmt_user = $conn->prepare("DELETE FROM users WHERE id_karyawan = ?");
        $stmt_user->bind_param("s", $id_karyawan_string);
        $stmt_user->execute();
        $stmt_user->close();
        
        // Ubah status menjadi nonaktif dan set tanggal resign
        $stmt_update = $conn->prepare("UPDATE karyawan SET status = 'nonaktif', tanggal_resign = CURDATE() WHERE id = ?");
        $stmt_update->bind_param("i", $id);
        if ($stmt_update->execute()) {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'success', 'message' => htmlspecialchars($nama_karyawan) . " berhasil diarsipkan/dinonaktifkan."]);
                exit();
            }
            $_SESSION['success_message'] = htmlspecialchars($nama_karyawan) . " berhasil diarsipkan/dinonaktifkan.";
        } else {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'error', 'message' => "Gagal menonaktifkan karyawan: " . $stmt_update->error]);
                exit();
            }
            $_SESSION['error_message'] = "Gagal menonaktifkan karyawan: " . $stmt_update->error;
        }
        $stmt_update->close();
    } else {
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "Karyawan tidak ditemukan."]);
            exit();
        }
        $_SESSION['error_message'] = "Karyawan tidak ditemukan.";
    }
    
    $stmt_karyawan->close();
    if (!isset($_GET['is_ajax'])) {
        header("Location: data_karyawan.php");
        exit();
    }
}

if (isset($_GET['aktifkan_karyawan'])) {
    $id = intval($_GET['aktifkan_karyawan']);
    $stmt_update = $conn->prepare("UPDATE karyawan SET status = 'aktif', tanggal_resign = NULL WHERE id = ?");
    $stmt_update->bind_param("i", $id);
    if ($stmt_update->execute()) {
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'success', 'message' => "Karyawan berhasil diaktifkan kembali. Silakan buat akun baru di Setting User."]);
            exit();
        }
        $_SESSION['success_message'] = "Karyawan berhasil diaktifkan kembali. Silakan buat akun baru di Setting User.";
    } else {
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "Gagal mengaktifkan karyawan: " . $stmt_update->error]);
            exit();
        }
        $_SESSION['error_message'] = "Gagal mengaktifkan karyawan: " . $stmt_update->error;
    }
    $stmt_update->close();
    if (!isset($_GET['is_ajax'])) {
        header("Location: data_karyawan.php?view=arsip");
        exit();
    }
}

if (isset($_GET['hapus_karyawan'])) {
    $id = intval($_GET['hapus_karyawan']);
    
    // Ambil detail karyawan dulu sebelum dihapus
    $stmt_karyawan = $conn->prepare("SELECT id_karyawan, nama_karyawan FROM karyawan WHERE id = ?");
    $stmt_karyawan->bind_param("i", $id);
    $stmt_karyawan->execute();
    $result_karyawan = $stmt_karyawan->get_result();
    
    if ($result_karyawan->num_rows > 0) {
        $row = $result_karyawan->fetch_assoc();
        $id_karyawan_string = $row['id_karyawan'];
        $nama_karyawan = $row['nama_karyawan'];
        
        // Histori Absensi dan Slip Gaji TIDAK dihapus agar tetap ada untuk keperluan Laporan / Tutup Buku
        // (Sesuai permintaan user, data riwayat tidak ikut terhapus meski karyawan dihapus)

        // Hapus user terkait secara eksplisit
        $stmt_user = $conn->prepare("DELETE FROM users WHERE id_karyawan = ?");
        $stmt_user->bind_param("s", $id_karyawan_string);
        $stmt_user->execute();
        $stmt_user->close();
        
        // Hapus karyawan
        $stmt_delete = $conn->prepare("DELETE FROM karyawan WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        
        if ($stmt_delete->execute()) {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'success', 'message' => htmlspecialchars($nama_karyawan) . " beserta akun dan data terkait berhasil dihapus."]);
                exit();
            }
            $_SESSION['success_message'] = htmlspecialchars($nama_karyawan) . " beserta akun dan data terkait berhasil dihapus.";
        } else {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'error', 'message' => "Gagal menghapus karyawan: " . $stmt_delete->error]);
                exit();
            }
            $_SESSION['error_message'] = "Gagal menghapus karyawan: " . $stmt_delete->error;
        }
        $stmt_delete->close();
        
    } else {
        if (isset($_GET['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => "Karyawan tidak ditemukan."]);
            exit();
        }
        $_SESSION['error_message'] = "Karyawan tidak ditemukan.";
    }
    
    $stmt_karyawan->close();
    if (!isset($_GET['is_ajax'])) {
        header("Location: data_karyawan.php");
        exit();
    }
}

// --- PROSES FACE PERMISSION ---
if (isset($_GET['action']) && isset($_GET['id_karyawan'])) {
    $action = $_GET['action'];
    $id_karyawan = $conn->real_escape_string($_GET['id_karyawan']);
    $sql = "";
    $msg = "";
    
    if ($action == 'allow_reset') {
        $sql = "UPDATE users SET face_reset_allowed = 1 WHERE id_karyawan = ?";
        $msg = "Izin reset wajah diberikan.";
    } elseif ($action == 'lock_face') {
        $sql = "UPDATE users SET face_reset_allowed = 0 WHERE id_karyawan = ?";
        $msg = "Izin reset wajah dicabut.";
    } elseif ($action == 'delete_face') {
        $sql = "UPDATE users SET face_descriptor = NULL, face_registered_at = NULL, face_reset_allowed = 0 WHERE id_karyawan = ?";
        $msg = "Data wajah berhasil dihapus. Karyawan harus mendaftar ulang.";
    }
    
    if ($sql !== "") {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id_karyawan);
        if ($stmt->execute()) {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'success', 'message' => $msg]);
                exit();
            }
            $_SESSION['success_message'] = $msg;
        } else {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'error', 'message' => "Gagal memproses aksi wajah: " . $stmt->error]);
                exit();
            }
            $_SESSION['error_message'] = "Gagal memproses aksi wajah: " . $stmt->error;
        }
        $stmt->close();
    }
    
    if (!isset($_GET['is_ajax'])) {
        header("Location: setting_users.php");
        exit();
    }
}

// --- PROSES USER MANAGEMENT ---
if (isset($_POST['tambah_staff'])) {
    $id_karyawan = $conn->real_escape_string($_POST['id_karyawan_selected']);
    $password = $_POST['password'];
    
    // Username otomatis sama dengan ID Karyawan
    $username = $id_karyawan;
    $role = 'staff';
    
    if (empty($id_karyawan) || empty($password)) {
        $_SESSION['error_message'] = "Semua field harus diisi.";
    } else if (strlen($password) < 8) {
        $_SESSION['error_message'] = "Password minimal 8 karakter.";
    } else if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $_SESSION['error_message'] = "Password harus kombinasi huruf dan angka.";
    } else {
        // Ambil nama karyawan dari tabel karyawan
        $stmt_get_nama = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
        $stmt_get_nama->bind_param("s", $id_karyawan);
        $stmt_get_nama->execute();
        $res_nama = $stmt_get_nama->get_result();
        
        if ($res_nama->num_rows == 0) {
            $_SESSION['error_message'] = "Karyawan tidak ditemukan.";
        } else {
            $nama_karyawan = $res_nama->fetch_assoc()['nama_karyawan'];
            
            // Cek apakah username sudah ada
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR id_karyawan = ?");
            $stmt_check->bind_param("ss", $username, $id_karyawan);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();
            
            if ($res_check->num_rows > 0) {
                $_SESSION['error_message'] = "Karyawan ini sudah memiliki akun.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt_insert = $conn->prepare("INSERT INTO users (nama, username, password, role, id_karyawan) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("sssss", $nama_karyawan, $username, $hashed_password, $role, $id_karyawan);
                
                if ($stmt_insert->execute()) {
                    $_SESSION['success_message'] = "Akun staff untuk '$nama_karyawan' (Username: $username) berhasil dibuat!";
                } else {
                    $_SESSION['error_message'] = "Gagal membuat akun: " . $stmt_insert->error;
                }
            }
        }
    }
    
    if (isset($_POST['is_ajax'])) {
        if (isset($_SESSION['error_message'])) {
            $msg = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        } else if (isset($_SESSION['success_message'])) {
            $msg = $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            echo json_encode(['status' => 'success', 'message' => $msg]);
            exit();
        }
    }
    
    header("Location: setting_users.php");
    exit();
}

    // Handler Tambah Owner
if (isset($_POST['tambah_owner'])) {
    $nama = sanitizeInput($_POST['nama_owner']);
    $username = sanitizeInput($_POST['username_owner']);
    $password = $_POST['password_owner'];
    
    // Validasi
    if (strlen($username) < 4 || strlen($password) < 8) {
        if (isset($_POST['is_ajax'])) {
            echo json_encode(['status' => 'error', 'message' => 'Username min 4 karakter, password min 8 karakter.']);
            exit();
        }
        $_SESSION['error_message'] = "Username min 4 karakter, password min 8 karakter.";
        header("Location: setting_users.php");
        exit();
    }
    
    // Cek username sudah ada atau belum
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $_SESSION['error_message'] = "Username sudah digunakan.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'owner';
        
        $sql = "INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $nama, $username, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Akun Owner berhasil dibuat.";
            logActivity($conn, 'create_user', "Buat akun owner: $username", null);
        } else {
            $_SESSION['error_message'] = "Gagal membuat akun owner.";
        }
    }
    
    if (isset($_POST['is_ajax'])) {
        if (isset($_SESSION['error_message'])) {
            $msg = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        } else if (isset($_SESSION['success_message'])) {
            $msg = $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            echo json_encode(['status' => 'success', 'message' => $msg]);
            exit();
        }
    }
    
    header("Location: setting_users.php");
    exit();
}

// Edit Password User
if (isset($_POST['edit_user'])) {
    $id_user = intval($_POST['id_user']);
    $password = $_POST['password'];
    $current_user_id = $_SESSION['user_id'];
    
    // Cek apakah user yang akan diedit ada
    $stmt_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $id_user);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($res_check->num_rows == 0) {
        $_SESSION['error_message'] = "User tidak ditemukan.";
    } else {
        $user_data = $res_check->fetch_assoc();
        
        // Admin tidak bisa edit password admin lain (kecuali diri sendiri)
        if ($user_data['role'] == 'admin' && $id_user != $current_user_id) {
            $_SESSION['error_message'] = "Anda tidak dapat mengubah password admin lain.";
        } else if (strlen($password) < 6) {
            $_SESSION['error_message'] = "Password minimal 6 karakter.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $hashed_password, $id_user);
            
            if ($stmt_update->execute()) {
                if (isset($_POST['is_ajax'])) {
                    echo json_encode(['status' => 'success', 'message' => 'Password berhasil diperbarui!']);
                    exit();
                }
                $_SESSION['success_message'] = "Password berhasil diperbarui!";
            } else {
                if (isset($_POST['is_ajax'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui password.']);
                    exit();
                }
                $_SESSION['error_message'] = "Gagal memperbarui password.";
            }
        }
    }
    if (isset($_POST['is_ajax']) && isset($_SESSION['error_message'])) {
        $msg = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit();
    }
    header("Location: setting_users.php");
    exit();
}

// Hapus User
if (isset($_GET['hapus_user'])) {
    $id_user = intval($_GET['hapus_user']);
    $current_user_id = $_SESSION['user_id'];
    
    $stmt_check = $conn->prepare("SELECT role, username FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $id_user);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($res_check->num_rows == 0) {
        $_SESSION['error_message'] = "User tidak ditemukan.";
    } else {
        $user_to_delete = $res_check->fetch_assoc();
        
        // Cek apakah ini admin terakhir
        if ($user_to_delete['role'] == 'admin') {
            $sql_admin_count = "SELECT COUNT(*) as total_admin FROM users WHERE role = 'admin'";
            $res_admin_count = $conn->query($sql_admin_count);
            $admin_count = $res_admin_count->fetch_assoc()['total_admin'];
            
            if ($admin_count <= 1) {
                if (isset($_GET['is_ajax'])) {
                    echo json_encode(['status' => 'error', 'message' => "Tidak dapat menghapus admin terakhir."]);
                    exit();
                }
                $_SESSION['error_message'] = "Tidak dapat menghapus admin terakhir.";
                header("Location: setting_users.php");
                exit();
            }
            
            // Admin tidak bisa hapus admin lain (kecuali diri sendiri)
            if ($id_user != $current_user_id) {
                if (isset($_GET['is_ajax'])) {
                    echo json_encode(['status' => 'error', 'message' => "Anda tidak dapat menghapus akun admin lain."]);
                    exit();
                }
                $_SESSION['error_message'] = "Anda tidak dapat menghapus akun admin lain.";
                header("Location: setting_users.php");
                exit();
            }
        }
        
        // Proses hapus
        $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete->bind_param("i", $id_user);
        
        if ($stmt_delete->execute()) {
            // Jika menghapus akun sendiri, logout
            if ($id_user == $current_user_id) {
                session_destroy();
                if (isset($_GET['is_ajax'])) {
                    echo json_encode(['status' => 'success', 'redirect' => 'login.php']);
                    exit();
                }
                header("Location: login.php");
                exit();
            }
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'success', 'message' => "User '{$user_to_delete['username']}' berhasil dihapus!"]);
                exit();
            }
            $_SESSION['success_message'] = "User '{$user_to_delete['username']}' berhasil dihapus!";
        } else {
            if (isset($_GET['is_ajax'])) {
                echo json_encode(['status' => 'error', 'message' => "Gagal menghapus user."]);
                exit();
            }
            $_SESSION['error_message'] = "Gagal menghapus user.";
        }
    }
    
    if (isset($_GET['is_ajax'])) {
        if (isset($_SESSION['error_message'])) {
            $msg = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit();
        }
    }
    
    header("Location: setting_users.php");
    exit();
}
?>

