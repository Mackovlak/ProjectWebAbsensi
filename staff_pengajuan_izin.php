<?php
/**
 * ==========================================
 * PENGAJUAN IZIN / CUTI / DINAS LUAR - Karyawan
 * ==========================================
 * Karyawan mengajukan izin untuk rentang tanggal, memantau sisa kuota
 * tahunan, dan melihat riwayat pengajuannya.
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
    $_SESSION['error_message'] = 'Akun Anda belum tertaut dengan data karyawan, sehingga tidak memiliki kuota cuti.';
    header('Location: ' . dashboardUntukRole($role_pengaju));
    exit();
}
$csrf_token = generateCSRFToken();

$tahun_aktif = isset($_GET['tahun']) ? intval($_GET['tahun']) : (int)date('Y');
if ($tahun_aktif < 2020 || $tahun_aktif > (int)date('Y') + 1) {
    $tahun_aktif = (int)date('Y');
}

$kuota = getRingkasanKuotaIzin($conn, $id_karyawan_staff, $tahun_aktif);

// Data untuk estimasi hari kerja di sisi klien. Angka final tetap dihitung
// ulang di server saat pengajuan disimpan & disetujui.
$stmt_cbg_izin = $conn->prepare("SELECT id_cabang FROM karyawan WHERE id_karyawan = ?");
$stmt_cbg_izin->bind_param("s", $id_karyawan_staff);
$stmt_cbg_izin->execute();
$row_cbg_izin = $stmt_cbg_izin->get_result()->fetch_assoc();
$stmt_cbg_izin->close();
$id_cabang_staff = $row_cbg_izin ? (int)$row_cbg_izin['id_cabang'] : null;

$hari_kerja_js = getHariKerja($conn);
// Ambil libur setahun ke depan supaya estimasi klien ikut melewatinya
$libur_map = getHariLibur(
    $conn,
    date('Y-m-d', strtotime('-1 month')),
    date('Y-m-d', strtotime('+13 months')),
    $id_cabang_staff
);
$libur_js = array_keys($libur_map);
// tanggal => nama hari libur, untuk tooltip & penanda di kalender
$libur_nama_js = array_map(function ($h) { return $h['nama'] ?? 'Hari libur'; }, $libur_map);
$tanggal_maks_js = date('Y-m-d', strtotime('+13 months'));
// Jumlah pengajuan setengah hari bulan ini, hanya sebagai info (tidak memblokir).
$setengah_bulan_ini = hitungPengajuanSetengahHariBulan($conn, $id_karyawan_staff, (int)date('n'), (int)date('Y'));
$persen_terpakai = $kuota['jatah'] > 0 ? min(100, round(($kuota['terpakai'] / $kuota['jatah']) * 100)) : 0;

// Riwayat pengajuan pada tahun terpilih
$sql_riwayat = "SELECT p.*, u.nama AS nama_reviewer
                FROM pengajuan_izin p
                LEFT JOIN users u ON p.reviewed_by = u.id
                WHERE p.id_karyawan = ? AND YEAR(p.tanggal_mulai) = ?
                ORDER BY p.created_at DESC";
$stmt_riwayat = $conn->prepare($sql_riwayat);
$stmt_riwayat->bind_param("si", $id_karyawan_staff, $tahun_aktif);
$stmt_riwayat->execute();
$riwayat = $stmt_riwayat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_riwayat->close();

// Daftar tahun yang punya data, untuk filter
$stmt_tahun = $conn->prepare("SELECT DISTINCT YEAR(tanggal_mulai) AS tahun FROM pengajuan_izin
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
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Pengajuan Izin &amp; Cuti</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        Ajukan izin untuk rentang tanggal tertentu. Pengajuan akan direview oleh atasan yang berwenang.
    </p>
</div>

<?php include 'alert_messages.php'; ?>

<!-- Ringkasan Kuota -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
        <div class="flex items-start justify-between mb-5">
            <div>
                <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Kuota Izin Tahun <?php echo $tahun_aktif; ?></p>
                <p class="text-3xl font-bold text-slate-800 dark:text-white mt-1">
                    <?php echo formatHariIzin($kuota['sisa']); ?><span class="text-lg text-slate-400 font-semibold"> / <?php echo $kuota['jatah']; ?> hari</span>
                </p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/30 flex items-center justify-center">
                <i class="ph-duotone ph-calendar-check text-2xl text-fuchsia-600 dark:text-fuchsia-400"></i>
            </div>
        </div>

        <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden mb-4">
            <div class="h-full bg-gradient-to-r from-fuchsia-500 to-purple-600 rounded-full transition-all duration-500"
                 style="width: <?php echo $persen_terpakai; ?>%"></div>
        </div>

        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <p class="text-xl font-bold text-slate-700 dark:text-slate-200"><?php echo formatHariIzin($kuota['terpakai']); ?></p>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">Terpakai</p>
            </div>
            <div>
                <p class="text-xl font-bold text-amber-600 dark:text-amber-400"><?php echo formatHariIzin($kuota['tertahan']); ?></p>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">Menunggu</p>
            </div>
            <div>
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo formatHariIzin($kuota['tersedia']); ?></p>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">Bisa Diajukan</p>
            </div>
        </div>
    </div>

    <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-200 dark:border-slate-700 p-6">
        <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3">Ketentuan</p>
        <ul class="space-y-2.5 text-sm text-slate-600 dark:text-slate-300">
            <li class="flex gap-2.5">
                <i class="ph-duotone ph-check-circle text-emerald-500 text-lg shrink-0"></i>
                <span><b>Cuti, Sakit,</b> dan <b>Izin</b> memotong jatah <?php echo $kuota['jatah']; ?> hari per tahun.</span>
            </li>
            <li class="flex gap-2.5">
                <i class="ph-duotone ph-clock-half text-fuchsia-500 text-lg shrink-0"></i>
                <span><b>Setengah hari</b> (Cuti/Izin) adalah jatah tersendiri: <b>tidak memotong</b> kuota <?php echo $kuota['jatah']; ?> hari. Batas wajar <?php echo IZIN_SETENGAH_HARI_MAKS_PER_BULAN; ?>x per bulan &mdash; lebih dari itu tetap bisa diajukan tanpa potongan, hanya ditandai untuk atasan. Tetap diajukan sebelum hari-H.</span>
            </li>
            <li class="flex gap-2.5">
                <i class="ph-duotone ph-briefcase text-sky-500 text-lg shrink-0"></i>
                <span><b>Dinas Luar</b> tidak memotong kuota, dan membebaskan validasi lokasi saat absen.</span>
            </li>
            <li class="flex gap-2.5">
                <i class="ph-duotone ph-heart text-pink-500 text-lg shrink-0"></i>
                <span><b>Menikah, Menikahkan Anak, Melahirkan,</b> dan <b>Duka Cita</b> adalah cuti khusus &mdash; tidak memotong kuota tahunan, lama pengajuan diserahkan ke persetujuan atasan.</span>
            </li>
            <li class="flex gap-2.5">
                <i class="ph-duotone ph-calendar-x text-slate-400 text-lg shrink-0"></i>
                <span>Hanya hari kerja (<b><?php echo labelHariKerja($conn); ?></b>) yang dihitung. Hari lembur, akhir pekan, dan hari libur nasional tidak memotong kuota.</span>
            </li>
        </ul>
    </div>
</div>

<!-- Form Pengajuan -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm mb-8 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3">
        <i class="ph-duotone ph-note-pencil text-xl text-fuchsia-600 dark:text-fuchsia-400"></i>
        <h2 class="font-bold text-slate-800 dark:text-white">Buat Pengajuan Baru</h2>
    </div>

    <form action="proses_pengajuan_izin.php" method="POST" enctype="multipart/form-data" class="p-6" id="form-izin">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="ajukan_izin" value="1">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jenis Pengajuan</label>
                <select name="jenis" id="jenis-izin" required
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent outline-none transition">
                    <option value="Cuti">Cuti Tahunan</option>
                    <option value="Izin">Izin Keperluan Pribadi</option>
                    <option value="Sakit">Sakit</option>
                    <option value="Dinas Luar">Dinas Luar (Tugas Kantor)</option>
                    <option value="Menikah">Menikah (Cuti Khusus)</option>
                    <option value="Menikahkan Anak">Menikahkan Anak (Cuti Khusus)</option>
                    <option value="Melahirkan">Cuti Melahirkan (Cuti Khusus)</option>
                    <option value="Duka Cita">Duka Cita / Keluarga Meninggal (Cuti Khusus)</option>
                </select>
                <p class="text-xs text-slate-400 mt-1.5" id="info-jenis">Memotong kuota tahunan Anda.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Lampiran <span class="font-normal text-slate-400">(opsional)</span></label>
                <input type="file" name="lampiran" id="lampiran-izin" accept=".jpg,.jpeg,.png,.pdf"
                       class="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-fuchsia-50 file:text-fuchsia-700 hover:file:bg-fuchsia-100">
                <p class="text-xs text-slate-400 mt-1.5" id="info-lampiran">Surat dokter / undangan / bukti pendukung. JPG, PNG, atau PDF maks 6 MB.</p>
            </div>

            <!-- Pemilih tanggal: satu kalender rentang (menggantikan dua input date bawaan browser) -->
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Tanggal
                    <span class="font-normal text-slate-400" id="petunjuk-tanggal">&mdash; klik tanggal mulai, lalu tanggal selesai</span>
                </label>

                <div class="flex flex-col md:flex-row gap-5 items-start">
                    <div id="kalender-izin" class="shrink-0 max-w-full">
                        <input type="text" id="picker-tanggal" class="hidden" aria-label="Pilih tanggal pengajuan">
                    </div>

                    <div class="flex-1 w-full space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-hari-ini"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-fuchsia-700 dark:text-fuchsia-300 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800/50 hover:bg-fuchsia-100 dark:hover:bg-fuchsia-900/40 transition">
                                <i class="ph-bold ph-calendar-dot"></i> Hari ini
                            </button>
                            <button type="button" id="btn-besok"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                                <i class="ph-bold ph-calendar-plus"></i> Besok
                            </button>
                            <button type="button" id="btn-hapus-tanggal"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition">
                                <i class="ph-bold ph-x"></i> Hapus
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-0.5">Mulai</p>
                                <p class="text-sm font-semibold text-slate-800 dark:text-white" id="teks-mulai">&mdash;</p>
                            </div>
                            <div class="px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-0.5">Selesai</p>
                                <p class="text-sm font-semibold text-slate-800 dark:text-white" id="teks-selesai">&mdash;</p>
                            </div>
                        </div>

                        <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <li class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-fuchsia-500"></span> Dipilih</li>
                            <li class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600"></span> Akhir pekan / hari lembur (tidak dihitung)</li>
                            <li class="inline-flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-400"></span> Hari libur (tidak dihitung)</li>
                        </ul>
                    </div>
                </div>

                <!-- Nilai yang dikirim ke server; diisi oleh kalender -->
                <input type="hidden" name="tanggal_mulai" id="tanggal-mulai">
                <input type="hidden" name="tanggal_selesai" id="tanggal-selesai">
            </div>
        </div>

        <!-- Durasi: sehari penuh / setengah hari (hanya Cuti & Izin) -->
        <div id="blok-durasi" class="mb-5">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Durasi</label>
            <div class="flex flex-wrap gap-3">
                <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm text-slate-700 dark:text-slate-200 cursor-pointer has-[:checked]:border-fuchsia-500 has-[:checked]:bg-fuchsia-50 dark:has-[:checked]:bg-fuchsia-900/20">
                    <input type="radio" name="durasi" value="penuh" id="durasi-penuh" checked class="accent-fuchsia-600">
                    Satu hari penuh / lebih
                </label>
                <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 text-sm text-slate-700 dark:text-slate-200 cursor-pointer has-[:checked]:border-fuchsia-500 has-[:checked]:bg-fuchsia-50 dark:has-[:checked]:bg-fuchsia-900/20">
                    <input type="radio" name="durasi" value="setengah" id="durasi-setengah" class="accent-fuchsia-600">
                    Setengah hari
                </label>
            </div>
            <input type="hidden" name="setengah_hari" id="input-setengah-hari" value="">

            <div id="blok-sesi" class="hidden mt-4">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Bagian hari yang diizinkan</label>
                <select name="sesi_setengah_hari" id="sesi-setengah-hari"
                        class="w-full md:w-1/2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent outline-none transition">
                    <option value="Masuk Siang">Masuk Siang (pagi libur, masuk setelah istirahat)</option>
                    <option value="Pulang Awal">Pulang Awal (masuk pagi, pulang sebelum jam pulang)</option>
                </select>
                <p class="text-xs text-slate-400 mt-1.5">
                    <b>Tidak memotong kuota tahunan</b>. Satu tanggal saja. Anda tetap absen (wajah &amp; lokasi).
                    <b>Masuk Siang</b>: tidak dihitung terlambat. <b>Pulang Awal</b>: tidak perlu izin pulang cepat.
                    Bulan ini sudah diajukan <b><?php echo $setengah_bulan_ini; ?>x</b> (batas wajar <?php echo IZIN_SETENGAH_HARI_MAKS_PER_BULAN; ?>x &mdash; lebih dari itu tetap bisa diajukan tanpa potongan, hanya ditandai untuk atasan).
                </p>
            </div>
        </div>

        <div class="mb-5">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Keperluan</label>
            <textarea name="keperluan" rows="3" required minlength="5"
                      placeholder="Contoh: Menjenguk keluarga di luar kota."
                      class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent outline-none transition resize-none"></textarea>
        </div>

        <div id="ringkasan-hari" class="hidden mb-5 px-4 py-3 rounded-xl bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800/50 text-sm text-fuchsia-800 dark:text-fuchsia-300"></div>

        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-fuchsia-600 to-purple-600 text-white text-sm font-semibold shadow-lg shadow-fuchsia-600/20 hover:shadow-fuchsia-600/40 hover:-translate-y-0.5 transition-all">
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
            <i class="ph-duotone ph-clock-counter-clockwise text-xl text-fuchsia-600 dark:text-fuchsia-400"></i>
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
            <p class="text-sm text-slate-400 mt-3">Belum ada pengajuan pada tahun <?php echo $tahun_aktif; ?>.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            <?php foreach ($riwayat as $row): ?>
                <?php
                    $bisa_batal = ($row['status'] === 'Pending')
                        || ($row['status'] === 'Disetujui' && $row['tanggal_mulai'] > date('Y-m-d'));
                ?>
                <div class="px-6 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo badgeJenisIzin($row['jenis']); ?>">
                                    <?php echo safe_output($row['jenis']); ?>
                                </span>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo badgeStatusIzin($row['status']); ?>">
                                    <?php echo safe_output($row['status']); ?>
                                </span>
                                <?php if (!empty($row['setengah_hari'])): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide bg-teal-100 text-teal-700 border-teal-200 dark:bg-teal-900/30 dark:text-teal-400 dark:border-teal-800/50">
                                        Setengah Hari &middot; <?php echo safe_output($row['sesi_setengah_hari']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($row['potong_kuota']): ?>
                                    <span class="text-xs text-slate-400 font-medium"><?php echo formatHariIzin($row['jumlah_hari_kerja']); ?> hari kuota</span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 font-medium">tidak memotong kuota</span>
                                <?php endif; ?>
                            </div>

                            <p class="font-semibold text-slate-800 dark:text-white">
                                <?php echo formatRentangTanggal($row['tanggal_mulai'], $row['tanggal_selesai']); ?>
                            </p>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo safe_output($row['keperluan']); ?></p>

                            <?php if (!empty($row['lampiran'])): ?>
                                <a href="assets/uploads/izin/<?php echo urlencode($row['lampiran']); ?>" target="_blank"
                                   class="inline-flex items-center gap-1.5 text-xs font-semibold text-fuchsia-600 dark:text-fuchsia-400 hover:underline mt-2">
                                    <i class="ph-bold ph-paperclip"></i> Lihat lampiran
                                </a>
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
                                Diajukan <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                            </p>
                            <?php if ($bisa_batal): ?>
                                <form action="proses_pengajuan_izin.php" method="POST" class="inline form-batal">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="batal_izin" value="1">
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css"
      integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
<style>
    /* Kalender rentang tanggal: warna mengikuti tema fuchsia halaman & mode gelap (class .dark) */
    #kalender-izin .flatpickr-calendar.inline { box-shadow: none; border: 1px solid #e2e8f0; border-radius: 1rem; width: 320px; max-width: 100%; }
    #kalender-izin .flatpickr-innerContainer, #kalender-izin .flatpickr-rContainer, #kalender-izin .dayContainer { width: 100%; min-width: 100%; max-width: 100%; }
    #kalender-izin .flatpickr-day { max-width: none; height: 40px; line-height: 40px; border-radius: 0.6rem; }
    #kalender-izin .flatpickr-day.today { border-color: #c026d3; }
    #kalender-izin .flatpickr-day.selected, #kalender-izin .flatpickr-day.startRange, #kalender-izin .flatpickr-day.endRange {
        background: #c026d3; border-color: #c026d3; color: #fff; }
    #kalender-izin .flatpickr-day.inRange { background: #fae8ff; border-color: #fae8ff; box-shadow: -5px 0 0 #fae8ff, 5px 0 0 #fae8ff; color: #86198f; }
    #kalender-izin .flatpickr-day.fp-pekan { color: #94a3b8; background: #f8fafc; }
    #kalender-izin .flatpickr-day.fp-libur { color: #e11d48; background: #fff1f2; font-weight: 600; }
    #kalender-izin .flatpickr-day.flatpickr-disabled { color: #cbd5e1; background: transparent; }
    #kalender-izin .flatpickr-day.selected.fp-pekan, #kalender-izin .flatpickr-day.selected.fp-libur,
    #kalender-izin .flatpickr-day.startRange.fp-pekan, #kalender-izin .flatpickr-day.endRange.fp-pekan,
    #kalender-izin .flatpickr-day.startRange.fp-libur, #kalender-izin .flatpickr-day.endRange.fp-libur { color: #fff; background: #c026d3; }

    .dark #kalender-izin .flatpickr-calendar { background: #1e293b; border-color: #334155; }
    .dark #kalender-izin .flatpickr-months .flatpickr-month, .dark #kalender-izin .flatpickr-current-month .flatpickr-monthDropdown-months,
    .dark #kalender-izin .flatpickr-current-month input.cur-year { color: #f1f5f9; fill: #f1f5f9; background: transparent; }
    .dark #kalender-izin .flatpickr-current-month .flatpickr-monthDropdown-months option { background: #1e293b; }
    .dark #kalender-izin .flatpickr-months .flatpickr-prev-month svg, .dark #kalender-izin .flatpickr-months .flatpickr-next-month svg { fill: #cbd5e1; }
    .dark #kalender-izin span.flatpickr-weekday { color: #94a3b8; background: transparent; }
    .dark #kalender-izin .flatpickr-day { color: #e2e8f0; }
    .dark #kalender-izin .flatpickr-day:hover { background: #334155; border-color: #334155; }
    .dark #kalender-izin .flatpickr-day.fp-pekan { color: #64748b; background: #0f172a; }
    .dark #kalender-izin .flatpickr-day.fp-libur { color: #fb7185; background: rgba(225, 29, 72, 0.12); }
    .dark #kalender-izin .flatpickr-day.flatpickr-disabled { color: #475569; background: transparent; }
    .dark #kalender-izin .flatpickr-day.inRange { background: rgba(192, 38, 211, 0.22); border-color: transparent; box-shadow: -5px 0 0 rgba(192, 38, 211, 0.22), 5px 0 0 rgba(192, 38, 211, 0.22); color: #f5d0fe; }
    .dark #kalender-izin .flatpickr-day.selected, .dark #kalender-izin .flatpickr-day.startRange, .dark #kalender-izin .flatpickr-day.endRange { background: #c026d3; color: #fff; }
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"
        integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/id.js"
        integrity="sha384-KMhDAY3QD073iimt/X068iLaMgNPBD+J57j7adrjgvlWbfAv2ONSvLFyOtU9TzDN" crossorigin="anonymous"></script>

<script>
(function () {
    const jenisEl     = document.getElementById('jenis-izin');
    const infoEl      = document.getElementById('info-jenis');
    const mulaiEl     = document.getElementById('tanggal-mulai');
    const selesaiEl   = document.getElementById('tanggal-selesai');
    const ringkasan   = document.getElementById('ringkasan-hari');
    const lampiranEl  = document.getElementById('lampiran-izin');
    const infoLampiranEl = document.getElementById('info-lampiran');

    const kuotaTersedia = <?php echo (float)$kuota['tersedia']; ?>;
    const jenisSetengahHari = <?php echo json_encode(IZIN_JENIS_SETENGAH_HARI); ?>;
    const blokDurasi   = document.getElementById('blok-durasi');
    const blokSesi     = document.getElementById('blok-sesi');
    const radioSetengah = document.getElementById('durasi-setengah');
    const radioPenuh   = document.getElementById('durasi-penuh');
    const inputSetengah = document.getElementById('input-setengah-hari');
    const sesiEl       = document.getElementById('sesi-setengah-hari');

    function fmtHari(n) { return String(n).replace('.', ','); }
    function isSetengahHari() { return radioSetengah.checked && jenisSetengahHari.includes(jenisEl.value); }

    // Setengah hari hanya untuk Cuti/Izin, dan selalu satu tanggal:
    // tanggal selesai dikunci mengikuti tanggal mulai.
    function perbaruiDurasi() {
        const boleh = jenisSetengahHari.includes(jenisEl.value);
        blokDurasi.classList.toggle('hidden', !boleh);
        if (!boleh) radioPenuh.checked = true;

        const setengah = isSetengahHari();
        blokSesi.classList.toggle('hidden', !setengah);
        inputSetengah.value = setengah ? '1' : '';
        sesiEl.disabled = !setengah;
        terapkanModeKalender(setengah);
    }

    // Setengah hari = satu tanggal (klik sekali); selain itu rentang (mulai -> selesai).
    function terapkanModeKalender(setengah) {
        if (!picker) return;
        const mode = setengah ? 'single' : 'range';
        if (picker.config.mode === mode) return;
        const pertama = picker.selectedDates[0] || null;
        picker.set('mode', mode);
        if (pertama) picker.setDate(pertama, true); else picker.clear();
        petunjukEl.innerHTML = setengah
            ? '&mdash; pilih satu tanggal'
            : '&mdash; klik tanggal mulai, lalu tanggal selesai';
    }
    const hariIni = '<?php echo date('Y-m-d'); ?>';
    // 1 = Senin ... 7 = Minggu, mengikuti pengaturan hari kerja perusahaan
    const hariKerja = <?php echo json_encode($hari_kerja_js); ?>;
    const tanggalLibur = <?php echo json_encode($libur_js); ?>;
    const namaLibur = <?php echo json_encode($libur_nama_js, JSON_UNESCAPED_UNICODE); ?>;
    const tanggalMaks = '<?php echo $tanggal_maks_js; ?>';

    // Tanggal lokal -> 'Y-m-d' (toISOString() memakai UTC dan bisa mundur sehari di WIB)
    function ymd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function parseYmd(str) { return new Date(str + 'T00:00:00'); }
    function tambahHari(str, n) { const d = parseYmd(str); d.setDate(d.getDate() + n); return ymd(d); }
    const formatTampil = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    const teksMulaiEl = document.getElementById('teks-mulai');
    const teksSelesaiEl = document.getElementById('teks-selesai');
    const petunjukEl = document.getElementById('petunjuk-tanggal');
    let picker = null; // diisi setelah semua fungsi terdefinisi (lihat bawah)

    const keteranganJenis = {
        'Cuti':             'Memotong kuota tahunan Anda.',
        'Izin':             'Memotong kuota tahunan Anda.',
        'Sakit':            'Boleh diajukan mundur maksimal 14 hari. Lampirkan surat dokter agar TIDAK memotong kuota tahunan Anda.',
        'Dinas Luar':       'Tidak memotong kuota. Setelah disetujui, absen di lokasi tugas tidak akan ditolak sistem.',
        'Menikah':          'Cuti khusus, tidak memotong kuota tahunan. Lama pengajuan mengikuti persetujuan atasan.',
        'Menikahkan Anak':  'Cuti khusus, tidak memotong kuota tahunan. Lama pengajuan mengikuti persetujuan atasan.',
        'Melahirkan':       'Cuti khusus, tidak memotong kuota tahunan. Lama pengajuan mengikuti persetujuan atasan.',
        'Duka Cita':        'Cuti khusus, tidak memotong kuota tahunan. Lama pengajuan mengikuti persetujuan atasan.'
    };

    // Diturunkan langsung dari IZIN_JENIS_VALID / IZIN_JENIS_POTONG_KUOTA di
    // izin_functions.php, bukan didaftar ulang manual, supaya tidak pernah
    // desync dari aturan kuota sisi server.
    const jenisQuotaExempt = <?php echo json_encode(array_values(array_diff(IZIN_JENIS_VALID, IZIN_JENIS_POTONG_KUOTA))); ?>;

    const infoLampiranDefault = infoLampiranEl.textContent;

    function perbaruiInfoLampiran() {
        const adaLampiran = lampiranEl.files && lampiranEl.files.length > 0;
        if (jenisEl.value === 'Sakit' && adaLampiran) {
            infoLampiranEl.innerHTML = '<b class="text-emerald-600 dark:text-emerald-400">Lampiran terpasang - pengajuan Sakit ini tidak akan memotong kuota tahunan.</b>';
        } else {
            infoLampiranEl.textContent = infoLampiranDefault;
        }
    }

    function batasTanggalMinimum() {
        // Hanya Sakit yang boleh mundur ke belakang (maks 14 hari)
        return jenisEl.value === 'Sakit' ? tambahHari(hariIni, -14) : hariIni;
    }

    function perbaruiBatas() {
        const min = batasTanggalMinimum();
        if (picker) {
            picker.set('minDate', min);
            // Pilihan lama yang jatuh sebelum batas baru dihapus supaya tidak lolos diam-diam
            if (mulaiEl.value && mulaiEl.value < min) picker.clear();
        }
        infoEl.textContent = keteranganJenis[jenisEl.value] || '';
    }

    // Tampilkan tanggal terpilih di panel samping kalender
    function tampilkanTanggalTerpilih() {
        teksMulaiEl.textContent = mulaiEl.value ? formatTampil.format(parseYmd(mulaiEl.value)) : '\u2014';
        teksSelesaiEl.textContent = selesaiEl.value ? formatTampil.format(parseYmd(selesaiEl.value)) : '\u2014';
    }

    // Kalender -> input tersembunyi yang dikirim ke server
    function sinkronkanDariKalender(dipilih) {
        if (!dipilih.length) {
            mulaiEl.value = '';
            selesaiEl.value = '';
        } else {
            mulaiEl.value = ymd(dipilih[0]);
            // Klik pertama langsung berarti satu hari; klik kedua memperpanjang rentang
            selesaiEl.value = ymd(dipilih[dipilih.length - 1]);
        }
        tampilkanTanggalTerpilih();
        hitungRingkasan();
    }

    // Hitung perkiraan hari kerja (lewati hari Minggu). Angka pasti tetap
    // dihitung ulang di server saat pengajuan disimpan & disetujui.
    function hitungRingkasan() {
        if (!mulaiEl.value || !selesaiEl.value) {
            ringkasan.classList.add('hidden');
            return;
        }

        const mulai = parseYmd(mulaiEl.value);
        const selesai = parseYmd(selesaiEl.value);
        if (mulai > selesai) {
            ringkasan.classList.add('hidden');
            return;
        }

        let total = 0, efektif = 0, dilewati = 0;
        for (let d = new Date(mulai); d <= selesai; d.setDate(d.getDate() + 1)) {
            total++;
            // getDay(): 0 = Minggu. Ubah ke ISO 1-7 agar cocok dengan hariKerja.
            const iso = d.getDay() === 0 ? 7 : d.getDay();
            const tgl = ymd(d);

            if (hariKerja.indexOf(iso) === -1 || tanggalLibur.indexOf(tgl) !== -1) {
                dilewati++;
            } else {
                efektif++;
            }
        }

        const adaLampiran = lampiranEl.files && lampiranEl.files.length > 0;
        const sakitDenganBukti = jenisEl.value === 'Sakit' && adaLampiran;
        // Setengah hari berdiri sendiri: tidak pernah memotong kuota tahunan.
        const setengahHari = isSetengahHari();
        const potong = !setengahHari && !jenisQuotaExempt.includes(jenisEl.value) && !sakitDenganBukti;
        let teks = `<b>${total} hari kalender</b>, perkiraan <b>${efektif} hari kerja</b>`;
        teks += dilewati > 0
            ? ` (${dilewati} hari dilewati: akhir pekan/hari lembur/libur nasional).`
            : '.';

        if (setengahHari) {
            teks = efektif > 0
                ? `<b>Setengah hari</b> (${sesiEl.value}) &mdash; <b>tidak memotong</b> kuota tahunan.`
                : '';
        } else if (potong) {
            teks += ` Sisa kuota yang bisa dipakai: <b>${fmtHari(kuotaTersedia)} hari</b>.`;
            if (efektif > kuotaTersedia) {
                teks += ` <span class="font-bold text-rose-600 dark:text-rose-400">Melebihi kuota Anda.</span>`;
            }
        } else if (sakitDenganBukti) {
            teks += ' Sakit dengan lampiran tidak memotong kuota.';
        } else {
            teks += ` ${jenisEl.value} tidak memotong kuota (cuti khusus).`;
        }

        if (efektif === 0) {
            teks += ' <span class="font-bold text-rose-600 dark:text-rose-400">Tidak ada hari kerja pada rentang ini.</span>';
        }
        if (mulai.getFullYear() !== selesai.getFullYear()) {
            teks += ' <span class="font-bold text-rose-600 dark:text-rose-400">Pengajuan tidak boleh melewati pergantian tahun &mdash; buat dua pengajuan terpisah.</span>';
        }

        ringkasan.innerHTML = teks;
        ringkasan.classList.remove('hidden');
    }

    jenisEl.addEventListener('change', function () { perbaruiBatas(); perbaruiDurasi(); perbaruiInfoLampiran(); hitungRingkasan(); });
    radioPenuh.addEventListener('change', function () { perbaruiDurasi(); hitungRingkasan(); });
    radioSetengah.addEventListener('change', function () { perbaruiDurasi(); hitungRingkasan(); });
    sesiEl.addEventListener('change', hitungRingkasan);
    lampiranEl.addEventListener('change', function () { perbaruiInfoLampiran(); hitungRingkasan(); });

    // ---------- Kalender rentang tanggal ----------
    if (typeof flatpickr === 'undefined') {
        // CDN tidak termuat: jangan biarkan form terlihat normal tapi tidak bisa dipakai.
        petunjukEl.innerHTML = '<span class="font-semibold text-rose-600 dark:text-rose-400">&mdash; kalender gagal dimuat, muat ulang halaman.</span>';
    } else {
        picker = flatpickr('#picker-tanggal', {
            inline: true,
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: Object.assign({}, flatpickr.l10ns.id, { firstDayOfWeek: 1 }),   // Senin pertama, seperti kalender dashboard
            minDate: batasTanggalMinimum(),
            maxDate: tanggalMaks,
            disableMobile: true,
            onChange: function (dipilih) { sinkronkanDariKalender(dipilih); },
            // Tandai akhir pekan/hari lembur dan hari libur supaya jelas mana yang tidak dihitung
            onDayCreate: function (_d, _s, _fp, el) {
                const tgl = ymd(el.dateObj);
                const iso = el.dateObj.getDay() === 0 ? 7 : el.dateObj.getDay();
                if (namaLibur[tgl] !== undefined) {
                    el.classList.add('fp-libur');
                    el.title = namaLibur[tgl];
                } else if (hariKerja.indexOf(iso) === -1) {
                    el.classList.add('fp-pekan');
                }
            }
        });

        document.getElementById('btn-hari-ini').addEventListener('click', function () {
            picker.setDate(hariIni, true);
            picker.jumpToDate(hariIni);
        });
        document.getElementById('btn-besok').addEventListener('click', function () {
            const besok = tambahHari(hariIni, 1);
            picker.setDate(besok, true);
            picker.jumpToDate(besok);
        });
        document.getElementById('btn-hapus-tanggal').addEventListener('click', function () { picker.clear(); });
    }

    perbaruiBatas();
    perbaruiDurasi();
    perbaruiInfoLampiran();
    tampilkanTanggalTerpilih();

    // Input tanggal kini tersembunyi, jadi atribut `required` tidak berlaku: periksa manual.
    document.getElementById('form-izin').addEventListener('submit', function (e) {
        let pesan = '';
        if (!mulaiEl.value || !selesaiEl.value) {
            pesan = 'Pilih tanggal pengajuan pada kalender terlebih dahulu.';
        } else if (mulaiEl.value.slice(0, 4) !== selesaiEl.value.slice(0, 4)) {
            pesan = 'Pengajuan tidak boleh melewati pergantian tahun. Buat dua pengajuan terpisah.';
        }
        if (pesan) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Tanggal belum lengkap', text: pesan, confirmButtonColor: '#c026d3' });
            else alert(pesan);
            document.getElementById('kalender-izin').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Konfirmasi pembatalan
    document.querySelectorAll('.form-batal').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (typeof Swal === 'undefined') return;
            e.preventDefault();
            Swal.fire({
                title: 'Batalkan pengajuan?',
                text: 'Kuota yang tertahan akan dikembalikan.',
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
