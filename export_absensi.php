<?php
require 'config.php';

// PERBAIKAN: Izinkan akses untuk 'admin' dan 'owner'
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'owner'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Pastikan CSRF token ada sebelum diverifikasi
    if (isset($_POST['csrf_token'])) {
        verifyCSRFToken($_POST['csrf_token']);
    }

    $data = json_decode($_POST['data'], true);
    $format = $_POST['format'];
    $cabang = $_POST['cabang'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if ($format == 'excel') {
        // Export ke Excel menggunakan CSV format
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Absensi_' . str_replace(' ', '_', $cabang) . '_' . date('Y-m-d') . '.csv"');
        
        // UTF-8 BOM untuk Excel
        echo "\xEF\xBB\xBF";
        
        // Buat output
        $output = fopen('php://output', 'w');
        
        // Header informasi
        fputcsv($output, ['REKAP ABSENSI'], ';');
        fputcsv($output, ['Cabang: ' . $cabang], ';');
        fputcsv($output, ['Periode: ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date))], ';');
        fputcsv($output, [''], ';');
        
        // Header kolom - DITAMBAHKAN KOLOM STATUS PULANG
        fputcsv($output, ['No', 'Nama Karyawan', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status Masuk', 'Status Pulang', 'Keterangan'], ';');
        
        // Data
        if (!empty($data)) {
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['no'],
                    html_entity_decode($row['nama'], ENT_QUOTES, 'UTF-8'),
                    $row['tanggal'],
                    $row['jam_masuk'],
                    $row['jam_keluar'],
                    $row['status_masuk'],
                    $row['status_pulang'], // KOLOM BARU
                    $row['keterangan']
                ], ';');
            }
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
            <title>Rekap Absensi - <?php echo htmlspecialchars($cabang); ?></title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 15mm;
                }
                
                body {
                    font-family: 'Arial', sans-serif;
                    font-size: 10px;
                    line-height: 1.5;
                    color: #333;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 25px;
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 15px;
                }
                
                .header h1 {
                    margin: 0;
                    font-size: 20px;
                    color: #667eea;
                    text-transform: uppercase;
                }
                
                .header h2 {
                    margin: 5px 0 0 0;
                    font-size: 16px;
                    font-weight: normal;
                }
                
                .info-box {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    border-left: 4px solid #667eea;
                }

                .info-box p {
                    margin: 3px 0;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }
                
                th {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 10px 8px;
                    text-align: left;
                    font-weight: bold;
                    font-size: 11px;
                    border: 1px solid #667eea;
                }

                th:first-child { text-align: center; width: 5%; }
                th:nth-child(n+3) { text-align: center; }
                
                td {
                    padding: 8px;
                    border: 1px solid #dee2e6;
                }

                td:first-child { text-align: center; }
                td:nth-child(n+3) { text-align: center; }
                
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                
                /* Badge untuk Status Pulang */
                .status-setengah-hari {
                    color: #ff9800;
                    font-weight: 600;
                }
                
                .status-overtime {
                    color: #ffa726;
                    font-weight: 600;
                }
                
                .status-normal {
                    color: #4caf50;
                    font-weight: 600;
                }
                
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 9px;
                    color: #777;
                    border-top: 1px solid #dee2e6;
                    padding-top: 10px;
                }
                
                @media print {
                    body {
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
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
            <div class="header">
                <h1>Rekap Histori Absensi</h1>
                <h2>JAVAG TEAM</h2>
            </div>
            
            <div class="info-box">
                <p><strong>Cabang:</strong> <?php echo htmlspecialchars($cabang); ?></p>
                <p><strong>Periode:</strong> <?php echo date('d F Y', strtotime($start_date)); ?> s/d <?php echo date('d F Y', strtotime($end_date)); ?></p>
                <p><strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i'); ?></p>
                <p><strong>Dicetak oleh:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Karyawan</th>
                        <th>Tanggal</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status Masuk</th>
                        <th>Status Pulang</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $row['no']; ?></td>
                            <td><?php echo htmlspecialchars(html_entity_decode($row['nama'], ENT_QUOTES, 'UTF-8')); ?></td>
                            <td><?php echo $row['tanggal']; ?></td>
                            <td><?php echo $row['jam_masuk']; ?></td>
                            <td><?php echo $row['jam_keluar']; ?></td>
                            <td><?php echo htmlspecialchars($row['status_masuk']); ?></td>
                            <td>
                                <?php 
                                $status_pulang = $row['status_pulang'];
                                if ($status_pulang == 'Setengah Hari') {
                                    echo '<span class="status-setengah-hari">Setengah Hari</span>';
                                } elseif ($status_pulang == 'Over Time') {
                                    echo '<span class="status-overtime">Over Time</span>';
                                } elseif ($status_pulang == 'Normal') {
                                    echo '<span class="status-normal">Normal</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">Tidak ada data untuk diekspor</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi Javag Team</p>
                <p>&copy; <?php echo date('Y'); ?> Javag Team - All Rights Reserved</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
