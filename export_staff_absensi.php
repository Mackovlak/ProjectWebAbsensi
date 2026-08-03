<?php
require 'config.php';

// Cek apakah user sudah login dan adalah staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    die("Akses ditolak");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if(isset($_POST['csrf_token'])) {
        verifyCSRFToken($_POST['csrf_token']);
    }
    
    $data = json_decode($_POST['data'], true);
    $id_karyawan = $_POST['id_karyawan'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Ambil nama karyawan dari database
    $stmt_nama = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
    $stmt_nama->bind_param("s", $id_karyawan);
    $stmt_nama->execute();
    $result_nama = $stmt_nama->get_result();
    $nama_karyawan = $result_nama->fetch_assoc()['nama_karyawan'] ?? 'Tidak Ditemukan';
    $stmt_nama->close();
    
    // Export ke PDF menggunakan HTML
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Absensi - <?php echo htmlspecialchars($nama_karyawan); ?></title>
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
                font-size: 16px;
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
                text-align: center;
                font-weight: 600;
                font-size: 11px;
                border: 1px solid #667eea;
                color: white;
            }
            
            th:first-child {
                width: 40px;
            }
            
            th:nth-child(5), th:nth-child(6) { 
                width: 90px;
            }
            
            td {
                padding: 8px;
                border: 1px solid #dee2e6;
                font-size: 10px;
                text-align: center;
            }
            
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            .status-hadir { 
                color: #fff;
                background: #00d2ff; 
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: 600;
            }
            .status-off { 
                color: #fff;
                background: #6c757d; 
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: 600;
            }
            .status-sakit { 
                color: #fff;
                background: #f5576c; 
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: 600;
            }
            .status-cuti { 
                color: #fff;
                background: #4facfe; 
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: 600;
            }
            .status-alpha { 
                color: #fff;
                background: #fa709a; 
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: 600;
            }
            
            .summary-box {
                margin-top: 30px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 5px;
                border: 1px solid #dee2e6;
            }
            
            .summary-box h3 {
                color: #2575fc;
                margin-bottom: 10px;
                font-size: 14px;
            }
            
            .summary-grid {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .summary-item {
                flex: 1;
                text-align: center;
                padding: 10px;
                background: white;
                border-radius: 5px;
                border-left: 4px solid #2575fc;
                min-width: 80px;
            }
            
            .summary-item .label {
                font-size: 10px;
                color: #666;
                margin-bottom: 5px;
            }
            
            .summary-item .value {
                font-size: 18px;
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
                
                .header, .summary-box {
                    page-break-inside: avoid;
                }
                
                table {
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
        <div class="watermark">DINIA <br> TEAM</div>
        
        <div class="container">
            <div class="header">
                <h1>LAPORAN ABSENSI KARYAWAN</h1>
                <h2>DINIA TEAM</h2>
                <p>Dokumen Resmi Absensi</p>
            </div>
            
            <div class="info-box">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="width: 150px; padding: 3px 0; border: none; font-weight: bold; text-align: left;">Nama Karyawan</td>
                        <td style="width: 10px; padding: 3px 5px; border: none; font-weight: bold; text-align: left;">:</td>
                        <td style="padding: 3px 0; border: none; text-align: left;"><?php echo htmlspecialchars($nama_karyawan); ?></td>
                    </tr>
                    <tr>
                        <td style="width: 150px; padding: 3px 0; border: none; font-weight: bold; text-align: left;">ID Karyawan</td>
                        <td style="width: 10px; padding: 3px 5px; border: none; font-weight: bold; text-align: left;">:</td>
                        <td style="padding: 3px 0; border: none; text-align: left;"><?php echo htmlspecialchars($id_karyawan); ?></td>
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
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status Masuk</th>
                        <th>Status Pulang</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $total_hadir = 0;
                        $total_tepat_waktu = 0;
                        $total_terlambat = 0;
                        $total_setengah_hari = 0;
                        $total_overtime = 0;
                        $total_minggu = 0; // BARU: Track Minggu (Sunday)
                        $total_off = 0;
                        $total_sakit = 0;
                        $total_cuti = 0;
                        $total_alpha = 0;
                        
                        // Query dengan durasi_menit - SAMA SEPERTI staff_dashboard.php
                        $sql = "SELECT a.*, 
                                TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit,
                                (SELECT MAX(jam_pulang) FROM jam_kerja jk 
                                 JOIN karyawan k2 ON jk.id_cabang = k2.id_cabang 
                                 WHERE k2.id_karyawan = a.id_karyawan) as max_jam_pulang
                                FROM absensi a
                                WHERE a.id_karyawan = ? 
                                AND a.tanggal BETWEEN ? AND ?
                                ORDER BY a.tanggal DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sss", $id_karyawan, $start_date, $end_date);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0):
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                                
                                // LOGIKA STATUS PULANG - SAMA PERSIS DENGAN staff_dashboard.php
                                $status_pulang = '-';
                                $status_pulang_text = '-';
                                $status_pulang_color = '#888';
                                
                                // Cek apakah ada jam pulang yang valid
                                if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00' && $row['jam_pulang'] != NULL) {
                                    $durasi_menit = $row['durasi_menit'];
                                    
                                    // Pastikan durasi valid
                                    if ($durasi_menit !== NULL && $durasi_menit > 0) {
                                        
                                        // CRITICAL: Cek Setengah Hari PERTAMA (< 330 menit = < 5 jam 30 menit)
                                        if ($durasi_menit < 330) {
                                            $status_pulang = 'Setengah Hari';
                                            $status_pulang_text = 'Setengah Hari';
                                            $status_pulang_color = '#ff9800';
                                            $total_setengah_hari++;
                                        } 
                                        // Kemudian cek Overtime
                                        elseif (!empty($row['max_jam_pulang']) && strtotime($row['jam_pulang']) > strtotime($row['max_jam_pulang'])) {
                                            $status_pulang = 'Overtime';
                                            $status_pulang_text = 'Overtime';
                                            $status_pulang_color = '#ffd700';
                                            
                                            $selisih_menit = round((strtotime($row['jam_pulang']) - strtotime($row['max_jam_pulang'])) / 60);
                                            if ($selisih_menit < 35) {
                                                $total_overtime += 0.5;
                                            } else {
                                                $total_overtime += 1;
                                            }
                                        } 
                                        // Sisanya Normal
                                        else {
                                            $status_pulang = 'Normal';
                                            $status_pulang_text = 'Normal';
                                            $status_pulang_color = '#4CAF50';
                                        }
                                    }
                                } else if ($row['keterangan'] == 'Hadir' && strtotime($row['tanggal']) < strtotime('today')) {
                                    $status_pulang = 'Setengah Hari'; // Use Setengah Hari for PDF/Excel logic counting
                                    $status_pulang_text = 'Belum Absen Pulang = Set. Hari';
                                    $status_pulang_color = '#ff9800';
                                    $total_setengah_hari++;
                                }

                                // Hitung total per kategori
                                // UPDATED: Cek apakah Minggu (Sunday) dengan logic 0.5 untuk setengah hari
                                $day_of_week = date('N', strtotime($row['tanggal'])); // 1=Mon, 7=Sun
                                if ($day_of_week == 7 && strtolower($row['keterangan']) == 'hadir') {
                                    // Jika setengah hari, tambah 0.5, jika full day, tambah 1
                                    if ($status_pulang == 'Setengah Hari') {
                                        $total_minggu += 0.5;
                                    } else {
                                        $total_minggu += 1;
                                    }
                                }
                                switch(strtolower($row['keterangan'])) {
                                    case 'hadir': 
                                        if ($status_pulang == 'Setengah Hari') {
                                            $total_hadir += 0.5;
                                        } else {
                                            $total_hadir += 1;
                                        }
                                        if ($row['status_masuk'] == 'Tepat Waktu') $total_tepat_waktu++;
                                        else $total_terlambat++;
                                        break;
                                    case 'off': $total_off++; break;
                                    case 'sakit': $total_sakit++; break;
                                    case 'cuti': $total_cuti++; break;
                                    case 'alpha': $total_alpha++; break;
                                }
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                            <td><?php echo $row['jam_masuk'] ? date('H:i:s', strtotime($row['jam_masuk'])) : '-'; ?></td>
                            <td><?php echo ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') ? date('H:i:s', strtotime($row['jam_pulang'])) : '-'; ?></td>
                            
                            <td>
                                <?php if ($row['keterangan'] == 'Hadir'): ?>
                                    <?php if ($row['status_masuk'] == 'Tepat Waktu'): ?>
                                        <span style="color: #00ff7f; font-weight: 600;">Tepat Waktu</span>
                                    <?php else: ?>
                                        <span style="color: #ff6b6b; font-weight: 600;">Terlambat</span>
                                    <?php endif; ?>
                                <?php else: ?> - <?php endif; ?>
                            </td>
                            
                            <td>
                                <span style="color: <?php echo $status_pulang_color; ?>; font-weight: 600;">
                                    <?php echo $status_pulang_text; ?>
                                </span>
                            </td>
                            
                            <td>
                                <span class="status-<?php echo strtolower($row['keterangan']); ?>">
                                    <?php echo htmlspecialchars($row['keterangan']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                        $stmt->close();
                    ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">
                                Tidak ada data absensi untuk periode yang dipilih
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($result->num_rows > 0): ?>
                <div class="summary-box">
                    <h3>Ringkasan Absensi</h3>
                    <div class="summary-grid">                       
                        <div class="summary-item" style="border-left: 4px solid #0f9f42ff;">
                            <div class="label">Tepat Waktu</div>
                            <div class="value" style="color: #0f9f42ff;"><?php echo $total_tepat_waktu; ?></div>
                        </div>
                        
                        <div class="summary-item" style="border-left: 4px solid #ff6b6b;">
                            <div class="label">Terlambat</div>
                            <div class="value" style="color: #ff6b6b;"><?php echo $total_terlambat; ?></div>
                        </div>
                        
                        <div class="summary-item" style="border-left: 4px solid #ff9800;">
                            <div class="label">Setengah Hari</div>
                            <div class="value" style="color: #ff9800;"><?php echo $total_setengah_hari; ?></div>
                        </div>
                        
                        <div class="summary-item" style="border-left: 4px solid #ffd700;">
                            <div class="label">Overtime</div>
                            <div class="value" style="color: #ffd700;"><?php echo $total_overtime; ?></div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="label">Sakit</div>
                            <div class="value" style="color: #f5576c;"><?php echo $total_sakit; ?></div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="label">Cuti</div>
                            <div class="value" style="color: #4facfe;"><?php echo $total_cuti; ?></div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="label">Alpha</div>
                            <div class="value" style="color: #fa709a;"><?php echo $total_alpha; ?></div>
                        </div>
                        
                        <div class="summary-item" style="border-left: 4px solid #1e24d3ff;">
                            <div class="label">TOTAL HADIR</div>
                            <div class="value" style="color: #1e24d3ff;"><?php echo $total_hadir; ?></div>
                        </div>
                        
                        <div class="summary-item">
                            <div class="label">TOTAL OFF</div>
                            <div class="value" style="color: #6c757d;"><?php echo $total_off; ?></div>
                        </div>

                        <div class="summary-item" style="border-left: 4px solid #ff6f00;">
                            <div class="label">MASUK AHAD</div>
                            <div class="value" style="color: #ff6f00;"><?php echo $total_minggu; ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="footer">
                <p><strong>© <?php echo date('Y'); ?> DINIA TEAM</strong></p>
                <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi</p>
                <p>Dicetak oleh: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
