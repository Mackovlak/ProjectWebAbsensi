<?php
require 'config.php';
include 'admin_header.php';

// Ambil semua data jabatan dari database
$result = $conn->query("SELECT * FROM jabatan ORDER BY nama_jabatan ASC");
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Data Jabatan</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola daftar jabatan karyawan.</p>
    </div>
    
    <div class="flex items-center gap-3 w-full sm:w-auto">
        <button onclick="openModal('modal-tambah')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30 w-full sm:w-auto whitespace-nowrap">
            <i class="fa-solid fa-plus"></i> Tambah Baru
        </button>
    </div>
</div>

<?php include 'alert_messages.php'; ?>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    
    <!-- Table Toolbar (Search & Entries) -->
    <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between gap-4">
        <!-- Search Area -->
        <div class="relative w-full sm:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
            </div>
            <input type="text" id="searchInput" onkeyup="filterTable()" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari nama jabatan...">
        </div>

        <!-- Pilihan Jumlah Tampilan -->
        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 self-start sm:self-center">
            <span>Tampilkan</span>
            <select id="entriesSelect" onchange="changeEntries()" class="border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 px-3 py-2 outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                <option value="5" selected>5</option>
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="all">Semua</option>
            </select>
            <span>data</span>
        </div>
    </div>

    <!-- Table Wrapper -->
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse" id="jabatanTable">
            <thead class="sticky top-0 z-10">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold w-24 text-center">No</th>
                    <th class="px-6 py-4 font-semibold">Nama Jabatan</th>
                    <th class="px-6 py-4 font-semibold text-right">Tunjangan Jabatan</th>
                    <th class="px-6 py-4 font-semibold text-center">Lembur Sabtu</th>
                    <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <?php if ($result->num_rows > 0): ?>
                    <?php 
                    $no = 1;
                    while($row = $result->fetch_assoc()): 
                    ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-center font-medium text-slate-500 dark:text-slate-400">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="font-semibold text-slate-800 dark:text-white text-sm search-target"><?php echo htmlspecialchars($row['nama_jabatan']); ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-slate-700 dark:text-slate-300">
                                Rp <?php echo number_format($row['tunjangan_jabatan'], 0, ',', '.'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if (!empty($row['overtime_sabtu'])): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50">
                                        <i class="fa-solid fa-hourglass-half"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                    <button onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['nama_jabatan'])); ?>', '<?php echo $row['tunjangan_jabatan']; ?>', <?php echo !empty($row['overtime_sabtu']) ? 'true' : 'false'; ?>)" class="p-2 text-brand-600 hover:bg-brand-50 rounded-lg dark:text-brand-400 dark:hover:bg-brand-900/30 transition-colors" title="Edit Data">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="master_process.php?hapus_jabatan=<?php echo $row['id']; ?>" onclick="event.preventDefault(); handleDeleteAction(this.href, 'Hapus Jabatan?', 'Apakah Anda yakin ingin menghapus jabatan <?php echo addslashes($row['nama_jabatan']); ?>?');" class="p-2 text-red-600 hover:bg-red-50 rounded-lg dark:text-red-400 dark:hover:bg-red-900/30 transition-colors" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-50"></i>
                            <p>Tidak ada data jabatan.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="p-5 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/20">
        <span id="tableInfo" class="text-sm text-slate-500 dark:text-slate-400">Menampilkan 0 hingga 0 dari 0 data</span>
        <div id="paginationControls" class="flex gap-1">
            <!-- Buttons injected by JS -->
        </div>
    </div>
</div>

<!-- Modal Tambah Jabatan -->
<div id="modal-tambah" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-tambah')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Tambah Jabatan Baru</h3>
                <button onclick="closeModal('modal-tambah')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form action="master_process.php" method="POST">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Jabatan <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_jabatan" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tunjangan Jabatan (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" inputmode="numeric" name="tunjangan_jabatan" required value="0" class="format-rp w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 cursor-pointer">
                        <input type="checkbox" name="overtime_sabtu" value="1" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span class="text-sm text-amber-800 dark:text-amber-300">
                            <b>Bisa ditugaskan lembur hari Sabtu</b>
                            <span class="block text-xs mt-0.5 opacity-90">Jam kerja Sabtu jabatan ini akan dihitung sebagai lembur pada slip gaji. Biarkan kosong untuk jabatan tanpa lembur.</span>
                        </span>
                    </label>
                </div>
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-tambah')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                    <button type="submit" name="tambah_jabatan" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl shadow-sm shadow-brand-500/30 transition-colors text-sm font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Jabatan -->
<div id="modal-edit" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-edit')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Edit Jabatan</h3>
                <button onclick="closeModal('modal-edit')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form action="master_process.php" method="POST" id="form-edit">
                <input type="hidden" name="id_jabatan" id="edit-id-jabatan">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Jabatan <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_jabatan" id="edit-nama-jabatan" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tunjangan Jabatan (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" inputmode="numeric" name="tunjangan_jabatan" id="edit-tunjangan-jabatan" required class="format-rp w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 cursor-pointer">
                        <input type="checkbox" name="overtime_sabtu" id="edit-overtime-sabtu" value="1" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span class="text-sm text-amber-800 dark:text-amber-300">
                            <b>Bisa ditugaskan lembur hari Sabtu</b>
                            <span class="block text-xs mt-0.5 opacity-90">Jam kerja Sabtu jabatan ini akan dihitung sebagai lembur pada slip gaji.</span>
                        </span>
                    </label>
                </div>
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-edit')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                    <button type="submit" name="edit_jabatan" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl shadow-sm shadow-brand-500/30 transition-colors text-sm font-medium">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let entriesPerPage = 5;

function openEditModal(id, nama, tunjangan, overtimeSabtu) {
    document.getElementById('edit-overtime-sabtu').checked = !!overtimeSabtu;
    document.getElementById('form-edit').reset();
    document.getElementById('edit-id-jabatan').value = id;
    document.getElementById('edit-nama-jabatan').value = nama;
    
    // Format tunjangan saat modal edit dibuka, pastikan di-parse ke integer dulu untuk membuang desimal .00
    let valTunjangan = parseInt(tunjangan, 10) || 0;
    let formatTunjangan = valTunjangan.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    document.getElementById('edit-tunjangan-jabatan').value = formatTunjangan;
    
    const modal = document.getElementById('modal-edit');
    modal.classList.remove('hidden');
}

function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    filterTable();
    
    // Format input Rupiah
    document.querySelectorAll('.format-rp').forEach(input => {
        input.addEventListener('input', function(e) {
            // Hanya izinkan angka
            let val = this.value.replace(/[^0-9]/g, '');
            // Format titik
            if (val) {
                val = parseInt(val, 10).toString();
                this.value = val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            } else {
                this.value = '';
            }
        });
    });
});

function changeEntries() {
    const val = document.getElementById('entriesSelect').value;
    entriesPerPage = val === 'all' ? Number.MAX_SAFE_INTEGER : parseInt(val);
    currentPage = 1;
    filterTable();
}

function goToPage(page) {
    currentPage = page;
    filterTable();
}

function filterTable() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('jabatanTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    let filteredRows = [];

    // Filter baris
    rows.forEach(row => {
        // Skip baris "Tidak ada data"
        if (row.querySelector('td[colspan]')) return;

        let matchSearch = false;
        const targets = row.querySelectorAll('.search-target');
        
        if (targets.length > 0) {
            targets.forEach(target => {
                if (target.textContent.toLowerCase().includes(searchInput)) {
                    matchSearch = true;
                }
            });
        } else {
            // Fallback jika tidak ada class search-target
            matchSearch = row.textContent.toLowerCase().includes(searchInput);
        }

        if (matchSearch) {
            filteredRows.push(row);
        }
        row.style.display = 'none'; // Sembunyikan semua dulu
    });

    const totalEntries = filteredRows.length;
    const totalPages = Math.ceil(totalEntries / entriesPerPage);
    
    // Pastikan current page valid
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIndex = (currentPage - 1) * entriesPerPage;
    const endIndex = Math.min(startIndex + entriesPerPage, totalEntries);

    // Update Nomor Urut dan Tampilkan baris sesuai halaman
    let displayIndex = startIndex + 1;
    for (let i = startIndex; i < endIndex; i++) {
        filteredRows[i].style.display = '';
        // Update kolom pertama (No) jika bukan th
        const firstTd = filteredRows[i].querySelector('td:first-child');
        if (firstTd && !firstTd.hasAttribute('colspan')) {
            firstTd.innerHTML = displayIndex++;
        }
    }

    // Update Info
    const infoSpan = document.getElementById('tableInfo');
    if (totalEntries === 0) {
        infoSpan.textContent = 'Menampilkan 0 hingga 0 dari 0 data';
    } else {
        infoSpan.textContent = `Menampilkan ${startIndex + 1} hingga ${endIndex} dari ${totalEntries} data`;
    }

    // Update Pagination Buttons
    const paginationControls = document.getElementById('paginationControls');
    paginationControls.innerHTML = '';

    if (totalPages > 1) {
        // Prev button
        const prevBtn = document.createElement('button');
        prevBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}`;
        prevBtn.textContent = 'Previous';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => goToPage(currentPage - 1);
        paginationControls.appendChild(prevBtn);

        // Page buttons (simplified: show all if few, or just surrounding pages if many)
        for (let i = 1; i <= totalPages; i++) {
            // Batasi tombol yang tampil agar tidak kepanjangan
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

        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}`;
        nextBtn.textContent = 'Next';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => goToPage(currentPage + 1);
        paginationControls.appendChild(nextBtn);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const editForm = document.getElementById('form-edit');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            formData.append('is_ajax', '1');
            formData.append('edit_jabatan', '1'); 
            
            const confirmAndSubmit = () => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Menyimpan...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }
                
                fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            closeModal('modal-edit');
                            fetch(window.location.href)
                                .then(res => res.text())
                                .then(html => {
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');
                                    document.querySelector('#jabatanTable tbody').innerHTML = doc.querySelector('#jabatanTable tbody').innerHTML;
                                    filterTable(); // Re-apply pagination state
                                }); 
                        });
                    } else {
                        alert(data.message);
                        closeModal('modal-edit');
                        fetch(window.location.href)
                                .then(res => res.text())
                                .then(html => {
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');
                                    document.querySelector('#jabatanTable tbody').innerHTML = doc.querySelector('#jabatanTable tbody').innerHTML;
                                    filterTable(); // Re-apply pagination state
                                });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message
                        });
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Terjadi Kesalahan!',
                        text: 'Gagal terhubung ke server.'
                    });
                } else {
                    alert('Terjadi kesalahan koneksi.');
                }
            });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Konfirmasi Edit',
                    text: 'Apakah Anda yakin ingin menyimpan perubahan ini?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#c026d3',
                    cancelButtonColor: '#ef4444',
                    confirmButtonText: '<i class="fa-solid fa-check mr-2"></i>Ya, Simpan',
                    cancelButtonText: '<i class="fa-solid fa-xmark mr-2"></i>Batal',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        confirmAndSubmit();
                    }
                });
            } else {
                if (confirm('Apakah Anda yakin ingin menyimpan perubahan ini?')) {
                    confirmAndSubmit();
                }
            }
        });
    }
});
</script>
<?php include 'admin_footer.php'; ?>


