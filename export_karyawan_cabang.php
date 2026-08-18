<?php
require 'config.php';

// Cek apakah user sudah login dan adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode($_POST['data'], true);
    $nama_cabang = $_POST['cabang'];
    $alamat_cabang = $_POST['alamat'];
    $format = isset($_POST['format']) ? $_POST['format'] : 'excel';
    
    if ($format == 'excel') {
        // Export ke Excel menggunakan CSV format
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Karyawan_' . str_replace(' ', '_', $nama_cabang) . '_' . date('Y-m-d') . '.csv"');
        
        // UTF-8 BOM untuk Excel
        echo "\xEF\xBB\xBF";
        
        // Buat output
        $output = fopen('php://output', 'w');
        
        // Header informasi
        fputcsv($output, ['DAFTAR KARYAWAN'], ';');
        fputcsv($output, [''], ';');
        fputcsv($output, ['Cabang: ' . $nama_cabang], ';');
        fputcsv($output, ['Alamat: ' . $alamat_cabang], ';');
        fputcsv($output, ['Tanggal Export: ' . date('d-m-Y H:i')], ';');
        fputcsv($output, [''], ';');
        
        // Header kolom
        fputcsv($output, ['No', 'ID Karyawan', 'Nama Karyawan', 'Jabatan', 'Status'], ';');
        
        // Data
        if (!empty($data)) {
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['no'],
                    $row['id'],
                    $row['nama'],
                    $row['jabatan'],
                    $row['status']
                ], ';');
            }
            
            // Total row
            fputcsv($output, [''], ';');
            fputcsv($output, ['Total Karyawan:', count($data)], ';');
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
            <title>Daftar Karyawan - <?php echo htmlspecialchars($nama_cabang); ?></title>
            <style>
                @page {
                    size: A4;
                    margin: 15mm;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Arial', sans-serif;
                    font-size: 11px;
                    line-height: 1.5;
                    color: #333;
                }
                
                .container {
                    width: 100%;
                    max-width: 100%;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 25px;
                    padding-bottom: 15px;
                    border-bottom: 3px solid #2575fc;
                }
                
                .header h1 {
                    font-size: 22px;
                    color: #2575fc;
                    margin-bottom: 5px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                
                .header h2 {
                    font-size: 18px;
                    color: #333;
                    font-weight: normal;
                    margin-bottom: 3px;
                }
                
                .header p {
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }
                
                .info-box {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    border-left: 4px solid #2575fc;
                }
                
                .info-box p {
                    margin: 4px 0;
                    font-size: 12px;
                }
                
                .info-box strong {
                    color: #2575fc;
                    display: inline-block;
                    width: 120px;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                    font-size: 11px;
                }
                
                thead {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                
                th {
                    padding: 10px 8px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 11px;
                    border: 1px solid #667eea;
                }
                
                th:first-child {
                    width: 40px;
                    text-align: center;
                }
                
                th:nth-child(2) {
                    width: 100px;
                }
                
                th:last-child {
                    width: 80px;
                    text-align: center;
                }
                
                td {
                    padding: 8px;
                    border: 1px solid #dee2e6;
                    font-size: 10px;
                }
                
                td:first-child {
                    text-align: center;
                    font-weight: 500;
                }
                
                td:last-child {
                    text-align: center;
                }
                
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                
                .id-karyawan {
                    font-family: monospace;
                    color: #2575fc;
                    font-weight: 600;
                }
                
                .jabatan-badge {
                    background: #667eea;
                    color: white;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 9px;
                    font-weight: 600;
                    display: inline-block;
                }
                
                .status-aktif {
                    color: #fff;
                    background: #28a745;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-weight: 600;
                    font-size: 9px;
                }
                
                .status-nonaktif {
                    color: #fff;
                    background: #dc3545;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-weight: 600;
                    font-size: 9px;
                }
                
                .summary-box {
                    margin-top: 30px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 5px;
                    border: 1px solid #dee2e6;
                    text-align: center;
                }
                
                .summary-box h3 {
                    color: #2575fc;
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                
                .summary-item {
                    display: inline-block;
                    margin: 0 20px;
                }
                
                .summary-label {
                    color: #666;
                    font-size: 10px;
                    margin-bottom: 5px;
                }
                
                .summary-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2575fc;
                }
                
                .footer {
                    margin-top: 40px;
                    padding-top: 15px;
                    border-top: 1px solid #dee2e6;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                }
                
                .footer p {
                    margin: 2px 0;
                }
                
                .watermark {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 100px;
                    color: rgba(0, 0, 0, 0.05);
                    z-index: -1;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                
                @media print {
                    body {
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
                    }
                    
                    .header {
                        page-break-after: avoid;
                    }
                    
                    table {
                        page-break-inside: avoid;
                    }
                }
            </style>
            <script>
                window.onload = function() {
                    window.print();
                    // BARIS window.close() DIHAPUS DARI SINI
                }
            </script>
        </head>
        <body>
            <div class="watermark">JAVAG TEAM</div>
            
            <div class="container">
                <div class="header">
                    <h1>DAFTAR KARYAWAN</h1>
                    <h2>JAVAG TEAM</h2>
                    <p>Dokumen Resmi Karyawan</p>
                </div>
                
                <div class="info-box">
                    <p><strong>Nama Cabang:</strong> <?php echo htmlspecialchars($nama_cabang); ?></p>
                    <p><strong>Alamat:</strong> <?php echo htmlspecialchars($alamat_cabang); ?></p>
                    <p><strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i'); ?></p>
                    <p><strong>Dicetak Oleh:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Karyawan</th>
                            <th>Nama Karyawan</th>
                            <th>Jabatan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!empty($data)): 
                            $total_aktif = 0;
                            $total_nonaktif = 0;
                            
                            foreach ($data as $row): 
                                if ($row['status'] == 'Aktif') {
                                    $total_aktif++;
                                } else {
                                    $total_nonaktif++;
                                }
                        ?>
                            <tr>
                                <td><?php echo $row['no']; ?></td>
                                <td class="id-karyawan"><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                <td>
                                    <span class="jabatan-badge">
                                        <?php echo htmlspecialchars($row['jabatan']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['status'] == 'Aktif'): ?>
                                        <span class="status-aktif">Aktif</span>
                                    <?php else: ?>
                                        <span class="status-nonaktif">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">
                                    Tidak ada data karyawan untuk cabang ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($data)): ?>
                    <div class="summary-box">
                        <h3>Ringkasan Data Karyawan</h3>
                        <div class="summary-item">
                            <div class="summary-label">Total Karyawan</div>
                            <div class="summary-value"><?php echo count($data); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Aktif</div>
                            <div class="summary-value" style="color: #28a745;"><?php echo $total_aktif; ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Tidak Aktif</div>
                            <div class="summary-value" style="color: #dc3545;"><?php echo $total_nonaktif; ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="footer">
                    <p><strong>© <?php echo date('Y'); ?> JAVAG TEAM</strong></p>
                    <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi</p>
                    <p>Cabang: <?php echo htmlspecialchars($nama_cabang); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>

