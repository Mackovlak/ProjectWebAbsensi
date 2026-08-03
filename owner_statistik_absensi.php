<?php
require 'owner_header.php';

// Validasi cabang
if (!isset($_GET['cabang']) || !is_numeric($_GET['cabang'])) {
    header("Location: owner_rekap_absensi.php");
    exit();
}

$id_cabang = intval($_GET['cabang']);

// Ambil nama cabang SEBELUM digunakan
$stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
$stmt->bind_param("i", $id_cabang);
$stmt->execute();
$result = $stmt->get_result();
$cabang_info = $result->fetch_assoc();

// Pastikan nama_cabang ada
if (!$cabang_info) {
    $_SESSION['error_message'] = "Cabang tidak ditemukan.";
    header("Location: owner_rekap_absensi.php");
    exit();
}

$nama_cabang = $cabang_info['nama_cabang'];
$stmt->close();

// Filter tanggal
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) 
    ? sanitizeInput($_GET['start_date']) 
    : date('Y-m-01');
    
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) 
    ? sanitizeInput($_GET['end_date']) 
    : date('Y-m-t');

// Query statistik per karyawan
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
    
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Tepat Waktu' THEN a.id END) as total_tepat_waktu,
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Terlambat' THEN a.id END) as total_terlambat,
    
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
    COUNT(DISTINCT CASE WHEN a.keterangan = 'Alpha' THEN a.id END) as total_alpha
FROM karyawan k
LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan AND a.tanggal BETWEEN ? AND ?
WHERE k.id_cabang = ?
GROUP BY k.id_karyawan, k.nama_karyawan
ORDER BY k.nama_karyawan ASC";

$stmt = $conn->prepare($sql_statistik);
$stmt->bind_param("ssi", $start_date, $end_date, $id_cabang);
$stmt->execute();
$result_stats = $stmt->get_result();

$stats_data = [];
while ($row = $result_stats->fetch_assoc()) {
    $count_hadir_raw = (int)($row['count_hadir_raw'] ?? 0);
    $count_setengah_hari = (int)($row['count_setengah_hari'] ?? 0);
    $count_full_day = $count_hadir_raw - $count_setengah_hari;
    $total_hadir_adjusted = $count_full_day + ($count_setengah_hari * 0.5);
    
    $count_minggu_raw = (int)($row['count_minggu_raw'] ?? 0);
    $count_minggu_setengah_hari = (int)($row['count_minggu_setengah_hari'] ?? 0);
    $count_minggu_full_day = $count_minggu_raw - $count_minggu_setengah_hari;
    $total_minggu_adjusted = $count_minggu_full_day + ($count_minggu_setengah_hari * 0.5);
    
    $row['total_hadir'] = $total_hadir_adjusted;
    $row['count_hadir_raw'] = $count_hadir_raw;
    $row['count_setengah_hari'] = $count_setengah_hari;
    $row['total_minggu'] = $total_minggu_adjusted;
    
    $stats_data[] = $row;
}
$stmt->close();

$total_hadir_adjusted = 0;
$total_tepat_waktu = 0;
$total_terlambat = 0;
$total_setengah_hari = 0;
$total_overtime = 0;
$total_minggu = 0;
$total_off = 0;
$total_sakit = 0;
$total_cuti = 0;
$total_alpha = 0;

foreach($stats_data as $row) {
    $total_hadir_adjusted += $row["total_hadir"];
    $total_tepat_waktu += $row["total_tepat_waktu"];
    $total_terlambat += $row["total_terlambat"];
    $total_setengah_hari += $row["total_setengah_hari"];
    $total_overtime += $row["total_overtime"];
    $total_minggu += $row["total_minggu"];
    $total_off += $row["total_off"];
    $total_sakit += $row["total_sakit"];
    $total_cuti += $row["total_cuti"];
    $total_alpha += $row["total_alpha"];
}

$csrf_token = generateCSRFToken();
?>

<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
/* Custom DataTables Styling */
.dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.25rem 0.5rem;
    outline: none;
    background-color: #ffffff;
    color: #334155;
}
.dark .dataTables_wrapper .dataTables_length select, .dark .dataTables_wrapper .dataTables_filter input {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.25rem 0.75rem;
    margin-left: 0.25rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #e0f2fe;
    color: #0284c7 !important;
    border-color: #bae6fd;
}
.dark .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: rgba(14, 165, 233, 0.2) !important;
    color: #38bdf8 !important;
    border-color: rgba(14, 165, 233, 0.3) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155 !important;
}
.dark .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #334155 !important;
    border-color: #475569 !important;
    color: #f8fafc !important;
}
.dataTables_info, .dataTables_length, .dataTables_filter {
    color: #64748b !important;
    font-size: 0.875rem;
    margin-bottom: 1rem;
    margin-top: 1rem;
}
.dark .dataTables_info, .dark .dataTables_length, .dark .dataTables_filter {
    color: #94a3b8 !important;
}
table.dataTable.no-footer {
    border-bottom: none;
}
/* Override DataTables default sorting background */
table.dataTable tbody tr > .sorting_1,
table.dataTable.display tbody tr.odd > .sorting_1,
table.dataTable.order-column.stripe tbody tr.odd > .sorting_1,
table.dataTable.display tbody tr.even > .sorting_1,
table.dataTable.order-column.stripe tbody tr.even > .sorting_1 {
    background-color: transparent !important;
}
/* Ensure dark mode table text visibility */
.dark table.dataTable tbody tr {
    background-color: transparent !important;
}
</style>

<!-- Top Action Bar -->
<div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
            <a href="owner_rekap_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="text-slate-400 hover:text-brand-500 transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Statistik: <?php echo htmlspecialchars($nama_cabang); ?>
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 xl:ml-9">Ringkasan akumulasi kehadiran karyawan cabang <?php echo htmlspecialchars($nama_cabang); ?>.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
        <button onclick="exportToPDF()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 hover:bg-rose-100 dark:hover:bg-rose-900/50 rounded-xl transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
            <i class="fa-solid fa-file-pdf"></i> Export PDF
        </button>
        
        <button onclick="exportToExcel()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 rounded-xl transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
            <i class="fa-solid fa-file-excel"></i> Export Excel
        </button>
    </div>
</div>

<!-- Filter Section -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 mb-8 relative overflow-hidden">
    <form method="GET" action="owner_statistik_absensi.php" class="relative z-10 flex flex-col lg:flex-row gap-4 items-end">
        <input type="hidden" name="cabang" value="<?php echo $id_cabang; ?>">
        
        <div class="w-full lg:w-48">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
        </div>
        
        <div class="w-full lg:w-48">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
        </div>

        <button type="submit" class="w-full lg:w-auto px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium shadow-md shadow-brand-500/30 transition-all flex items-center justify-center gap-2 hover:shadow-brand-500/40 hover:-translate-y-0.5">
            <i class="fa-solid fa-filter"></i> Terapkan
        </button>
    </form>
</div>

<!-- Summary Cards (Modern Glassmorphism) -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
    <!-- Total Hadir -->
    <div class="group relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-brand-500/10 dark:bg-brand-500/20 rounded-full blur-xl group-hover:bg-brand-500/20 transition-all"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-user-check text-lg"></i>
            </div>
            <span class="text-xs font-bold px-2 py-1 bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-400 rounded-md">HADIR</span>
        </div>
        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white relative z-10"><?php echo number_format($total_hadir_adjusted, 1); ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 relative z-10 font-medium">Total Akumulasi</p>
    </div>
    
    <!-- Tepat Waktu -->
    <div class="group relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-full blur-xl group-hover:bg-emerald-500/20 transition-all"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-inner">
                <i class="fa-regular fa-clock text-lg"></i>
            </div>
        </div>
        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white relative z-10"><?php echo $total_tepat_waktu; ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 relative z-10 font-medium">Tepat Waktu</p>
    </div>
    
    <!-- Terlambat -->
    <div class="group relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-rose-500/10 dark:bg-rose-500/20 rounded-full blur-xl group-hover:bg-rose-500/20 transition-all"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-person-running text-lg"></i>
            </div>
        </div>
        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white relative z-10"><?php echo $total_terlambat; ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 relative z-10 font-medium">Terlambat</p>
    </div>
    
    <!-- Overtime -->
    <div class="group relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 dark:bg-purple-500/20 rounded-full blur-xl group-hover:bg-purple-500/20 transition-all"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-business-time text-lg"></i>
            </div>
        </div>
        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white relative z-10"><?php echo $total_overtime; ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 relative z-10 font-medium">Overtime</p>
    </div>
    
    <!-- Minggu -->
    <div class="group relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-500/10 dark:bg-amber-500/20 rounded-full blur-xl group-hover:bg-amber-500/20 transition-all"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-inner">
                <i class="fa-regular fa-calendar-check text-lg"></i>
            </div>
        </div>
        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white relative z-10"><?php echo $total_minggu; ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 relative z-10 font-medium">Hadir Ahad/Minggu</p>
    </div>
    
    <!-- OFF -->
    <div class="group relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 overflow-hidden">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-slate-500/10 dark:bg-slate-500/20 rounded-full blur-xl group-hover:bg-slate-500/20 transition-all"></div>
        <div class="flex justify-between items-start mb-4 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 flex items-center justify-center shadow-inner">
                <i class="fa-solid fa-power-off text-lg"></i>
            </div>
        </div>
        <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white relative z-10"><?php echo $total_off; ?></h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 relative z-10 font-medium">Libur (OFF)</p>
    </div>
</div>

<!-- Table Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col mb-8 relative p-2 sm:p-4">
    <div class="overflow-x-auto w-full">
        <table id="statistikTable" class="w-full text-left border-collapse whitespace-nowrap opacity-0 transition-opacity duration-500" style="width:100%">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-4 font-bold text-center w-12 border-b">No</th>
                    <th class="px-4 py-4 font-bold border-b">Nama Karyawan</th>
                    <th class="px-4 py-4 font-bold text-center bg-brand-50/50 dark:bg-brand-900/10 text-brand-600 dark:text-brand-400 border-b">Total</th>
                    <th class="px-4 py-4 font-bold text-center border-b">Tepat Waktu</th>
                    <th class="px-4 py-4 font-bold text-center border-b">Terlambat</th>
                    <th class="px-4 py-4 font-bold text-center border-b">1/2 Hari</th>
                    <th class="px-4 py-4 font-bold text-center border-b">Overtime</th>
                    <th class="px-4 py-4 font-bold text-center bg-amber-50/50 dark:bg-amber-900/10 text-amber-600 border-b">Minggu</th>
                    <th class="px-4 py-4 font-bold text-center border-b">OFF</th>
                    <th class="px-4 py-4 font-bold text-center border-b">Sakit</th>
                    <th class="px-4 py-4 font-bold text-center border-b">Cuti</th>
                    <th class="px-4 py-4 font-bold text-center border-b">Alpha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-sm">
                <?php if (!empty($stats_data)): ?>
                    <?php 
                    $no = 1;
                    $dataForExport = [];

                    foreach($stats_data as $row): 
                        $dataForExport[] = [
                            'no' => $no,
                            'nama' => htmlspecialchars($row['nama_karyawan']),
                            'hadir' => $row['total_hadir'] ?: '0',
                            'tepat_waktu' => $row['total_tepat_waktu'] ?: '0',
                            'terlambat' => $row['total_terlambat'] ?: '0',
                            'setengah_hari' => $row['total_setengah_hari'] ?: '0',
                            'overtime' => $row['total_overtime'] ?? '0',
                            'off' => $row['total_off'] ?: '0',
                            'minggu' => $row['total_minggu'] ?? '0',
                            'sakit' => $row['total_sakit'] ?: '0',
                            'cuti' => $row['total_cuti'] ?: '0',
                            'alpha' => $row['total_alpha'] ?: '0'
                        ];
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors text-sm">
                        <td class="px-4 py-3 text-center text-slate-500 dark:text-slate-400"><?php echo $no++; ?></td>
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-white uppercase"><?php echo htmlspecialchars($row['nama_karyawan']); ?></td>
                        <td class="px-3 py-3 text-center font-bold text-brand-600 dark:text-brand-400"><?php echo $row['total_hadir'] ?: '-'; ?></td>
                        <td class="px-3 py-3 text-center font-semibold text-emerald-600 dark:text-emerald-400"><?php echo $row['total_tepat_waktu'] ?: '-'; ?></td>
                        <td class="px-3 py-3 text-center font-semibold text-rose-600 dark:text-rose-400"><?php echo $row['total_terlambat'] ?: '-'; ?></td>
                        <td class="px-3 py-3 text-center font-semibold text-amber-600 dark:text-amber-400"><?php echo $row['total_setengah_hari'] ?: '-'; ?></td>
                        <td class="px-3 py-3 text-center font-semibold text-purple-600 dark:text-purple-400">
                            <div class="flex items-center justify-center gap-2">
                                <span><?php echo ($row['total_overtime'] ?? 0) ?: '-'; ?></span>
                                <?php if (($row['total_overtime'] ?? 0) > 0): ?>
                                    <button type="button" onclick="openOvertimeDetails('<?php echo htmlspecialchars($row['id_karyawan']); ?>', '<?php echo date('m', strtotime($start_date)); ?>', '<?php echo date('Y', strtotime($start_date)); ?>', '<?php echo htmlspecialchars(addslashes($row['nama_karyawan'])); ?>')" class="text-fuchsia-500 hover:text-fuchsia-700 bg-fuchsia-50 hover:bg-fuchsia-100 p-1.5 rounded-lg transition-colors dark:bg-fuchsia-900/30 dark:hover:bg-fuchsia-800/50 focus:outline-none" title="Lihat Hari & Tanggal Overtime">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center font-bold <?php echo ($row['total_minggu'] ?? 0) > 0 ? 'text-amber-500 bg-amber-50/50 dark:bg-amber-900/10' : 'text-slate-400'; ?>"><?php echo ($row['total_minggu'] ?? 0) > 0 ? $row['total_minggu'] : '-'; ?></td>
                        <td class="px-4 py-3 text-center font-semibold text-slate-600 dark:text-slate-400"><?php echo $row['total_off'] ?: '-'; ?></td>
                        <td class="px-4 py-3 text-center font-semibold text-pink-600 dark:text-pink-400"><?php echo $row['total_sakit'] ?: '-'; ?></td>
                        <td class="px-4 py-3 text-center font-semibold text-fuchsia-600 dark:text-fuchsia-400"><?php echo $row['total_cuti'] ?: '-'; ?></td>
                        <td class="px-4 py-3 text-center font-semibold text-rose-600 dark:text-rose-500"><?php echo $row['total_alpha'] ?: '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-100 dark:bg-slate-900/80 font-bold text-sm border-t-2 border-slate-200 dark:border-slate-700">
                    <td colspan="2" class="px-4 py-4 text-right text-slate-800 dark:text-white uppercase tracking-wider">Total Keseluruhan</td>
                    <td class="px-3 py-4 text-center text-brand-600 dark:text-brand-400"><?php echo number_format($total_hadir_adjusted, 1); ?></td>
                    <td class="px-3 py-4 text-center text-emerald-600 dark:text-emerald-400"><?php echo $total_tepat_waktu; ?></td>
                    <td class="px-3 py-4 text-center text-rose-600 dark:text-rose-400"><?php echo $total_terlambat; ?></td>
                    <td class="px-3 py-4 text-center text-amber-600 dark:text-amber-400"><?php echo $total_setengah_hari; ?></td>
                    <td class="px-3 py-4 text-center text-purple-600 dark:text-purple-400"><?php echo $total_overtime; ?></td>
                    <td class="px-3 py-4 text-center text-amber-500"><?php echo $total_minggu; ?></td>
                    <td class="px-4 py-4 text-center text-slate-600 dark:text-slate-400"><?php echo $total_off; ?></td>
                    <td class="px-4 py-4 text-center text-pink-600 dark:text-pink-400"><?php echo $total_sakit; ?></td>
                    <td class="px-4 py-4 text-center text-fuchsia-600 dark:text-fuchsia-400"><?php echo $total_cuti; ?></td>
                    <td class="px-4 py-4 text-center text-rose-600 dark:text-rose-500"><?php echo $total_alpha; ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Modal Overtime Details -->
<div id="modal-overtime-details" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-business-time text-brand-500"></i> Detail Overtime
            </h3>
            <button type="button" onclick="document.getElementById('modal-overtime-details').classList.add('hidden'); document.body.style.overflow='auto';" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3" id="overtime-modal-title">Daftar Overtime: Karyawan</p>
            <div id="overtime-list-container" class="space-y-2 max-h-[300px] overflow-y-auto pr-2">
                <div class="text-center py-4 text-slate-500"><i class="fas fa-spinner fa-spin"></i> Memuat data...</div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="document.getElementById('modal-overtime-details').classList.add('hidden'); document.body.style.overflow='auto';" class="px-5 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors font-medium text-sm">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form Export -->
<form id="exportForm" method="POST" action="export_statistik.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="data" id="exportData">
    <input type="hidden" name="format" id="exportFormat">
    <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($nama_cabang); ?>">
    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
    <input type="hidden" name="total_hadir" value="<?php echo number_format($total_hadir_adjusted, 1); ?>">
    <input type="hidden" name="total_tepat_waktu" value="<?php echo $total_tepat_waktu; ?>">
    <input type="hidden" name="total_terlambat" value="<?php echo $total_terlambat; ?>">
    <input type="hidden" name="total_setengah_hari" value="<?php echo $total_setengah_hari; ?>">
    <input type="hidden" name="total_minggu" value="<?php echo $total_minggu; ?>">
    <input type="hidden" name="total_overtime" value="<?php echo $total_overtime; ?>">
    <input type="hidden" name="total_off" value="<?php echo $total_off; ?>">
    <input type="hidden" name="total_sakit" value="<?php echo $total_sakit; ?>">
    <input type="hidden" name="total_cuti" value="<?php echo $total_cuti; ?>">
    <input type="hidden" name="total_alpha" value="<?php echo $total_alpha; ?>">
</form>

<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#statistikTable').DataTable({
        "language": {
            "sEmptyTable":   "Tidak ada data yang tersedia pada tabel ini",
            "sProcessing":   "Sedang memproses...",
            "sLengthMenu":   "Tampilkan _MENU_ baris",
            "sZeroRecords":  "Tidak ditemukan data yang sesuai",
            "sInfo":         "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            "sInfoEmpty":    "Menampilkan 0 sampai 0 dari 0 data",
            "sInfoFiltered": "(disaring dari _MAX_ data keseluruhan)",
            "sInfoPostFix":  "",
            "sSearch":       "Cari Karyawan:",
            "sUrl":          "",
            "oPaginate": {
                "sFirst":    "Pertama",
                "sPrevious": "Sebelumnya",
                "sNext":     "Selanjutnya",
                "sLast":     "Terakhir"
            }
        },
        "pageLength": 10,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]],
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "columnDefs": [
            { "orderable": false, "targets": [0] } // Disable sorting untuk kolom No
        ],
        "initComplete": function(settings, json) {
            $('#statistikTable').removeClass('opacity-0');
        }
    });
});
</script>

<script>
const exportDataArray = <?php echo json_encode($dataForExport ?? []); ?>;

function exportToPDF() {
    if(exportDataArray.length === 0) {
        alert("Tidak ada data untuk di-export.");
        return;
    }
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportFormat').value = 'pdf';
    document.getElementById('exportForm').submit();
}

function exportToExcel() {
    if(exportDataArray.length === 0) {
        alert("Tidak ada data untuk di-export.");
        return;
    }
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportFormat').value = 'excel';
    document.getElementById('exportForm').submit();
}

function openOvertimeDetails(id_karyawan, bulan, tahun, nama) {
    document.getElementById('modal-overtime-details').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    document.getElementById('overtime-modal-title').textContent = 'Daftar Overtime: ' + nama;
    const container = document.getElementById('overtime-list-container');
    container.innerHTML = '<div class="text-center py-4 text-slate-500"><i class="fas fa-spinner fa-spin"></i> Memuat data...</div>';

    fetch(`ajax_get_overtime_details.php?id_karyawan=${id_karyawan}&bulan=${bulan}&tahun=${tahun}`)
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                if (res.data.length > 0) {
                    let html = '';
                    res.data.forEach((item, index) => {
                        html += `
                        <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-xs shrink-0">
                                ${index + 1}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-white">${item.hari}, ${item.tanggal}</p>
                            </div>
                        </div>`;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-center py-4 text-slate-500">Tidak ada data overtime.</div>';
                }
            } else {
                container.innerHTML = `<div class="text-center py-4 text-red-500">${res.message}</div>`;
            }
        })
        .catch(err => {
            container.innerHTML = '<div class="text-center py-4 text-red-500">Gagal memuat data.</div>';
        });
}
</script>

<?php require 'owner_footer.php'; ?>
