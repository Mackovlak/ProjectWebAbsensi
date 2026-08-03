<?php
require 'config.php';
requireAdmin();

// Ambil daftar cabang untuk dropdown
$cabang_list = [];
$res_cabang = $conn->query("SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC");
if ($res_cabang) {
    while($row = $res_cabang->fetch_assoc()) {
        $cabang_list[] = $row;
    }
}

// Default ke cabang pertama jika tidak ada yang dipilih
if (!isset($_GET['cabang']) || !is_numeric($_GET['cabang'])) {
    if (!empty($cabang_list)) {
        $id_cabang = intval($cabang_list[0]['id']);
    } else {
        $id_cabang = 0;
    }
} else {
    $id_cabang = intval($_GET['cabang']);
}

// Ambil nama cabang
$nama_cabang = 'Cabang Tidak Ditemukan';
foreach ($cabang_list as $c) {
    if ($c['id'] == $id_cabang) {
        $nama_cabang = $c['nama_cabang'];
        break;
    }
}

// Filter tanggal
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) 
    ? sanitizeInput($_GET['start_date']) 
    : date('Y-m-01');
    
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) 
    ? sanitizeInput($_GET['end_date']) 
    : date('Y-m-t');

// Validasi format tanggal
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$result_statistik = null;

if ($id_cabang > 0) {
    $sql_statistik = "SELECT
        k.nama_karyawan,
        k.id_karyawan,
        
        -- Total Hadir (PERBAIKAN: Kurangi setengah hari A- 0.5)
        COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') THEN a.id END) as count_hadir_raw,
        COUNT(DISTINCT CASE 
            WHEN a.keterangan = 'Hadir' AND (
                (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
                OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
            )
            THEN a.id 
        END) as count_setengah_hari,
        
        -- Tepat Waktu
        COUNT(DISTINCT CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Tepat Waktu' THEN a.id END) as total_tepat_waktu,
        
        -- Terlambat
        COUNT(DISTINCT CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Terlambat' THEN a.id END) as total_terlambat,
        
        -- Setengah Hari
        COUNT(DISTINCT CASE 
            WHEN a.keterangan = 'Hadir' AND (
                (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
                OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
            )
            THEN a.id 
        END) as total_setengah_hari,
        
        -- OVERTIME WITH SHIFT DETECTION (CRITICAL FIX!)
        SUM(CASE 
            WHEN a.jam_pulang IS NOT NULL 
            AND a.jam_pulang != '00:00:00'
            AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) >= 330
            AND a.jam_pulang > (
                -- Subquery: Find closest shift based on jam_masuk
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
        
        -- PERBAIKAN 1: TAMBAH KOLOM MINGGU (Sunday = DAYOFWEEK = 1)
        COUNT(DISTINCT CASE 
            WHEN a.keterangan = 'Hadir' 
            AND DAYOFWEEK(a.tanggal) = 1 
            THEN a.id 
        END) as count_minggu_raw,
        
        -- Count Sunday half-day (< 350 minutes)
        COUNT(DISTINCT CASE 
            WHEN a.keterangan = 'Hadir'
            AND DAYOFWEEK(a.tanggal) = 1
            AND (
                (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
                OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
            )
            THEN a.id 
        END) as count_minggu_setengah_hari,

        -- Status lainnya
        COUNT(DISTINCT CASE WHEN a.keterangan = 'OFF' THEN a.id END) as total_off,
        COUNT(DISTINCT CASE WHEN a.keterangan = 'Sakit' THEN a.id END) as total_sakit,
        COUNT(DISTINCT CASE WHEN a.keterangan = 'Cuti' THEN a.id END) as total_cuti,
        COUNT(DISTINCT CASE WHEN a.keterangan = 'Dinas Luar' THEN a.id END) as total_dinas_luar,
        COUNT(DISTINCT CASE WHEN a.keterangan = 'Alpha' THEN a.id END) as total_alpha
    FROM karyawan k
    LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan AND a.tanggal BETWEEN ? AND ?
    WHERE k.id_cabang = ?
    GROUP BY k.id_karyawan, k.nama_karyawan
    ORDER BY k.nama_karyawan ASC";

    $stmt = $conn->prepare($sql_statistik);
    $stmt->bind_param("ssi", $start_date, $end_date, $id_cabang);
    $stmt->execute();
    $result_statistik = $stmt->get_result();
}

$csrf_token = generateCSRFToken();

require 'admin_header.php';
?>

<div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white uppercase tracking-tight">Daftar Statistik</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Akumulasi kehadiran per cabang dan periode tertentu.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-start sm:justify-end">
            <a href="histori_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium text-sm shadow-sm">
                <i class="fa-solid fa-arrow-left text-brand-500"></i> Kembali ke Histori
            </a>
            <button onclick="exportStatistikToPDF()" class="flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-red-500/30">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- Filter Form Inline -->
    <form action="statistik_absensi.php" method="GET" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 flex flex-wrap gap-4 items-end">
        <div class="w-full sm:w-auto flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Cabang</label>
            <select name="cabang" onchange="this.form.submit()" class="block w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 outline-none focus:border-brand-500 dark:text-white cursor-pointer">
                <?php if (empty($cabang_list)): ?>
                    <option value="">-- Tidak ada cabang --</option>
                <?php else: ?>
                    <?php foreach($cabang_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $id_cabang == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nama_cabang']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Mulai Tanggal</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="block w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-sm outline-none focus:border-brand-500 dark:text-white">
        </div>
        <div class="w-full sm:w-auto flex-1 min-w-[150px]">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="block w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-sm outline-none focus:border-brand-500 dark:text-white">
        </div>
        <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-slate-800 dark:bg-slate-700 text-white rounded-xl font-medium shadow-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-sm h-[38px] flex items-center justify-center gap-2">
            <i class="fa-solid fa-magnifying-glass"></i> Cari Data
        </button>
    </form>

    <!-- Table Container -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[1200px]" id="dataTable">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-4 font-bold text-center w-12">No</th>
                        <th class="px-4 py-4 font-bold">Nama Karyawan</th>
                        <th class="px-4 py-4 font-bold text-center bg-slate-100/50 dark:bg-slate-700/30">Total</th>
                        <th class="px-4 py-4 font-bold text-center">Tepat Waktu</th>
                        <th class="px-4 py-4 font-bold text-center">Terlambat</th>
                        <th class="px-4 py-4 font-bold text-center">Setengah Hari</th>
                        <th class="px-4 py-4 font-bold text-center">Overtime</th>
                        <th class="px-4 py-4 font-bold text-center">Ahad</th>
                        <th class="px-4 py-4 font-bold text-center">Dinas Luar</th>
                        <th class="px-4 py-4 font-bold text-center">OFF</th>
                        <th class="px-4 py-4 font-bold text-center">Sakit</th>
                        <th class="px-4 py-4 font-bold text-center">Cuti</th>
                        <th class="px-4 py-4 font-bold text-center">Alpha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700 text-sm" id="tableBody">
                    <?php if ($result_statistik && $result_statistik->num_rows > 0): ?>
                        <?php 
                        $total_all_hadir_adjusted = 0;
                        $total_all_tepat_waktu = 0;
                        $total_all_terlambat = 0;
                        $total_all_setengah_hari = 0;
                        $total_all_overtime = 0;
                        $total_all_minggu = 0;
                        $total_all_off = 0;
                        $total_all_sakit = 0;
                        $total_all_cuti = 0;
                        $total_all_dinas_luar = 0;
                        $total_all_alpha = 0;
                        
                        $no = 1;
                        $dataForExport = [];

                        while($row = $result_statistik->fetch_assoc()): 
                            $count_hadir_raw = (int)$row['count_hadir_raw'];
                            $count_setengah_hari = (int)$row['count_setengah_hari'];
                            $count_full_day = $count_hadir_raw - $count_setengah_hari;
                            $total_hadir_adjusted = $count_full_day + ($count_setengah_hari * 0.5);
                            
                            $count_minggu_raw = (int)$row['count_minggu_raw'];
                            $count_minggu_setengah_hari = (int)$row['count_minggu_setengah_hari'];
                            $count_minggu_full_day = $count_minggu_raw - $count_minggu_setengah_hari;
                            $total_minggu_adjusted = $count_minggu_full_day + ($count_minggu_setengah_hari * 0.5);
                            
                            $total_all_hadir_adjusted += $total_hadir_adjusted;
                            $total_all_tepat_waktu += (int)$row['total_tepat_waktu'];
                            $total_all_terlambat += (int)$row['total_terlambat'];
                            $total_all_setengah_hari += (int)$row['total_setengah_hari'];
                            $total_all_overtime += (int)$row['total_overtime'];
                            $total_all_minggu += $total_minggu_adjusted;
                            $total_all_off += (int)$row['total_off'];
                            $total_all_sakit += (int)$row['total_sakit'];
                            $total_all_cuti += (int)$row['total_cuti'];
                            $total_all_dinas_luar += (int)$row['total_dinas_luar'];
                            $total_all_alpha += (int)$row['total_alpha'];

                            $dataForExport[] = [
                                'no' => $no,
                                'nama' => htmlspecialchars($row['nama_karyawan']),
                                'hadir' => number_format($total_hadir_adjusted, 1),
                                'tepat_waktu' => $row['total_tepat_waktu'],
                                'terlambat' => $row['total_terlambat'],
                                'setengah_hari' => $row['total_setengah_hari'],
                                'overtime' => $row['total_overtime'],
                                'minggu' => $total_minggu_adjusted,
                                'off' => $row['total_off'],
                                'sakit' => $row['total_sakit'],
                                'cuti' => $row['total_cuti'],
                                'dinas_luar' => $row['total_dinas_luar'],
                                'alpha' => $row['total_alpha']
                            ];
                        ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-4 py-4 text-center text-slate-500"><?php echo $no++; ?></td>
                                <td class="px-4 py-4 font-semibold text-slate-800 dark:text-white uppercase">
                                    <a href="histori_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>&search_name=<?php echo urlencode($row['nama_karyawan']); ?>" class="hover:text-brand-500 transition-colors" title="Lihat Histori Kehadiran">
                                        <?php echo htmlspecialchars($row['nama_karyawan']); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-4 text-center font-bold bg-slate-100/30 dark:bg-slate-700/10">
                                    <?php echo ($count_setengah_hari > 0) ? number_format($total_hadir_adjusted, 1) : ($count_hadir_raw ?: '-'); ?>
                                </td>
                                <td class="px-4 py-4 text-center"><?php echo $row['total_tepat_waktu'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center text-rose-500 font-medium"><?php echo $row['total_terlambat'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center"><?php echo $row['total_setengah_hari'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center font-semibold text-purple-600 dark:text-purple-400">
                                    <div class="flex items-center justify-center gap-2">
                                        <span><?php echo $row['total_overtime'] ?: '-'; ?></span>
                                        <?php if ($row['total_overtime'] > 0): ?>
                                            <button type="button" onclick="openOvertimeDetails('<?php echo htmlspecialchars($row['id_karyawan']); ?>', '<?php echo date('m', strtotime($start_date)); ?>', '<?php echo date('Y', strtotime($start_date)); ?>', '<?php echo htmlspecialchars(addslashes($row['nama_karyawan'])); ?>')" class="text-fuchsia-500 hover:text-fuchsia-700 bg-fuchsia-50 hover:bg-fuchsia-100 p-1.5 rounded-lg transition-colors dark:bg-fuchsia-900/30 dark:hover:bg-fuchsia-800/50" title="Lihat Hari & Tanggal Overtime">
                                                <i class="fa-solid fa-eye text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center font-medium"><?php echo $total_minggu_adjusted ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center text-purple-500"><?php echo $row['total_dinas_luar'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center text-slate-400"><?php echo $row['total_off'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center text-amber-500"><?php echo $row['total_sakit'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center text-purple-500"><?php echo $row['total_cuti'] ?: '-'; ?></td>
                                <td class="px-4 py-4 text-center text-red-500 font-bold"><?php echo $row['total_alpha'] ?: '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <!-- TOTAL ROW -->
                        <tr class="bg-brand-50/50 dark:bg-brand-900/10 border-t-2 border-brand-200 dark:border-brand-800/50 flex-none" id="totalRow">
                            <td colspan="2" class="px-4 py-4 text-right font-bold text-slate-800 dark:text-white uppercase text-xs tracking-wider">Total Keseluruhan</td>
                            <td class="px-4 py-4 text-center font-bold text-brand-600 dark:text-brand-400 bg-brand-100/30 dark:bg-brand-800/20"><?php echo number_format($total_all_hadir_adjusted, 1); ?></td>
                            <td class="px-4 py-4 text-center font-bold text-slate-700 dark:text-slate-300"><?php echo $total_all_tepat_waktu; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-rose-600 dark:text-rose-400"><?php echo $total_all_terlambat; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-slate-700 dark:text-slate-300"><?php echo $total_all_setengah_hari; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-purple-600 dark:text-purple-400"><?php echo $total_all_overtime; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-slate-700 dark:text-slate-300"><?php echo $total_all_minggu; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-purple-600"><?php echo $total_all_dinas_luar; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-slate-500"><?php echo $total_all_off; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-amber-600"><?php echo $total_all_sakit; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-purple-600"><?php echo $total_all_cuti; ?></td>
                            <td class="px-4 py-4 text-center font-bold text-red-600"><?php echo $total_all_alpha; ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-20"></i>
                                    <p>Tidak ada data statistik untuk periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Controls -->
        <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50 dark:bg-slate-900/50">
            <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto justify-between sm:justify-start">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-500 dark:text-slate-400">Tampilkan</span>
                    <select id="rowsPerPage" onchange="updateRowsPerPage()" class="px-2.5 py-1.5 border border-slate-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 outline-none focus:border-brand-500 cursor-pointer shadow-sm">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-sm text-slate-500 dark:text-slate-400">baris</span>
                </div>
                <div class="text-sm text-slate-500 dark:text-slate-400" id="tableInfo">
                    Menampilkan 0 hingga 0 dari 0 data
                </div>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto justify-center md:justify-end pb-1 md:pb-0" id="paginationControls">
                <!-- Buttons will be rendered here by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form untuk Export -->
<form id="exportForm" method="POST" action="export_statistik.php" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="data" id="exportData">
    <input type="hidden" name="format" id="exportFormat">
    <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($nama_cabang); ?>">
    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
</form>

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
                <!-- Data loaded via AJAX -->
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

<script>
// DataTables JS Pagination
const tableBody = document.getElementById('tableBody');
let currentPage = 1;
let rowsPerPage = 5;

// Exclude the Total row from pagination
let allTrs = Array.from(tableBody.querySelectorAll('tr'));
let trs = allTrs.filter(tr => !tr.hasAttribute('id') || tr.id !== 'totalRow');
let totalRow = document.getElementById('totalRow');

function updateRowsPerPage() {
    rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
    initPagination();
}

function initPagination() {
    if (trs.length === 0 || (trs.length === 1 && trs[0].querySelector('td[colspan]'))) {
        document.getElementById('tableInfo').textContent = 'Menampilkan 0 hingga 0 dari 0 data';
        document.getElementById('paginationControls').innerHTML = '';
        return;
    }
    goToPage(1);
}

function goToPage(page) {
    const totalEntries = trs.length;
    const totalPages = Math.ceil(totalEntries / rowsPerPage);
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentPage = page;

    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, totalEntries);

    trs.forEach((tr, index) => {
        if (index >= startIndex && index < endIndex) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    });
    
    if (totalRow) totalRow.style.display = ''; // Selalu tampilkan baris total

    const infoSpan = document.getElementById('tableInfo');
    infoSpan.textContent = `Menampilkan ${startIndex + 1} hingga ${endIndex} dari ${totalEntries} data`;

    const paginationControls = document.getElementById('paginationControls');
    paginationControls.innerHTML = '';

    if (totalPages > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}`;
        prevBtn.textContent = 'Previous';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => goToPage(currentPage - 1);
        paginationControls.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            if (totalPages > 7) {
                if (i !== 1 && i !== totalPages && (i < currentPage - 1 || i > currentPage + 1)) {
                    if (i === currentPage - 2 || i === currentPage + 2) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'px-2 py-1 text-slate-500';
                        ellipsis.innerHTML = '&hellip;';
                        paginationControls.appendChild(ellipsis);
                    }
                    continue;
                }
            }

            const pageBtn = document.createElement('button');
            if (i === currentPage) {
                pageBtn.className = 'px-3 py-1 border border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400 rounded-md font-medium';
            } else {
                pageBtn.className = 'px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors';
            }
            pageBtn.textContent = i;
            pageBtn.onclick = () => goToPage(i);
            paginationControls.appendChild(pageBtn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}`;
        nextBtn.textContent = 'Next';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => goToPage(currentPage + 1);
        paginationControls.appendChild(nextBtn);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initPagination();
});

const exportDataArray = <?php echo json_encode(isset($dataForExport) ? $dataForExport : []); ?>;

function exportStatistikToPDF() {
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportFormat').value = 'pdf';
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

<?php 
if (isset($stmt)) $stmt->close();
require 'admin_footer.php'; 
?>
