<?php
/**
 * SLIP GAJI - HALAMAN 2: PILIH KARYAWAN
 * File: slip_gaji_karyawan.php
 * Purpose: Menampilkan list karyawan per cabang untuk dipilih
 */

require 'config.php';
requireAdmin();

// Validasi cabang
if (!isset($_GET['cabang']) || empty($_GET['cabang'])) {
    $_SESSION['error_message'] = "Cabang tidak valid.";
    redirect("slip_gaji.php");
}

$id_cabang = (int)$_GET['cabang'];

// Ambil info cabang
$stmt_cabang = $conn->prepare("SELECT nama_cabang, alamat_cabang FROM cabang WHERE id = ?");
$stmt_cabang->bind_param("i", $id_cabang);
$stmt_cabang->execute();
$cabang_info = $stmt_cabang->get_result()->fetch_assoc();
$stmt_cabang->close();

if (!$cabang_info) {
    $_SESSION['error_message'] = "Cabang tidak ditemukan.";
    redirect("slip_gaji.php");
}

// Periode filter
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Ambil karyawan di cabang ini beserta status slip
$query_karyawan = "SELECT k.id_karyawan, k.nama_karyawan, j.nama_jabatan,
                          s.id as id_slip, s.status_admin_acc, s.status_owner_acc
                   FROM karyawan k
                   LEFT JOIN jabatan j ON k.id_jabatan = j.id
                   LEFT JOIN slip_gaji s ON k.id_karyawan = s.id_karyawan AND s.bulan = ? AND s.tahun = ?
                   WHERE k.id_cabang = ? AND k.status = 'aktif'
                   ORDER BY k.nama_karyawan ASC";
$stmt_karyawan = $conn->prepare($query_karyawan);
$stmt_karyawan->bind_param("iii", $bulan, $tahun, $id_cabang);
$stmt_karyawan->execute();
$result_karyawan = $stmt_karyawan->get_result();

require 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <a href="slip_gaji.php" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
                <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Daftar Karyawan</h2>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 pl-11">Pilih karyawan cabang <strong class="text-brand-600 dark:text-brand-400"><?php echo htmlspecialchars($cabang_info['nama_cabang']); ?></strong> untuk membuat slip gaji.</p>
        </div>
        
        <!-- Bisa ditambahkan tombol cetak semua bulan ini kalau diperlukan nantinya -->
    </div>

    <?php include 'alert_messages.php'; ?>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col overflow-hidden">
        
        <!-- Toolbar Table -->
        <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/20 flex flex-col sm:flex-row gap-4 justify-between">
            <div class="relative w-full sm:w-1/2 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                </div>
                <input type="text" id="searchKaryawan" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-white dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari Nama Karyawan atau Jabatan...">
            </div>
            
            <!-- Filter Periode (Bulan & Tahun) -->
            <form method="GET" class="flex gap-2 w-full sm:w-auto">
                <input type="hidden" name="cabang" value="<?php echo $id_cabang; ?>">
                <select name="bulan" onchange="this.form.submit()" class="block w-auto px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer">
                    <?php
                    $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                    foreach($months as $idx => $m) {
                        $sel = ($idx+1 == $bulan) ? 'selected' : '';
                        echo "<option value='".($idx+1)."' $sel>$m</option>";
                    }
                    ?>
                </select>
                <select name="tahun" onchange="this.form.submit()" class="block w-auto px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer">
                    <?php
                    for($y=date('Y')-2; $y<=date('Y')+1; $y++) {
                        $sel = ($y == $tahun) ? 'selected' : '';
                        echo "<option value='$y' $sel>$y</option>";
                    }
                    ?>
                </select>
            </form>
        </div>

        <!-- Karyawan Table -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[800px]" id="karyawanTable">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <th class="px-6 py-4 font-semibold w-12 text-center">NO</th>
                        <th class="px-6 py-4 font-semibold">ID KARYAWAN</th>
                        <th class="px-6 py-4 font-semibold">NAMA</th>
                        <th class="px-6 py-4 font-semibold">JABATAN</th>
                        <th class="px-6 py-4 font-semibold text-center">STATUS</th>
                        <th class="px-6 py-4 font-semibold text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    
                    <?php if ($result_karyawan && $result_karyawan->num_rows > 0): ?>
                        <?php $no = 1; while($row = $result_karyawan->fetch_assoc()): ?>
                            <tr class="karyawan-row hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
                                data-nama="<?php echo strtolower(htmlspecialchars($row['nama_karyawan'])); ?>"
                                data-jabatan="<?php echo strtolower(htmlspecialchars($row['nama_jabatan'] ?? '')); ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500"><?php echo $no++; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-700 dark:text-slate-300">
                                    <?php echo htmlspecialchars($row['id_karyawan']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-medium text-slate-800 dark:text-white text-sm">
                                        <?php echo htmlspecialchars($row['nama_karyawan']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                    <?php echo htmlspecialchars($row['nama_jabatan'] ?? '-'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($row['id_slip']): ?>
                                        <?php if ($row['status_admin_acc']): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                                <i class="fa-solid fa-check-double"></i> Di-ACC
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                                <i class="fa-solid fa-clock"></i> Draft
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                            -
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium flex justify-center gap-2">
                                    <a href="slip_gaji_form.php?id_karyawan=<?php echo urlencode($row['id_karyawan']); ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>" class="inline-flex items-center gap-2 px-3 py-1.5 bg-brand-50 text-brand-600 hover:bg-brand-100 dark:bg-brand-500/20 dark:text-brand-300 dark:hover:bg-brand-500/30 rounded-lg transition-colors border border-brand-200 dark:border-brand-500/30 shadow-sm">
                                        <i class="fa-solid fa-file-invoice-dollar"></i> <?php echo $row['id_slip'] ? 'Edit Slip' : 'Buat Slip'; ?>
                                    </a>
                                    <?php if ($row['id_slip'] && !$row['status_admin_acc']): ?>
                                        <button onclick="accSlip(<?php echo $row['id_slip']; ?>)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/20 dark:text-emerald-400 dark:hover:bg-emerald-500/30 rounded-lg transition-colors border border-emerald-200 dark:border-emerald-500/30 shadow-sm">
                                            <i class="fa-solid fa-check"></i> ACC
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyStateRow">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <i class="fa-solid fa-users text-4xl mb-3 opacity-50 block"></i>
                                <p>Belum ada karyawan di cabang ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr id="noResultsRow" class="hidden">
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-search text-4xl mb-3 opacity-50 block"></i>
                            <p>Tidak ada karyawan yang sesuai dengan pencarian.</p>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
        
    </div>

</div>

<script>
// Search functionality
document.getElementById('searchKaryawan').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.karyawan-row');
    const noResults = document.getElementById('noResultsRow');
    const emptyState = document.getElementById('emptyStateRow');
    let visibleCount = 0;
    
    // Hide empty state if searching
    if (emptyState) {
        if (searchTerm !== '') {
            emptyState.style.display = 'none';
        } else {
            emptyState.style.display = ''; // Reset
        }
    }

    rows.forEach(row => {
        const nama = row.dataset.nama;
        const jabatan = row.dataset.jabatan;
        
        if (nama.includes(searchTerm) || jabatan.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    if (visibleCount === 0 && rows.length > 0) {
        noResults.classList.remove('hidden');
    } else {
        noResults.classList.add('hidden');
    }
});

function accSlip(id) {
    Swal.fire({
        title: 'ACC Slip Gaji?',
        text: 'Anda akan menyetujui slip gaji ini. Setelah di-ACC, slip dapat diproses lebih lanjut.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, ACC!',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

            const formData = new FormData();
            formData.append('action', 'acc_admin');
            formData.append('id_slip', id);

            fetch('proses_acc_gaji.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Slip gaji telah di-ACC.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
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
</script>

<?php 
$stmt_karyawan->close();
require 'admin_footer.php'; 
?>
