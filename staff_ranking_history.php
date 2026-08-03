<?php
require 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: login.php");
    exit();
}

$id_karyawan = $_SESSION['id_karyawan'];
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

$nama_bulan_arr = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

include 'staff_header.php';
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
.dataTables_wrapper > div:first-child {
    margin-bottom: 1.25rem !important;
}
.dataTables_wrapper > div:last-child {
    margin-top: 1.25rem !important;
}
@media (max-width: 640px) {
    .dataTables_wrapper > div.grid,
    .dataTables_wrapper > div.flex {
        display: flex !important;
        flex-direction: column !important;
        gap: 1rem !important;
    }
    .dataTables_wrapper > div > div {
        width: 100% !important;
        max-width: 100% !important;
        justify-content: center !important;
        text-align: center !important;
    }
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
    .dataTables_wrapper .dataTables_info {
        text-align: center !important;
        margin-bottom: 0.75rem !important;
    }
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 0.5rem !important;
        display: flex !important;
        justify-content: center !important;
        width: 100% !important;
    }
    .dataTables_wrapper .dataTables_paginate ul.pagination {
        flex-wrap: wrap !important;
        justify-content: center !important;
        gap: 0.35rem !important;
    }
    .dataTables_wrapper .overflow-x-auto {
        width: 100% !important;
    }
}
</style>

<div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <i class="fa-solid fa-ranking-star text-brand-500"></i>
            Tabel Peringkat Performa
        </h2>
        <form method="GET" action="" class="flex items-center gap-2">
            <select name="tahun" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-xl text-sm outline-none focus:border-brand-500">
                <?php 
                $current_year = date('Y');
                for($y = $current_year; $y >= 2020; $y--) {
                    $sel = ($y == $tahun) ? 'selected' : '';
                    echo "<option value=\"$y\" $sel>$y</option>";
                }
                ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-medium transition-colors shadow-sm">Tampilkan</button>
        </form>
    </div>

    <!-- Tabel Riwayat -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-6">
            <table id="tabelRiwayatRanking" class="w-full text-left border-collapse whitespace-nowrap opacity-0 transition-opacity duration-500">
                <thead>
                    <tr>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400">Bulan</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400 text-center">Kehadiran (Hari)</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400 text-center">Durasi Kerja</th>
                        <th class="p-4 border-b border-slate-200 dark:border-slate-700 font-semibold text-sm text-slate-500 dark:text-slate-400 text-center">Ranking Nasional</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $sql = "SELECT k.id_karyawan, COUNT(a.id) as total_hadir, SUM(TIME_TO_SEC(TIMEDIFF(a.jam_pulang, a.jam_masuk))) as total_detik
                                FROM karyawan k
                                JOIN absensi a ON k.id_karyawan = a.id_karyawan
                                WHERE a.keterangan = 'Hadir' AND a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
                                  AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
                                GROUP BY k.id_karyawan
                                ORDER BY total_hadir DESC, total_detik DESC";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $m, $tahun);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        
                        $my_rank = '-';
                        $my_hadir = 0;
                        $my_detik = 0;
                        $total_karyawan_aktif = $res->num_rows;
                        
                        $current_rank = 1;
                        while($row = $res->fetch_assoc()) {
                            if ($row['id_karyawan'] == $id_karyawan) {
                                $my_rank = $current_rank;
                                $my_hadir = $row['total_hadir'];
                                $my_detik = $row['total_detik'];
                                break; // Stop loop karena sudah ketemu
                            }
                            $current_rank++;
                        }
                        
                        $jam = floor($my_detik / 3600);
                        $menit = floor(($my_detik % 3600) / 60);

                        $rankBadge = '<span class="text-slate-400 italic text-xs">Tidak masuk klasemen</span>';
                        if ($my_rank !== '-') {
                            if ($my_rank == 1) {
                                $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400 font-bold text-lg border border-yellow-200 dark:border-yellow-700" title="Juara 1"><i class="fa-solid fa-medal"></i></span>';
                            } elseif ($my_rank == 2) {
                                $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 font-bold text-base border border-slate-300 dark:border-slate-600" title="Juara 2">#2</span>';
                            } elseif ($my_rank == 3) {
                                $rankBadge = '<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400 font-bold text-base border border-orange-200 dark:border-orange-700" title="Juara 3">#3</span>';
                            } else {
                                $rankBadge = '<span class="inline-flex items-center justify-center min-w-[3rem] px-2.5 h-8 rounded-full bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400 font-bold text-sm">#' . $my_rank . ' <span class="text-[10px] ml-1 font-normal opacity-70"> / ' . $total_karyawan_aktif . '</span></span>';
                            }
                        }
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors border-b border-slate-100 dark:border-slate-700/50">
                            <td class="p-4 text-slate-800 dark:text-slate-200 font-medium">
                                <span class="hidden"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></span>
                                <?php echo $nama_bulan_arr[$m]; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($my_hadir > 0): ?>
                                    <span class="px-3 py-1 bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-lg font-bold">
                                        <?php echo $my_hadir; ?> Hari
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center text-slate-600 dark:text-slate-400">
                                <?php if ($my_hadir > 0): ?>
                                    <?php echo $jam; ?>h <?php echo $menit; ?>m
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php echo $rankBadge; ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tabelRiwayatRanking').DataTable({
        pageLength: 5,
        lengthMenu: [[5, 10, 12, -1], [5, 10, 12, "Semua"]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
            lengthMenu: "Tampilkan _MENU_ baris",
            search: "Cari:",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ bulan",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: '<i class="fa-solid fa-chevron-right text-xs"></i>',
                previous: '<i class="fa-solid fa-chevron-left text-xs"></i>'
            },
            emptyTable: "Belum ada data ranking."
        },
        responsive: false,
        scrollX: true,
        ordering: true,
        order: [],
        initComplete: function(settings, json) {
            $('#tabelRiwayatRanking').removeClass('opacity-0');
        }
    });
});
</script>

<?php include 'staff_footer.php'; ?>
