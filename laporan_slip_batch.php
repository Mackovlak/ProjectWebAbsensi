<?php
/**
 * LAPORAN SLIP BATCH (CETAK MASAL)
 * Generate multiple slip gaji in PDF format for printing
 */

require 'config.php';
requireLogin(); // Ganti dari requireAdminOrOwner agar staff bisa masuk dan diverifikasi

// Get parameters
$tipe = $_GET['tipe'] ?? 'cetak_slip_batch';
$user_id = sanitizeInput($_GET['user_id'] ?? '');
$cabang_id = sanitizeInput($_GET['cabang_id'] ?? '');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

function showErrorAndGoBack($title, $message) {
    echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { font-family: sans-serif; background-color: #f1f5f9; }</style>
</head>
<body>
    <script>
        Swal.fire({
            icon: "info",
            title: "' . addslashes($title) . '",
            text: "' . addslashes($message) . '",
            confirmButtonColor: "#3085d6",
            confirmButtonText: "Kembali",
            allowOutsideClick: false
        }).then((result) => {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        });
    </script>
</body>
</html>';
    exit();
}

// Otorisasi Ketat
if ($_SESSION['role'] === 'staff') {
    // Karyawan hanya boleh cetak slip_batch dan WAJIB user_id miliknya sendiri
    $tipe = 'cetak_slip_batch';
    $user_id = $_SESSION['id_karyawan']; 
} else {
    // Admin & Owner bypass check
    if (!isAdmin() && !isOwner()) {
        $_SESSION['error_message'] = "Akses ditolak.";
        header("Location: login.php");
        exit();
    }
}

if ($tipe === 'cetak_slip_batch') {
    if (empty($user_id)) {
        showErrorAndGoBack("Gagal", "Karyawan belum dipilih. Harap pilih karyawan pada filter.");
    }
    // Get slip gaji data for specific user
    $stmt = $conn->prepare("
        SELECT sg.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', sg.id_karyawan, ')')) as nama_karyawan, COALESCE(j.nama_jabatan, '-') as nama_jabatan, COALESCE(c.nama_cabang, '-') as nama_cabang,
               u_admin.ttd_path as admin_ttd, u_admin.nama as admin_nama,
               u_owner.ttd_path as owner_ttd, u_owner.stempel_path as owner_stempel,
               (SELECT u.ttd_path FROM users u WHERE u.id_karyawan = sg.id_karyawan ORDER BY (u.role = 'staff') DESC, u.id ASC LIMIT 1) as staff_ttd
        FROM slip_gaji sg
        LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan
        LEFT JOIN jabatan j ON k.id_jabatan = j.id
        LEFT JOIN cabang c ON k.id_cabang = c.id
        LEFT JOIN users u_admin ON sg.admin_id = u_admin.id
        LEFT JOIN users u_owner ON sg.owner_id = u_owner.id
        WHERE sg.id_karyawan = ?
        AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
        AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')
        GROUP BY sg.id
        ORDER BY sg.tahun ASC, sg.bulan ASC
    ");
    $stmt->bind_param("sss", $user_id, $start_date, $end_date);
} else if ($tipe === 'cetak_slip_divisi') {
    if ($cabang_id === 'all' || empty($cabang_id)) {
        $stmt = $conn->prepare("
            SELECT sg.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', sg.id_karyawan, ')')) as nama_karyawan, COALESCE(j.nama_jabatan, '-') as nama_jabatan, COALESCE(c.nama_cabang, '-') as nama_cabang,
                   u_admin.ttd_path as admin_ttd, u_admin.nama as admin_nama,
                   u_owner.ttd_path as owner_ttd, u_owner.stempel_path as owner_stempel,
                   (SELECT u.ttd_path FROM users u WHERE u.id_karyawan = sg.id_karyawan ORDER BY (u.role = 'staff') DESC, u.id ASC LIMIT 1) as staff_ttd
            FROM slip_gaji sg
            LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan
            LEFT JOIN jabatan j ON k.id_jabatan = j.id
            LEFT JOIN cabang c ON k.id_cabang = c.id
            LEFT JOIN users u_admin ON sg.admin_id = u_admin.id
            LEFT JOIN users u_owner ON sg.owner_id = u_owner.id
            WHERE STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
            AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')
            GROUP BY sg.id
            ORDER BY c.nama_cabang ASC, k.nama_karyawan ASC, sg.tahun ASC, sg.bulan ASC
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
    } else {
        $stmt = $conn->prepare("
            SELECT sg.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', sg.id_karyawan, ')')) as nama_karyawan, COALESCE(j.nama_jabatan, '-') as nama_jabatan, COALESCE(c.nama_cabang, '-') as nama_cabang,
                   u_admin.ttd_path as admin_ttd, u_admin.nama as admin_nama,
                   u_owner.ttd_path as owner_ttd, u_owner.stempel_path as owner_stempel,
                   (SELECT u.ttd_path FROM users u WHERE u.id_karyawan = sg.id_karyawan ORDER BY (u.role = 'staff') DESC, u.id ASC LIMIT 1) as staff_ttd
            FROM slip_gaji sg
            LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan
            LEFT JOIN jabatan j ON k.id_jabatan = j.id
            LEFT JOIN cabang c ON k.id_cabang = c.id
            LEFT JOIN users u_admin ON sg.admin_id = u_admin.id
            LEFT JOIN users u_owner ON sg.owner_id = u_owner.id
            WHERE k.id_cabang = ?
            AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
            AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')
            GROUP BY sg.id
            ORDER BY k.nama_karyawan ASC, sg.tahun ASC, sg.bulan ASC
        ");
        $stmt->bind_param("sss", $cabang_id, $start_date, $end_date);
    }
} else {
    showErrorAndGoBack("Error", "Tipe laporan tidak valid.");
}

$stmt->execute();
$slips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($slips) === 0) {
    if ($tipe === 'cetak_slip_batch') {
        // Ambil data karyawan untuk mencetak template slip kosong (nilai 0)
        $stmt_k = $conn->prepare("
            SELECT k.nama_karyawan, j.nama_jabatan, c.nama_cabang
            FROM karyawan k
            LEFT JOIN jabatan j ON k.id_jabatan = j.id
            LEFT JOIN cabang c ON k.id_cabang = c.id
            WHERE k.id_karyawan = ?
        ");
        $stmt_k->bind_param("s", $user_id);
        $stmt_k->execute();
        $k_info = $stmt_k->get_result()->fetch_assoc();
        $stmt_k->close();

        if (!$k_info) {
            showErrorAndGoBack("Error", "Data karyawan tidak ditemukan.");
        }

        // Buat data array slip kosong (dummy) agar tabel tetap tercetak dengan rapi
        $slips = [[
            'id' => 0,
            'nama_karyawan' => $k_info['nama_karyawan'],
            'nama_jabatan' => $k_info['nama_jabatan'] ?? '-',
            'nama_cabang' => $k_info['nama_cabang'] ?? '-',
            'bulan' => date('n', strtotime($start_date)),
            'tahun' => date('Y', strtotime($start_date)),
            'gaji_pokok' => 0,
            'transport_nominal' => 0, 'transport_hari' => 0, 'transport_total' => 0,
            'overtime_nominal' => 0, 'overtime_jam' => 0, 'overtime_total' => 0,
            'insentif_ahad_nominal' => 0, 'insentif_ahad_hari' => 0, 'insentif_ahad_total' => 0,
            'tunjangan_cs' => 0,
            'akomodasi' => 0,
            'total_penghasilan' => 0,
            'keterlambatan_nominal' => 0, 'keterlambatan_jumlah' => 0, 'keterlambatan_total' => 0,
            'total_potongan' => 0,
            'gaji_bersih' => 0,
            'dibuat_oleh' => $_SESSION['nama_lengkap'] ?? 'Admin',
            'tanggal_cetak' => date('Y-m-d')
        ]];
    } else {
        showErrorAndGoBack("Data Kosong", "Tidak ada data slip gaji pada divisi dan periode yang dipilih.");
    }
}

// Get Owner Name
$stmt = $conn->prepare("SELECT nama FROM users WHERE role = 'owner' LIMIT 1");
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$owner_name = $owner ? $owner['nama'] : 'Owner';
$stmt->close();

$months = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Format rupiah
function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
function ribuan($angka) {
    return number_format($angka, 0, ',', '.');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Slip Gaji Batch - <?php echo htmlspecialchars($slips[0]['nama_karyawan']); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Libraries for Image, PDF & Zip Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <style>
        /* Menggunakan font standar dokumen (Arial) agar identik dengan Excel */
        body { font-family: 'Arial', sans-serif; background-color: #f1f5f9; }
        
        /* Pengaturan Kertas A4 */
        .a4-paper {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 2rem auto;
            padding: 10mm 15mm; /* Margin yang cukup untuk print */
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            page-break-after: always;
        }
        
        /* Hilangkan page break di elemen terakhir */
        .a4-paper:last-child {
            page-break-after: auto;
        }

        /* Warna Custom Sesuai Sketsa Desain */
        .bg-pink-header { background-color: #eab8e4 !important; } 
        .bg-yellow-header { background-color: #eee065 !important; }
        
        /* Styling Tabel Utama */
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th, td { border: 1px solid #000000; padding: 4px 6px; vertical-align: middle; }
        
        /* Tabel tanpa border untuk Kop */
        .table-noborder th, .table-noborder td { border: none !important; padding: 2px 4px; }

        /* Alignment Utilities */
        .flex-rp { display: flex; justify-content: space-between; width: 100%; }

        /* Mode Cetak */
        @media print {
            body { background-color: white; margin: 0; padding: 0; }
            .a4-paper { margin: 0; box-shadow: none; width: 100%; min-height: auto; padding: 5mm 10mm; }
            .print-hidden { display: none !important; }
            /* Memaksa browser mencetak warna background */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        
        /* Loader untuk Zip */
        #zipLoader {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            display: none;
        }
        .spinner {
            border: 5px solid #f3f3f3; border-top: 5px solid #3498db;
            border-radius: 50%; width: 50px; height: 50px;
            animation: spin 1s linear infinite; margin-bottom: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
    <script>
        <?php $action = $_GET['action'] ?? 'preview'; ?>
        
        window.onload = function() { 
            <?php if ($action === 'print'): ?>
                window.print();
            <?php elseif ($action === 'image'): ?>
                downloadZip();
            <?php elseif ($action === 'pdf'): ?>
                downloadPdfZip();
            <?php endif; ?>
        }
    </script>
</head>
<body>

    <!-- Tombol Mengambang (Sembunyi saat print) -->
    <div class="fixed bottom-8 right-8 flex flex-col gap-3 print-hidden z-50">
        <a href="#" onclick="window.history.back()" class="bg-slate-800 hover:bg-slate-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="Kembali">
            <i class="fa-solid fa-arrow-left text-xl"></i>
        </a>
        <button onclick="window.print()" class="bg-fuchsia-600 hover:bg-fuchsia-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="Cetak / Print">
            <i class="fa-solid fa-print text-xl"></i>
        </button>
        <button onclick="downloadPdfZip()" class="bg-red-600 hover:bg-red-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="Unduh PDF / ZIP">
            <i class="fa-solid fa-file-pdf text-xl"></i>
        </button>
        <button onclick="downloadZip()" class="bg-emerald-600 hover:bg-emerald-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="Unduh Image / ZIP">
            <i class="fa-solid fa-image text-xl"></i>
        </button>
    </div>

    <?php foreach ($slips as $slip): 
        // Get penghasilan extra
        $stmt = $conn->prepare("SELECT * FROM slip_gaji_penghasilan WHERE id_slip_gaji = ? ORDER BY urutan");
        $stmt->bind_param("i", $slip['id']);
        $stmt->execute();
        $penghasilan_extra = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get potongan extra
        $stmt = $conn->prepare("SELECT * FROM slip_gaji_potongan WHERE id_slip_gaji = ? ORDER BY urutan");
        $stmt->bind_param("i", $slip['id']);
        $stmt->execute();
        $potongan_extra = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $bulan = (int)$slip['bulan'];
        $tahun = (int)$slip['tahun'];

        // --- FILTER BPJS DARI EXTRA ---
        $bpjs_inc = [];
        $other_inc = [];
        foreach ($penghasilan_extra as $pe) {
            if (in_array($pe['keterangan'], ['Tunj. JHT (BPJS TK)', 'Tunj. JKK (BPJS TK)', 'Tunj. JKM (BPJS TK)'])) {
                $bpjs_inc[$pe['keterangan']] = $pe['nominal'];
            } else {
                $other_inc[] = $pe;
            }
        }
        $has_bpjs_inc = !empty($bpjs_inc);
        $umk_inc = 2484162;
        $pct_jht = 3.70;
        $pct_jkk = 0.24;
        $pct_jkm = 0.30;
        if (isset($bpjs_inc['Tunj. JHT (BPJS TK)']) && $bpjs_inc['Tunj. JHT (BPJS TK)'] > 0) {
            $umk_inc = round($bpjs_inc['Tunj. JHT (BPJS TK)'] / ($pct_jht / 100));
        }

        $bpjs_dec = [];
        $other_dec = [];
        foreach ($potongan_extra as $pe) {
            if (in_array($pe['keterangan'], ['Iuran JHT Peserta (BPJS TK)', 'Iuran Perusahaan (BPJS TK)'])) {
                $bpjs_dec[$pe['keterangan']] = $pe['nominal'];
            } else {
                $other_dec[] = $pe;
            }
        }
        $has_bpjs_dec = !empty($bpjs_dec);
        $umk_dec = 2484162;
        $pct_peserta = 2.00;
        $pct_perusahaan = 4.00; 

        if (isset($bpjs_dec['Iuran JHT Peserta (BPJS TK)']) && $bpjs_dec['Iuran JHT Peserta (BPJS TK)'] > 0) {
            $umk_dec = round($bpjs_dec['Iuran JHT Peserta (BPJS TK)'] / ($pct_peserta / 100));
        }
        if ($umk_dec > 0 && isset($bpjs_dec['Iuran Perusahaan (BPJS TK)']) && $bpjs_dec['Iuran Perusahaan (BPJS TK)'] > 0) {
            $pct_perusahaan = round(($bpjs_dec['Iuran Perusahaan (BPJS TK)'] / $umk_dec) * 100, 2);
        }

        $tanggal_cetak = date('d/m/Y', strtotime($slip['tanggal_cetak'] ?? date('Y-m-d')));
        $no_rev = "GJ" . $tahun . str_pad($bulan, 2, "0", STR_PAD_LEFT) . str_pad($slip['id'], 4, "0", STR_PAD_LEFT);

        $no = 1;
    ?>
    <!-- KERTAS A4 -->
    <div class="a4-paper text-black" data-filename="Slip_Gaji_<?php echo htmlspecialchars($slip['nama_karyawan']); ?>-<?php echo $months[$bulan] . ' ' . $tahun; ?>">
        
        <!-- HEADER (Logo & Judul) -->
        <div class="flex flex-col items-center mb-6">
            <img src="assets/images/logo.png" alt="" class="h-16 mb-2" onerror="this.style.display='none'"> 
            <h1 class="font-bold text-base tracking-wide uppercase">GAJI KARYAWAN</h1>
            <p class="text-[10px]">Periode: <?php echo $months[$bulan] . " " . $tahun; ?></p>
        </div>

        <!-- INFO KARYAWAN (Kop Surat) -->
        <div class="flex justify-between items-start text-xs font-bold mb-4">
            <!-- Kiri -->
            <div class="w-1/2">
                <table class="table-noborder w-full text-xs">
                    <tr>
                        <td class="w-20 uppercase">NAMA</td>
                        <td class="uppercase">: <?php echo htmlspecialchars($slip['nama_karyawan']); ?></td>
                    </tr>
                    <tr>
                        <td class="uppercase">JABATAN</td>
                        <td class="uppercase">: <?php echo htmlspecialchars($slip['nama_jabatan']); ?></td>
                    </tr>
                    <tr>
                        <td class="uppercase">DIVISI</td>
                        <td class="uppercase">: <?php echo htmlspecialchars($slip['nama_cabang']); ?></td>
                    </tr>
                </table>
            </div>
            <!-- Kanan -->
            <div class="w-1/2 flex flex-col items-end">
                <table class="table-noborder w-auto text-xs">
                    <tr>
                        <td class="w-20 uppercase">TANGGAL</td>
                        <td class="uppercase">: <?php echo $tanggal_cetak; ?></td>
                    </tr>
                    <tr>
                        <td class="uppercase">NO REV</td>
                        <td class="uppercase">: <?php echo $no_rev; ?></td>
                    </tr>
                </table>
                <div class="mt-2 text-red-600 italic font-bold text-[11px] tracking-wider pr-1">
                    " BERSIFAT CONFIDENTIAL "
                </div>
            </div>
        </div>

        <!-- TABEL RINCIAN GAJI -->
        <table class="w-full">
            <!-- HEADER: PENGHASILAN -->
            <thead>
                <tr>
                    <th colspan="7" class="bg-pink-header text-center py-1.5 uppercase text-xs tracking-wider border border-black">PENGHASILAN</th>
                </tr>
                <tr class="bg-yellow-header">
                    <th class="w-[5%] text-center border border-black">No.</th>
                    <th class="w-[32%] text-left border border-black pl-2">KETERANGAN</th>
                    <th class="w-[18%] text-left border border-black"></th>
                    <th class="w-[5%] text-center border border-black"></th>
                    <th class="w-[12%] text-center border border-black"></th>
                    <th class="w-[10%] text-center border border-black"></th>
                    <th class="w-[18%] text-center border border-black">JUMLAH</th>
                </tr>
            </thead>
            <tbody>
                <!-- Gaji Pokok -->
                <?php if ($slip['gaji_pokok'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase">GAJI POKOK</td>
                    <td></td><td></td><td></td><td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['gaji_pokok']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Transport -->
                <?php if ($slip['transport_total'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase">TRANSPORT</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['transport_nominal']); ?></span></div></td>
                    <td class="text-center">X</td>
                    <td class="text-center"><?php echo $slip['transport_hari']; ?></td>
                    <td class="pl-2">HARI</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['transport_total']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Overtime -->
                <?php if ($slip['overtime_total'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase">OVERTIME</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['overtime_nominal']); ?></span></div></td>
                    <td class="text-center">X</td>
                    <td class="text-center"><?php echo $slip['overtime_jam']; ?></td>
                    <td class="pl-2">JAM</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['overtime_total']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Insentif Ahad -->
                <?php if ($slip['insentif_ahad_total'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase">INSENTIF HARI AHAD</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['insentif_ahad_nominal']); ?></span></div></td>
                    <td class="text-center">X</td>
                    <td class="text-center"><?php echo $slip['insentif_ahad_hari']; ?></td>
                    <td class="pl-2">KALI</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['insentif_ahad_total']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Tunjangan CS -->
                <?php if ($slip['tunjangan_cs'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase">TUNJANGAN JABATAN</td>
                    <td></td><td></td><td></td><td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['tunjangan_cs']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Akomodasi -->
                <?php if ($slip['akomodasi'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase">AKOMODASI</td>
                    <td></td><td></td><td></td><td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['akomodasi']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Penghasilan Lain-lain -->
                <?php foreach($other_inc as $inc): 
                    $rate = isset($inc['rate']) && $inc['rate'] > 0 ? $inc['rate'] : $inc['nominal'];
                    $qty = isset($inc['qty']) && $inc['qty'] > 0 ? $inc['qty'] : 1;
                ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="pl-2 uppercase"><?php echo htmlspecialchars($inc['keterangan']); ?></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($rate); ?></span></div></td>
                    <td class="text-center">X</td>
                    <td class="text-center"><?php echo floatval($qty); ?></td>
                    <td class="pl-2">KALI</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($inc['nominal']); ?></span></div></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- BPJS TK -->
                <?php if($has_bpjs_inc): 
                    $subtotal_bpjs_inc = array_sum($bpjs_inc);
                ?>
                <tr>
                    <td class="text-center align-top" rowspan="3"><?php echo $no++; ?></td>
                    <td class="pl-2 align-top uppercase" rowspan="3">TUNJANGAN BPJS TK</td>
                    <td class="pl-2 italic text-xs">JAMINAN HARI TUA</td>
                    <td class="text-center"><?php echo number_format($pct_jht, 2, ',', ''); ?>%</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($umk_inc); ?></span></div></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($bpjs_inc['Tunj. JHT (BPJS TK)'] ?? 0); ?></span></div></td>
                    <td class="px-2 align-top" rowspan="3"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($subtotal_bpjs_inc); ?></span></div></td>
                </tr>
                <tr>
                    <td class="pl-2 italic text-xs">JAMINAN KECELAKAAN KERJA</td>
                    <td class="text-center"><?php echo number_format($pct_jkk, 2, ',', ''); ?>%</td>
                    <td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($bpjs_inc['Tunj. JKK (BPJS TK)'] ?? 0); ?></span></div></td>
                </tr>
                <tr>
                    <td class="pl-2 italic text-xs">JAMINAN KEMATIAN</td>
                    <td class="text-center"><?php echo number_format($pct_jkm, 2, ',', ''); ?>%</td>
                    <td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($bpjs_inc['Tunj. JKM (BPJS TK)'] ?? 0); ?></span></div></td>
                </tr>
                <?php endif; ?>

                <!-- TOTAL PENGHASILAN -->
                <tr class="font-bold text-xs">
                    <td colspan="6" class="text-center py-1.5 uppercase">TOTAL DI TERIMA (A)</td>
                    <td class="px-2"><div class="flex-rp"><span>RP</span> <span><?php echo ribuan($slip['total_penghasilan']); ?></span></div></td>
                </tr>

                <!-- HEADER: POTONGAN -->
                <tr>
                    <th colspan="7" class="bg-pink-header text-center py-1.5 uppercase text-xs tracking-wider border border-black">POTONGAN</th>
                </tr>
                <tr class="bg-yellow-header">
                    <th class="w-[5%] text-center border border-black">No.</th>
                    <th class="w-[32%] text-left border border-black pl-2">KETERANGAN</th>
                    <th class="w-[18%] text-left border border-black"></th>
                    <th class="w-[5%] text-center border border-black"></th>
                    <th class="w-[12%] text-center border border-black"></th>
                    <th class="w-[10%] text-center border border-black"></th>
                    <th class="w-[18%] text-center border border-black">JUMLAH</th>
                </tr>

                <?php 
                $no_pot = 1;
                ?>
                <!-- Keterlambatan -->
                <?php if ($slip['keterlambatan_total'] > 0): ?>
                <tr>
                    <td class="text-center"><?php echo $no_pot++; ?></td>
                    <td class="pl-2 uppercase">POT. KETERLAMBATAN</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['keterlambatan_nominal']); ?></span></div></td>
                    <td class="text-center">X</td>
                    <td class="text-center"><?php echo $slip['keterlambatan_jumlah']; ?></td>
                    <td class="pl-2">KALI</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($slip['keterlambatan_total']); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- Potongan Lain-lain -->
                <?php foreach($other_dec as $dec): 
                    $rate = isset($dec['rate']) && $dec['rate'] > 0 ? $dec['rate'] : $dec['nominal'];
                    $qty = isset($dec['qty']) && $dec['qty'] > 0 ? $dec['qty'] : 1;
                ?>
                <tr>
                    <td class="text-center"><?php echo $no_pot++; ?></td>
                    <td class="pl-2 uppercase"><?php echo htmlspecialchars($dec['keterangan']); ?></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($rate); ?></span></div></td>
                    <td class="text-center">X</td>
                    <td class="text-center"><?php echo floatval($qty); ?></td>
                    <td class="pl-2">KALI</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($dec['nominal']); ?></span></div></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- BPJS TK -->
                <?php if($has_bpjs_dec): 
                    $subtotal_bpjs_dec = array_sum($bpjs_dec);
                ?>
                <tr>
                    <td class="text-center align-top" rowspan="2"><?php echo $no_pot++; ?></td>
                    <td class="pl-2 align-top uppercase" rowspan="2">BPJS TK</td>
                    <td class="italic text-center text-xs">PESERTA</td>
                    <td class="text-center"><?php echo number_format($pct_peserta, 0, ',', ''); ?>%</td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($umk_dec); ?></span></div></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($bpjs_dec['Iuran JHT Peserta (BPJS TK)'] ?? 0); ?></span></div></td>
                    <td class="px-2 align-top" rowspan="2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($subtotal_bpjs_dec); ?></span></div></td>
                </tr>
                <tr>
                    <td class="italic text-center text-xs">PERUSAHAAN</td>
                    <td class="text-center"><?php echo number_format($pct_perusahaan, 2, ',', ''); ?>%</td>
                    <td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span><?php echo ribuan($bpjs_dec['Iuran Perusahaan (BPJS TK)'] ?? 0); ?></span></div></td>
                </tr>
                <?php endif; ?>
                
                <!-- If No Potongan at all, show empty row -->
                <?php if ($no_pot == 1): ?>
                <tr>
                    <td class="text-center">1</td>
                    <td class="pl-2">-</td>
                    <td></td><td></td><td></td><td></td>
                    <td class="px-2"><div class="flex-rp"><span>Rp</span> <span>0</span></div></td>
                </tr>
                <?php endif; ?>

                <!-- TOTAL POTONGAN -->
                <tr class="font-bold text-xs">
                    <td colspan="6" class="text-center py-1.5 uppercase">TOTAL POTONGAN (B)</td>
                    <td class="px-2"><div class="flex-rp"><span>RP</span> <span><?php echo ribuan($slip['total_potongan']); ?></span></div></td>
                </tr>
                
                <!-- TOTAL GAJI BERSIH -->
                <tr class="font-bold text-xs bg-pink-header border border-black">
                    <td colspan="6" class="text-center py-1.5 uppercase">TOTAL GAJI BERSIH (A-B)</td>
                    <td class="px-2 border-l border-black"><div class="flex-rp"><span>RP</span> <span><?php echo ribuan($slip['total_penghasilan'] - $slip['total_potongan']); ?></span></div></td>
                </tr>

                <!-- DIGENAPKAN -->
                <tr class="font-bold text-xs">
                    <td colspan="6" class="text-center py-1.5 uppercase">DIGENAPKAN</td>
                    <td class="px-2"><div class="flex-rp"><span>RP</span> <span><?php echo ribuan($slip['gaji_bersih']); ?></span></div></td>
                </tr>

                <!-- QUOTES -->
                <tr class="bg-pink-header">
                    <td colspan="7" class="text-center py-2 text-[11px] font-bold italic tracking-wide">
                        " Team, Thank for your present & performance "
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- AREA TANDA TANGAN (Tanpa Border) -->
        <table class="w-full text-center font-bold text-xs mt-6 table-noborder relative">
            <tr>
                <td class="py-1 w-1/3">DIBUAT OLEH</td>
                <td class="py-1 w-1/3">DI SETUJUI OLEH</td>
                <td class="py-1 w-1/3">DITERIMA OLEH</td>
            </tr>
            <tr>
                <td class="h-20 align-middle relative">
                    <?php if (!empty($slip['status_admin_acc']) && !empty($slip['admin_ttd'])): ?>
                        <img src="assets/uploads/<?php echo htmlspecialchars($slip['admin_ttd']); ?>" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 max-h-[110px] max-w-[180px] object-contain z-10" alt="TTD Admin" crossorigin="anonymous">
                    <?php endif; ?>
                </td>
                <td class="h-20 align-middle relative">
                    <?php if (!empty($slip['status_owner_acc']) && !empty($slip['owner_stempel'])): ?>
                        <img src="assets/uploads/<?php echo htmlspecialchars($slip['owner_stempel']); ?>" class="absolute left-[60%] top-1/2 -translate-x-1/2 -translate-y-1/2 max-h-[120px] max-w-[180px] opacity-80 object-contain z-0" alt="Stempel Owner" crossorigin="anonymous">
                    <?php endif; ?>
                    <?php if (!empty($slip['status_owner_acc']) && !empty($slip['owner_ttd'])): ?>
                        <img src="assets/uploads/<?php echo htmlspecialchars($slip['owner_ttd']); ?>" class="absolute left-[40%] top-1/2 -translate-x-1/2 -translate-y-1/2 max-h-[110px] max-w-[180px] object-contain z-10" alt="TTD Owner" crossorigin="anonymous">
                    <?php endif; ?>
                </td>
                <td class="h-20 align-middle relative">
                    <?php if (!empty($slip['status_karyawan_acc']) && !empty($slip['staff_ttd'])): ?>
                        <img src="assets/uploads/<?php echo htmlspecialchars($slip['staff_ttd']); ?>" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 max-h-[110px] max-w-[180px] object-contain z-10" alt="TTD Karyawan" crossorigin="anonymous">
                    <?php endif; ?>
                </td>
            </tr>
            <!-- Nama Terang -->
            <tr>
                <?php $pembuat = (!empty($slip['status_admin_acc'])) ? ($slip['admin_nama'] ?? $slip['dibuat_oleh']) : ($slip['dibuat_oleh'] ?? ''); ?>
                <td class="py-1 uppercase"><?php echo htmlspecialchars($pembuat); ?></td>
                <td class="py-1 uppercase"><?php echo htmlspecialchars($owner_name); ?></td>
                <td class="py-1 uppercase"><?php echo htmlspecialchars($slip['nama_karyawan']); ?></td>
            </tr>
        </table>

    </div>
    <?php endforeach; ?>

    <!-- Loader Zip -->
    <div id="zipLoader">
        <div class="spinner"></div>
        <h2 id="loaderTitle" class="text-xl font-bold text-slate-700 mb-2">Menyiapkan Unduhan...</h2>
        <p class="text-slate-500">Sedang memproses <span id="zipProgress">0</span> dari <?php echo count($slips); ?> slip gaji.</p>
        <p class="text-xs text-slate-400 mt-2">Mohon jangan tutup halaman ini.</p>
    </div>

    <script>
        async function downloadZip() {
            const loader = document.getElementById('zipLoader');
            const progress = document.getElementById('zipProgress');
            document.getElementById('loaderTitle').innerText = 'Menyiapkan Unduhan PNG...';
            loader.style.display = 'flex';
            
            const zip = new JSZip();
            const papers = document.querySelectorAll('.a4-paper');
            const total = papers.length;
            
            // Sembunyikan elemen yang tidak perlu di-print/di-capture
            const hideElems = document.querySelectorAll('.print-hidden');
            hideElems.forEach(el => el.style.display = 'none');
            
            // Jika hanya 1 halaman, langsung download PNG
            if (total === 1) {
                const paper = papers[0];
                let filename = paper.getAttribute('data-filename') || 'Slip_Gaji_1';
                
                await new Promise(r => setTimeout(r, 100));
                
                const originalMargin = paper.style.margin;
                paper.style.margin = '0';
                
                const scale = 2;
                const dataUrl = await domtoimage.toPng(paper, {
                    bgcolor: '#ffffff',
                    width: paper.clientWidth * scale,
                    height: paper.clientHeight * scale,
                    style: {
                        transform: 'scale(' + scale + ')',
                        transformOrigin: 'top left',
                        margin: '0'
                    }
                });
                
                paper.style.margin = originalMargin;
                
                const link = document.createElement('a');
                link.download = filename + '.png';
                link.href = dataUrl;
                link.click();
                
                hideElems.forEach(el => el.style.display = '');
                loader.style.display = 'none';
                return;
            }

            for (let i = 0; i < total; i++) {
                const paper = papers[i];
                let filename = paper.getAttribute('data-filename');
                if (!filename) {
                    filename = 'Slip_Gaji_' + (i + 1);
                }
                
                // Tambahkan delay kecil agar browser tidak freeze
                await new Promise(r => setTimeout(r, 100));
                
                const originalMargin = paper.style.margin;
                paper.style.margin = '0';
                
                const scale = 2;
                const dataUrl = await domtoimage.toPng(paper, {
                    bgcolor: '#ffffff',
                    width: paper.clientWidth * scale,
                    height: paper.clientHeight * scale,
                    style: {
                        transform: 'scale(' + scale + ')',
                        transformOrigin: 'top left',
                        margin: '0'
                    }
                });
                
                paper.style.margin = originalMargin;
                
                // Convert to blob, then add to zip
                const imgData = dataUrl.split(',')[1];
                zip.file(filename + '.png', imgData, {base64: true});
                
                progress.innerText = (i + 1);
            }
            
            // Kembalikan elemen yang disembunyikan
            hideElems.forEach(el => el.style.display = '');
            
            // Generate Zip
            const content = await zip.generateAsync({type: "blob"});
            saveAs(content, "Slip_Gaji_<?php echo date('F Y', strtotime($start_date)); ?>.zip");
            
            loader.style.display = 'none';
        }

        // =============================================
        // UNDUH PDF / ZIP (1 slip = 1 PDF terpisah)
        // =============================================
        async function downloadPdfZip() {
            const { jsPDF } = window.jspdf;
            const loader = document.getElementById('zipLoader');
            const progress = document.getElementById('zipProgress');
            document.getElementById('loaderTitle').innerText = 'Menyiapkan Unduhan PDF...';
            loader.style.display = 'flex';

            const papers = document.querySelectorAll('.a4-paper');
            const total = papers.length;

            // Sembunyikan elemen yang tidak perlu
            const hideElems = document.querySelectorAll('.print-hidden');
            hideElems.forEach(el => el.style.display = 'none');

            // Helper: render 1 paper menjadi PDF blob
            async function renderPdf(paper) {
                await new Promise(r => setTimeout(r, 100));
                
                const originalMargin = paper.style.margin;
                paper.style.margin = '0';
                
                const scale = 2;
                const dataUrl = await domtoimage.toJpeg(paper, {
                    quality: 0.95,
                    bgcolor: '#ffffff',
                    width: paper.clientWidth * scale,
                    height: paper.clientHeight * scale,
                    style: {
                        transform: 'scale(' + scale + ')',
                        transformOrigin: 'top left',
                        margin: '0'
                    }
                });
                
                paper.style.margin = originalMargin;

                // Ukuran A4 dalam mm
                const pdfW = 210;
                const pdfH = 297;

                const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
                
                // Hitung rasio agar gambar pas di halaman A4
                const imgW_px = paper.clientWidth * scale;
                const imgH_px = paper.clientHeight * scale;
                const canvasRatio = imgH_px / imgW_px;
                
                let imgW = pdfW;
                let imgH = pdfW * canvasRatio;

                // Jika lebih tinggi dari A4, sesuaikan
                if (imgH > pdfH) {
                    imgH = pdfH;
                    imgW = pdfH / canvasRatio;
                }

                // Center secara horizontal
                const offsetX = (pdfW - imgW) / 2;
                doc.addImage(dataUrl, 'JPEG', offsetX, 0, imgW, imgH);

                return doc.output('arraybuffer');
            }

            // Jika hanya 1 halaman, langsung download PDF tunggal
            if (total === 1) {
                const paper = papers[0];
                let filename = paper.getAttribute('data-filename') || 'Slip_Gaji_1';

                const pdfBuffer = await renderPdf(paper);
                const blob = new Blob([pdfBuffer], { type: 'application/pdf' });
                saveAs(blob, filename + '.pdf');

                hideElems.forEach(el => el.style.display = '');
                loader.style.display = 'none';
                return;
            }

            // Multiple halaman → ZIP (1 slip = 1 PDF)
            const zip = new JSZip();

            for (let i = 0; i < total; i++) {
                const paper = papers[i];
                let filename = paper.getAttribute('data-filename');
                if (!filename) {
                    filename = 'Slip_Gaji_' + (i + 1);
                }

                const pdfBuffer = await renderPdf(paper);
                zip.file(filename + '.pdf', pdfBuffer);

                progress.innerText = (i + 1);
            }

            // Kembalikan elemen yang disembunyikan
            hideElems.forEach(el => el.style.display = '');

            // Generate & Download ZIP
            const content = await zip.generateAsync({ type: 'blob' });
            saveAs(content, "Slip_Gaji_<?php echo date('F Y', strtotime($start_date)); ?>.zip");

            loader.style.display = 'none';
        }
    </script>
</body>
</html>

