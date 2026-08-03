<?php
/**
 * PERSETUJUAN SLIP GAJI - HALAMAN OWNER
 */
require 'config.php';
require 'owner_header.php';
// Ambil semua cabang untuk dropdown filter
$query_cabang = "SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC";
$result_cabang = $conn->query($query_cabang);
$cabang_list = [];
while($row = $result_cabang->fetch_assoc()) {
    $cabang_list[] = $row;
}

// Periode filter
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');

// Ambil semua slip gaji yang sudah di-ACC Admin
$query_karyawan = "SELECT k.id_karyawan, k.nama_karyawan, j.nama_jabatan, c.nama_cabang,
                          s.id as id_slip, s.status_admin_acc, s.status_owner_acc, s.gaji_bersih
                   FROM karyawan k
                   LEFT JOIN jabatan j ON k.id_jabatan = j.id
                   LEFT JOIN cabang c ON k.id_cabang = c.id
                   INNER JOIN slip_gaji s ON k.id_karyawan = s.id_karyawan 
                   WHERE k.status = 'aktif' AND s.bulan = ? AND s.tahun = ? AND s.status_admin_acc = 1
                   ORDER BY k.nama_karyawan ASC";

$stmt_k = $conn->prepare($query_karyawan);
$stmt_k->bind_param("ii", $bulan, $tahun);
$stmt_k->execute();
$result_karyawan = $stmt_k->get_result();
?>

<div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Persetujuan Slip Gaji</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Daftar slip gaji yang sudah diperiksa oleh Admin dan menunggu pengesahan Anda.</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
        
        <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-center gap-4">
            
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto flex-1 max-w-2xl">
                <!-- Search Input -->
                <div class="relative w-full sm:w-1/3">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                    </div>
                    <input type="text" id="searchKaryawan" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari Nama...">
                </div>
                
                <!-- Filter Cabang -->
                <div class="w-full sm:w-1/3 relative">
                    <select id="filterCabang" class="appearance-none block w-full px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer">
                        <option value="">Semua Cabang</option>
                        <?php foreach($cabang_list as $cabang): ?>
                            <option value="<?php echo strtolower(htmlspecialchars($cabang['nama_cabang'])); ?>">
                                <?php echo htmlspecialchars($cabang['nama_cabang']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </div>
                </div>

                <!-- Filter Periode (Bulan & Tahun) -->
                <form method="GET" class="flex gap-2 w-full sm:w-auto">
                    <select name="bulan" onchange="this.form.submit()" class="block w-auto px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer">
                        <?php
                        $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        foreach($months as $idx => $m) {
                            $sel = ($idx+1 == $bulan) ? 'selected' : '';
                            echo "<option value='".($idx+1)."' $sel>$m</option>";
                        }
                        ?>
                    </select>
                    <select name="tahun" onchange="this.form.submit()" class="block w-auto px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer">
                        <?php
                        for($y=date('Y')-2; $y<=date('Y')+1; $y++) {
                            $sel = ($y == $tahun) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>
                </form>
            </div>
            
            <!-- Items per page -->
            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 w-full sm:w-auto justify-between sm:justify-end">
                <button id="btnAccTerpilih" onclick="accBulk()" class="hidden items-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 rounded-xl transition-colors shadow-sm font-medium mr-2">
                    <i class="fa-solid fa-check-double"></i> ACC Terpilih (<span id="countTerpilih">0</span>)
                </button>
                <span>Tampilkan</span>
                <select id="entriesSelect" onchange="changeEntries()" class="border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 px-2 py-1 outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="all">Semua</option>
                </select>
                <span>data</span>
            </div>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[800px]" id="karyawanTable">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-4 w-12 text-center">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 text-brand-600 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200 focus:ring-opacity-50 cursor-pointer">
                        </th>
                        <th class="px-6 py-4 font-semibold w-12 text-center">NO</th>
                        <th class="px-6 py-4 font-semibold">NAMA KARYAWAN</th>
                        <th class="px-6 py-4 font-semibold">CABANG</th>
                        <th class="px-6 py-4 font-semibold text-right">TOTAL GAJI</th>
                        <th class="px-6 py-4 font-semibold text-center">STATUS</th>
                        <th class="px-6 py-4 font-semibold text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    
                    <?php if ($result_karyawan && $result_karyawan->num_rows > 0): ?>
                        <?php $no = 1; while($row = $result_karyawan->fetch_assoc()): ?>
                            <tr class="karyawan-row hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
                                data-nama="<?php echo strtolower(htmlspecialchars($row['nama_karyawan'])); ?>"
                                data-id="<?php echo strtolower(htmlspecialchars($row['id_karyawan'])); ?>"
                                data-cabang="<?php echo strtolower(htmlspecialchars($row['nama_cabang'] ?? '')); ?>">
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <?php if (!$row['status_owner_acc']): ?>
                                        <input type="checkbox" class="slip-checkbox rounded border-slate-300 text-brand-600 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200 focus:ring-opacity-50 cursor-pointer" value="<?php echo $row['id_slip']; ?>">
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500 row-number"><?php echo $no++; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-medium text-slate-800 dark:text-white text-sm block">
                                        <?php echo htmlspecialchars($row['nama_karyawan']); ?>
                                    </span>
                                    <span class="text-xs text-slate-500"><?php echo htmlspecialchars($row['nama_jabatan']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                        <?php echo htmlspecialchars($row['nama_cabang'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                                    Rp <?php echo number_format($row['gaji_bersih'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($row['status_owner_acc']): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                            <i class="fa-solid fa-check-double"></i> Di-ACC Owner
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                            <i class="fa-solid fa-clock"></i> Menunggu
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium flex justify-center gap-2">
                                    <a href="laporan_slip_batch.php?tipe=cetak_slip_batch&user_id=<?php echo urlencode($row['id_karyawan']); ?>&start_date=<?php echo date('Y-m-d', strtotime("$tahun-$bulan-01")); ?>&end_date=<?php echo date('Y-m-t', strtotime("$tahun-$bulan-01")); ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 rounded-lg transition-colors border border-slate-200 dark:border-slate-600">
                                        <i class="fa-solid fa-eye"></i> Lihat Slip
                                    </a>
                                    <?php if (!$row['status_owner_acc']): ?>
                                        <button onclick="accSlip(<?php echo $row['id_slip']; ?>)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-500/20 dark:text-emerald-400 dark:hover:bg-emerald-500/30 rounded-lg transition-colors border border-emerald-200 dark:border-emerald-500/30">
                                            <i class="fa-solid fa-check"></i> ACC
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyStateRow">
                            <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <i class="fa-solid fa-file-circle-check text-4xl mb-3 opacity-50 block"></i>
                                <p>Belum ada slip gaji yang menunggu persetujuan (atau di-ACC Admin) untuk periode ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr id="noResultsRow" class="hidden">
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-search text-4xl mb-3 opacity-50 block"></i>
                            <p>Tidak ada data yang sesuai dengan pencarian atau filter.</p>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
        
        <div class="p-5 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/20">
            <div class="text-sm text-slate-500 dark:text-slate-400" id="showingInfo">
                Menampilkan 0 data
            </div>
            
            <div class="flex items-center gap-1" id="paginationControls">
                <!-- Disisipkan lewat JavaScript -->
            </div>
        </div>

    </div>

</div>

<script>
let currentPage = 1;
let entriesPerPage = 5;

function accSlip(id) {
    Swal.fire({
        title: 'ACC Slip Gaji (Owner)?',
        text: 'Anda akan memberikan persetujuan final (pengesahan) untuk slip gaji ini.',
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
            formData.append('action', 'acc_owner');
            formData.append('id_slip', id);

            fetch('proses_acc_gaji.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Slip gaji telah disahkan.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
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

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchKaryawan');
    const filterCabang = document.getElementById('filterCabang');
    
    searchInput.addEventListener('input', () => { currentPage = 1; filterData(); });
    filterCabang.addEventListener('change', () => { currentPage = 1; filterData(); });
    
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const visibleCheckboxes = document.querySelectorAll('.karyawan-row:not([style*="display: none"]) .slip-checkbox');
            visibleCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkButton();
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('slip-checkbox')) {
            updateBulkButton();
            updateSelectAllState();
        }
    });

    filterData();
});

function updateBulkButton() {
    const checked = document.querySelectorAll('.slip-checkbox:checked').length;
    const btn = document.getElementById('btnAccTerpilih');
    const count = document.getElementById('countTerpilih');
    if (checked > 0) {
        btn.classList.remove('hidden');
        btn.classList.add('inline-flex');
        count.textContent = checked;
    } else {
        btn.classList.add('hidden');
        btn.classList.remove('inline-flex');
    }
}

function updateSelectAllState() {
    const visibleCheckboxes = document.querySelectorAll('.karyawan-row:not([style*="display: none"]) .slip-checkbox');
    const checkedBoxes = document.querySelectorAll('.karyawan-row:not([style*="display: none"]) .slip-checkbox:checked');
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = (visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedBoxes.length);
    }
}

function accBulk() {
    const checkedBoxes = document.querySelectorAll('.slip-checkbox:checked');
    if (checkedBoxes.length === 0) return;

    Swal.fire({
        title: 'ACC Slip Terpilih (Owner)?',
        text: `Anda akan memberikan pengesahan untuk ${checkedBoxes.length} slip gaji.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, ACC Semua!',
        cancelButtonText: 'Batal',
        customClass: { popup: 'rounded-3xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

            const formData = new FormData();
            formData.append('action', 'acc_owner');
            checkedBoxes.forEach(cb => {
                formData.append('id_slip[]', cb.value);
            });

            fetch('proses_acc_gaji.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
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

function changeEntries() {
    const val = document.getElementById('entriesSelect').value;
    entriesPerPage = val === 'all' ? Number.MAX_SAFE_INTEGER : parseInt(val);
    currentPage = 1;
    filterData();
}

function goToPage(page) {
    currentPage = page;
    filterData();
}

function filterData() {
    const searchInput = document.getElementById('searchKaryawan');
    const filterCabang = document.getElementById('filterCabang');
    const rows = document.querySelectorAll('.karyawan-row');
    const noResultsRow = document.getElementById('noResultsRow');
    const emptyStateRow = document.getElementById('emptyStateRow');
    const showingInfo = document.getElementById('showingInfo');

    const searchTerm = searchInput.value.toLowerCase().trim();
    const cabangFilter = filterCabang.value.toLowerCase();
    
    let visibleRows = [];

    if (emptyStateRow && emptyStateRow.style.display !== 'none' && !emptyStateRow.classList.contains('hidden')) {
        if (searchTerm !== '' || cabangFilter !== '') {
            emptyStateRow.style.display = 'none';
        } else {
            emptyStateRow.style.display = '';
        }
    }

    rows.forEach(row => {
        const nama = row.dataset.nama;
        const id = row.dataset.id;
        const cabang = row.dataset.cabang;
        
        const matchSearch = nama.includes(searchTerm) || id.includes(searchTerm);
        const matchCabang = cabangFilter === '' || cabang === cabangFilter;
        
        if (matchSearch && matchCabang) {
            visibleRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    const totalEntries = visibleRows.length;
    const totalPages = Math.ceil(totalEntries / entriesPerPage) || 1;
    
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * entriesPerPage;
    const endIdx = Math.min(startIdx + entriesPerPage, totalEntries);

    visibleRows.forEach((row, index) => {
        if (index >= startIdx && index < endIdx) {
            row.style.display = '';
            row.querySelector('.row-number').textContent = index + 1;
        } else {
            row.style.display = 'none';
        }
    });

    if (totalEntries === 0 && rows.length > 0) {
        noResultsRow.classList.remove('hidden');
    } else {
        noResultsRow.classList.add('hidden');
    }
    
    const showStart = totalEntries === 0 ? 0 : startIdx + 1;
    showingInfo.textContent = `Menampilkan ${showStart} hingga ${endIdx} dari ${totalEntries} data`;
    
    updatePaginationControls(totalPages);
    updateSelectAllState();
}

function updatePaginationControls(totalPages) {
    const controls = document.getElementById('paginationControls');
    let html = '';

    if (currentPage === 1) {
        html += `<button class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-400 cursor-not-allowed text-sm" disabled>Previous</button>`;
    } else {
        html += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm">Previous</button>`;
    }

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand-600 text-white font-medium text-sm shadow-sm">${i}</button>`;
        } else {
            html += `<button onclick="goToPage(${i})" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 font-medium text-sm">${i}</button>`;
        }
    }

    if (currentPage === totalPages || totalPages === 0) {
        html += `<button class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-400 cursor-not-allowed text-sm" disabled>Next</button>`;
    } else {
        html += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm">Next</button>`;
    }

    controls.innerHTML = html;
}
</script>

<?php require 'owner_footer.php'; ?>
