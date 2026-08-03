<?php
require 'config.php';
require 'owner_header.php';

// --- Ambil data Jabatan & Cabang untuk dropdown ---
$jabatans = [];
$result_jabatan = $conn->query("SELECT * FROM jabatan ORDER BY nama_jabatan");
while ($row = $result_jabatan->fetch_assoc()) {
    $jabatans[] = $row;
}
$cabangs = [];
$result_cabang = $conn->query("SELECT * FROM cabang ORDER BY nama_cabang");
while ($row = $result_cabang->fetch_assoc()) {
    $cabangs[] = $row;
}

// --- Membuat ID Karyawan Otomatis ---
$prefix = date('Ymd');
$sql_last_id = "SELECT id_karyawan FROM karyawan WHERE id_karyawan LIKE '$prefix%' ORDER BY id_karyawan DESC LIMIT 1";
$result_last_id = $conn->query($sql_last_id);
if ($result_last_id->num_rows > 0) {
    $last_id = $result_last_id->fetch_assoc()['id_karyawan'];
    $last_num = intval(substr($last_id, -3));
    $new_num = $last_num + 1;
} else {
    $new_num = 1;
}
$new_employee_id = $prefix . str_pad($new_num, 3, '0', STR_PAD_LEFT);

// --- Filter View (Aktif / Arsip) ---
$view = $_GET['view'] ?? 'aktif';
$status_filter = ($view == 'arsip') ? 'nonaktif' : 'aktif';

// --- Ambil data karyawan dengan JOIN ---
$sql = "SELECT k.id, k.id_karyawan, k.nama_karyawan, k.jenis_kelamin, k.foto, j.nama_jabatan, c.nama_cabang, k.id_jabatan, k.id_cabang
        FROM karyawan k
        LEFT JOIN jabatan j ON k.id_jabatan = j.id
        LEFT JOIN cabang c ON k.id_cabang = c.id
        WHERE k.status = '$status_filter'
        ORDER BY k.nama_karyawan ASC";
$result = $conn->query($sql);

// Helper function untuk warna badge cabang (opsional, bisa disesuaikan)
function getCabangColorClass($nama_cabang) {
    $colors = ['bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-400', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400', 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400', 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'];
    $hash = md5($nama_cabang);
    $index = hexdec(substr($hash, 0, 1)) % count($colors);
    return $colors[$index];
}
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white"><?php echo $view == 'arsip' ? 'Arsip Karyawan Nonaktif' : 'Daftar Karyawan Aktif'; ?></h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola Data Karyawan, Jabatan, dan Penempatan Cabang.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <?php if ($view == 'arsip'): ?>
            <a href="owner_data_karyawan.php" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Aktif
            </a>
        <?php else: ?>
            <a href="owner_data_karyawan.php?view=arsip" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-50 border border-amber-200 text-amber-600 rounded-xl hover:bg-amber-100 transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
                <i class="fa-solid fa-folder-open"></i> Arsip / Non-aktif
            </a>
            <button onclick="exportLaporanKaryawan()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
                <i class="fa-solid fa-file-pdf text-red-500"></i> Export PDF
            </button>
        <?php endif; ?>
    </div>
</div>

<?php include 'alert_messages.php'; ?>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    
    <!-- Table Toolbar (Search, Filter Cabang & Entries) -->
    <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-center gap-4">
        
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto flex-1 max-w-2xl">
            <!-- Search Area -->
            <div class="relative w-full sm:w-1/2">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                </div>
                <input type="text" id="searchInput" onkeyup="filterTable()" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari ID, Nama, atau Jabatan...">
            </div>
            
            <!-- Filter Cabang -->
            <div class="w-full sm:w-1/2 relative">
                <select id="filterCabang" onchange="filterTable()" class="appearance-none block w-full px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer">
                    <option value="all">Semua Cabang</option>
                    <?php foreach($cabangs as $cabang): ?>
                        <option value="<?php echo $cabang['id']; ?>">
                            <?php echo htmlspecialchars($cabang['nama_cabang']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </div>
            </div>
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
        <table class="w-full text-left border-collapse" id="karyawanTable">
            <thead class="sticky top-0 z-10">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold">Karyawan</th>
                    <th class="px-6 py-4 font-semibold">ID / Username</th>
                    <th class="px-6 py-4 font-semibold">Cabang</th>
                    <th class="px-6 py-4 font-semibold">Jabatan</th>
                    <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $cabangClass = getCabangColorClass($row['nama_cabang']);
                        $jenis_kelamin = $row['jenis_kelamin'] ?? 'L';
                        $foto = $row['foto'] ?? '';
                        
                        // Generate URL Avatar Kartun (Lokal)
                        if (!empty($foto)) {
                            $avatar_url = 'assets/images/foto_karyawan/' . $foto;
                        } else if ($jenis_kelamin == 'P') {
                            // Wanita: Style Karir Berhijab
                            $avatar_url = "assets/images/avatar_p.png?v=2";
                        } else {
                            // Pria: Style Karir
                            $avatar_url = "assets/images/avatar_l.png?v=2";
                        }
                    ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group" data-cabang-id="<?php echo $row['id_cabang']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="h-10 w-10 rounded-full object-cover bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 shadow-sm shrink-0">
                                    <div>
                                        <p class="font-semibold text-slate-800 dark:text-white text-sm search-target"><?php echo htmlspecialchars($row['nama_karyawan']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300 search-target">
                                <?php echo htmlspecialchars($row['id_karyawan']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium <?php echo $cabangClass; ?> search-target">
                                    <?php echo htmlspecialchars($row['nama_cabang']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300 search-target">
                                <?php echo htmlspecialchars($row['nama_jabatan']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                        <a href="owner_detail_karyawan.php?id=<?php echo $row['id']; ?>" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg dark:text-indigo-400 dark:hover:bg-indigo-900/30 transition-colors" title="Lihat detail info karyawan">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-50"></i>
                            <p>Tidak ada data karyawan.</p>
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



<script>
let currentPage = 1;
let entriesPerPage = 5;

document.addEventListener('DOMContentLoaded', () => {
    filterTable();
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
    const filterCabang = document.getElementById('filterCabang') ? document.getElementById('filterCabang').value.toLowerCase() : '';
    const table = document.getElementById('karyawanTable');
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

        const cabangId = row.dataset.cabangId || '';
        const matchCabang = filterCabang === 'all' || cabangId === filterCabang;

        if (matchSearch && matchCabang) {
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

    // Tampilkan baris sesuai halaman
    for (let i = startIndex; i < endIndex; i++) {
        filteredRows[i].style.display = '';
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
function handleKaryawanAction(url, title, text, confirmColor) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Oke',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch(url + '&is_ajax=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                fetch(window.location.href)
                                    .then(res => res.text())
                                    .then(html => {
                                        const parser = new DOMParser();
                                        const doc = parser.parseFromString(html, 'text/html');
                                        document.querySelector('#karyawanTable tbody').innerHTML = doc.querySelector('#karyawanTable tbody').innerHTML;
                                        filterTable(); // Re-apply pagination state
                                    });
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan!',
                            text: 'Gagal terhubung ke server.'
                        });
                    });
            }
        });
    } else {
        if (confirm(text)) {
            window.location.href = url;
        }
    }
}
</script>

<script>
function exportLaporanKaryawan() {
    const filterCabang = document.getElementById('filterCabang');
    let cabangId = 'all';
    if (filterCabang && filterCabang.value !== '') {
        cabangId = filterCabang.value;
    }
    const currentView = '<?php echo $view; ?>';
    window.open(`laporan_karyawan_print.php?cabang_id=${cabangId}&status=${currentView}&action=preview`, '_blank');
}

document.addEventListener('DOMContentLoaded', () => {
    handleFormAjaxGlobal('form-tambah', 'Menyimpan data karyawan...', 'Apakah Anda yakin ingin menambahkan karyawan baru ini?', 'tambah_karyawan');
    
    const editForm = document.getElementById('form-edit');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            // Kita harus cek apakah tombol yg diklik adalah tombol submit dgn name="edit_karyawan"
            // Karena form.submit() atau fetch default gak ngirim value dari tombol submit
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            formData.append('is_ajax', '1');
            formData.append('edit_karyawan', '1'); // Untuk trigger kondisi di master_process.php
            
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
                                    document.querySelector('#karyawanTable tbody').innerHTML = doc.querySelector('#karyawanTable tbody').innerHTML;
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
                                    document.querySelector('#karyawanTable tbody').innerHTML = doc.querySelector('#karyawanTable tbody').innerHTML;
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
<?php require 'owner_footer.php'; ?>

