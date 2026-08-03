<?php
/**
 * EXPORT SLIP GAJI PDF
 * Generate slip gaji in PDF format for printing
 */

require 'config.php';
requireAdmin();

// Get parameters
$id_karyawan = sanitizeInput($_GET['id_karyawan'] ?? '');
$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

// Get slip gaji data
$stmt = $conn->prepare("
    SELECT sg.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', sg.id_karyawan, ')')) as nama_karyawan, COALESCE(j.nama_jabatan, '-') as nama_jabatan, COALESCE(c.nama_cabang, '-') as nama_cabang,
           u_admin.ttd_path as admin_ttd, u_admin.nama as admin_nama,
           u_owner.ttd_path as owner_ttd, u_owner.stempel_path as owner_stempel,
           u_staff.ttd_path as staff_ttd
    FROM slip_gaji sg
    LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan
    LEFT JOIN jabatan j ON k.id_jabatan = j.id
    LEFT JOIN cabang c ON k.id_cabang = c.id
    LEFT JOIN users u_admin ON sg.admin_id = u_admin.id
    LEFT JOIN users u_owner ON sg.owner_id = u_owner.id
    LEFT JOIN users u_staff ON sg.id_karyawan = u_staff.id_karyawan
    WHERE sg.id_karyawan = ? AND sg.bulan = ? AND sg.tahun = ?
");
$stmt->bind_param("sii", $id_karyawan, $bulan, $tahun);
$stmt->execute();
$slip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$slip) {
    die("Slip gaji tidak ditemukan.");
}

// Get Owner Name
$stmt = $conn->prepare("SELECT nama FROM users WHERE role = 'owner' LIMIT 1");
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$owner_name = $owner ? $owner['nama'] : 'Owner';
$stmt->close();

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

$months = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

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
// Default ke 4.24% agar sinkron dengan HTML slip_gaji_form, 
// atau kita bisa hitung persennya jika kita tahu UMK dari JHT Peserta
$pct_perusahaan = 4.00; 

if (isset($bpjs_dec['Iuran JHT Peserta (BPJS TK)']) && $bpjs_dec['Iuran JHT Peserta (BPJS TK)'] > 0) {
    $umk_dec = round($bpjs_dec['Iuran JHT Peserta (BPJS TK)'] / ($pct_peserta / 100));
}
if ($umk_dec > 0 && isset($bpjs_dec['Iuran Perusahaan (BPJS TK)']) && $bpjs_dec['Iuran Perusahaan (BPJS TK)'] > 0) {
    $pct_perusahaan = round(($bpjs_dec['Iuran Perusahaan (BPJS TK)'] / $umk_dec) * 100, 2);
}

// Format rupiah
function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
function ribuan($angka) {
    return number_format($angka, 0, ',', '.');
}

$tanggal_cetak = date('d/m/Y', strtotime($slip['tanggal_cetak'] ?? date('Y-m-d')));
$no_rev = "GJ" . $tahun . str_pad($bulan, 2, "0", STR_PAD_LEFT) . str_pad($slip['id'], 4, "0", STR_PAD_LEFT);

$no = 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Slip Gaji - <?php echo htmlspecialchars($slip['nama_karyawan']); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        }

        /* Warna Custom Sesuai Sketsa Desain */
        .bg-pink-header { background-color: #eab8e4 !important; } 
        .bg-yellow-header { background-color: #eee065 !important; }
        
        /* Styling Tabel Utama */
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th, td { border: 1px solid #000000; padding: 4px 6px; }
        
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
    </style>
    <script>
        window.onload = function() { window.print(); }
    </script>
</head>
<body>

    <!-- Tombol Mengambang (Sembunyi saat print) -->
    <div class="fixed bottom-8 right-8 flex flex-col gap-3 print-hidden z-50">
        <a href="#" onclick="window.history.back()" class="bg-slate-800 hover:bg-slate-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="Kembali">
            <i class="fa-solid fa-arrow-left text-xl"></i>
        </a>
        <button onclick="window.print()" class="bg-fuchsia-600 hover:bg-fuchsia-700 text-white w-14 h-14 rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-110" title="Cetak PDF">
            <i class="fa-solid fa-print text-xl"></i>
        </button>
    </div>

    <!-- KERTAS A4 -->
    <div class="a4-paper text-black">
        
        <!-- HEADER (Logo & Judul) -->
        <div class="flex flex-col items-center mb-6">
            <img src="Dinia-Logo.png" alt="" class="h-16 mb-2" onerror="this.style.display=\'none\'"> 
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

</body>
</html>

