<?php
/**
 * ==========================================
 * PENGAJUAN IZIN / CUTI / DINAS LUAR - Staff
 * ==========================================
 * Karyawan mengajukan izin untuk rentang tanggal, memantau sisa kuota
 * tahunan, dan melihat riwayat pengajuannya.
 */

include 'staff_header.php';

$id_karyawan_staff = $_SESSION['id_karyawan'] ?? '';
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
$libur_js = array_keys(getHariLibur(
    $conn,
    date('Y-m-d', strtotime('-1 month')),
    date('Y-m-d', strtotime('+13 months')),
    $id_cabang_staff
));
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
        Ajukan izin untuk rentang tanggal tertentu. Pengajuan akan direview oleh Supervisor cabang Anda.
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
                    <?php echo $kuota['sisa']; ?><span class="text-lg text-slate-400 font-semibold"> / <?php echo $kuota['jatah']; ?> hari</span>
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
                <p class="text-xl font-bold text-slate-700 dark:text-slate-200"><?php echo $kuota['terpakai']; ?></p>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">Terpakai</p>
            </div>
            <div>
                <p class="text-xl font-bold text-amber-600 dark:text-amber-400"><?php echo $kuota['tertahan']; ?></p>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide mt-0.5">Menunggu</p>
            </div>
            <div>
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo $kuota['tersedia']; ?></p>
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
                <i class="ph-duotone ph-briefcase text-sky-500 text-lg shrink-0"></i>
                <span><b>Dinas Luar</b> tidak memotong kuota, dan membebaskan validasi lokasi saat absen.</span>
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
                </select>
                <p class="text-xs text-slate-400 mt-1.5" id="info-jenis">Memotong kuota tahunan Anda.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Lampiran <span class="font-normal text-slate-400">(opsional)</span></label>
                <input type="file" name="lampiran" id="lampiran-izin" accept=".jpg,.jpeg,.png,.pdf"
                       class="w-full px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-fuchsia-50 file:text-fuchsia-700 hover:file:bg-fuchsia-100">
                <p class="text-xs text-slate-400 mt-1.5" id="info-lampiran">Surat dokter / undangan / bukti pendukung. JPG, PNG, atau PDF maks 6 MB.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" id="tanggal-mulai" required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" id="tanggal-selesai" required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent outline-none transition">
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
                                <?php if ($row['potong_kuota']): ?>
                                    <span class="text-xs text-slate-400 font-medium"><?php echo (int)$row['jumlah_hari_kerja']; ?> hari kuota</span>
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

<script>
(function () {
    const jenisEl     = document.getElementById('jenis-izin');
    const infoEl      = document.getElementById('info-jenis');
    const mulaiEl     = document.getElementById('tanggal-mulai');
    const selesaiEl   = document.getElementById('tanggal-selesai');
    const ringkasan   = document.getElementById('ringkasan-hari');
    const lampiranEl  = document.getElementById('lampiran-izin');
    const infoLampiranEl = document.getElementById('info-lampiran');

    const kuotaTersedia = <?php echo (int)$kuota['tersedia']; ?>;
    const hariIni = '<?php echo date('Y-m-d'); ?>';
    // 1 = Senin ... 7 = Minggu, mengikuti pengaturan hari kerja perusahaan
    const hariKerja = <?php echo json_encode($hari_kerja_js); ?>;
    const tanggalLibur = <?php echo json_encode($libur_js); ?>;

    const keteranganJenis = {
        'Cuti':       'Memotong kuota tahunan Anda.',
        'Izin':       'Memotong kuota tahunan Anda.',
        'Sakit':      'Boleh diajukan mundur maksimal 14 hari. Lampirkan surat dokter agar TIDAK memotong kuota tahunan Anda.',
        'Dinas Luar': 'Tidak memotong kuota. Setelah disetujui, absen di lokasi tugas tidak akan ditolak sistem.'
    };

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
        // Hanya Sakit yang boleh mundur ke belakang
        return jenisEl.value === 'Sakit'
            ? new Date(Date.now() - 14 * 86400000).toISOString().slice(0, 10)
            : hariIni;
    }

    function perbaruiBatas() {
        const min = batasTanggalMinimum();
        mulaiEl.min = min;
        selesaiEl.min = mulaiEl.value || min;
        infoEl.textContent = keteranganJenis[jenisEl.value] || '';
    }

    // Hitung perkiraan hari kerja (lewati hari Minggu). Angka pasti tetap
    // dihitung ulang di server saat pengajuan disimpan & disetujui.
    function hitungRingkasan() {
        if (!mulaiEl.value || !selesaiEl.value) {
            ringkasan.classList.add('hidden');
            return;
        }

        const mulai = new Date(mulaiEl.value);
        const selesai = new Date(selesaiEl.value);
        if (mulai > selesai) {
            ringkasan.classList.add('hidden');
            return;
        }

        let total = 0, efektif = 0, dilewati = 0;
        for (let d = new Date(mulai); d <= selesai; d.setDate(d.getDate() + 1)) {
            total++;
            // getDay(): 0 = Minggu. Ubah ke ISO 1-7 agar cocok dengan hariKerja.
            const iso = d.getDay() === 0 ? 7 : d.getDay();
            const ymd = d.getFullYear() + '-' +
                        String(d.getMonth() + 1).padStart(2, '0') + '-' +
                        String(d.getDate()).padStart(2, '0');

            if (hariKerja.indexOf(iso) === -1 || tanggalLibur.indexOf(ymd) !== -1) {
                dilewati++;
            } else {
                efektif++;
            }
        }

        const adaLampiran = lampiranEl.files && lampiranEl.files.length > 0;
        const sakitDenganBukti = jenisEl.value === 'Sakit' && adaLampiran;
        const potong = jenisEl.value !== 'Dinas Luar' && !sakitDenganBukti;
        let teks = `<b>${total} hari kalender</b>, perkiraan <b>${efektif} hari kerja</b>`;
        teks += dilewati > 0
            ? ` (${dilewati} hari dilewati: akhir pekan/hari lembur/libur nasional).`
            : '.';

        if (potong) {
            teks += ` Sisa kuota yang bisa dipakai: <b>${kuotaTersedia} hari</b>.`;
            if (efektif > kuotaTersedia) {
                teks += ` <span class="font-bold text-rose-600 dark:text-rose-400">Melebihi kuota Anda.</span>`;
            }
        } else if (sakitDenganBukti) {
            teks += ' Sakit dengan lampiran tidak memotong kuota.';
        } else {
            teks += ' Dinas Luar tidak memotong kuota.';
        }

        if (efektif === 0) {
            teks += ' <span class="font-bold text-rose-600 dark:text-rose-400">Tidak ada hari kerja pada rentang ini.</span>';
        }

        ringkasan.innerHTML = teks;
        ringkasan.classList.remove('hidden');
    }

    jenisEl.addEventListener('change', function () { perbaruiBatas(); perbaruiInfoLampiran(); hitungRingkasan(); });
    mulaiEl.addEventListener('change', function () {
        selesaiEl.min = mulaiEl.value;
        if (selesaiEl.value && selesaiEl.value < mulaiEl.value) selesaiEl.value = mulaiEl.value;
        hitungRingkasan();
    });
    selesaiEl.addEventListener('change', hitungRingkasan);
    lampiranEl.addEventListener('change', function () { perbaruiInfoLampiran(); hitungRingkasan(); });
    perbaruiBatas();
    perbaruiInfoLampiran();

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

<?php include 'staff_footer.php'; ?>
