<?php
require 'config.php';
requireLogin();

// Pastikan hanya staff yang bisa mengakses ini
if ($_SESSION['role'] != 'staff') {
    $_SESSION['error_message'] = "Halaman ini khusus untuk Karyawan.";
    header("Location: admin_dashboard.php");
    exit();
}

// Pastikan user memiliki id_karyawan yang valid
if (empty($_SESSION['id_karyawan'])) {
    $_SESSION['error_message'] = "Akun Anda belum terhubung dengan data karyawan.";
    header("Location: staff_dashboard.php");
    exit();
}

$id_karyawan = $_SESSION['id_karyawan'];

// Set default bulan dan tahun
$current_month = date('m');
$current_year = date('Y');

// Ambil daftar tahun yang tersedia untuk filter
$query_years = "SELECT DISTINCT tahun FROM slip_gaji WHERE id_karyawan = ? ORDER BY tahun DESC";
$stmt_years = $conn->prepare($query_years);
$stmt_years->bind_param("s", $id_karyawan);
$stmt_years->execute();
$result_years = $stmt_years->get_result();
$available_years = [];
while ($row_year = $result_years->fetch_assoc()) {
    $available_years[] = $row_year['tahun'];
}
if (empty($available_years)) {
    $available_years[] = $current_year;
}

$filter_tahun = isset($_GET['filter_tahun']) ? (int)$_GET['filter_tahun'] : $current_year;

// Ambil riwayat slip gaji untuk karyawan ini berdasarkan tahun
$query_slip = "SELECT id, bulan, tahun, total_penghasilan, total_potongan, gaji_bersih, 
                      status_admin_acc, status_owner_acc, status_karyawan_acc 
               FROM slip_gaji 
               WHERE id_karyawan = ? AND tahun = ?
               ORDER BY tahun DESC, bulan DESC";
$stmt_slip = $conn->prepare($query_slip);
$stmt_slip->bind_param("si", $id_karyawan, $filter_tahun);
$stmt_slip->execute();
$result_slip = $stmt_slip->get_result();

require 'staff_header.php';
?>

<!-- MAIN CONTENT -->
<div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
    
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Informasi Gaji</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"> Slip Gaji dan Riwayat Gaji Anda.</p>
    </div>

    <!-- Grid Layout untuk Laporan -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- CARD: CETAK SLIP GAJI -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col transition-all hover:shadow-md">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center gap-4 bg-slate-50/50 dark:bg-slate-900/20">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500 to-purple-600 flex items-center justify-center text-white shadow-sm shrink-0">
                    <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Slip Gaji</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400"> Unduh slip gaji bulanan Anda.</p>
                </div>
            </div>
            <div class="p-6 flex-1">
                <form action="laporan_slip_batch.php" method="GET" target="_blank" class="space-y-5" onsubmit="return convertDate(this)">
                    <input type="hidden" name="tipe" value="cetak_slip_batch">
                    <!-- id_karyawan akan di-override di sisi server, tapi dikirim untuk kompatibilitas -->
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($id_karyawan); ?>">
                    <input type="hidden" name="start_date" class="start_date_hidden">
                    <input type="hidden" name="end_date" class="end_date_hidden">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Bulan</label>
                            <select name="bulan_slip" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                                <option value="all">1 Tahun (Semua Bulan)</option>
                                <?php
                                $months = ['01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April', '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus', '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'];
                                foreach ($months as $num => $name) {
                                    $selected = ($num == $current_month) ? 'selected' : '';
                                    echo "<option value=\"$num\" $selected>$name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tahun</label>
                            <select name="tahun_slip" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                                <?php
                                $start_year = 2024;
                                $end_year = date('Y') + 1;
                                for ($y = $end_year; $y >= $start_year; $y--) {
                                    $selected = ($y == $current_year) ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-5 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors shadow-sm">
                            <i class="fa-solid fa-print"></i> Cetak / Preview Slip
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CARD: CETAK RIWAYAT GAJI PRIBADI -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col transition-all hover:shadow-md">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center gap-4 bg-slate-50/50 dark:bg-slate-900/20">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shadow-sm shrink-0">
                    <i class="fa-solid fa-file-contract text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Riwayat Gaji Pribadi</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400"> Cetak rekap riwayat gaji Anda.</p>
                </div>
            </div>
            <div class="p-6 flex-1">
                <form action="laporan_gaji_print.php" method="GET" target="_blank" class="space-y-5" onsubmit="return convertDateRiwayat(this)">
                    <input type="hidden" name="tipe" value="per_karyawan">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($id_karyawan); ?>">
                    <input type="hidden" name="start_date" class="start_date_hidden_riwayat">
                    <input type="hidden" name="end_date" class="end_date_hidden_riwayat">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Periode Awal</label>
                            <div class="flex gap-2">
                                <select name="bln_awal" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                    <?php
                                    foreach ($months as $num => $name) {
                                        $selected = ($num == '01') ? 'selected' : '';
                                        echo "<option value=\"$num\" $selected>$name</option>";
                                    }
                                    ?>
                                </select>
                                <select name="thn_awal" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                    <?php
                                    for ($y = $end_year; $y >= $start_year; $y--) {
                                        $selected = ($y == $current_year) ? 'selected' : '';
                                        echo "<option value=\"$y\" $selected>$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Periode Akhir</label>
                            <div class="flex gap-2">
                                <select name="bln_akhir" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                    <?php
                                    foreach ($months as $num => $name) {
                                        $selected = ($num == $current_month) ? 'selected' : '';
                                        echo "<option value=\"$num\" $selected>$name</option>";
                                    }
                                    ?>
                                </select>
                                <select name="thn_akhir" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                    <?php
                                    for ($y = $end_year; $y >= $start_year; $y--) {
                                        $selected = ($y == $current_year) ? 'selected' : '';
                                        echo "<option value=\"$y\" $selected>$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium transition-colors shadow-sm">
                            <i class="fa-solid fa-print"></i> Cetak / Preview Riwayat
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CARD: DAFTAR SLIP GAJI -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col transition-all hover:shadow-md lg:col-span-2">
            <div class="p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/20 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="fa-solid fa-file-signature text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Tanda Tangani Slip Gaji</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Slip gaji yang telah diterbitkan dan menunggu persetujuan Anda.</p>
                    </div>
                </div>
                <!-- Filter Tahun -->
                <form action="" method="GET" class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Filter Tahun:</label>
                    <select name="filter_tahun" onchange="this.form.submit()" class="px-4 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-brand-500 outline-none transition-all cursor-pointer">
                        <?php foreach($available_years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($filter_tahun == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="p-0 flex-1 overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <th class="px-6 py-4 font-semibold w-12 text-center">NO</th>
                            <th class="px-6 py-4 font-semibold">PERIODE</th>
                            <th class="px-6 py-4 font-semibold text-right">TOTAL GAJI</th>
                            <th class="px-6 py-4 font-semibold text-center">STATUS PENGESAHAN</th>
                            <th class="px-6 py-4 font-semibold text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php 
                        $months_name = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        if ($result_slip && $result_slip->num_rows > 0): 
                            $no = 1; while($row = $result_slip->fetch_assoc()): 
                        ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500"><?php echo $no++; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-slate-800 dark:text-white text-sm block">
                                    <?php echo $months_name[$row['bulan']] . ' ' . $row['tahun']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                Rp <?php echo number_format($row['gaji_bersih'], 0, ',', '.'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if (!$row['status_owner_acc']): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        <i class="fa-solid fa-clock"></i> Diproses
                                    </span>
                                <?php elseif ($row['status_owner_acc'] && !$row['status_karyawan_acc']): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                                        <i class="fa-solid fa-file-signature"></i> Perlu TTD Anda
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <i class="fa-solid fa-check-double"></i> Selesai
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium flex justify-center gap-2">
                                <?php if ($row['status_owner_acc']): ?>
                                    <a href="laporan_slip_batch.php?tipe=cetak_slip_batch&user_id=<?php echo urlencode($id_karyawan); ?>&start_date=<?php echo date('Y-m-d', strtotime("{$row['tahun']}-{$row['bulan']}-01")); ?>&end_date=<?php echo date('Y-m-t', strtotime("{$row['tahun']}-{$row['bulan']}-01")); ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 rounded-lg transition-colors border border-slate-200 dark:border-slate-600">
                                        <i class="fa-solid fa-eye"></i> Lihat Slip
                                    </a>
                                    <?php if (!$row['status_karyawan_acc']): ?>
                                    <button onclick="accSlipKaryawan(<?php echo $row['id']; ?>)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/20 dark:text-emerald-400 dark:hover:bg-emerald-500/30 rounded-lg transition-colors border border-emerald-200 dark:border-emerald-500/30">
                                        <i class="fa-solid fa-signature"></i> Setujui
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Menunggu Owner</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <i class="fa-solid fa-file-invoice text-4xl mb-3 opacity-50 block"></i>
                                <p>Belum ada riwayat slip gaji.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
function accSlipKaryawan(id) {
    Swal.fire({
        title: 'Setujui Slip Gaji?',
        text: 'Dengan ini Anda menyatakan bahwa slip gaji ini sudah sesuai dan sah.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Setujui!',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

            const formData = new FormData();
            formData.append('action', 'acc_karyawan');
            formData.append('id_slip', id);

            fetch('proses_acc_gaji.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Slip gaji telah Anda setujui.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, customClass: { popup: 'rounded-3xl' }});
                }
            })
            .catch(error => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', customClass: { popup: 'rounded-3xl' }});
            });
        }
    });
}

// Fungsi untuk convert Pilihan Bulan & Tahun menjadi format date start_date dan end_date 
// agar sesuai dengan penerima laporan PDF.
function convertDate(form) {
    let bulan = form.querySelector("select[name^='bulan_']").value;
    let tahun = form.querySelector("select[name^='tahun_']").value;
    
    let startDate, endDate;

    if (bulan === 'all') {
        startDate = tahun + "-01-01";
        endDate = tahun + "-12-31";
    } else {
        // YYYY-MM-01
        startDate = tahun + "-" + bulan + "-01";
        
        // Mencari tanggal akhir bulan (YYYY-MM-t)
        let endDay = new Date(tahun, parseInt(bulan), 0).getDate();
        endDate = tahun + "-" + bulan + "-" + endDay;
    }

    form.querySelector(".start_date_hidden").value = startDate;
    form.querySelector(".end_date_hidden").value = endDate;

    return true; // Lanjutkan submit
}

function convertDateRiwayat(form) {
    let blnAwal = form.querySelector("select[name='bln_awal']").value;
    let thnAwal = form.querySelector("select[name='thn_awal']").value;
    let blnAkhir = form.querySelector("select[name='bln_akhir']").value;
    let thnAkhir = form.querySelector("select[name='thn_akhir']").value;

    let startDate = thnAwal + "-" + blnAwal + "-01";
    
    let endDay = new Date(thnAkhir, parseInt(blnAkhir), 0).getDate();
    let endDate = thnAkhir + "-" + blnAkhir + "-" + endDay;

    form.querySelector(".start_date_hidden_riwayat").value = startDate;
    form.querySelector(".end_date_hidden_riwayat").value = endDate;

    return true;
}
</script>

<?php 
require 'staff_footer.php'; // Atau footer yang sesuai dengan dashboard staff
?>
