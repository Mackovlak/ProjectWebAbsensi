<?php
require 'config.php';
requireAdmin(); // Check admin access

// Validasi ID cabang
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID Cabang tidak valid.";
    header("Location: data_cabang.php");
    exit();
}

$id_cabang = intval($_GET['id']);

// Ambil informasi cabang
$stmt = $conn->prepare("SELECT nama_cabang, alamat_cabang FROM cabang WHERE id = ?");
$stmt->bind_param("i", $id_cabang);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Cabang tidak ditemukan.";
    header("Location: data_cabang.php");
    exit();
}

$cabang = $result->fetch_assoc();
$stmt->close();

// Ambil data karyawan di cabang ini dengan informasi jabatan
$sql_karyawan = "SELECT k.*, j.nama_jabatan 
                 FROM karyawan k 
                 LEFT JOIN jabatan j ON k.id_jabatan = j.id
                 WHERE k.id_cabang = ?
                 ORDER BY k.nama_karyawan ASC";

$stmt = $conn->prepare($sql_karyawan);
$stmt->bind_param("i", $id_cabang);
$stmt->execute();
$result_karyawan = $stmt->get_result();

// Hitung total karyawan
$total_karyawan = $result_karyawan->num_rows;

require 'admin_header.php';
?>

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    
    <!-- Header Content & Info Cards -->
    <div class="p-6 lg:p-8 border-b border-slate-200 dark:border-slate-700">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Daftar Karyawan - <?php echo htmlspecialchars($cabang['nama_cabang']); ?></h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    <i class="fa-solid fa-location-dot mr-1"></i> <?php echo htmlspecialchars($cabang['alamat_cabang']); ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="data_cabang.php" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
                <?php if ($total_karyawan > 0): ?>
                    <button onclick="exportToPDF()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-sm transition-colors text-sm font-medium flex items-center gap-2">
                        <i class="fa-solid fa-file-pdf"></i> Export PDF
                    </button>
                    <button onclick="exportToExcel()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-sm transition-colors text-sm font-medium flex items-center gap-2">
                        <i class="fa-solid fa-file-excel"></i> Export Excel
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Total Karyawan</p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white"><?php echo $total_karyawan; ?></p>
                </div>
            </div>
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Nama Cabang</p>
                    <p class="text-xl font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($cabang['nama_cabang']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'alert_messages.php'; ?>

    <?php if ($total_karyawan > 0): ?>
        <!-- Table Toolbar (Search & Entries) -->
        <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between gap-4">
            <!-- Search Area -->
            <div class="relative w-full sm:w-96">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                </div>
                <input type="text" id="searchInput" onkeyup="filterTable()" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari ID atau nama karyawan...">
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
            <table class="w-full text-left border-collapse" id="karyawanCabangTable">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                        <th class="px-6 py-4 font-semibold w-24 text-center">No</th>
                        <th class="px-6 py-4 font-semibold">ID Karyawan</th>
                        <th class="px-6 py-4 font-semibold">Nama Karyawan</th>
                        <th class="px-6 py-4 font-semibold">Jabatan</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold text-right">Histori Absensi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    <?php 
                    $no = 1;
                    $dataForExport = [];
                    while($row = $result_karyawan->fetch_assoc()): 
                        $stmt_user = $conn->prepare("SELECT id FROM users WHERE id_karyawan = ?");
                        $stmt_user->bind_param("s", $row['id_karyawan']);
                        $stmt_user->execute();
                        $has_account = $stmt_user->get_result()->num_rows > 0;
                        $stmt_user->close();
                        
                        $dataForExport[] = [
                            'no' => $no,
                            'id' => $row['id_karyawan'],
                            'nama' => $row['nama_karyawan'],
                            'jabatan' => $row['nama_jabatan'],
                            'status' => $has_account ? 'Aktif' : 'Tidak Aktif'
                        ];
                    ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-center font-medium text-slate-500 dark:text-slate-400">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300 search-target">
                                <?php echo htmlspecialchars($row['id_karyawan']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                    <p class="font-semibold text-slate-800 dark:text-white text-sm search-target"><?php echo htmlspecialchars($row['nama_karyawan']); ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300 search-target">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 border border-brand-200 dark:border-brand-800/50">
                                    <?php echo htmlspecialchars($row['nama_jabatan']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($has_account): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                        <i class="fa-solid fa-check-circle"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50">
                                        <i class="fa-solid fa-times-circle"></i> Tidak Aktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="histori_absensi.php?cabang=<?php echo $id_cabang; ?>&search_name=<?php echo urlencode($row['nama_karyawan']); ?>" class="inline-flex items-center gap-2 text-slate-600 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 transition-colors" title="Lihat Histori Absensi">
                                    <i class="fa-solid fa-clock-rotate-left"></i> Histori
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
    <?php else: ?>
        <div class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-4 text-2xl">
                <i class="fa-solid fa-users-slash"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Tidak Ada Karyawan</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-6">Belum ada karyawan yang terdaftar di cabang <?php echo htmlspecialchars($cabang['nama_cabang']); ?>.</p>
            <a href="data_karyawan.php" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm">
                <i class="fa-solid fa-plus"></i> Tambah Karyawan
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden Form untuk Export -->
<form id="exportForm" method="POST" action="export_karyawan_cabang.php" style="display: none;">
    <input type="hidden" name="data" id="exportData">
    <input type="hidden" name="format" id="exportFormat">
    <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($cabang['nama_cabang']); ?>">
    <input type="hidden" name="alamat" value="<?php echo htmlspecialchars($cabang['alamat_cabang']); ?>">
</form>



<script>
let currentPage = 1;
let entriesPerPage = 5;

<?php if ($total_karyawan > 0): ?>
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
    const table = document.getElementById('karyawanCabangTable');
    if(!table) return;
    
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
<?php endif; ?>

const exportDataArray = <?php echo json_encode($dataForExport ?? []); ?>;

function exportToExcel() {
    if (exportDataArray.length === 0) {
        alert('Tidak ada data untuk diekspor.');
        return;
    }
    
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportFormat').value = 'excel';
    document.getElementById('exportForm').submit();
}

function exportToPDF() {
    if (exportDataArray.length === 0) {
        alert('Tidak ada data untuk diekspor.');
        return;
    }
    
    document.getElementById('exportData').value = JSON.stringify(exportDataArray);
    document.getElementById('exportFormat').value = 'pdf';
    document.getElementById('exportForm').submit();
}
</script>

<?php 
$stmt->close();
require 'admin_footer.php'; 
?>
