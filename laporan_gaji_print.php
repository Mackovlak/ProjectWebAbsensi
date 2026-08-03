<?php
require 'config.php';
requireLogin();

$action = $_GET['action'] ?? 'preview';
$tipe = $_GET['tipe'] ?? 'all_cabang';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Filter Variables
$cabang_id = $_GET['cabang_id'] ?? '';
$user_id = $_GET['user_id'] ?? '';

// Otorisasi Ketat
if ($_SESSION['role'] === 'staff') {
    // Karyawan hanya boleh lihat tipe per_karyawan dan WAJIB user_id miliknya sendiri
    $tipe = 'per_karyawan';
    $user_id = $_SESSION['id_karyawan']; 
} else {
    // Admin & Owner bypass check
    if (!isAdmin() && !isOwner()) {
        $_SESSION['error_message'] = "Akses ditolak.";
        header("Location: login.php");
        exit();
    }
}

$filter_text = "Semua Cabang";
if (in_array($tipe, ['lap_gaji_divisi', 'rekap_gaji_divisi']) && !empty($cabang_id) && $cabang_id !== 'all') {
    $stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
    $stmt->bind_param("i", $cabang_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $filter_text = $row['nama_cabang'];
    }
} elseif ($tipe === 'per_karyawan' && !empty($user_id)) {
    $stmt = $conn->prepare("SELECT k.nama_karyawan as nama_lengkap, c.nama_cabang FROM karyawan k LEFT JOIN cabang c ON k.id_cabang = c.id WHERE k.id_karyawan = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $filter_text = "Karyawan: " . $row['nama_lengkap'];
        $emp_nama = $row['nama_lengkap'];
        $emp_cabang = $row['nama_cabang'];
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Gaji - <?php echo htmlspecialchars($filter_text); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Fix html2canvas vertical alignment rendering */
        table th, table td { vertical-align: middle; }
        @media screen {
            body { overflow-x: auto; }
            #report-container { min-width: 800px; margin: 0 auto; }
        }
        @media print {
            body { background: white; padding: 0; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none !important; }
            #report-container { border: none; box-shadow: none; margin: 0; padding: 0; }
            @page { margin: 1cm; size: A4 portrait; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8 font-sans">
    
    <?php if($action === 'preview'): ?>
    <div class="max-w-6xl mx-auto mb-4 no-print flex justify-end gap-2">
        <button onclick="window.print()" class="px-4 py-2 bg-fuchsia-600 text-white rounded hover:bg-fuchsia-700 font-semibold shadow text-sm">Print / PDF</button>
        <button onclick="downloadImage()" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 font-semibold shadow text-sm">Unduh Image</button>
    </div>
    <?php endif; ?>

    <div id="report-container" class="max-w-6xl mx-auto bg-white p-4 sm:p-8 rounded-lg shadow-sm border border-gray-200">
        <!-- Header Laporan -->
        <div class="text-center mb-8 border-b-2 border-gray-800 pb-4">
            <img src="Dinia-Logo.png" alt="" class="h-16 mx-auto mb-2" onerror="this.style.display='none'">
            <h1 class="text-2xl font-bold uppercase tracking-widest text-gray-900"><?php echo ($_SESSION['role'] === 'staff') ? 'Laporan Riwayat Gaji' : 'Laporan Pengeluaran Gaji'; ?></h1>
            <?php if ($tipe === 'lap_gaji_divisi'): ?>
            <p class="text-sm text-gray-500 font-semibold uppercase tracking-wide mt-1">(Pernama dan Percabang)</p>
            <?php elseif ($tipe === 'rekap_gaji_divisi'): ?>
            <p class="text-sm text-gray-500 font-semibold uppercase tracking-wide mt-1">(Percabang)</p>
            <?php elseif ($tipe === 'per_karyawan'): ?>
            <p class="text-sm text-gray-500 font-semibold uppercase tracking-wide mt-1">(Pernama / Personal)</p>
            <?php endif; ?>
            
            <?php if ($tipe !== 'per_karyawan'): ?>
            <p class="text-gray-600 mt-2"><strong><?php echo htmlspecialchars($filter_text); ?></strong></p>
            <?php endif; ?>
            <p class="text-gray-600">Periode: <?php echo date('d M Y', strtotime($start_date)); ?> s/d <?php echo date('d M Y', strtotime($end_date)); ?></p>
            <p class="text-xs text-gray-500 mt-4">Dicetak Oleh : <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
            <p class="text-xs text-gray-500"> Pada Tanggal <?php echo date('d-m-Y | H:i:s'); ?></p>
        </div>

        <!-- Tabel Laporan -->
        <?php if ($tipe === 'per_karyawan' && isset($emp_nama)): ?>
        <div class="mb-4 text-sm text-gray-800">
            <table class="w-auto">
                <tr>
                    <td class="pr-4 font-bold uppercase">Nama</td>
                    <td class="pr-2">:</td>
                    <td class="uppercase"><?php echo htmlspecialchars($emp_nama); ?></td>
                </tr>
                <tr>
                    <td class="pr-4 font-bold uppercase">Cabang</td>
                    <td class="pr-2">:</td>
                    <td class="uppercase"><?php echo htmlspecialchars($emp_cabang ?? '-'); ?></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-max text-left border-collapse">
            <?php if ($tipe === 'per_karyawan'): ?>
            <thead>
                <tr class="bg-gray-300 border-y border-gray-400">
                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-center border-x border-gray-300">No</th>
                    <th class="px-2 py-2 text-xs font-bold text-gray-800 border-r border-gray-300">Periode Gaji</th>
                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Total Penghasilan (A)</th>
                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Total Potongan (B)</th>
                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Gaji Bersih (A-B)</th>
                </tr>
            </thead>
            <?php endif; ?>
            <tbody>
                <?php
                // Kita asumsikan ada data di slip_gaji, tapi kita bisa join
                // dengan user untuk dapat nama dan cabang.
                // Jika ingin mencari total, kita perlu menjumlahkan komponen dari slip_gaji
                
                if ($tipe === 'rekap_gaji_divisi') {
                    $query = "SELECT sg.bulan, sg.tahun, c.nama_cabang, 
                              SUM(sg.total_penghasilan) as total_penghasilan, 
                              SUM(sg.total_potongan) as total_potongan, 
                              SUM(sg.gaji_bersih) as gaji_bersih 
                              FROM slip_gaji sg 
                              LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan 
                              LEFT JOIN cabang c ON k.id_cabang = c.id 
                              WHERE STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
                              AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')";
                } else {
                    $query = "SELECT sg.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', sg.id_karyawan, ')')) as nama_lengkap, c.nama_cabang 
                              FROM slip_gaji sg 
                              LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan 
                              LEFT JOIN cabang c ON k.id_cabang = c.id 
                              WHERE STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
                              AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')";
                }
                
                $params = [$start_date, $end_date];
                $types = "ss";

                if (in_array($tipe, ['lap_gaji_divisi', 'rekap_gaji_divisi']) && !empty($cabang_id) && $cabang_id !== 'all') {
                    $query .= " AND k.id_cabang = ?";
                    $params[] = $cabang_id;
                    $types .= "i";
                } elseif ($tipe === 'per_karyawan' && !empty($user_id)) {
                    $query .= " AND sg.id_karyawan = ?";
                    $params[] = $user_id;
                    $types .= "s";
                }
                
                if ($tipe === 'per_karyawan') {
                    $query .= " ORDER BY sg.tahun ASC, sg.bulan ASC";
                } elseif ($tipe === 'rekap_gaji_divisi') {
                    $query .= " GROUP BY sg.tahun, sg.bulan, c.id, c.nama_cabang ORDER BY c.nama_cabang ASC, sg.tahun ASC, sg.bulan ASC";
                } else {
                    $query .= " ORDER BY c.nama_cabang ASC, k.nama_karyawan ASC, sg.tahun ASC, sg.bulan ASC";
                }
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $res = $stmt->get_result();
                
                $months = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                $no = 1;
                $grand_penghasilan = 0;
                $grand_potongan = 0;
                $grand_bersih = 0;
                
                // For sub-totals in lap_gaji_divisi
                $current_cabang = null;
                $current_karyawan = null;
                $sub_penghasilan = 0;
                $sub_potongan = 0;
                $sub_bersih = 0;
                
                if ($res->num_rows > 0):
                    while ($row = $res->fetch_assoc()):
                        // Ambil nilai yang sudah dihitung dan tersimpan di database
                        $tot_inc = (float)$row['total_penghasilan'];
                        $tot_dec = (float)$row['total_potongan'];
                        $bersih = (float)$row['gaji_bersih'];
                        
                        $grand_penghasilan += $tot_inc;
                        $grand_potongan += $tot_dec;
                        $grand_bersih += $bersih;
                        
                        if ($tipe === 'lap_gaji_divisi') {
                            // Cek jika Cabang berubah
                            if ($current_cabang !== null && $current_cabang !== $row['nama_cabang']) {
                                if ($current_karyawan !== null) {
                                    ?>
                                    <tr class="bg-fuchsia-50 font-semibold border-b-2 border-gray-800">
                                        <td colspan="2" class="px-2 py-2 text-xs text-right text-gray-800 border-x border-gray-300 uppercase italic">Sub Total :</td>
                                        <td class="px-2 py-2 text-xs text-emerald-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_penghasilan, 0, ',', '.'); ?></td>
                                        <td class="px-2 py-2 text-xs text-red-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_potongan, 0, ',', '.'); ?></td>
                                        <td class="px-2 py-2 text-xs text-gray-900 text-right border-r border-gray-300">Rp <?php echo number_format($sub_bersih, 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php
                                    $current_karyawan = null; // Tandai sudah diprint
                                }
                                
                                // Print Spacer
                                ?>
                                <tr><td colspan="5" style="height: 2rem; background-color: white; border: none;"></td></tr>
                                <?php
                            }
                            
                            if ($current_cabang !== $row['nama_cabang']) {
                                ?>
                                <!-- Header Cabang -->
                                <?php if (empty($cabang_id) || $cabang_id === 'all'): ?>
                                <tr class="bg-gray-800 border-y border-gray-900">
                                    <td colspan="5" class="px-3 py-3 text-sm font-bold text-white text-center uppercase tracking-widest border-x border-gray-900">
                                        <?php echo htmlspecialchars($row['nama_cabang'] ?? 'Cabang Dihapus'); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php
                                $current_cabang = $row['nama_cabang'];
                            }
                            
                            if ($current_karyawan !== $row['id_karyawan']) {
                                if ($current_karyawan !== null) {
                                    ?>
                                    <tr class="bg-fuchsia-50 font-semibold border-b-2 border-gray-800">
                                        <td colspan="2" class="px-2 py-2 text-xs text-right text-gray-800 border-x border-gray-300 uppercase italic">Sub Total :</td>
                                        <td class="px-2 py-2 text-xs text-emerald-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_penghasilan, 0, ',', '.'); ?></td>
                                        <td class="px-2 py-2 text-xs text-red-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_potongan, 0, ',', '.'); ?></td>
                                        <td class="px-2 py-2 text-xs text-gray-900 text-right border-r border-gray-300">Rp <?php echo number_format($sub_bersih, 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php
                                }
                                $current_karyawan = $row['id_karyawan'];
                                $sub_penghasilan = 0;
                                $sub_potongan = 0;
                                $sub_bersih = 0;
                                $no = 1; // reset number per karyawan
                                
                                ?>
                                <tr class="bg-gray-200 border-y border-gray-400">
                                    <td colspan="5" class="px-3 py-2 text-sm font-bold text-gray-800 uppercase tracking-wide border-x border-gray-300">
                                        <?php echo htmlspecialchars($row['nama_lengkap']); ?> 
                                    </td>
                                </tr>
                                <tr class="bg-gray-300 border-b border-gray-400">
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-center border-x border-gray-300">No</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 border-r border-gray-300">Periode Gaji</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Total Penghasilan (A)</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Total Potongan (B)</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Gaji Bersih (A-B)</th>
                                </tr>
                                <?php
                            }
                            $sub_penghasilan += $tot_inc;
                            $sub_potongan += $tot_dec;
                            $sub_bersih += $bersih;
                        } elseif ($tipe === 'rekap_gaji_divisi') {
                            if ($current_cabang !== null && $current_cabang !== $row['nama_cabang']) {
                                ?>
                                <tr class="bg-fuchsia-50 font-semibold border-b-2 border-gray-800">
                                    <td colspan="2" class="px-2 py-2 text-xs text-right text-gray-800 border-x border-gray-300 uppercase italic">Sub Total :</td>
                                    <td class="px-2 py-2 text-xs text-emerald-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_penghasilan, 0, ',', '.'); ?></td>
                                    <td class="px-2 py-2 text-xs text-red-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_potongan, 0, ',', '.'); ?></td>
                                    <td class="px-2 py-2 text-xs text-gray-900 text-right border-r border-gray-300">Rp <?php echo number_format($sub_bersih, 0, ',', '.'); ?></td>
                                </tr>
                                <tr><td colspan="5" style="height: 2rem; background-color: white; border: none;"></td></tr>
                                <?php
                                $sub_penghasilan = 0;
                                $sub_potongan = 0;
                                $sub_bersih = 0;
                                $no = 1;
                            }
                            
                            if ($current_cabang !== $row['nama_cabang']) {
                                ?>
                                <?php if (empty($cabang_id) || $cabang_id === 'all'): ?>
                                <tr class="bg-gray-800 border-y border-gray-900">
                                    <td colspan="5" class="px-3 py-3 text-sm font-bold text-white text-center uppercase tracking-widest border-x border-gray-900">
                                        <?php echo htmlspecialchars($row['nama_cabang'] ?? 'Cabang Dihapus'); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr class="bg-gray-300 border-b border-gray-400">
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-center border-x border-gray-300">No</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 border-r border-gray-300">Periode Gaji</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Total Penghasilan (A)</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Total Potongan (B)</th>
                                    <th class="px-2 py-2 text-xs font-bold text-gray-800 text-right border-r border-gray-300">Gaji Bersih (A-B)</th>
                                </tr>
                                <?php
                                $current_cabang = $row['nama_cabang'];
                            }
                            
                            $sub_penghasilan += $tot_inc;
                            $sub_potongan += $tot_dec;
                            $sub_bersih += $bersih;
                        }
                ?>
                <tr class="border-b border-gray-300 hover:bg-gray-50">
                    <td class="px-2 py-2 text-xs text-center border-x border-gray-300"><?php echo $no++; ?></td>
                    <td class="px-2 py-2 text-xs font-semibold text-gray-800 border-r border-gray-300">
                        <?php echo $months[(int)$row['bulan']] . ' ' . $row['tahun']; ?>
                    </td>
                    <td class="px-2 py-2 text-xs text-emerald-600 text-right border-r border-gray-300">Rp <?php echo number_format($tot_inc, 0, ',', '.'); ?></td>
                    <td class="px-2 py-2 text-xs text-red-600 text-right border-r border-gray-300">Rp <?php echo number_format($tot_dec, 0, ',', '.'); ?></td>
                    <td class="px-2 py-2 text-xs text-gray-900 font-bold text-right border-r border-gray-300">Rp <?php echo number_format($bersih, 0, ',', '.'); ?></td>
                </tr>
                <?php 
                    endwhile; 
                    
                    if ($tipe === 'lap_gaji_divisi' && $current_karyawan !== null) {
                        ?>
                        <tr class="bg-fuchsia-50 font-semibold border-b-2 border-gray-800">
                            <td colspan="2" class="px-2 py-2 text-xs text-right text-gray-800 border-x border-gray-300 uppercase italic">Sub Total :</td>
                            <td class="px-2 py-2 text-xs text-emerald-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_penghasilan, 0, ',', '.'); ?></td>
                            <td class="px-2 py-2 text-xs text-red-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_potongan, 0, ',', '.'); ?></td>
                            <td class="px-2 py-2 text-xs text-gray-900 text-right border-r border-gray-300">Rp <?php echo number_format($sub_bersih, 0, ',', '.'); ?></td>
                        </tr>
                        <?php
                    } elseif ($tipe === 'rekap_gaji_divisi' && $current_cabang !== null) {
                        ?>
                        <tr class="bg-fuchsia-50 font-semibold border-b-2 border-gray-800">
                            <td colspan="2" class="px-2 py-2 text-xs text-right text-gray-800 border-x border-gray-300 uppercase italic">Sub Total :</td>
                            <td class="px-2 py-2 text-xs text-emerald-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_penghasilan, 0, ',', '.'); ?></td>
                            <td class="px-2 py-2 text-xs text-red-600 text-right border-r border-gray-300">Rp <?php echo number_format($sub_potongan, 0, ',', '.'); ?></td>
                            <td class="px-2 py-2 text-xs text-gray-900 text-right border-r border-gray-300">Rp <?php echo number_format($sub_bersih, 0, ',', '.'); ?></td>
                        </tr>
                        <?php
                    }
                ?>
                <!-- Baris Total Keseluruhan -->
                <tr class="bg-gray-300 border-b border-gray-400 font-bold">
                    <td colspan="2" class="px-2 py-3 text-sm text-right text-gray-900 border-x border-gray-300 uppercase tracking-wider">Total Keseluruhan</td>
                    <td class="px-2 py-3 text-sm text-emerald-700 text-right border-r border-gray-300">Rp <?php echo number_format($grand_penghasilan, 0, ',', '.'); ?></td>
                    <td class="px-2 py-3 text-sm text-red-700 text-right border-r border-gray-300">Rp <?php echo number_format($grand_potongan, 0, ',', '.'); ?></td>
                    <td class="px-2 py-3 text-base text-gray-900 text-right border-r border-gray-300">Rp <?php echo number_format($grand_bersih, 0, ',', '.'); ?></td>
                </tr>
                <?php else: ?>
                <tr class="border-b border-gray-300"><td colspan="6" class="px-3 py-4 text-center text-sm text-gray-500 italic border-x border-gray-300">Tidak ada data gaji pada periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        
        <!-- System Footer -->
        <div class="pt-12 pb-2 text-center text-xs text-gray-500">
            <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi Dinia Team</p>
            <p>&copy; <?php echo date('Y'); ?> Dinia Team - All Rights Reserved</p>
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
                link.download = 'Laporan_Gaji_<?php echo date('Ymd_His'); ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>

