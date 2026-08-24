<?php
/**
 * ==========================================
 * WIDGET KALENDER (partial, bukan halaman)
 * ==========================================
 * Dipakai bersama oleh dashboard staff (mode pribadi) dan dashboard
 * supervisor/admin/owner (mode global). Sertakan dengan menyiapkan variabel
 * berikut lebih dulu, lalu `include 'kalender_widget.php';`
 *
 *   $kalender        (wajib) hasil bangunKalenderBulan()
 *   $kalender_global (opsional) true = tampilkan nama karyawan pada tiap izin
 *   $kalender_judul  (opsional) judul kartu
 *   $kalender_url    (opsional) halaman tujuan tombol navigasi bulan,
 *                    default halaman saat ini
 *   $kalender_query  (opsional) array parameter GET tambahan yang dipertahankan
 *                    saat pindah bulan (mis. ['cabang' => 3])
 */

if (!isset($kalender) || empty($kalender['minggu'])) {
    return;
}

$k_global = !empty($kalender_global);
$k_judul  = $kalender_judul ?? ($k_global ? 'Kalender Tim' : 'Kalender Saya');
$k_url    = $kalender_url ?? basename($_SERVER['PHP_SELF']);
$k_query  = $kalender_query ?? [];

// Navigasi bulan sebelumnya / berikutnya
$k_prev_ts = strtotime(sprintf('%04d-%02d-01 -1 month', $kalender['tahun'], $kalender['bulan']));
$k_next_ts = strtotime(sprintf('%04d-%02d-01 +1 month', $kalender['tahun'], $kalender['bulan']));

$k_link = function ($ts) use ($k_url, $k_query) {
    $q = array_merge($k_query, ['bulan' => date('n', $ts), 'tahun' => date('Y', $ts)]);
    return $k_url . '?' . http_build_query($q);
};
$k_link_ini = function () use ($k_url, $k_query) {
    $q = array_merge($k_query, ['bulan' => date('n'), 'tahun' => date('Y')]);
    return $k_url . '?' . http_build_query($q);
};
?>

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">

    <!-- Kepala kartu: judul + navigasi bulan -->
    <div class="px-5 sm:px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <i class="ph-duotone ph-calendar-dots text-xl text-fuchsia-600 dark:text-fuchsia-400 shrink-0"></i>
            <div class="min-w-0">
                <h2 class="font-bold text-slate-800 dark:text-white truncate"><?php echo safe_output($k_judul); ?></h2>
                <p class="text-xs text-slate-400"><?php echo $kalender['label']; ?></p>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <a href="<?php echo safe_output($k_link($k_prev_ts)); ?>"
               class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 transition-colors"
               title="Bulan sebelumnya">
                <i class="ph-bold ph-caret-left"></i>
            </a>
            <a href="<?php echo safe_output($k_link_ini()); ?>"
               class="px-3 h-9 flex items-center rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 transition-colors">
                Hari Ini
            </a>
            <a href="<?php echo safe_output($k_link($k_next_ts)); ?>"
               class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 transition-colors"
               title="Bulan berikutnya">
                <i class="ph-bold ph-caret-right"></i>
            </a>
        </div>
    </div>

    <!-- Keterangan warna -->
    <div class="px-5 sm:px-6 py-3 border-b border-slate-100 dark:border-slate-700/60 flex flex-wrap gap-x-4 gap-y-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
        <span class="inline-flex items-center gap-1.5">
            <span class="w-3 h-3 rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800"></span> Hari kerja
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-3 h-3 rounded border border-amber-300 dark:border-amber-700 bg-amber-100 dark:bg-amber-900/40"></span> Hari lembur
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-3 h-3 rounded border border-rose-300 dark:border-rose-700 bg-rose-100 dark:bg-rose-900/40"></span> Libur nasional
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-3 h-3 rounded border border-slate-300 dark:border-slate-600 bg-slate-200 dark:bg-slate-700"></span> Libur mingguan
        </span>
    </div>

    <!-- Grid kalender -->
    <div class="p-3 sm:p-4 overflow-x-auto">
        <div class="min-w-[560px]">
            <!-- Nama hari -->
            <div class="grid grid-cols-7 gap-1.5 mb-1.5">
                <?php foreach (KALENDER_NAMA_HARI as $n => $nama): ?>
                    <div class="text-center text-[10px] sm:text-[11px] font-bold uppercase tracking-wider py-1.5
                                <?php echo $n === 7 ? 'text-rose-400' : ($n === 6 ? 'text-amber-500' : 'text-slate-400'); ?>">
                        <?php echo substr($nama, 0, 3); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Sel tanggal -->
            <?php foreach ($kalender['minggu'] as $baris): ?>
                <div class="grid grid-cols-7 gap-1.5 mb-1.5">
                    <?php foreach ($baris as $sel): ?>
                        <?php if ($sel === null): ?>
                            <div class="min-h-[76px] rounded-lg bg-transparent"></div>
                        <?php else: ?>
                            <?php
                                $kelas_sel = warnaSelKalender($sel['jenis']);
                                $ring = $sel['hari_ini'] ? ' ring-2 ring-fuchsia-500 ring-offset-1 dark:ring-offset-slate-800' : '';

                                // Tooltip: gabungkan semua info penting hari itu
                                $tip = [];
                                if ($sel['libur'])                     $tip[] = $sel['libur']['nama'];
                                if ($sel['jenis'] === 'overtime')      $tip[] = 'Hari lembur';
                                if ($sel['absensi']) {
                                    $tip[] = 'Absensi: ' . ($sel['absensi']['keterangan'] ?: '-')
                                        . ($sel['absensi']['jam_masuk'] ? ' (' . substr($sel['absensi']['jam_masuk'], 0, 5) . ')' : '');
                                }
                                foreach ($sel['izin'] as $iz) {
                                    $tip[] = ($k_global ? $iz['nama_karyawan'] . ': ' : '') . $iz['jenis'] . ' (' . $iz['status'] . ')';
                                }
                            ?>
                            <div class="min-h-[76px] rounded-lg border p-1.5 flex flex-col gap-1 <?php echo $kelas_sel . $ring; ?>"
                                 <?php if (!empty($tip)): ?>title="<?php echo safe_output(implode(' · ', $tip)); ?>"<?php endif; ?>>

                                <div class="flex items-start justify-between gap-1">
                                    <span class="text-xs font-bold <?php echo $sel['hari_ini'] ? 'text-fuchsia-600 dark:text-fuchsia-400' : 'text-slate-600 dark:text-slate-300'; ?>">
                                        <?php echo $sel['hari_ke']; ?>
                                    </span>
                                    <?php if ($sel['jenis'] === 'overtime'): ?>
                                        <i class="ph-bold ph-hourglass-high text-[10px] text-amber-500" title="Hari lembur"></i>
                                    <?php elseif ($sel['libur']): ?>
                                        <i class="ph-fill ph-star text-[10px] text-rose-500"></i>
                                    <?php endif; ?>
                                </div>

                                <?php if ($sel['libur']): ?>
                                    <p class="text-[9px] leading-tight font-semibold text-rose-600 dark:text-rose-400 line-clamp-2">
                                        <?php echo safe_output($sel['libur']['nama']); ?>
                                    </p>
                                <?php endif; ?>

                                <?php
                                // Absensi pribadi: tampilkan jam masuk atau keterangan
                                if (!$k_global && $sel['absensi']):
                                    $ket = $sel['absensi']['keterangan'];
                                    $warna_abs = 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';
                                    if ($ket === 'Hadir' || $ket === 'Dinas Luar') {
                                        $warna_abs = $sel['absensi']['status_masuk'] === 'Terlambat'
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
                                    }
                                ?>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?php echo $warna_abs; ?> truncate">
                                        <?php echo $sel['absensi']['jam_masuk']
                                            ? substr($sel['absensi']['jam_masuk'], 0, 5)
                                            : safe_output($ket); ?>
                                    </span>
                                <?php endif; ?>

                                <?php
                                // Rentang izin. Mode global bisa banyak orang di satu hari,
                                // jadi maksimal 2 ditampilkan lalu sisanya diringkas.
                                $tampil = array_slice($sel['izin'], 0, 2);
                                $sisa   = count($sel['izin']) - count($tampil);
                                foreach ($tampil as $iz):
                                    $pending = $iz['status'] === 'Pending';
                                ?>
                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded border truncate <?php echo badgeJenisIzin($iz['jenis']); ?> <?php echo $pending ? 'opacity-60 border-dashed' : ''; ?>"
                                          title="<?php echo safe_output(($k_global ? $iz['nama_karyawan'] . ' - ' : '') . $iz['jenis'] . ' (' . $iz['status'] . ')'); ?>">
                                        <?php if ($k_global): ?>
                                            <?php
                                                // Ruang sempit: pakai nama depan saja
                                                $nama_pendek = explode(' ', trim($iz['nama_karyawan']))[0];
                                                echo safe_output($nama_pendek);
                                            ?>
                                        <?php else: ?>
                                            <?php echo safe_output($iz['jenis']); ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>

                                <?php if ($sisa > 0): ?>
                                    <span class="text-[9px] font-bold text-slate-400">+<?php echo $sisa; ?> lagi</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Agenda bulan ini -->
    <?php if (!empty($kalender['agenda'])): ?>
        <div class="px-5 sm:px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">
                Agenda <?php echo $kalender['label']; ?>
            </p>
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <?php foreach ($kalender['agenda'] as $ag): ?>
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-11 text-center">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">
                                <?php echo substr(KALENDER_NAMA_HARI[(int)date('N', strtotime($ag['tanggal']))], 0, 3); ?>
                            </p>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo date('j', strtotime($ag['tanggal'])); ?></p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="text-sm font-semibold text-slate-800 dark:text-white truncate"><?php echo safe_output($ag['judul']); ?></p>
                                <?php if ($ag['tipe'] === 'libur'): ?>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50">
                                        <?php echo safe_output($ag['jenis']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase <?php echo badgeStatusIzin($ag['status']); ?>">
                                        <?php echo safe_output($ag['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($ag['tipe'] === 'izin'): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo safe_output($ag['rentang']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($ag['catatan'])): ?>
                                <p class="text-xs text-slate-400 truncate"><?php echo safe_output($ag['catatan']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
