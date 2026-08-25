<?php
/**
 * ==========================================
 * DASHBOARD SUPERVISOR
 * ==========================================
 * Ringkasan pengajuan izin yang perlu direview dan pemakaian kuota cuti
 * seluruh karyawan pada cabang yang disupervisi.
 */

include 'supervisor_header.php';

$tahun = (int)date('Y');
$hari_ini = date('Y-m-d');

// ---------- Statistik pengajuan tahun berjalan ----------
$stmt_stat = $conn->prepare("SELECT p.status, COUNT(*) AS jml
                             FROM pengajuan_izin p
                             JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                             WHERE k.id_cabang = ? AND YEAR(p.tanggal_mulai) = ?
                             GROUP BY p.status");
$stmt_stat->bind_param("ii", $cabang_supervisor, $tahun);
$stmt_stat->execute();
$res_stat = $stmt_stat->get_result();
$stat = ['Pending' => 0, 'Disetujui' => 0, 'Ditolak' => 0, 'Dibatalkan' => 0];
while ($row = $res_stat->fetch_assoc()) {
    $stat[$row['status']] = (int)$row['jml'];
}
$stmt_stat->close();

// ---------- Karyawan yang sedang izin hari ini ----------
$stmt_hari_ini = $conn->prepare("SELECT p.jenis, p.tanggal_mulai, p.tanggal_selesai, p.keperluan, k.nama_karyawan
                                 FROM pengajuan_izin p
                                 JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                                 WHERE k.id_cabang = ?
                                   AND p.status = 'Disetujui'
                                   AND ? BETWEEN p.tanggal_mulai AND p.tanggal_selesai
                                 ORDER BY p.jenis ASC, k.nama_karyawan ASC");
$stmt_hari_ini->bind_param("is", $cabang_supervisor, $hari_ini);
$stmt_hari_ini->execute();
$sedang_izin = $stmt_hari_ini->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_hari_ini->close();

// ---------- Rekap kuota per karyawan ----------
$stmt_tim = $conn->prepare("SELECT k.id_karyawan, k.nama_karyawan, k.jatah_cuti, j.nama_jabatan
                            FROM karyawan k
                            LEFT JOIN jabatan j ON k.id_jabatan = j.id
                            WHERE k.id_cabang = ? AND k.status = 'aktif'
                            ORDER BY k.nama_karyawan ASC");
$stmt_tim->bind_param("i", $cabang_supervisor);
$stmt_tim->execute();
$tim = $stmt_tim->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_tim->close();
?>

<div class="mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">
        Selamat datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?>
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        <?php if ($nama_cabang_supervisor): ?>
            Anda mensupervisi cabang <b><?php echo htmlspecialchars($nama_cabang_supervisor); ?></b>.
        <?php else: ?>
            <span class="text-amber-600 dark:text-amber-400 font-semibold">
                Akun Anda belum ditautkan ke cabang mana pun &mdash; hubungi Admin agar pengajuan tim Anda muncul di sini.
            </span>
        <?php endif; ?>
    </p>
</div>

<?php include 'alert_messages.php'; ?>

<!-- Kartu Statistik -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <a href="kelola_pengajuan_izin.php?status=Pending"
       class="bg-white dark:bg-slate-800 rounded-2xl border <?php echo $stat['Pending'] > 0 ? 'border-amber-300 dark:border-amber-700' : 'border-slate-200 dark:border-slate-700'; ?> p-5 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Perlu Review</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $stat['Pending']; ?></p>
            </div>
            <i class="ph-duotone ph-hourglass-medium text-3xl text-amber-500"></i>
        </div>
    </a>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Disetujui <?php echo $tahun; ?></p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $stat['Disetujui']; ?></p>
            </div>
            <i class="ph-duotone ph-check-circle text-3xl text-emerald-500"></i>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Ditolak <?php echo $tahun; ?></p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $stat['Ditolak']; ?></p>
            </div>
            <i class="ph-duotone ph-x-circle text-3xl text-rose-500"></i>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">Izin Hari Ini</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo count($sedang_izin); ?></p>
            </div>
            <i class="ph-duotone ph-user-minus text-3xl text-sky-500"></i>
        </div>
    </div>
</div>

<!-- Kalender Global Tim -->
<div class="mb-6">
    <a href="supervisor_kalender.php" class="flex items-center justify-between gap-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 hover:-translate-y-0.5 hover:shadow-md transition-all group">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-12 h-12 shrink-0 rounded-xl bg-fuchsia-50 dark:bg-fuchsia-900/30 flex items-center justify-center">
                <i class="ph-duotone ph-calendar-dots text-2xl text-fuchsia-600 dark:text-fuchsia-400"></i>
            </div>
            <div class="min-w-0">
                <h3 class="font-bold text-slate-800 dark:text-white">Kalender Tim</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Hari kerja, hari libur, dan jadwal izin tim Anda.</p>
            </div>
        </div>
        <i class="ph-bold ph-arrow-right text-slate-400 group-hover:translate-x-1 transition-transform shrink-0"></i>
    </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- Sedang izin hari ini -->
    <div class="xl:col-span-1 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3">
            <i class="ph-duotone ph-calendar-x text-xl text-sky-600 dark:text-sky-400"></i>
            <h2 class="font-bold text-slate-800 dark:text-white">Tidak Masuk Hari Ini</h2>
        </div>

        <?php if (empty($sedang_izin)): ?>
            <div class="px-6 py-12 text-center">
                <i class="ph-duotone ph-users-three text-4xl text-slate-300 dark:text-slate-600"></i>
                <p class="text-sm text-slate-400 mt-2">Seluruh tim masuk hari ini.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                <?php foreach ($sedang_izin as $row): ?>
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="font-semibold text-slate-800 dark:text-white truncate"><?php echo safe_output($row['nama_karyawan']); ?></p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border uppercase shrink-0 <?php echo badgeJenisIzin($row['jenis']); ?>">
                                <?php echo safe_output($row['jenis']); ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo safe_output($row['keperluan']); ?></p>
                        <p class="text-[11px] text-slate-400 mt-1">
                            <i class="fa-regular fa-calendar mr-1"></i>
                            <?php echo formatRentangTanggal($row['tanggal_mulai'], $row['tanggal_selesai']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rekap kuota tim -->
    <div class="xl:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <i class="ph-duotone ph-chart-bar text-xl text-fuchsia-600 dark:text-fuchsia-400"></i>
                <h2 class="font-bold text-slate-800 dark:text-white">Pemakaian Kuota Izin <?php echo $tahun; ?></h2>
            </div>
            <a href="kelola_pengajuan_izin.php?status=Semua" class="text-xs font-semibold text-fuchsia-600 dark:text-fuchsia-400 hover:underline">
                Lihat semua pengajuan &rarr;
            </a>
        </div>

        <?php if (empty($tim)): ?>
            <div class="px-6 py-12 text-center">
                <i class="ph-duotone ph-users-three text-4xl text-slate-300 dark:text-slate-600"></i>
                <p class="text-sm text-slate-400 mt-2">Belum ada karyawan aktif pada cabang ini.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200 dark:border-slate-700">
                            <th class="px-6 py-3">Karyawan</th>
                            <th class="px-6 py-3">Terpakai</th>
                            <th class="px-6 py-3 w-1/3">Progres</th>
                            <th class="px-6 py-3 text-right">Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($tim as $anggota): ?>
                            <?php
                                $k = getRingkasanKuotaIzin($conn, $anggota['id_karyawan'], $tahun);
                                $persen = $k['jatah'] > 0 ? min(100, round(($k['terpakai'] / $k['jatah']) * 100)) : 0;
                                $warna_bar = $persen >= 100 ? 'bg-rose-500' : ($persen >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-3.5">
                                    <p class="font-semibold text-slate-800 dark:text-white"><?php echo safe_output($anggota['nama_karyawan']); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo safe_output($anggota['nama_jabatan'] ?? '-'); ?></p>
                                </td>
                                <td class="px-6 py-3.5 text-slate-600 dark:text-slate-300">
                                    <?php echo $k['terpakai']; ?> / <?php echo $k['jatah']; ?> hari
                                    <?php if ($k['tertahan'] > 0): ?>
                                        <span class="block text-[11px] text-amber-600 dark:text-amber-400 font-semibold">+<?php echo $k['tertahan']; ?> menunggu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="w-full h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                                        <div class="h-full <?php echo $warna_bar; ?> rounded-full" style="width: <?php echo $persen; ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-right font-bold <?php echo $k['sisa'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'; ?>">
                                    <?php echo $k['sisa']; ?> hari
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'supervisor_footer.php'; ?>
