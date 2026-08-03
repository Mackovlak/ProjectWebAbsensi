<?php
require 'config.php';

// Cek apakah user sudah login dan adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode($_POST['data'], true);

    // Menghitung jumlah karyawan per cabang dan gender
    $cabang_counts = [];
    $total_laki = 0;
    $total_perempuan = 0;
    if (!empty($data)) {
        foreach ($data as $row) {
            $cabang = $row['cabang'];
            if (!isset($cabang_counts[$cabang])) {
                $cabang_counts[$cabang] = 0;
            }
            $cabang_counts[$cabang]++;
            
            if (($row['jenis_kelamin'] ?? 'L') == 'P') {
                $total_perempuan++;
            } else {
                $total_laki++;
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Data Karyawan - Dinia Team</title>
        <style>
            @page {
                size: A4;
                margin: 15mm;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                font-size: 10px;
                line-height: 1.4;
                color: #333;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
            }

            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #667eea;
            }
            
            .header h1 {
                font-size: 20px;
                color: #667eea;
                margin-bottom: 5px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .header h2 {
                font-size: 16px;
                font-weight: normal;
                margin: 0;
            }
            
            .info-box {
                background: #f8f9fa;
                padding: 10px 15px;
                border-radius: 5px;
                margin-bottom: 15px;
                border-left: 4px solid #667eea;
                display: flex;
                justify-content: space-between;
            }
            
            .info-box p {
                margin: 2px 0;
                font-size: 11px;
            }
            
            .summary-box {
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
            }
            
            .summary-box h3 {
                color: #667eea;
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 13px;
                text-align: center;
                text-transform: uppercase;
            }
            
            .summary-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
            
            .summary-item {
                background: white;
                padding: 8px 12px;
                border-radius: 5px;
                border: 1px solid #e0e0e0;
                text-align: center;
                min-width: 120px;
            }
            
            .summary-item .label {
                font-size: 10px;
                color: #555;
                margin-bottom: 4px;
                font-weight: bold;
            }
            
            .summary-item .value {
                font-size: 16px;
                font-weight: bold;
                color: #667eea;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
            }
            
            thead {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            th {
                padding: 8px 6px;
                text-align: left;
                font-weight: 600;
                font-size: 11px;
                border: 1px solid #667eea;
            }
            
            th:first-child, td:first-child {
                text-align: center;
                width: 5%;
            }
            
            td {
                padding: 6px;
                border: 1px solid #dee2e6;
            }
            
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .footer {
                margin-top: 25px;
                padding-top: 10px;
                border-top: 1px solid #dee2e6;
                text-align: center;
                font-size: 9px;
                color: #666;
            }
            
            .footer p { margin: 2px 0; }

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
        <div class="container">
            <div class="header">
                <h1>Data Seluruh Karyawan</h1>
                <h2>DINIA TEAM</h2>
            </div>
            
            <div class="info-box">
                <div>
                    <p><strong>Total Karyawan:</strong> <?php echo count($data); ?> Orang</p>
                    <p style="margin-top: 5px;">Laki-Laki: <?php echo $total_laki; ?> &nbsp;|&nbsp; Perempuan: <?php echo $total_perempuan; ?></p>
                </div>
                <div>
                    <p><strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i'); ?></p>
                </div>
            </div>
            
            <?php if (!empty($cabang_counts)): ?>
            <div class="summary-box">
                <h3>Ringkasan Karyawan per Cabang</h3>
                <div class="summary-grid">
                    <?php foreach ($cabang_counts as $cabang => $count): ?>
                    <div class="summary-item">
                        <div class="label"><?php echo htmlspecialchars($cabang); ?></div>
                        <div class="value"><?php echo $count; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>ID Karyawan</th>
                        <th>Nama Karyawan</th>
                        <th>L/P</th>
                        <th>Jabatan</th>
                        <th>Cabang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $row['no']; ?></td>
                            <td><?php echo htmlspecialchars($row['id_karyawan']); ?></td>
                            <td><?php echo htmlspecialchars(html_entity_decode($row['nama'], ENT_QUOTES, 'UTF-8')); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($row['jenis_kelamin'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['jabatan']); ?></td>
                            <td><?php echo htmlspecialchars($row['cabang']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data untuk diekspor</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi Dinia Team</p>
                <p>&copy; <?php echo date('Y'); ?> Dinia Team - All Rights Reserved</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
