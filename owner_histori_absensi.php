<?php
require 'owner_header.php';

// Validasi cabang
if (!isset($_GET['cabang']) || !is_numeric($_GET['cabang'])) {
    header("Location: owner_rekap_absensi.php");
    exit();
}

$id_cabang = intval($_GET['cabang']);

// Ambil nama cabang
$stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
$stmt->bind_param("i", $id_cabang);
$stmt->execute();
$result = $stmt->get_result();
$cabang_info = $result->fetch_assoc();
$nama_cabang = $cabang_info ? $cabang_info['nama_cabang'] : 'Tidak Ditemukan';
$stmt->close();

if (!$cabang_info) {
    $_SESSION['error_message'] = "Cabang tidak ditemukan.";
    header("Location: owner_rekap_absensi.php");
    exit();
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

// Query dengan GROUP BY / logic serupa admin
$sql = "SELECT a.*, 
        k.nama_karyawan, 
        k.id as karyawan_id,
        k.id_cabang,
        (SELECT MAX(jam_pulang) FROM jam_kerja WHERE id_cabang = k.id_cabang) AS jam_pulang_standar,
        TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit
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

// CRITICAL FIX: Ambil semua shift data untuk cabang ini
$sql_shifts = "SELECT nama_shift, jam_masuk_akhir, jam_pulang FROM jam_kerja WHERE id_cabang = ? ORDER BY jam_pulang ASC";
$stmt_shifts = $conn->prepare($sql_shifts);
$stmt_shifts->bind_param("i", $id_cabang);
$stmt_shifts->execute();
$shifts_data = $stmt_shifts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_shifts->close();

// Function untuk detect shift berdasarkan jam masuk karyawan
function detectCorrectShift($jam_masuk_karyawan, $shifts_data) {
    if (empty($jam_masuk_karyawan) || empty($shifts_data)) {
        return null;
    }
    
    $jam_masuk_ts = strtotime($jam_masuk_karyawan);
    
    // Cari shift yang jam_masuk karyawan paling dekat dengan jam_masuk_akhir shift
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
?>

<!-- Top Action Bar -->
<div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-3">
            <a href="owner_rekap_absensi.php" class="text-slate-400 hover:text-brand-500 transition-colors">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Histori: <?php echo htmlspecialchars($nama_cabang); ?>
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 ml-9">Rekapitulasi data kehadiran karyawan cabang ini.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
        <a href="owner_statistik_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30 w-full sm:w-auto">
            <i class="fa-solid fa-chart-pie"></i> Statistik
        </a>
        
        <button onclick="exportToPDF()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-rose-500/30 w-full sm:w-auto">
            <i class="fa-solid fa-file-pdf"></i> Export PDF
        </button>
        
        <button onclick="exportToExcel()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-emerald-500/30 w-full sm:w-auto">
            <i class="fa-solid fa-file-excel"></i> Export Excel
        </button>

        <?php if (!empty($search_name)): ?>
            <a href="owner_histori_absensi.php?cabang=<?php echo $id_cabang; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-xl transition-colors font-medium text-sm w-full sm:w-auto">
                <i class="fa-solid fa-xmark"></i> Clear Pencarian
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Section -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 mb-8">
    <form method="GET" action="owner_histori_absensi.php" class="flex flex-col lg:flex-row gap-4 items-end">
        <input type="hidden" name="cabang" value="<?php echo $id_cabang; ?>">
        
        <div class="w-full lg:w-48">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Dari Tanggal</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
        </div>
        
        <div class="w-full lg:w-48">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
        </div>

        <div class="w-full lg:flex-1">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Cari Karyawan</label>
            <div class="relative">
                <input type="text" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Ketik nama karyawan..." class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full lg:w-auto px-6 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-brand-600 dark:hover:bg-brand-700 text-white rounded-xl font-medium shadow-sm transition-colors flex items-center justify-center gap-2">
            <i class="fa-solid fa-filter"></i> Terapkan
        </button>
    </form>
</div>

<?php if (!empty($search_name)): ?>
    <div class="bg-fuchsia-50 dark:bg-fuchsia-900/30 border border-fuchsia-100 dark:border-fuchsia-800/50 rounded-xl p-4 mb-6 flex items-center gap-3 text-fuchsia-800 dark:text-fuchsia-300">
        <i class="fa-solid fa-circle-info"></i>
        <p class="text-sm">Menampilkan hasil pencarian untuk: <strong>"<?php echo htmlspecialchars($search_name); ?>"</strong></p>
    </div>
<?php endif; ?>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    <div class="overflow-x-auto h-[65vh] relative">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead class="sticky top-0 z-10">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold text-center w-16">No</th>
                    <th class="px-6 py-4 font-semibold">Nama Karyawan</th>
                    <th class="px-6 py-4 font-semibold text-center">Tanggal</th>
                    <th class="px-6 py-4 font-semibold text-center">Jam Masuk</th>
                    <th class="px-6 py-4 font-semibold text-center">Jam Pulang</th>
                    <th class="px-6 py-4 font-semibold text-center">Status Masuk</th>
                    <th class="px-6 py-4 font-semibold text-center">Status Pulang</th>
                    <th class="px-6 py-4 font-semibold text-center">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <?php if ($absensi_list && $absensi_list->num_rows > 0): ?>
                    <?php 
                    $no = 1; 
                    $dataForExport = [];
                    while($row = $absensi_list->fetch_assoc()): 
                        $status_pulang = '-';
                        
                        $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
                        $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;
                        
                        if (!empty($row['jam_pulang']) && $row['jam_pulang'] != '00:00:00') {
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

                        $dataForExport[] = [
                            'no' => $no,
                            'nama' => htmlspecialchars($row['nama_karyawan']),
                            'tanggal' => date('d-m-Y', strtotime($row['tanggal'])),
                            'jam_masuk' => $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : '-',
                            'jam_keluar' => ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') ? date('H:i:s', strtotime($row['jam_pulang'])) : '-',
                            'status_masuk' => $row['keterangan'] == 'Hadir' ? $row['status_masuk'] : '-',
                            'status_pulang' => $status_pulang,
                            'keterangan' => htmlspecialchars($row['keterangan'])
                        ];
                    ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 text-center text-sm font-medium text-slate-500 dark:text-slate-400">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-800 dark:text-white text-sm"><?php echo htmlspecialchars($row['nama_karyawan']); ?></p>
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
                                        <i class="fa-solid fa-clock-rotate-left"></i> Setengah Hari
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
                                $ket_class = '';
                                $ket_icon = '';
                                switch($row['keterangan']) {
                                    case 'Hadir': $ket_class = 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800/50'; $ket_icon = 'fa-circle-check'; break;
                                    case 'OFF': $ket_class = 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'; $ket_icon = 'fa-calendar-xmark'; break;
                                    case 'Sakit': $ket_class = 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50'; $ket_icon = 'fa-bed-pulse'; break;
                                    case 'Cuti': $ket_class = 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200 dark:bg-fuchsia-900/30 dark:text-fuchsia-400 dark:border-fuchsia-800/50'; $ket_icon = 'fa-plane-departure'; break;
                                    case 'Alpha': $ket_class = 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50'; $ket_icon = 'fa-triangle-exclamation'; break;
                                    case 'Dinas Luar': $ket_class = 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50'; $ket_icon = 'fa-briefcase'; break;
                                }
                                ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border <?php echo $ket_class; ?> uppercase tracking-wide shadow-sm">
                                    <i class="fa-solid <?php echo $ket_icon; ?>"></i> <?php echo htmlspecialchars($row['keterangan']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-folder-open text-5xl mb-4 opacity-50"></i>
                            <p class="text-lg font-medium text-slate-800 dark:text-white">Tidak ada data absensi</p>
                            <p class="mt-1">
                                <?php if (!empty($search_name)): ?>
                                    Untuk pencarian "<?php echo htmlspecialchars($search_name); ?>"
                                <?php else: ?>
                                    Pada periode yang dipilih.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden Form Export -->
<form id="exportForm" method="POST" action="export_absensi.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="data" id="exportData">
    <input type="hidden" name="format" id="exportFormat">
    <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($nama_cabang); ?>">
    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
</form>

<script>
const exportDataArray = <?php echo json_encode(isset($dataForExport) ? $dataForExport : []); ?>;

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
</script>

<?php 
$stmt->close();
require 'owner_footer.php'; 
?>
