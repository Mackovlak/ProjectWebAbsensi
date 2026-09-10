<?php
require 'config.php';
require_once 'attendance_capture.php';
$branch = requireAttendancePhotoReviewer($conn);
$id = (int)($_GET['id'] ?? 0);
$jenis = $_GET['jenis'] ?? '';
if ($id < 1 || !in_array($jenis, ['masuk', 'pulang'], true) || !attendanceCaptureReady($conn)) {
    http_response_code(404);
    exit('Foto tidak tersedia.');
}
$sql = 'SELECT p.foto FROM absensi_capture p JOIN absensi a ON a.id = p.id_absensi
        JOIN karyawan k ON k.id_karyawan = a.id_karyawan WHERE p.id_absensi = ? AND p.jenis = ?';
if ($branch !== null) $sql .= ' AND k.id_cabang = ?';
$stmt = $conn->prepare($sql);
if ($branch !== null) $stmt->bind_param('isi', $id, $jenis, $branch);
else $stmt->bind_param('is', $id, $jenis);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$photo) {
    http_response_code(404);
    exit('Foto tidak tersedia.');
}
if (ob_get_length()) ob_clean();
header('Content-Type: image/jpeg');
header('Content-Disposition: inline; filename="absensi-' . $jenis . '.jpg"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $photo['foto'];
