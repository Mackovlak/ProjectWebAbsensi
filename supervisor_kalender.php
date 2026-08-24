<?php
/**
 * ==========================================
 * HALAMAN KALENDER (Supervisor)
 * ==========================================
 * Halaman penuh untuk kalender tim, dipisah dari supervisor_dashboard.php
 * supaya landing dashboard tetap fokus ke ringkasan pengajuan. Mode global:
 * menampilkan seluruh rentang izin yang SUDAH disetujui pada cabang ini.
 */
include 'supervisor_header.php';

$kal_bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : (int)date('n');
$kal_tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : (int)date('Y');
if ($kal_bulan < 1 || $kal_bulan > 12) $kal_bulan = (int)date('n');
if ($kal_tahun < 2020 || $kal_tahun > (int)date('Y') + 2) $kal_tahun = (int)date('Y');

$kalender = bangunKalenderBulan($conn, $kal_bulan, $kal_tahun, [
    'id_cabang' => $cabang_supervisor,
    'global'    => true,
]);
$kalender_global = true;
$kalender_judul  = 'Kalender Tim' . ($nama_cabang_supervisor ? ' - ' . $nama_cabang_supervisor : '');
?>

<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Kalender Tim</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        <?php if ($nama_cabang_supervisor): ?>
            Hari kerja, hari lembur, hari libur, dan jadwal izin cabang <b><?php echo htmlspecialchars($nama_cabang_supervisor); ?></b>.
        <?php else: ?>
            <span class="text-amber-600 dark:text-amber-400 font-semibold">
                Akun Anda belum ditautkan ke cabang mana pun &mdash; hubungi Admin agar kalender tim Anda muncul di sini.
            </span>
        <?php endif; ?>
    </p>
</div>

<?php include 'alert_messages.php'; ?>

<?php include 'kalender_widget.php'; ?>

<?php include 'supervisor_footer.php'; ?>
