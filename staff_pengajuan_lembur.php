<?php
/**
 * ==========================================
 * PENGAJUAN IZIN LEMBUR - Karyawan
 * ==========================================
 * Karyawan mengajukan izin lembur untuk rentang tanggal tertentu (mis. ada
 * proyek yang harus diselesaikan malam itu) sebelum overtime terjadi. Tidak
 * ada kuota di sini - ini bukan hak istirahat, cuma izin dari atasan supaya
 * jam lembur yang nanti tercatat lewat absen aktual dihitung ke slip gaji.
 */

require_once 'config.php';

$role_pengaju = $_SESSION['role'] ?? '';
if (!isLoggedIn() || !in_array($role_pengaju, ['staff', 'supervisor', 'admin'], true)) {
    header('Location: login.php');
    exit();
}

if ($role_pengaju === 'admin') {
    include 'admin_header.php';
    $footer_pengaju = 'admin_footer.php';
} elseif ($role_pengaju === 'supervisor') {
    include 'supervisor_header.php';
    $footer_pengaju = 'supervisor_footer.php';
} else {
    include 'staff_header.php';
    $footer_pengaju = 'staff_footer.php';
}

$id_karyawan_staff = $_SESSION['id_karyawan'] ?? '';
if ($id_karyawan_staff === '') {
    $_SESSION['error_message'] = 'Akun Anda belum tertaut dengan data karyawan.';
    header('Location: ' . dashboardUntukRole($role_pengaju));
    exit();
}
$csrf_token = generateCSRFToken();

$tahun_aktif = isset($_GET['tahun']) ? intval($_GET['tahun']) : (int)date('Y');
if ($tahun_aktif < 2020 || $tahun_aktif > (int)date('Y') + 1) {
    $tahun_aktif = (int)date('Y');
}

kedaluwarsakanPenugasanLembur($conn);

// Penugasan dari atasan yang masih menunggu jawaban karyawan ini.
$stmt_tugas = $conn->prepare("SELECT p.*, ut.nama AS nama_penugas
                              FROM pengajuan_lembur p
                              LEFT JOIN users ut ON p.ditugaskan_oleh = ut.id
                              WHERE p.id_karyawan = ? AND p.sumber = 'Penugasan'
                                AND p.status = 'Menunggu Karyawan'
                              ORDER BY p.tanggal_mulai ASC");
$stmt_tugas->bind_param("s", $id_karyawan_staff);
$stmt_tugas->execute();
$penugasan_menunggu = $stmt_tugas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_tugas->close();

$sql_riwayat = "SELECT p.*, u.nama AS nama_reviewer, ut.nama AS nama_penugas
                FROM pengajuan_lembur p
                LEFT JOIN users u ON p.reviewed_by = u.id
                LEFT JOIN users ut ON p.ditugaskan_oleh = ut.id
                WHERE p.id_karyawan = ? AND YEAR(p.tanggal_mulai) = ?
                  AND p.status <> 'Menunggu Karyawan'
                ORDER BY p.created_at DESC";
$stmt_riwayat = $conn->prepare($sql_riwayat);
$stmt_riwayat->bind_param("si", $id_karyawan_staff, $tahun_aktif);
$stmt_riwayat->execute();
$riwayat = $stmt_riwayat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_riwayat->close();

$stmt_tahun = $conn->prepare("SELECT DISTINCT YEAR(tanggal_mulai) AS tahun FROM pengajuan_lembur
                              WHERE id_karyawan = ? ORDER BY tahun DESC");
$stmt_tahun->bind_param("s", $id_karyawan_staff);
$stmt_tahun->execute();
$daftar_tahun = array_column($stmt_tahun->get_result()->fetch_all(MYSQLI_ASSOC), 'tahun');
$stmt_tahun->close();
if (!in_array((int)date('Y'), $daftar_tahun)) {
    array_unshift($daftar_tahun, (int)date('Y'));
}
?>

<!-- Header Halaman -->
<div class="mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Pengajuan Izin Lembur</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Ajukan izin lembur SEBELUM malam itu terjadi (mis. ada proyek yang harus diselesaikan). Jam lembur dihitung otomatis dari absen aktual Anda setelah izin disetujui.
    </p>
</div>

<?php include 'alert_messages.php'; ?>

<?php if (!empty($penugasan_menunggu)): ?>
<!-- Penugasan dari Atasan -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-sky-200 dark:border-sky-800/50 shadow-sm mb-8 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3">
        <i class="ph-duotone ph-user-focus text-xl text-sky-600 dark:text-sky-400"></i>
        <h2 class="font-bold text-slate-800 dark:text-white">Penugasan Lembur dari Atasan</h2>
        <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"><?php echo count($penugasan_menunggu); ?> menunggu jawaban</span>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
        <?php foreach ($penugasan_menunggu as $tg): ?>
            <div class="px-6 py-5">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-800 dark:text-white">
                            <?php echo formatRentangTanggal($tg['tanggal_mulai'], $tg['tanggal_selesai']); ?>
                            <span class="ml-2 text-sm font-semibold text-amber-600 dark:text-amber-400"><i class="ph-bold ph-clock"></i> <?php echo formatJendelaJamLembur($tg['jam_mulai'], $tg['jam_selesai']); ?></span>
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo safe_output($tg['keperluan']); ?></p>
                    </div>
                    <p class="text-xs text-slate-400 text-right shrink-0">
                        Dari <?php echo !empty($tg['nama_penugas']) ? safe_output($tg['nama_penugas']) : 'atasan'; ?><br>
                        <?php echo date('d/m/Y H:i', strtotime($tg['created_at'])); ?><br>
                        <span class="text-sky-600 dark:text-sky-400 font-semibold">Jawab paling lambat <?php echo date('d/m/Y', strtotime($tg['tanggal_mulai'])); ?></span>
                    </p>
                </div>

                <form action="proses_pengajuan_lembur.php" method="POST" class="form-respon flex flex-wrap gap-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="respon_penugasan" value="1">
                    <input type="hidden" name="id_pengajuan" value="<?php echo (int)$tg['id']; ?>">
                    <input type="hidden" name="aksi" class="input-aksi" value="">
                    <input type="text" name="alasan_karyawan" maxlength="255"
                           placeholder="Catatan / alasan (wajib bila menolak)"
                           class="flex-1 min-w-[220px] px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-sky-500 focus:border-transparent outline-none transition">
                    <button type="submit" data-aksi="terima"
                            class="btn-respon inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition">
                        <i class="ph-bold ph-check"></i> Terima
                    </button>
                    <button type="submit" data-aksi="tolak"
                            class="btn-respon inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white dark:bg-slate-700 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 text-sm font-semibold hover:bg-rose-50 dark:hover:bg-rose-900/30 transition">
                        <i class="ph-bold ph-x"></i> Tolak
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 mb-8">
    <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3">Ketentuan</p>
    <ul class="space-y-2.5 text-sm text-slate-600 dark:text-slate-300">
        <li class="flex gap-2.5">
            <i class="ph-duotone ph-info text-sky-500 text-lg shrink-0"></i>
            <span>Izin ini <b>tidak memotong kuota cuti</b> - beda dari Izin/Cuti, ini murni persetujuan untuk boleh lembur.</span>
        </li>
        <li class="flex gap-2.5">
            <i class="ph-duotone ph-clock text-amber-500 text-lg shrink-0"></i>
            <span>Jam lembur yang dihitung ke slip gaji adalah selisih jam pulang aktual Anda dengan jam pulang shift normal, HANYA pada tanggal yang disetujui di sini.</span>
        </li>
        <li class="flex gap-2.5">
            <i class="ph-duotone ph-calendar-x text-slate-400 text-lg shrink-0"></i>
            <span>Harus diajukan sebelum tanggal pelaksanaan (tidak bisa mundur), maksimal 31 hari per pengajuan.</span>
        </li>
    </ul>
</div>

<!-- Form Pengajuan -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm mb-8 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3">
        <i class="ph-duotone ph-note-pencil text-xl text-amber-600 dark:text-amber-400"></i>
        <h2 class="font-bold text-slate-800 dark:text-white">Buat Pengajuan Baru</h2>
    </div>

    <form action="proses_pengajuan_lembur.php" method="POST" class="p-6" id="form-lembur">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="ajukan_lembur" value="1">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" id="tanggal-mulai" required min="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" id="tanggal-selesai" required min="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div class="mb-5">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Keperluan / Proyek</label>
            <textarea name="keperluan" rows="3" required minlength="5"
                      placeholder="Contoh: Menyelesaikan laporan closing bulanan sebelum deadline besok pagi."
                      class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition resize-none"></textarea>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-semibold shadow-lg shadow-amber-600/20 hover:shadow-amber-600/40 hover:-translate-y-0.5 transition-all">
                <i class="ph-bold ph-paper-plane-tilt"></i>
                Kirim Pengajuan
            </button>
        </div>
    </form>
</div>

<!-- Riwayat Pengajuan -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <i class="ph-duotone ph-clock-counter-clockwise text-xl text-amber-600 dark:text-amber-400"></i>
            <h2 class="font-bold text-slate-800 dark:text-white">Riwayat Pengajuan</h2>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tahun</label>
            <select name="tahun" onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm outline-none">
                <?php foreach ($daftar_tahun as $th): ?>
                    <option value="<?php echo $th; ?>" <?php echo $th == $tahun_aktif ? 'selected' : ''; ?>><?php echo $th; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($riwayat)): ?>
        <div class="px-6 py-16 text-center">
            <i class="ph-duotone ph-tray text-5xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-sm text-slate-400 mt-3">Belum ada pengajuan lembur pada tahun <?php echo $tahun_aktif; ?>.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            <?php foreach ($riwayat as $row): ?>
                <?php
                $is_penugasan = (($row['sumber'] ?? 'Pengajuan') === 'Penugasan');
                $bisa_batal   = !$is_penugasan && in_array($row['status'], ['Pending', 'Disetujui'], true);
                $jendela_jam  = formatJendelaJamLembur($row['jam_mulai'] ?? null, $row['jam_selesai'] ?? null);
                ?>
                <div class="px-6 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo badgeStatusLembur($row['status']); ?>">
                                    <?php echo safe_output($row['status']); ?>
                                </span>
                                <?php if ($is_penugasan): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold border bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50">
                                        <i class="ph-bold ph-user-plus"></i> Ditugaskan<?php echo !empty($row['nama_penugas']) ? ' oleh ' . safe_output($row['nama_penugas']) : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p class="font-semibold text-slate-800 dark:text-white">
                                <?php echo formatRentangTanggal($row['tanggal_mulai'], $row['tanggal_selesai']); ?>
                                <?php if ($jendela_jam !== ''): ?>
                                    <span class="ml-2 text-sm font-semibold text-amber-600 dark:text-amber-400"><i class="ph-bold ph-clock"></i> <?php echo $jendela_jam; ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo safe_output($row['keperluan']); ?></p>

                            <?php if ($is_penugasan && !empty($row['alasan_karyawan'])): ?>
                                <div class="mt-3 px-3.5 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50">
                                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-0.5">Jawaban Anda</p>
                                    <p class="text-sm text-slate-600 dark:text-slate-300"><?php echo safe_output($row['alasan_karyawan']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($row['catatan_reviewer'])): ?>
                                <div class="mt-3 px-3.5 py-2.5 rounded-lg bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50">
                                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-0.5">
                                        Catatan <?php echo !empty($row['nama_reviewer']) ? safe_output($row['nama_reviewer']) : 'Reviewer'; ?>
                                    </p>
                                    <p class="text-sm text-slate-600 dark:text-slate-300"><?php echo safe_output($row['catatan_reviewer']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-xs text-slate-400 mb-2">
                                <?php echo $is_penugasan ? 'Ditugaskan' : 'Diajukan'; ?> <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                            </p>
                            <?php if ($bisa_batal): ?>
                                <form action="proses_pengajuan_lembur.php" method="POST" class="inline form-batal">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="batal_lembur" value="1">
                                    <input type="hidden" name="id_pengajuan" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800/50 hover:bg-rose-100 dark:hover:bg-rose-900/40 transition">
                                        <i class="ph-bold ph-x-circle"></i> Batalkan
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const mulaiEl = document.getElementById('tanggal-mulai');
    const selesaiEl = document.getElementById('tanggal-selesai');
    mulaiEl.addEventListener('change', function () {
        selesaiEl.min = mulaiEl.value;
        if (selesaiEl.value && selesaiEl.value < mulaiEl.value) selesaiEl.value = mulaiEl.value;
    });

    document.querySelectorAll('.form-respon').forEach(function (form) {
        const inputAksi = form.querySelector('.input-aksi');
        const alasan    = form.querySelector('input[name="alasan_karyawan"]');
        form.querySelectorAll('.btn-respon').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const aksi = btn.dataset.aksi;
                inputAksi.value = aksi;

                if (aksi === 'tolak' && alasan.value.trim().length < 5) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Alasan wajib diisi',
                            text: 'Tuliskan alasan menolak (minimal 5 karakter) agar atasan tahu penyebabnya.',
                            confirmButtonColor: '#d97706'
                        }).then(function () { alasan.focus(); });
                    } else {
                        alert('Tuliskan alasan menolak terlebih dahulu.');
                        alasan.focus();
                    }
                    return;
                }

                if (typeof Swal === 'undefined') { form.submit(); return; }
                Swal.fire({
                    title: aksi === 'terima' ? 'Terima penugasan lembur ini?' : 'Tolak penugasan lembur ini?',
                    text: aksi === 'terima'
                        ? 'Setelah diterima, pembatalan hanya bisa dilakukan oleh atasan yang menugaskan.'
                        : 'Atasan akan melihat alasan penolakan Anda.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: aksi === 'terima' ? '#059669' : '#e11d48',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: aksi === 'terima' ? 'Ya, terima' : 'Ya, tolak',
                    cancelButtonText: 'Kembali'
                }).then(function (result) {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    });

    document.querySelectorAll('.form-batal').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (typeof Swal === 'undefined') return;
            e.preventDefault();
            Swal.fire({
                title: 'Batalkan pengajuan lembur?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Ya, batalkan',
                cancelButtonText: 'Kembali'
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });
})();
</script>

<?php include $footer_pengaju; ?>
