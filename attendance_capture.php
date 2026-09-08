<?php
// Loaded after config.php. No schema changes occur in attendance requests.
function attendanceCaptureReady($conn) {
    $result = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'absensi_capture'");
    return $result && $result->num_rows > 0;
}

function readAttendanceCapture($file) {
    if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Foto kamera wajib diambil. Silakan verifikasi wajah lagi.');
    }
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 512 * 1024) {
        throw new RuntimeException('Foto kamera maksimal 512 KB. Silakan coba lagi.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] > 1280 || $info[1] > 1280) {
        throw new RuntimeException('Foto kamera harus berupa JPEG dengan ukuran maksimal 1280 piksel.');
    }
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) throw new RuntimeException('Gagal membaca foto kamera.');
    return $bytes;
}

// Must be called inside the same transaction as the attendance write.
function saveAttendanceCapture($conn, $id, $jenis, $bytes) {
    if ($bytes === null) return;
    $stmt = $conn->prepare('INSERT INTO absensi_capture (id_absensi, jenis, foto) VALUES (?, ?, ?)');
    if (!$stmt) throw new RuntimeException('Penyimpanan foto absensi belum tersedia.');
    $stmt->bind_param('iss', $id, $jenis, $bytes);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) throw new RuntimeException('Gagal menyimpan foto absensi. Silakan coba lagi.');
}

function requireAttendancePhotoReviewer($conn) {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'supervisor'], true)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $branch = getCabangReviewer($conn, $_SESSION['user_id'], $_SESSION['role']);
    // Fail closed if a supervisor has no assigned branch.
    if ($_SESSION['role'] === 'supervisor' && !$branch) {
        http_response_code(403);
        exit('Cabang supervisor belum ditentukan.');
    }
    return $branch;
}
