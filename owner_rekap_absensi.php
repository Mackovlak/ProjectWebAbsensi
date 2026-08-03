<?php
require 'config.php';
requireAdminOrOwner(); // Check admin or owner access

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

// Search filter
$search_name = isset($_GET['search_name']) ? html_entity_decode(sanitizeInput($_GET['search_name']), ENT_QUOTES, 'UTF-8') : '';

$absensi_list = null;
$shifts_data = [];
$dataForExport = [];

if ($id_cabang > 0) {
    // CRITICAL FIX: Ambil semua shift data untuk cabang ini
    $sql_shifts = "SELECT nama_shift, jam_masuk_akhir, jam_pulang FROM jam_kerja WHERE id_cabang = ? ORDER BY jam_pulang ASC";
    $stmt_shifts = $conn->prepare($sql_shifts);
    $stmt_shifts->bind_param("i", $id_cabang);
    $stmt_shifts->execute();
    $shifts_data = $stmt_shifts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_shifts->close();

    // Query diperbarui dengan is_manual_entry dan manual_entry_by
    $sql = "SELECT a.*, 
            k.nama_karyawan, 
            k.id as karyawan_id,
            k.id_cabang,
            (SELECT MAX(jam_pulang) FROM jam_kerja WHERE id_cabang = k.id_cabang) AS jam_pulang_standar,
            TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit,
            a.is_manual_entry,
            a.manual_entry_by
            FROM absensi a 
            JOIN karyawan k ON a.id_karyawan = k.id_karyawan
            WHERE k.id_cabang = ? 
            AND a.tanggal BETWEEN ? AND ?";

    if (!empty($search_name)) {
        $sql .= " AND k.nama_karyawan LIKE ?";
    }

    $sql .= " ORDER BY a.tanggal DESC, a.jam_masuk DESC";

    $stmt = $conn->prepare($sql);

    if (!empty($search_name)) {
        $search_param = '%' . $search_name . '%';
        $stmt->bind_param("isss", $id_cabang, $start_date, $end_date, $search_param);
    } else {
        $stmt->bind_param("iss", $id_cabang, $start_date, $end_date);
    }

    $stmt->execute();
    $absensi_list = $stmt->get_result();
}

// Function untuk detect shift berdasarkan jam masuk karyawan
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

$csrf_token = generateCSRFToken();

require 'owner_header.php';
?>

<!-- MAIN CONTENT -->
<div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
    <?php include 'alert_messages.php'; ?>
    
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Data Log Harian</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Pantau dan kelola riwayat kedatangan/kepulangan karyawan per hari.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-start sm:justify-end">

            <!-- Tombol Statistik -->
            <a href="owner_statistik_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30 w-full sm:w-auto">
                <i class="fa-solid fa-chart-simple"></i> Lihat Statistik
            </a>

            <!-- Tombol Export PDF -->
            <button onclick="exportToPDF()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800/50 rounded-xl hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </button>

            <!-- Filter Opsi Cabang -->
            <form action="owner_rekap_absensi.php" method="GET" class="relative m-0" id="formCabang">
                <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                <input type="hidden" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                <select name="cabang" onchange="document.getElementById('formCabang').submit()" class="appearance-none pl-4 pr-10 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors font-medium text-sm shadow-sm outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer">
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
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card Container -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
        
        <!-- Table Toolbar (Filter) -->
        <form action="owner_rekap_absensi.php" method="GET" class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-end gap-4">
            <input type="hidden" name="cabang" value="<?php echo $id_cabang; ?>">
            <div class="w-full sm:w-auto flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Pencarian</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                    </div>
                    <input type="text" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" class="block w-full pl-10 pr-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:text-white" placeholder="Cari nama karyawan...">
                </div>
            </div>

            <div class="w-full sm:w-auto flex-1 min-w-[150px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tanggal Mulai</label>
                <div class="relative">
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="block w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:text-white">
                </div>
            </div>

            <div class="w-full sm:w-auto flex-1 min-w-[150px]">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tanggal Akhir</label>
                <div class="relative">
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="block w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:text-white">
                </div>
            </div>

            <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-slate-800 dark:bg-slate-700 text-white rounded-xl font-medium shadow-sm hover:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-sm h-[38px] flex items-center justify-center gap-2">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <?php if (!empty($search_name)): ?>
                <a href="owner_rekap_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" class="w-full sm:w-auto px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl font-medium shadow-sm hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors text-sm h-[38px] flex items-center justify-center gap-2">
                    <i class="fa-solid fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Table View -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[1000px]" id="dataTable">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <th class="px-5 py-4 font-semibold text-center w-12">NO</th>
                        <th class="px-5 py-4 font-semibold">TANGGAL</th>
                        <th class="px-5 py-4 font-semibold">NAMA KARYAWAN</th>
                        <th class="px-5 py-4 font-semibold text-center">JAM MASUK</th>
                        <th class="px-5 py-4 font-semibold text-center">JAM PULANG</th>
                        <th class="px-5 py-4 font-semibold">STATUS / KETERANGAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700" id="tableBody">
                    <?php if ($absensi_list && $absensi_list->num_rows > 0): ?>
                        <?php 
                        $no = 1; 
                        while($row = $absensi_list->fetch_assoc()): 
                            $status_pulang = '-';
                            
                            // CRITICAL FIX: Detect shift berdasarkan jam masuk
                            $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
                            $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;
                            
                            if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00' && $row['jam_pulang'] != NULL) {
                                
                                // Hitung durasi dalam menit
                                $durasi_menit = $row['durasi_menit'];
                                
                                if ($durasi_menit !== null && $durasi_menit > 0) {
                                    if ($durasi_menit < 330) {
                                        $status_pulang = 'Setengah Hari';
                                    } elseif (!empty($jam_pulang_standar) && strtotime($row['jam_pulang']) > strtotime($jam_pulang_standar)) {
                                        $status_pulang = 'Over Time';
                                    } else {
                                        $status_pulang = 'Normal';
                                    }
                                }
                            } else if ($row['keterangan'] == 'Hadir' && strtotime($row['tanggal']) < strtotime('today')) {
                                $status_pulang = 'Belum Absen Pulang = Set. Hari';
                            }

                            // Simpan untuk export
                            $dataForExport[] = [
                                'no' => $no,
                                'nama' => htmlspecialchars($row['nama_karyawan']),
                                'tanggal' => date('d-m-Y', strtotime($row['tanggal'])),
                                'jam_masuk' => $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : '-',
                                'jam_keluar' => ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') ? date('H:i:s', strtotime($row['jam_pulang'])) : '-',
                                'status_masuk' => $row['keterangan'] == 'Hadir' ? $row['status_masuk'] : '-',
                                'status_pulang' => $status_pulang,
                                'keterangan' => htmlspecialchars($row['keterangan']),
                            ];

                            // Kumpulkan badge class
                            $tr_class = "hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors";
                            if ($row['keterangan'] == 'Sakit') $tr_class .= " bg-rose-50/30 dark:bg-rose-900/5";
                            else if ($row['keterangan'] == 'Cuti') $tr_class .= " bg-purple-50/30 dark:bg-purple-900/5";
                            else if ($row['keterangan'] == 'Alpha') $tr_class .= " bg-red-50/30 dark:bg-red-900/5";
                            else if ($row['keterangan'] == 'OFF') $tr_class .= " bg-slate-50/30 dark:bg-slate-800/30";
                        ?>
                            <tr class="<?php echo $tr_class; ?>">
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-center text-slate-500"><?php echo $no++; ?></td>
                                <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300 font-medium"><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-slate-800 dark:text-white text-sm"><?php echo htmlspecialchars($row['nama_karyawan']); ?></span>
                                        <?php if (isset($row['is_manual_entry']) && $row['is_manual_entry'] == 1): ?>
                                            <span class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-700 text-[10px] rounded font-medium text-slate-600 dark:text-slate-300" title="Ditambahkan manual">Manual</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($nama_cabang); ?></p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    <span class="inline-block px-3 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded font-mono text-sm border border-slate-200 dark:border-slate-600">
                                        <?php echo $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : '--:--:--'; ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    <?php if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00' && $row['jam_pulang'] != NULL): ?>
                                        <span class="inline-block px-3 py-1 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded font-mono text-sm border border-slate-200 dark:border-slate-600">
                                            <?php echo date('H:i:s', strtotime($row['jam_pulang'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-3 py-1 bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400 rounded font-mono text-sm border border-amber-200 dark:border-amber-800/50 font-bold">--:--:--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1.5 items-start">
                                        <?php if ($row['keterangan'] == 'Hadir'): ?>
                                            <?php if ($row['status_masuk'] == 'Tepat Waktu'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50">
                                                    <i class="fa-solid fa-circle-check text-[10px]"></i> Hadir Tepat Waktu
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50">
                                                    <i class="fa-solid fa-circle-exclamation text-[10px]"></i> Terlambat
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00'): ?>
                                                <?php if ($status_pulang == 'Setengah Hari'): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800/50">
                                                        <i class="fa-solid fa-clock-rotate-left"></i> Setengah Hari
                                                    </span>
                                                <?php elseif ($status_pulang == 'Over Time'): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50">
                                                        <i class="fa-solid fa-business-time"></i> Over Time
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo ($status_pulang == 'Belum Absen Pulang = Set. Hari') ? 'Belum Absen Pulang = Set. Hari' : 'Belum Absen Pulang'; ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php
                                                $keterangan_classes = [
                                                    'OFF' => 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600',
                                                    'Sakit' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50',
                                                    'Cuti' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50',
                                                    'Alpha' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50',
                                                    'Dinas Luar' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50'
                                                ];
                                                
                                                $icons = [
                                                    'OFF' => 'fa-calendar-times',
                                                    'Sakit' => 'fa-file-medical',
                                                    'Cuti' => 'fa-calendar-check',
                                                    'Alpha' => 'fa-xmark',
                                                    'Dinas Luar' => 'fa-briefcase'
                                                ];
                                                
                                                $class = $keterangan_classes[$row['keterangan']] ?? $keterangan_classes['OFF'];
                                                $icon = $icons[$row['keterangan']] ?? 'fa-circle';
                                            ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border <?php echo $class; ?>">
                                                <i class="fa-solid <?php echo $icon; ?> text-[10px]"></i> <?php echo htmlspecialchars($row['keterangan']); ?>
                                            </span>
                                            <?php if ($row['keterangan'] === 'Sakit' || $row['keterangan'] === 'Cuti' || $row['keterangan'] === 'Dinas Luar'): ?>
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <?php if ($row['keterangan'] === 'Dinas Luar' && $status_pulang === 'Setengah Hari'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800/50">
                                                            <i class="fa-solid fa-clock-rotate-left"></i> Setengah Hari
                                                        </span>
                                                    <?php elseif ($row['keterangan'] === 'Dinas Luar' && $status_pulang === 'Over Time'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50">
                                                            <i class="fa-solid fa-business-time"></i> Over Time
                                                        </span>
                                                        <?php if (!empty($row['alasan_pulang'])): ?>
                                                            <button type="button" onclick="openDetailAlasanModal(this)" data-alasan="<?php echo htmlspecialchars($row['alasan_pulang'] ?? ''); ?>" data-foto="<?php echo htmlspecialchars($row['foto_pulang'] ?? ''); ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100 transition-colors dark:bg-fuchsia-900/30 dark:text-fuchsia-400 dark:border-fuchsia-800/50">
                                                                <i class="fa-solid fa-eye"></i> Detail Lembur
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <button type="button" onclick="openDetailAlasanModal(this)" data-alasan="<?php echo htmlspecialchars($row['alasan'] ?? ''); ?>" data-foto="<?php echo htmlspecialchars($row['foto_bukti'] ?? ''); ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-medium bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100 transition-colors dark:bg-fuchsia-900/30 dark:text-fuchsia-400 dark:border-fuchsia-800/50">
                                                        <i class="fa-solid fa-eye"></i> Detail
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-20"></i>
                                    <p>Tidak ada data absensi yang ditemukan.</p>
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
<form id="exportForm" method="POST" action="export_absensi.php" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="data" id="exportData">
    <input type="hidden" name="format" id="exportFormat">
    <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($nama_cabang); ?>">
    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
</form>

<!-- ========================================== -->
<!-- MODAL: DETAIL ALASAN & FOTO                -->
<!-- ========================================== -->
<div id="modal-detail-alasan" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-file-lines text-fuchsia-500"></i> Detail Keterangan
            </h3>
            <button type="button" onclick="closeModal('modal-detail-alasan')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        
        <div class="p-6">
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Alasan</label>
                <div id="detail-alasan-text" class="p-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-700 dark:text-slate-300 min-h-[60px]">
                    <!-- Text alasan akan muncul di sini -->
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Foto Bukti</label>
                <div id="detail-alasan-foto-container" class="rounded-xl border border-slate-200 dark:border-slate-700 p-2 bg-slate-50 dark:bg-slate-900/50 text-center">
                    <img id="detail-alasan-foto" src="" alt="Foto Bukti" class="max-w-full max-h-[300px] rounded-lg mx-auto hidden cursor-pointer" onclick="window.open(this.src, '_blank')">
                    <p id="detail-alasan-no-foto" class="text-sm text-slate-500 dark:text-slate-400 py-4 hidden">
                        <i class="fa-solid fa-image-slash text-2xl mb-2 opacity-50 block"></i>
                        Tidak ada lampiran foto
                    </p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('modal-detail-alasan')" class="px-5 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors font-medium text-sm">
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
let trs = Array.from(tableBody.querySelectorAll('tr'));

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

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.style.overflow = 'auto';
}

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
        fotoImg.classList.add('hidden');
        noFoto.classList.remove('hidden');
    }
    
    openModal('modal-detail-alasan');
}

const exportDataArray = <?php echo json_encode(isset($dataForExport) ? $dataForExport : []); ?>;

function exportToPDF() {
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportFormat').value = 'pdf';
    document.getElementById('exportForm').submit();
}
</script>

<?php 
if (isset($stmt)) $stmt->close();
require 'owner_footer.php'; 
?>
