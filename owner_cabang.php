<?php
require 'owner_header.php';

// Ambil semua cabang
$sql_cabang = "SELECT * FROM cabang ORDER BY nama_cabang ASC";
$result_cabang = $conn->query($sql_cabang);
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Data Cabang</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Daftar seluruh cabang perusahaan dan jumlah karyawan.</p>
    </div>
</div>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    
    <!-- Table Wrapper -->
    <div class="overflow-x-auto relative">
        <table class="w-full text-left border-collapse" id="cabangTable">
            <thead class="sticky top-0 z-10">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold w-24 text-center">No</th>
                    <th class="px-6 py-4 font-semibold">Nama Cabang</th>
                    <th class="px-6 py-4 font-semibold">Alamat Cabang</th>
                    <th class="px-6 py-4 font-semibold text-center">Total Karyawan</th>
                    <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700" id="tableBody">
                <?php if ($result_cabang->num_rows > 0): ?>
                    <?php $no = 1; while($cabang = $result_cabang->fetch_assoc()): ?>
                        <?php
                        // Hitung total karyawan per cabang
                        $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM karyawan WHERE id_cabang = ?");
                        $stmt_count->bind_param("i", $cabang['id']);
                        $stmt_count->execute();
                        $total_karyawan = $stmt_count->get_result()->fetch_assoc()['total'];
                        ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-center font-medium text-slate-500 dark:text-slate-400">
                                <?php echo $no++; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center text-lg shrink-0">
                                        <i class="fa-solid fa-building"></i>
                                    </div>
                                    <p class="font-semibold text-slate-800 dark:text-white text-sm"><?php echo htmlspecialchars($cabang['nama_cabang']); ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                <div class="flex items-start gap-2">
                                    <i class="fa-solid fa-map-location-dot text-rose-500 mt-1"></i>
                                    <span><?php echo nl2br(htmlspecialchars($cabang['alamat_cabang'])); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border border-purple-200 dark:border-purple-800/50">
                                    <i class="fa-solid fa-users"></i> <?php echo $total_karyawan; ?> Orang
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="owner_detail_cabang.php?id=<?php echo $cabang['id']; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-fuchsia-50 hover:bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:hover:bg-fuchsia-900/50 dark:text-fuchsia-400 rounded-xl font-medium transition-colors border border-fuchsia-200 dark:border-fuchsia-800/50 shadow-sm">
                                    <i class="fa-solid fa-eye"></i> Lihat Data
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-building-circle-xmark text-5xl mb-4 opacity-50"></i>
                            <p class="text-lg font-medium text-slate-800 dark:text-white">Tidak ada data cabang.</p>
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

