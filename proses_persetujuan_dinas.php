<?php
require 'config.php';
requireLogin();

if (!isAdmin() && !isSupervisor()) {
    $_SESSION['error'] = "Akses ditolak. Persetujuan Dinas Luar hanya untuk Admin atau Supervisor.";
    header("Location: " . dashboardUntukRole($_SESSION['role'] ?? 'staff'));
    exit;
}

$default_redirect = isSupervisor() ? 'supervisor_dashboard.php' : 'histori_absensi.php';
$redirect_url = isset($_POST['redirect_url']) ? basename(sanitizeInput($_POST['redirect_url'])) : $default_redirect;
if (!preg_match('/^[a-zA-Z0-9_-]+\.php$/', $redirect_url)) {
    $redirect_url = $default_redirect;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $default_redirect");
    exit;
}

// Validasi CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Validasi token gagal. Silakan coba lagi.";
    header("Location: $redirect_url");
    exit;
}

$id_absensi = isset($_POST['id_absensi']) ? intval($_POST['id_absensi']) : 0;
$action = isset($_POST['action']) ? sanitizeInput($_POST['action']) : '';

if ($id_absensi <= 0 || !in_array($action, ['acc', 'tolak'])) {
    $_SESSION['error'] = "Data tidak valid.";
    header("Location: $redirect_url");
    exit;
}

// Ambil data absensi beserta cabang untuk penegakan scope Supervisor.
$stmt = $conn->prepare("SELECT a.id, a.id_karyawan, a.keterangan, a.foto_bukti,
                               k.nama_karyawan, k.id_cabang
                        FROM absensi a
                        JOIN karyawan k ON a.id_karyawan = k.id_karyawan
                        WHERE a.id = ?");
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

if (isSupervisor()) {
    $cabang_supervisor = getCabangReviewer($conn, $_SESSION['user_id'], 'supervisor');
    if ($cabang_supervisor <= 0 || (int)$data['id_cabang'] !== (int)$cabang_supervisor) {
        $_SESSION['error'] = "Permintaan Dinas Luar ini berada di luar cabang yang Anda supervisi.";
        header("Location: $redirect_url");
        exit;
    }
}

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

