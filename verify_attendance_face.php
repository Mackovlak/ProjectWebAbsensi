<?php
require 'config.php';
require_once 'attendance_face.php';
require_once 'attendance_capture.php';
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        throw new RuntimeException('Gunakan POST.');
    }
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        throw new RuntimeException('Sesi tidak valid. Muat ulang halaman absensi.');
    }
    $id = $_POST['id_karyawan'] ?? null;
    $action = $_POST['action'] ?? null;
    if (!is_string($id) || !validateIDKaryawan($id) || !in_array($action, ['start', 'verify'], true)) {
        throw new RuntimeException('Permintaan verifikasi tidak valid.');
    }
    $limit = checkRateLimit('attendance_face_' . $action, $action === 'start' ? 2 : 3);
    if (!$limit['allowed']) throw new RuntimeException('Tunggu ' . $limit['remaining'] . ' detik lalu coba lagi.');

    $stmt = $conn->prepare("SELECT u.face_descriptor FROM users u JOIN karyawan k ON k.id_karyawan = u.id_karyawan
                           WHERE u.id_karyawan = ? AND u.is_active = 1 AND k.status = 'aktif'");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account || empty($account['face_descriptor'])) throw new RuntimeException('Registrasi wajah aktif diperlukan.');

    $requested_kind = $_POST['jenis'] ?? null;
    if (!in_array($requested_kind, ['masuk', 'pulang'], true)) {
        throw new RuntimeException('Jenis absensi tidak valid.');
    }

    $date = date('Y-m-d');
    $stmt = $conn->prepare('SELECT id, jam_pulang, keterangan FROM absensi WHERE id_karyawan = ? AND tanggal = ?');
    $stmt->bind_param('ss', $id, $date);
    $stmt->execute();
    $attendance = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($attendance) {
        if ($requested_kind === 'masuk'
            || (!empty($attendance['jam_pulang']) && $attendance['jam_pulang'] !== '00:00:00')
            || !in_array($attendance['keterangan'], ['Hadir', 'Dinas Luar'], true)) {
            throw new RuntimeException('Status absensi saat ini tidak dapat diverifikasi. Muat ulang halaman.');
        }
    }
    // Tidak ada baris + jenis=pulang: karyawan lupa absen masuk dan memilih
    // Pulang lebih dulu (lihat proses_absen.php) - tetap wajib verifikasi
    // wajah yang sama seperti Pulang normal, cuma tanpa attendance_id nyata.
    $context = attendanceFaceContext($id, $attendance, $account['face_descriptor'], $date, $requested_kind);

    if ($action === 'start') {
        $result = ['success' => true, 'nonce' => attendanceFaceIssueToken($_SESSION, 'attendance_face_challenges', $context), 'expires_in' => ATTENDANCE_FACE_TOKEN_TTL];
    } else {
        attendanceFaceConsumeToken($_SESSION, 'attendance_face_challenges', $_POST['nonce'] ?? null, $context);
        $confidence = attendanceFaceConfidence($_POST['face_descriptor'] ?? null, $account['face_descriptor']);
        if ($confidence < ATTENDANCE_FACE_MIN_CONFIDENCE) throw new RuntimeException('Wajah tidak cocok. Coba lagi dengan posisi dan pencahayaan yang baik.');
        $photo = readAttendanceCapture($_FILES['foto_capture'] ?? null);
        $context['confidence'] = round($confidence, 2);
        $context['photo_hash'] = hash('sha256', $photo);
        $result = ['success' => true, 'confidence' => $context['confidence'],
            'verification_token' => attendanceFaceIssueToken($_SESSION, 'attendance_face_receipts', $context),
            'expires_in' => ATTENDANCE_FACE_TOKEN_TTL];
    }
} catch (RuntimeException $e) {
    $result = ['success' => false, 'message' => $e->getMessage()];
} catch (Throwable $e) {
    error_log('Attendance face verification: ' . $e->getMessage());
    http_response_code(500);
    $result = ['success' => false, 'message' => 'Verifikasi server gagal. Silakan coba lagi.'];
}
if (ob_get_length()) ob_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE);
