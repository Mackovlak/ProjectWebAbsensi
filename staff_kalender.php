<?php
/**
 * ==========================================
 * HALAMAN KALENDER (Staff)
 * ==========================================
 * Halaman penuh untuk kalender pribadi, dipisah dari staff_dashboard.php
 * supaya landing dashboard tetap fokus ke histori absensi.
 */
include 'staff_header.php';

$id_karyawan_staff = $_SESSION['id_karyawan'];

$kal_bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : (int)date('n');
$kal_tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : (int)date('Y');
if ($kal_bulan < 1 || $kal_bulan > 12) $kal_bulan = (int)date('n');
if ($kal_tahun < 2020 || $kal_tahun > (int)date('Y') + 2) $kal_tahun = (int)date('Y');

$stmt_cbg_kal = $conn->prepare("SELECT id_cabang FROM karyawan WHERE id_karyawan = ?");
$stmt_cbg_kal->bind_param("s", $id_karyawan_staff);
$stmt_cbg_kal->execute();
$row_cbg_kal = $stmt_cbg_kal->get_result()->fetch_assoc();
$stmt_cbg_kal->close();

$id_cabang_staff = $row_cbg_kal ? (int)$row_cbg_kal['id_cabang'] : null;

// Tanpa cabang, kalender tim tidak bisa dibatasi ke rekan satu cabang - daripada
// bocor ke semua cabang, turunkan ke mode pribadi saja untuk akun ini.
$kalender = bangunKalenderBulan($conn, $kal_bulan, $kal_tahun, [
    'id_karyawan' => $id_karyawan_staff,
    'id_cabang'   => $id_cabang_staff,
    'global'      => $id_cabang_staff !== null,
]);
$kalender_global = $id_cabang_staff !== null;
$kalender_judul  = $kalender_global ? 'Kalender Saya & Tim' : 'Kalender Saya';
?>

<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Kalender Saya &amp; Tim</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Hari kerja, hari lembur, hari libur, absensi &amp; izin Anda, serta jadwal Cuti/Sakit/Izin rekan kerja satu cabang
        <?php if (!$id_cabang_staff): ?>
            <span class="text-amber-600 dark:text-amber-400 font-semibold">&mdash; akun Anda belum ditautkan ke cabang, hubungi Admin agar bagian tim ini muncul.</span>
        <?php endif; ?>
    </p>
</div>

<?php include 'alert_messages.php'; ?>

<?php include 'kalender_widget.php'; ?>

<?php include 'staff_footer.php'; ?>
