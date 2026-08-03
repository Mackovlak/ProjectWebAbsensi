<?php
require 'owner_header.php';

// Validasi ID cabang
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: owner_cabang.php");
    exit();
}

$id_cabang = intval($_GET['id']);

// Ambil info cabang
$stmt_cabang = $conn->prepare("SELECT * FROM cabang WHERE id = ?");
$stmt_cabang->bind_param("i", $id_cabang);
$stmt_cabang->execute();
$result_cabang = $stmt_cabang->get_result();
$cabang = $result_cabang->fetch_assoc();

if (!$cabang) {
    $_SESSION['error_message'] = "Cabang tidak ditemukan.";
    header("Location: owner_cabang.php");
    exit();
}

// Ambil karyawan di cabang ini
$sql_karyawan = "SELECT k.id_karyawan, k.nama_karyawan, j.nama_jabatan 
                 FROM karyawan k
                 LEFT JOIN jabatan j ON k.id_jabatan = j.id
                 WHERE k.id_cabang = ?
                 ORDER BY k.nama_karyawan ASC";
$stmt_karyawan = $conn->prepare($sql_karyawan);
$stmt_karyawan->bind_param("i", $id_cabang);
$stmt_karyawan->execute();
$result_karyawan = $stmt_karyawan->get_result();
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Detail Karyawan Cabang</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Daftar karyawan yang ditugaskan pada cabang <?php echo htmlspecialchars($cabang['nama_cabang']); ?>.</p>
    </div>
    
    <div class="flex items-center gap-3 w-full sm:w-auto">
        <a href="owner_cabang.php" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-xl transition-colors font-medium text-sm w-full sm:w-auto">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Cabang Info Box -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 mb-8 flex flex-col sm:flex-row gap-6 items-start sm:items-center justify-between">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center text-2xl border border-brand-100 dark:border-brand-800/30 shrink-0">
            <i class="fa-solid fa-building-user"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($cabang['nama_cabang']); ?></h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 flex items-start gap-1 mt-1">
                <i class="fa-solid fa-location-dot mt-0.5 text-rose-500"></i>
                <span><?php echo nl2br(htmlspecialchars($cabang['alamat_cabang'])); ?></span>
            </p>
        </div>
    </div>
    
    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl px-5 py-3 border border-slate-100 dark:border-slate-800 flex items-center gap-3 w-full sm:w-auto">
        <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Karyawan</p>
            <p class="text-lg font-bold text-slate-800 dark:text-white"><?php echo $result_karyawan->num_rows; ?> <span class="text-sm font-normal text-slate-500">Orang</span></p>
        </div>
    </div>
</div>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    <!-- Table Wrapper -->
    <div class="overflow-x-auto relative">
        <table class="w-full text-left border-collapse">
            <thead class="sticky top-0 z-10">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold w-24 text-center">No</th>
                    <th class="px-6 py-4 font-semibold">Nama Karyawan</th>
                    <th class="px-6 py-4 font-semibold">ID Karyawan</th>
                    <th class="px-6 py-4 font-semibold">Jabatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700" id="tableBody">
                <?php if ($result_karyawan->num_rows > 0): ?>
                    <?php $no = 1; while($karyawan = $result_karyawan->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-center font-medium text-slate-500 dark:text-slate-400">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="font-semibold text-slate-800 dark:text-white text-sm"><?php echo htmlspecialchars($karyawan['nama_karyawan']); ?></p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-block px-3 py-1 rounded-md text-xs font-mono font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                    <?php echo htmlspecialchars($karyawan['id_karyawan']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border border-purple-200 dark:border-purple-800/50 uppercase tracking-wide">
                                    <?php echo htmlspecialchars($karyawan['nama_jabatan'] ?? '-'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-users-slash text-5xl mb-4 opacity-50"></i>
                            <p class="text-lg font-medium text-slate-800 dark:text-white">Belum ada karyawan</p>
                            <p class="mt-1">Tidak ada karyawan yang ditugaskan di cabang ini.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50 dark:bg-slate-900/50">
        <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto justify-between sm:justify-start">
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500 dark:text-slate-400">Tampilkan</span>
                <select id="rowsPerPage" onchange="updateRowsPerPage()" class="px-2.5 py-1.5 border border-slate-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 outline-none focus:border-brand-500 cursor-pointer shadow-sm">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-sm text-slate-500 dark:text-slate-400">baris</span>
            </div>
            <div class="text-sm text-slate-500 dark:text-slate-400" id="tableInfo">
                Menampilkan 0 hingga 0 dari 0 data
            </div>
        </div>
        <div class="flex items-center gap-2 overflow-x-auto w-full md:w-auto justify-center md:justify-end pb-1 md:pb-0" id="paginationControls">
            <!-- Buttons will be rendered here by JS -->
        </div>
    </div>
</div>

<script>
// DataTables JS Pagination
const tableBody = document.getElementById('tableBody');
let currentPage = 1;
let rowsPerPage = 5;
let trs = Array.from(tableBody.querySelectorAll('tr'));

function updateRowsPerPage() {
    rowsPerPage = parseInt(document.getElementById('rowsPerPage').value);
    initPagination();
}

function initPagination() {
    if (trs.length === 0 || (trs.length === 1 && trs[0].querySelector('td[colspan]'))) {
        document.getElementById('tableInfo').textContent = 'Menampilkan 0 hingga 0 dari 0 data';
        document.getElementById('paginationControls').innerHTML = '';
        return;
    }
    goToPage(1);
}

function goToPage(page) {
    const totalEntries = trs.length;
    const totalPages = Math.ceil(totalEntries / rowsPerPage);
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentPage = page;

    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, totalEntries);

    trs.forEach((tr, index) => {
        if (index >= startIndex && index < endIndex) {
            tr.style.display = '';
        } else {
            tr.style.display = 'none';
        }
    });

    const infoSpan = document.getElementById('tableInfo');
    infoSpan.textContent = `Menampilkan ${startIndex + 1} hingga ${endIndex} dari ${totalEntries} data`;

    const paginationControls = document.getElementById('paginationControls');
    paginationControls.innerHTML = '';

    if (totalPages > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}`;
        prevBtn.textContent = 'Previous';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => goToPage(currentPage - 1);
        paginationControls.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
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

        const nextBtn = document.createElement('button');
        nextBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}`;
        nextBtn.textContent = 'Next';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => goToPage(currentPage + 1);
        paginationControls.appendChild(nextBtn);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initPagination();
});
</script>

<?php require 'owner_footer.php'; ?>

