<?php
/** Run with PHP 8.3 against the configured local DB. Read-only; no fixtures inserted. */
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
require dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/laporan_data.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function checkExport($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$admin = $conn->query("SELECT id FROM users WHERE role IN ('admin', 'owner') AND is_active = 1 LIMIT 1")->fetch_assoc();
checkExport($admin !== null, 'An active admin/owner account is required for this read-only test.');
$branch = $conn->query('SELECT id FROM cabang ORDER BY id LIMIT 1')->fetch_assoc();
$employee = $conn->query('SELECT id_karyawan FROM karyawan ORDER BY id LIMIT 1')->fetch_assoc();
$temp = sys_get_temp_dir() . '/laporan_test_' . bin2hex(random_bytes(6));
mkdir($temp);

function runExport($params, $role = 'admin', $endpoint = 'export_laporan.php') {
    global $root, $admin, $temp;
    $bootstrap = $temp . '/request.php';
    $output = $temp . '/result.xlsx';
    $status = $temp . '/status.json';
    $code = '<?php chdir(' . var_export($root, true) . ');'
        . '$_SERVER["SERVER_NAME"] = "localhost"; $_SERVER["SERVER_PORT"] = 80;'
        . '$isLocalhost = true; session_start(); $_SESSION = ' . var_export(['user_id' => (int)$admin['id'], 'role' => $role, 'username' => 'Export Test'], true) . ';'
        . '$_GET = ' . var_export($params, true) . ';'
        . 'register_shutdown_function(function () { file_put_contents(' . var_export($status, true) . ', json_encode(http_response_code())); session_destroy(); });'
        . 'require ' . var_export($endpoint, true) . ';';
    file_put_contents($bootstrap, $code);
    $process = proc_open([PHP_BINARY, $bootstrap], [0 => ['pipe', 'r'], 1 => ['file', $output, 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    checkExport($exitCode === 0 && $errors === '', 'Export process failed: ' . $errors . ($exitCode ? substr(file_get_contents($output), 0, 800) : '')); 
    $body = file_get_contents($output);
    $httpStatus = json_decode(file_get_contents($status), true);
    if (!str_starts_with($body, 'PK')) return [$httpStatus, $body, null];
    $zip = new ZipArchive();
    checkExport($zip->open($output) === true, 'Invalid XLSX archive.');
    $sheet = new DOMDocument();
    checkExport($sheet->loadXML($zip->getFromName('xl/worksheets/sheet1.xml')), 'Invalid worksheet XML.');
    $zip->close();
    $xpath = new DOMXPath($sheet);
    $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    checkExport($xpath->query('//s:f')->length === 0, 'Export must never turn text into formulas.');
    $rows = [];
    foreach ($xpath->query('//s:sheetData/s:row') as $row) {
        $values = [];
        foreach ($xpath->query('s:c', $row) as $cell) {
            $values[] = $cell->getAttribute('t') === 'inlineStr'
                ? $xpath->evaluate('string(s:is/s:t)', $cell)
                : (float)$xpath->evaluate('string(s:v)', $cell);
        }
        $rows[] = $values;
    }
    return [$httpStatus, $rows, $xpath];
}

try {
    $period = ['start_date' => '2020-01-01', 'end_date' => '2099-12-31'];
    $scopes = ['all'];
    if ($branch) $scopes[] = (string)$branch['id'];
    foreach ($scopes as $scope) {
        foreach (['aktif', 'nonaktif'] as $status) {
            [, $rows] = runExport(['report' => 'karyawan', 'tipe' => 'rekap', 'cabang_id' => $scope, 'status' => $status]);
            $expected = laporanKaryawan($conn, $scope, $status)->num_rows;
            checkExport(count($rows) === 5 + max(1, $expected), 'Employee row count differs from preview.');
            foreach (array_slice($rows, 5, $expected) as $row) checkExport($row[7] === $status, 'Employee status filter failed.');
        }
        foreach (['log', 'statistik'] as $type) {
            [, $rows] = runExport(['report' => 'absensi', 'tipe' => $type, 'cabang_id' => $scope] + $period);
            $result = $type === 'log'
                ? laporanAbsensiLog($conn, $scope, $period['start_date'], $period['end_date'])
                : laporanAbsensiStatistik($conn, $scope, $period['start_date'], $period['end_date']);
            checkExport(count($rows) === 5 + max(1, $result->num_rows), 'Attendance row count differs from preview.');
        }
        foreach (['lap_gaji_divisi', 'rekap_gaji_divisi'] as $type) {
            [, $rows, $xpath] = runExport(['report' => 'gaji', 'tipe' => $type, 'cabang_id' => $scope] + $period);
            $result = laporanGaji($conn, $type, $scope, '', $period['start_date'], $period['end_date']);
            checkExport(count($rows) === 6 + max(1, $result->num_rows), 'Payroll row count differs from preview.');
            $totals = [0, 0, 0];
            while ($row = $result->fetch_assoc()) {
                foreach (['total_penghasilan', 'total_potongan', 'gaji_bersih'] as $index => $key) $totals[$index] += (float)$row[$key];
            }
            foreach ($totals as $index => $total) checkExport(abs($rows[4][$index + 1] - $total) < 0.01, 'Payroll total differs from preview.');
            checkExport($xpath->query('//s:row[@r="5"]/s:c[s:v]')->length === 3, 'Payroll amounts must be numeric Excel cells.');
        }
    }
    [, , $ownerSheet] = runExport(['report' => 'karyawan', 'tipe' => 'rekap'], 'owner');
    checkExport($ownerSheet !== null, 'Owner should be able to export.');
    foreach ([
        ['laporan_karyawan_print.php', ['tipe' => 'rekap']],
        ['laporan_absensi_print.php', ['tipe' => 'log']],
        ['laporan_absensi_print.php', ['tipe' => 'statistik']],
        ['laporan_gaji_print.php', ['tipe' => 'lap_gaji_divisi']],
        ['laporan_gaji_print.php', ['tipe' => 'rekap_gaji_divisi']],
    ] as [$endpoint, $params]) {
        [, $body] = runExport($params + $period, 'admin', $endpoint);
        checkExport(str_contains($body, 'id="report-container"') && !preg_match('/Warning:|Fatal error:/', $body), 'Preview regression: ' . $endpoint);
    }
    if ($employee) {
        [, $rows] = runExport(['report' => 'gaji', 'tipe' => 'per_karyawan', 'user_id' => $employee['id_karyawan'], 'cabang_id' => 'all'] + $period);
        $result = laporanGaji($conn, 'per_karyawan', 'all', $employee['id_karyawan'], $period['start_date'], $period['end_date']);
        checkExport(count($rows) === 6 + max(1, $result->num_rows), 'Personal payroll row count differs.');
        foreach (array_slice($rows, 6, $result->num_rows) as $row) checkExport($row[1] === (string)$employee['id_karyawan'], 'Personal payroll scope failed.');
    }
    [, $rows] = runExport(['report' => 'absensi', 'tipe' => 'log', 'start_date' => '2099-01-01', 'end_date' => '2099-01-01']);
    checkExport($rows[5][0] === 'Tidak ada data sesuai filter.', 'Empty report should be readable.');
    foreach (['supervisor', 'staff', ''] as $role) {
        [, $body, $sheet] = runExport(['report' => 'gaji', 'tipe' => 'lap_gaji_divisi'] + $period, $role);
        checkExport($sheet === null && $body === '', 'Unauthorized role received export data.');
    }
    foreach ([
        ['report' => 'absensi', 'tipe' => 'log', 'start_date' => '2026-02-30'],
        ['report' => 'absensi', 'tipe' => 'log', 'start_date' => '2026-12-01', 'end_date' => '2026-01-01'],
        ['report' => 'gaji', 'tipe' => 'per_karyawan'],
        ['report' => 'karyawan', 'tipe' => 'rekap', 'cabang_id' => 'invalid'],
        ['report' => 'karyawan', 'tipe' => 'rekap', 'status' => 'invalid'],
        ['report' => 'gaji', 'tipe' => 'cetak_slip_divisi'],
        ['report' => ['gaji'], 'tipe' => 'lap_gaji_divisi'],
    ] as $params) {
        [$status, , $sheet] = runExport($params);
        checkExport($status === 400 && $sheet === null, 'Invalid filters should fail with HTTP 400.');
    }
    echo "Laporan exports passed: filters, numeric totals, empty reports, role denial, invalid requests.\n";
} finally {
    foreach (glob($temp . '/*') as $file) unlink($file);
    rmdir($temp);
}
