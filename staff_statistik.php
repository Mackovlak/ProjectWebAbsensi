<?php
require 'staff_header.php';

// Validasi session
if (!isset($_SESSION['id_karyawan'])) {
    $_SESSION['error_message'] = "Session tidak valid. Silakan login kembali.";
    header("Location: login.php");
    exit();
}

$id_karyawan_staff = $_SESSION['id_karyawan']; 

// Ambil id_cabang dan nama karyawan dari database
$stmt_cabang = $conn->prepare("SELECT k.id_cabang, k.nama_karyawan, c.nama_cabang 
                                FROM karyawan k 
                                JOIN cabang c ON k.id_cabang = c.id 
                                WHERE k.id_karyawan = ?");
$stmt_cabang->bind_param("s", $id_karyawan_staff);
$stmt_cabang->execute();
$result_cabang = $stmt_cabang->get_result();

if ($result_cabang->num_rows == 0) {
    $_SESSION['error_message'] = "Data karyawan tidak ditemukan.";
    header("Location: staff_dashboard.php");
    exit();
}

$data_karyawan = $result_cabang->fetch_assoc();
$id_cabang = $data_karyawan['id_cabang'];
$nama_karyawan = $data_karyawan['nama_karyawan'];
$nama_cabang = $data_karyawan['nama_cabang'];
$stmt_cabang->close();

// Filter tanggal
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) 
    ? sanitizeInput($_GET['start_date']) 
    : date('Y-m-01');
    
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) 
    ? sanitizeInput($_GET['end_date']) 
    : date('Y-m-t');

$sql_statistik = "SELECT
    k.nama_karyawan,
    k.id_karyawan,
    
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') THEN a.id END) as count_hadir_raw,
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as count_setengah_hari,
    
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' 
        AND a.status_masuk = 'Tepat Waktu'
        THEN a.id 
    END) as total_tepat_waktu,
    
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' 
        AND a.status_masuk = 'Terlambat'
        THEN a.id 
    END) as total_terlambat,
    
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as total_setengah_hari,
    
    SUM(CASE 
        WHEN a.jam_pulang IS NOT NULL 
        AND a.jam_pulang != '00:00:00'
        AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) >= 330
        AND a.jam_pulang > (
            SELECT jk.jam_pulang
            FROM jam_kerja jk
            WHERE jk.id_cabang = k.id_cabang
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC
            LIMIT 1
        )
        THEN 
            CASE WHEN (TIME_TO_SEC(a.jam_pulang) - TIME_TO_SEC((SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1))) < 2100 THEN 0.5 ELSE 1 END
        ELSE 0
    END) as total_overtime,
    
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' 
        AND DAYOFWEEK(a.tanggal) = 1 
        THEN a.id 
    END) as count_minggu_raw,
    
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir'
        AND DAYOFWEEK(a.tanggal) = 1
        AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as count_minggu_setengah_hari,

    COUNT(DISTINCT CASE WHEN a.keterangan = 'OFF' THEN a.id END) as total_off,
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Sakit' THEN a.id END) as total_sakit,
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Cuti' THEN a.id END) as total_cuti,
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Dinas Luar' THEN a.id END) as total_dinas_luar,
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Alpha' THEN a.id END) as total_alpha
FROM karyawan k
LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan AND a.tanggal BETWEEN ? AND ?
WHERE k.id_karyawan = ?
GROUP BY k.id_karyawan, k.nama_karyawan
ORDER BY k.nama_karyawan ASC";
$stmt = $conn->prepare($sql_statistik);
$stmt->bind_param("sss", $start_date, $end_date, $id_karyawan_staff);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($stats) {
    $count_hadir_raw = (int)($stats['count_hadir_raw'] ?? 0);
    $count_setengah_hari = (int)($stats['count_setengah_hari'] ?? 0);
    $count_full_day = $count_hadir_raw - $count_setengah_hari;
    $total_hadir_adjusted = $count_full_day + ($count_setengah_hari * 0.5);
    
    $count_minggu_raw = (int)($stats['count_minggu_raw'] ?? 0);
    $count_minggu_setengah_hari = (int)($stats['count_minggu_setengah_hari'] ?? 0);
    $count_minggu_full_day = $count_minggu_raw - $count_minggu_setengah_hari;
    $total_minggu_adjusted = $count_minggu_full_day + ($count_minggu_setengah_hari * 0.5);
    
    $stats['total_hadir'] = $total_hadir_adjusted;
    $stats['count_hadir_raw'] = $count_hadir_raw;
    $stats['count_setengah_hari'] = $count_setengah_hari;
    $stats['total_minggu'] = $total_minggu_adjusted;
} else {
    $stats = [
        'total_hadir' => 0, 'total_tepat_waktu' => 0, 'total_terlambat' => 0,
        'total_setengah_hari' => 0, 'total_overtime' => 0, 'total_minggu' => 0,
        'total_off' => 0, 'total_sakit' => 0, 'total_cuti' => 0, 'total_dinas_luar' => 0, 'total_alpha' => 0,
        'count_hadir_raw' => 0, 'count_setengah_hari' => 0
    ];
}

$csrf_token = generateCSRFToken();
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div class="hidden sm:block">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Statistik Pribadi</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Ringkasan kehadiran Anda.</p>
    </div>
    
    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <a href="staff_dashboard.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-xl transition-colors font-medium text-sm">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <a href="staff_statistik_print.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" target="_blank" class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30">
            <i class="fa-solid fa-file-pdf"></i> PDF Statistik
        </a>
    </div>
</div>

<!-- Info Header Profile -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 shadow-sm mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative overflow-hidden">
    <!-- Decorative background -->
    <div class="absolute -right-10 -top-10 w-32 h-32 bg-brand-500/10 rounded-full blur-2xl"></div>
    <div class="absolute -left-10 -bottom-10 w-32 h-32 bg-purple-500/10 rounded-full blur-2xl"></div>
    
    <div class="flex items-center gap-4 relative z-10">
        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-brand-500 to-purple-600 text-white flex items-center justify-center shadow-lg shadow-brand-500/30 shrink-0">
            <i class="fa-solid fa-user-tie text-2xl"></i>
        </div>
        <div>
            <h3 class="text-lg sm:text-xl font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($nama_karyawan); ?></h3>
            <p class="text-sm font-medium text-brand-600 dark:text-brand-400 mt-0.5 flex items-center gap-1.5">
                <i class="fa-solid fa-building text-xs"></i> <?php echo htmlspecialchars($nama_cabang); ?>
            </p>
        </div>
    </div>
    
    <div class="relative z-10 sm:text-right bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700/50 sm:bg-transparent sm:border-0 sm:p-0">
        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Periode Laporan</p>
        <p class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2 sm:justify-end">
            <i class="fa-solid fa-calendar-days text-brand-500"></i>
            <?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
        </p>
    </div>
</div>

<!-- Filter Section -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 sm:p-5 mb-8">
    <form method="GET" action="staff_statistik.php" class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="w-full sm:w-auto flex-1">
            <label class="block text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dari Tanggal</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fa-solid fa-calendar text-slate-400"></i>
                </div>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
            </div>
        </div>
        
        <div class="w-full sm:w-auto flex-1">
            <label class="block text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Sampai Tanggal</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fa-solid fa-calendar-check text-slate-400"></i>
                </div>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
            </div>
        </div>

        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium shadow-sm shadow-brand-500/30 transition-all active:scale-95 flex items-center justify-center gap-2">
            <i class="fa-solid fa-filter"></i> Terapkan
        </button>
    </form>
</div>

<!-- Summary Cards Grid -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-8">
    
    <!-- Total Hadir -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-brand-100 dark:border-brand-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-brand-50 dark:bg-brand-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-600 dark:bg-brand-900/40 dark:text-brand-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-user-check text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Total Hadir</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white">
                <?php 
                    if (isset($stats['count_setengah_hari']) && $stats['count_setengah_hari'] > 0) {
                        echo number_format($stats['total_hadir'], 1);
                    } else {
                        echo $stats['count_hadir_raw'] ?? 0;
                    }
                ?>
            </h3>
        </div>
    </div>
    
    <!-- Tepat Waktu -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-emerald-100 dark:border-emerald-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-emerald-50 dark:bg-emerald-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-clock text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Tepat Waktu</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white"><?php echo $stats['total_tepat_waktu'] ?? 0; ?></h3>
        </div>
    </div>
    
    <!-- Terlambat -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-rose-100 dark:border-rose-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-rose-50 dark:bg-rose-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-exclamation-triangle text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Terlambat</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white"><?php echo $stats['total_terlambat'] ?? 0; ?></h3>
        </div>
    </div>
    
    <!-- Setengah Hari -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-amber-100 dark:border-amber-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-amber-50 dark:bg-amber-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-star-half-stroke text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Stngh. Hari</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white"><?php echo $stats['total_setengah_hari'] ?? 0; ?></h3>
        </div>
    </div>
    
    <!-- Overtime -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-purple-100 dark:border-purple-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-purple-50 dark:bg-purple-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-business-time text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Over Time</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white"><?php echo $stats['total_overtime'] ?? 0; ?></h3>
        </div>
    </div>

    <!-- Minggu -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-orange-100 dark:border-orange-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-orange-50 dark:bg-orange-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 dark:bg-orange-900/40 dark:text-orange-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-calendar-day text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Minggu</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white"><?php echo $stats['total_minggu'] ?? 0; ?></h3>
        </div>
    </div>
    
    <!-- Cuti -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-sky-100 dark:border-sky-900/30 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-sky-50 dark:bg-sky-900/20 rounded-full transition-transform group-hover:scale-150 duration-500"></div>
        <div class="relative z-10">
            <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-400 flex items-center justify-center mb-3 shadow-sm">
                <i class="fa-solid fa-plane-departure text-lg"></i>
            </div>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-semibold uppercase tracking-wide mb-1">Cuti</p>
            <h3 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white"><?php echo $stats['total_cuti'] ?? 0; ?></h3>
        </div>
    </div>

    <!-- Alpha / Sakit / OFF -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
        <div class="relative z-10 h-full flex flex-col justify-center gap-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><i class="fa-solid fa-bed-pulse text-pink-500 w-4"></i> Sakit</span>
                <span class="font-bold text-slate-800 dark:text-white"><?php echo $stats['total_sakit'] ?? 0; ?></span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400"><i class="fa-solid fa-calendar-xmark text-slate-500 w-4"></i> OFF</span>
                <span class="font-bold text-slate-800 dark:text-white"><?php echo $stats['total_off'] ?? 0; ?></span>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-700 pt-1 mt-1">
                <span class="text-xs font-semibold text-purple-500 dark:text-purple-400"><i class="fa-solid fa-briefcase text-purple-500 w-4"></i> Dinas Luar</span>
                <span class="font-bold text-purple-600 dark:text-purple-400"><?php echo $stats['total_dinas_luar'] ?? 0; ?></span>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-700 pt-1 mt-1">
                <span class="text-xs font-semibold text-rose-500 dark:text-rose-400"><i class="fa-solid fa-triangle-exclamation text-rose-500 w-4"></i> Alpha</span>
                <span class="font-bold text-rose-600 dark:text-rose-400"><?php echo $stats['total_alpha'] ?? 0; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 sm:p-8 mb-8 relative overflow-hidden">
    <!-- Decorative elements -->
    <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-brand-500/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-fuchsia-500/5 rounded-full blur-3xl pointer-events-none"></div>

    <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-8 flex items-center gap-3 relative z-10">
        <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-900/40 text-brand-600 dark:text-brand-400 flex items-center justify-center border border-brand-100 dark:border-brand-800/50 shadow-sm">
            <i class="fa-solid fa-chart-column text-lg"></i>
        </div>
        Statistik Kehadiran
    </h3>
    
    <div class="w-full relative z-10">
        <!-- Chart Container -->
        <div id="attendanceApexChart" class="w-full min-h-[350px]"></div>
    </div>
</div>

<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDarkMode = document.documentElement.classList.contains('dark');
    
    const chartData = [
        <?php echo $stats['count_hadir_raw'] ?? 0; ?>,
        <?php echo $stats['total_setengah_hari'] ?? 0; ?>,
        <?php echo $stats['total_overtime'] ?? 0; ?>,
        <?php echo $stats['total_minggu'] ?? 0; ?>,
        <?php echo $stats['total_off'] ?? 0; ?>,
        <?php echo $stats['total_sakit'] ?? 0; ?>,
        <?php echo $stats['total_cuti'] ?? 0; ?>,
        <?php echo $stats['total_dinas_luar'] ?? 0; ?>,
        <?php echo $stats['total_alpha'] ?? 0; ?>
    ];
    
    const labels = ['Hadir', 'Stngh. Hari', 'Over Time', 'Minggu', 'OFF', 'Sakit', 'Cuti', 'Dinas Luar', 'Alpha'];
    const colors = ['#d946ef', '#f59e0b', '#a855f7', '#eab308', '#64748b', '#ec4899', '#06b6d4', '#6366f1', '#ef4444'];
    
    const totalData = chartData.reduce((a, b) => a + b, 0);

    const options = {
        series: [{
            name: 'Total',
            data: totalData > 0 ? chartData : [0,0,0,0,0,0,0,0,0]
        }],
        chart: {
            type: 'bar',
            height: 380,
            fontFamily: 'Inter, sans-serif',
            toolbar: {
                show: false
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '55%',
                distributed: true,
            }
        },
        colors: colors,
        dataLabels: {
            enabled: true,
            style: {
                fontSize: '12px',
                fontFamily: 'Inter, sans-serif',
                fontWeight: 'bold',
                colors: ['#fff']
            },
            formatter: function (val) {
                return val > 0 ? val : '';
            }
        },
        legend: {
            show: false
        },
        xaxis: {
            categories: labels,
            labels: {
                style: {
                    colors: Array(9).fill(isDarkMode ? '#94a3b8' : '#64748b'),
                    fontSize: '12px',
                    fontFamily: 'Inter, sans-serif',
                    fontWeight: 500
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: isDarkMode ? '#94a3b8' : '#64748b',
                },
                formatter: function (val) {
                    return Math.floor(val);
                }
            }
        },
        grid: {
            borderColor: isDarkMode ? '#334155' : '#e2e8f0',
            strokeDashArray: 4,
            yaxis: {
                lines: {
                    show: true
                }
            },
            xaxis: {
                lines: {
                    show: false
                }
            }
        },
        tooltip: {
            theme: isDarkMode ? 'dark' : 'light',
            y: {
                formatter: function(val) {
                    return val + " Hari"
                }
            }
        }
    };

    const chart = new ApexCharts(document.querySelector("#attendanceApexChart"), options);
    chart.render();

    // Dark mode observer
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === "class") {
                const isDark = document.documentElement.classList.contains('dark');
                
                chart.updateOptions({
                    xaxis: {
                        labels: {
                            style: {
                                colors: Array(9).fill(isDark ? '#94a3b8' : '#64748b')
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: isDark ? '#94a3b8' : '#64748b'
                            }
                        }
                    },
                    grid: {
                        borderColor: isDark ? '#334155' : '#e2e8f0'
                    },
                    tooltip: {
                        theme: isDark ? 'dark' : 'light'
                    }
                });
            }
        });
    });

    observer.observe(document.documentElement, { attributes: true });
});
</script>

<?php require 'staff_footer.php'; ?>
