<?php
require 'config.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id_karyawan']) || !isset($_GET['bulan']) || !isset($_GET['tahun'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

$id_karyawan = sanitizeInput($_GET['id_karyawan']);
$bulan = intval($_GET['bulan']);
$tahun = intval($_GET['tahun']);

$sql = "SELECT a.tanggal
        FROM absensi a
        JOIN karyawan k ON a.id_karyawan = k.id_karyawan
        WHERE k.id_karyawan = ? 
        AND MONTH(a.tanggal) = ? 
        AND YEAR(a.tanggal) = ?
        AND a.keterangan IN ('Hadir', 'Dinas Luar')
        AND a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
        AND a.jam_pulang > (
            SELECT jk.jam_pulang 
            FROM jam_kerja jk 
            WHERE jk.id_cabang = k.id_cabang 
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC 
            LIMIT 1
        )
        ORDER BY a.tanggal DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $id_karyawan, $bulan, $tahun);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$hari_indo = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

while ($row = $result->fetch_assoc()) {
    $timestamp = strtotime($row['tanggal']);
    $hari_eng = date('l', $timestamp);
    $hari = $hari_indo[$hari_eng];
    $tanggal_indo = date('d-m-Y', $timestamp);
    
    $data[] = [
        'hari' => $hari,
        'tanggal' => $tanggal_indo
    ];
}

$stmt->close();

echo json_encode([
    'success' => true,
    'data' => $data
]);
?>

