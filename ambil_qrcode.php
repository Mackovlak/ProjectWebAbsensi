<?php
require 'config.php';
require 'admin_header.php';

// Ambil semua data karyawan beserta jabatan dan cabang
$sql = "SELECT k.id_karyawan, k.nama_karyawan, j.nama_jabatan, c.nama_cabang 
        FROM karyawan k
        LEFT JOIN jabatan j ON k.id_jabatan = j.id
        LEFT JOIN cabang c ON k.id_cabang = c.id
        ORDER BY k.nama_karyawan ASC";
$result = $conn->query($sql);

$karyawans = [];
$cabangs = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $karyawans[] = $row;
        if (!empty($row['nama_cabang']) && !in_array($row['nama_cabang'], $cabangs)) {
            $cabangs[] = $row['nama_cabang'];
        }
    }
}
sort($cabangs);
?>

<!-- Library html2canvas & jsPDF untuk Unduh PNG dan PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">ID Card & QR Code</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Buat dan unduh kode QR untuk identitas absensi karyawan.</p>
    </div>
    
    <div class="flex items-center gap-3 w-full sm:w-auto">
        <!-- Tombol Cetak/Unduh Massal PDF -->
        <button id="btnDownloadAllPdf" onclick="downloadAllPdf()" class="flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30">
            <i class="fa-solid fa-file-pdf"></i> Unduh Semua QR (PDF)
        </button>
    </div>
</div>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col mb-8">
    
    <!-- Table Toolbar (Search & Filter Cabang) -->
    <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between gap-4">
        
        <!-- Search & Filter Area -->
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto flex-1 max-w-xl">
            <!-- Kolom Cari -->
            <div class="relative w-full sm:w-2/3">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                </div>
                <input type="text" id="searchInput" onkeyup="filterTable()" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari Nama atau ID Karyawan...">
            </div>
            
            <!-- Filter Cabang -->
            <div class="w-full sm:w-1/3">
                <select id="branchFilter" onchange="filterTable()" class="block w-full px-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl bg-slate-50 dark:bg-slate-900/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:text-sm transition-colors cursor-pointer appearance-none">
                    <option value="all">Semua Cabang</option>
                    <?php foreach ($cabangs as $cabang): ?>
                        <option value="<?php echo htmlspecialchars($cabang); ?>"><?php echo htmlspecialchars($cabang); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Pilihan Jumlah Tampilan -->
        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span>Tampilkan</span>
            <select id="entriesSelect" onchange="changeEntries()" class="border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 px-3 py-2 outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                <option value="5" selected>5</option>
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="all">Semua</option>
            </select>
            <span>data</span>
        </div>
    </div>

    <!-- TABLE SCROLL WRAPPER (Responsive X) -->
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse min-w-[800px]" id="employeeTable">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                    <th class="px-6 py-4 font-semibold w-12 text-center">NO</th>
                    <th class="px-6 py-4 font-semibold">ID KARYAWAN</th>
                    <th class="px-6 py-4 font-semibold">NAMA</th>
                    <th class="px-6 py-4 font-semibold">JABATAN</th>
                    <th class="px-6 py-4 font-semibold">CABANG</th>
                    <th class="px-6 py-4 font-semibold text-center">QR CODE</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700" id="tableBody">
                <?php 
                $no = 1;
                foreach ($karyawans as $karyawan): 
                    $roleLabel = ($karyawan['nama_jabatan'] ?? '') . ($karyawan['nama_cabang'] ? ' - ' . $karyawan['nama_cabang'] : '');
                ?>
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors employee-row" 
                    data-branch="<?php echo htmlspecialchars($karyawan['nama_cabang'] ?? ''); ?>" 
                    data-name="<?php echo htmlspecialchars($karyawan['nama_karyawan']); ?>" 
                    data-id="<?php echo htmlspecialchars($karyawan['id_karyawan']); ?>" 
                    data-role="<?php echo htmlspecialchars($roleLabel); ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-slate-500 row-number"><?php echo $no++; ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-700 dark:text-slate-300">
                        <?php echo htmlspecialchars($karyawan['id_karyawan']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-slate-800 dark:text-white text-sm emp-name"><?php echo htmlspecialchars($karyawan['nama_karyawan']); ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                        <?php echo htmlspecialchars($karyawan['nama_jabatan'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 emp-branch">
                            <?php echo htmlspecialchars($karyawan['nama_cabang'] ?? '-'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <button onclick="openQrModal('<?php echo addslashes(htmlspecialchars($karyawan['nama_karyawan'])); ?>', '<?php echo addslashes(htmlspecialchars($karyawan['id_karyawan'])); ?>', '<?php echo addslashes(htmlspecialchars($roleLabel)); ?>')" class="inline-flex items-center justify-center w-8 h-8 bg-purple-50 text-purple-600 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-800/50 rounded-lg transition-colors border border-purple-200 dark:border-purple-800" title="Tampilkan QR">
                            <i class="fa-solid fa-qrcode"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($karyawans)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                        <i class="fa-solid fa-folder-open text-4xl mb-3 opacity-50"></i>
                        <p>Tidak ada data karyawan.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- PAGINATION -->
    <div class="p-5 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/20">
        <div class="text-sm text-slate-500 dark:text-slate-400" id="tableInfo">
            Showing ...
        </div>
        
        <div class="flex items-center gap-1" id="paginationControls">
            <!-- Disisipkan lewat JavaScript -->
        </div>
    </div>
</div>

<!-- CONTAINER TERSEMBUNYI UNTUK MERENDER PDF (HIDDEN DARI USER) -->
<!-- Width diset ke 794px untuk mensimulasikan lebar A4 -->
<div id="pdfRenderContainer" style="position: absolute; top: -9999px; left: -9999px; width: 794px; background-color: #ffffff; padding: 20px;">
    <!-- Container untuk flex grid ID Cards -->
    <div id="pdfCardGrid" style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: flex-start; align-content: flex-start;">
        <!-- Kartu ID akan di-inject ke sini oleh JavaScript -->
    </div>
</div>

<!-- MODAL POPUP: TAMPILAN ID CARD / QR CODE (TAMPILAN TUNGGAL) -->
<div id="employeeQrModal" class="fixed inset-0 z-[60] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden transform transition-all">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Preview ID Card</h3>
            <button onclick="closeQrModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body: ID Card Layout -->
        <div class="p-6 bg-slate-100 dark:bg-slate-900 flex justify-center">
            <!-- ID Card Print Area -->
            <div id="idCardPrintArea" class="bg-white w-64 rounded-xl shadow-md overflow-hidden border border-slate-200 relative">
                <!-- Header Card -->
                <div class="bg-purple-700 text-white text-center py-3 border-b-4 border-purple-900">
                    <i class="fa-solid fa-user-shield text-2xl mb-1 text-white"></i>
                    <h4 class="text-sm font-bold tracking-widest text-white">ABSENSI JAVAG</h4>
                </div>
                
                <!-- Content Card -->
                <div class="p-4 flex flex-col items-center">
                    <h2 id="modalEmpName" class="text-lg font-bold text-slate-800 text-center uppercase mb-1 leading-tight">NAMA KARYAWAN</h2>
                    <p id="modalEmpRole" class="text-xs text-purple-700 font-semibold mb-4 text-center">JABATAN - CABANG</p>
                    
                    <!-- QR Code -->
                    <div class="w-40 h-40 bg-white border-2 border-slate-200 rounded-lg flex items-center justify-center p-2 mb-3">
                        <img src="" crossorigin="anonymous" alt="QR Code" class="w-full h-full object-contain opacity-90" id="modalQrImage">
                    </div>
                    
                    <p class="text-[10px] text-slate-400 uppercase tracking-widest">ID Karyawan</p>
                    <p id="modalEmpId" class="text-sm font-mono font-bold text-slate-700">1234567890</p>
                </div>
                
                <div class="bg-slate-50 text-[10px] text-center py-2 text-slate-500 border-t border-slate-100">
                    Scan QR ini saat tiba di lokasi kerja.
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-white dark:bg-slate-800 border-t border-slate-100 dark:border-slate-700 flex justify-between gap-3">
            <button onclick="closeQrModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl font-medium transition-colors text-sm">Tutup</button>
            
            <!-- Tombol Unduh PNG Tunggal -->
            <button onclick="downloadSinglePng(event)" class="flex-1 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors text-sm shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Unduh PNG
            </button>
        </div>
    </div>
</div>

<script>
    // Memastikan jsPDF terinisialisasi dengan benar
    if (window.jspdf && window.jspdf.jsPDF) {
        window.jsPDF = window.jspdf.jsPDF;
    }

    // Variabel Pagination
    let currentPage = 1;
    let entriesPerPage = 5;
    
    // Inisialisasi awal
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
    
    // Fungsi untuk Filter Tabel berdasarkan Pencarian & Dropdown Cabang
    function filterTable() {
        const searchVal = document.getElementById('searchInput').value.toLowerCase();
        const branchFilter = document.getElementById('branchFilter').value.toLowerCase();
        const rows = document.querySelectorAll('.employee-row');
        
        let visibleRows = [];

        rows.forEach(row => {
            const name = row.getAttribute('data-name').toLowerCase();
            const branch = row.getAttribute('data-branch').toLowerCase();
            const id = row.getAttribute('data-id').toLowerCase();

            const matchSearch = name.includes(searchVal) || id.includes(searchVal);
            const matchBranch = branchFilter === 'all' || branch === branchFilter || (branch === '' && branchFilter === '-');

            if (matchSearch && matchBranch) {
                visibleRows.push(row);
            } else {
                row.style.display = 'none';
            }
        });

        const totalEntries = visibleRows.length;
        const totalPages = Math.ceil(totalEntries / entriesPerPage) || 1;
        
        if (currentPage > totalPages) currentPage = totalPages;
        
        const startIdx = (currentPage - 1) * entriesPerPage;
        const endIdx = Math.min(startIdx + entriesPerPage, totalEntries);

        // Update penomoran dan tampilan baris
        visibleRows.forEach((row, index) => {
            if (index >= startIdx && index < endIdx) {
                row.style.display = '';
                row.querySelector('.row-number').textContent = index + 1;
            } else {
                row.style.display = 'none';
            }
        });

        // Update informasi pagination
        const showStart = totalEntries === 0 ? 0 : startIdx + 1;
        document.getElementById('tableInfo').textContent = `Showing ${showStart} to ${endIdx} of ${totalEntries} entries`;
        
        // Update tombol pagination
        updatePaginationControls(totalPages);
    }

    function updatePaginationControls(totalPages) {
        const controls = document.getElementById('paginationControls');
        let html = '';

        // Previous button
        if (currentPage === 1) {
            html += `<button class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-400 cursor-not-allowed text-sm" disabled>Previous</button>`;
        } else {
            html += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm">Previous</button>`;
        }

        // Page buttons (simplified for brevity, max 5 buttons)
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

        // Next button
        if (currentPage === totalPages || totalPages === 0) {
            html += `<button class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-400 cursor-not-allowed text-sm" disabled>Next</button>`;
        } else {
            html += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm">Next</button>`;
        }

        controls.innerHTML = html;
    }

    // ----- FUNGSI UNTUK MODAL TUNGGAL -----
    function openQrModal(name, id, role) {
        // Ambil URL yang sesuai (misal untuk abensi, sama seperti sebelumnya: absen.php?id=...)
        const qrContent = "<?php echo "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>/absen.php?id=" + id;
        
        document.getElementById('modalEmpName').textContent = name;
        document.getElementById('modalEmpId').textContent = id;
        document.getElementById('modalEmpRole').textContent = role;
        document.getElementById('modalQrImage').src = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" + encodeURIComponent(qrContent);
        document.getElementById('employeeQrModal').classList.remove('hidden');
    }

    function closeQrModal() {
        document.getElementById('employeeQrModal').classList.add('hidden');
    }

    // Fungsi Unduh PNG untuk SATU Karyawan (Dari Modal)
    function downloadSinglePng(event) {
        const cardElement = document.getElementById('idCardPrintArea');
        const empName = document.getElementById('modalEmpName').textContent.replace(/\s+/g, '_');
        
        const btn = event.currentTarget;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
        btn.disabled = true;

        html2canvas(cardElement, { scale: 3, useCORS: true, backgroundColor: "#ffffff" }).then(canvas => {
            const imageURI = canvas.toDataURL("image/png");
            
            const downloadLink = document.createElement("a");
            downloadLink.href = imageURI;
            downloadLink.download = `ID_Card_Javag_${empName}.png`;
            
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);

            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }).catch(err => {
            console.error(err);
            alert("Gagal mengunduh gambar PNG.");
            btn.innerHTML = originalHTML; 
            btn.disabled = false;
        });
    }

    // ----- FUNGSI UNDUH MASSAL PDF (Berdasarkan Filter Tabel) -----
    async function downloadAllPdf() {
        const btn = document.getElementById('btnDownloadAllPdf');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Membuat PDF...';
        btn.disabled = true;

        // Ambil semua baris yang sesuai filter
        const searchVal = document.getElementById('searchInput').value.toLowerCase();
        const branchFilter = document.getElementById('branchFilter').value.toLowerCase();
        const rows = document.querySelectorAll('.employee-row');
        
        let filteredRows = [];
        rows.forEach(row => {
            const name = row.getAttribute('data-name').toLowerCase();
            const branch = row.getAttribute('data-branch').toLowerCase();
            const id = row.getAttribute('data-id').toLowerCase();

            const matchSearch = name.includes(searchVal) || id.includes(searchVal);
            const matchBranch = branchFilter === 'all' || branch === branchFilter;

            if (matchSearch && matchBranch) {
                filteredRows.push(row);
            }
        });
        
        if (filteredRows.length === 0) {
            alert("Tidak ada data karyawan yang sesuai filter untuk dicetak.");
            btn.innerHTML = originalHTML; btn.disabled = false;
            return;
        }

        const gridContainer = document.getElementById('pdfCardGrid');
        const pdfRenderContainer = document.getElementById('pdfRenderContainer');
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const cardsPerPage = 12; // 3 kolom x 4 baris agar muat 1 halaman A4

        try {
            // Proses chunk per halaman
            for (let i = 0; i < filteredRows.length; i += cardsPerPage) {
                const chunk = filteredRows.slice(i, i + cardsPerPage);
                gridContainer.innerHTML = ''; 

                // Bangun HTML untuk setiap ID Card di chunk ini
                chunk.forEach(row => {
                    const name = row.getAttribute('data-name');
                    const id = row.getAttribute('data-id');
                    const role = row.getAttribute('data-role');
                    const qrContent = "<?php echo "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>/absen.php?id=" + id;
                    const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" + encodeURIComponent(qrContent);

                    // Desain kartu sedikit lebih kecil (lebar ~175px)
                    const cardHTML = `
                        <div style="width: 175px; height: 100%; display: flex; flex-direction: column; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; font-family: sans-serif; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);">
                            <div style="background-color: #7e22ce; color: white; text-align: center; padding: 8px 0; border-bottom: 3px solid #581c87;">
                                <h4 style="margin: 0; font-size: 10px; letter-spacing: 1px;">ABSENSI JAVAG</h4>
                            </div>
                            <div style="padding: 12px; display: flex; flex-direction: column; align-items: center; flex-grow: 1;">
                                <h2 style="margin: 0 0 4px 0; font-size: 13px; font-weight: bold; color: #1e293b; text-align: center; text-transform: uppercase;">${name}</h2>
                                <p style="margin: 0 0 10px 0; font-size: 8px; color: #7e22ce; font-weight: bold; text-align: center;">${role}</p>
                                
                                <div style="margin-top: auto; display: flex; flex-direction: column; align-items: center;">
                                    <div style="width: 100px; height: 100px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px; margin-bottom: 10px; background: white;">
                                        <img src="${qrUrl}" crossorigin="anonymous" style="width: 100%; height: 100%; object-fit: contain;">
                                    </div>
                                    
                                    <p style="margin: 0; font-size: 8px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">ID Karyawan</p>
                                    <p style="margin: 2px 0 0 0; font-size: 12px; font-family: monospace; font-weight: bold; color: #334155;">${id}</p>
                                </div>
                            </div>
                            <div style="background-color: #f8fafc; font-size: 8px; text-align: center; padding: 6px; color: #64748b; border-top: 1px solid #f1f5f9;">
                                Scan QR ini saat tiba.
                            </div>
                        </div>
                    `;
                    gridContainer.innerHTML += cardHTML;
                });

                // Tunggu QR Code termuat
                await new Promise(resolve => setTimeout(resolve, 1000));

                const canvas = await html2canvas(pdfRenderContainer, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: "#ffffff"
                });

                const imgData = canvas.toDataURL('image/png');
                const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

                if (i > 0) pdf.addPage();
                
                pdf.text(`Data Cetak ID Card - Javag Team (Hal ${Math.floor(i/cardsPerPage) + 1})`, 14, 10);
                pdf.addImage(imgData, 'PNG', 0, 15, pdfWidth, pdfHeight);
            }

            const filterDropdown = document.getElementById('branchFilter');
            const filterText = filterDropdown.options[filterDropdown.selectedIndex].text.replace(/\s+/g, '_');
            
            pdf.save(`ID_Cards_${filterText}.pdf`);
        } catch (err) {
            console.error(err);
            alert("Terjadi kesalahan saat memproses PDF masal.");
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            gridContainer.innerHTML = ''; 
        }
    }
</script>

<?php require 'admin_footer.php'; ?>
