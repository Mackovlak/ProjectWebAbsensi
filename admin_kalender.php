<?php
/**
 * ==========================================
 * HALAMAN KALENDER (Admin)
 * ==========================================
 * Halaman penuh untuk kalender perusahaan, dipisah dari admin_dashboard.php
 * supaya landing dashboard tetap ringkas. Admin melihat seluruh cabang,
 * dengan filter cabang opsional lewat ?cabang=.
 */
require 'config.php';
include 'admin_header.php';

$kal_bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : (int)date('n');
$kal_tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : (int)date('Y');
if ($kal_bulan < 1 || $kal_bulan > 12) $kal_bulan = (int)date('n');
if ($kal_tahun < 2020 || $kal_tahun > (int)date('Y') + 2) $kal_tahun = (int)date('Y');

$kal_cabang = isset($_GET['cabang']) && $_GET['cabang'] !== '' ? intval($_GET['cabang']) : null;

$kalender = bangunKalenderBulan($conn, $kal_bulan, $kal_tahun, [
    'id_cabang' => $kal_cabang,
    'global'    => true,
]);
$kalender_global = true;
$kalender_judul  = 'Kalender Perusahaan';
$kalender_query  = $kal_cabang !== null ? ['cabang' => $kal_cabang] : [];
?>

<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Kalender Perusahaan</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Hari kerja, hari lembur, hari libur, dan jadwal izin seluruh cabang.</p>
</div>

<?php include 'alert_messages.php'; ?>

<?php include 'kalender_widget.php'; ?>

<?php
include 'admin_footer.php';
?>
