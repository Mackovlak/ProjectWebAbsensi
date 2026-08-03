<?php
require 'config.php';
include 'admin_header.php';

// Ambil tanggal hari ini
$today = date("Y-m-d");

// 1. Query untuk mengambil data summary dari database
$total_karyawan_result = $conn->query("SELECT COUNT(*) as total FROM karyawan");
$total_cabang_result = $conn->query("SELECT COUNT(*) as total FROM cabang");

$hadir_stmt = $conn->prepare("SELECT COUNT(DISTINCT id_karyawan) as total FROM absensi WHERE tanggal = ? AND keterangan = 'Hadir'");
$hadir_stmt->bind_param("s", $today);
$hadir_stmt->execute();
$hadir_hari_ini_result = $hadir_stmt->get_result();

$terlambat_stmt = $conn->prepare("SELECT COUNT(*) as total FROM absensi WHERE tanggal = ? AND status_masuk = 'Terlambat'");
$terlambat_stmt->bind_param("s", $today);
$terlambat_stmt->execute();
$terlambat_hari_ini_result = $terlambat_stmt->get_result();

$total_karyawan = $total_karyawan_result->fetch_assoc()['total'];
$total_cabang = $total_cabang_result->fetch_assoc()['total'];
$hadir_hari_ini = $hadir_hari_ini_result->fetch_assoc()['total'];
$terlambat_hari_ini = $terlambat_hari_ini_result->fetch_assoc()['total'];

// 2. Data untuk Chart HTML - Karyawan per Cabang
$sql_cabang_chart = "SELECT c.nama_cabang, COUNT(k.id) as jumlah 
                     FROM cabang c 
                     LEFT JOIN karyawan k ON c.id = k.id_cabang 
                     GROUP BY c.id, c.nama_cabang
                     ORDER BY c.nama_cabang ASC LIMIT 5";
$result_cabang_chart = $conn->query($sql_cabang_chart);

$cabang_data = [];
$max_karyawan = 0;
while($row = $result_cabang_chart->fetch_assoc()) {
    $cabang_data[] = $row;
    if ($row['jumlah'] > $max_karyawan) {
        $max_karyawan = $row['jumlah'];
    }
}
if ($max_karyawan == 0) $max_karyawan = 1; // Mencegah division by zero

// Warna bar chart (dirotasi) - Dinia Colors
$bar_colors = [
    'bg-purple-500 dark:bg-purple-600',
    'bg-yellow-400 dark:bg-yellow-500',
    'bg-orange-500 dark:bg-orange-600'
];

// 3. Data untuk Aktivitas Terbaru (5 Absensi Terakhir)
$sql_aktivitas = "SELECT a.tanggal, a.jam_masuk, a.keterangan, a.status_masuk, k.nama_karyawan, c.nama_cabang 
                  FROM absensi a 
                  JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
                  JOIN cabang c ON k.id_cabang = c.id 
                  ORDER BY a.tanggal DESC, a.jam_masuk DESC 
                  LIMIT 5";
$result_aktivitas = $conn->query($sql_aktivitas);

// 4. Data untuk Best Performance (Bulan Ini)
$current_month = date('m');
$current_year = date('Y');
$sql_best = "SELECT 
    k.nama_karyawan,
    c.nama_cabang,
    COUNT(a.id) as total_hadir,
    SUM(TIME_TO_SEC(TIMEDIFF(a.jam_pulang, a.jam_masuk))) as total_detik
FROM karyawan k
JOIN absensi a ON k.id_karyawan = a.id_karyawan
LEFT JOIN cabang c ON k.id_cabang = c.id
WHERE a.keterangan = 'Hadir' 
  AND a.jam_pulang IS NOT NULL 
  AND MONTH(a.tanggal) = '$current_month' 
  AND YEAR(a.tanggal) = '$current_year'
GROUP BY k.id_karyawan, k.nama_karyawan, c.nama_cabang
ORDER BY total_hadir DESC, total_detik DESC
LIMIT 3";
$result_best = $conn->query($sql_best);
$best_performances = [];
if ($result_best) {
    while($row = $result_best->fetch_assoc()) {
        $best_performances[] = $row;
    }
}
?>

<!-- SCROLLABLE CONTENT AREA -->
<div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-8">
    
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-purple-800 via-purple-600 to-orange-500 rounded-2xl p-6 md:p-8 text-white shadow-lg shadow-purple-500/30 relative overflow-hidden">
        <div class="relative z-10">
            <h2 class="text-2xl md:text-3xl font-bold mb-2">Selamat Datang, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</h2>
            <p class="text-brand-100 opacity-90">Berikut adalah ringkasan data absensi hari ini, <?php echo date('F Y'); ?>.</p>
        </div>
        <!-- Efek gambar uang/jam -->
        <div class="absolute right-0 bottom-0 opacity-20 pointer-events-none transform translate-x-4 translate-y-4">
            <i class="fa-solid fa-coins text-8xl md:text-9xl"></i>
        </div>
        <div class="absolute right-20 md:right-32 top-0 opacity-10 pointer-events-none transform -translate-y-4">
            <i class="fa-solid fa-clock text-6xl md:text-7xl"></i>
        </div>
    </div>

    <!-- STATISTIC CARDS GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Card 1 -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-300">
            <div class="w-14 h-14 rounded-full bg-fuchsia-50 dark:bg-fuchsia-900/30 text-fuchsia-600 dark:text-fuchsia-400 flex items-center justify-center text-2xl shrink-0">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Karyawan</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $total_karyawan; ?></p>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-300">
            <div class="w-14 h-14 rounded-full bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl shrink-0">
                <i class="fa-solid fa-building"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Cabang</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $total_cabang; ?></p>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-300">
            <div class="w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl shrink-0">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Hadir Hari Ini</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $hadir_hari_ini; ?></p>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-100 dark:border-slate-700 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-300">
            <div class="w-14 h-14 rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 flex items-center justify-center text-2xl shrink-0">
                <i class="fa-solid fa-user-clock"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Terlambat</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $terlambat_hari_ini; ?></p>
            </div>
        </div>

    </div>

    <!-- CHARTS/CONTENT AREA -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Box 1: Karyawan per Cabang (HTML Bar Chart) -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-bold text-lg text-slate-800 dark:text-white">Karyawan per Cabang</h3>
                <button class="text-slate-400 hover:text-brand-600"><i class="fa-solid fa-ellipsis-vertical"></i></button>
            </div>
            
            <div class="h-64 flex items-end justify-between gap-4 pt-4 border-b border-slate-200 dark:border-slate-700 pb-2">
                <?php 
                $color_index = 0;
                foreach($cabang_data as $cabang): 
                    // Hitung persentase tinggi bar (min 10% agar tetap terlihat)
                    $height_pct = max(10, round(($cabang['jumlah'] / $max_karyawan) * 90)); 
                    $color_class = $bar_colors[$color_index % count($bar_colors)];
                    
                    // Jika data 0, tinggi 0 dan warna pudar
                    if ($cabang['jumlah'] == 0) {
                        $height_pct = 0;
                    }
                ?>
                    <div class="flex-1 flex flex-col justify-end items-center h-full relative">
                        <div class="w-10 md:w-14 <?php echo $color_class; ?> rounded-t-lg relative group transition-all duration-500 shadow-sm" style="height: <?php echo $height_pct; ?>%;">
                            <div class="absolute -top-7 w-full text-center text-xs font-bold text-slate-600 dark:text-slate-300"><?php echo $cabang['jumlah']; ?></div>
                        </div>
                        <div class="absolute -bottom-7 w-[150%] text-center text-xs text-slate-500 truncate" title="<?php echo htmlspecialchars($cabang['nama_cabang']); ?>">
                            <?php 
                            // Persingkat nama cabang jika terlalu panjang
                            $nama = $cabang['nama_cabang'];
                            echo htmlspecialchars(strlen($nama) > 10 ? substr($nama, 0, 10).'..' : $nama); 
                            ?>
                        </div>
                    </div>
                <?php 
                $color_index++;
                endforeach; 
                ?>
                
                <?php if(empty($cabang_data)): ?>
                    <div class="w-full h-full flex items-center justify-center text-slate-400 text-sm">
                        Belum ada data cabang
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Box 2: Aktivitas Terbaru -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-bold text-lg text-slate-800 dark:text-white">Aktivitas Terbaru</h3>
            </div>
            
            <!-- List Activity -->
            <div class="space-y-4">
                <?php if ($result_aktivitas && $result_aktivitas->num_rows > 0): ?>
                    <?php while($act = $result_aktivitas->fetch_assoc()): 
                        // Tentukan pesan dan warna dot
                        $dot_color = 'bg-brand-500'; // Default biru
                        $pesan = '';
                        $waktu = date('d M Y', strtotime($act['tanggal'])) . ', ' . ($act['jam_masuk'] ? date('H:i', strtotime($act['jam_masuk'])) . ' WIB' : '');
                        
                        if (strtolower($act['keterangan']) == 'hadir') {
                            if (strtolower($act['status_masuk']) == 'terlambat') {
                                $dot_color = 'bg-rose-500'; // Merah
                                $pesan = htmlspecialchars($act['nama_karyawan']) . " melakukan absen masuk (Terlambat)";
                            } else {
                                $dot_color = 'bg-emerald-500'; // Hijau
                                $pesan = htmlspecialchars($act['nama_karyawan']) . " melakukan absen masuk (Tepat Waktu)";
                            }
                        } else {
                            $dot_color = 'bg-amber-500'; // Kuning/Orange
                            $pesan = htmlspecialchars($act['nama_karyawan']) . " berstatus: " . htmlspecialchars($act['keterangan']);
                        }
                    ?>
                        <div class="flex items-start gap-4">
                            <div class="w-2 h-2 mt-2 rounded-full <?php echo $dot_color; ?> shrink-0"></div>
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?php echo $pesan; ?></p>
                                <p class="text-xs text-slate-500 mt-1"><?php echo $waktu; ?> - Cabang <?php echo htmlspecialchars($act['nama_cabang']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-8 text-slate-400">
                        <i class="fa-solid fa-clock-rotate-left text-3xl mb-3 opacity-50 block"></i>
                        <p class="text-sm">Belum ada aktivitas hari ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Box 3: Best Performance -->
        <div class="bg-gradient-to-br from-purple-800 via-purple-600 to-orange-500 dark:from-purple-900 dark:via-purple-700 dark:to-orange-600 rounded-2xl border border-purple-400/50 dark:border-purple-700 shadow-lg shadow-purple-500/30 p-6 text-white relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/20 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
            <div class="absolute right-4 bottom-4 opacity-20 transform group-hover:rotate-12 transition-transform duration-500">
                <i class="fa-solid fa-trophy text-6xl"></i>
            </div>
            <div class="relative z-10">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg text-white">Best Performance</h3>
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                        <i class="fa-solid fa-medal text-yellow-200"></i>
                    </div>
                </div>
                
                <?php if (!empty($best_performances)): ?>
                    <div class="space-y-3 mt-2">
                        <?php foreach($best_performances as $index => $best): 
                            $jam = floor($best['total_detik'] / 3600);
                            $menit = floor(($best['total_detik'] % 3600) / 60);
                            $rank = $index + 1;
                            
                            if ($rank == 1):
                        ?>
                            <!-- Juara 1 (Besar) -->
                            <div class="text-center">
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white/20 mb-2 backdrop-blur-sm border-2 border-white/50 shadow-inner">
                                    <span class="text-xl font-bold text-yellow-100">#1</span>
                                </div>
                                <h4 class="text-lg font-bold truncate" title="<?php echo htmlspecialchars($best['nama_karyawan']); ?>"><?php echo htmlspecialchars($best['nama_karyawan']); ?></h4>
                                <p class="text-amber-100 text-xs mb-3"><?php echo htmlspecialchars($best['nama_cabang']); ?></p>
                                
                                <div class="grid grid-cols-2 gap-2 bg-black/10 rounded-xl p-2 backdrop-blur-sm">
                                    <div>
                                        <p class="text-[10px] text-amber-100">Kehadiran</p>
                                        <p class="font-bold text-base"><?php echo $best['total_hadir']; ?> <span class="text-[10px] font-normal">hari</span></p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-amber-100">Durasi</p>
                                        <p class="font-bold text-base"><?php echo $jam; ?>h <?php echo $menit; ?>m</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Juara 2 & 3 (Kecil list) -->
                            <div class="flex items-center justify-between bg-black/10 rounded-xl p-2 backdrop-blur-sm">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full <?php echo $rank == 2 ? 'bg-slate-300/30' : 'bg-amber-700/30'; ?> flex items-center justify-center border border-white/20">
                                        <span class="text-sm font-bold <?php echo $rank == 2 ? 'text-slate-100' : 'text-amber-200'; ?>">#<?php echo $rank; ?></span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-bold truncate" title="<?php echo htmlspecialchars($best['nama_karyawan']); ?>"><?php echo htmlspecialchars($best['nama_karyawan']); ?></h4>
                                        <p class="text-amber-100/80 text-[10px] truncate"><?php echo htmlspecialchars($best['nama_cabang']); ?></p>
                                        <p class="text-amber-100 text-[10px] truncate"><?php echo $best['total_hadir']; ?> hr | <?php echo $jam; ?>h <?php echo $menit; ?>m</p>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <a href="klasemen_performance.php" class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-bold text-white backdrop-blur-sm transition-colors shadow-sm relative z-20">
                            Lihat Semua <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fa-solid fa-medal text-4xl mb-3 opacity-50 block"></i>
                        <p class="text-sm">Belum ada data untuk bulan ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php
include 'admin_footer.php';
?>
