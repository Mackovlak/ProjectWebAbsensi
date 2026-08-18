<?php
require 'config.php';

function formatTanggalIndonesia($date) {
    $hari = array(
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    );
    $bulan = array(
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli',
        'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober',
        'November' => 'November', 'December' => 'Desember'
    );
    $namaHari = date('l', strtotime($date));
    $namaBulan = date('F', strtotime($date));
    $tanggal = date('d', strtotime($date));
    $tahun = date('Y', strtotime($date));
    return $hari[$namaHari] . ', ' . $tanggal . ' ' . $bulan[$namaBulan] . ' ' . $tahun;
}

$id_karyawan = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';
if (empty($id_karyawan) || !validateIDKaryawan($id_karyawan)) {
    die("ID Karyawan tidak valid atau tidak disediakan.");
}

$stmt = $conn->prepare("SELECT k.nama_karyawan, u.face_descriptor, u.username FROM karyawan k LEFT JOIN users u ON k.id_karyawan = u.id_karyawan WHERE k.id_karyawan = ?");
$stmt->bind_param("s", $id_karyawan);
$stmt->execute();
$result_karyawan = $stmt->get_result();
if ($result_karyawan->num_rows == 0) {
    die("ID Karyawan tidak ditemukan.");
}
$karyawan_data = $result_karyawan->fetch_assoc();
$nama_karyawan = $karyawan_data['nama_karyawan'];
$username_karyawan = $karyawan_data['username'];
$has_face_data = !empty($karyawan_data['face_descriptor']);
$stmt->close();

$today = date('Y-m-d');
$status_absen = 'belum_absen';
$absen_hari_ini = null;
$status_pulang = null;

// Auto-Reject (Delete) Pending Dinas yang sudah lebih dari 4 jam (System-Wide)
$conn->query("DELETE FROM absensi WHERE keterangan = 'Pending Dinas' AND TIMESTAMPDIFF(HOUR, waktu_alasan, NOW()) >= 4");

$stmt_check = $conn->prepare(
    "SELECT a.id, a.jam_masuk, a.jam_pulang, a.keterangan, a.status_masuk, k.id_cabang, a.alasan, a.foto_bukti, a.waktu_alasan
     FROM absensi a 
     JOIN karyawan k ON a.id_karyawan = k.id_karyawan
     WHERE a.id_karyawan = ? AND a.tanggal = ?"
);
$stmt_check->bind_param("ss", $id_karyawan, $today);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $absen_hari_ini = $result_check->fetch_assoc();
    if ($absen_hari_ini['jam_pulang'] != NULL && $absen_hari_ini['jam_pulang'] != '00:00:00') {
        $status_absen = 'sudah_pulang';
        
        $jam_masuk_ts = strtotime($absen_hari_ini['jam_masuk']);
        $jam_pulang_ts = strtotime($absen_hari_ini['jam_pulang']);
        $durasi_menit = ($jam_pulang_ts - $jam_masuk_ts) / 60;
        
        if ($durasi_menit > 0 && $durasi_menit < 330) {
            $status_pulang = 'Setengah Hari';
        } else {
            $stmt_jam = $conn->prepare("SELECT MAX(jam_pulang) as jam_pulang_standar FROM jam_kerja WHERE id_cabang = ?");
            $stmt_jam->bind_param("i", $absen_hari_ini['id_cabang']);
            $stmt_jam->execute();
            $jam_kerja = $stmt_jam->get_result()->fetch_assoc();
            
            if ($jam_kerja && !empty($jam_kerja['jam_pulang_standar'])) {
                if (strtotime($absen_hari_ini['jam_pulang']) > strtotime($jam_kerja['jam_pulang_standar'])) {
                    $status_pulang = 'Overtime';
                } else {
                    $status_pulang = 'Normal';
                }
            } else {
                $status_pulang = 'Normal';
            }
            $stmt_jam->close();
        }
    } else {
        $status_absen = 'sudah_masuk';
    }
}
$stmt_check->close();

// Pengajuan Dinas Luar yang sudah disetujui untuk hari ini (validasi lokasi dilewati)
$izin_dinas_hari_ini = getIzinDinasDisetujui($conn, $id_karyawan, $today);

$disable_pulang = false;
$alasan_disable = '';
if ($status_absen === 'sudah_masuk' && $absen_hari_ini['keterangan'] !== 'Hadir' && $absen_hari_ini['keterangan'] !== 'Dinas Luar') {
    $disable_pulang = true;
    $alasan_disable = $absen_hari_ini['keterangan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Online - Javag Team</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #1e1b4b 0%, #3b0764 50%, #6b21a8 100%); 
            min-height: 100vh; display: flex; justify-content: center; align-items: center;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0;
        }
        #main-container, #success-content {
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; width: 100%; position: fixed; top: 0; left: 0; padding: 20px; box-sizing: border-box; overflow-y: auto;
        }
        .absen-container { 
            background: #ffffff; padding: 32px 24px; border-radius: 28px; 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15); width: 100%; max-width: 380px; 
            text-align: center; animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1); position: relative; margin: auto;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .logo-container {
            display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 24px;
        }
        .logo-img-wrapper {
            background: rgba(255, 255, 255, 0.9); padding: 6px; border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05); display: flex; align-items: center; justify-content: center;
        }
        .logo-img-wrapper img { height: 32px; width: auto; object-fit: contain; }
        .logo-text-wrapper { display: flex; flex-direction: column; justify-content: center; text-align: left; }
        .logo-title { font-size: 20px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; line-height: 1.1; margin: 0; }
        .logo-title span { background: linear-gradient(to right, #f97316, #facc15); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo-subtitle { font-size: 11px; color: #64748b; font-weight: 600; letter-spacing: 0.3px; line-height: 1; margin-top: 2px; }
        h2 { color: #1e293b; margin-bottom: 12px; font-size: 22px; font-weight: 700; line-height: 1.3; }
        p { color: #64748b; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
        .date-text { color: #0ea5e9; font-weight: 600; font-size: 15px; background: #f0f9ff; padding: 8px 16px; border-radius: 12px; display: inline-block; margin-bottom: 24px;}
        .face-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.95); display: none; justify-content: center;
            align-items: center; z-index: 10000;
        }
        .face-verification-box {
            background: white; border-radius: 20px; padding: 30px;
            max-width: 500px; width: 90%; text-align: center;
        }
        .face-video-container {
            position: relative; width: 100%; background: #000;
            border-radius: 15px; overflow: hidden; margin: 20px 0;
        }
        #face-video { width: 100%; transform: scaleX(-1); }
        #face-canvas {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: scaleX(-1);
        }
        .face-guide {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 60%; aspect-ratio: 1; border: 3px dashed rgba(0, 255, 0, 0.6); border-radius: 50%;
        }
        .face-status {
            background: #f8f9fa; padding: 15px; border-radius: 10px;
            margin: 15px 0; font-size: 14px; color: #666;
        }
        .face-status.success { background: #d4edda; color: #155724; }
        .face-status.error { background: #f8d7da; color: #721c24; }
        .btn { 
            width: 100%; padding: 14px; margin: 8px 0 14px 0; border: none; border-radius: 16px; 
            font-size: 14px; font-weight: 700; cursor: pointer; transition: transform 0.1s ease, box-shadow 0.1s ease, background 0.2s ease; 
            display: flex; align-items: center; justify-content: center; gap: 8px; 
            letter-spacing: 0.3px; position: relative;
        }
        .btn:active:not(:disabled) { transform: translateY(5px); box-shadow: 0 0px 0 transparent !important; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px; }
        .action-grid .btn { margin: 0 0 5px 0; padding: 12px 10px; font-size: 13px; border-radius: 14px; }
        
        .btn-hadir { background: #10b981; color: white; box-shadow: 0 5px 0 #059669; padding: 16px !important; font-size: 16px !important;}
        .btn-hadir:hover:not(:disabled) { background: #0f9d6e; }
        
        .btn-pulang { background: #8b5cf6; color: white; box-shadow: 0 5px 0 #7c3aed; }
        .btn-pulang:hover:not(:disabled) { background: #7e4de6; }
        
        .btn-off { background: #64748b; color: white; box-shadow: 0 5px 0 #475569; }
        .btn-off:hover:not(:disabled) { background: #5c6b81; }
        
        .btn-sakit { background: #ec4899; color: white; box-shadow: 0 5px 0 #db2777; }
        .btn-sakit:hover:not(:disabled) { background: #de3c8b; }
        
        .btn-cuti { background: #0ea5e9; color: white; box-shadow: 0 5px 0 #0284c7; }
        .btn-cuti:hover:not(:disabled) { background: #0c98d6; }
        
        .btn-alpha { background: #eab308; color: white; box-shadow: 0 5px 0 #ca8a04; }
        .btn-alpha:hover:not(:disabled) { background: #dbaa07; }
        
        .btn-secondary { background: #64748b; color: white; box-shadow: 0 5px 0 #475569; margin-bottom: 12px !important; }
        .btn-secondary:hover:not(:disabled) { background: #5c6b81; }
        #status-lokasi { 
            font-size: 14px; margin-top: 25px; padding: 15px 20px; 
            background: #f0f0f0; border-radius: 12px; color: #666;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.3s ease; font-weight: 500; border: 2px solid transparent;
        }
        .lokasi-berhasil { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb) !important; 
            color: #155724 !important; border-color: #28a745 !important;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        }
        .lokasi-gagal { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb) !important; 
            color: #721c24 !important; border-color: #dc3545 !important;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2); animation: shake 0.5s ease;
        }
        .absen-info { 
            background: linear-gradient(135deg, #f0f4ff, #e8efff);
            border-left: 5px solid #667eea; padding: 18px; border-radius: 12px; 
            margin-bottom: 20px; text-align: left;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
        }
        .absen-info strong { 
            display: flex; align-items: center; gap: 8px;
            color: #333; margin-bottom: 8px; font-size: 14px;
        }
        .absen-info span { 
            color: #667eea; font-weight: bold; font-size: 20px;
            display: block; margin-left: 28px;
        }
        .absen-info .status-normal { color: #23aa42ff !important; }
        .absen-info .status-overtime { color: #ffa726 !important; }
        .absen-info .status-setengah { color: #d86315ff !important; }
        .info-disabled {
            background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px;
            padding: 12px 15px; margin-bottom: 15px; display: flex;
            align-items: center; gap: 10px; font-size: 13px;
            color: #856404; line-height: 1.4;
        }
        .info-disabled i { font-size: 20px; color: #ffc107; }
        .absen-success .icon { 
            font-size: 80px; color: #00d2ff; margin-bottom: 25px; animation: bounce 1s ease;
        }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
        .loader-wrapper { display: flex; justify-content: center; align-items: center; height: 300px; flex-direction: column; gap: 20px; }
        .loader { width: 60px; height: 60px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading-text { color: #667eea; font-weight: 600; font-size: 16px; }
        .error-container {
            background: #ffffff; padding: 30px 25px; border-radius: 24px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15); text-align: center;
            animation: slideUpBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 380px; width: 95%; border: 1px solid #f0f0f0;
        }
        @keyframes slideUpBounce { 0% { opacity: 0; transform: translateY(40px) scale(0.9); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
        .error-icon { font-size: 70px; margin-bottom: 20px; color: #dc3545; }
        .error-icon.location { color: #ffc107; }
        .error-title { font-size: 22px; font-weight: 700; color: #333; margin-bottom: 12px; }
        .error-message { color: #666; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
        .error-details { background: #f8f9fa; border-radius: 12px; padding: 15px; margin: 20px 0; }
        .detail-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
        .detail-item:last-child { border-bottom: none; }
        .detail-icon { font-size: 24px; }
        .icon-jarak { color: #dc3545; }
        .icon-radius { color: #28a745; }
        .icon-kelebihan { color: #ffc107; }
        .detail-item > div { flex: 1; text-align: left; }
        .detail-item span { display: block; font-size: 12px; color: #666; margin-bottom: 4px; }
        .detail-item strong { font-size: 18px; color: #333; }
        .btn-retry {
            width: 100%; padding: 15px; margin-top: 15px;
            background: linear-gradient(135deg, #891fdfff 0%, #bf198dff 100%);
            color: white; border: none; border-radius: 14px; font-size: 16px;
            font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 5px 20px rgba(59, 130, 246, 0.3);
            display: inline-flex; align-items: center; gap: 10px; justify-content: center;
        }
        .btn-retry:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(59, 130, 246, 0.4); }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-10px); } 75% { transform: translateX(10px); } }
        .swal2-container { z-index: 20000 !important; }
    </style>
</head>
<body>
    <div class="face-overlay" id="face-overlay">
        <div class="face-verification-box">
            <h2><i class="fas fa-user-shield"></i> Verifikasi Wajah</h2>
            <p id="liveness-instruction" style="font-size: 16px; font-weight: bold; color: #4f46e5; margin-bottom: 10px;">Posisikan wajah Anda di dalam panduan</p>
            <div class="face-video-container">
                <video id="face-video" autoplay playsinline></video>
                <canvas id="face-canvas"></canvas>
                <div class="face-guide"></div>
            </div>
            <div class="face-status" id="face-status">
                <i class="fas fa-spinner fa-spin"></i> Memuat sistem face recognition...
            </div>
            <button class="btn btn-secondary" onclick="cancelFaceVerification()">
                <i class="fas fa-times"></i> Batal
            </button>
        </div>
    </div>

    <div id="hidden-inputs-container" style="display: none;">
        <input type="hidden" id="global-id-karyawan" value="<?php echo htmlspecialchars($id_karyawan); ?>">
        <input type="hidden" id="lokasi" value="">
        <input type="hidden" id="face-descriptor" value="">
        <input type="hidden" id="face-confidence" value="">
        <input type="hidden" id="lokasi-pulang" value="">
        <input type="hidden" id="face-descriptor-pulang" value="">
        <input type="hidden" id="face-confidence-pulang" value="">
    </div>

    <div id="main-container">
        <?php if ($status_absen == 'belum_absen'): ?>
        <div class="absen-container">
            <div class="logo-container">
                <div class="logo-img-wrapper">
                    <img src="/assets/images/logo.png" alt="Javag Logo" onerror="this.style.display='none'">
                </div>
                <div class="logo-text-wrapper">
                    <div class="logo-title">Absen<span>Kita</span></div>
                    <div class="logo-subtitle">Java Abadi Gemilang</div>
                </div>
            </div>
            <div class="greeting-wrapper" style="margin-bottom: 24px;">
                <span style="display: block; font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;">Hallo,</span>
                <h2 style="margin: 0; font-size: 24px; color: #1e293b; font-weight: 800; letter-spacing: -0.5px; line-height: 1.2;"><?php echo htmlspecialchars($nama_karyawan); ?></h2>
            </div>
            <?php if ($izin_dinas_hari_ini): ?>
            <div style="background: #e0f2fe; border: 2px solid #0ea5e9; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                <div style="display: flex; align-items: start; gap: 12px;">
                    <i class="fas fa-briefcase" style="color: #0284c7; font-size: 22px; margin-top: 2px;"></i>
                    <div style="text-align: left;">
                        <strong style="color: #075985; font-size: 15px; display: block; margin-bottom: 6px;">Dinas Luar Disetujui</strong>
                        <small style="color: #075985; line-height: 1.6; display: block;">
                            <?php echo htmlspecialchars($izin_dinas_hari_ini['keperluan']); ?><br>
                            <span style="opacity: .85;"><?php echo formatRentangTanggal($izin_dinas_hari_ini['tanggal_mulai'], $izin_dinas_hari_ini['tanggal_selesai']); ?></span>
                        </small>
                        <small style="color: #075985; display: block; margin-top: 8px; font-weight: 600;">
                            Absensi Anda hari ini tidak dibatasi radius lokasi. Verifikasi wajah tetap diperlukan.
                        </small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$has_face_data): ?>
            <div style="background: #ffe5e5; border: 2px solid #dc3545; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                <div style="display: flex; align-items: start; gap: 12px;">
                    <i class="fas fa-exclamation-circle" style="color: #dc3545; font-size: 24px; margin-top: 2px;"></i>
                    <div style="text-align: left;">
                        <strong style="color: #721c24; font-size: 15px; display: block; margin-bottom: 8px;">⚠️ Registrasi Wajah Diperlukan</strong>
                        <small style="color: #721c24; line-height: 1.6; display: block; margin-bottom: 10px;">
                            Untuk absensi <strong>HADIR</strong>, Anda wajib registrasi wajah terlebih dahulu melalui akun Anda.
                        </small>
                        <div style="background: rgba(220, 53, 69, 0.1); padding: 10px; border-radius: 8px; margin-top: 8px;">
                            <small style="color: #721c24; display: block; line-height: 1.5; font-size: 12px; font-weight: 500;">
                                Silakan Bisa Buat Akun Terlebih Dahulu.<br>
                                Jika sudah buat akun, bisa Login ke Akun dan klik ikon garis tiga (<i class="fas fa-bars"></i>) di bagian kiri Pojok atas. lalu pilih "Registrasi Wajah" dan ikuti instruksi selanjutnya.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <p>Silakan pilih status kehadiran Anda untuk hari ini</p>
            <p class="date-text"><?php echo formatTanggalIndonesia($today); ?></p>
            <form id="form-absen">
                <button type="button" class="btn btn-hadir" onclick="submitAbsen('Hadir')"><i class="fas fa-check-circle"></i> HADIR</button>
                <div class="action-grid">
                    <button type="button" class="btn btn-off" onclick="submitAbsenWithConfirm('OFF', 'Apakah Anda yakin hari ini sedang OFF?')"><i class="fas fa-calendar-times"></i> OFF</button>
                    <button type="button" class="btn btn-sakit" onclick="submitAbsenWithConfirm('Sakit', 'Apakah Anda yakin hari ini izin SAKIT?')"><i class="fas fa-heartbeat"></i> SAKIT</button>
                    <button type="button" class="btn btn-cuti" onclick="submitAbsenWithConfirm('Cuti', 'Apakah Anda yakin hari ini izin CUTI?')"><i class="fas fa-calendar-check"></i> CUTI</button>
                    <button type="button" class="btn btn-alpha" onclick="submitAbsenWithConfirm('Alpha', 'Pilih ALPHA jika Anda absen tanpa keterangan. Yakin?')"><i class="fas fa-times-circle"></i> ALPHA</button>
                </div>
            </form>
            
            <?php if (!empty($username_karyawan)): ?>
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px;">
                <a href="login.php?username=<?php echo urlencode($username_karyawan); ?>" class="btn" style="background: #f1f5f9; color: #3b82f6; border: 2px solid #e2e8f0; font-weight: 600; text-decoration: none; box-shadow: none;">
                    <i class="fas fa-user-circle"></i> Login ke Akun
                </a>
            </div>
            <?php else: ?>
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px;">
                <button type="button" onclick="document.getElementById('modal-buat-akun').style.display='flex'" class="btn" style="background: #1e293b; color: white; box-shadow: 0 5px 0 #0f172a; font-weight: 800; letter-spacing: 0.5px;">
                    <i class="fas fa-user-plus"></i> Buat Akun Karyawan
                </button>
            </div>
            <?php endif; ?>
            
            <div id="status-lokasi"><i class="fas fa-map-marker-alt"></i> <span>Mendeteksi lokasi...</span></div>
        </div>
        <?php endif; ?>

        <?php if ($status_absen == 'sudah_masuk'): ?>
        <div class="absen-container">
            <div class="logo-container">
                <div class="logo-img-wrapper">
                    <img src="/assets/images/logo.png" alt="Javag Logo" onerror="this.style.display='none'">
                </div>
                <div class="logo-text-wrapper">
                    <div class="logo-title">Absen<span>Kita</span></div>
                    <div class="logo-subtitle">Java Abadi Gemilang</div>
                </div>
            </div>
            <?php
                $judul_halaman = "Semangat Kerjanya..";
                switch ($absen_hari_ini['keterangan']) {
                    case 'Sakit': $judul_halaman = "Semoga Lekas Sembuh,"; break;
                    case 'Cuti': $judul_halaman = "Selamat Menikmati Hari Cuti,"; break;
                    case 'OFF': $judul_halaman = "Selamat Berlibur,"; break;
                    case 'Alpha': $judul_halaman = "Alphamu Tercatat!,"; break;
                }
            ?>
            <div class="greeting-wrapper" style="margin-bottom: 16px;">
                <span style="display: block; font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;"><?php echo $judul_halaman; ?></span>
                <h2 style="margin: 0; font-size: 24px; color: #1e293b; font-weight: 800; letter-spacing: -0.5px; line-height: 1.2;"><?php echo htmlspecialchars($nama_karyawan); ?></h2>
            </div>
            <p class="date-text"><?php echo formatTanggalIndonesia($today); ?></p>
            <?php if (!$disable_pulang): ?>
                <p>Jangan lupa untuk absen pulang yaaw...</p>
            <?php endif; ?>
            <div class="absen-info">
                <strong><i class="fas fa-sign-in-alt"></i> Jam Masuk:</strong>
                <span><?php echo date('H:i:s', strtotime($absen_hari_ini['jam_masuk'])); ?></span>
            </div>
            <?php if($absen_hari_ini['keterangan'] == 'Hadir'): ?>
            <div class="absen-info">
                <strong><i class="fas fa-info-circle"></i> Status Masuk:</strong>
                <span><?php echo htmlspecialchars($absen_hari_ini['status_masuk']); ?></span>
            </div>
            <?php else: ?>
            <div class="absen-info">
                <strong><i class="fas fa-info-circle"></i> Keterangan:</strong>
                <span><?php echo htmlspecialchars($absen_hari_ini['keterangan']); ?></span>
            </div>
            <?php 
                if (in_array($absen_hari_ini['keterangan'], ['Sakit', 'Cuti', 'Pending Dinas', 'Dinas Luar'])):
                    $btn_text = in_array($absen_hari_ini['keterangan'], ['Pending Dinas', 'Dinas Luar']) ? "Lihat / Edit Detail Dinas" : "Lihat / Edit Alasan";
            ?>
            <button type="button" class="btn btn-secondary" onclick="openEditAlasanModal()" style="background: #3b82f6; border-color: #3b82f6; margin-bottom: 20px;">
                <i class="fas fa-edit"></i> <?php echo $btn_text; ?>
            </button>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($disable_pulang): ?>
            <div class="info-disabled" <?php if($alasan_disable === 'Pending Dinas') echo 'style="background-color: #fff3cd; border-color: #ffeeba; color: #856404;"'; ?>>
                <i class="fas fa-info-circle"></i>
                <div>
                    <?php if ($alasan_disable === 'Pending Dinas'): ?>
                        <strong>Mohon Menunggu Persetujuan</strong><br>
                        Terimakasih atas Absensi Dinas Luarnya, Mohon Menunggu Persetujuan dari Admin untuk kamu bisa melakukan absensi pulang.
                    <?php else: ?>
                        <strong>Absen pulang tidak diperlukan</strong><br>
                        Anda absen dengan keterangan <strong><?php echo htmlspecialchars($alasan_disable); ?></strong>, sehingga tidak perlu absen pulang.
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <form id="form-absen-pulang" onsubmit="event.preventDefault(); submitAbsenPulang();">
                <button type="button" class="btn btn-pulang" onclick="submitAbsenPulang()" <?php echo $disable_pulang ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-out-alt"></i> Absen Pulang
                </button>
            </form>
            <?php if (!empty($username_karyawan)): ?>
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px;">
                <a href="login.php?username=<?php echo urlencode($username_karyawan); ?>" class="btn" style="background: #f1f5f9; color: #3b82f6; border: 2px solid #e2e8f0; font-weight: 600; text-decoration: none; box-shadow: none;">
                    <i class="fas fa-user-circle"></i> Login ke Akun
                </a>
            </div>
            <?php else: ?>
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px;">
                <button type="button" onclick="document.getElementById('modal-buat-akun').style.display='flex'" class="btn" style="background: #1e293b; color: white; box-shadow: 0 5px 0 #0f172a; font-weight: 800; letter-spacing: 0.5px;">
                    <i class="fas fa-user-plus"></i> Buat Akun Karyawan
                </button>
            </div>
            <?php endif; ?>
            
            <?php if (!$disable_pulang): ?>
            <div id="status-lokasi"><i class="fas fa-map-marker-alt"></i> <span>Mendeteksi lokasi...</span></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($status_absen == 'sudah_pulang'): ?>
        <div class="absen-container">
            <div class="logo-container">
                <div class="logo-img-wrapper">
                    <img src="/assets/images/logo.png" alt="Javag Logo" onerror="this.style.display='none'">
                </div>
                <div class="logo-text-wrapper">
                    <div class="logo-title">Absen<span>Kita</span></div>
                    <div class="logo-subtitle">Java Abadi Gemilang</div>
                </div>
            </div>
            <div class="greeting-wrapper" style="margin-bottom: 20px;">
                <span style="display: block; font-size: 14px; color: #10b981; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;">Absensi Selesai!</span>
                <h2 style="margin: 0; font-size: 24px; color: #1e293b; font-weight: 800; letter-spacing: -0.5px; line-height: 1.2;">Terima Kasih,<br><?php echo htmlspecialchars($nama_karyawan); ?></h2>
            </div>
            <p>Anda telah menyelesaikan absensi untuk hari ini.</p>
            <div class="absen-info">
                <strong><i class="fas fa-sign-in-alt"></i> Jam Masuk:</strong>
                <span><?php echo date('H:i:s', strtotime($absen_hari_ini['jam_masuk'])); ?></span>
            </div>
            <div class="absen-info">
                <strong><i class="fas fa-sign-out-alt"></i> Jam Pulang:</strong>
                <span><?php echo date('H:i:s', strtotime($absen_hari_ini['jam_pulang'])); ?></span>
            </div>
            <?php if ($status_pulang): ?>
            <div class="absen-info">
                <strong><i class="fas fa-business-time"></i> Status Pulang:</strong>
                <span class="status-<?php echo $status_pulang == 'Overtime' ? 'overtime' : ($status_pulang == 'Setengah Hari' ? 'setengah' : 'normal'); ?>">
                    <?php echo $status_pulang; ?>
                </span>
            </div>
            <?php endif; ?>
            <?php 
                if (in_array($absen_hari_ini['keterangan'], ['Sakit', 'Cuti', 'Pending Dinas', 'Dinas Luar'])):
                    $btn_text = in_array($absen_hari_ini['keterangan'], ['Pending Dinas', 'Dinas Luar']) ? "Lihat / Edit Detail Dinas" : "Lihat / Edit Alasan";
            ?>
            <button type="button" class="btn btn-secondary" onclick="openEditAlasanModal()" style="background: #3b82f6; border-color: #3b82f6; margin-top: 10px;">
                <i class="fas fa-edit"></i> <?php echo $btn_text; ?>
            </button>
            <?php endif; ?>
            <p style="margin-top:25px; font-size: 16px; color: #667eea;"><i class="fas fa-home"></i> Selamat rehat, jumpa lagi besok...</p>
            
            <?php if (!empty($username_karyawan)): ?>
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px;">
                <a href="login.php?username=<?php echo urlencode($username_karyawan); ?>" class="btn" style="background: #f1f5f9; color: #3b82f6; border: 2px solid #e2e8f0; font-weight: 600; text-decoration: none; box-shadow: none;">
                    <i class="fas fa-user-circle"></i> Login ke Akun
                </a>
            </div>
            <?php else: ?>
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px;">
                <button type="button" onclick="document.getElementById('modal-buat-akun').style.display='flex'" class="btn" style="background: #1e293b; color: white; box-shadow: 0 5px 0 #0f172a; font-weight: 800; letter-spacing: 0.5px;">
                    <i class="fas fa-user-plus"></i> Buat Akun Karyawan
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div id="success-content" style="display:none;">
        <div class="absen-container absen-success">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <h2 id="success-title">Terima Kasih!</h2>
            <p id="success-message">Absensi telah berhasil direkam.</p>
        </div>
    </div>

    <!-- Modal Input Alasan -->
    <div id="modal-input-alasan" class="face-overlay" style="align-items: center; z-index: 10001;">
        <div class="face-verification-box" style="text-align: left; padding: 25px; border-radius: 20px;">
            <h2 id="modal-input-title" style="margin-bottom: 5px;"><i class="fas fa-edit text-brand-500"></i> Alasan Kehadiran</h2>
            <p style="font-size: 13px; margin-bottom: 20px;">Mohon isi alasan (wajib) dan foto bukti (opsional).</p>
            
            <form id="form-input-alasan" onsubmit="event.preventDefault(); submitAlasanForm();">
                <input type="hidden" id="input-keterangan" value="">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Alasan / Keterangan <span style="color: red;">*</span></label>
                    <textarea id="input-alasan-text" required rows="3" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none;"></textarea>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Foto Bukti (Opsional)</label>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('input-foto-bukti').setAttribute('capture', 'environment'); document.getElementById('input-foto-bukti').click();">
                            <i class="fas fa-camera"></i> Kamera
                        </button>
                        <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('input-foto-bukti').removeAttribute('capture'); document.getElementById('input-foto-bukti').click();">
                            <i class="fas fa-image"></i> Galeri
                        </button>
                    </div>
                    <input type="file" id="input-foto-bukti" accept="image/*" style="display: none;" onchange="updateFileName('input-foto-bukti', 'input-file-name')">
                    <div id="input-file-name" style="font-size: 12px; color: #0ea5e9; font-weight: 500; margin-bottom: 5px;"></div>
                    <small style="display: block; margin-top: 5px; color: #666; font-size: 11px;">Maks. 6MB. Format JPG, PNG.</small>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="document.getElementById('modal-input-alasan').style.display='none'">Batal</button>
                    <button type="submit" class="btn" style="background: #0ea5e9; color: white;">Kirim Absensi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Lihat / Edit Alasan -->
    <div id="modal-edit-alasan" class="face-overlay" style="align-items: center; z-index: 10001;">
        <div class="face-verification-box" style="text-align: left; padding: 25px; border-radius: 20px; width: 90%; max-width: 400px; max-height: 90vh; overflow-y: auto;">
            <h2 id="modal-edit-title" style="margin-bottom: 5px;"><i class="fas fa-file-alt text-brand-500"></i> Detail Keterangan</h2>
            <p id="edit-alasan-info" style="font-size: 13px; margin-bottom: 20px;">Anda dapat mengedit data ini maksimal 2 jam setelah dikirim.</p>
            
            <form id="form-edit-alasan" action="update_alasan_karyawan.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_absensi" value="<?php echo $absen_hari_ini ? $absen_hari_ini['id'] : ''; ?>">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Alasan / Keterangan <span style="color: red;">*</span></label>
                    <textarea name="alasan" id="edit-alasan-text" required rows="3" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none;"></textarea>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Foto Bukti Saat Ini</label>
                    <div id="current-foto-container" style="margin-bottom: 10px; display: none;">
                        <img id="current-foto-img" src="" style="max-width: 100%; max-height: 150px; border-radius: 8px; border: 1px solid #ddd;">
                    </div>
                    <p id="no-foto-text" style="font-size: 12px; color: #888; font-style: italic;">Tidak ada foto terlampir.</p>
                    
                    <div id="edit-foto-input-group" style="margin-top: 10px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #333;">Ganti Foto (Opsional)</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('edit-foto-bukti').setAttribute('capture', 'environment'); document.getElementById('edit-foto-bukti').click();">
                                <i class="fas fa-camera"></i> Kamera
                            </button>
                            <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('edit-foto-bukti').removeAttribute('capture'); document.getElementById('edit-foto-bukti').click();">
                                <i class="fas fa-image"></i> Galeri
                            </button>
                        </div>
                        <input type="file" name="foto_bukti" id="edit-foto-bukti" accept="image/*" style="display: none;" onchange="updateFileName('edit-foto-bukti', 'edit-file-name')">
                        <div id="edit-file-name" style="font-size: 12px; color: #0ea5e9; font-weight: 500; margin-bottom: 5px;"></div>
                        <small style="display: block; margin-top: 5px; color: #666; font-size: 11px;">Biarkan kosong jika tidak ingin mengubah foto. Maks. 6MB.</small>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="document.getElementById('modal-edit-alasan').style.display='none'">Tutup</button>
                    <button type="submit" id="btn-save-edit" class="btn" style="background: #10b981; color: white;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Buat Akun Mandiri -->
    <div id="modal-buat-akun" class="face-overlay" style="align-items: center; z-index: 10001;">
        <div class="face-verification-box" style="text-align: left; padding: 25px; border-radius: 20px;">
            <h2 style="margin-bottom: 5px;"><i class="fas fa-user-plus text-brand-500"></i> Buat Akun Karyawan</h2>
            <p style="font-size: 13px; margin-bottom: 20px;">Buat password untuk login ke dashboard mandiri Anda.</p>
            
            <form id="form-buat-akun" onsubmit="event.preventDefault(); submitBuatAkun();">
                <input type="hidden" id="buat-id-karyawan" value="<?php echo htmlspecialchars($id_karyawan); ?>">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">ID Karyawan</label>
                    <input type="text" value="<?php echo htmlspecialchars($id_karyawan); ?>" readonly style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; background: #e2e8f0; color: #64748b; outline: none;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Username (Pilihan) <span style="font-weight: normal; color: #666; font-size: 11px;">*Opsional, untuk login selain ID</span></label>
                    <input type="text" id="buat-username-custom" placeholder="Masukkan username unik" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Password <span style="color: red;">*</span></label>
                    <div style="position: relative;">
                        <input type="password" id="buat-password" required minlength="6" placeholder="Minimal 6 karakter" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none; padding-right: 40px;">
                        <i class="fas fa-eye" id="toggle-buat-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; font-size: 16px;" onclick="togglePasswordVisibility('buat-password', 'toggle-buat-password')"></i>
                    </div>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Konfirmasi Password <span style="color: red;">*</span></label>
                    <div style="position: relative;">
                        <input type="password" id="buat-konfirmasi-password" required minlength="6" placeholder="Ulangi password" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none; padding-right: 40px;">
                        <i class="fas fa-eye" id="toggle-buat-konfirmasi-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; font-size: 16px;" onclick="togglePasswordVisibility('buat-konfirmasi-password', 'toggle-buat-konfirmasi-password')"></i>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="document.getElementById('modal-buat-akun').style.display='none'">Batal</button>
                    <button type="submit" id="btn-submit-buat-akun" class="btn" style="background: #10b981; color: white;">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Dinas Luar -->
    <div id="modal-dinas-luar" class="face-overlay" style="align-items: center; z-index: 10001;">
        <div class="face-verification-box" style="text-align: left; padding: 25px; border-radius: 20px; width: 90%; max-width: 400px; max-height: 90vh; overflow-y: auto;">
            <h2 style="margin-bottom: 5px;"><i class="fas fa-briefcase text-brand-500"></i> Permintaan Dinas Luar</h2>
            <p style="font-size: 13px; margin-bottom: 20px;">Anda berada di luar area radius. Jika sedang dinas luar, silakan isi form di bawah ini untuk meminta persetujuan Admin.</p>
            
            <form id="form-dinas-luar" onsubmit="event.preventDefault(); submitDinasLuarForm();">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Keterangan Dinas <span style="color: red;">*</span></label>
                    <textarea id="dinas-alasan-text" required rows="3" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none;" placeholder="Contoh: Kunjungan klien PT ABC"></textarea>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Foto Bukti <span style="color: red;">*</span></label>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('dinas-foto-bukti').setAttribute('capture', 'environment'); document.getElementById('dinas-foto-bukti').click();">
                            <i class="fas fa-camera"></i> Kamera
                        </button>
                        <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('dinas-foto-bukti').removeAttribute('capture'); document.getElementById('dinas-foto-bukti').click();">
                            <i class="fas fa-image"></i> Galeri
                        </button>
                    </div>
                    <input type="file" id="dinas-foto-bukti" accept="image/*" required style="display: none;" onchange="updateFileName('dinas-foto-bukti', 'dinas-file-name')">
                    <div id="dinas-file-name" style="font-size: 12px; color: #0ea5e9; font-weight: 500; margin-bottom: 5px;"></div>
                    <small style="display: block; margin-top: 5px; color: #666; font-size: 11px;">Maks. 6MB. Format JPG, PNG. (WAJIB)</small>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="document.getElementById('modal-dinas-luar').style.display='none'">Batal</button>
                    <button type="submit" id="btn-submit-dinas" class="btn" style="background: #4f46e5; color: white;"><i class="fas fa-paper-plane"></i> Minta Persetujuan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Overtime -->
    <div id="modal-input-overtime" class="face-overlay" style="align-items: center; z-index: 10001;">
        <div class="face-verification-box" style="text-align: left; padding: 25px; border-radius: 20px;">
            <h2 style="margin-bottom: 5px;"><i class="fas fa-business-time text-brand-500"></i> Keterangan Overtime</h2>
            <p style="font-size: 13px; margin-bottom: 20px;">Anda absen pulang melewati jam kerja. Mohon isi alasan (wajib) dan foto bukti (wajib).</p>
            
            <form id="form-input-overtime" onsubmit="event.preventDefault(); submitOvertimeForm();">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Alasan Overtime <span style="color: red;">*</span></label>
                    <textarea id="overtime-alasan-text" required rows="3" style="width: 100%; padding: 10px; border-radius: 10px; border: 1px solid #ccc; font-family: inherit; font-size: 14px; outline: none;"></textarea>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #333;">Foto Bukti <span style="color: red;">*</span></label>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('overtime-foto-bukti').setAttribute('capture', 'environment'); document.getElementById('overtime-foto-bukti').click();">
                            <i class="fas fa-camera"></i> Kamera
                        </button>
                        <button type="button" class="btn" style="background: #e2e8f0; color: #475569; margin:0; padding: 10px; font-size: 13px; flex: 1;" onclick="document.getElementById('overtime-foto-bukti').removeAttribute('capture'); document.getElementById('overtime-foto-bukti').click();">
                            <i class="fas fa-image"></i> Galeri
                        </button>
                    </div>
                    <input type="file" id="overtime-foto-bukti" accept="image/*" required style="display: none;" onchange="updateFileName('overtime-foto-bukti', 'overtime-file-name')">
                    <div id="overtime-file-name" style="font-size: 12px; color: #0ea5e9; font-weight: 500; margin-bottom: 5px;"></div>
                    <small style="display: block; margin-top: 5px; color: #666; font-size: 11px;">Maks. 6MB. Format JPG, PNG.</small>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" style="background: #f1f5f9; color: #475569;" onclick="document.getElementById('modal-input-overtime').style.display='none'; location.reload();">Batal</button>
                    <button type="submit" class="btn" style="background: #0ea5e9; color: white;">Simpan Overtime</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/face-recognition.js?v=2.0"></script>
    <script>
        function updateFileName(inputId, textId) {
            const input = document.getElementById(inputId);
            const textElement = document.getElementById(textId);
            if (input.files && input.files.length > 0) {
                textElement.innerHTML = '<i class="fas fa-check-circle"></i> File terpilih: ' + input.files[0].name;
            } else {
                textElement.innerHTML = '';
            }
        }
        
        let faceSystem = null;
        let hasFaceData = <?php echo $has_face_data ? 'true' : 'false'; ?>;
        let registeredFaceDescriptors = <?php echo $has_face_data ? $karyawan_data['face_descriptor'] : '[]'; ?>;
        let currentAbsenType = null;

        document.addEventListener('DOMContentLoaded', function() {
            const statusLokasi = document.getElementById('status-lokasi');
            const lokasiInput = document.getElementById('lokasi');
            const lokasiPulangInput = document.getElementById('lokasi-pulang');
            if (!statusLokasi) return;
            if (!navigator.geolocation) {
                statusLokasi.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i><span>Browser Anda tidak mendukung deteksi lokasi</span>`;
                statusLokasi.className = 'lokasi-gagal';
                return;
            }
            statusLokasi.innerHTML = `<i class="fas fa-spinner fa-spin" style="color: #00e5ff;"></i> <span>Mendeteksi lokasi Anda...</span>`;
            statusLokasi.className = '';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude, accuracy } = position.coords;
                    const lokasiValue = `${latitude},${longitude}`;
                    if (lokasiInput) lokasiInput.value = lokasiValue;
                    if (lokasiPulangInput) lokasiPulangInput.value = lokasiValue;
                    statusLokasi.innerHTML = `<i class="fas fa-check-circle" style="color: #28a745;"></i> <span>Lokasi terdeteksi (Akurasi: ${Math.round(accuracy)}m)</span>`;
                    statusLokasi.className = 'lokasi-berhasil';
                },
                (error) => {
                    let errorMsg = "";
                    let errorIcon = "fa-exclamation-triangle";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = "Izin lokasi ditolak. Absen dinonaktifkan.";
                            errorIcon = "fa-lock";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = "Lokasi tidak tersedia. Absen dinonaktifkan.";
                            break;
                        case error.TIMEOUT:
                            errorMsg = "Timeout. Coba lagi atau periksa GPS Anda.";
                            break;
                        default:
                            errorMsg = "Gagal deteksi lokasi. Absen dinonaktifkan.";
                    }
                    statusLokasi.innerHTML = `<i class="fas ${errorIcon}" style="color: #dc3545;"></i> <span>${errorMsg}</span>`;
                    statusLokasi.className = 'lokasi-gagal';
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });

        function submitBuatAkun() {
            const id_karyawan = document.getElementById('buat-id-karyawan').value;
            const username_custom = document.getElementById('buat-username-custom').value;
            const password = document.getElementById('buat-password').value;
            const konfirmasi_password = document.getElementById('buat-konfirmasi-password').value;

            if (password !== konfirmasi_password) {
                Swal.fire('Error', 'Password dan konfirmasi tidak cocok!', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('id_karyawan', id_karyawan);
            formData.append('username_custom', username_custom);
            formData.append('password', password);
            formData.append('konfirmasi_password', konfirmasi_password);

            const btnSubmit = document.getElementById('btn-submit-buat-akun');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btnSubmit.disabled = true;

            fetch('proses_buat_akun_mandiri.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modal-buat-akun').style.display = 'none';
                    Swal.fire({
                        title: 'Berhasil!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Gagal', 'Terjadi kesalahan sistem, silakan coba lagi.', 'error');
            })
            .finally(() => {
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
            });
        }

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function submitAbsenWithConfirm(keterangan, message) {
            Swal.fire({
                title: 'Konfirmasi Kehadiran',
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d946ef',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-check"></i> Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    if (keterangan === 'Sakit' || keterangan === 'Cuti') {
                        document.getElementById('input-keterangan').value = keterangan;
                        document.getElementById('modal-input-title').innerHTML = `<i class="fas fa-edit text-brand-500"></i> Alasan ${keterangan}`;
                        document.getElementById('modal-input-alasan').style.display = 'flex';
                    } else {
                        submitAbsen(keterangan);
                    }
                }
            });
        }

        function bukaModalDinasLuar() {
            document.getElementById('modal-dinas-luar').style.display = 'flex';
        }

        function submitDinasLuarForm() {
            const alasan = document.getElementById('dinas-alasan-text').value;
            const fotoInput = document.getElementById('dinas-foto-bukti');
            
            if (alasan.trim() === '') {
                Swal.fire('Peringatan', 'Keterangan dinas wajib diisi!', 'warning');
                return;
            }
            if (fotoInput.files.length === 0) {
                Swal.fire('Peringatan', 'Foto bukti wajib diunggah!', 'warning');
                return;
            }
            if (fotoInput.files[0].size > 6 * 1024 * 1024) {
                Swal.fire('Peringatan', 'Ukuran foto maksimal 6MB!', 'warning');
                return;
            }

            const btnSubmit = document.getElementById('btn-submit-dinas');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btnSubmit.disabled = true;

            document.getElementById('modal-dinas-luar').style.display = 'none';
            
            // Re-use the existing submitAbsen, but pass isDinasLuar flag
            submitAbsen('Hadir', alasan, fotoInput.files[0], true).finally(() => {
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
            });
        }

        function submitAlasanForm() {
            const keterangan = document.getElementById('input-keterangan').value;
            const alasan = document.getElementById('input-alasan-text').value;
            const fotoInput = document.getElementById('input-foto-bukti');
            let fotoFile = null;
            
            if (alasan.trim() === '') {
                Swal.fire('Peringatan', 'Alasan wajib diisi!', 'warning');
                return;
            }

            if (fotoInput.files.length > 0) {
                fotoFile = fotoInput.files[0];
                if (fotoFile.size > 6 * 1024 * 1024) {
                    Swal.fire('Peringatan', 'Ukuran foto maksimal 6MB!', 'warning');
                    return;
                }
            }

            document.getElementById('modal-input-alasan').style.display = 'none';
            submitAbsen(keterangan, alasan, fotoFile);
        }

        async function submitOvertimeForm() {
            const alasan = document.getElementById('overtime-alasan-text').value;
            const fotoInput = document.getElementById('overtime-foto-bukti');
            
            if (alasan.trim() === '') {
                Swal.fire('Peringatan', 'Alasan Overtime wajib diisi!', 'warning');
                return;
            }
            if (fotoInput.files.length === 0) {
                Swal.fire('Peringatan', 'Foto bukti Overtime wajib diunggah!', 'warning');
                return;
            }
            if (fotoInput.files[0].size > 6 * 1024 * 1024) {
                Swal.fire('Peringatan', 'Ukuran foto maksimal 6MB!', 'warning');
                return;
            }

            document.getElementById('modal-input-overtime').style.display = 'none';
            
            const formData = new FormData();
            formData.append('id_karyawan', document.getElementById('global-id-karyawan').value);
            formData.append('lokasi', document.getElementById('lokasi-pulang').value);
            formData.append('keterangan', 'pulang');
            formData.append('face_descriptor', document.getElementById('face-descriptor-pulang').value || '');
            formData.append('face_confidence', document.getElementById('face-confidence-pulang').value || '');
            formData.append('alasan_pulang', alasan);
            formData.append('foto_pulang', fotoInput.files[0]);
            
            performSubmit(formData);
        }

        async function submitAbsen(keterangan, alasan = '', fotoFile = null, isDinasLuar = false) {
            currentAbsenType = 'masuk';
            const lokasiValue = document.getElementById('lokasi').value;
            // GPS Location requirement ONLY if it's NOT a Dinas Luar attempt
            if (keterangan === 'Hadir' && (!lokasiValue || lokasiValue === '') && !isDinasLuar) {
                Swal.fire('Lokasi Diperlukan', 'Lokasi GPS wajib untuk absen HADIR!\n\nPastikan GPS aktif dan Anda telah memberikan izin lokasi.', 'warning');
                document.getElementById('status-lokasi').style.animation = 'shake 0.5s';
                setTimeout(() => { document.getElementById('status-lokasi').style.animation = '' }, 500);
                return;
            }

            // Define function to build FormData from global hidden inputs
            const buildFormData = () => {
                const formData = new FormData();
                formData.append('id_karyawan', document.getElementById('global-id-karyawan').value);
                formData.append('lokasi', document.getElementById('lokasi').value);
                formData.append('face_descriptor', document.getElementById('face-descriptor').value);
                formData.append('face_confidence', document.getElementById('face-confidence').value);
                formData.append('keterangan', keterangan);
                if (alasan) formData.append('alasan', alasan);
                if (fotoFile) formData.append('foto_bukti', fotoFile);
                if (isDinasLuar) formData.append('is_dinas_luar', 'true');
                return formData;
            };

            if (hasFaceData && keterangan === 'Hadir' && !document.getElementById('face-descriptor').value) {
                try {
                    await verifyFace();
                    const formData = buildFormData();
                    performSubmit(formData);
                } catch (error) {
                    Swal.fire('Verifikasi Gagal', error.message, 'error');
                }
            } else {
                const formData = buildFormData();
                performSubmit(formData);
            }
        }
        async function submitAbsenPulang() {
            currentAbsenType = 'pulang';
            const lokasiValue = document.getElementById('lokasi-pulang').value;
            if (!lokasiValue || lokasiValue === '') {
                alert('⚠️ Lokasi GPS wajib untuk absen PULANG!\n\nPastikan GPS aktif dan Anda telah memberikan izin lokasi.');
                document.getElementById('status-lokasi').style.animation = 'shake 0.5s';
                setTimeout(() => { document.getElementById('status-lokasi').style.animation = '' }, 500);
                return; 
            }
            
            const buildFormDataPulang = () => {
                const formData = new FormData();
                formData.append('id_karyawan', document.getElementById('global-id-karyawan').value);
                formData.append('lokasi', document.getElementById('lokasi-pulang').value);
                formData.append('keterangan', 'pulang');
                formData.append('face_descriptor', document.getElementById('face-descriptor-pulang').value || '');
                formData.append('face_confidence', document.getElementById('face-confidence-pulang').value || '');
                return formData;
            };

            if (hasFaceData) {
                try {
                    await verifyFace();
                    const formData = buildFormDataPulang();
                    performSubmit(formData);
                } catch (error) {
                    Swal.fire('Verifikasi Gagal', error.message, 'error');
                }
            } else {
                const formData = buildFormDataPulang();
                performSubmit(formData);
            }
        }

        async function verifyFace() {
            return new Promise(async (resolve, reject) => {
                try {
                    document.getElementById('face-overlay').style.display = 'flex';
                    const instructionEl = document.getElementById('liveness-instruction');
                    instructionEl.innerHTML = 'Memuat model AI...';
                    instructionEl.style.color = '#4f46e5';
                    
                    updateFaceStatus('loading', 'Memuat model AI...');
                    if (!faceSystem) {
                        faceSystem = new FaceRecognitionSystem();
                        await faceSystem.loadModels();
                    }
                    updateFaceStatus('loading', 'Mengaktifkan kamera...');
                    await faceSystem.startCamera('face-video');
                    
                    // Liveness Challenge Setup
                    const challenges = ['blink', 'mouth'];
                    const currentChallenge = challenges[Math.floor(Math.random() * challenges.length)];
                    let challengePassed = false;
                    
                    if (currentChallenge === 'blink') {
                        instructionEl.innerHTML = 'TANTANGAN: Tolong Kedipkan Mata Anda';
                    } else {
                        instructionEl.innerHTML = 'TANTANGAN: Tolong Buka Mulut / Senyum';
                    }
                    instructionEl.style.color = '#e11d48';
                    updateFaceStatus('loading', 'Ikuti instruksi di atas...');

                    // 10 seconds limit, checking every ~100ms -> ~100 attempts
                    let attempts = 0;
                    const maxAttempts = 100; 
                    
                    const verifyLoop = async () => {
                        if (attempts >= maxAttempts) {
                            faceSystem.stopCamera();
                            document.getElementById('face-overlay').style.display = 'none';
                            reject(new Error('Waktu habis. Gagal mendeteksi liveness. Silakan coba lagi.'));
                            return;
                        }
                        attempts++;
                        try {
                            const result = await faceSystem.captureFaceDescriptor();
                            const quality = faceSystem.validateFaceQuality(result);
                            
                            if (!quality.valid) {
                                updateFaceStatus('error', quality.message);
                                setTimeout(verifyLoop, 300);
                                return;
                            }

                            // 1. Check Liveness First
                            if (!challengePassed) {
                                const passed = faceSystem.checkLiveness(result.landmarks, currentChallenge);
                                if (passed) {
                                    challengePassed = true;
                                    instructionEl.innerHTML = 'Tantangan Berhasil! Mencocokkan Wajah...';
                                    instructionEl.style.color = '#10b981';
                                    updateFaceStatus('success', 'Liveness terdeteksi. Mencocokkan wajah...');
                                    // Wait a tiny bit before matching so the user sees the success message
                                    setTimeout(verifyLoop, 500); 
                                    return;
                                } else {
                                    updateFaceStatus('loading', 'Menunggu gerakan Anda...');
                                    // Kurangi delay menjadi 100ms agar tidak melewatkan kedipan mata yang cepat (100-300ms)
                                    setTimeout(verifyLoop, 100);
                                    return;
                                }
                            }

                            // 2. Liveness passed, now match face identity
                            let bestMatch = { isMatch: false, confidence: 0 };
                            for (let registeredDesc of registeredFaceDescriptors) {
                                const comparison = faceSystem.compareFaces(result.descriptor, registeredDesc);
                                if (comparison.confidence > bestMatch.confidence) {
                                    bestMatch = comparison;
                                }
                            }
                            console.log('Best match:', bestMatch);
                            
                            if (bestMatch.isMatch) {
                                const canvas = document.getElementById('face-canvas');
                                canvas.width = faceSystem.videoElement.videoWidth;
                                canvas.height = faceSystem.videoElement.videoHeight;
                                faceSystem.drawDetection(result, canvas);
                                updateFaceStatus('success', `Wajah terverifikasi! (${bestMatch.confidence.toFixed(1)}%)`);
                                
                                if (currentAbsenType === 'masuk') {
                                    document.getElementById('face-descriptor').value = JSON.stringify(result.descriptor);
                                    document.getElementById('face-confidence').value = bestMatch.confidence.toFixed(2);
                                } else {
                                    document.getElementById('face-descriptor-pulang').value = JSON.stringify(result.descriptor);
                                    document.getElementById('face-confidence-pulang').value = bestMatch.confidence.toFixed(2);
                                }
                                setTimeout(() => {
                                    faceSystem.stopCamera();
                                    document.getElementById('face-overlay').style.display = 'none';
                                    resolve(true);
                                }, 1500);
                            } else {
                                updateFaceStatus('error', `Wajah tidak cocok (${bestMatch.confidence.toFixed(1)}%). Coba lagi...`);
                                // Reset attempt count slightly for identity matching, but still bound by overall maxAttempts
                                setTimeout(verifyLoop, 1000);
                            }
                        } catch (error) {
                            console.error('Verification error:', error);
                            updateFaceStatus('error', error.message);
                            setTimeout(verifyLoop, 500);
                        }
                    };
                    setTimeout(verifyLoop, 500);
                } catch (error) {
                    console.error('Face verification error:', error);
                    if (faceSystem) faceSystem.stopCamera();
                    document.getElementById('face-overlay').style.display = 'none';
                    reject(error);
                }
            });
        }

        function cancelFaceVerification() {
            if (faceSystem) {
                faceSystem.stopCamera();
            }
            document.getElementById('face-overlay').style.display = 'none';
        }

        function updateFaceStatus(type, message) {
            const statusEl = document.getElementById('face-status');
            statusEl.className = `face-status ${type}`;
            let icon = '<i class="fas fa-spinner fa-spin"></i>';
            if (type === 'success') icon = '<i class="fas fa-check-circle"></i>';
            if (type === 'error') icon = '<i class="fas fa-exclamation-triangle"></i>';
            statusEl.innerHTML = `${icon} ${message}`;
        }

        function performSubmit(formData) {
            const mainContainer = document.getElementById('main-container');
            const successContainer = document.getElementById('success-content');
            console.log('=== SUBMIT DATA ===');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            mainContainer.innerHTML = `
                <div class="absen-container">
                    <div class="loader-wrapper">
                        <div class="loader"></div>
                        <div class="loading-text">Memvalidasi dan memproses absensi...</div>
                    </div>
                </div>
            `;
            fetch('proses_absen.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    if (data.success) {
                        mainContainer.style.display = 'none';
                        
                        // Menampilkan SweetAlert2 untuk konfirmasi visual yang lebih jelas
                        Swal.fire({
                            icon: 'success',
                            title: data.title || 'Berhasil',
                            html: data.message,
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                        
                    } else if (data.type === 'overtime_form_required') {
                        // Hilangkan loader, tampilkan modal overtime
                        mainContainer.innerHTML = '';
                        document.getElementById('modal-input-overtime').style.display = 'flex';
                    } else {
                        mainContainer.innerHTML = `
                            <div class="error-container">
                                <div class="error-icon ${data.type === 'location_error' ? 'location' : ''}">
                                    <i class="fas ${data.type === 'location_error' ? 'fa-map-marker-alt' : 'fa-exclamation-circle'}"></i>
                                </div>
                                <div class="error-title">${data.type === 'location_error' ? 'Lokasi Di Luar Area' : 'Absensi Gagal'}</div>
                                <div class="error-message">${data.message}</div>
                                ${data.jarak ? `
                                <div class="error-details">
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt detail-icon icon-jarak"></i>
                                        <div><span>Jarak Anda</span><strong>${Math.round(data.jarak)} meter</strong></div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-check-circle detail-icon icon-radius"></i>
                                        <div><span>Radius Maksimal</span><strong>${data.radius} meter</strong></div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-exclamation-triangle detail-icon icon-kelebihan"></i>
                                        <div><span>Kelebihan</span><strong>${Math.round(data.jarak - data.radius)} meter</strong></div>
                                    </div>
                                </div>
                                ` : ''}
                                <button onclick="location.reload()" class="btn-retry"><i class="fas fa-redo"></i> Coba Lagi</button>
                                ${data.type === 'location_error' && '<?php echo $status_absen; ?>' === 'belum_absen' ? `
                                <button onclick="bukaModalDinasLuar()" class="btn-retry" style="background: #4f46e5; margin-top: 10px; color: white; box-shadow: 0 4px 0 #3730a3; border-color: #3730a3;"><i class="fas fa-briefcase"></i> Sedang Dinas Luar?</button>
                                ` : ''}
                            </div>
                        `;
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    mainContainer.innerHTML = `
                        <div class="error-container">
                            <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="error-title">Error Server</div>
                            <div class="error-message">Server mengembalikan response yang tidak valid.</div>
                            <button onclick="location.reload()" class="btn-retry"><i class="fas fa-redo"></i> Coba Lagi</button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                mainContainer.innerHTML = `
                    <div class="error-container">
                        <div class="error-icon"><i class="fas fa-wifi-slash"></i></div>
                        <div class="error-title">Koneksi Bermasalah</div>
                        <div class="error-message">Tidak dapat terhubung ke server.</div>
                        <button onclick="location.reload()" class="btn-retry"><i class="fas fa-redo"></i> Coba Lagi</button>
                    </div>
                `;
            });
        }

        window.addEventListener('beforeunload', () => {
            if (faceSystem) {
                faceSystem.stopCamera();
            }
        });

        // Edit Alasan Logic
        <?php if ($absen_hari_ini && in_array($absen_hari_ini['keterangan'], ['Sakit', 'Cuti', 'Pending Dinas', 'Dinas Luar'])): ?>
        const alasanData = {
            alasan: <?php echo json_encode($absen_hari_ini['alasan'] ?? ''); ?>,
            foto_bukti: <?php echo json_encode($absen_hari_ini['foto_bukti'] ?? ''); ?>,
            waktu_alasan: <?php echo json_encode($absen_hari_ini['waktu_alasan'] ?? ''); ?>
        };

        function openEditAlasanModal() {
            const ket = "<?php echo $absen_hari_ini['keterangan'] ?? ''; ?>";
            const titleEl = document.getElementById('modal-edit-title');
            if(titleEl) {
                if(ket === 'Pending Dinas' || ket === 'Dinas Luar') {
                    titleEl.innerHTML = '<i class="fas fa-briefcase text-brand-500"></i> Detail Dinas Luar';
                } else {
                    titleEl.innerHTML = '<i class="fas fa-file-alt text-brand-500"></i> Detail Keterangan';
                }
            }
            document.getElementById('edit-alasan-text').value = alasanData.alasan || '';
            
            if (alasanData.foto_bukti) {
                document.getElementById('current-foto-container').style.display = 'block';
                document.getElementById('current-foto-img').src = 'assets/uploads/absensi/' + alasanData.foto_bukti;
                document.getElementById('no-foto-text').style.display = 'none';
            } else {
                document.getElementById('current-foto-container').style.display = 'none';
                document.getElementById('no-foto-text').style.display = 'block';
            }

            // Hitung sisa waktu 2 jam
            let canEdit = true;
            if (alasanData.waktu_alasan) {
                const submitTime = new Date(alasanData.waktu_alasan.replace(/-/g, "/")); // Kompatibilitas iOS
                const now = new Date();
                const diffMs = now - submitTime;
                const diffHours = Math.floor(diffMs / 1000 / 60 / 60);
                if (diffHours >= 2) {
                    canEdit = false;
                }
            } else {
                // Jika belum ada waktu alasan, berarti belum pernah submit alasan, bisa diedit
            }

            const txtArea = document.getElementById('edit-alasan-text');
            const fileInputGroup = document.getElementById('edit-foto-input-group');
            const btnSave = document.getElementById('btn-save-edit');
            const infoText = document.getElementById('edit-alasan-info');

            if (!canEdit) {
                txtArea.disabled = true;
                txtArea.style.background = '#f1f5f9';
                fileInputGroup.style.display = 'none';
                btnSave.style.display = 'none';
                infoText.innerHTML = '<span style="color: #dc3545;">Waktu edit telah habis (Maksimal 2 jam). Anda hanya dapat melihat data.</span>';
            } else {
                txtArea.disabled = false;
                txtArea.style.background = '#fff';
                fileInputGroup.style.display = 'block';
                btnSave.style.display = 'block';
                infoText.innerHTML = 'Anda dapat mengedit data ini maksimal 2 jam setelah dikirim.';
            }

            document.getElementById('modal-edit-alasan').style.display = 'flex';
        }

        document.getElementById('form-edit-alasan').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            
            const fotoInput = document.getElementById('edit-foto-bukti');
            if (fotoInput.files.length > 0) {
                if (fotoInput.files[0].size > 6 * 1024 * 1024) {
                    Swal.fire('Peringatan', 'Ukuran foto maksimal 6MB!', 'warning');
                    return;
                }
            }

            const btnSave = document.getElementById('btn-save-edit');
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

            fetch('update_alasan_karyawan.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                    btnSave.disabled = false;
                    btnSave.innerHTML = 'Simpan Perubahan';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Gagal', 'Terjadi kesalahan sistem.', 'error');
                btnSave.disabled = false;
                btnSave.innerHTML = 'Simpan Perubahan';
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
