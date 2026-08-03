<?php
include 'staff_header.php';

// Filter tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

$id_karyawan_staff = $_SESSION['id_karyawan'];

// Query
$sql_absensi = "SELECT a.*, k.nama_karyawan, 
                TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit,
                (SELECT MAX(jam_pulang) FROM jam_kerja WHERE id_cabang = k.id_cabang) AS jam_pulang_standar
                FROM absensi a 
                JOIN karyawan k ON a.id_karyawan = k.id_karyawan
                WHERE a.id_karyawan = ? 
                AND a.tanggal BETWEEN ? AND ?
                ORDER BY a.tanggal DESC, a.jam_masuk DESC";
                        
$stmt_absensi = $conn->prepare($sql_absensi);
$stmt_absensi->bind_param("sss", $id_karyawan_staff, $start_date, $end_date);
$stmt_absensi->execute();
$result_absensi = $stmt_absensi->get_result();

// CRITICAL FIX: Ambil semua shift data untuk staff's cabang
$sql_shifts = "SELECT jk.nama_shift, jk.jam_masuk_akhir, jk.jam_pulang 
               FROM jam_kerja jk
               JOIN karyawan k ON jk.id_cabang = k.id_cabang
               WHERE k.id_karyawan = ?
               ORDER BY jk.jam_pulang ASC";
$stmt_shifts = $conn->prepare($sql_shifts);
$stmt_shifts->bind_param("s", $id_karyawan_staff);
$stmt_shifts->execute();
$shifts_data = $stmt_shifts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_shifts->close();

function detectCorrectShift($jam_masuk_karyawan, $shifts_data) {
    if (empty($jam_masuk_karyawan) || empty($shifts_data)) {
        return null;
    }
    
    $jam_masuk_ts = strtotime($jam_masuk_karyawan);
    $best_match = null;
    $min_diff = PHP_INT_MAX;
    
    foreach ($shifts_data as $shift) {
        $shift_masuk_ts = strtotime($shift['jam_masuk_akhir']);
        $diff = abs($jam_masuk_ts - $shift_masuk_ts);
        
        if ($diff < $min_diff) {
            $min_diff = $diff;
            $best_match = $shift;
        }
    }
    
    return $best_match;
}

$nama_karyawan = $_SESSION['nama_karyawan'] ?? '';
$csrf_token = generateCSRFToken();
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div class="hidden sm:block">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Histori Kehadiran</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Daftar rekaman absensi Anda.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <a href="staff_statistik.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30">
            <i class="fa-solid fa-chart-pie"></i> <span class="hidden sm:inline">Statistik</span>
        </a>
        
        <button onclick="exportToPDF()" class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-rose-500/30">
            <i class="fa-solid fa-file-pdf"></i> <span class="hidden sm:inline">Export PDF</span>
        </button>
    </div>
</div>

<!-- Filter Section -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 sm:p-5 mb-6 sm:mb-8">
    <form method="GET" action="staff_dashboard.php" class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="w-full sm:w-auto flex-1">
            <label class="block text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 sm:py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
        </div>
        
        <div class="w-full sm:w-auto flex-1">
            <label class="block text-xs sm:text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 sm:py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
        </div>

        <button type="submit" class="w-full sm:w-auto px-6 py-2 sm:py-2.5 bg-slate-800 hover:bg-slate-900 dark:bg-brand-600 dark:hover:bg-brand-700 text-white rounded-xl font-medium shadow-sm transition-colors flex items-center justify-center gap-2">
            <i class="fa-solid fa-filter"></i> Terapkan
        </button>
    </form>
</div>

<!-- Table Container -->
<div class="bg-transparent sm:bg-white sm:dark:bg-slate-800 sm:rounded-2xl sm:border sm:border-slate-200 sm:dark:border-slate-700 sm:shadow-sm overflow-hidden flex flex-col mb-8">
    
    <!-- Mobile View (Cards) -->
    <div class="block sm:hidden space-y-4">
        <?php if ($result_absensi->num_rows > 0): ?>
            <?php 
            $no = 1; 
            $dataForExport = [];
            mysqli_data_seek($result_absensi, 0); // Reset pointer
            while($row = $result_absensi->fetch_assoc()): 
                $status_pulang = '-';
                $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
                $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;
                
                if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') {
                    $durasi_menit = $row['durasi_menit'];
                    if ($durasi_menit !== NULL && $durasi_menit > 0) {
                        if ($durasi_menit < 330) $status_pulang = 'Setengah Hari';
                        elseif (!empty($jam_pulang_standar) && strtotime($row['jam_pulang']) > strtotime($jam_pulang_standar)) $status_pulang = 'Over Time';
                        else $status_pulang = 'Normal';
                    }
                } else if ($row['keterangan'] == 'Hadir' && strtotime($row['tanggal']) < strtotime('today')) {
                    $status_pulang = 'Belum Absen Pulang = Set. Hari';
                }
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm relative overflow-hidden">
                <!-- Color bar side indicator based on attendance -->
                <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php 
                    if($row['keterangan'] == 'Hadir') echo 'bg-emerald-500';
                    elseif($row['keterangan'] == 'OFF') echo 'bg-slate-400';
                    elseif($row['keterangan'] == 'Sakit') echo 'bg-amber-500';
                    elseif($row['keterangan'] == 'Cuti') echo 'bg-fuchsia-500';
                    else echo 'bg-rose-500';
                ?>"></div>

                <div class="flex justify-between items-start mb-3 pl-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>
                        <span class="font-bold text-slate-800 dark:text-white"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></span>
                    </div>
                    
                    <?php 
                        $ket_class = ''; $ket_icon = '';
                        switch($row['keterangan']) {
                            case 'Hadir': $ket_class = 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50'; $ket_icon = 'fa-circle-check'; break;
                            case 'OFF': $ket_class = 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'; $ket_icon = 'fa-calendar-xmark'; break;
                            case 'Sakit': $ket_class = 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50'; $ket_icon = 'fa-bed-pulse'; break;
                            case 'Cuti': $ket_class = 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200 dark:bg-fuchsia-900/30 dark:text-fuchsia-400 dark:border-fuchsia-800/50'; $ket_icon = 'fa-plane-departure'; break;
                            case 'Alpha': $ket_class = 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50'; $ket_icon = 'fa-triangle-exclamation'; break;
                        }
                    ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border <?php echo $ket_class; ?> uppercase tracking-wide">
                        <i class="fa-solid <?php echo $ket_icon; ?>"></i> <?php echo htmlspecialchars($row['keterangan']); ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-2 gap-3 pl-2">
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                        <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider mb-1">Masuk</p>
                        <p class="font-mono text-lg font-bold text-slate-800 dark:text-white mb-1"><?php echo $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '--:--'; ?></p>
                        <?php if ($row['keterangan'] == 'Hadir'): ?>
                            <?php if ($row['status_masuk'] == 'Tepat Waktu'): ?>
                                <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"><i class="fa-solid fa-check"></i> Tepat Waktu</span>
                            <?php else: ?>
                                <span class="text-[10px] font-semibold text-rose-600 dark:text-rose-400"><i class="fa-solid fa-xmark"></i> Terlambat</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                        <p class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider mb-1">Pulang</p>
                        <p class="font-mono text-lg font-bold text-slate-800 dark:text-white mb-1"><?php echo ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') ? date('H:i', strtotime($row['jam_pulang'])) : '--:--'; ?></p>
                        <?php if ($status_pulang == 'Setengah Hari'): ?>
                            <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400"><i class="fa-solid fa-clock"></i> 1/2 Hari</span>
                        <?php elseif ($status_pulang == 'Belum Absen Pulang = Set. Hari'): ?>
                            <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400"><i class="fa-solid fa-triangle-exclamation"></i> Belum Absen Pulang = Set. Hari</span>
                        <?php elseif ($status_pulang == 'Over Time'): ?>
                            <span class="text-[10px] font-semibold text-purple-600 dark:text-purple-400"><i class="fa-solid fa-business-time"></i> Over Time</span>
                        <?php elseif ($status_pulang == 'Normal'): ?>
                            <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"><i class="fa-solid fa-check"></i> Normal</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (in_array($row['keterangan'], ['Sakit', 'Cuti', 'Pending Dinas', 'Dinas Luar'])): ?>
                <div class="mt-3 pl-2">
                    <button type="button" onclick="openDetailAlasanModal(this)" data-alasan="<?php echo htmlspecialchars($row['alasan'] ?? ''); ?>" data-foto="<?php echo htmlspecialchars($row['foto_bukti'] ?? ''); ?>" class="w-full inline-flex justify-center items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors dark:bg-slate-700/50 dark:text-slate-300 dark:hover:bg-slate-600 border border-slate-200 dark:border-slate-600 shadow-sm">
                        <i class="fa-solid fa-file-lines"></i> Lihat Detail
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-8 text-center shadow-sm">
                <i class="fa-solid fa-inbox text-4xl text-slate-300 dark:text-slate-600 mb-3"></i>
                <p class="text-sm font-medium text-slate-800 dark:text-white">Tidak ada data absensi</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Desktop View (Table) -->
    <div class="hidden sm:block overflow-x-auto h-[60vh] relative">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead class="sticky top-0 z-10">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold text-center w-16">No</th>
                    <th class="px-6 py-4 font-semibold text-center">Tanggal</th>
                    <th class="px-6 py-4 font-semibold text-center">Jam Masuk</th>
                    <th class="px-6 py-4 font-semibold text-center">Jam Pulang</th>
                    <th class="px-6 py-4 font-semibold text-center">Status Masuk</th>
                    <th class="px-6 py-4 font-semibold text-center">Status Pulang</th>
                    <th class="px-6 py-4 font-semibold text-center">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <?php if ($result_absensi->num_rows > 0): ?>
                    <?php 
                    $no = 1; 
                    mysqli_data_seek($result_absensi, 0); // Reset pointer
                    while($row = $result_absensi->fetch_assoc()): 
                        $status_pulang = '-';
                        $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
                        $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;
                        
                        if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') {
                            $durasi_menit = $row['durasi_menit'];
                            if ($durasi_menit !== NULL && $durasi_menit > 0) {
                                if ($durasi_menit < 330) $status_pulang = 'Setengah Hari';
                                elseif (!empty($jam_pulang_standar) && strtotime($row['jam_pulang']) > strtotime($jam_pulang_standar)) $status_pulang = 'Over Time';
                                else $status_pulang = 'Normal';
                            }
                        } else if ($row['keterangan'] == 'Hadir' && strtotime($row['tanggal']) < strtotime('today')) {
                            $status_pulang = 'Belum Absen Pulang = Set. Hari';
                        }

                        // Untuk PDF
                        $dataForExport[] = [
                            'no' => $no,
                            'tanggal' => date('d-m-Y', strtotime($row['tanggal'])),
                            'jam_masuk' => $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : '-',
                            'jam_pulang' => ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') ? date('H:i:s', strtotime($row['jam_pulang'])) : '-',
                            'status_masuk' => $row['keterangan'] == 'Hadir' ? $row['status_masuk'] : '-',
                            'status_pulang' => $status_pulang,
                            'keterangan' => ucfirst($row['keterangan'])
                        ];
                    ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 text-center text-sm font-medium text-slate-500 dark:text-slate-400">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-slate-600 dark:text-slate-300 font-medium">
                                <?php echo date('d M Y', strtotime($row['tanggal'])); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($row['jam_masuk']): ?>
                                    <span class="font-mono text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-900/50 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700">
                                        <?php echo date('H:i', strtotime($row['jam_masuk'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00'): ?>
                                    <span class="font-mono text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-900/50 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700">
                                        <?php echo date('H:i', strtotime($row['jam_pulang'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($row['keterangan'] == 'Hadir'): ?>
                                    <?php if ($row['status_masuk'] == 'Tepat Waktu'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 uppercase tracking-wide">
                                            <i class="fa-solid fa-check-circle"></i> Tepat
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 uppercase tracking-wide">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Telat
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($status_pulang == 'Setengah Hari'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50 uppercase tracking-wide">
                                        <i class="fa-solid fa-clock-rotate-left"></i> 1/2 Hari
                                    </span>
                                <?php elseif ($status_pulang == 'Belum Absen Pulang = Set. Hari'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50 uppercase tracking-wide">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Belum Absen Pulang = Set. Hari
                                    </span>
                                <?php elseif ($status_pulang == 'Over Time'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border border-purple-200 dark:border-purple-800/50 uppercase tracking-wide">
                                        <i class="fa-solid fa-briefcase"></i> Over Time
                                    </span>
                                <?php elseif ($status_pulang == 'Normal'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 uppercase tracking-wide">
                                        <i class="fa-solid fa-check"></i> Normal
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php 
                                $ket_class = ''; $ket_icon = '';
                                switch($row['keterangan']) {
                                    case 'Hadir': $ket_class = 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50'; $ket_icon = 'fa-circle-check'; break;
                                    case 'OFF': $ket_class = 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'; $ket_icon = 'fa-calendar-xmark'; break;
                                    case 'Sakit': $ket_class = 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50'; $ket_icon = 'fa-bed-pulse'; break;
                                    case 'Cuti': $ket_class = 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200 dark:bg-fuchsia-900/30 dark:text-fuchsia-400 dark:border-fuchsia-800/50'; $ket_icon = 'fa-plane-departure'; break;
                                    case 'Alpha': $ket_class = 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50'; $ket_icon = 'fa-triangle-exclamation'; break;
                                }
                                ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border <?php echo $ket_class; ?> uppercase tracking-wide shadow-sm">
                                    <i class="fa-solid <?php echo $ket_icon; ?>"></i> <?php echo htmlspecialchars($row['keterangan']); ?>
                                </span>
                                <?php if (in_array($row['keterangan'], ['Sakit', 'Cuti', 'Pending Dinas', 'Dinas Luar'])): ?>
                                    <div class="mt-2">
                                        <button type="button" onclick="openDetailAlasanModal(this)" data-alasan="<?php echo htmlspecialchars($row['alasan'] ?? ''); ?>" data-foto="<?php echo htmlspecialchars($row['foto_bukti'] ?? ''); ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 transition-colors dark:bg-slate-700/50 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600 shadow-sm">
                                            <i class="fa-solid fa-file-lines"></i> Detail
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-inbox text-5xl mb-4 opacity-50"></i>
                            <p class="text-lg font-medium text-slate-800 dark:text-white">Tidak ada data absensi</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Detail Alasan -->
<div id="modal-detail-alasan" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity opacity-0">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-200 dark:border-slate-700 transform transition-transform scale-95" id="modal-detail-alasan-content">
        <div class="flex justify-between items-center p-4 sm:p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-file-lines text-brand-500"></i> Detail Keterangan
            </h3>
            <button type="button" onclick="closeDetailAlasanModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <div class="p-4 sm:p-5 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Alasan / Catatan</label>
                <div id="detail-alasan-text" class="p-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-300 min-h-[60px]">
                    <!-- Text alasan akan muncul di sini -->
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Foto / Bukti Lampiran</label>
                <div id="detail-alasan-foto-container" class="rounded-xl border border-slate-200 dark:border-slate-700 p-2 bg-slate-50 dark:bg-slate-900/50 text-center">
                    <img id="detail-alasan-foto" src="" alt="Foto Bukti" class="max-w-full max-h-[300px] rounded-lg mx-auto hidden cursor-pointer shadow-sm hover:shadow-md transition-shadow" onclick="window.open(this.src, '_blank')">
                    <p id="detail-alasan-no-foto" class="text-sm text-slate-500 dark:text-slate-400 py-4 hidden">
                        <i class="fa-solid fa-image-slash text-2xl mb-2 opacity-50 block"></i>
                        Tidak ada foto bukti yang dilampirkan
                    </p>
                </div>
            </div>
        </div>
        <div class="p-4 sm:p-5 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex justify-end">
            <button type="button" onclick="closeDetailAlasanModal()" class="px-5 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors font-medium text-sm">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Hidden Form Export PDF -->
<form id="exportForm" method="POST" action="export_staff_absensi.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="data" id="exportData">
    <input type="hidden" name="nama_karyawan" value="<?php echo htmlspecialchars($nama_karyawan); ?>">
    <input type="hidden" name="id_karyawan" value="<?php echo htmlspecialchars($id_karyawan_staff); ?>">
    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
</form>

<script>
// Modal Functions
function openDetailAlasanModal(button) {
    const alasan = button.getAttribute('data-alasan');
    const foto = button.getAttribute('data-foto');
    
    document.getElementById('detail-alasan-text').textContent = alasan || '- Tidak ada keterangan yang ditulis -';
    
    const fotoImg = document.getElementById('detail-alasan-foto');
    const noFoto = document.getElementById('detail-alasan-no-foto');
    
    if (foto) {
        fotoImg.src = 'assets/uploads/absensi/' + foto;
        fotoImg.classList.remove('hidden');
        noFoto.classList.add('hidden');
    } else {
        fotoImg.src = '';
        fotoImg.classList.add('hidden');
        noFoto.classList.remove('hidden');
    }
    
    const modal = document.getElementById('modal-detail-alasan');
    const modalContent = document.getElementById('modal-detail-alasan-content');
    modal.classList.remove('hidden');
    // Trigger reflow
    void modal.offsetWidth;
    modal.classList.remove('opacity-0');
    modalContent.classList.remove('scale-95');
}

function closeDetailAlasanModal() {
    const modal = document.getElementById('modal-detail-alasan');
    const modalContent = document.getElementById('modal-detail-alasan-content');
    modal.classList.add('opacity-0');
    modalContent.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300); // Matches Tailwind's default duration
}

const exportDataArray = <?php echo json_encode($dataForExport ?? []); ?>;

function exportToPDF() {
    if (exportDataArray.length === 0) {
        alert('Tidak ada data untuk diekspor.');
        return;
    }
    
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportForm').submit();
}
</script>

<?php include 'staff_footer.php'; ?>
