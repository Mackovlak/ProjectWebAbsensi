<?php
/**
 * ==========================================
 * TOGGLE FACE RESET PERMISSION - Admin Only
 * Dinia Team - Security Enhancement
 * ==========================================
 */

require 'config.php';
requireAdmin(); // Only admin can access

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_POST['id_karyawan']) || !isset($_POST['action'])) {
        throw new Exception('Parameter tidak lengkap');
    }

    $id_karyawan = $_POST['id_karyawan'];
    $action = $_POST['action']; // 'allow_reset', 'delete_face', 'lock_face'
    $admin_id = $_SESSION['id_karyawan'];

    // Validasi action
    $valid_actions = ['allow_reset', 'delete_face', 'lock_face'];
    if (!in_array($action, $valid_actions)) {
        throw new Exception('Action tidak valid');
    }

    // Cek apakah user exists
    $check_stmt = $conn->prepare("SELECT username, face_descriptor FROM users WHERE id_karyawan = ?");
    $check_stmt->bind_param("s", $id_karyawan);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('User tidak ditemukan');
    }
    
    $user_data = $check_result->fetch_assoc();
    $check_stmt->close();

    $conn->begin_transaction();

    try {
        $message = '';
        
        switch ($action) {
            case 'allow_reset':
                // Allow user to register face again
                $stmt = $conn->prepare(
                    "UPDATE users 
                     SET face_reset_allowed = 1 
                     WHERE id_karyawan = ?"
                );
                $stmt->bind_param("s", $id_karyawan);
                $stmt->execute();
                $stmt->close();
                
                $message = "Izin reset wajah diberikan kepada: " . $user_data['username'];
                break;

            case 'delete_face':
                // Hard delete face data
                $stmt = $conn->prepare(
                    "UPDATE users 
                     SET face_descriptor = NULL, 
                         face_registered_at = NULL, 
                         face_images_count = 0,
                         face_reset_allowed = 1 
                     WHERE id_karyawan = ?"
                );
                $stmt->bind_param("s", $id_karyawan);
                $stmt->execute();
                $stmt->close();
                
                $message = "Data wajah berhasil dihapus untuk: " . $user_data['username'];
                break;

            case 'lock_face':
                // Lock face registration (prevent reset)
                $stmt = $conn->prepare(
                    "UPDATE users 
                     SET face_reset_allowed = 0 
                     WHERE id_karyawan = ?"
                );
                $stmt->bind_param("s", $id_karyawan);
                $stmt->execute();
                $stmt->close();
                
                $message = "Registrasi wajah dikunci untuk: " . $user_data['username'];
                break;
        }

        // Log admin action
        $log_stmt = $conn->prepare(
            "INSERT INTO face_admin_logs 
             (admin_id, target_id_karyawan, action_type, ip_address) 
             VALUES (?, ?, ?, ?)"
        );
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("ssss", $admin_id, $id_karyawan, $action, $ip);
        $log_stmt->execute();
        $log_stmt->close();

        // Log to activity log
        logActivity($conn, 'face_admin_action', "$action untuk $id_karyawan", $admin_id);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => $message,
            'action' => $action
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Face admin action error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
