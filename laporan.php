<?php
require 'config.php';
requireAdminOrOwner();

// Ambil data cabang
$cabang_result = $conn->query("SELECT * FROM cabang ORDER BY nama_cabang ASC");
$cabang_list = [];
while ($c = $cabang_result->fetch_assoc()) {
    $cabang_list[] = $c;
}

// Ambil data karyawan
$karyawan_result = $conn->query("SELECT id_karyawan as id, nama_karyawan as nama_lengkap, status FROM karyawan ORDER BY nama_karyawan ASC");
$karyawan_list = [];
while ($k = $karyawan_result->fetch_assoc()) {
    $karyawan_list[] = $k;
}

if (isAdmin()) {
    require 'admin_header.php';
} else {
    require 'owner_header.php';
}
?>

<!-- Konten Utama -->
<div class="flex-1 p-6 md:p-8 bg-slate-50 dark:bg-slate-900">
    <!-- Header Halaman -->
    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-slate-800 dark:text-white mb-2">Pusat Laporan</h1>
            <p class="text-sm md:text-base text-slate-500 dark:text-slate-400">Generate dan unduh laporan Karyawan, Absensi, dan Gaji.</p>
        </div>
    </div>

    <!-- Tab Laporan Layout -->
    <div class="mb-6 flex justify-start overflow-x-auto no-scrollbar pb-2">
        <div class="relative bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-xl inline-flex min-w-max" id="laporanTabsWrapper">
            <!-- Sliding Indicator -->
            <div id="tab-slider" class="absolute top-1.5 bottom-1.5 left-1.5 bg-brand-600 dark:bg-brand-500 rounded-lg shadow-md transition-all duration-300 ease-out z-0"></div>
            
            <ul class="flex relative z-10" id="laporanTabs" role="tablist">
                <li role="presentation" class="flex-1">
                    <button class="w-full inline-flex justify-center items-center px-6 py-2.5 rounded-lg transition-colors text-sm font-bold text-white group tab-btn whitespace-nowrap" id="karyawan-tab" data-tabs-target="#karyawan" type="button" role="tab" aria-controls="karyawan" aria-selected="true" onclick="switchTab('karyawan', this)">
                        <i class="fa-solid fa-users mr-2"></i> Laporan Karyawan
                    </button>
                </li>
                <li role="presentation" class="flex-1">
                    <button class="w-full inline-flex justify-center items-center px-6 py-2.5 rounded-lg transition-colors text-sm font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 group tab-btn whitespace-nowrap" id="absensi-tab" data-tabs-target="#absensi" type="button" role="tab" aria-controls="absensi" aria-selected="false" onclick="switchTab('absensi', this)">
                        <i class="fa-solid fa-calendar-check mr-2"></i> Laporan Absensi
                    </button>
                </li>
                <li role="presentation" class="flex-1">
                    <button class="w-full inline-flex justify-center items-center px-6 py-2.5 rounded-lg transition-colors text-sm font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 group tab-btn whitespace-nowrap" id="gaji-tab" data-tabs-target="#gaji" type="button" role="tab" aria-controls="gaji" aria-selected="false" onclick="switchTab('gaji', this)">
                        <i class="fa-solid fa-money-check-dollar mr-2"></i> Laporan Gaji
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Konten Tab -->
    <div id="laporanTabContent" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 lg:p-8 max-w-4xl mx-auto md:mx-0 md:max-w-none">
        
        <!-- Tab 1: Karyawan -->
        <div class="tab-pane" id="karyawan" role="tabpanel" aria-labelledby="karyawan-tab">
            <div class="mb-6">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-users text-brand-500"></i> Data Karyawan
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Export data master karyawan berdasarkan divisi.</p>
            </div>
            
            <form action="laporan_karyawan_print.php" method="GET" target="_blank" class="space-y-5" id="formLapKaryawan" onsubmit="return validateLaporanKaryawan()">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jenis Laporan</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="rekap" checked class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleKaryawanFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Rekap Data (Tabel)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="biodata" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleKaryawanFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Biodata Lengkap (Cetak per Karyawan)</span>
                        </label>
                    </div>
                </div>

                <div id="filterRekapKaryawan" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Cabang</label>
                        <div class="relative w-full">
                            <select name="cabang_id" class="appearance-none w-full px-4 py-2.5 pr-10 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer shadow-sm">
                                <option value="all">Semua Cabang</option>
                                <?php foreach ($cabang_list as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nama_cabang']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 dark:text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Status Karyawan</label>
                        <div class="relative w-full">
                            <select name="status" class="appearance-none w-full px-4 py-2.5 pr-10 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer shadow-sm">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Resign</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 dark:text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Karyawan untuk Biodata -->
                <div id="filterBiodataKaryawan" style="display:none;" class="p-4 bg-slate-50 dark:bg-slate-900/30 rounded-xl border border-slate-100 dark:border-slate-700/50 mt-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Pilih Karyawan Target</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-400"><i class="fa-solid fa-search text-sm"></i></span>
                        <input type="hidden" name="user_id" id="karyawan_selected_user_id" value="">
                        <input type="text" id="karyawan_selected_user_name" readonly placeholder="- Pilih Karyawan -" onclick="openKaryawanModal('karyawan')" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer" autocomplete="off">
                    </div>
                </div>
                
                <div class="pt-6 mt-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-3">
                    <button type="submit" name="action" value="preview" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors shadow-sm shadow-brand-500/30">
                        <i class="fa-solid fa-file-magnifying-glass"></i> <span>Preview</span>
                    </button>
                    <button type="submit" name="action" value="print" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 border border-transparent rounded-xl font-medium transition-colors shadow-sm">
                        <i class="fa-solid fa-print"></i> <span>Print / PDF</span>
                    </button>
                    <button type="submit" name="action" value="image" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 border border-transparent rounded-xl font-medium transition-colors shadow-sm">
                        <i class="fa-solid fa-download"></i> <span>Unduh Jpeg/PNG</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab 2: Absensi -->
        <div class="hidden tab-pane" id="absensi" role="tabpanel" aria-labelledby="absensi-tab">
            <div class="mb-6">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-brand-500"></i> Data Absensi
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Export log kehadiran atau statistik absensi.</p>
            </div>
            
            <form action="laporan_absensi_print.php" method="GET" target="_blank" class="space-y-5" id="formLapAbsensi" onsubmit="return validateLaporanAbsensi()">
                
                <div id="filterTanggalAbsensi" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-01'); ?>" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?php echo date('Y-m-t'); ?>" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                </div>

                <div id="filterTahunAbsensi" style="display:none;" class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Pilih Tahun</label>
                        <select name="tahun" class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                            <?php 
                            $current_year = date('Y');
                            for($y = $current_year; $y >= 2020; $y--) {
                                echo "<option value=\"$y\">$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jenis Laporan</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="log" checked class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleAbsensiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Log Harian</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="statistik" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleAbsensiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Statistik Cabang</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="statistik_karyawan" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleAbsensiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Statistik Personal</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="juara_tahunan" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleAbsensiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Rekap Best Performance Tahunan</span>
                        </label>
                    </div>
                </div>

                <!-- Filter Cabang Absensi -->
                <div id="filterDivisiAbsensi" class="p-4 bg-slate-50 dark:bg-slate-900/30 rounded-xl border border-slate-100 dark:border-slate-700/50 mt-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Cabang</label>
                    <div class="relative w-full md:max-w-sm">
                        <select name="cabang_id" class="appearance-none w-full px-4 py-2.5 pr-10 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer shadow-sm">
                            <option value="all">Semua Cabang</option>
                            <?php foreach ($cabang_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nama_cabang']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- Filter Karyawan Absensi -->
                <div id="filterKaryawanAbsensi" style="display:none;" class="p-4 bg-slate-50 dark:bg-slate-900/30 rounded-xl border border-slate-100 dark:border-slate-700/50 mt-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Pilih Karyawan Target</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-400"><i class="fa-solid fa-search text-sm"></i></span>
                        <input type="hidden" name="user_id" id="absensi_selected_user_id" value="">
                        <input type="text" id="absensi_selected_user_name" readonly placeholder="- Pilih Karyawan -" onclick="openKaryawanModal('absensi')" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer" autocomplete="off">
                    </div>
                </div>
                
                <div class="pt-6 mt-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-3">
                    <button type="submit" name="action" value="preview" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors shadow-sm shadow-brand-500/30">
                        <i class="fa-solid fa-file-magnifying-glass"></i> <span>Preview</span>
                    </button>
                    <button type="submit" name="action" value="print" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 border border-transparent rounded-xl font-medium transition-colors shadow-sm">
                        <i class="fa-solid fa-print"></i> <span>Print / PDF</span>
                    </button>
                    <button type="submit" name="action" value="image" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 border border-transparent rounded-xl font-medium transition-colors shadow-sm">
                        <i class="fa-solid fa-download"></i> <span>Unduh Jpeg/PNG</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab 3: Gaji -->
        <div class="hidden tab-pane" id="gaji" role="tabpanel" aria-labelledby="gaji-tab">
            <div class="mb-6">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-money-check-dollar text-brand-500"></i> Data Gaji & Slip
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Export rekap gaji dan cetak slip gaji karyawan.</p>
            </div>
            
            <form action="laporan_gaji_print.php" method="GET" target="_blank" class="space-y-5" id="formLapGaji" onsubmit="return validateLaporanGaji()">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Dari Tanggal</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-01'); ?>" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?php echo date('Y-m-t'); ?>" required class="w-full px-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jenis Laporan</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="lap_gaji_divisi" checked class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleGajiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Rekap Pengeluaran (per Nama & Cabang)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="rekap_gaji_divisi" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleGajiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Rekap Pengeluaran (per Cabang)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="per_karyawan" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleGajiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Rekap Pengeluaran (Personal)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="cetak_slip_divisi" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleGajiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Cetak Slip Gaji (Cabang)</span>
                        </label>
                        <label class="flex items-center gap-3 p-3 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors bg-white dark:bg-slate-800 shadow-sm has-[:checked]:bg-brand-50 has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-900/20">
                            <input type="radio" name="tipe" value="cetak_slip_batch" class="text-brand-500 focus:ring-brand-500 w-4 h-4" onchange="toggleGajiFilters()">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Cetak Slip Gaji (Personal)</span>
                        </label>
                    </div>
                </div>

                <!-- Filter Dinamis -->
                <div id="filterDivisi" style="display:none;" class="p-4 bg-slate-50 dark:bg-slate-900/30 rounded-xl border border-slate-100 dark:border-slate-700/50 mt-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Pilih Cabang</label>
                    <div class="relative w-full md:max-w-sm">
                        <select name="cabang_id" class="appearance-none w-full px-4 py-2.5 pr-10 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer shadow-sm">
                            <option value="all">Semua Cabang</option>
                            <?php foreach ($cabang_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nama_cabang']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                <div id="filterKaryawan" style="display:none;" class="p-4 bg-slate-50 dark:bg-slate-900/30 rounded-xl border border-slate-100 dark:border-slate-700/50 mt-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Pilih Karyawan Target</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-slate-400"><i class="fa-solid fa-search text-sm"></i></span>
                        <input type="hidden" name="user_id" id="selected_user_id" value="">
                        <input type="text" id="selected_user_name" readonly placeholder="- Pilih Karyawan -" onclick="openKaryawanModal('gaji')" class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer" autocomplete="off">
                    </div>
                </div>
                
                <div class="pt-6 mt-6 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row gap-3">
                    <button type="submit" name="action" value="preview" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors shadow-sm shadow-brand-500/30">
                        <i class="fa-solid fa-file-magnifying-glass"></i> <span>Preview</span>
                    </button>
                    <button type="submit" name="action" value="print" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 border border-transparent rounded-xl font-medium transition-colors shadow-sm">
                        <i class="fa-solid fa-print"></i> <span>Print / PDF</span>
                    </button>
                    <button type="submit" name="action" value="image" class="flex-1 flex items-center justify-center gap-2 px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 border border-transparent rounded-xl font-medium transition-colors shadow-sm">
                        <i class="fa-solid fa-download"></i> <span>Unduh Jpeg/PNG</span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- MODAL: PILIH KARYAWAN -->
<div id="modal-pilih-karyawan" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden flex flex-col max-h-[90vh]">
        
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-users text-brand-500"></i> Cari Karyawan
            </h3>
            <button type="button" onclick="closeModal('modal-pilih-karyawan')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        
        <div class="p-6 flex flex-col gap-4 flex-1 overflow-hidden min-h-[300px]">
            <!-- Search -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                </div>
                <input type="text" id="searchKaryawan" onkeyup="filterKaryawanTable()" class="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-xl text-sm bg-slate-50 dark:bg-slate-900/50 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:text-white" placeholder="Ketik nama atau ID karyawan untuk mencari...">
            </div>

            <!-- Filter Status di Modal -->
            <div class="bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 w-full sm:w-[320px] mx-auto sm:mx-0">
                <div class="relative flex w-full">
                    <!-- Sliding Background Pill -->
                    <div id="filterKaryawanPill" class="absolute inset-y-0 left-0 w-1/2 bg-brand-600 rounded-lg shadow-md shadow-brand-500/30 border border-brand-600 transition-transform duration-300 ease-in-out" style="transform: translateX(0);"></div>
                    
                    <button type="button" id="btnFilterAktif" onclick="setKaryawanFilter('aktif')" class="relative z-10 flex-1 py-1.5 text-center text-sm text-white font-bold transition-colors duration-300">Karyawan Aktif</button>
                    <button type="button" id="btnFilterResign" onclick="setKaryawanFilter('nonaktif')" class="relative z-10 flex-1 py-1.5 text-center text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300 font-semibold transition-colors duration-300">Karyawan Resign</button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-xl flex-1 relative">
                <table class="w-full text-left border-collapse" id="tableKaryawanModal">
                    <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900 z-10 shadow-sm">
                        <tr class="text-slate-600 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <th class="px-4 py-3 font-semibold bg-slate-100 dark:bg-slate-900">ID Karyawan</th>
                            <th class="px-4 py-3 font-semibold bg-slate-100 dark:bg-slate-900">Nama Lengkap</th>
                            <th class="px-4 py-3 font-semibold text-center w-24 bg-slate-100 dark:bg-slate-900">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php foreach ($karyawan_list as $k): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors" data-status="<?php echo htmlspecialchars($k['status']); ?>">
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400 font-mono"><?php echo htmlspecialchars($k['id']); ?></td>
                            <td class="px-4 py-3 text-sm text-slate-800 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($k['nama_lengkap']); ?></td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" onclick="selectKaryawan('<?php echo addslashes($k['id']); ?>', '<?php echo addslashes($k['nama_lengkap']); ?>')" class="px-3 py-1.5 bg-brand-50 text-brand-600 hover:bg-brand-100 dark:bg-brand-900/30 dark:text-brand-400 dark:hover:bg-brand-900/50 rounded-lg text-xs font-semibold transition-colors border border-brand-200 dark:border-brand-800">
                                    <i class="fa-solid fa-check mr-1"></i> Pilih
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700 flex justify-end bg-slate-50 dark:bg-slate-900/50">
            <button type="button" onclick="closeModal('modal-pilih-karyawan')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium text-sm">Batal</button>
        </div>
    </div>
</div>

<script>
let currentKaryawanTarget = 'gaji';

// Tab Switching Logic
function switchTab(tabId, btn = null) {
    if (!btn) btn = document.getElementById(tabId + '-tab');
    
    document.querySelectorAll('.tab-pane').forEach(el => {
        el.classList.add('hidden');
    });
    document.getElementById(tabId).classList.remove('hidden');

    document.querySelectorAll('[role="tab"]').forEach(el => {
        el.classList.remove('text-white', 'font-bold');
        el.classList.add('text-slate-500', 'dark:text-slate-400', 'font-semibold', 'hover:text-slate-700', 'dark:hover:text-slate-300');
        el.setAttribute('aria-selected', 'false');
    });

    btn.classList.remove('text-slate-500', 'dark:text-slate-400', 'font-semibold', 'hover:text-slate-700', 'dark:hover:text-slate-300');
    btn.classList.add('text-white', 'font-bold');
    btn.setAttribute('aria-selected', 'true');
    
    const slider = document.getElementById('tab-slider');
    const wrapper = document.getElementById('laporanTabsWrapper');
    if (slider && btn && wrapper) {
        // Position relative to the wrapper
        const leftPos = btn.offsetLeft;
        const width = btn.offsetWidth;
        slider.style.width = width + 'px';
        slider.style.left = leftPos + 'px';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const activeBtn = document.querySelector('[role="tab"][aria-selected="true"]');
        if (activeBtn) {
            const slider = document.getElementById('tab-slider');
            slider.style.width = activeBtn.offsetWidth + 'px';
            slider.style.left = activeBtn.offsetLeft + 'px';
        }
    }, 100); // Small delay to let the DOM paint completely
});

function toggleAbsensiFilters() {
    const tipe = document.querySelector('#formLapAbsensi input[name="tipe"]:checked').value;
    const fDiv = document.getElementById('filterDivisiAbsensi');
    const fKar = document.getElementById('filterKaryawanAbsensi');
    const fTgl = document.getElementById('filterTanggalAbsensi');
    const fThn = document.getElementById('filterTahunAbsensi');
    
    if (tipe === 'statistik_karyawan') {
        fDiv.style.display = 'none';
        fKar.style.display = 'block';
        fTgl.style.display = 'grid';
        fThn.style.display = 'none';
    } else if (tipe === 'juara_tahunan') {
        fDiv.style.display = 'block';
        fKar.style.display = 'none';
        fTgl.style.display = 'none';
        fThn.style.display = 'grid';
    } else {
        fDiv.style.display = 'block';
        fKar.style.display = 'none';
        fTgl.style.display = 'grid';
        fThn.style.display = 'none';
    }
}

function toggleKaryawanFilters() {
    const tipe = document.querySelector('#formLapKaryawan input[name="tipe"]:checked');
    const formLapKaryawan = document.getElementById('formLapKaryawan');
    const fRekap = document.getElementById('filterRekapKaryawan');
    const fBiodata = document.getElementById('filterBiodataKaryawan');

    if (tipe && tipe.value === 'biodata') {
        formLapKaryawan.action = 'laporan_biodata_print.php';
        fRekap.style.display = 'none';
        fBiodata.style.display = 'block';
    } else {
        formLapKaryawan.action = 'laporan_karyawan_print.php';
        fRekap.style.display = 'grid';
        fBiodata.style.display = 'none';
    }
}

function toggleGajiFilters() {
    const tipe = document.querySelector('#formLapGaji input[name="tipe"]:checked').value;
    const fDiv = document.getElementById('filterDivisi');
    const fKar = document.getElementById('filterKaryawan');
    const formLapGaji = document.getElementById('formLapGaji');
    
    if (tipe === 'lap_gaji_divisi' || tipe === 'rekap_gaji_divisi') {
        fDiv.style.display = 'block';
        fKar.style.display = 'none';
        formLapGaji.action = 'laporan_gaji_print.php';
    } else if (tipe === 'per_karyawan') {
        fDiv.style.display = 'none';
        fKar.style.display = 'block';
        formLapGaji.action = 'laporan_gaji_print.php';
    } else if (tipe === 'cetak_slip_batch') {
        fDiv.style.display = 'none';
        fKar.style.display = 'block';
        formLapGaji.action = 'laporan_slip_batch.php';
    } else if (tipe === 'cetak_slip_divisi') {
        fDiv.style.display = 'block';
        fKar.style.display = 'none';
        formLapGaji.action = 'laporan_slip_batch.php';
    }
}

// Popup Karyawan Script
let currentKaryawanStatusFilter = 'aktif';

function setKaryawanFilter(status) {
    currentKaryawanStatusFilter = status;
    
    const btnAktif = document.getElementById('btnFilterAktif');
    const btnResign = document.getElementById('btnFilterResign');
    const pill = document.getElementById('filterKaryawanPill');
    
    if (status === 'aktif') {
        pill.style.transform = 'translateX(0)';
        btnAktif.className = 'relative z-10 flex-1 py-1.5 text-center text-sm text-white font-bold transition-colors duration-300';
        btnResign.className = 'relative z-10 flex-1 py-1.5 text-center text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300 font-semibold transition-colors duration-300';
    } else {
        pill.style.transform = 'translateX(100%)';
        btnResign.className = 'relative z-10 flex-1 py-1.5 text-center text-sm text-white font-bold transition-colors duration-300';
        btnAktif.className = 'relative z-10 flex-1 py-1.5 text-center text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300 font-semibold transition-colors duration-300';
    }
    
    filterKaryawanTable();
}

function openKaryawanModal(target) {
    currentKaryawanTarget = target;
    
    // Reset modal state
    document.getElementById('searchKaryawan').value = '';
    setKaryawanFilter('aktif');
    
    openModal('modal-pilih-karyawan');
    setTimeout(() => {
        document.getElementById('searchKaryawan').focus();
    }, 100);
}

function selectKaryawan(id, nama) {
    if (currentKaryawanTarget === 'gaji') {
        document.getElementById('selected_user_id').value = id;
        document.getElementById('selected_user_name').value = nama;
    } else if (currentKaryawanTarget === 'absensi') {
        document.getElementById('absensi_selected_user_id').value = id;
        document.getElementById('absensi_selected_user_name').value = nama;
    } else if (currentKaryawanTarget === 'karyawan') {
        document.getElementById('karyawan_selected_user_id').value = id;
        document.getElementById('karyawan_selected_user_name').value = nama;
    }
    closeModal('modal-pilih-karyawan');
}

function filterKaryawanTable() {
    let input = document.getElementById('searchKaryawan');
    let filter = input.value.toLowerCase();
    let table = document.getElementById('tableKaryawanModal');
    let trs = table.getElementsByTagName('tr');

    for (let i = 1; i < trs.length; i++) { // Skip thead
        let tdId = trs[i].getElementsByTagName('td')[0];
        let tdNama = trs[i].getElementsByTagName('td')[1];
        let rowStatus = trs[i].getAttribute('data-status');
        
        if (tdId || tdNama) {
            let txtId = tdId.textContent || tdId.innerText;
            let txtNama = tdNama.textContent || tdNama.innerText;
            
            let matchesSearch = (txtId.toLowerCase().indexOf(filter) > -1 || txtNama.toLowerCase().indexOf(filter) > -1);
            let matchesStatus = (rowStatus === currentKaryawanStatusFilter);
            
            if (matchesSearch && matchesStatus) {
                trs[i].style.display = '';
            } else {
                trs[i].style.display = 'none';
            }
        }
    }
}

// Validasi Form Karyawan
function validateLaporanKaryawan() {
    const tipeElement = document.querySelector('#formLapKaryawan input[name="tipe"]:checked');
    if (tipeElement && tipeElement.value === 'biodata') {
        const userId = document.getElementById('karyawan_selected_user_id').value;
        if (!userId) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Harap pilih karyawan terlebih dahulu pada pencarian pop-up!',
                confirmButtonColor: '#3085d6'
            });
            return false;
        }
    }
    return true;
}

// Validasi Form Absensi
function validateLaporanAbsensi() {
    const tipe = document.querySelector('#formLapAbsensi input[name="tipe"]:checked').value;
    if (tipe === 'statistik_karyawan') {
        const userId = document.getElementById('absensi_selected_user_id').value;
        if (!userId) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Harap pilih karyawan terlebih dahulu pada pencarian pop-up!',
                confirmButtonColor: '#3085d6'
            });
            return false; // Mencegah form di-submit
        }
    }
    return true;
}

// Validasi Form Gaji
function validateLaporanGaji() {
    const tipe = document.querySelector('#formLapGaji input[name="tipe"]:checked').value;
    if (tipe === 'per_karyawan' || tipe === 'cetak_slip_batch') {
        const userId = document.getElementById('selected_user_id').value;
        if (!userId) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Harap pilih karyawan terlebih dahulu pada pencarian pop-up!',
                confirmButtonColor: '#3085d6'
            });
            return false; // Mencegah form di-submit
        }
    }
    return true;
}

// Init on load
document.addEventListener('DOMContentLoaded', () => {
    toggleGajiFilters();
    toggleAbsensiFilters();
    toggleKaryawanFilters();
});
</script>

<?php 
if (isAdmin()) {
    require 'admin_footer.php'; 
} else {
    require 'owner_footer.php';
}
?>

