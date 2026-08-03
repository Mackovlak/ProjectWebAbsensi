<?php
require 'config.php';

// Validasi session khusus staff (atau siapa saja yang memiliki id_karyawan, tapi ini diakses oleh staff)
if (empty($_SESSION['id_karyawan'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

$user_id = $_SESSION['id_karyawan'];
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Ambil nama karyawan
$karyawan_name = '';
$stmt = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $karyawan_name = $row['nama_karyawan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Statistik Detail Karyawan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        @media screen {
            body { overflow-x: auto; }
            #report-container { min-width: 800px; margin: 0 auto; }
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { margin: 10mm; size: A4 portrait; }
            #report-container { padding: 0 !important; border: none !important; box-shadow: none !important; max-width: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 font-sans">
    <div class="max-w-6xl mx-auto mb-4 no-print flex justify-end gap-2">
        <button onclick="window.print()" class="px-4 py-2 bg-fuchsia-600 text-white rounded hover:bg-fuchsia-700 font-semibold shadow text-sm">Print / PDF</button>
        <button onclick="downloadImage()" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 font-semibold shadow text-sm">Unduh Image</button>
    </div>

    <div id="report-container" class="max-w-6xl mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-sm border border-gray-200">
        <!-- Header Laporan -->
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-4">
            <img src="Dinia-Logo.png" alt="" class="h-16 mx-auto mb-2" onerror="this.style.display='none'">
            <h1 class="text-2xl font-bold uppercase tracking-widest text-gray-900">LAPORAN STATISTIK DETAIL KARYAWAN</h1>
            <p class="text-gray-600 mt-1">Nama Karyawan: <strong><?php echo htmlspecialchars($karyawan_name); ?></strong></p>
            <p class="text-gray-600">Periode: <?php echo date('d M Y', strtotime($start_date)); ?> s/d <?php echo date('d M Y', strtotime($end_date)); ?></p>
            <p class="text-xs text-gray-500 mt-4">Dicetak Oleh : <strong><?php echo htmlspecialchars($karyawan_name); ?></strong></p>
            <p class="text-xs text-gray-500"> Pada Tanggal <?php echo date('d-m-Y | H:i:s'); ?></p>
        </div>

        <?php
            $query = "SELECT tanggal, keterangan, status_masuk, jam_masuk FROM absensi WHERE id_karyawan = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $user_id, $start_date, $end_date);
            $stmt->execute();
            $res = $stmt->get_result();

            $data_karyawan = [
                'Cuti' => [],
                'Sakit' => [],
                'Dinas Luar' => [],
                'Alpha' => [],
                'OFF' => [],
                'Hadir (Tepat Waktu)' => [],
                'Hadir (Terlambat)' => []
            ];

            $total_hadir = 0;

            while($row = $res->fetch_assoc()) {
                if ($row['keterangan'] === 'Hadir') {
                    $total_hadir++;
                    if ($row['status_masuk'] === 'Terlambat') {
                        $data_karyawan['Hadir (Terlambat)'][] = $row;
                    } else {
                        $data_karyawan['Hadir (Tepat Waktu)'][] = $row;
                    }
                } elseif ($row['keterangan'] === 'Cuti') {
                    $data_karyawan['Cuti'][] = $row;
                } elseif ($row['keterangan'] === 'Sakit') {
                    $data_karyawan['Sakit'][] = $row;
                } elseif ($row['keterangan'] === 'Alpha') {
                    $data_karyawan['Alpha'][] = $row;
                } elseif ($row['keterangan'] === 'OFF') {
                    $data_karyawan['OFF'][] = $row;
                } elseif ($row['keterangan'] === 'Dinas Luar') {
                    $data_karyawan['Dinas Luar'][] = $row;
                }
            }

            function getIndonesianDay($date) {
                $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                return $days[date('w', strtotime($date))];
            }
        ?>

        <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-fuchsia-50 border border-fuchsia-200 rounded-lg p-4 text-center">
                <p class="text-xs text-fuchsia-600 font-semibold mb-1">Total Hadir</p>
                <p class="text-2xl font-bold text-fuchsia-800"><?php echo $total_hadir; ?></p>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-center">
                <p class="text-xs text-emerald-600 font-semibold mb-1">Hadir Tepat Waktu</p>
                <p class="text-2xl font-bold text-emerald-800"><?php echo count($data_karyawan['Hadir (Tepat Waktu)']); ?></p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
                <p class="text-xs text-orange-600 font-semibold mb-1">Hadir Terlambat</p>
                <p class="text-2xl font-bold text-orange-800"><?php echo count($data_karyawan['Hadir (Terlambat)']); ?></p>
            </div>
            <div class="bg-gray-50 border border-gray-300 rounded-lg p-4 text-center">
                <p class="text-xs text-gray-600 font-semibold mb-1">Total OFF</p>
                <p class="text-2xl font-bold text-gray-800"><?php echo count($data_karyawan['OFF']); ?></p>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                <p class="text-xs text-purple-600 font-semibold mb-1">Total Cuti</p>
                <p class="text-2xl font-bold text-purple-800"><?php echo count($data_karyawan['Cuti']); ?></p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
                <p class="text-xs text-amber-600 font-semibold mb-1">Total Sakit</p>
                <p class="text-2xl font-bold text-amber-800"><?php echo count($data_karyawan['Sakit']); ?></p>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                <p class="text-xs text-red-600 font-semibold mb-1">Total Alpha</p>
                <p class="text-2xl font-bold text-red-800"><?php echo count($data_karyawan['Alpha']); ?></p>
            </div>
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                <p class="text-xs text-purple-600 font-semibold mb-1">Total Dinas Luar</p>
                <p class="text-2xl font-bold text-purple-800"><?php echo count($data_karyawan['Dinas Luar']); ?></p>
            </div>
        </div>

        <?php
            // Hapus dari detail list karena sudah ada di kotak summary
            unset($data_karyawan['Hadir (Tepat Waktu)']);
            unset($data_karyawan['Hadir (Terlambat)']);
            unset($data_karyawan['OFF']);
        ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach($data_karyawan as $kategori => $items): ?>
            <div class="border border-gray-200 rounded-xl overflow-hidden shadow-sm break-inside-avoid mb-4">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-gray-800"><?php echo $kategori; ?></h3>
                    <span class="bg-gray-200 text-gray-700 py-0.5 px-2 rounded-full text-xs font-semibold"><?php echo count($items); ?>x</span>
                </div>
                <div class="p-4">
                    <?php if(count($items) > 0): ?>
                        <ul class="space-y-2">
                            <?php foreach($items as $idx => $item): ?>
                                <li class="flex items-start gap-3 text-sm text-gray-600 border-b border-dashed border-gray-200 pb-2 last:border-0 last:pb-0">
                                    <span class="text-gray-400 font-mono"><?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?>.</span>
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            <?php echo getIndonesianDay($item['tanggal']); ?>, <?php echo date('d M Y', strtotime($item['tanggal'])); ?>
                                        </p>
                                        <?php if($kategori === 'Hadir (Terlambat)' && !empty($item['jam_masuk'])): ?>
                                            <p class="text-xs text-red-500 mt-0.5">Jam Masuk: <?php echo $item['jam_masuk']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-sm text-gray-400 italic text-center py-2">Tidak ada catatan.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- System Footer -->
        <div class="pt-12 pb-2 text-center text-xs text-gray-500">
            <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi Dinia Team</p>
            <p>&copy; <?php echo date('Y'); ?> Dinia Team - All Rights Reserved</p>
        </div>
    </div>

    <script>
        function downloadImage() {
            const container = document.getElementById('report-container');
            const originalShadow = container.style.boxShadow;
            const originalBorder = container.style.border;
            container.style.boxShadow = 'none';
            container.style.border = 'none';
            container.style.padding = '40px';
            
            html2canvas(container, {
                scale: 2,
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
                container.style.boxShadow = originalShadow;
                container.style.border = originalBorder;
                container.style.padding = '';

                const link = document.createElement('a');
                link.download = 'Laporan_Absensi_Karyawan_<?php echo date('Ymd_His'); ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>

