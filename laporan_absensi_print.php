<?php
require 'config.php';
requireAdminOrOwner();

// Fungsi untuk mendeteksi shift
if (!function_exists('detectCorrectShift')) {
    function detectCorrectShift($jam_masuk_karyawan, $shifts_data) {
        if (empty($jam_masuk_karyawan) || empty($shifts_data)) {
            return null;
        }
        $jam_masuk_ts = strtotime($jam_masuk_karyawan);
        $best_match = null;
        $min_diff = PHP_INT_MAX;
        foreach ($shifts_data as $shift) {
            $shift_masuk_ts = strtotime($shift['jam_masuk_akhir']);
            $diff = abs($jam_masuk_ts - $shift_masuk_ts);
            if ($diff < $min_diff) {
                $min_diff = $diff;
                $best_match = $shift;
            }
        }
        return $best_match;
    }
}

$action = $_GET['action'] ?? 'preview';
$cabang_id = $_GET['cabang_id'] ?? 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$tipe = $_GET['tipe'] ?? 'log';

// Fetch Branch Name
$cabang_name = "SEMUA CABANG";
if ($cabang_id !== 'all') {
    $stmt = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
    $stmt->bind_param("i", $cabang_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $cabang_name = $row['nama_cabang'];
    }
}

$title = 'Data Log Harian Absensi';
if ($tipe === 'statistik') {
    $title = 'Statistik Absensi Cabang';
} elseif ($tipe === 'statistik_karyawan') {
    $title = 'Statistik Detail Karyawan';
} elseif ($tipe === 'juara_tahunan') {
    $title = 'Rekap Juara Best Performance Tahunan';
}

$user_id = $_GET['user_id'] ?? '';
$karyawan_name = '';
if ($tipe === 'statistik_karyawan' && !empty($user_id)) {
    $stmt = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $karyawan_name = $row['nama_karyawan'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan <?php echo $title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { margin: 10mm; size: A4 portrait; }
            #report-container { padding: 0 !important; border: none !important; box-shadow: none !important; max-width: none !important; zoom: 0.9; }
            table th, table td { padding: 3px 4px !important; font-size: 8px !important; word-wrap: break-word; border: 1px solid #9ca3af !important; }
            .text-2xl { font-size: 16px !important; }
            .mb-8 { margin-bottom: 0.5rem !important; }
            .mt-12 { margin-top: 1rem !important; }
            .mb-16 { margin-bottom: 2rem !important; }
            p { font-size: 10px !important; }
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
            <h1 class="text-2xl font-bold uppercase tracking-widest text-gray-900">Laporan <?php echo $title; ?></h1>
            <?php if ($tipe === 'statistik_karyawan'): ?>
            <p class="text-gray-600 mt-1">Nama Karyawan: <strong><?php echo htmlspecialchars($karyawan_name); ?></strong></p>
            <?php else: ?>
            <p class="text-gray-600 mt-1"><strong><?php echo htmlspecialchars($cabang_name); ?></strong></p>
            <?php endif; ?>
            <?php if ($tipe === 'juara_tahunan'): ?>
            <p class="text-gray-600">Tahun: <?php echo htmlspecialchars($_GET['tahun'] ?? date('Y')); ?></p>
            <?php else: ?>
            <p class="text-gray-600">Periode: <?php echo date('d M Y', strtotime($start_date)); ?> s/d <?php echo date('d M Y', strtotime($end_date)); ?></p>
            <?php endif; ?>
            <p class="text-xs text-gray-500 mt-4">Dicetak Oleh : <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
            <p class="text-xs text-gray-500"> Pada Tanggal <?php echo date('d-m-Y | H:i:s'); ?></p>
        </div>

        <!-- Tabel Laporan -->
        <?php if ($tipe === 'log'): ?>
            <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left border-collapse">
                <thead>
                    <tr class="bg-gray-300">
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">No</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800">Nama</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800">Tanggal</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Jam Masuk</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Jam Pulang</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Status Masuk</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Status Pulang</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Ket</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ambil semua shift
                    $all_shifts = [];
                    $res_shifts = $conn->query("SELECT id_cabang, nama_shift, jam_masuk_akhir, jam_pulang FROM jam_kerja");
                    while($row_shift = $res_shifts->fetch_assoc()) {
                        $all_shifts[$row_shift['id_cabang']][] = $row_shift;
                    }

                    $query = "SELECT a.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', a.id_karyawan, ')')) as nama_lengkap, k.id_cabang, TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit
                              FROM absensi a 
                              LEFT JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
                              WHERE a.tanggal BETWEEN ? AND ?";
                    $params = [$start_date, $end_date];
                    $types = "ss";

                    if ($cabang_id !== 'all') {
                        $query .= " AND k.id_cabang = ?";
                        $params[] = $cabang_id;
                        $types .= "i";
                    }
                    $query .= " ORDER BY a.tanggal DESC, k.nama_karyawan ASC";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $no = 1;
                    
                    if ($res->num_rows > 0):
                        while ($row = $res->fetch_assoc()):
                    ?>
                    <tr>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center"><?php echo $no++; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs font-semibold text-gray-800 max-w-[150px] whitespace-normal break-words"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-gray-600"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-gray-600 text-center"><?php echo $row['jam_masuk'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-gray-600 text-center"><?php echo ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') ? $row['jam_pulang'] : '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center">
                            <?php 
                                if($row['keterangan'] === 'Hadir') {
                                    $st = $row['status_masuk'];
                                    echo $st === 'Terlambat' ? '<span class="text-red-600">Terlambat</span>' : '<span class="text-emerald-600">Tepat Waktu</span>';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center">
                            <?php 
                                $status_pulang = '-';
                                $shifts_data = $all_shifts[$row['id_cabang']] ?? [];
                                $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
                                $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;
                                
                                if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') {
                                    $durasi_menit = $row['durasi_menit'];
                                    if ($durasi_menit !== null && $durasi_menit > 0) {
                                        if ($durasi_menit < 330) {
                                            $status_pulang = 'Setengah Hari';
                                        } elseif (!empty($jam_pulang_standar) && strtotime($row['jam_pulang']) > strtotime($jam_pulang_standar)) {
                                            $status_pulang = 'Over Time';
                                        } else {
                                            $status_pulang = 'Normal';
                                        }
                                    }
                                } else if ($row['keterangan'] == 'Hadir' && strtotime($row['tanggal']) < strtotime('today')) {
                                    $status_pulang = 'Belum Absen Pulang = Set. Hari';
                                }

                                if($row['keterangan'] === 'Hadir') {
                                    if ($status_pulang === 'Setengah Hari') echo '<span class="text-orange-600">Setengah Hari</span>';
                                    elseif ($status_pulang === 'Belum Absen Pulang = Set. Hari') echo '<span class="text-amber-500">Belum Absen Pulang = Set. Hari</span>';
                                    elseif ($status_pulang === 'Over Time') echo '<span class="text-purple-600">Over Time</span>';
                                    elseif ($status_pulang === 'Normal') echo '<span class="text-emerald-600">Normal</span>';
                                    else echo '<span class="text-amber-500">Belum Pulang</span>';
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-gray-600 text-center"><?php echo htmlspecialchars($row['keterangan']); ?></td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr><td colspan="8" class="border border-gray-300 px-3 py-4 text-center text-sm text-gray-500 italic">Tidak ada data absensi.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php elseif ($tipe === 'statistik'): // Statistik Divisi ?>
            <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left border-collapse">
                <thead>
                    <tr class="bg-gray-300">
                        <th rowspan="2" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center align-middle">No</th>
                        <th rowspan="2" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-left align-middle">Nama Karyawan</th>
                        <th colspan="7" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Kehadiran</th>
                        <th rowspan="2" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center align-middle">OFF</th>
                        <th rowspan="2" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center align-middle">Sakit</th>
                        <th rowspan="2" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center align-middle">Cuti</th>
                        <th rowspan="2" class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center align-middle">Alpha</th>
                    </tr>
                    <tr class="bg-gray-300">
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Total</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Tepat Waktu</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Terlambat</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Setengah Hari</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-gray-800 text-center">Overtime</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-orange-600 text-center">Ahad</th>
                        <th class="border border-gray-400 px-2 py-2 text-xs font-bold text-purple-600 text-center">Dinas Luar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT k.id_karyawan as id, k.nama_karyawan as nama_lengkap, 
                              SUM(CASE WHEN a.keterangan = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
                              SUM(CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Tepat Waktu' THEN 1 ELSE 0 END) as total_tepat_waktu,
                              SUM(CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) as total_telat,
                              SUM(CASE 
                                  WHEN a.keterangan = 'Hadir' AND (
                                      (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
                                      OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
                                  )
                                  THEN 1 ELSE 0 
                              END) as total_setengah_hari,
                              SUM(CASE 
                                  WHEN a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
                                  AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) >= 330
                                  AND a.jam_pulang > (
                                      SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang
                                      ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1
                                  )
                                  THEN 
                                      CASE WHEN (TIME_TO_SEC(a.jam_pulang) - TIME_TO_SEC((SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1))) < 2100 THEN 0.5 ELSE 1 END
                                  ELSE 0 
                              END) as total_overtime,
                              SUM(CASE WHEN DAYOFWEEK(a.tanggal) = 1 AND a.keterangan = 'Hadir' THEN 1 ELSE 0 END) as total_minggu,
                              SUM(CASE WHEN a.keterangan = 'OFF' THEN 1 ELSE 0 END) as total_off,
                              SUM(CASE WHEN a.keterangan = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
                              SUM(CASE WHEN a.keterangan = 'Cuti' THEN 1 ELSE 0 END) as total_cuti,
                              SUM(CASE WHEN a.keterangan = 'Dinas Luar' THEN 1 ELSE 0 END) as total_dinas_luar,
                              SUM(CASE WHEN a.keterangan = 'Alpha' THEN 1 ELSE 0 END) as total_alpha
                              FROM karyawan k 
                              LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan AND a.tanggal BETWEEN ? AND ? 
                              WHERE 1=1";
                    
                    $params = [$start_date, $end_date];
                    $types = "ss";

                    if ($cabang_id !== 'all') {
                        $query .= " AND k.id_cabang = ?";
                        $params[] = $cabang_id;
                        $types .= "i";
                    }
                    $query .= " GROUP BY k.id_karyawan ORDER BY k.nama_karyawan ASC";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $no = 1;
                    
                    if ($res->num_rows > 0):
                        while ($row = $res->fetch_assoc()):
                    ?>
                    <tr>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center"><?php echo $no++; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs font-semibold text-gray-800 max-w-[150px] whitespace-normal break-words"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-gray-800 font-semibold">
                            <?php 
                                $total_hadir_calculated = ($row['total_hadir'] ?? 0) - (($row['total_setengah_hari'] ?? 0) * 0.5);
                                echo $total_hadir_calculated > 0 ? $total_hadir_calculated : '-'; 
                            ?>
                        </td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-emerald-600"><?php echo $row['total_tepat_waktu'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-red-500"><?php echo $row['total_telat'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-orange-500"><?php echo $row['total_setengah_hari'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-amber-500"><?php echo $row['total_overtime'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-orange-600 font-semibold"><?php echo $row['total_minggu'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-purple-500"><?php echo $row['total_dinas_luar'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-gray-500"><?php echo $row['total_off'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-fuchsia-500"><?php echo $row['total_sakit'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-purple-500"><?php echo $row['total_cuti'] ?: '-'; ?></td>
                        <td class="border border-gray-300 px-2 py-2 text-xs text-center text-red-500"><?php echo $row['total_alpha'] ?: '-'; ?></td>
                    </tr>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                    <tr><td colspan="13" class="border border-gray-300 px-3 py-4 text-center text-sm text-gray-500 italic">Tidak ada data karyawan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php elseif ($tipe === 'statistik_karyawan'): // Statistik Per Karyawan ?>
            <?php
            if (empty($user_id)) {
                echo "<div class='text-center py-16'><i class='fa-solid fa-users-slash text-4xl text-gray-300 mb-3'></i><p class='text-gray-500 font-medium'>Silakan pilih karyawan terlebih dahulu di halaman sebelumnya.</p></div>";
            } else {
                $query = "SELECT a.tanggal, a.keterangan, a.status_masuk, a.jam_masuk, a.jam_pulang, TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit, k.id_cabang FROM absensi a JOIN karyawan k ON a.id_karyawan = k.id_karyawan WHERE a.id_karyawan = ? AND a.tanggal BETWEEN ? AND ? ORDER BY a.tanggal ASC";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iss", $user_id, $start_date, $end_date);
                $stmt->execute();
                $res = $stmt->get_result();

                $all_shifts = [];
                $res_shifts = $conn->query("SELECT id_cabang, nama_shift, jam_masuk_akhir, jam_pulang FROM jam_kerja");
                while($row_shift = $res_shifts->fetch_assoc()) {
                    $all_shifts[$row_shift['id_cabang']][] = $row_shift;
                }

                $data_karyawan = [
                    'Setengah Hari' => [],
                    'Overtime' => [],
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
                        
                        $status_pulang = 'Normal';
                        $shifts_data = $all_shifts[$row['id_cabang']] ?? [];
                        $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
                        $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;
                        
                        if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') {
                            $durasi_menit = $row['durasi_menit'];
                            if ($durasi_menit !== null && $durasi_menit > 0) {
                                if ($durasi_menit < 330) {
                                    $status_pulang = 'Setengah Hari';
                                } elseif (!empty($jam_pulang_standar) && strtotime($row['jam_pulang']) > strtotime($jam_pulang_standar)) {
                                    $status_pulang = 'Overtime';
                                }
                            }
                        } else if (strtotime($row['tanggal']) < strtotime('today')) {
                            $status_pulang = 'Setengah Hari';
                        }

                        if ($status_pulang === 'Setengah Hari') {
                            $data_karyawan['Setengah Hari'][] = $row;
                            $total_hadir -= 0.5;
                        } elseif ($status_pulang === 'Overtime') {
                            $data_karyawan['Overtime'][] = $row;
                        } else {
                            if ($row['status_masuk'] === 'Terlambat') {
                                $data_karyawan['Hadir (Terlambat)'][] = $row;
                            } else {
                                $data_karyawan['Hadir (Tepat Waktu)'][] = $row;
                            }
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

            <div class="mb-6 grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-fuchsia-50 border border-fuchsia-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-fuchsia-600 font-semibold mb-1">Total Hadir</p>
                    <p class="text-2xl font-bold text-fuchsia-800"><?php echo $total_hadir > 0 ? $total_hadir : 0; ?></p>
                </div>
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-emerald-600 font-semibold mb-1">Tepat Waktu</p>
                    <p class="text-2xl font-bold text-emerald-800"><?php echo count($data_karyawan['Hadir (Tepat Waktu)']); ?></p>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-orange-600 font-semibold mb-1">Terlambat</p>
                    <p class="text-2xl font-bold text-orange-800"><?php echo count($data_karyawan['Hadir (Terlambat)']); ?></p>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-amber-600 font-semibold mb-1">Setengah Hari</p>
                    <p class="text-2xl font-bold text-amber-800"><?php echo count($data_karyawan['Setengah Hari']); ?></p>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-purple-600 font-semibold mb-1">Overtime</p>
                    <p class="text-2xl font-bold text-purple-800"><?php echo count($data_karyawan['Overtime']); ?></p>
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
                <div class="bg-sky-50 border border-sky-200 rounded-lg p-4 text-center">
                    <p class="text-xs text-sky-600 font-semibold mb-1">Total Dinas Luar</p>
                    <p class="text-2xl font-bold text-sky-800"><?php echo count($data_karyawan['Dinas Luar']); ?></p>
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
            <?php } ?>


        <?php elseif ($tipe === 'juara_tahunan'): // Rekap Juara Tahunan ?>
            <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left border-collapse">
                <thead>
                    <tr class="bg-amber-100">
                        <th class="border border-amber-300 px-2 py-3 text-sm font-bold text-amber-900 text-center w-12">Bulan</th>
                        <th class="border border-amber-300 px-2 py-3 text-sm font-bold text-amber-900">Nama Juara</th>
                        <th class="border border-amber-300 px-2 py-3 text-sm font-bold text-amber-900">Cabang</th>
                        <th class="border border-amber-300 px-2 py-3 text-sm font-bold text-amber-900 text-center">Kehadiran</th>
                        <th class="border border-amber-300 px-2 py-3 text-sm font-bold text-amber-900 text-center">Durasi Kerja</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tahun = $_GET['tahun'] ?? date('Y');
                    $nama_bulan = [
                        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                    ];
                    
                    $ada_data = false;

                    for ($m = 1; $m <= 12; $m++) {
                        $sql = "SELECT k.nama_karyawan, c.nama_cabang, COUNT(a.id) as total_hadir, SUM(TIME_TO_SEC(TIMEDIFF(a.jam_pulang, a.jam_masuk))) as total_detik
                                FROM karyawan k
                                JOIN absensi a ON k.id_karyawan = a.id_karyawan
                                LEFT JOIN cabang c ON k.id_cabang = c.id
                                WHERE a.keterangan = 'Hadir' AND a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
                                  AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?";
                        $params = [$m, $tahun];
                        $types = "ii";
                        
                        if ($cabang_id !== 'all') {
                            $sql .= " AND k.id_cabang = ?";
                            $params[] = $cabang_id;
                            $types .= "i";
                        }
                        
                        $sql .= " GROUP BY k.id_karyawan, k.nama_karyawan, c.nama_cabang
                                  ORDER BY total_hadir DESC, total_detik DESC
                                  LIMIT 1";
                                  
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        
                        if ($row = $res->fetch_assoc()) {
                            $ada_data = true;
                            $jam = floor($row['total_detik'] / 3600);
                            $menit = floor(($row['total_detik'] % 3600) / 60);
                            ?>
                            <tr class="hover:bg-amber-50">
                                <td class="border border-gray-300 px-3 py-3 text-sm text-center font-semibold text-gray-700 bg-gray-50"><?php echo $nama_bulan[$m]; ?></td>
                                <td class="border border-gray-300 px-3 py-3 text-sm font-bold text-amber-700 flex items-center gap-2">
                                    <i class="fa-solid fa-medal text-amber-500"></i> <?php echo htmlspecialchars($row['nama_karyawan']); ?>
                                </td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($row['nama_cabang']); ?></td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-center"><span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded font-bold"><?php echo $row['total_hadir']; ?> Hari</span></td>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-center text-gray-600"><?php echo $jam; ?>h <?php echo $menit; ?>m</td>
                            </tr>
                            <?php
                        } else {
                            ?>
                            <tr>
                                <td class="border border-gray-300 px-3 py-3 text-sm text-center font-semibold text-gray-700 bg-gray-50"><?php echo $nama_bulan[$m]; ?></td>
                                <td colspan="4" class="border border-gray-300 px-3 py-3 text-sm text-gray-400 italic text-center">Belum ada data Best Performance</td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

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
                link.download = 'Laporan_Absensi_<?php echo date('Ymd_His'); ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
    </script>
</body>
</html>

