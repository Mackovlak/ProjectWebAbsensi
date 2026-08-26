<?php
require 'config.php';
requireAdminOrOwner();

$action = $_GET['action'] ?? 'preview';
$cabang_id = $_GET['cabang_id'] ?? 'all';

// Fetch Branch Name
$cabang_name = "Semua Cabang";
if ($cabang_id !== 'all') {
    $stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
    $stmt->bind_param("i", $cabang_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $cabang_name = $row['nama_cabang'];
    }
}

$status = $_GET['status'] ?? 'Aktif';

// Fetch Karyawan
$query = "SELECT k.id_karyawan as id, k.nama_karyawan as nama_lengkap, k.jenis_kelamin, IFNULL(GROUP_CONCAT(u.username SEPARATOR ', '), '-') as username, j.nama_jabatan, c.nama_cabang, k.tanggal_resign 
          FROM karyawan k 
          LEFT JOIN jabatan j ON k.id_jabatan = j.id 
          LEFT JOIN cabang c ON k.id_cabang = c.id 
          LEFT JOIN users u ON k.id_karyawan = u.id_karyawan
          WHERE 1=1";
$params = [];
$types = "";

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
$query .= " GROUP BY k.id ORDER BY c.nama_cabang ASC, k.nama_karyawan ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$karyawan_data = [];
$summary_cabang = [];
$total_laki = 0;
$total_perempuan = 0;

while ($row = $result->fetch_assoc()) {
    $karyawan_data[] = $row;
    $cabang = $row['nama_cabang'] ? $row['nama_cabang'] : 'Tanpa Cabang';
    if (!isset($summary_cabang[$cabang])) {
        $summary_cabang[$cabang] = 0;
    }
    $summary_cabang[$cabang]++;
    
    if (($row['jenis_kelamin'] ?? 'L') == 'P') {
        $total_perempuan++;
    } else {
        $total_laki++;
    }
}
$total_karyawan = count($karyawan_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Karyawan - <?php echo htmlspecialchars($cabang_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Fix html2canvas vertical alignment rendering */
        table th, table td { vertical-align: middle; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { margin: 10mm; size: A4 portrait; }
            #report-container { padding: 0 !important; border: none !important; box-shadow: none !important; max-width: none !important; }
            table th, table td { padding: 4px 6px !important; font-size: 11px !important; }
            .text-2xl { font-size: 18px !important; }
            .mb-8 { margin-bottom: 1rem !important; }
            .mb-6 { margin-bottom: 1rem !important; }
            .mt-12 { margin-top: 1rem !important; }
            .mb-16 { margin-bottom: 2rem !important; }
            p { font-size: 11px !important; }
            
            /* Ringkasan Per Divisi adjustments */
            .p-5 { padding: 0.75rem !important; }
            .py-3 { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
            .px-6 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
            .text-xl { font-size: 14px !important; }
            .text-xs { font-size: 10px !important; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 font-sans">
    
    <?php if($action === 'preview'): ?>
    <div class="max-w-4xl mx-auto mb-4 no-print flex justify-end gap-2">
        <button onclick="window.print()" class="px-4 py-2 bg-fuchsia-600 text-white rounded hover:bg-fuchsia-700 font-semibold shadow text-sm">Print / PDF</button>
        <button onclick="downloadImage()" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 font-semibold shadow text-sm">Unduh Image</button>
    </div>
    <?php endif; ?>

    <div id="report-container" class="max-w-4xl mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-sm border border-gray-200">
        <!-- Header Laporan -->
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-4">
            <img src="assets/images/logo.png" alt="" class="h-16 mx-auto mb-2" onerror="this.style.display='none'">
            <?php
            $title_laporan = "Laporan Data Karyawan";
            if ($status === 'nonaktif') {
                $title_laporan = "Laporan Data Karyawan Resign";
            }
            ?>
            <h1 class="text-2xl font-bold uppercase tracking-widest text-gray-900"><?php echo $title_laporan; ?></h1>
            <p class="text-gray-600 mt-1">Cabang/Divisi: <strong><?php echo htmlspecialchars($cabang_name); ?></strong></p>
            <p class="text-xs text-gray-500 mt-4">Dicetak Oleh : <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
            <p class="text-xs text-gray-500">Pada Tanggal <?php echo date('d-m-Y | H:i:s'); ?></p>
        </div>

        <!-- Summary Section -->
        <div class="mb-6 space-y-4">
            <div class="border-l-4 border-fuchsia-500 bg-fuchsia-50/50 p-4 rounded-r-lg flex flex-wrap gap-4 items-center justify-between">
                <p class="text-sm text-gray-800 font-semibold">Total Karyawan: <span class="font-normal"><?php echo $total_karyawan; ?> Orang</span></p>
                <div class="flex gap-4 text-sm text-gray-700">
                    <span>Laki-Laki: <strong class="text-fuchsia-600"><?php echo $total_laki; ?></strong></span>
                    <span>Perempuan: <strong class="text-pink-600"><?php echo $total_perempuan; ?></strong></span>
                </div>
            </div>
            
            <?php if (!empty($summary_cabang) && $cabang_id === 'all'): ?>
            <div class="border border-gray-200 rounded-lg p-5">
                <h3 class="text-sm font-bold text-fuchsia-600 uppercase tracking-wide text-center mb-4">RINGKASAN KARYAWAN PER CABANG</h3>
                <div class="flex flex-wrap justify-center gap-4">
                    <?php foreach ($summary_cabang as $cabang => $count): ?>
                    <div class="border border-gray-200 rounded-md py-3 px-6 text-center min-w-[120px] bg-white shadow-sm">
                        <p class="text-xs font-semibold text-gray-700 mb-1"><?php echo htmlspecialchars($cabang); ?></p>
                        <p class="text-xl font-bold text-fuchsia-600"><?php echo $count; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabel Laporan -->
        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left border-collapse">
                <thead>
                <tr class="bg-gray-300">
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800 text-center w-10">No</th>
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800">Nama Lengkap</th>
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800 text-center w-12">L/P</th>
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800">Username</th>
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800">Jabatan</th>
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800">Cabang</th>
                    <?php if ($status === 'nonaktif'): ?>
                    <th class="border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800 text-center">Tanggal Resign</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($karyawan_data as $row): 
                ?>
                <tr>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center"><?php echo $no++; ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center font-bold text-gray-700"><?php echo htmlspecialchars($row['jenis_kelamin'] ?? '-'); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($row['nama_jabatan']); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($row['nama_cabang']); ?></td>
                    <?php if ($status === 'nonaktif'): ?>
                    <td class="border border-gray-300 px-3 py-2 text-sm text-gray-600 text-center">
                        <?php 
                        if (!empty($row['tanggal_resign'])) {
                            $hari = array(
                                'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
                                'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
                            );
                            $namaHari = date('l', strtotime($row['tanggal_resign']));
                            echo $hari[$namaHari] . ', ' . date('d-m-Y', strtotime($row['tanggal_resign']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php if(count($karyawan_data) === 0): ?>
                <tr>
                    <td colspan="<?php echo ($status === 'nonaktif') ? '7' : '6'; ?>" class="border border-gray-300 px-3 py-4 text-center text-sm text-gray-500 italic">Tidak ada data karyawan.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <!-- System Footer -->
        <div class="pt-12 pb-2 text-center text-xs text-gray-500">
            <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi Javag Team</p>
            <p>&copy; <?php echo date('Y'); ?> Javag Team - All Rights Reserved</p>
        </div>
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
            const container = document.getElementById('report-container');
            
            // Temporary styles for better image rendering
            const originalShadow = container.style.boxShadow;
            const originalBorder = container.style.border;
            container.style.boxShadow = 'none';
            container.style.border = 'none';
            container.style.padding = '40px'; // ensure enough space
            
            html2canvas(container, {
                scale: 2, // higher resolution
                backgroundColor: '#ffffff',
                onclone: function(clonedDoc) {
                    const elements = clonedDoc.querySelectorAll('td, th');
                    for (let i = 0; i < elements.length; i++) {
                        elements[i].style.paddingBottom = '8px';
                        elements[i].style.verticalAlign = 'top';
                    }
                    const all = clonedDoc.querySelectorAll('*');
                    for (let i = 0; i < all.length; i++) {
                        all[i].style.fontFamily = 'Arial, Helvetica, sans-serif';
                    }
                }
            }).then(canvas => {
                // Restore styles
                container.style.boxShadow = originalShadow;
                container.style.border = originalBorder;
                container.style.padding = '';

                // Download image
                const link = document.createElement('a');
                link.download = 'Laporan_Karyawan_<?php echo date('Ymd_His'); ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                
                <?php if($action === 'image'): ?>
                // If it was direct image download action, maybe close window or alert
                // setTimeout(() => window.close(), 1000); 
                <?php endif; ?>
            });
        }
    </script>
</body>
</html>

