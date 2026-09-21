<?php
require 'config.php';
require_once 'absensi_otomatis_functions.php';

// Wajib dijalankan oleh cron/Task Scheduler, bukan melalui URL publik.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}

// Default menutup hari ini. Argumen opsional dipakai untuk menjalankan ulang:
// php cron_tutup_absensi.php 2026-09-21
$tanggal = $argv[1] ?? date('Y-m-d');

try {
    $hasil = catatUnpaidAlphaOtomatis($conn, $tanggal);

    if (!$hasil['hari_kerja']) {
        echo "{$tanggal} bukan hari kerja. Tidak ada data yang dibuat." . PHP_EOL;
        exit(0);
    }

    echo "Penutupan absensi {$tanggal} selesai. "
       . "UNPAID/Alpha dibuat: {$hasil['dibuat']}; "
       . "sudah tercatat: {$hasil['sudah_tercatat']}; "
       . "libur cabang: {$hasil['libur']}; "
       . "karyawan diperiksa: {$hasil['kandidat']}." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Penutupan absensi gagal: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

