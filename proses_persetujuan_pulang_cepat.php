<?php
/**
 * Persetujuan Izin Pulang Cepat (Setengah Hari)
 * ==============================================
 * Pulang cepat tidak lagi menahan absen pulang (lihat jamPulangCepatPerluTinjau()
 * di izin_functions.php): baris berstatus 'Pending' bisa berupa pemberitahuan
 * di muka (belum absen pulang) ATAU pulang cepat yang sudah terjadi (jam_pulang
 * terisi) - keduanya ditinjau di sini, tidak dibatasi tanggal hari ini.
 * Yang berubah hanya kolom izin_pulang_cepat, bukan keterangan/jam. Ditolak
 * untuk saat ini hanya catatan (belum ada potongan gaji). Tidak memotong
 * kuota tahunan sama sekali.
 */
require 'config.php';
requireLogin();

if (!isAdmin() && !isSupervisor()) {
    $_SESSION['error_message'] = "Akses ditolak. Persetujuan Izin Pulang Cepat hanya untuk Admin atau Supervisor.";
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
$action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';

if ($id_absensi <= 0 || !in_array($action, ['acc', 'tolak'], true)) {
    $_SESSION['error_message'] = "Data tidak valid.";
    header("Location: $redirect_url");
    exit;
}

$stmt = $conn->prepare("SELECT a.id, a.id_karyawan, a.tanggal, a.jam_pulang, a.izin_pulang_cepat, k.nama_karyawan, k.id_cabang
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
        $_SESSION['error_message'] = "Permintaan pulang cepat ini berada di luar cabang yang Anda supervisi.";
        header("Location: $redirect_url");
        exit;
    }
}

if ($data['izin_pulang_cepat'] !== 'Pending') {
    $_SESSION['error_message'] = "Status izin pulang cepat bukan Pending, tidak dapat diproses.";
    header("Location: $redirect_url");
    exit;
}

$status_baru = ($action === 'acc') ? 'Disetujui' : 'Ditolak';
$sudah_pulang = !empty($data['jam_pulang']) && $data['jam_pulang'] !== '00:00:00';
$tanggal_label = date('d/m/Y', strtotime($data['tanggal']));
$stmt_upd = $conn->prepare("UPDATE absensi SET izin_pulang_cepat = ? WHERE id = ?");
$stmt_upd->bind_param("si", $status_baru, $id_absensi);
if ($stmt_upd->execute()) {
    if ($action === 'acc') {
        $_SESSION['success_message'] = "Pulang cepat {$data['nama_karyawan']} tanggal $tanggal_label disetujui.";
        logActivity($conn, 'acc_pulang_cepat', "Menyetujui pulang cepat {$data['nama_karyawan']} tanggal $tanggal_label", $data['id_karyawan']);
    } else {
        $_SESSION['success_message'] = "Pulang cepat {$data['nama_karyawan']} tanggal $tanggal_label ditolak (dicatat).";
        logActivity($conn, 'tolak_pulang_cepat', "Menolak pulang cepat {$data['nama_karyawan']} tanggal $tanggal_label", $data['id_karyawan']);
    }
} else {
    $_SESSION['error_message'] = "Gagal memproses izin pulang cepat.";
}
$stmt_upd->close();

if ($sudah_pulang) {
    $push_body = "Pulang cepat Anda tanggal $tanggal_label sudah ditinjau atasan.";
} else {
    $push_body = $action === 'acc'
        ? 'Anda bisa absen pulang lebih awal hari ini.'
        : 'Sebaiknya absen pulang pada jam normal. Bila tetap pulang lebih awal, alasan Anda akan ditinjau ulang.';
}
pushAntreKaryawan($conn, $data['id_karyawan'],
    $action === 'acc' ? 'Pulang cepat disetujui ✅' : 'Pulang cepat ditolak',
    $push_body, 'login.php', 'pulang-cepat-hasil');

header("Location: $redirect_url");
exit;
