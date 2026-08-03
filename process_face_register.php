<?php
require 'config.php';
requireStaff();

header('Content-Type: application/json; charset=utf-8');

try {
    // Rate limiting
    $rate_check = checkRateLimit('face_register', 30);
    if (!$rate_check['allowed']) {
        echo json_encode([
            'success' => false,
            'message' => 'Tunggu ' . $rate_check['remaining'] . ' detik sebelum mencoba lagi.'
        ]);
        exit;
    }

    $id_karyawan = $_SESSION['id_karyawan'];
    
    if (!isset($_POST['descriptors']) || empty($_POST['descriptors'])) {
        throw new Exception('Data wajah tidak ditemukan');
    }

    $descriptors_json = $_POST['descriptors'];
    $descriptors = json_decode($descriptors_json, true);

    if (!is_array($descriptors) || count($descriptors) < 3) {
        throw new Exception('Data wajah tidak lengkap. Minimal 3 foto diperlukan.');
    }

    // Validasi setiap descriptor
    foreach ($descriptors as $desc) {
        if (!is_array($desc) || count($desc) !== 128) {
            throw new Exception('Format data wajah tidak valid');
        }
    }

    // Simpan ke database
    $conn->begin_transaction();

    try {
        // SECURITY: Check permission before allowing registration
        $check_stmt = $conn->prepare("SELECT face_descriptor, face_reset_allowed FROM users WHERE id_karyawan = ?");
        $check_stmt->bind_param("s", $id_karyawan);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();

        $has_face = !empty($check_data['face_descriptor']);
        $reset_allowed = ($check_data['face_reset_allowed'] == 1);

        // Prevent registration if already registered and reset not allowed
        if ($has_face && !$reset_allowed) {
            throw new Exception('❌ Tidak diizinkan! Wajah sudah terdaftar dan terkunci. Hubungi admin untuk reset.');
        }

        // Update users table + AUTO LOCK after successful registration
        $stmt = $conn->prepare(
            "UPDATE users 
             SET face_descriptor = ?, 
                 face_registered_at = NOW(), 
                 face_images_count = ?,
                 face_reset_allowed = 0
             WHERE id_karyawan = ?"
        );
        
        $images_count = count($descriptors);
        $stmt->bind_param("sis", $descriptors_json, $images_count, $id_karyawan);
        
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan data wajah: ' . $stmt->error);
        }
        $stmt->close();

        // Log activity
        $action_type = $has_face ? 'face_re_register' : 'face_register';
        $action_desc = $has_face ? "Re-registrasi wajah berhasil ($images_count foto)" : "Registrasi wajah berhasil ($images_count foto)";
        logActivity($conn, $action_type, $action_desc, $id_karyawan);

        // Log ke face_recognition_logs
        $log_type = $has_face ? 're_registration' : 'registration';
        $stmt_log = $conn->prepare(
            "INSERT INTO face_recognition_logs 
             (id_karyawan, attempt_type, status, ip_address) 
             VALUES (?, ?, 'success', ?)"
        );
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt_log->bind_param("sss", $id_karyawan, $log_type, $ip);
        $stmt_log->execute();
        $stmt_log->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => $has_face ? 'Wajah berhasil didaftarkan ulang! 🔒 Registrasi dikunci otomatis.' : 'Wajah berhasil didaftarkan! 🔒 Registrasi dikunci otomatis.',
            'images_count' => $images_count,
            'was_reset' => $has_face
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Face registration error: " . $e->getMessage());
    
    // Log failed attempt
    if (isset($id_karyawan)) {
        $stmt_log = $conn->prepare(
            "INSERT INTO face_recognition_logs 
             (id_karyawan, attempt_type, status, error_message, ip_address) 
             VALUES (?, 'registration', 'error', ?, ?)"
        );
        $error_msg = $e->getMessage();
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt_log->bind_param("sss", $id_karyawan, $error_msg, $ip);
        $stmt_log->execute();
        $stmt_log->close();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
