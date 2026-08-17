<?php
require 'config.php';

// PERBAIKAN: Izinkan akses untuk 'admin' dan 'owner'
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'owner'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (isset($_POST['csrf_token'])) {
        verifyCSRFToken($_POST['csrf_token']);
    }
    
    $data = json_decode($_POST['data'], true);
    $format = $_POST['format'];
    $cabang = $_POST['cabang'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // PERBAIKAN: Ambil semua total termasuk setengah_hari dan minggu
    $total_hadir = $_POST['total_hadir'] ?? 0;
    $total_tepat_waktu = $_POST['total_tepat_waktu'] ?? 0;
    $total_terlambat = $_POST['total_terlambat'] ?? 0;
    $total_setengah_hari = $_POST['total_setengah_hari'] ?? 0;
    $total_overtime = $_POST['total_overtime'] ?? 0;
    $total_minggu = $_POST['total_minggu'] ?? 0; // TAMBAHAN BARU!
    $total_off = $_POST['total_off'] ?? 0;
    $total_sakit = $_POST['total_sakit'] ?? 0;
    $total_cuti = $_POST['total_cuti'] ?? 0;
    $total_alpha = $_POST['total_alpha'] ?? 0;
    
    if ($format == 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Statistik_Absensi_' . str_replace(' ', '_', $cabang) . '_' . date('Y-m-d') . '.csv"');
        
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['STATISTIK ABSENSI KARYAWAN'], ';');
        fputcsv($output, [''], ';');
        fputcsv($output, ['Cabang: ' . $cabang], ';');
        fputcsv($output, ['Periode: ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date))], ';');
        fputcsv($output, ['Tanggal Cetak: ' . date('d-m-Y H:i')], ';');
        fputcsv($output, [''], ';');
        
        // PERBAIKAN: Menambahkan kolom 'Setengah Hari', 'Overtime', dan 'Minggu'
        fputcsv($output, ['No', 'Nama Karyawan', 'Total Hadir', 'Tepat Waktu', 'Terlambat', 'Setengah Hari', 'Overtime', 'Minggu', 'OFF', 'Sakit', 'Cuti', 'Alpha'], ';');
        
        if (!empty($data)) {
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['no'],
                    html_entity_decode($row['nama'], ENT_QUOTES, 'UTF-8'),
                    $row['hadir'] ?: '0',
                    $row['tepat_waktu'] ?: '0',
                    $row['terlambat'] ?: '0',
                    $row['setengah_hari'] ?: '0',
                    $row['overtime'] ?: '0',
                    $row['minggu'] ?: '0', // TAMBAHAN BARU!
                    $row['off'] ?: '0',
                    $row['sakit'] ?: '0',
                    $row['cuti'] ?: '0',
                    $row['alpha'] ?: '0'
                ], ';');
            }
            fputcsv($output, [''], ';');
            fputcsv($output, ['', 'TOTAL', $total_hadir, $total_tepat_waktu, $total_terlambat, $total_setengah_hari, $total_overtime, $total_minggu, $total_off, $total_sakit, $total_cuti, $total_alpha], ';');
        }
        
        fclose($output);
        exit;
        
    } elseif ($format == 'pdf') {
        // Export ke PDF menggunakan HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Statistik Absensi - <?php echo htmlspecialchars($cabang); ?></title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                
                body {
                    font-family: 'Arial', sans-serif;
                    font-size: 9px;
                    line-height: 1.3;
                    color: #333;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 15px;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #667eea;
                }
                
                .header h1 {
                    font-size: 18px;
                    color: #667eea;
                    margin-bottom: 3px;
                    text-transform: uppercase;
                }
                
                .header h2 {
                    font-size: 14px;
                    font-weight: normal;
                    margin-bottom: 2px;
                }
                
                .header p {
                    font-size: 11px;
                }
                
                .info-box {
                    background: #f8f9fa;
                    padding: 8px 12px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                    border-left: 3px solid #667eea;
                }
                @media print {
                    @page {
                        margin: 1cm;
                        size: A4 portrait;
                    }
                }
                
                .info-box p {
                    margin: 1px 0;
                    font-size: 10px;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 9px;
                }
                
                thead {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                
                th {
                    padding: 6px 5px;
                    text-align: center;
                    font-weight: 600;
                    font-size: 10px;
                    border: 1px solid #667eea;
                    color: white;
                }
                
                /* Sub-header lebih gelap */
                thead tr:nth-child(2) th {
                    background: linear-gradient(135deg, #4a5fc1, #5a3d7d);
                    font-size: 9px;
                }
                
                /* Sub-header Minggu - Orange */
                thead tr:nth-child(2) th:nth-child(6) {
                    background: linear-gradient(135deg, #e67e22, #d35400);
                    font-weight: 700;
                }
                
                th[rowspan="2"]:first-child { width: 4%; }
                th[rowspan="2"]:nth-child(2) { text-align: center; }
                
                td {
                    padding: 5px 5px;
                    border: 1px solid #dee2e6;
                    text-align: center;
                }

                td:nth-child(2) {
                    text-align: left;
                }
                
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                
                .status-dot {
                    display: inline-block;
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    margin-right: 5px;
                }
                .dot-tepat-waktu { background-color: #28a745; }
                .dot-terlambat { background-color: #dc3545; }
                .dot-setengah-hari { background-color: #ff9800; }
                .dot-overtime { background-color: #ffa726; }
                .dot-minggu { background-color: #ff6f00; }
                
                .total-row {
                    background: #e9ecef;
                    font-weight: bold;
                    font-size: 10px;
                }
                
                .total-row td {
                    padding: 6px 5px;
                    border: 1px solid #c8ced3;
                    text-align: center !important;
                }
                
                .footer {
                    margin-top: 20px;
                    padding-top: 8px;
                    border-top: 1px solid #dee2e6;
                    text-align: center;
                    font-size: 8px;
                    color: #666;
                }

                .footer p { margin: 2px 0; }
                
                @media print {
                    body {
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
                    }
                    thead, .total-row {
                        page-break-inside: avoid;
                    }
                }
            </style>
            <script>
                window.onload = function() {
                    window.print();
                }
            </script>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Statistik Absensi Karyawan</h1>
                    <h2>JAVAG TEAM</h2>
                    <p><?php echo htmlspecialchars($cabang); ?></p>
                </div>
                
                <div class="info-box">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="width: 150px; padding: 3px 0; border: none; font-weight: bold; text-align: left;">Cabang</td>
                            <td style="width: 10px; padding: 3px 5px; border: none; font-weight: bold; text-align: left;">:</td>
                            <td style="padding: 3px 0; border: none; text-align: left;"><?php echo htmlspecialchars($cabang); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 150px; padding: 3px 0; border: none; font-weight: bold; text-align: left;">Periode</td>
                            <td style="width: 10px; padding: 3px 5px; border: none; font-weight: bold; text-align: left;">:</td>
                            <td style="padding: 3px 0; border: none; text-align: left;"><?php echo date('d F Y', strtotime($start_date)); ?> s/d <?php echo date('d F Y', strtotime($end_date)); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 150px; padding: 3px 0; border: none; font-weight: bold; text-align: left;">Tanggal Cetak</td>
                            <td style="width: 10px; padding: 3px 5px; border: none; font-weight: bold; text-align: left;">:</td>
                            <td style="padding: 3px 0; border: none; text-align: left;"><?php echo date('d F Y H:i'); ?></td>
                        </tr>
                    </table>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2">No</th>
                            <th rowspan="2">Nama Karyawan</th>
                            <th colspan="6">Kehadiran</th>
                            <th rowspan="2">OFF</th>
                            <th rowspan="2">Sakit</th>
                            <th rowspan="2">Cuti</th>
                            <th rowspan="2">Alpha</th>
                        </tr>
                        <tr>
                            <th>Total</th>
                            <th>Tepat Waktu</th>
                            <th>Terlambat</th>
                            <th>Setengah Hari</th>
                            <th>Overtime</th>
                            <th>Ahad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data)): ?>
                            <?php foreach ($data as $row): ?>
                            <tr>
                                <td><?php echo $row['no']; ?></td>
                                <td><?php echo htmlspecialchars(html_entity_decode($row['nama'], ENT_QUOTES, 'UTF-8')); ?></td>
                                <td><?php echo $row['hadir'] > 0 ? $row['hadir'] : '-'; ?></td>
                                <td><?php echo $row['tepat_waktu'] > 0 ? '<span class="status-dot dot-tepat-waktu"></span>' . $row['tepat_waktu'] : '-'; ?></td>
                                <td><?php echo $row['terlambat'] > 0 ? '<span class="status-dot dot-terlambat"></span>' . $row['terlambat'] : '-'; ?></td>
                                <td><?php echo ($row['setengah_hari'] ?? 0) > 0 ? '<span class="status-dot dot-setengah-hari"></span>' . $row['setengah_hari'] : '-'; ?></td>
                                <td><?php echo ($row['overtime'] ?? 0) > 0 ? '<span class="status-dot dot-overtime"></span>' . $row['overtime'] : '-'; ?></td>
                                <td><?php echo ($row['minggu'] ?? 0) > 0 ? '<span class="status-dot dot-minggu"></span>' . $row['minggu'] : '-'; ?></td>
                                <td><?php echo $row['off'] > 0 ? $row['off'] : '-'; ?></td>
                                <td><?php echo $row['sakit'] > 0 ? $row['sakit'] : '-'; ?></td>
                                <td><?php echo $row['cuti'] > 0 ? $row['cuti'] : '-'; ?></td>
                                <td><?php echo $row['alpha'] > 0 ? $row['alpha'] : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <tr class="total-row">
                                <td colspan="2">TOTAL</td>
                                <td><?php echo $total_hadir; ?></td>
                                <td><?php echo $total_tepat_waktu; ?></td>
                                <td><?php echo $total_terlambat; ?></td>
                                <td><?php echo $total_setengah_hari; ?></td>
                                <td><?php echo $total_overtime; ?></td>
                                <td><?php echo $total_minggu; ?></td>
                                <td><?php echo $total_off; ?></td>
                                <td><?php echo $total_sakit; ?></td>
                                <td><?php echo $total_cuti; ?></td>
                                <td><?php echo $total_alpha; ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" style="padding: 20px;">Tidak ada data untuk diekspor</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="footer">
                    <p><strong>© <?php echo date('Y'); ?> JAVAG TEAM</strong></p>
                    <p>Dokumen ini dicetak secara otomatis dan resmi dari Sistem Absensi Javag Team</p>
                    <p>Dicetak oleh: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
