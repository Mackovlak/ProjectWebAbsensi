<?php
require 'config.php';
require 'owner_header.php';

$id = $_GET['id'] ?? 0;

// Fetch Employee Data
$sql = "SELECT k.*, j.nama_jabatan, c.nama_cabang
        FROM karyawan k
        LEFT JOIN jabatan j ON k.id_jabatan = j.id
        LEFT JOIN cabang c ON k.id_cabang = c.id
        WHERE k.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>alert('Data karyawan tidak ditemukan!'); window.location.href='owner_data_karyawan.php';</script>";
    exit();
}

$data = $result->fetch_assoc();
$stmt->close();

$jenis_kelamin = $data['jenis_kelamin'] ?? 'L';
$default_avatar = ($jenis_kelamin == 'P') ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2';
$foto = !empty($data['foto']) ? 'assets/images/foto_karyawan/' . $data['foto'] : $default_avatar;

$status_badge = ($data['status'] == 'aktif') 
    ? '<span class="px-3 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm"><i class="fa-solid fa-check-circle mr-1"></i> Aktif</span>' 
    : '<span class="px-3 py-1 bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm"><i class="fa-solid fa-user-slash mr-1"></i> Non-Aktif</span>';

// Format Tanggal
function formatDate($date) {
    if (!$date) return '-';
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . ' ' . $bulan[date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

$ttl = (!empty($data['tempat_lahir']) && !empty($data['tanggal_lahir'])) 
        ? $data['tempat_lahir'] . ', ' . formatDate($data['tanggal_lahir']) 
        : '-';

$join_date_string = substr($data['id_karyawan'], 0, 8); // Format: YYYYMMDD
$join_date_formatted = substr($join_date_string, 0, 4) . '-' . substr($join_date_string, 4, 2) . '-' . substr($join_date_string, 6, 2);
$bergabung = formatDate($join_date_formatted);
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Detail Karyawan</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Informasi lengkap biodata dan status karyawan.</p>
    </div>
    
    <div class="flex items-center gap-3 w-full sm:w-auto">
        <a href="owner_data_karyawan.php" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium text-sm shadow-sm w-full sm:w-auto">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Left Column: Photo & Status -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden p-6 flex flex-col items-center relative">
            <div class="absolute top-4 right-4">
                <?php echo $status_badge; ?>
            </div>
            
            <div class="w-40 h-40 mt-4 mb-5 relative cursor-pointer group" onclick="openZoomModal()">
                <img src="<?php echo htmlspecialchars($foto); ?>" alt="Foto Karyawan" class="w-full h-full object-cover rounded-2xl border-4 border-slate-100 dark:border-slate-700 shadow-md transition-transform duration-300 group-hover:scale-105">
                <div class="absolute inset-0 bg-black/40 rounded-2xl flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    <i class="fa-solid fa-magnifying-glass-plus text-white text-3xl"></i>
                </div>
            </div>
            
            <h3 class="text-lg font-bold text-slate-800 dark:text-white text-center mb-1"><?php echo htmlspecialchars($data['nama_karyawan']); ?></h3>
            <p class="text-sm font-medium text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/20 px-3 py-1 rounded-lg mb-4"><?php echo htmlspecialchars($data['id_karyawan']); ?></p>
            
            <div class="w-full pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-between text-sm">
                <span class="text-slate-500 dark:text-slate-400">Penempatan</span>
                <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($data['nama_cabang']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Details -->
    <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-regular fa-address-card text-brand-500"></i> Informasi Data Diri
                </h3>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nama Lengkap</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($data['nama_karyawan']); ?></span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Jabatan</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($data['nama_jabatan']); ?></span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Jenis Kelamin</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo $jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan'; ?></span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Tempat, Tanggal Lahir</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($ttl); ?></span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Agama</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo !empty($data['agama']) ? htmlspecialchars($data['agama']) : '-'; ?></span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Bergabung Sejak</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo $bergabung; ?></span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nomor WhatsApp</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium">
                            <?php if(!empty($data['no_whatsapp'])): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $data['no_whatsapp']); ?>" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1">
                                    <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($data['no_whatsapp']); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <!-- Item -->
                    <div class="flex flex-col md:col-span-2">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Alamat Lengkap</span>
                        <span class="text-slate-800 dark:text-slate-200 font-medium whitespace-pre-wrap leading-relaxed bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-100 dark:border-slate-700/50"><?php echo !empty($data['alamat_lengkap']) ? htmlspecialchars($data['alamat_lengkap']) : '-'; ?></span>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Zoom Image Modal -->
<div id="zoomModal" class="fixed inset-0 z-[100] hidden bg-black/95 flex items-center justify-center p-4">
    <button onclick="closeZoomModal()" class="absolute top-6 right-6 text-white hover:text-red-500 transition-colors z-[110]">
        <i class="fa-solid fa-times text-3xl"></i>
    </button>
    
    <div class="relative w-full h-full flex items-center justify-center overflow-hidden" id="zoomContainer" onwheel="handleWheel(event)">
        <img id="zoomModalImage" src="<?php echo htmlspecialchars($foto); ?>" class="max-w-none max-h-[90vh] transition-transform duration-200 cursor-grab origin-center" style="transform: scale(1);" onmousedown="startDrag(event)">
    </div>
    
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex items-center gap-4 bg-white/10 backdrop-blur-md px-6 py-3 rounded-full border border-white/20 text-white z-[110] shadow-2xl">
        <button onclick="zoomOut()" class="hover:text-fuchsia-400 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-all"><i class="fa-solid fa-minus"></i></button>
        <span id="zoomLevel" class="font-bold min-w-[5ch] text-center text-sm tracking-wider">100%</span>
        <button onclick="zoomIn()" class="hover:text-fuchsia-400 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-all"><i class="fa-solid fa-plus"></i></button>
        <div class="w-px h-6 bg-white/30 mx-2"></div>
        <button onclick="resetZoom()" class="hover:text-fuchsia-400 w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-all" title="Reset Zoom"><i class="fa-solid fa-rotate-right"></i></button>
    </div>
</div>

<script>
    let scale = 1;
    const MIN_SCALE = 0.5;
    const MAX_SCALE = 4;
    const STEP = 0.2;
    
    const zoomModal = document.getElementById('zoomModal');
    const zoomImg = document.getElementById('zoomModalImage');
    const zoomLevelText = document.getElementById('zoomLevel');
    
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;

    function openZoomModal() {
        zoomModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        resetZoom();
    }

    function closeZoomModal() {
        zoomModal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function updateZoom() {
        zoomImg.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
        zoomLevelText.textContent = Math.round(scale * 100) + '%';
    }

    function zoomIn() {
        if (scale < MAX_SCALE) {
            scale += STEP;
            updateZoom();
        }
    }

    function zoomOut() {
        if (scale > MIN_SCALE) {
            scale -= STEP;
            updateZoom();
        }
    }

    function resetZoom() {
        scale = 1;
        translateX = 0;
        translateY = 0;
        updateZoom();
    }

    function handleWheel(e) {
        e.preventDefault();
        if (e.deltaY < 0) {
            zoomIn();
        } else {
            zoomOut();
        }
    }

    function startDrag(e) {
        e.preventDefault();
        isDragging = true;
        startX = e.clientX - translateX;
        startY = e.clientY - translateY;
        zoomImg.style.cursor = 'grabbing';
    }

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        translateX = e.clientX - startX;
        translateY = e.clientY - startY;
        updateZoom();
    });

    window.addEventListener('mouseup', () => {
        isDragging = false;
        zoomImg.style.cursor = 'grab';
    });
</script>

<?php require 'owner_footer.php'; ?>
