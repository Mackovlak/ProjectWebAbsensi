<?php
require_once 'config.php';
require 'supervisor_header.php';

$view = ($_GET['view'] ?? 'aktif') === 'arsip' ? 'arsip' : 'aktif';
$status_filter = $view === 'arsip' ? 'nonaktif' : 'aktif';

if ($cabang_supervisor <= 0) {
    $karyawan = [];
} else {
    $stmt = $conn->prepare("SELECT k.id, k.id_karyawan, k.nama_karyawan, k.jenis_kelamin, k.foto,
                                  j.nama_jabatan, c.nama_cabang
                           FROM karyawan k
                           LEFT JOIN jabatan j ON j.id = k.id_jabatan
                           LEFT JOIN cabang c ON c.id = k.id_cabang
                           WHERE k.id_cabang = ? AND k.status = ?
                           ORDER BY k.nama_karyawan ASC");
    $stmt->bind_param('is', $cabang_supervisor, $status_filter);
    $stmt->execute();
    $karyawan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white"><?php echo $view === 'arsip' ? 'Arsip Karyawan Cabang' : 'Data Karyawan Cabang'; ?></h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Hanya menampilkan karyawan di cabang <?php echo htmlspecialchars($nama_cabang_supervisor ?: '-'); ?>.
        </p>
    </div>
    <a href="supervisor_data_karyawan.php<?php echo $view === 'aktif' ? '?view=arsip' : ''; ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium text-sm shadow-sm">
        <i class="fa-solid <?php echo $view === 'aktif' ? 'fa-box-archive' : 'fa-arrow-left'; ?>"></i>
        <?php echo $view === 'aktif' ? 'Lihat Arsip' : 'Karyawan Aktif'; ?>
    </a>
</div>

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-8">
    <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between gap-4">
        <div class="relative w-full max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-slate-400"></i>
            <input id="searchInput" type="search" placeholder="Cari ID, nama, atau jabatan..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span>Tampilkan</span>
            <select id="entriesSelect" class="border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 px-3 py-2 outline-none focus:ring-2 focus:ring-purple-500">
                <option value="5">5</option><option value="10">10</option><option value="20">20</option><option value="all">Semua</option>
            </select>
            <span>data</span>
        </label>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="karyawanTable">
            <thead><tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                <th class="px-6 py-4 font-semibold">Karyawan</th>
                <th class="px-6 py-4 font-semibold">ID Karyawan</th>
                <th class="px-6 py-4 font-semibold">Jabatan</th>
                <th class="px-6 py-4 font-semibold text-right">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php foreach ($karyawan as $row):
                $avatar = !empty($row['foto']) ? 'assets/images/foto_karyawan/' . $row['foto'] : (($row['jenis_kelamin'] ?? 'L') === 'P' ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2');
            ?>
                <tr class="employee-row hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-6 py-4"><div class="flex items-center gap-3">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" class="h-10 w-10 rounded-full object-cover border border-slate-200 dark:border-slate-600">
                        <span class="font-semibold text-slate-800 dark:text-white employee-search"><?php echo htmlspecialchars($row['nama_karyawan']); ?></span>
                    </div></td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 employee-search"><?php echo htmlspecialchars($row['id_karyawan']); ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 employee-search"><?php echo htmlspecialchars($row['nama_jabatan'] ?: '-'); ?></td>
                    <td class="px-6 py-4 text-right"><a href="supervisor_detail_karyawan.php?id=<?php echo (int)$row['id']; ?>" class="inline-flex p-2 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg" title="Lihat detail"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$karyawan): ?>
                <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">Tidak ada data karyawan pada cabang ini.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="p-5 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/20">
        <span id="tableInfo" class="text-sm text-slate-500 dark:text-slate-400">Menampilkan 0 hingga 0 dari 0 data</span>
        <div id="paginationControls" class="flex flex-wrap justify-center gap-1"></div>
    </div>
</div>

<script>
let currentPage = 1;
let entriesPerPage = 5;

function goToPage(page) {
    currentPage = page;
    renderEmployeeTable();
}

function paginationButton(label, page, disabled = false, active = false) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = label;
    button.disabled = disabled;
    button.className = active
        ? 'px-3 py-1.5 rounded-lg border border-purple-500 bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 font-semibold'
        : 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed';
    if (!disabled) button.addEventListener('click', () => goToPage(page));
    return button;
}

function renderEmployeeTable() {
    const keyword = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
    const rows = Array.from(document.querySelectorAll('.employee-row'));
    const filteredRows = rows.filter(row => row.textContent.toLowerCase().includes(keyword));
    const totalEntries = filteredRows.length;
    const totalPages = entriesPerPage === Number.MAX_SAFE_INTEGER ? (totalEntries ? 1 : 0) : Math.ceil(totalEntries / entriesPerPage);
    if (totalPages > 0 && currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    rows.forEach(row => { row.style.display = 'none'; });
    const startIndex = (currentPage - 1) * entriesPerPage;
    const endIndex = Math.min(startIndex + entriesPerPage, totalEntries);
    filteredRows.slice(startIndex, endIndex).forEach(row => { row.style.display = ''; });

    document.getElementById('tableInfo').textContent = totalEntries
        ? `Menampilkan ${startIndex + 1} hingga ${endIndex} dari ${totalEntries} data`
        : 'Menampilkan 0 hingga 0 dari 0 data';

    const controls = document.getElementById('paginationControls');
    controls.innerHTML = '';
    if (totalPages <= 1) return;
    controls.appendChild(paginationButton('Previous', currentPage - 1, currentPage === 1));
    for (let page = 1; page <= totalPages; page++) {
        controls.appendChild(paginationButton(String(page), page, false, page === currentPage));
    }
    controls.appendChild(paginationButton('Next', currentPage + 1, currentPage === totalPages));
}

document.getElementById('searchInput')?.addEventListener('input', () => {
    currentPage = 1;
    renderEmployeeTable();
});
document.getElementById('entriesSelect')?.addEventListener('change', function () {
    entriesPerPage = this.value === 'all' ? Number.MAX_SAFE_INTEGER : Number(this.value);
    currentPage = 1;
    renderEmployeeTable();
});
renderEmployeeTable();
</script>

<?php require 'supervisor_footer.php'; ?>
