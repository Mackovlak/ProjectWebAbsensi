<?php
require 'config.php';
requireAdminOrOwner();

$action = $_GET['action'] ?? 'preview';
$cabang_id = $_GET['cabang_id'] ?? 'all';
$status = $_GET['status'] ?? 'Aktif';

// Fetch Branch Name
$cabang_name = "Semua Divisi";
if ($cabang_id !== 'all') {
    $stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
    $stmt->bind_param("i", $cabang_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $cabang_name = $row['nama_cabang'];
    }
}

$user_id = $_GET['user_id'] ?? null;

// Fetch Karyawan
$query = "SELECT k.*, j.nama_jabatan, c.nama_cabang, GROUP_CONCAT(u.username SEPARATOR ', ') as username 
          FROM karyawan k 
          LEFT JOIN jabatan j ON k.id_jabatan = j.id 
          LEFT JOIN cabang c ON k.id_cabang = c.id 
          LEFT JOIN users u ON k.id_karyawan = u.id_karyawan
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($user_id)) {
    $query .= " AND k.id_karyawan = ?";
    $params[] = $user_id;
    $types .= "s";
} else {
    // Fallback jika tetap menggunakan cabang/status
    if ($status !== 'all') {
        $query .= " AND k.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($cabang_id !== 'all') {
        $query .= " AND k.id_cabang = ?";
        $params[] = $cabang_id;
        $types .= "i";
    }
}
$query .= " GROUP BY k.id ORDER BY c.nama_cabang ASC, k.nama_karyawan ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$karyawan_data = [];
while ($row = $result->fetch_assoc()) {
    $karyawan_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biodata Karyawan - <?php echo htmlspecialchars($cabang_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { margin: 10mm; size: A4 portrait; }
            .page-break { page-break-after: always; }
            .page-break:last-child { page-break-after: auto; }
            .report-container { box-shadow: none !important; border: 1px solid #e2e8f0 !important; margin-bottom: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 md:p-8 font-sans">
    
    <?php if($action === 'preview'): ?>
    <div class="max-w-3xl mx-auto mb-4 no-print flex justify-end gap-2">
        <button onclick="window.print()" class="px-4 py-2 bg-fuchsia-600 text-white rounded hover:bg-fuchsia-700 font-semibold shadow text-sm">Print / PDF</button>
        <button onclick="downloadImage()" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 font-semibold shadow text-sm">Unduh Image</button>
    </div>
    <?php endif; ?>

    <div id="all-reports">
        <?php if(count($karyawan_data) === 0): ?>
            <div class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-sm text-center">
                <p class="text-slate-500 italic">Tidak ada data karyawan yang ditemukan.</p>
            </div>
        <?php else: ?>
            <?php 
            // Deklarasikan fungsi format tanggal di luar loop
            if (!function_exists('formatIndo')) {
                function formatIndo($dateStr) {
                    if (!$dateStr) return '-';
                    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    $t = explode('-', $dateStr);
                    if(count($t) !== 3) return $dateStr;
                    return $t[2] . ' ' . $bulan[(int)$t[1]] . ' ' . $t[0];
                }
            }

            foreach ($karyawan_data as $index => $data): 
                // Tentukan Avatar default jika foto tidak ada
                $foto_path = 'assets/images/foto_karyawan/' . $data['foto'];
                if (!empty($data['foto']) && file_exists($foto_path)) {
                    $foto = $foto_path;
                } else {
                    $foto = ($data['jenis_kelamin'] == 'P') ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2';
                }

                // Bergabung Sejak
                $join_date_string = substr($data['id_karyawan'], 0, 8);
                if(strlen($join_date_string) === 8) {
                    $join_date_formatted = substr($join_date_string, 0, 4) . '-' . substr($join_date_string, 4, 2) . '-' . substr($join_date_string, 6, 2);
                    $bergabung = formatIndo($join_date_formatted);
                } else {
                    $bergabung = '-';
                }

                $ttl = !empty($data['tempat_lahir']) && !empty($data['tanggal_lahir']) 
                    ? $data['tempat_lahir'] . ', ' . formatIndo($data['tanggal_lahir']) 
                    : '-';
            ?>
            
            <div class="report-container max-w-3xl mx-auto bg-white p-8 sm:p-12 rounded-2xl shadow-sm border border-slate-200 mb-8 page-break relative overflow-hidden">
                <!-- Decorative background elements -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-fuchsia-50 rounded-bl-full -z-0"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-purple-50 rounded-tr-full -z-0"></div>
                
                <div class="relative z-10">
                    <!-- Header Kop Laporan -->
                    <div class="flex items-center justify-between border-b-2 border-slate-800 pb-6 mb-8">
                        <div>
                            <img src="Dinia-Logo.png" alt="Logo" class="h-12 object-contain" onerror="this.style.display='none'">
                        </div>
                        <div class="text-right">
                            <h1 class="text-xl font-black text-slate-800 uppercase tracking-widest">BIODATA KARYAWAN</h1>
                            <p class="text-sm font-semibold text-fuchsia-600 tracking-wider">DINIA HOUSE OF HIJAB TEGAL</p>
                        </div>
                    </div>

                    <!-- Profil Singkat -->
                    <div class="flex flex-col sm:flex-row gap-8 mb-8 items-center sm:items-start">
                        <div class="w-40 h-40 shrink-0">
                            <img src="<?php echo htmlspecialchars($foto); ?>" alt="Foto" class="w-full h-full object-cover rounded-2xl border-4 border-white shadow-lg print:shadow-none print:border print:border-slate-300">
                        </div>
                        <div class="flex-1 text-center sm:text-left mt-2">
                            <h2 class="text-2xl font-bold text-slate-800 mb-2 uppercase"><?php echo htmlspecialchars($data['nama_karyawan']); ?></h2>
                            <div class="inline-flex px-3 py-1 bg-fuchsia-100 text-fuchsia-700 rounded-lg text-sm font-bold tracking-widest mb-4 print:border print:border-fuchsia-200">
                                ID: <?php echo htmlspecialchars($data['id_karyawan']); ?>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div>
                                    <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Jabatan</p>
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($data['nama_jabatan']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Penempatan</p>
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($data['nama_cabang']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Detail -->
                    <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 print:border-slate-300">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-address-card text-fuchsia-500"></i> Informasi Pribadi Lengkap
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-8">
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Jenis Kelamin</p>
                                <p class="font-medium text-slate-800">
                                    <?php echo ($data['jenis_kelamin'] == 'L') ? 'Laki-laki' : (($data['jenis_kelamin'] == 'P') ? 'Perempuan' : '-'); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Tempat, Tanggal Lahir</p>
                                <p class="font-medium text-slate-800"><?php echo htmlspecialchars($ttl); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Agama</p>
                                <p class="font-medium text-slate-800"><?php echo !empty($data['agama']) ? htmlspecialchars($data['agama']) : '-'; ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">No. WhatsApp</p>
                                <p class="font-medium text-slate-800"><?php echo !empty($data['no_whatsapp']) ? htmlspecialchars($data['no_whatsapp']) : '-'; ?></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Alamat Lengkap</p>
                                <p class="font-medium text-slate-800 whitespace-pre-wrap leading-relaxed"><?php echo !empty($data['alamat_lengkap']) ? htmlspecialchars($data['alamat_lengkap']) : '-'; ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Bergabung Sejak</p>
                                <p class="font-medium text-slate-800"><?php echo htmlspecialchars($bergabung); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Status Karyawan</p>
                                <p class="font-medium">
                                    <?php if ($data['status'] === 'aktif'): ?>
                                        <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded text-sm"><i class="fa-solid fa-check-circle mr-1"></i>Aktif</span>
                                    <?php else: ?>
                                        <span class="text-rose-600 bg-rose-50 px-2 py-1 rounded text-sm"><i class="fa-solid fa-times-circle mr-1"></i>Resign</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Section (Signatures) -->
                    <div class="mt-12 pt-8 flex justify-between items-end border-t border-slate-100">
                        <div class="text-xs text-slate-400">
                            <p>Dokumen ini dicetak otomatis dari sistem.</p>
                            <p>Dicetak oleh: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
                            <p>Tanggal: <?php echo date('d-m-Y H:i'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        <?php if($action === 'print'): ?>
        window.onload = function() {
            window.print();
        }
        <?php elseif($action === 'image'): ?>
        window.onload = function() {
            downloadImage();
        }
        <?php endif; ?>

        function downloadImage() {
            const containers = document.querySelectorAll('.report-container');
            if (containers.length === 0) return;
            
            // Provide feedback that download is starting
            alert('Proses unduh gambar mungkin memerlukan waktu jika data banyak. Harap tunggu...');
            
            // Only capture the first one if there are many to prevent browser crash,
            // or we could loop. Since this is an image download, usually user downloads single report.
            // If they export branch "all" as images, we'll download just the first or a long scroll.
            // A long scroll might break html2canvas, but let's try the whole container.
            
            const wrapper = document.getElementById('all-reports');
            
            html2canvas(wrapper, {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Biodata_Karyawan_<?php echo date('Ymd_His'); ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            }).catch(err => {
                console.error('Error rendering image', err);
                alert('Terjadi kesalahan saat membuat gambar.');
            });
        }
    </script>
</body>
</html>
