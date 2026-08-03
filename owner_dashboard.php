<?php
require 'owner_header.php';

// Ambil statistik umum
$total_karyawan = $conn->query("SELECT COUNT(*) as total FROM karyawan")->fetch_assoc()['total'];
$total_cabang = $conn->query("SELECT COUNT(*) as total FROM cabang")->fetch_assoc()['total'];

// Statistik absensi hari ini
$today = date('Y-m-d');
$stats_today = [
    'hadir' => 0,
    'off' => 0,
    'sakit' => 0,
    'cuti' => 0,
    'alpha' => 0,
    'belum_absen' => 0
];

$sql_today = "SELECT keterangan, COUNT(*) as jumlah 
            FROM absensi 
            WHERE tanggal = ? 
            GROUP BY keterangan";
$stmt = $conn->prepare($sql_today);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $stats_today[strtolower($row['keterangan'])] = $row['jumlah'];
}

// Hitung yang belum absen
$total_absen_today = array_sum($stats_today);
$stats_today['belum_absen'] = $total_karyawan - $total_absen_today;

// Data untuk chart - 7 hari terakhir
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('d M', strtotime($date));
    
    $sql_chart = "SELECT COUNT(*) as total 
                FROM absensi 
                WHERE tanggal = ? AND keterangan = 'Hadir'";
    $stmt_chart = $conn->prepare($sql_chart);
    $stmt_chart->bind_param("s", $date);
    $stmt_chart->execute();
    $result_chart = $stmt_chart->get_result();
    $total = $result_chart->fetch_assoc()['total'];
    
    $chart_data[] = [
        'label' => $label,
        'value' => $total
    ];
}

// Data untuk Best Performance (Bulan Ini)
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

<!-- Header Section (Welcome Banner) -->
<div class="bg-gradient-to-r from-purple-800 via-purple-600 to-orange-500 rounded-2xl p-6 md:p-8 text-white shadow-lg shadow-purple-500/30 relative overflow-hidden mb-8">
    <div class="relative z-10">
        <h2 class="text-2xl md:text-3xl font-bold mb-2">Selamat Datang, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Owner'); ?>!</h2>
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

<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Karyawan -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 shadow-lg shadow-purple-500/20 text-white relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
        <div class="relative z-10 flex justify-between items-start">
            <div>
                <p class="text-white/80 text-sm font-medium mb-1">Total Karyawan</p>
                <h3 class="text-4xl font-bold"><?php echo $total_karyawan; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-xl backdrop-blur-sm">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>

    <!-- Total Cabang -->
    <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 shadow-lg shadow-emerald-500/20 text-white relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
        <div class="relative z-10 flex justify-between items-start">
            <div>
                <p class="text-white/80 text-sm font-medium mb-1">Total Cabang</p>
                <h3 class="text-4xl font-bold"><?php echo $total_cabang; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-xl backdrop-blur-sm">
                <i class="fa-solid fa-building"></i>
            </div>
        </div>
    </div>

    <!-- Hadir Hari Ini -->
    <div class="bg-gradient-to-br from-fuchsia-500 to-cyan-600 rounded-2xl p-6 shadow-lg shadow-fuchsia-500/20 text-white relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
        <div class="relative z-10 flex justify-between items-start">
            <div>
                <p class="text-white/80 text-sm font-medium mb-1">Hadir Hari Ini</p>
                <h3 class="text-4xl font-bold"><?php echo $stats_today['hadir']; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-xl backdrop-blur-sm">
                <i class="fa-solid fa-user-check"></i>
            </div>
        </div>
    </div>

    <!-- Belum Absen -->
    <div class="bg-gradient-to-br from-rose-500 to-pink-600 rounded-2xl p-6 shadow-lg shadow-rose-500/20 text-white relative overflow-hidden group">
        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl group-hover:scale-150 transition-transform duration-500"></div>
        <div class="relative z-10 flex justify-between items-start">
            <div>
                <p class="text-white/80 text-sm font-medium mb-1">Belum Absen</p>
                <h3 class="text-4xl font-bold"><?php echo $stats_today['belum_absen']; ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-xl backdrop-blur-sm">
                <i class="fa-solid fa-user-clock"></i>
            </div>
        </div>
    </div>
</div>

<!-- Bagian Bawah: Chart, Status, & Best Performance -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
    <!-- Chart Section (Lebar 2 kolom) -->
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-brand-500"></i>
                Trend Kehadiran (7 Hari)
            </h3>
        </div>
        <div class="relative h-72 w-full">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>

    <!-- Status Kehadiran Hari Ini -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6 flex items-center gap-2">
            <i class="fa-solid fa-chart-pie text-brand-500"></i>
            Status Hari Ini
        </h3>
        
        <div class="grid grid-cols-2 gap-4 flex-1 content-start">
            <!-- Hadir -->
            <div class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-xl border border-emerald-100 dark:border-emerald-800/30 flex flex-col items-center justify-center text-center transition-transform hover:scale-105">
                <i class="fa-solid fa-circle-check text-emerald-500 text-2xl mb-2"></i>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mb-1"><?php echo $stats_today['hadir']; ?></span>
                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Hadir</span>
            </div>
            
            <!-- OFF -->
            <div class="bg-slate-50 dark:bg-slate-700/50 p-4 rounded-xl border border-slate-200 dark:border-slate-600/50 flex flex-col items-center justify-center text-center transition-transform hover:scale-105">
                <i class="fa-solid fa-calendar-xmark text-slate-400 dark:text-slate-500 text-2xl mb-2"></i>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mb-1"><?php echo $stats_today['off']; ?></span>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">OFF</span>
            </div>
            
            <!-- Sakit -->
            <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-xl border border-amber-100 dark:border-amber-800/30 flex flex-col items-center justify-center text-center transition-transform hover:scale-105">
                <i class="fa-solid fa-bed-pulse text-amber-500 text-2xl mb-2"></i>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mb-1"><?php echo $stats_today['sakit']; ?></span>
                <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wide">Sakit</span>
            </div>
            
            <!-- Cuti -->
            <div class="bg-fuchsia-50 dark:bg-fuchsia-900/20 p-4 rounded-xl border border-fuchsia-100 dark:border-fuchsia-800/30 flex flex-col items-center justify-center text-center transition-transform hover:scale-105">
                <i class="fa-solid fa-plane-departure text-fuchsia-500 text-2xl mb-2"></i>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mb-1"><?php echo $stats_today['cuti']; ?></span>
                <span class="text-xs font-semibold text-fuchsia-600 dark:text-fuchsia-400 uppercase tracking-wide">Cuti</span>
            </div>

            <!-- Alpha -->
            <div class="bg-rose-50 dark:bg-rose-900/20 p-4 rounded-xl border border-rose-100 dark:border-rose-800/30 flex flex-col items-center justify-center text-center transition-transform hover:scale-105 col-span-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-500 text-2xl mb-2"></i>
                <span class="text-2xl font-bold text-slate-800 dark:text-white mb-1"><?php echo $stats_today['alpha']; ?></span>
                <span class="text-xs font-semibold text-rose-600 dark:text-rose-400 uppercase tracking-wide">Alpha</span>
            </div>
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
                    <div class="mt-4 flex justify-end">
                        <a href="klasemen_performance.php" class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-bold text-white backdrop-blur-sm transition-colors shadow-sm relative z-20">
                            Lihat Semua <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo json_encode($chart_data); ?>;
    const labels = chartData.map(d => d.label);
    const values = chartData.map(d => d.value);
    
    // Check initial dark mode
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(14, 165, 233, 0.5)'); // brand-500
    gradient.addColorStop(1, 'rgba(14, 165, 233, 0.0)');

    const attendanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Kehadiran',
                data: values,
                borderColor: '#d946ef', // brand-500
                backgroundColor: gradient,
                borderWidth: 3,
                tension: 0.4, // Smooth curves
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#d946ef',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: isDark ? '#1e293b' : '#fff',
                    titleColor: isDark ? '#f8fafc' : '#0f172a',
                    bodyColor: isDark ? '#cbd5e1' : '#475569',
                    borderColor: isDark ? '#334155' : '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return `${context.parsed.y} Karyawan`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: textColor,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11
                        }
                    },
                    grid: {
                        color: gridColor,
                        drawBorder: false
                    },
                    border: { display: false }
                },
                x: {
                    ticks: {
                        color: textColor,
                        font: {
                            family: "'Inter', sans-serif",
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    },
                    border: { display: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });

    // Handle dark mode toggle for Chart.js
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                const isDarkNow = document.documentElement.classList.contains('dark');
                const newGridColor = isDarkNow ? 'rgba(148, 163, 184, 0.1)' : 'rgba(148, 163, 184, 0.2)';
                const newTextColor = isDarkNow ? '#94a3b8' : '#64748b';
                
                attendanceChart.options.scales.y.grid.color = newGridColor;
                attendanceChart.options.scales.y.ticks.color = newTextColor;
                attendanceChart.options.scales.x.ticks.color = newTextColor;
                
                attendanceChart.options.plugins.tooltip.backgroundColor = isDarkNow ? '#1e293b' : '#fff';
                attendanceChart.options.plugins.tooltip.titleColor = isDarkNow ? '#f8fafc' : '#0f172a';
                attendanceChart.options.plugins.tooltip.bodyColor = isDarkNow ? '#cbd5e1' : '#475569';
                attendanceChart.options.plugins.tooltip.borderColor = isDarkNow ? '#334155' : '#e2e8f0';
                
                attendanceChart.update();
            }
        });
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class']
    });
});
</script>

<?php require 'owner_footer.php'; ?>
