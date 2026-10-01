<?php
/**
 * Tinjau Dinas Luar Dadakan Saat Pulang
 * ==============================================
 * Beda dari proses_persetujuan_dinas.php (masuk) dan
 * proses_persetujuan_pulang_cepat.php: absen pulang di sini SUDAH tercatat
 * sah (lihat proses_absen.php, blok $dinas_pulang_dadakan) - tidak ada
 * ACC/Tolak, admin/supervisor cuma menandai sudah memeriksa alasannya.
 */
require 'config.php';
requireLogin();

if (!isAdmin() && !isSupervisor()) {
    $_SESSION['error_message'] = "Akses ditolak. Meninjau dinas luar saat pulang hanya untuk Admin atau Supervisor.";
    header("Location: " . dashboardUntukRole($_SESSION['role'] ?? 'staff'));
    exit;
}

$default_redirect = isSupervisor() ? 'supervisor_dashboard.php' : 'kelola_pengajuan_izin.php';
$redirect_url = isset($_POST['redirect_url']) ? basename(sanitizeInput($_POST['redirect_url'])) : $default_redirect;
if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $redirect_url)) {
    $redirect_url = $default_redirect;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $default_redirect");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Validasi token gagal. Silakan coba lagi.";
    header("Location: $redirect_url");
    exit;
}

$id_absensi = isset($_POST['id_absensi']) ? intval($_POST['id_absensi']) : 0;
if ($id_absensi <= 0) {
    $_SESSION['error_message'] = "Data tidak valid.";
    header("Location: $redirect_url");
    exit;
}

$stmt = $conn->prepare("SELECT a.id, a.id_karyawan, a.dinas_pulang_dadakan, a.dinas_pulang_ditinjau_at, k.nama_karyawan, k.id_cabang
                        FROM absensi a
                        JOIN karyawan k ON a.id_karyawan = k.id_karyawan
                        WHERE a.id = ?");
$stmt->bind_param("i", $id_absensi);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Data absensi tidak ditemukan.";
    $stmt->close();
    header("Location: $redirect_url");
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

if (isSupervisor()) {
    $cabang_supervisor = getCabangReviewer($conn, $_SESSION['user_id'], 'supervisor');
    if ($cabang_supervisor <= 0 || (int)$data['id_cabang'] !== (int)$cabang_supervisor) {
        $_SESSION['error_message'] = "Absensi ini berada di luar cabang yang Anda supervisi.";
        header("Location: $redirect_url");
        exit;
    }
}

if (!$data['dinas_pulang_dadakan']) {
    $_SESSION['error_message'] = "Absensi ini bukan dinas luar dadakan saat pulang.";
    header("Location: $redirect_url");
    exit;
}
if ($data['dinas_pulang_ditinjau_at'] !== null) {
    $_SESSION['error_message'] = "Absensi ini sudah ditinjau sebelumnya.";
    header("Location: $redirect_url");
    exit;
}

$stmt_upd = $conn->prepare("UPDATE absensi SET dinas_pulang_ditinjau_oleh = ?, dinas_pulang_ditinjau_at = NOW() WHERE id = ?");
$stmt_upd->bind_param("ii", $_SESSION['user_id'], $id_absensi);
if ($stmt_upd->execute()) {
    $_SESSION['success_message'] = "Dinas luar saat pulang untuk {$data['nama_karyawan']} ditandai sudah ditinjau.";
    logActivity($conn, 'tinjau_dinas_pulang', "Menandai sudah meninjau dinas luar saat pulang {$data['nama_karyawan']}", $data['id_karyawan']);
} else {
    $_SESSION['error_message'] = "Gagal menandai sudah ditinjau.";
}
$stmt_upd->close();

header("Location: $redirect_url");
exit;
