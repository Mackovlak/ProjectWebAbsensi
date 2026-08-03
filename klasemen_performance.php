<?php
require 'config.php';

// Cek role user
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
if ($role == 'admin') {
    include 'admin_header.php';
} elseif ($role == 'owner') {
    include 'owner_header.php';
} else {
    // Jika role bukan admin/owner, arahkan ke dashboard staff
    header("Location: staff_dashboard.php");
    exit();
}

// Data untuk Klasemen Performance
$current_month = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$current_year = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$nama_bulan_arr = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$bulan_nama = $nama_bulan_arr[(int)$current_month];
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
ORDER BY total_hadir DESC, total_detik DESC";
$result_best = $conn->query($sql_best);
$klasemen = [];
if ($result_best) {
    while($row = $result_best->fetch_assoc()) {
        $klasemen[] = $row;
    }
}
?>

<!-- DataTables CSS untuk Tailwind -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
<!-- jQuery & DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>

<style>
/* Override DataTables length menu styling for a cleaner look */
.dataTables_length select {
    padding-top: 0.25rem !important;
    padding-bottom: 0.25rem !important;
    padding-left: 0.75rem !important;
    padding-right: 2rem !important;
    font-size: 0.875rem !important;
    line-height: 1.25rem !important;
    border-radius: 0.5rem !important;
    border-color: #e2e8f0 !important;
    background-color: #ffffff !important;
    color: #475569 !important;
    min-width: 4rem !important;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
}
.dark .dataTables_length select {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #cbd5e1 !important;
}
.dataTables_length label, .dataTables_filter label {
    font-size: 0.875rem !important;
    color: #64748b !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}
.dark .dataTables_length label, .dark .dataTables_filter label {
    color: #94a3b8 !important;
}
.dataTables_filter input {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
    border-radius: 0.5rem !important;
    border-color: #e2e8f0 !important;
    background-color: #ffffff !important;
}
.dark .dataTables_filter input {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #cbd5e1 !important;
}

/* Custom Responsive DataTables Fixes */
@media (max-width: 640px) {
    /* Ubah pembungkus grid/flex bawaan DataTables menjadi bertumpuk */
    .dataTables_wrapper > div.grid,
    .dataTables_wrapper > div.flex {
        display: flex !important;
        flex-direction: column !important;
        gap: 1rem !important;
    }
    .dataTables_wrapper > div > div {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Perbaikan Filter/Pencarian */
    .dataTables_wrapper .dataTables_filter {
        text-align: left !important;
        margin-top: 0.5rem !important;
    }
    .dataTables_wrapper .dataTables_filter label {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        width: 100% !important;
    }
    .dataTables_wrapper .dataTables_filter input {
        width: 100% !important;
        margin-left: 0 !important;
        margin-top: 0.5rem !important;
    }

    /* Hilangkan teks info "Menampilkan..." di mobile */
    .dataTables_wrapper .dataTables_info {
        display: none !important;
    }

    /* Perbaikan Pagination */
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 1rem !important;
        display: flex !important;
        justify-content: center !important;
        width: 100% !important;
    }
    .dataTables_wrapper .dataTables_paginate ul.pagination {
        flex-wrap: wrap !important;
        justify-content: center !important;
        gap: 0.25rem !important;
    }
    
    /* Ensure table can scroll */
    .dataTables_wrapper .overflow-x-auto {
        width: 100% !important;
    }
}
</style>

<div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <i class="fa-solid fa-trophy text-amber-500"></i>
            Klasemen Best Performance (<?php echo $bulan_nama . ' ' . $current_year; ?>)
        </h2>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="" class="flex items-center gap-2">
                <select name="bulan" class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl text-sm outline-none focus:border-brand-500">
                    <?php 
                    foreach($nama_bulan_arr as $num => $name) {
                        $sel = ($num == $current_month) ? 'selected' : '';
                        echo "<option value=\"$num\" $sel>$name</option>";
                    }
                    ?>
                </select>
                <select name="tahun" class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl text-sm outline-none focus:border-brand-500">
                    <?php 
                    for($y = date('Y'); $y >= 2020; $y--) {
                        $sel = ($y == $current_year) ? 'selected' : '';
                        echo "<option value=\"$y\" $sel>$y</option>";
                    }
                    ?>
                </select>
                <button type="submit" class="px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-medium transition-colors"><i class="fa-solid fa-filter"></i></button>
            </form>
            <a href="<?php echo $role == 'admin' ? 'admin_dashboard.php' : 'owner_dashboard.php'; ?>" class="px-4 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl transition-colors text-sm font-medium inline-flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Tabel DataTables -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6">
            <table id="tabelKlasemen" class="w-full text-left border-collapse whitespace-nowrap opacity-0 transition-opacity duration-500">
                <thead>
                    <tr>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400 text-center">Peringkat</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400">Nama Karyawan</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400">Cabang</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400 text-center">Kehadiran (Hari)</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400 text-center">Durasi Jam Kerja</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php 
                    $rank = 1;
                    foreach($klasemen as $k): 
                        $jam = floor($k['total_detik'] / 3600);
                        $menit = floor(($k['total_detik'] % 3600) / 60);
                        
                        // Styling Peringkat Top 3
                        $rowClass = '';
                        $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold">' . $rank . '</span>';
                        
                        if ($rank == 1) {
                            $rowClass = 'bg-amber-50 dark:bg-amber-900/10 font-medium';
                            $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400 font-bold text-lg border border-yellow-200 dark:border-yellow-700"><i class="fa-solid fa-medal"></i></span>';
                        } elseif ($rank == 2) {
                            $rowClass = 'bg-slate-50 dark:bg-slate-800/50 font-medium';
                            $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 font-bold text-base border border-slate-300 dark:border-slate-600">#2</span>';
                        } elseif ($rank == 3) {
                            $rowClass = 'bg-orange-50 dark:bg-orange-900/10 font-medium';
                            $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 font-bold text-base border border-orange-200 dark:border-orange-700">#3</span>';
                        }
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors border-b border-slate-100 dark:border-slate-700/50 <?php echo $rowClass; ?>">
                        <td class="p-4 text-center">
                            <!-- Hidden text agar DataTables bisa sorting dengan benar -->
                            <span class="hidden"><?php echo str_pad($rank, 4, '0', STR_PAD_LEFT); ?></span>
                            <?php echo $rankBadge; ?>
                        </td>
                        <td class="p-4 text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($k['nama_karyawan']); ?></td>
                        <td class="p-4 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($k['nama_cabang']); ?></td>
                        <td class="p-4 text-center">
                            <span class="px-3 py-1 bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400 rounded-lg font-bold">
                                <?php echo $k['total_hadir']; ?>
                            </span>
                        </td>
                        <td class="p-4 text-center text-slate-600 dark:text-slate-400">
                            <?php echo $jam; ?>h <?php echo $menit; ?>m
                        </td>
                    </tr>
                    <?php 
                        $rank++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelKlasemen').DataTable({
        pageLength: 5,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
            lengthMenu: "Tampilkan _MENU_ data",
            search: "Cari:",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Selanjutnya",
                previous: "Sebelumnya"
            },
            emptyTable: "Belum ada data absensi untuk periode ini."
        },
        responsive: false,
        scrollX: true,
        order: [[0, 'asc']], // Urutkan berdasarkan kolom pertama (peringkat) secara ascending
        drawCallback: function(settings) {
            // Re-apply Tailwind styling untuk elemen pagination dan length menu jika perlu
            // (karena DataTables Tailwind integration mungkin butuh sedikit penyesuaian di dark mode)
        },
        initComplete: function(settings, json) {
            $('#tabelKlasemen').removeClass('opacity-0');
        }
    });
});
</script>

<?php
if ($role == 'admin') {
    include 'admin_footer.php';
} elseif ($role == 'owner') {
    include 'owner_footer.php';
}
?>
