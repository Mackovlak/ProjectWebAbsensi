<?php
/**
 * SLIP GAJI FORM - Complete Version Tailwind
 * Auto-calculation from attendance data
 */
require 'config.php';
requireAdmin();

// Validate karyawan ID
if (!isset($_GET['id_karyawan'])) {
    $_SESSION['error_message'] = "ID Karyawan tidak valid.";
    redirect("slip_gaji.php");
}

$id_karyawan = sanitizeInput($_GET['id_karyawan']);

// Get karyawan data
$stmt = $conn->prepare("
    SELECT k.*, c.nama_cabang, c.id as cabang_id, j.nama_jabatan, j.tunjangan_jabatan 
    FROM karyawan k
    LEFT JOIN cabang c ON k.id_cabang = c.id
    LEFT JOIN jabatan j ON k.id_jabatan = j.id
    WHERE k.id_karyawan = ?
");
$stmt->bind_param("s", $id_karyawan);
$stmt->execute();
$karyawan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$karyawan) {
    $_SESSION['error_message'] = "Karyawan tidak ditemukan.";
    redirect("slip_gaji.php");
}

// Periode default
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Get attendance data via raw SQL (menggantikan stored procedure untuk kompatibilitas hosting)
$sql_absensi = "SELECT 
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') THEN a.id END) as total_hadir_raw,
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as total_setengah_hari,
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') AND a.status_masuk = 'Terlambat' THEN a.id END) as total_terlambat,
    SUM(CASE 
        WHEN a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
        AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) >= 330
        AND a.jam_pulang > (
            SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1
        )
        THEN 
            CASE WHEN (TIME_TO_SEC(a.jam_pulang) - TIME_TO_SEC((SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1))) < 2100 THEN 0.5 ELSE 1 END
        ELSE 0 
    END) as total_overtime,
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') AND DAYOFWEEK(a.tanggal) = 1 THEN a.id END) as total_ahad_full_raw,
    COUNT(DISTINCT CASE 
        WHEN a.keterangan IN ('Hadir', 'Dinas Luar') AND DAYOFWEEK(a.tanggal) = 1
        AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as total_ahad_setengah
FROM karyawan k
LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
    AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
WHERE k.id_karyawan = ?";

$stmt = $conn->prepare($sql_absensi);
$stmt->bind_param("iis", $bulan, $tahun, $id_karyawan);
$stmt->execute();
$raw_absensi = $stmt->get_result()->fetch_assoc();
$stmt->close();

$absensi = [
    'total_hari_hadir' => ($raw_absensi['total_hadir_raw'] ?? 0) - (($raw_absensi['total_setengah_hari'] ?? 0) * 0.5),
    'total_terlambat' => $raw_absensi['total_terlambat'] ?? 0,
    'total_overtime' => $raw_absensi['total_overtime'] ?? 0,
    'total_ahad_full' => ($raw_absensi['total_ahad_full_raw'] ?? 0) - ($raw_absensi['total_ahad_setengah'] ?? 0),
    'total_ahad_setengah' => $raw_absensi['total_ahad_setengah'] ?? 0
];

// ---------- Lembur hari lembur (Sabtu) ----------
// Jam kerja Sabtu bervariasi dan tidak bisa diukur dari selisih jam_pulang
// shift, jadi dihitung dari durasi kerja sebenarnya. Hanya jabatan dengan
// overtime_sabtu = 1 yang dihitung. Angkanya DISARANKAN, bukan otomatis
// menimpa - admin yang memutuskan lewat tombol "Tambahkan".
$lembur_sabtu = getLemburHariSabtu($conn, $id_karyawan, $bulan, $tahun);

// Check existing slip
$stmt = $conn->prepare("SELECT * FROM slip_gaji WHERE id_karyawan = ? AND bulan = ? AND tahun = ?");
$stmt->bind_param("sii", $id_karyawan, $bulan, $tahun);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_edit = !empty($existing);

$is_locked = false;
if ($is_edit && !empty($existing['created_at'])) {
    if ((time() - strtotime($existing['created_at'])) > (5 * 24 * 60 * 60)) {
        $is_locked = true;
    }
}

// Get penghasilan tambahan
$penghasilan_extra = [];
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM slip_gaji_penghasilan WHERE id_slip_gaji = ? ORDER BY urutan");
    $stmt->bind_param("i", $existing['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $penghasilan_extra[] = $row;
    }
    $stmt->close();
}

// Get potongan tambahan
$potongan_extra = [];
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM slip_gaji_potongan WHERE id_slip_gaji = ? ORDER BY urutan");
    $stmt->bind_param("i", $existing['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $potongan_extra[] = $row;
    }
    $stmt->close();
}

// --- FILTER BPJS DARI EXTRA ---
$bpjs_inc = [];
$other_inc = [];
foreach ($penghasilan_extra as $pe) {
    if (in_array($pe['keterangan'], ['Tunj. JHT (BPJS TK)', 'Tunj. JKK (BPJS TK)', 'Tunj. JKM (BPJS TK)'])) {
        $bpjs_inc[$pe['keterangan']] = $pe['nominal'];
    } else {
        $other_inc[] = $pe;
    }
}
$has_bpjs_inc = !empty($bpjs_inc);
$umk_inc = 2484162;
if (isset($bpjs_inc['Tunj. JHT (BPJS TK)']) && $bpjs_inc['Tunj. JHT (BPJS TK)'] > 0) {
    $umk_inc = round($bpjs_inc['Tunj. JHT (BPJS TK)'] / (3.70 / 100));
}

$bpjs_dec = [];
$other_dec = [];
foreach ($potongan_extra as $pe) {
    if (in_array($pe['keterangan'], ['Iuran JHT Peserta (BPJS TK)', 'Iuran Perusahaan (BPJS TK)'])) {
        $bpjs_dec[$pe['keterangan']] = $pe['nominal'];
    } else {
        $other_dec[] = $pe;
    }
}
$has_bpjs_dec = !empty($bpjs_dec);
$umk_dec = 2484162;
if (isset($bpjs_dec['Iuran JHT Peserta (BPJS TK)']) && $bpjs_dec['Iuran JHT Peserta (BPJS TK)'] > 0) {
    $umk_dec = round($bpjs_dec['Iuran JHT Peserta (BPJS TK)'] / (2.00 / 100));
}

$months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Generate URL Avatar Karyawan
$jenis_kelamin = $karyawan['jenis_kelamin'] ?? 'L';
if (!empty($karyawan['foto']) && file_exists('assets/images/foto_karyawan/' . $karyawan['foto'])) {
    $avatar_url = "assets/images/foto_karyawan/" . $karyawan['foto'];
} else {
    if ($jenis_kelamin == 'P') {
        // Wanita: Style Karir Berhijab
        $avatar_url = "assets/images/avatar_p.png?v=2";
    } else {
        // Pria: Style Karir
        $avatar_url = "assets/images/avatar_l.png?v=2";
    }
}

require 'admin_header.php';
?>

<!-- MAIN CONTENT -->
<div class="flex-1 p-4 sm:p-6 lg:p-8">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Buat / Edit Slip Gaji</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Form ini terhubung langsung dengan data histori absensi karyawan.</p>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <a href="slip_gaji_karyawan.php?cabang=<?php echo $karyawan['cabang_id']; ?>" class="flex-1 sm:flex-none flex justify-center items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl transition-colors font-medium text-sm shadow-sm">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
            <?php if($is_edit): ?>
            <button onclick="exportPDF()" type="button" class="flex-1 sm:flex-none flex justify-center items-center gap-2 px-4 py-2.5 bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-400 dark:hover:bg-rose-800/50 border border-rose-200 dark:border-rose-800 rounded-xl transition-colors font-medium text-sm shadow-sm">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if($is_locked): ?>
    <div class="mb-6 p-4 bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-800 dark:text-rose-400 flex items-start gap-3 shadow-sm">
        <i class="fa-solid fa-lock mt-1 text-rose-500"></i>
        <div>
            <h4 class="font-bold text-sm">Slip Gaji Terkunci (Lebih dari 5 Hari)</h4>
            <p class="text-xs mt-1">Slip gaji ini dibuat pada <b><?php echo date('d M Y H:i', strtotime($existing['created_at'])); ?></b>. Tombol simpan default dan update form telah dinonaktifkan secara otomatis untuk menjaga integritas data histori bulan lalu.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN FORM -->
    <form id="formSlipGaji" method="POST" action="slip_gaji_process.php" class="flex flex-col xl:flex-row gap-6">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="id_karyawan" value="<?php echo $id_karyawan; ?>">
        <input type="hidden" name="slip_id" value="<?php echo $existing['id'] ?? ''; ?>">
        <input type="hidden" name="is_edit" value="<?php echo $is_edit ? 1 : 0; ?>">
        
        <!-- KOLOM KIRI (FORM INPUT) -->
        <div class="flex-1 space-y-6">
            
            <!-- 1. DATA DASAR & INFO ABSENSI -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-brand-500/5 rounded-bl-full -z-10"></div>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 dark:border-slate-700 pb-4 mb-4 relative z-10">
                        <div class="flex items-center gap-4">
                            <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover bg-white dark:bg-slate-700 border-2 border-slate-200 dark:border-slate-600 shadow-sm shrink-0">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800 dark:text-white uppercase"><?php echo htmlspecialchars($karyawan['nama_karyawan']); ?></h3>
                            <p class="text-sm text-brand-600 dark:text-brand-400 font-medium"><?php echo htmlspecialchars($karyawan['nama_jabatan']); ?> - <?php echo htmlspecialchars($karyawan['nama_cabang']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="flex flex-col w-1/2 sm:w-auto">
                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">Bulan</label>
                            <select name="bulan" id="selectBulan" class="px-3 py-1.5 border border-slate-200 dark:border-slate-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white transition-colors cursor-pointer">
                                <?php foreach($months as $i => $m): ?>
                                    <option value="<?php echo $i+1; ?>" <?php echo ($i+1==$bulan)?'selected':''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col w-1/2 sm:w-auto">
                            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">Tahun</label>
                            <select name="tahun" id="selectTahun" class="px-3 py-1.5 border border-slate-200 dark:border-slate-600 rounded-lg text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white transition-colors cursor-pointer">
                                <?php for($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y==$tahun)?'selected':''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Data Absensi ditarik dari DB (Readonly Info) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700/50 text-center">
                        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase mb-1">Kehadiran</p>
                        <p class="font-bold text-emerald-600 dark:text-emerald-400"><span><?php echo $absensi['total_hari_hadir'] ?? 0; ?></span> Hari</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700/50 text-center">
                        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase mb-1">Terlambat</p>
                        <p class="font-bold text-rose-600 dark:text-rose-400"><span><?php echo $absensi['total_terlambat'] ?? 0; ?></span> Kali</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700/50 text-center">
                        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase mb-1">Hari Minggu</p>
                        <p class="font-bold text-fuchsia-600 dark:text-fuchsia-400"><span><?php echo ($absensi["total_ahad_full"]??0) + (($absensi["total_ahad_setengah"]??0)*0.5); ?></span> Hari</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700/50 text-center">
                        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase mb-1">Overtime</p>
                        <p class="font-bold text-purple-600 dark:text-purple-400"><span><?php echo $absensi['total_overtime'] ?? 0; ?></span> Jam</p>
                    </div>
                </div>
            </div>

            <!-- 2. PENGHASILAN / PENERIMAAN -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 px-6 py-3 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-trend-up"></i> KOMPONEN PENGHASILAN
                    </h3>
                </div>
                
                <div class="p-6 space-y-4" id="penghasilanContainer">
                    <!-- Gaji Pokok -->
                    <div class="flex items-center gap-4">
                        <div class="w-1/3 text-sm font-semibold text-slate-700 dark:text-slate-300">Gaji Pokok</div>
                        <div class="w-2/3 flex items-center gap-1.5">
                            <div class="relative flex-1 group">
                                <span class="absolute left-3 top-2.5 text-slate-400 text-sm group-focus-within:text-brand-500 transition-colors">Rp</span>
                                <input type="text" id="gajiPokokInput" inputmode="numeric" value="<?php echo number_format($existing['gaji_pokok'] ?? $karyawan['gaji_pokok'] ?? 0, 0, ',', '.'); ?>" class="format-rp inc-input hidden-real-input w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm outline-none focus:ring-2 focus:ring-brand-500 bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white font-mono transition-colors">
                                <input type="hidden" id="gajiPokokValue" name="gaji_pokok" value="<?php echo $existing['gaji_pokok'] ?? $karyawan['gaji_pokok'] ?? 0; ?>">
                            </div>
                            <?php if(!$is_locked): ?>
                            <button type="button" onclick="saveRate('gaji_pokok', 'gajiPokokValue', this)" class="shrink-0 px-3 py-2.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-800/50 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm font-semibold transition-colors" title="Simpan sebagai Default Karyawan">
                                <i class="fa-solid fa-save"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tunjangan CS -->
                    <div class="flex items-center gap-4">
                        <div class="w-1/3 text-sm font-semibold text-slate-700 dark:text-slate-300">Tunjangan Jabatan</div>
                        <div class="w-2/3 flex items-center gap-2">
                            <div class="relative flex-1 group">
                                <span class="absolute left-3 top-2.5 text-slate-400 text-sm">Rp</span>
                                <input type="text" id="tunjanganJabatanDisplay" value="<?php echo number_format(isset($existing['tunjangan_cs']) ? $existing['tunjangan_cs'] : ($karyawan['tunjangan_jabatan'] ?? 0), 0, ',', '.'); ?>" readonly class="format-rp inc-input hidden-real-input w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 cursor-not-allowed outline-none font-mono transition-colors">
                                <input type="hidden" id="tunjanganJabatanValue" name="tunjangan_cs" value="<?php echo isset($existing['tunjangan_cs']) ? $existing['tunjangan_cs'] : ($karyawan['tunjangan_jabatan'] ?? 0); ?>">
                            </div>
                            <?php if(!$is_locked): ?>
                            <button type="button" onclick="sesuaikanTunjanganJabatan(<?php echo $karyawan['tunjangan_jabatan'] ?? 0; ?>)" class="flex items-center justify-center gap-1.5 px-3 py-2.5 bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-800/50 border border-sky-200 dark:border-sky-800 rounded-xl text-xs font-semibold transition-colors shrink-0" title="Update ke Master Data">
                                <i class="fa-solid fa-sync"></i> Sesuaikan
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Removed Akomodasi -->

                    <hr class="border-dashed border-slate-200 dark:border-slate-700 my-4">

                    <!-- Transport (Otomatis Absen) -->
                    <div class="flex items-start gap-4">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Transport</p>
                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1"><i class="fa-solid fa-link"></i> Auto x Hadir (<?php echo $absensi['total_hari_hadir'] ?? 0; ?>)</p>
                        </div>
                        <div class="w-2/3 flex items-center gap-1.5">
                            <div class="relative flex-1 group">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rate</span>
                                <input type="text" id="rateTransportInput" inputmode="numeric" value="<?php echo number_format($existing['transport_nominal'] ?? $karyawan['rate_transport'] ?? 40000, 0, ',', '.'); ?>" class="format-rp calc-rate hidden-real-input w-full pl-10 pr-2 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                                <input type="hidden" id="rateTransportValue" name="transport_nominal" value="<?php echo $existing['transport_nominal'] ?? $karyawan['rate_transport'] ?? 40000; ?>">
                            </div>
                            <?php if(!$is_locked): ?>
                            <button type="button" onclick="saveRate('rate_transport', 'rateTransportValue', this)" class="shrink-0 px-3 py-2.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-800/50 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm font-semibold transition-colors" title="Simpan sebagai Default Karyawan">
                                <i class="fa-solid fa-save"></i>
                            </button>
                            <?php endif; ?>
                            <div class="text-slate-400 font-bold px-1">=</div>
                            <div class="relative flex-1">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rp</span>
                                <input type="text" id="resTransport" value="0" readonly class="format-rp inc-input w-full pl-8 pr-3 py-2.5 border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-900/20 rounded-xl text-right text-sm font-mono font-bold text-emerald-700 dark:text-emerald-400 cursor-not-allowed outline-none">
                                <input type="hidden" name="transport_hari" id="inTransportHari" value="">
                            </div>
                            <?php if(!$is_locked && $is_edit): ?>
                            <button type="button" onclick="resetAttendanceCalculation('hadir')" class="shrink-0 px-2.5 py-2.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-800/50 border border-amber-200 dark:border-amber-800 rounded-xl text-sm font-semibold transition-colors" title="Reset hitungan ke Absen terbaru">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($lembur_sabtu['berhak']) && $lembur_sabtu['total_jam'] > 0): ?>
                    <!-- Saran lembur hari Sabtu -->
                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50">
                        <i class="fa-solid fa-hourglass-half text-amber-600 dark:text-amber-400 mt-0.5"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
                                Lembur hari Sabtu: <?php echo $lembur_sabtu['total_jam']; ?> jam
                                (<?php echo count($lembur_sabtu['rincian']); ?> hari)
                            </p>
                            <p class="text-xs text-amber-700 dark:text-amber-400/90 mt-1">
                                <?php
                                $potongan_rincian = array_slice($lembur_sabtu['rincian'], 0, 5);
                                $teks = [];
                                foreach ($potongan_rincian as $r) {
                                    $teks[] = date('j M', strtotime($r['tanggal'])) . ' ('
                                        . substr($r['jam_masuk'], 0, 5) . '-' . substr($r['jam_pulang'], 0, 5)
                                        . ' = ' . $r['jam'] . 'j)';
                                }
                                echo safe_output(implode(', ', $teks));
                                if (count($lembur_sabtu['rincian']) > 5) {
                                    echo ' &hellip; +' . (count($lembur_sabtu['rincian']) - 5) . ' hari lagi';
                                }
                                ?>
                            </p>
                            <p class="text-[11px] text-amber-600 dark:text-amber-500 mt-1.5">
                                Belum termasuk pada kolom Overtime di bawah &mdash; tekan tombol untuk menambahkannya.
                            </p>
                        </div>
                        <?php if (!$is_locked): ?>
                        <button type="button" onclick="tambahLemburSabtu(<?php echo (float)$lembur_sabtu['total_jam']; ?>, this)"
                                class="shrink-0 px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambahkan
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Overtime (Otomatis Absen) -->
                    <div class="flex items-start gap-4">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Overtime (Lembur)</p>
                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1"><i class="fa-solid fa-link"></i> Auto x Jam (<?php echo $absensi['total_overtime'] ?? 0; ?>)</p>
                        </div>
                        <div class="w-2/3 flex items-center gap-1.5">
                            <div class="relative flex-1 group">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rate</span>
                                <input type="text" id="rateOvertimeInput" inputmode="numeric" value="<?php echo number_format($existing['overtime_nominal'] ?? $karyawan['rate_overtime'] ?? 7500, 0, ',', '.'); ?>" class="format-rp calc-rate hidden-real-input w-full pl-10 pr-2 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                                <input type="hidden" id="rateOvertimeValue" name="overtime_nominal" value="<?php echo $existing['overtime_nominal'] ?? $karyawan['rate_overtime'] ?? 7500; ?>">
                            </div>
                            <?php if(!$is_locked): ?>
                            <button type="button" onclick="saveRate('rate_overtime', 'rateOvertimeValue', this)" class="shrink-0 px-3 py-2.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-800/50 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm font-semibold transition-colors" title="Simpan sebagai Default Karyawan">
                                <i class="fa-solid fa-save"></i>
                            </button>
                            <?php endif; ?>
                            <div class="text-slate-400 font-bold px-1">=</div>
                            <div class="relative flex-1">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rp</span>
                                <input type="text" id="resOvertime" value="0" readonly class="format-rp inc-input w-full pl-8 pr-3 py-2.5 border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-900/20 rounded-xl text-right text-sm font-mono font-bold text-emerald-700 dark:text-emerald-400 cursor-not-allowed outline-none">
                                <input type="hidden" name="overtime_jam" id="inOvertimeJam" value="">
                            </div>
                            <?php if(!$is_locked && $is_edit): ?>
                            <button type="button" onclick="resetAttendanceCalculation('lembur')" class="shrink-0 px-2.5 py-2.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-800/50 border border-amber-200 dark:border-amber-800 rounded-xl text-sm font-semibold transition-colors" title="Reset hitungan ke Absen terbaru">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hari Ahad (Otomatis Absen) -->
                    <div class="flex items-start gap-4">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Insentif Minggu</p>
                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-1"><i class="fa-solid fa-link"></i> Auto x Hari (<?php echo ($absensi["total_ahad_full"]??0) + (($absensi["total_ahad_setengah"]??0)*0.5); ?>)</p>
                        </div>
                        <div class="w-2/3 flex items-center gap-1.5">
                            <div class="relative flex-1 group">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rate</span>
                                <input type="text" id="rateInsentifInput" inputmode="numeric" value="<?php echo number_format($existing['insentif_ahad_nominal'] ?? $karyawan['rate_insentif_minggu'] ?? 25000, 0, ',', '.'); ?>" class="format-rp calc-rate hidden-real-input w-full pl-10 pr-2 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                                <input type="hidden" id="rateInsentifValue" name="insentif_ahad_nominal" value="<?php echo $existing['insentif_ahad_nominal'] ?? $karyawan['rate_insentif_minggu'] ?? 25000; ?>">
                            </div>
                            <?php if(!$is_locked): ?>
                            <button type="button" onclick="saveRate('rate_insentif_minggu', 'rateInsentifValue', this)" class="shrink-0 px-3 py-2.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-800/50 border border-emerald-200 dark:border-emerald-800 rounded-xl text-sm font-semibold transition-colors" title="Simpan sebagai Default Karyawan">
                                <i class="fa-solid fa-save"></i>
                            </button>
                            <?php endif; ?>
                            <div class="text-slate-400 font-bold px-1">=</div>
                            <div class="relative flex-1">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rp</span>
                                <input type="text" id="resAhad" value="0" readonly class="format-rp inc-input w-full pl-8 pr-3 py-2.5 border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-900/20 rounded-xl text-right text-sm font-mono font-bold text-emerald-700 dark:text-emerald-400 cursor-not-allowed outline-none">
                                <input type="hidden" name="insentif_ahad_hari" id="inInsentifAhadHari" value="">
                            </div>
                            <?php if(!$is_locked && $is_edit): ?>
                            <button type="button" onclick="resetAttendanceCalculation('ahad')" class="shrink-0 px-2.5 py-2.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-800/50 border border-amber-200 dark:border-amber-800 rounded-xl text-sm font-semibold transition-colors" title="Reset hitungan ke Absen terbaru">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Penghasilan Ekstra Existing -->
                    <div id="penghasilanExtraExisting">
                        <?php foreach($other_inc as $pe): ?>
                            <div class="flex items-start gap-4 group mt-3 dynamic-row">
                                <div class="w-1/3">
                                    <input type="text" name="penghasilan_ket[]" value="<?php echo htmlspecialchars($pe['keterangan']); ?>" class="w-full bg-transparent border-b border-dashed border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-300 outline-none focus:border-brand-500 placeholder-slate-400 px-1 py-1" placeholder="Keterangan">
                                </div>
                                <div class="w-2/3 flex items-center gap-2">
                                    <div class="relative w-2/5">
                                        <span class="absolute left-2 top-2 text-slate-400 text-xs">Rp</span>
                                        <input type="text" name="penghasilan_rate[]" inputmode="numeric" value="<?php echo number_format($pe['rate'] ?? $pe['nominal'], 0, ',', '.'); ?>" class="format-rp dyn-rate w-full pl-7 pr-1 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                                    </div>
                                    <div class="text-slate-400 font-bold text-xs">x</div>
                                    <div class="relative w-1/5">
                                        <input type="number" name="penghasilan_qty[]" value="<?php echo floatval($pe['qty'] ?? 1); ?>" step="0.1" class="dyn-qty w-full px-1 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-center text-sm bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors" title="Jumlah Hari/Kali">
                                    </div>
                                    <div class="text-slate-400 font-bold text-xs">=</div>
                                    <div class="relative w-[35%] flex justify-between items-center gap-2">
                                        <input type="text" readonly value="<?php echo number_format($pe['nominal'], 0, ',', '.'); ?>" class="format-rp dyn-total w-full pl-2 pr-1 py-2 border-transparent bg-slate-50 dark:bg-slate-800 rounded-xl text-right text-sm font-bold text-slate-800 dark:text-white outline-none font-mono">
                                        <input type="hidden" name="penghasilan_nom[]" value="<?php echo $pe['nominal']; ?>">
                                        <button type="button" onclick="this.closest('.dynamic-row').remove(); calculateAll();" class="text-slate-300 hover:text-red-500 transition-colors" title="Hapus"><i class="fa-solid fa-xmark text-lg"></i></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
                
                <!-- Area Tambah Dinamis: Penghasilan -->
                <div class="px-6 pb-2">
                    <button type="button" onclick="addInputRow('penghasilanContainer', 'inc-input', 'penghasilan_ket[]', 'penghasilan_nom[]', 'penghasilan_rate[]', 'penghasilan_qty[]')" class="text-sm font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 flex items-center gap-1.5 bg-brand-50 dark:bg-brand-900/30 px-3 py-1.5 rounded-lg transition-colors">
                        <i class="fa-solid fa-plus"></i> Tambah Penghasilan Lainnya
                    </button>
                </div>
                
                <!-- Area Khusus BPJS TK Penghasilan -->
                <div id="bpjsIncContainer" class="px-6 pb-2"></div>

                <div class="px-6 pb-6">
                    <button type="button" onclick="addBpjsPenghasilan()" id="btnAddBpjsInc" class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-lg transition-colors">
                        <i class="fa-solid fa-plus"></i> BPJS Ketenagakerjaan (TK)
                    </button>
                </div>
            </div>

            <!-- 3. POTONGAN -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="bg-rose-50 dark:bg-rose-900/20 px-6 py-3 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                    <h3 class="font-bold text-rose-700 dark:text-rose-400 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-trend-down"></i> KOMPONEN POTONGAN
                    </h3>
                </div>
                
                <div class="p-6 space-y-4" id="potonganContainer">
                    
                    <!-- Keterlambatan (Otomatis Absen) -->
                    <div class="flex items-start gap-4">
                        <div class="w-1/3">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Pot. Keterlambatan</p>
                            <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-1"><i class="fa-solid fa-link"></i> Auto x Telat (<?php echo $absensi['total_terlambat'] ?? 0; ?>)</p>
                        </div>
                        <div class="w-2/3 flex items-center gap-1.5">
                            <div class="relative flex-1 group">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rate</span>
                                <input type="text" id="rateTelatInput" inputmode="numeric" value="<?php echo number_format($existing['keterlambatan_nominal'] ?? $karyawan['rate_keterlambatan'] ?? 20000, 0, ',', '.'); ?>" class="format-rp calc-rate hidden-real-input w-full pl-10 pr-2 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                                <input type="hidden" id="rateTelatValue" name="keterlambatan_nominal" value="<?php echo $existing['keterlambatan_nominal'] ?? $karyawan['rate_keterlambatan'] ?? 20000; ?>">
                            </div>
                            <?php if(!$is_locked): ?>
                            <button type="button" onclick="saveRate('rate_keterlambatan', 'rateTelatValue', this)" class="shrink-0 px-3 py-2.5 bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-800/50 border border-rose-200 dark:border-rose-800 rounded-xl text-sm font-semibold transition-colors" title="Simpan sebagai Default Karyawan">
                                <i class="fa-solid fa-save"></i>
                            </button>
                            <?php endif; ?>
                            <div class="text-slate-400 font-bold px-1">=</div>
                            <div class="relative flex-1">
                                <span class="absolute left-2 top-2.5 text-slate-400 text-xs">Rp</span>
                                <input type="text" id="resTelat" value="0" readonly class="format-rp dec-input w-full pl-8 pr-3 py-2.5 border border-rose-200 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-900/20 rounded-xl text-right text-sm font-mono font-bold text-rose-700 dark:text-rose-400 cursor-not-allowed outline-none">
                                <input type="hidden" name="keterlambatan_jumlah" id="inKeterlambatanJumlah" value="">
                            </div>
                            <?php if(!$is_locked && $is_edit): ?>
                            <button type="button" onclick="resetAttendanceCalculation('telat')" class="shrink-0 px-2.5 py-2.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-800/50 border border-amber-200 dark:border-amber-800 rounded-xl text-sm font-semibold transition-colors" title="Reset hitungan ke Absen terbaru">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Potongan Ekstra Existing -->
                    <div id="potonganExtraExisting">
                        <?php foreach($other_dec as $po): ?>
                            <div class="flex items-start gap-4 group mt-3 dynamic-row">
                                <div class="w-1/3">
                                    <input type="text" name="potongan_ket[]" value="<?php echo htmlspecialchars($po['keterangan']); ?>" class="w-full bg-transparent border-b border-dashed border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-300 outline-none focus:border-brand-500 placeholder-slate-400 px-1 py-1" placeholder="Keterangan">
                                </div>
                                <div class="w-2/3 flex items-center gap-2">
                                    <div class="relative w-2/5">
                                        <span class="absolute left-2 top-2 text-slate-400 text-xs">Rp</span>
                                        <input type="text" name="potongan_rate[]" inputmode="numeric" value="<?php echo number_format($po['rate'] ?? $po['nominal'], 0, ',', '.'); ?>" class="format-rp dyn-rate w-full pl-7 pr-1 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                                    </div>
                                    <div class="text-slate-400 font-bold text-xs">x</div>
                                    <div class="relative w-1/5">
                                        <input type="number" name="potongan_qty[]" value="<?php echo floatval($po['qty'] ?? 1); ?>" step="0.1" class="dyn-qty w-full px-1 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-center text-sm bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors" title="Jumlah Hari/Kali">
                                    </div>
                                    <div class="text-slate-400 font-bold text-xs">=</div>
                                    <div class="relative w-[35%] flex justify-between items-center gap-2">
                                        <input type="text" readonly value="<?php echo number_format($po['nominal'], 0, ',', '.'); ?>" class="format-rp dyn-total w-full pl-2 pr-1 py-2 border-transparent bg-slate-50 dark:bg-slate-800 rounded-xl text-right text-sm font-bold text-slate-800 dark:text-white outline-none font-mono">
                                        <input type="hidden" name="potongan_nom[]" value="<?php echo $po['nominal']; ?>">
                                        <button type="button" onclick="this.closest('.dynamic-row').remove(); calculateAll();" class="text-slate-300 hover:text-red-500 transition-colors" title="Hapus"><i class="fa-solid fa-xmark text-lg"></i></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

                <!-- Area Tambah Dinamis: Potongan -->
                <div class="px-6 pb-2">
                    <button type="button" onclick="addInputRow('potonganContainer', 'dec-input', 'potongan_ket[]', 'potongan_nom[]', 'potongan_rate[]', 'potongan_qty[]')" class="text-sm font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 flex items-center gap-1.5 bg-rose-50 dark:bg-rose-900/30 px-3 py-1.5 rounded-lg transition-colors">
                        <i class="fa-solid fa-plus"></i> Tambah Potongan Lainnya
                    </button>
                </div>
                
                <!-- Area Khusus BPJS TK Potongan -->
                <div id="bpjsDecContainer" class="px-6 pb-2"></div>

                <div class="px-6 pb-6">
                    <button type="button" onclick="addBpjsPotongan()" id="btnAddBpjsDec" class="text-sm font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 flex items-center gap-1.5 bg-rose-50 dark:bg-rose-900/30 px-3 py-1.5 rounded-lg transition-colors">
                        <i class="fa-solid fa-plus"></i> BPJS Ketenagakerjaan (TK)
                    </button>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN (SUMMARY / PREVIEW SLIP) -->
        <div class="w-full xl:w-96 flex-shrink-0">
            <div class="bg-slate-800 rounded-3xl shadow-xl overflow-hidden sticky top-8 border border-slate-700">
                
                <div class="p-6 text-center border-b border-slate-700/50 bg-slate-900/50">
                    <h3 class="text-slate-400 text-xs uppercase tracking-widest font-bold mb-1">Preview Perhitungan</h3>
                    <h2 class="text-white text-xl font-bold">SLIP GAJI</h2>
                    <p class="text-slate-300 text-sm font-medium mt-1 uppercase"><?php echo htmlspecialchars($karyawan['nama_karyawan']); ?></p>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400">Total Penghasilan</span>
                        <span class="text-emerald-400 font-mono font-bold" id="txtTotalInc">Rp 0</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400">Total Potongan</span>
                        <span class="text-rose-400 font-mono font-bold" id="txtTotalDec">Rp 0</span>
                    </div>
                    
                    <hr class="border-dashed border-slate-600 my-2">
                    
                    <!-- Fitur Auto Target Gaji Bersih -->
                    <div class="flex justify-between items-center text-sm mb-2 group">
                        <span class="text-brand-300 font-semibold cursor-help" title="Ketik target gaji akhir di sini, sistem akan otomatis menghitung selisih pembulatannya.">Target Gaji (Auto) <i class="fa-solid fa-wand-magic-sparkles text-xs ml-1"></i></span>
                        <input type="text" id="inTargetNet" inputmode="numeric" placeholder="Ketik target..." class="format-rp w-28 bg-brand-900/30 border border-brand-500/50 text-brand-100 rounded text-right px-2 py-1 outline-none focus:border-brand-400 focus:ring-1 focus:ring-brand-400 font-mono text-xs transition-colors placeholder-brand-300/50" <?php echo $is_locked ? 'disabled' : ''; ?>>
                    </div>

                    <!-- Penyesuaian/Pembulatan -->
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400">Penggenapan / Pembulatan</span>
                        <input type="text" id="digenapkanDisplay" inputmode="numeric" value="<?php echo number_format($existing['digenapkan'] ?? 0, 0, ',', '.'); ?>" class="format-rp hidden-real-input w-28 bg-slate-700 border border-slate-600 text-white rounded text-right px-2 py-1 outline-none focus:border-brand-500 font-mono text-xs transition-colors <?php echo $is_locked ? 'opacity-60 cursor-not-allowed' : ''; ?>" <?php echo $is_locked ? 'readonly' : ''; ?>>
                        <input type="hidden" name="digenapkan" id="inAdjustment" value="<?php echo $existing['digenapkan'] ?? 0; ?>">
                    </div>

                    <div class="bg-brand-600 rounded-xl p-4 mt-6 text-center shadow-inner relative overflow-hidden">
                        <i class="fa-solid fa-sack-dollar absolute -right-2 -bottom-2 text-5xl opacity-20 text-white"></i>
                        <p class="text-brand-100 text-xs uppercase tracking-wider font-semibold mb-1 relative z-10">Total Gaji Bersih</p>
                        <p class="text-white text-2xl font-bold font-mono tracking-tight relative z-10" id="txtNetSalary">Rp 0</p>
                    </div>
                </div>
                
                <div class="p-4 bg-slate-900 flex flex-col gap-3">
                    <?php if(!$is_locked): ?>
                    <button type="submit" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold rounded-xl transition-transform hover:-translate-y-0.5 shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-save"></i> <?php echo $is_edit ? 'UPDATE' : 'SIMPAN'; ?> DATA SLIP
                    </button>
                    <?php else: ?>
                    <button type="button" class="w-full py-3 bg-slate-700 text-slate-400 font-bold rounded-xl cursor-not-allowed flex items-center justify-center gap-2 opacity-80" disabled>
                        <i class="fa-solid fa-lock"></i> SLIP TERKUNCI (MELEWATI 5 HARI)
                    </button>
                    <?php endif; ?>
                    <?php if($is_edit): ?>
                    <button type="button" onclick="exportPDF()" class="w-full py-2.5 bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                        <i class="fa-solid fa-print"></i> Cetak PDF
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- SCRIPT LOGIKA PERHITUNGAN OTOMATIS & FORMAT ANGKA -->
<script>
    // Data Histori (Real-time DB)
    const realtimeAbsen = { 
        hadir: <?php echo $absensi['total_hari_hadir'] ?? 0; ?>, 
        telat: <?php echo $absensi['total_terlambat'] ?? 0; ?>, 
        ahad: <?php echo ($absensi["total_ahad_full"]??0) + (($absensi["total_ahad_setengah"]??0)*0.5); ?>, 
        lembur: <?php echo $absensi['total_overtime'] ?? 0; ?> 
    };

    // Data Digunakan untuk Kalkulasi (Locked jika sudah save, kecuali di-reset)
    let calcAbsen = {
        hadir: <?php echo isset($existing['transport_hari']) ? $existing['transport_hari'] : ($absensi['total_hari_hadir'] ?? 0); ?>,
        telat: <?php echo isset($existing['keterlambatan_jumlah']) ? $existing['keterlambatan_jumlah'] : ($absensi['total_terlambat'] ?? 0); ?>,
        ahad: <?php echo isset($existing['insentif_ahad_hari']) ? $existing['insentif_ahad_hari'] : (($absensi["total_ahad_full"]??0) + (($absensi["total_ahad_setengah"]??0)*0.5)); ?>,
        lembur: <?php echo isset($existing['overtime_jam']) ? $existing['overtime_jam'] : ($absensi['total_overtime'] ?? 0); ?>
    };

    // Tambahkan jam lembur Sabtu ke hitungan overtime (sekali klik saja)
    function tambahLemburSabtu(jam, btn) {
        calcAbsen.lembur = (parseFloat(calcAbsen.lembur) || 0) + jam;
        calculateAll();
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Ditambahkan';
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Lembur Sabtu ditambahkan',
                text: jam + ' jam masuk ke perhitungan Overtime. Periksa nominalnya sebelum menyimpan.',
                timer: 2600,
                showConfirmButton: false
            });
        }
    }

    function resetAttendanceCalculation(type) {
        if(type === 'hadir') calcAbsen.hadir = realtimeAbsen.hadir;
        else if(type === 'telat') calcAbsen.telat = realtimeAbsen.telat;
        else if(type === 'ahad') calcAbsen.ahad = realtimeAbsen.ahad;
        else if(type === 'lembur') calcAbsen.lembur = realtimeAbsen.lembur;
        
        calculateAll();
        
        // Show SweetAlert confirmation
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Di-reset!',
                text: 'Perhitungan telah disesuaikan dengan data kehadiran terbaru.',
                timer: 1500,
                showConfirmButton: false
            });
        }
    }

    // --- REKONSTRUKSI BPJS JIKA ADA DATA ---
    const hasBpjsInc = <?php echo $has_bpjs_inc ? 'true' : 'false'; ?>;
    const umkInc = <?php echo $umk_inc; ?>;
    
    const hasBpjsDec = <?php echo $has_bpjs_dec ? 'true' : 'false'; ?>;
    const umkDec = <?php echo $umk_dec; ?>;

    document.addEventListener('DOMContentLoaded', () => {
        if (hasBpjsInc) {
            addBpjsPenghasilan();
            const block = document.querySelector('#bpjsIncContainer .bpjs-block');
            if (block) {
                block.querySelector('.bpjs-umk').value = formatRibuan(umkInc);
                calculateBpjsBlock(block);
            }
        }
        
        if (hasBpjsDec) {
            addBpjsPotongan();
            const block = document.querySelector('#bpjsDecContainer .bpjs-block');
            if (block) {
                block.querySelector('.bpjs-umk').value = formatRibuan(umkDec);
                calculateBpjsBlock(block);
            }
        }
        
        calculateAll();
    });

    const inAdjustment = document.getElementById('inAdjustment');

    // --- FUNGSI FORMAT ANGKA (RIBUAN) ---
    // Menambahkan titik setiap 3 digit
    function formatRibuan(angka) {
        if (!angka && angka !== 0) return '';
        let parts = angka.toString().split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        return parts.join('.');
    }
    
    // Menghapus titik untuk operasi matematika
    function parseRibuan(stringAngka) {
        if (!stringAngka && stringAngka !== '0') return 0;
        return parseFloat(stringAngka.toString().replace(/\./g, '').replace(/,/g, '.')) || 0;
    }

    // Format untuk Label Text (Kanan Summary)
    const formatRupiah = (number) => {
        return "Rp " + formatRibuan(number);
    };

    // Sinkronisasi Hidden Input dengan Input Ribuan
    function syncHiddenInput(visibleInput) {
        let hiddenInput = visibleInput.nextElementSibling;
        if(hiddenInput && hiddenInput.tagName.toLowerCase() === 'input' && hiddenInput.type === 'hidden') {
            hiddenInput.value = parseRibuan(visibleInput.value);
        }
    }

    // --- FUNGSI UTAMA KALKULASI ---
    function calculateAll() {
        // Update all hidden inputs first
        document.querySelectorAll('.hidden-real-input').forEach(inp => {
            syncHiddenInput(inp);
        });

        // Validasi Target Gaji Input vs Gaji Pokok
        const gajiPokokVal = parseFloat(document.getElementById('gajiPokokValue').value) || 0;
        const inTargetNet = document.getElementById('inTargetNet');
        const isSlipLocked = <?php echo $is_locked ? 'true' : 'false'; ?>;
        
        if (inTargetNet) {
            if (isSlipLocked) {
                inTargetNet.disabled = true;
                inTargetNet.classList.add('opacity-60', 'cursor-not-allowed');
                inTargetNet.parentElement.setAttribute('title', 'Slip gaji telah terkunci');
            } else if (gajiPokokVal <= 0) {
                inTargetNet.disabled = true;
                inTargetNet.placeholder = "Isi Gaji Pokok!";
                inTargetNet.classList.add('opacity-60', 'cursor-not-allowed');
                inTargetNet.parentElement.setAttribute('title', 'Gaji Pokok harus diisi terlebih dahulu');
            } else {
                inTargetNet.disabled = false;
                inTargetNet.placeholder = "Ketik target...";
                inTargetNet.classList.remove('opacity-60', 'cursor-not-allowed');
                inTargetNet.parentElement.setAttribute('title', 'Ketik target gaji akhir di sini, sistem akan otomatis menghitung selisih pembulatannya.');
            }
        }

        // 1. Kalkulasi Auto dari Rate x Absensi (calcAbsen)
        const rateTransport = parseFloat(document.querySelector('[name="transport_nominal"]').value) || 0;
        const resTransport = rateTransport * calcAbsen.hadir;
        document.getElementById('resTransport').value = formatRibuan(resTransport);
        if(document.getElementById('inTransportHari')) document.getElementById('inTransportHari').value = calcAbsen.hadir;

        const rateOvertime = parseFloat(document.querySelector('[name="overtime_nominal"]').value) || 0;
        const resOvertime = rateOvertime * calcAbsen.lembur;
        document.getElementById('resOvertime').value = formatRibuan(resOvertime);
        if(document.getElementById('inOvertimeJam')) document.getElementById('inOvertimeJam').value = calcAbsen.lembur;

        const rateAhad = parseFloat(document.querySelector('[name="insentif_ahad_nominal"]').value) || 0;
        const resAhad = rateAhad * calcAbsen.ahad;
        document.getElementById('resAhad').value = formatRibuan(resAhad);
        if(document.getElementById('inInsentifAhadHari')) document.getElementById('inInsentifAhadHari').value = calcAbsen.ahad;

        const rateTelat = parseFloat(document.querySelector('[name="keterlambatan_nominal"]').value) || 0;
        const resTelat = rateTelat * calcAbsen.telat;
        document.getElementById('resTelat').value = formatRibuan(resTelat);
        if(document.getElementById('inKeterlambatanJumlah')) document.getElementById('inKeterlambatanJumlah').value = calcAbsen.telat;

        // 2. Jumlahkan Semua Penghasilan
        let totalPenghasilan = resTransport + resOvertime + resAhad;
        
        // Dari hidden inputs untuk penghasilan statis & dinamis
        document.querySelectorAll('input[name="gaji_pokok"], input[name="tunjangan_cs"]').forEach(input => {
            totalPenghasilan += parseFloat(input.value) || 0;
        });
        
        document.querySelectorAll('input[name="penghasilan_nom[]"]').forEach(input => {
            totalPenghasilan += parseFloat(input.value) || 0;
        });

        document.getElementById('txtTotalInc').textContent = formatRupiah(totalPenghasilan);

        // 3. Jumlahkan Semua Potongan
        let totalPotongan = resTelat;
        document.querySelectorAll('input[name="potongan_nom[]"]').forEach(input => {
            totalPotongan += parseFloat(input.value) || 0;
        });
        document.getElementById('txtTotalDec').textContent = formatRupiah(totalPotongan);

        // 4. Kalkulasi Gaji Bersih
        const adj = parseFloat(inAdjustment.value) || 0;
        const netSalary = (totalPenghasilan - totalPotongan) + adj;
        document.getElementById('txtNetSalary').textContent = formatRupiah(netSalary);
    }

    // --- FUNGSI TAMBAH BLOK BPJS PENGHASILAN ---
    function addBpjsPenghasilan() {
        const container = document.getElementById('bpjsIncContainer');
        document.getElementById('btnAddBpjsInc').style.display = 'none';

        const div = document.createElement('div');
        div.className = 'bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-4 bpjs-block shadow-sm';
        div.innerHTML = `
            <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-3">
                <h4 class="text-sm font-bold text-slate-800 dark:text-white">Tunjangan BPJS TK (Perusahaan)</h4>
                <button type="button" onclick="this.closest('.bpjs-block').remove(); document.getElementById('btnAddBpjsInc').style.display='flex'; calculateAll();" class="text-slate-400 hover:text-red-500 transition-colors" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            </div>
            
            <div class="flex items-center gap-4 mb-3">
                <div class="w-1/3 text-sm font-medium text-slate-700 dark:text-slate-300">UMK Kota/Kab.</div>
                <div class="w-2/3 relative">
                    <span class="absolute left-3 top-2 text-slate-400 text-sm">Rp</span>
                    <input type="text" inputmode="numeric" value="2.484.162" class="format-rp bpjs-umk w-full pl-9 pr-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 outline-none focus:ring-2 focus:ring-brand-500 text-right text-slate-800 dark:text-white font-mono">
                </div>
            </div>

            <div class="space-y-2 pl-4 border-l-2 border-slate-200 dark:border-slate-700 ml-2">
                <!-- JHT -->
                <div class="flex items-center gap-2 relative">
                    <input type="hidden" name="penghasilan_ket[]" value="Tunj. JHT (BPJS TK)">
                    <div class="w-1/3 text-xs text-slate-600 dark:text-slate-400">Jaminan Hari Tua</div>
                    <div class="w-2/3 flex items-center gap-2">
                        <div class="relative w-1/3">
                            <input type="number" value="3.70" step="0.01" class="bpjs-pct w-full pr-5 pl-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-center text-xs bg-white dark:bg-slate-800 outline-none focus:border-brand-500 text-slate-800 dark:text-white">
                            <span class="absolute right-1.5 top-1.5 text-slate-400 text-xs">%</span>
                        </div>
                        <div class="relative w-2/3">
                            <span class="absolute left-2 top-1.5 text-slate-400 text-xs">Rp</span>
                            <input type="text" readonly class="format-rp bpjs-subtotal w-full pl-7 pr-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-right text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 outline-none cursor-not-allowed font-mono">
                            <input type="hidden" name="penghasilan_nom[]" class="bpjs-hidden-nom" value="0">
                        </div>
                    </div>
                </div>
                <!-- JKK -->
                <div class="flex items-center gap-2 relative">
                    <input type="hidden" name="penghasilan_ket[]" value="Tunj. JKK (BPJS TK)">
                    <div class="w-1/3 text-xs text-slate-600 dark:text-slate-400">Jams. Kecelakaan Kerja</div>
                    <div class="w-2/3 flex items-center gap-2">
                        <div class="relative w-1/3">
                            <input type="number" value="0.24" step="0.01" class="bpjs-pct w-full pr-5 pl-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-center text-xs bg-white dark:bg-slate-800 outline-none focus:border-brand-500 text-slate-800 dark:text-white">
                            <span class="absolute right-1.5 top-1.5 text-slate-400 text-xs">%</span>
                        </div>
                        <div class="relative w-2/3">
                            <span class="absolute left-2 top-1.5 text-slate-400 text-xs">Rp</span>
                            <input type="text" readonly class="format-rp bpjs-subtotal w-full pl-7 pr-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-right text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 outline-none cursor-not-allowed font-mono">
                            <input type="hidden" name="penghasilan_nom[]" class="bpjs-hidden-nom" value="0">
                        </div>
                    </div>
                </div>
                <!-- JKM -->
                <div class="flex items-center gap-2 relative">
                    <input type="hidden" name="penghasilan_ket[]" value="Tunj. JKM (BPJS TK)">
                    <div class="w-1/3 text-xs text-slate-600 dark:text-slate-400">Jaminan Kematian</div>
                    <div class="w-2/3 flex items-center gap-2">
                        <div class="relative w-1/3">
                            <input type="number" value="0.30" step="0.01" class="bpjs-pct w-full pr-5 pl-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-center text-xs bg-white dark:bg-slate-800 outline-none focus:border-brand-500 text-slate-800 dark:text-white">
                            <span class="absolute right-1.5 top-1.5 text-slate-400 text-xs">%</span>
                        </div>
                        <div class="relative w-2/3">
                            <span class="absolute left-2 top-1.5 text-slate-400 text-xs">Rp</span>
                            <input type="text" readonly class="format-rp bpjs-subtotal w-full pl-7 pr-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-right text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 outline-none cursor-not-allowed font-mono">
                            <input type="hidden" name="penghasilan_nom[]" class="bpjs-hidden-nom" value="0">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                <div class="w-1/3 text-sm font-semibold text-slate-700 dark:text-slate-300 text-right pr-2">Subtotal BPJS</div>
                <div class="w-2/3 relative">
                    <span class="absolute left-3 top-2 text-slate-600 dark:text-slate-400 text-sm font-bold">Rp</span>
                    <input type="text" readonly class="format-rp bpjs-grandtotal w-full pl-9 pr-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-white font-bold font-mono rounded-lg text-sm text-right outline-none cursor-not-allowed">
                </div>
            </div>
        `;
        container.appendChild(div);
        calculateBpjsBlock(div);
        calculateAll();
    }

    // --- FUNGSI TAMBAH BLOK BPJS POTONGAN ---
    function addBpjsPotongan() {
        const container = document.getElementById('bpjsDecContainer');
        document.getElementById('btnAddBpjsDec').style.display = 'none';

        const div = document.createElement('div');
        div.className = 'bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-4 bpjs-block shadow-sm';
        div.innerHTML = `
            <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-3 mb-3">
                <h4 class="text-sm font-bold text-slate-800 dark:text-white">Potongan BPJS TK</h4>
                <button type="button" onclick="this.closest('.bpjs-block').remove(); document.getElementById('btnAddBpjsDec').style.display='flex'; calculateAll();" class="text-slate-400 hover:text-red-500 transition-colors" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            </div>
            
            <div class="flex items-center gap-4 mb-3">
                <div class="w-1/3 text-sm font-medium text-slate-700 dark:text-slate-300">UMK Kota/Kab.</div>
                <div class="w-2/3 relative">
                    <span class="absolute left-3 top-2 text-slate-400 text-sm">Rp</span>
                    <input type="text" inputmode="numeric" value="2.484.162" class="format-rp bpjs-umk w-full pl-9 pr-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 outline-none focus:ring-2 focus:ring-brand-500 text-right text-slate-800 dark:text-white font-mono">
                </div>
            </div>

            <div class="space-y-2 pl-4 border-l-2 border-slate-200 dark:border-slate-700 ml-2">
                <!-- Peserta -->
                <div class="flex items-center gap-2 relative">
                    <input type="hidden" name="potongan_ket[]" value="Iuran JHT Peserta (BPJS TK)">
                    <div class="w-1/3 text-xs text-slate-600 dark:text-slate-400">Peserta</div>
                    <div class="w-2/3 flex items-center gap-2">
                        <div class="relative w-1/3">
                            <input type="number" value="2.00" step="0.01" class="bpjs-pct w-full pr-5 pl-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-center text-xs bg-white dark:bg-slate-800 outline-none focus:border-brand-500 text-slate-800 dark:text-white">
                            <span class="absolute right-1.5 top-1.5 text-slate-400 text-xs">%</span>
                        </div>
                        <div class="relative w-2/3">
                            <span class="absolute left-2 top-1.5 text-slate-400 text-xs">Rp</span>
                            <input type="text" readonly class="format-rp bpjs-subtotal w-full pl-7 pr-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-right text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 outline-none cursor-not-allowed font-mono">
                            <input type="hidden" name="potongan_nom[]" class="bpjs-hidden-nom" value="0">
                        </div>
                    </div>
                </div>
                <!-- Perusahaan (jika memotong gaji, biasanya tidak, tapi mengikuti demo) -->
                <div class="flex items-center gap-2 relative">
                    <input type="hidden" name="potongan_ket[]" value="Iuran Perusahaan (BPJS TK)">
                    <div class="w-1/3 text-xs text-slate-600 dark:text-slate-400">Perusahaan</div>
                    <div class="w-2/3 flex items-center gap-2">
                        <div class="relative w-1/3">
                            <input type="number" value="4.24" step="0.01" class="bpjs-pct w-full pr-5 pl-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-center text-xs bg-white dark:bg-slate-800 outline-none focus:border-brand-500 text-slate-800 dark:text-white">
                            <span class="absolute right-1.5 top-1.5 text-slate-400 text-xs">%</span>
                        </div>
                        <div class="relative w-2/3">
                            <span class="absolute left-2 top-1.5 text-slate-400 text-xs">Rp</span>
                            <input type="text" readonly class="format-rp bpjs-subtotal w-full pl-7 pr-2 py-1.5 border border-slate-200 dark:border-slate-600 rounded-md text-right text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 outline-none cursor-not-allowed font-mono">
                            <input type="hidden" name="potongan_nom[]" class="bpjs-hidden-nom" value="0">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                <div class="w-1/3 text-sm font-semibold text-slate-700 dark:text-slate-300 text-right pr-2">Subtotal BPJS</div>
                <div class="w-2/3 relative">
                    <span class="absolute left-3 top-2 text-slate-600 dark:text-slate-400 text-sm font-bold">Rp</span>
                    <input type="text" readonly class="format-rp bpjs-grandtotal w-full pl-9 pr-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-white font-bold font-mono rounded-lg text-sm text-right outline-none cursor-not-allowed">
                </div>
            </div>
        `;
        container.appendChild(div);
        calculateBpjsBlock(div);
        calculateAll();
    }

    // Kalkulasi Matematika Blok BPJS
    function calculateBpjsBlock(block) {
        const umk = parseRibuan(block.querySelector('.bpjs-umk').value);
        let grandTotal = 0;
        
        const rows = block.querySelectorAll('.bpjs-pct');
        rows.forEach(pctInput => {
            const row = pctInput.closest('.flex.items-center');
            const pct = parseFloat(pctInput.value) || 0;
            
            // Hitung UMK * (Persen / 100), bulatkan
            const subtotal = Math.round(umk * (pct / 100));
            row.querySelector('.bpjs-subtotal').value = formatRibuan(subtotal);
            row.querySelector('.bpjs-hidden-nom').value = subtotal; // Save to hidden input to be sent
            
            grandTotal += subtotal;
        });
        
        block.querySelector('.bpjs-grandtotal').value = formatRibuan(grandTotal);
    }

    // Fungsi Tambah Input Dinamis (Lain-lain)
    function addInputRow(containerId, inputClass, ketName, nomName, rateName, qtyName) {
        const container = document.getElementById(containerId);
        const titleHolder = inputClass === 'inc-input' ? 'Nama Penghasilan...' : 'Nama Potongan...';
        
        const div = document.createElement('div');
        div.className = 'flex items-start gap-4 group mt-3 dynamic-row';
        div.innerHTML = `
            <div class="w-1/3">
                <input type="text" name="${ketName}" class="w-full bg-transparent border-b border-dashed border-slate-300 dark:border-slate-600 text-sm font-semibold text-slate-700 dark:text-slate-300 outline-none focus:border-brand-500 placeholder-slate-400 px-1 py-1" placeholder="${titleHolder}" required>
            </div>
            <div class="w-2/3 flex items-center gap-2">
                <!-- Kolom 1: Rp (Rate) -->
                <div class="relative w-2/5">
                    <span class="absolute left-2 top-2 text-slate-400 text-xs">Rp</span>
                    <input type="text" name="${rateName}" inputmode="numeric" value="0" class="format-rp dyn-rate w-full pl-7 pr-1 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-right text-sm bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 font-mono transition-colors">
                </div>
                <div class="text-slate-400 font-bold text-xs">x</div>
                <!-- Kolom 2: Hari / Kali -->
                <div class="relative w-1/5">
                    <input type="number" name="${qtyName}" value="1" step="0.1" class="dyn-qty w-full px-1 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-center text-sm bg-white dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors" title="Jumlah Hari/Kali">
                </div>
                <div class="text-slate-400 font-bold text-xs">=</div>
                <!-- Kolom 3: Hasil (=) -->
                <div class="relative w-2/5 flex items-center gap-1">
                    <input type="text" value="0" readonly class="format-rp dyn-total ${inputClass} hidden-real-input w-full px-2 py-2 border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-white rounded-xl text-right text-sm font-mono font-bold cursor-not-allowed outline-none transition-colors">
                    <input type="hidden" name="${nomName}" value="0">
                    <button type="button" onclick="this.closest('.dynamic-row').remove(); calculateAll();" class="text-slate-300 hover:text-red-500 p-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
    }

    // Listener Utama untuk seluruh input
    document.addEventListener('input', function(e) {
        
        // Logika format titik otomatis (Ribuan) saat user mengetik
        if (e.target.classList.contains('format-rp') && !e.target.readOnly) {
            let cursorPosition = e.target.selectionStart;
            let originalLength = e.target.value.length;
            
            let isNegative = e.target.value.trim().startsWith('-');
            let cleanVal = e.target.value.replace(/[^,\d]/g, '').replace(/,/g, '');
            if (isNegative && cleanVal !== '') cleanVal = '-' + cleanVal;
            
            e.target.value = formatRibuan(cleanVal);
            
            let newLength = e.target.value.length;
            e.target.setSelectionRange(cursorPosition + (newLength - originalLength), cursorPosition + (newLength - originalLength));
            
            syncHiddenInput(e.target);
        }

        if (e.target.tagName.toLowerCase() === 'input') {
            // Kalkulasi baris dinamis 3 kolom
            if (e.target.classList.contains('dyn-rate') || e.target.classList.contains('dyn-qty')) {
                const row = e.target.closest('.dynamic-row');
                const rate = parseRibuan(row.querySelector('.dyn-rate').value);
                const qty = parseFloat(row.querySelector('.dyn-qty').value) || 0;
                
                const totalInput = row.querySelector('.dyn-total');
                totalInput.value = formatRibuan(rate * qty);
                syncHiddenInput(totalInput);
            }

            // Kalkulasi blok BPJS jika UMK / Persen berubah
            if (e.target.classList.contains('bpjs-umk') || e.target.classList.contains('bpjs-pct')) {
                const block = e.target.closest('.bpjs-block');
                if (block) calculateBpjsBlock(block);
            }
            
            // Hitung total keseluruhan slip gaji
            calculateAll();

            // Jika user mengetik di input Target Gaji Bersih, hitung penggenapan otomatis
            if (e.target.id === 'inTargetNet') {
                let target = parseRibuan(e.target.value);
                if (e.target.value !== '' && e.target.value !== '-') {
                    let totalPenghasilan = parseRibuan(document.getElementById('txtTotalInc').textContent.replace('Rp ', ''));
                    let totalPotongan = parseRibuan(document.getElementById('txtTotalDec').textContent.replace('Rp ', ''));
                    
                    let baseNet = totalPenghasilan - totalPotongan;
                    let requiredAdjustment = target - baseNet;
                    
                    const inAdjustmentDisplay = document.getElementById('digenapkanDisplay');
                    if (inAdjustmentDisplay) {
                        inAdjustmentDisplay.value = formatRibuan(requiredAdjustment);
                        syncHiddenInput(inAdjustmentDisplay);
                        // Hitung lagi untuk update UI Gaji Bersih
                        calculateAll();
                    }
                }
            } else if (e.target.id === 'digenapkanDisplay') {
                // Clear target net input if user manually edits penggenapan
                const inTarget = document.getElementById('inTargetNet');
                if (inTarget) {
                    inTarget.value = '';
                }
            }
        }
    });

    // Period change reload
    function reloadPeriod() {
        const bulan = document.getElementById('selectBulan').value;
        const tahun = document.getElementById('selectTahun').value;
        window.location.href = '?id_karyawan=<?php echo $id_karyawan; ?>&bulan=' + bulan + '&tahun=' + tahun;
    }
    document.getElementById('selectBulan').addEventListener('change', reloadPeriod);
    document.getElementById('selectTahun').addEventListener('change', reloadPeriod);

    // Export PDF Function
    function exportPDF() {
        const bulan = document.getElementById("selectBulan").value;
        const tahun = document.getElementById("selectTahun").value;
        const id_karyawan = "<?php echo $id_karyawan; ?>";
        const url = `export_slip_gaji.php?id_karyawan=${id_karyawan}&bulan=${bulan}&tahun=${tahun}`;
        window.open(url, "_blank");
    }

    // Sesuaikan Tunjangan Jabatan ke Master Data
    function sesuaikanTunjanganJabatan(nominalMaster) {
        const display = document.getElementById('tunjanganJabatanDisplay');
        const hidden = document.getElementById('tunjanganJabatanValue');
        if (display && hidden) {
            display.value = formatRibuan(nominalMaster);
            hidden.value = nominalMaster;
            calculateAll();
            
            // Visual feedback
            display.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
            display.classList.add('bg-sky-100', 'dark:bg-sky-900/50', 'text-sky-700', 'dark:text-sky-300', 'ring-2', 'ring-sky-400');
            setTimeout(() => {
                display.classList.remove('bg-sky-100', 'dark:bg-sky-900/50', 'text-sky-700', 'dark:text-sky-300', 'ring-2', 'ring-sky-400');
                display.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
            }, 800);
        }
    }

    // Simpan Rate per Karyawan (AJAX)
    function saveRate(field, hiddenInputId, btnElement) {
        // Sync input text format to hidden before saving
        const textInput = document.getElementById(hiddenInputId.replace('Value', 'Input'));
        if(textInput) syncHiddenInput(textInput);
        
        const value = document.getElementById(hiddenInputId).value;
        const id_karyawan = "<?php echo $id_karyawan; ?>";
        
        const icon = btnElement.querySelector('i');
        const origClass = icon.className;
        icon.className = 'fa-solid fa-circle-notch fa-spin';
        
        const formData = new FormData();
        formData.append('id_karyawan', id_karyawan);
        formData.append('field', field);
        formData.append('value', value);
        
        fetch('ajax_save_rate.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                icon.className = 'fa-solid fa-check';
                btnElement.classList.replace('text-emerald-600', 'text-white');
                btnElement.classList.replace('dark:text-emerald-400', 'dark:text-white');
                btnElement.classList.replace('bg-emerald-50', 'bg-emerald-500');
                btnElement.classList.replace('dark:bg-emerald-900/30', 'dark:bg-emerald-600');
                
                setTimeout(() => {
                    icon.className = origClass;
                    btnElement.classList.replace('text-white', 'text-emerald-600');
                    btnElement.classList.replace('dark:text-white', 'dark:text-emerald-400');
                    btnElement.classList.replace('bg-emerald-500', 'bg-emerald-50');
                    btnElement.classList.replace('dark:bg-emerald-600', 'dark:bg-emerald-900/30');
                }, 2000);
            } else {
                alert(data.message);
                icon.className = origClass;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal menghubungi server.');
            icon.className = origClass;
        });
    }

    // Inisiasi kalkulasi saat web baru dibuka
    window.onload = calculateAll;

    // AJAX form submission with SweetAlert2
    const formSlip = document.getElementById('formSlipGaji');
    if(formSlip) {
        formSlip.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (typeof Swal !== 'undefined') {
                const isEdit = document.querySelector('input[name="is_edit"]').value == '1';
                const actionText = isEdit ? 'Update' : 'Simpan';
                
                Swal.fire({
                    title: 'Konfirmasi',
                    text: `Anda yakin ingin ${actionText} data slip gaji ini?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981', // emerald-500
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Ya, Lanjutkan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Memproses...',
                            text: 'Menyimpan data slip gaji',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        const formData = new FormData(this);
                        formData.append('is_ajax', '1');
                        
                        fetch(this.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data.message
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Terjadi Kesalahan!',
                                text: 'Gagal terhubung ke server.'
                            });
                        });
                    }
                });
            } else {
                if (confirm('Yakin ingin menyimpan data slip gaji ini?')) {
                    this.submit();
                }
            }
        });
    }
</script>

<!-- SweetAlert2 -->
<?php require 'admin_footer.php'; ?>

