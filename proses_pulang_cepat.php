<?php
/**
 * Pengajuan Izin Pulang Cepat (Setengah Hari) - dari kiosk absen.php
 * ====================================================================
 * Sessionless, sama seperti proses_absen.php - diidentifikasi lewat
 * id_karyawan, bukan sesi login. Karyawan yang sudah absen masuk (Hadir/
 * Dinas Luar) hari ini dan belum absen pulang bisa mengajukan izin pulang
 * lebih awal. OPSIONAL - pemberitahuan di muka saja: absen pulang lebih awal
 * TIDAK menunggu persetujuan (lihat jamPulangCepatPerluTinjau() di
 * izin_functions.php); alasan yang dikirim di sini dipakai saat absen pulang
 * sehingga tidak perlu diisi ulang, lalu ditinjau Admin/Supervisor kemudian.
 * Tidak memotong kuota tahunan sama sekali - hanya menandai baris absensi hari ini.
 */
require 'config.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function outputJSON($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $rate_check = checkRateLimit('pulang_cepat', 5);
    if (!$rate_check['allowed']) {
        outputJSON(['success' => false, 'message' => 'Tunggu ' . $rate_check['remaining'] . ' detik untuk mencoba lagi.']);
    }

    $id_karyawan = isset($_POST['id_karyawan']) ? sanitizeInput($_POST['id_karyawan']) : '';
    $alasan = isset($_POST['alasan']) ? sanitizeInput($_POST['alasan']) : '';
    $tanggal = date('Y-m-d');

    if (empty($id_karyawan) || !validateIDKaryawan($id_karyawan)) {
        outputJSON(['success' => false, 'message' => 'ID Karyawan tidak valid.']);
    }

    if (strlen($alasan) < 5) {
        outputJSON(['success' => false, 'message' => 'Alasan wajib diisi minimal 5 karakter.']);
    }

    $stmt = $conn->prepare("SELECT id, keterangan, jam_masuk, jam_pulang, izin_pulang_cepat
                            FROM absensi
                            WHERE id_karyawan = ? AND tanggal = ?
                            LIMIT 1");
    $stmt->bind_param("ss", $id_karyawan, $tanggal);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['jam_masuk']) || !in_array($row['keterangan'], ['Hadir', 'Dinas Luar'], true)) {
        outputJSON(['success' => false, 'message' => 'Anda belum absen masuk hari ini, sehingga tidak bisa mengajukan izin pulang cepat.']);
    }

    if (!empty($row['jam_pulang'])) {
        outputJSON(['success' => false, 'message' => 'Anda sudah absen pulang hari ini.']);
    }

    if ($row['izin_pulang_cepat'] === 'Pending') {
        outputJSON(['success' => false, 'message' => 'Pengajuan izin pulang cepat Anda hari ini sudah terkirim. Anda bisa langsung absen pulang tanpa menunggu persetujuan.']);
    }

    if ($row['izin_pulang_cepat'] === 'Disetujui') {
        outputJSON(['success' => false, 'message' => 'Izin pulang cepat Anda hari ini sudah disetujui. Silakan langsung absen pulang.']);
    }

    $alasan = mb_substr($alasan, 0, 255);
    $stmt_upd = $conn->prepare("UPDATE absensi SET izin_pulang_cepat = 'Pending', alasan_pulang_cepat = ? WHERE id = ?");
    $stmt_upd->bind_param("si", $alasan, $row['id']);

    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        logActivity($conn, 'ajukan_pulang_cepat', "Ajukan izin pulang cepat via kiosk absen", $id_karyawan);

        $stmt_cb = $conn->prepare("SELECT nama_karyawan, id_cabang FROM karyawan WHERE id_karyawan = ?");
        $stmt_cb->bind_param("s", $id_karyawan);
        $stmt_cb->execute();
        $kry_push = $stmt_cb->get_result()->fetch_assoc();
        $stmt_cb->close();
        if ($kry_push) {
            $pulang_cepat_ke = urutanPulangCepatBulan($conn, $id_karyawan, $tanggal);
            $tanda_batas = $pulang_cepat_ke > PULANG_CEPAT_MAKS_PER_BULAN
                ? " · ke-{$pulang_cepat_ke} bulan ini ⚠ melebihi batas " . PULANG_CEPAT_MAKS_PER_BULAN . "x"
                : '';
            pushAntreApprover($conn, (int)$kry_push['id_cabang'], 'Rencana pulang cepat',
                $kry_push['nama_karyawan'] . ' · ' . $alasan . $tanda_batas, 'kelola_pengajuan_izin.php',
                'pulang-cepat-' . $id_karyawan . '-' . $tanggal);
        }
        outputJSON([
            'success' => true,
            'title' => 'Permintaan Terkirim',
            'message' => 'Pemberitahuan pulang cepat Anda telah dikirim ke atasan. Anda bisa langsung absen pulang kapan saja tanpa menunggu persetujuan - atasan akan meninjaunya kemudian.'
        ]);
    } else {
        $stmt_upd->close();
        outputJSON(['success' => false, 'message' => 'Gagal mengirim pengajuan. Silakan coba lagi.']);
    }

} catch (Exception $e) {
    outputJSON(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
}
