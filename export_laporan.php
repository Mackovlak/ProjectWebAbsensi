<?php
/** Admin/owner consolidated Excel exports. All rows come from the database. */
require 'config.php';
requireAdminOrOwner();
require_once __DIR__ . '/laporan_data.php';
require_once __DIR__ . '/xlsx_writer.php';

function laporanExportError($message) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

$report = $_GET['report'] ?? '';
$tipe = $_GET['tipe'] ?? '';
$supported = [
    'karyawan' => ['rekap'],
    'absensi' => ['log', 'statistik'],
    'gaji' => ['lap_gaji_divisi', 'rekap_gaji_divisi', 'per_karyawan'],
];
if (!is_string($report) || !isset($supported[$report]) || !in_array($tipe, $supported[$report], true)) {
    laporanExportError('Jenis laporan Excel tidak valid.');
}
$cabang_id = $_GET['cabang_id'] ?? 'all';
$user_id = $_GET['user_id'] ?? '';
$status = $_GET['status'] ?? 'aktif';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
// Personal payroll ignores the hidden branch selection, just like the preview.
if ($report === 'gaji' && $tipe === 'per_karyawan') {
    if (!is_string($user_id) || !preg_match('/^\d{11}$/D', $user_id)) {
        laporanExportError('Pilih karyawan yang valid terlebih dahulu.');
    }
    $cabang_id = 'all';
}
$cabang_name = 'Semua Cabang';
if ($cabang_id !== 'all') {
    if (!is_string($cabang_id) || !ctype_digit($cabang_id) || (int)$cabang_id < 1) {
        laporanExportError('Cabang tidak valid.');
    }
    $stmt = $conn->prepare('SELECT nama_cabang FROM cabang WHERE id = ?');
    $stmt->bind_param('i', $cabang_id);
    $stmt->execute();
    $branch = $stmt->get_result()->fetch_assoc();
    if (!$branch) laporanExportError('Cabang tidak ditemukan.');
    $cabang_name = $branch['nama_cabang'];
}
if ($report === 'karyawan') {
    if (!in_array($status, ['aktif', 'nonaktif', 'all'], true)) {
        laporanExportError('Status karyawan tidak valid.');
    }
} else {
    foreach ([$start_date, $end_date] as $date) {
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            laporanExportError('Tanggal laporan tidak valid.');
        }
    }
    if ($start_date > $end_date) laporanExportError('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.');
}

$title = ['karyawan' => 'Data Karyawan', 'absensi' => 'Absensi', 'gaji' => 'Gaji'][$report];
$xlsx = new SimpleXLSXWriter('Laporan ' . $title);
$headers = [];
$rows = [];
$totals = null;
$currency_columns = [];
if ($report === 'karyawan') {
    $headers = ['No', 'ID Karyawan', 'Nama Karyawan', 'Cabang', 'Jabatan', 'Jenis Kelamin', 'Username', 'Status', 'Tanggal Resign'];
    $result = laporanKaryawan($conn, $cabang_id, $status);
    while ($row = $result->fetch_assoc()) {
        $rows[] = [count($rows) + 1, (string)$row['id'], $row['nama_lengkap'], $row['nama_cabang'] ?? 'Tanpa Cabang', $row['nama_jabatan'] ?? '-', $row['jenis_kelamin'], $row['username'], $row['status'], $row['tanggal_resign'] ?? ''];
    }
} elseif ($report === 'absensi' && $tipe === 'log') {
    $headers = ['No', 'ID Karyawan', 'Nama Karyawan', 'Cabang', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status Masuk', 'Status Pulang', 'Keterangan'];
    $all_shifts = [];
    $shifts = $conn->query('SELECT id_cabang, nama_shift, jam_masuk_akhir, jam_pulang FROM jam_kerja');
    while ($shift = $shifts->fetch_assoc()) $all_shifts[$shift['id_cabang']][] = $shift;
    $result = laporanAbsensiLog($conn, $cabang_id, $start_date, $end_date);
    while ($row = $result->fetch_assoc()) {
        $masuk = $row['keterangan'] === 'Hadir' ? ($row['status_masuk'] === 'Terlambat' ? 'Terlambat' : 'Tepat Waktu') : '-';
        $rows[] = [count($rows) + 1, (string)$row['id_karyawan'], $row['nama_lengkap'], $row['nama_cabang'] ?? 'Tanpa Cabang', $row['tanggal'], $row['jam_masuk'] ?: '-', ($row['jam_pulang'] && $row['jam_pulang'] !== '00:00:00') ? $row['jam_pulang'] : '-', $masuk, laporanStatusPulang($row, $all_shifts), $row['keterangan']];
    }
} elseif ($report === 'absensi') {
    $headers = ['No', 'ID Karyawan', 'Nama Karyawan', 'Cabang', 'Total Hadir', 'Tepat Waktu', 'Terlambat', 'Setengah Hari', 'Overtime', labelHariOvertime($conn), 'Dinas Luar', 'OFF', 'Sakit', 'Cuti', 'Alpha'];
    $keys = ['total_hadir', 'total_tepat_waktu', 'total_telat', 'total_setengah_hari', 'total_overtime', 'total_minggu', 'total_dinas_luar', 'total_off', 'total_sakit', 'total_cuti', 'total_alpha'];
    $result = laporanAbsensiStatistik($conn, $cabang_id, $start_date, $end_date);
    while ($row = $result->fetch_assoc()) {
        $values = [count($rows) + 1, (string)$row['id'], $row['nama_lengkap'], $row['nama_cabang'] ?? 'Tanpa Cabang'];
        foreach ($keys as $key) $values[] = (float)($row[$key] ?? 0);
        $rows[] = $values;
    }
} else {
    $rekap = $tipe === 'rekap_gaji_divisi';
    $headers = $rekap
        ? ['No', 'Cabang', 'Bulan', 'Tahun', 'Jumlah Slip', 'Penghasilan', 'Potongan', 'Gaji Bersih', 'Disetujui Admin', 'Disetujui Owner']
        : ['No', 'ID Karyawan', 'Nama Karyawan', 'Cabang', 'Bulan', 'Tahun', 'Penghasilan', 'Potongan', 'Gaji Bersih', 'Persetujuan Admin', 'Persetujuan Owner', 'Persetujuan Karyawan'];
    $currency_columns = $rekap ? [5, 6, 7] : [6, 7, 8];
    $totals = array_fill(0, count($headers), '');
    $totals[0] = 'TOTAL';
    foreach ($currency_columns as $column) $totals[$column] = 0;
    $result = laporanGaji($conn, $tipe, $cabang_id, $user_id, $start_date, $end_date);
    while ($row = $result->fetch_assoc()) {
        $values = $rekap
            ? [count($rows) + 1, $row['nama_cabang'] ?? 'Tanpa Cabang', (int)$row['bulan'], (int)$row['tahun'], (int)$row['jumlah_slip']]
            : [count($rows) + 1, (string)$row['id_karyawan'], $row['nama_lengkap'], $row['nama_cabang'] ?? 'Tanpa Cabang', (int)$row['bulan'], (int)$row['tahun']];
        foreach (['total_penghasilan', 'total_potongan', 'gaji_bersih'] as $key) $values[] = (float)$row[$key];
        if ($rekap) {
            $values[] = (int)$row['admin_disetujui'];
            $values[] = (int)$row['owner_disetujui'];
        } else {
            foreach (['status_admin_acc', 'status_owner_acc', 'status_karyawan_acc'] as $key) {
                $values[] = !empty($row[$key]) ? 'Disetujui' : 'Belum Disetujui';
            }
        }
        foreach ($currency_columns as $column) $totals[$column] += $values[$column];
        $rows[] = $values;
    }
}

$xlsx->setColumnWidths(array_map(static function ($header) { return max(14, min(32, strlen($header) + 5)); }, $headers));
$xlsx->addTitleRow('Laporan ' . $title . ' - ' . $tipe, count($headers));
$xlsx->addRow(['Cabang: ' . $cabang_name]);
$xlsx->addRow([$report === 'karyawan' ? 'Status: ' . $status : 'Periode: ' . $start_date . ' s/d ' . $end_date]);
$xlsx->addRow(['Diekspor oleh: ' . ($_SESSION['username'] ?? '') . ' | ' . date('d-m-Y H:i:s')]);
if ($totals !== null) {
    $xlsx->addRow(['Total penghasilan / potongan / gaji bersih (Rp)',
        ['value' => $totals[$currency_columns[0]], 'style' => 'currency_bold'],
        ['value' => $totals[$currency_columns[1]], 'style' => 'currency_bold'],
        ['value' => $totals[$currency_columns[2]], 'style' => 'currency_bold']]);
}
$xlsx->addHeaderRow($headers);
foreach ($rows as $values) {
    foreach ($currency_columns as $column) $values[$column] = ['value' => $values[$column], 'style' => 'currency'];
    $xlsx->addRow($values);
}
if (!$rows) $xlsx->addRow(['Tidak ada data sesuai filter.']);
header('Cache-Control: private, no-store');
$xlsx->output('Laporan_' . $report . '_' . $tipe . '_' . date('Ymd_His') . '.xlsx');
