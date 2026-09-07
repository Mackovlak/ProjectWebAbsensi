<?php
/**
 * EXPORT DAFTAR SLIP GAJI (SEMUA KARYAWAN) - Excel (.xlsx)
 *
 * Ekspor satu periode (bulan/tahun) sekaligus untuk seluruh karyawan yang
 * dilist di slip_gaji.php (admin) / owner_approval_gaji.php (owner), supaya
 * bisa dibandingkan dalam satu sheet alih-alih membuka slip PDF satu per
 * satu. Data yang sudah di-query di halaman pemanggil dikirim sebagai JSON,
 * di sini diformat jadi .xlsx asli lewat xlsx_writer.php (bukan CSV) supaya
 * kolom tidak berantakan tergantung setelan regional Excel pengguna, dan
 * angka benar-benar bertipe number (bisa langsung di-SUM/format di Excel).
 */
require 'config.php';
require 'xlsx_writer.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'owner'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Metode tidak diizinkan.");
}

verifyCSRFToken($_POST['csrf_token'] ?? '');

$data = json_decode($_POST['data'] ?? '[]', true);
$bulan = (int)($_POST['bulan'] ?? date('n'));
$tahun = (int)($_POST['tahun'] ?? date('Y'));
$months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$nama_bulan = $months[$bulan] ?? (string)$bulan;

$headers = [
    'No', 'ID Karyawan', 'Nama Karyawan', 'Jabatan', 'Cabang',
    'Gaji Pokok', 'Tunjangan', 'Akomodasi', 'Transport', 'Lembur', 'Insentif Hari ' . labelHariOvertime($conn),
    'Potongan Keterlambatan', 'Total Penghasilan', 'Total Potongan', 'Pembulatan',
    'Gaji Bersih (THP)', 'ACC Admin', 'ACC Owner'
];
$currencyKeys = [
    'gaji_pokok', 'tunjangan_cs', 'akomodasi', 'transport_total', 'overtime_total',
    'insentif_ahad_total', 'keterlambatan_total', 'total_penghasilan', 'total_potongan',
    'digenapkan', 'gaji_bersih',
];

$xlsx = new SimpleXLSXWriter('Slip Gaji ' . $nama_bulan . ' ' . $tahun);
$xlsx->setColumnWidths([6, 14, 24, 18, 16, 13, 13, 13, 13, 13, 13, 15, 15, 13, 12, 15, 11, 11]);
$xlsx->addTitleRow('DAFTAR SLIP GAJI KARYAWAN', count($headers));
$xlsx->addRow(['Periode: ' . $nama_bulan . ' ' . $tahun]);
$xlsx->addRow(['Tanggal Cetak: ' . date('d-m-Y H:i')]);
$xlsx->addRow(['']);
$xlsx->addHeaderRow($headers);

$totals = array_fill_keys($currencyKeys, 0.0);

if (!empty($data)) {
    $no = 1;
    foreach ($data as $row) {
        foreach ($totals as $key => $val) {
            $totals[$key] += (float)($row[$key] ?? 0);
        }
        $xlsx->addRow([
            $no++,
            $row['id_karyawan'] ?? '',
            html_entity_decode($row['nama_karyawan'] ?? '', ENT_QUOTES, 'UTF-8'),
            html_entity_decode($row['nama_jabatan'] ?? '-', ENT_QUOTES, 'UTF-8'),
            html_entity_decode($row['nama_cabang'] ?? '-', ENT_QUOTES, 'UTF-8'),
            ['value' => (float)($row['gaji_pokok'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['tunjangan_cs'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['akomodasi'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['transport_total'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['overtime_total'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['insentif_ahad_total'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['keterlambatan_total'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['total_penghasilan'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['total_potongan'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['digenapkan'] ?? 0), 'style' => 'currency'],
            ['value' => (float)($row['gaji_bersih'] ?? 0), 'style' => 'currency'],
            !empty($row['status_admin_acc']) ? 'Ya' : 'Belum',
            !empty($row['status_owner_acc']) ? 'Ya' : 'Belum',
        ]);
    }

    $xlsx->addRow(['']);
    $xlsx->addRow([
        ['value' => '', 'style' => 'normal'],
        ['value' => '', 'style' => 'normal'],
        ['value' => 'TOTAL', 'style' => 'bold'],
        '', '',
        ['value' => $totals['gaji_pokok'], 'style' => 'currency_bold'],
        ['value' => $totals['tunjangan_cs'], 'style' => 'currency_bold'],
        ['value' => $totals['akomodasi'], 'style' => 'currency_bold'],
        ['value' => $totals['transport_total'], 'style' => 'currency_bold'],
        ['value' => $totals['overtime_total'], 'style' => 'currency_bold'],
        ['value' => $totals['insentif_ahad_total'], 'style' => 'currency_bold'],
        ['value' => $totals['keterlambatan_total'], 'style' => 'currency_bold'],
        ['value' => $totals['total_penghasilan'], 'style' => 'currency_bold'],
        ['value' => $totals['total_potongan'], 'style' => 'currency_bold'],
        ['value' => $totals['digenapkan'], 'style' => 'currency_bold'],
        ['value' => $totals['gaji_bersih'], 'style' => 'currency_bold'],
        '', '',
    ]);
}

$xlsx->output('Slip_Gaji_' . $nama_bulan . '_' . $tahun . '_' . date('Y-m-d') . '.xlsx');
