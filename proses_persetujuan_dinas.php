<?php
require 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php");
    exit;
}

// Validasi CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Validasi token gagal. Silakan coba lagi.";
    $redirect = $_POST['redirect_url'] ?? 'histori_absensi.php';
    header("Location: $redirect");
    exit;
}

$id_absensi = isset($_POST['id_absensi']) ? intval($_POST['id_absensi']) : 0;
$action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';
$redirect_url = isset($_POST['redirect_url']) ? sanitizeInput($_POST['redirect_url']) : 'histori_absensi.php';

if ($id_absensi <= 0 || !in_array($action, ['acc', 'tolak'])) {
    $_SESSION['error'] = "Data tidak valid.";
    header("Location: $redirect_url");
    exit;
}

// Ambil data absensi
$stmt = $conn->prepare("SELECT a.id, a.id_karyawan, a.keterangan, a.foto_bukti, k.nama_karyawan FROM absensi a JOIN karyawan k ON a.id_karyawan = k.id_karyawan WHERE a.id = ?");
$stmt->bind_param("i", $id_absensi);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data absensi tidak ditemukan.";
    $stmt->close();
    header("Location: $redirect_url");
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();

if ($data['keterangan'] !== 'Pending Dinas') {
    $_SESSION['error'] = "Status absensi bukan Pending Dinas, tidak dapat diproses.";
    header("Location: $redirect_url");
    exit;
}

if ($action === 'tolak') {
    // Hapus foto jika ada
    if (!empty($data['foto_bukti'])) {
        $foto_path = __DIR__ . '/assets/uploads/absensi/' . $data['foto_bukti'];
        if (file_exists($foto_path)) {
            unlink($foto_path);
        }
    }
    
    // Hapus record absensi
    $stmt_del = $conn->prepare("DELETE FROM absensi WHERE id = ?");
    $stmt_del->bind_param("i", $id_absensi);
    if ($stmt_del->execute()) {
        $_SESSION['success'] = "Permintaan Dinas Luar untuk {$data['nama_karyawan']} berhasil ditolak dan dihapus.";
        logActivity($conn, 'tolak_dinas', "Menolak dan menghapus permintaan dinas luar {$data['nama_karyawan']}", $data['id_karyawan']);
    } else {
        $_SESSION['error'] = "Gagal menolak permintaan dinas.";
    }
    $stmt_del->close();
} else if ($action === 'acc') {
    // Update keterangan menjadi "Dinas Luar"
    $stmt_upd = $conn->prepare("UPDATE absensi SET keterangan = 'Dinas Luar' WHERE id = ?");
    $stmt_upd->bind_param("i", $id_absensi);
    if ($stmt_upd->execute()) {
        $_SESSION['success'] = "Permintaan Dinas Luar untuk {$data['nama_karyawan']} berhasil disetujui.";
        logActivity($conn, 'acc_dinas', "Menyetujui permintaan dinas luar {$data['nama_karyawan']}", $data['id_karyawan']);
    } else {
        $_SESSION['error'] = "Gagal menyetujui permintaan dinas.";
    }
    $stmt_upd->close();
}

header("Location: $redirect_url");
exit;
?>

