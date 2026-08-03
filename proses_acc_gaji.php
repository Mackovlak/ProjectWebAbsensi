<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
    exit();
}

$action = $_POST['action'] ?? '';
$id_slip_input = isset($_POST['id_slip']) ? $_POST['id_slip'] : null;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$id_slips = [];
if (is_array($id_slip_input)) {
    foreach ($id_slip_input as $id) {
        $id_slips[] = (int)$id;
    }
} else {
    $id_slips[] = (int)$id_slip_input;
}
$id_slips = array_filter($id_slips, function($val) { return $val > 0; });

if (empty($id_slips)) {
    echo json_encode(['success' => false, 'message' => 'ID Slip tidak valid.']);
    exit();
}

$placeholders = implode(',', array_fill(0, count($id_slips), '?'));
$types = str_repeat('i', count($id_slips));

if ($action === 'acc_admin') {
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit();
    }
    
    // Check if admin has TTD
    $stmt = $conn->prepare("SELECT ttd_path FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB Error (admin_ttd prepare): ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (empty($admin_data['ttd_path'])) {
        echo json_encode(['success' => false, 'message' => 'Anda belum mengupload Tanda Tangan Digital. Silakan upload di Pengaturan Akun terlebih dahulu.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE slip_gaji SET status_admin_acc = 1, admin_acc_at = NOW(), admin_id = ? WHERE id IN ($placeholders)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB Error (admin_acc prepare): ' . $conn->error]);
        exit();
    }
    $bind_types = "i" . $types;
    $bind_params = array_merge([$user_id], $id_slips);
    $stmt->bind_param($bind_types, ...$bind_params);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Slip gaji berhasil di-ACC.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
    }
    $stmt->close();

} elseif ($action === 'acc_owner') {
    if ($role !== 'owner') {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit();
    }
    
    // Check if owner has TTD and Stempel
    $stmt = $conn->prepare("SELECT ttd_path, stempel_path FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB Error (owner_ttd prepare): ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $owner_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (empty($owner_data['ttd_path']) || empty($owner_data['stempel_path'])) {
        echo json_encode(['success' => false, 'message' => 'Anda belum mengupload Tanda Tangan atau Stempel. Silakan lengkapi di Pengaturan Akun terlebih dahulu.']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE slip_gaji SET status_owner_acc = 1, owner_acc_at = NOW(), owner_id = ? WHERE id IN ($placeholders)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB Error (owner_acc prepare): ' . $conn->error]);
        exit();
    }
    $bind_types = "i" . $types;
    $bind_params = array_merge([$user_id], $id_slips);
    $stmt->bind_param($bind_types, ...$bind_params);
    if ($stmt->execute()) {
        // --- Integrasi WhatsApp Fonnte ---
        foreach ($id_slips as $slip_id) {
            $q_wa = "SELECT s.gaji_bersih, k.id_karyawan, k.nama_karyawan, k.no_whatsapp, 
                            a.nama as nama_admin, a.wa_token 
                     FROM slip_gaji s 
                     JOIN karyawan k ON s.id_karyawan = k.id_karyawan 
                     LEFT JOIN users a ON s.admin_id = a.id 
                     WHERE s.id = ?";
            $stmt_wa = $conn->prepare($q_wa);
            $stmt_wa->bind_param("i", $slip_id);
            $stmt_wa->execute();
            $res_wa = $stmt_wa->get_result()->fetch_assoc();
            $stmt_wa->close();

            if ($res_wa && !empty($res_wa['no_whatsapp']) && !empty($res_wa['wa_token'])) {
                $target = $res_wa['no_whatsapp'];
                $token = $res_wa['wa_token'];
                $gaji = number_format($res_wa['gaji_bersih'], 0, ',', '.');
                $nama = $res_wa['nama_karyawan'];
                $id_k = $res_wa['id_karyawan'];
                $admin = $res_wa['nama_admin'] ?: 'Admin';
                
                // Construct URL Login
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $domain = $_SERVER['HTTP_HOST'];
                $base_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']);
                $login_url = $base_url . "/login.php?username=" . urlencode($id_k);

                $pesan = "Assalamualaikum\n*" . $nama . "*\n\nKami mau menginformasikan bahwa Slip Gaji Kamu telah selesai diperiksa dan *Telah Disetujui*.\n\nTotal Gaji Bersih (Digenapkan) : *Rp " . $gaji . "*\n\nSilakan login ke link dibawah ini untuk melihat rincian slip gaji:\n" . $login_url . "\n\nThanks for your hard work and performance 😊\n\n" . $admin . "\n*ManagementHRDDinia*";

                $curl = curl_init();
                curl_setopt_array($curl, array(
                  CURLOPT_URL => 'https://api.fonnte.com/send',
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => '',
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 5, // Timeout to prevent long wait
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'POST',
                  CURLOPT_POSTFIELDS => array(
                    'target' => $target,
                    'message' => $pesan, 
                    'countryCode' => '62',
                  ),
                  CURLOPT_HTTPHEADER => array(
                    'Authorization: ' . $token
                  ),
                ));
                curl_exec($curl);
                curl_close($curl);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Slip gaji berhasil disetujui Owner dan Notifikasi WA telah dikirim (jika token diatur).']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
    }
    $stmt->close();

} elseif ($action === 'acc_karyawan') {
    if ($role !== 'staff') {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit();
    }
    
    // Karyawan tidak diwajibkan memiliki TTD untuk bisa ACC
    // (Jika mereka punya, akan dirender di PDF. Jika tidak, akan kosong).

    $stmt = $conn->prepare("UPDATE slip_gaji SET status_karyawan_acc = 1, karyawan_acc_at = NOW() WHERE id IN ($placeholders)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB Error (karyawan_acc prepare): ' . $conn->error]);
        exit();
    }
    $stmt->bind_param($types, ...$id_slips);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Slip gaji berhasil Anda setujui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate database.']);
    }
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
}
