<?php
/**
 * ==========================================
 * DATA HARI LIBUR & HARI KERJA - Admin
 * ==========================================
 * Master hari libur yang menggerakkan kalender di semua dashboard, sekaligus
 * pengaturan hari kerja perusahaan (default Senin-Jumat, Sabtu hari lembur).
 */

require 'config.php';
include 'admin_header.php';

$csrf_token = generateCSRFToken();

$tahun_aktif = isset($_GET['tahun']) ? intval($_GET['tahun']) : (int)date('Y');
if ($tahun_aktif < 2020 || $tahun_aktif > (int)date('Y') + 5) {
    $tahun_aktif = (int)date('Y');
}

// Daftar hari libur tahun terpilih
$stmt = $conn->prepare("SELECT h.*, c.nama_cabang
                        FROM hari_libur h
                        LEFT JOIN cabang c ON h.id_cabang = c.id
                        WHERE YEAR(h.tanggal) = ?
                        ORDER BY h.tanggal ASC");
$stmt->bind_param("i", $tahun_aktif);
$stmt->execute();
$daftar_libur = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Tahun yang punya data
$res_tahun = $conn->query("SELECT DISTINCT YEAR(tanggal) AS tahun FROM hari_libur ORDER BY tahun DESC");
$daftar_tahun = [];
if ($res_tahun) {
    while ($r = $res_tahun->fetch_assoc()) $daftar_tahun[] = (int)$r['tahun'];
}
foreach ([(int)date('Y'), (int)date('Y') + 1] as $th) {
    if (!in_array($th, $daftar_tahun, true)) $daftar_tahun[] = $th;
}
rsort($daftar_tahun);

// Cabang untuk dropdown
$res_cabang = $conn->query("SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC");

$hari_kerja_aktif    = getHariKerja($conn);
$hari_overtime_aktif = getHariOvertime($conn);
$jumlah_verifikasi   = 0;
foreach ($daftar_libur as $l) {
    if ((int)$l['perlu_verifikasi'] === 1) $jumlah_verifikasi++;
}
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Hari Libur &amp; Hari Kerja</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Tanggal di sini otomatis muncul di kalender semua pengguna dan tidak memotong kuota cuti karyawan.
        </p>
    </div>
    <button onclick="openModal('modal-tambah-libur')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30 w-full sm:w-auto whitespace-nowrap">
        <i class="fa-solid fa-calendar-plus"></i> Tambah Hari Libur
    </button>
</div>

<?php include 'alert_messages.php'; ?>

<?php if ($jumlah_verifikasi > 0): ?>
<div class="mb-6 px-5 py-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50">
    <div class="flex items-start gap-3">
        <i class="ph-duotone ph-warning-circle text-2xl text-amber-600 dark:text-amber-400 shrink-0"></i>
        <div class="text-sm text-amber-800 dark:text-amber-300">
            <b><?php echo $jumlah_verifikasi; ?> tanggal masih bertanda "Perlu Verifikasi".</b>
            Tanggal tersebut berasal dari seed otomatis dan mengikuti kalender Hijriah/Imlek/Saka yang
            baru pasti setelah <b>SKB 3 Menteri</b> diterbitkan. Mohon cocokkan dengan keputusan resmi,
            lalu perbaiki atau hapus bila berbeda.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Pengaturan Hari Kerja -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm mb-8 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-3">
        <i class="ph-duotone ph-briefcase text-xl text-fuchsia-600 dark:text-fuchsia-400"></i>
        <h3 class="font-bold text-slate-800 dark:text-white">Pengaturan Hari Kerja Perusahaan</h3>
    </div>

    <form action="master_process.php" method="POST" class="p-6">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="simpan_hari_kerja" value="1">

        <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
            <b>Hari kerja</b> memotong kuota cuti dan dinilai keterlambatannya.
            <b>Hari lembur</b> tidak memotong kuota, tidak dihitung terlambat, dan jam kerjanya
            dihitung sebagai lembur bagi jabatan yang berhak.
        </p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead>
                    <tr class="text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200 dark:border-slate-700">
                        <th class="px-3 py-2 text-left">Hari</th>
                        <?php foreach (KALENDER_NAMA_HARI as $n => $nama): ?>
                            <th class="px-3 py-2 text-center"><?php echo substr($nama, 0, 3); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-slate-100 dark:border-slate-700/60">
                        <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-200">Hari kerja</td>
                        <?php foreach (KALENDER_NAMA_HARI as $n => $nama): ?>
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" name="hari_kerja[]" value="<?php echo $n; ?>"
                                       <?php echo in_array($n, $hari_kerja_aktif, true) ? 'checked' : ''; ?>
                                       class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-200">Hari lembur</td>
                        <?php foreach (KALENDER_NAMA_HARI as $n => $nama): ?>
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" name="hari_overtime[]" value="<?php echo $n; ?>"
                                       <?php echo in_array($n, $hari_overtime_aktif, true) ? 'checked' : ''; ?>
                                       class="w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-5 border-t border-slate-100 dark:border-slate-700/60">
            <p class="text-xs text-slate-400">
                Saat ini: hari kerja <b class="text-slate-600 dark:text-slate-300"><?php echo labelHariKerja($conn); ?></b>,
                hari lembur
                <b class="text-slate-600 dark:text-slate-300">
                    <?php
                    echo implode(', ', array_map(function ($n) { return KALENDER_NAMA_HARI[$n]; }, $hari_overtime_aktif));
                    ?>
                </b>.
            </p>
            <button type="submit" class="px-6 py-2.5 bg-fuchsia-600 hover:bg-fuchsia-700 text-white rounded-xl text-sm font-semibold shadow-sm shadow-fuchsia-500/30 transition-colors">
                Simpan Pengaturan
            </button>
        </div>
    </form>
</div>

<!-- Daftar Hari Libur -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <i class="ph-duotone ph-calendar-star text-xl text-rose-600 dark:text-rose-400"></i>
            <h3 class="font-bold text-slate-800 dark:text-white">Daftar Hari Libur <?php echo $tahun_aktif; ?></h3>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">
                <?php echo count($daftar_libur); ?> tanggal
            </span>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tahun</label>
            <select name="tahun" onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm outline-none">
                <?php foreach ($daftar_tahun as $th): ?>
                    <option value="<?php echo $th; ?>" <?php echo $th === $tahun_aktif ? 'selected' : ''; ?>><?php echo $th; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($daftar_libur)): ?>
        <div class="px-6 py-16 text-center">
            <i class="ph-duotone ph-calendar-blank text-5xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-sm text-slate-400 mt-3">Belum ada hari libur terdaftar untuk <?php echo $tahun_aktif; ?>.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
                <thead>
                    <tr class="text-left text-[11px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200 dark:border-slate-700">
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Keterangan</th>
                        <th class="px-6 py-3">Jenis</th>
                        <th class="px-6 py-3">Cakupan</th>
                        <th class="px-6 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($daftar_libur as $l): ?>
                        <?php
                            $ts = strtotime($l['tanggal']);
                            $nama_hari = KALENDER_NAMA_HARI[(int)date('N', $ts)];
                            $lewat = $l['tanggal'] < date('Y-m-d');
                            $badge_jenis = $l['jenis'] === 'Nasional'
                                ? 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-400 dark:border-rose-800/50'
                                : ($l['jenis'] === 'Cuti Bersama'
                                    ? 'bg-indigo-100 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-400 dark:border-indigo-800/50'
                                    : 'bg-teal-100 text-teal-700 border-teal-200 dark:bg-teal-900/30 dark:text-teal-400 dark:border-teal-800/50');
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors <?php echo $lewat ? 'opacity-60' : ''; ?>">
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <p class="font-semibold text-slate-800 dark:text-white"><?php echo date('j M Y', $ts); ?></p>
                                <p class="text-xs text-slate-400"><?php echo $nama_hari; ?></p>
                            </td>
                            <td class="px-6 py-3.5">
                                <p class="text-slate-700 dark:text-slate-200"><?php echo safe_output($l['nama']); ?></p>
                                <?php if ((int)$l['perlu_verifikasi'] === 1): ?>
                                    <span class="inline-flex items-center gap-1 mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800/50">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Perlu Verifikasi
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo $badge_jenis; ?>">
                                    <?php echo safe_output($l['jenis']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-slate-500 dark:text-slate-400">
                                <?php echo $l['id_cabang'] === null ? 'Semua Cabang' : safe_output($l['nama_cabang'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-3.5 text-center whitespace-nowrap">
                                <button type="button"
                                        onclick="editLibur(<?php echo (int)$l['id']; ?>, '<?php echo $l['tanggal']; ?>', <?php echo htmlspecialchars(json_encode($l['nama']), ENT_QUOTES); ?>, '<?php echo $l['jenis']; ?>', '<?php echo $l['id_cabang'] === null ? '' : (int)$l['id_cabang']; ?>')"
                                        class="p-2 text-brand-600 hover:bg-brand-50 rounded-lg dark:text-brand-400 dark:hover:bg-brand-900/30 transition-colors" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form action="master_process.php" method="POST" class="inline form-hapus-libur">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="hapus_hari_libur" value="1">
                                    <input type="hidden" name="id_libur" value="<?php echo (int)$l['id']; ?>">
                                    <input type="hidden" name="nama_libur" value="<?php echo safe_output($l['nama']); ?>">
                                    <button type="submit" class="p-2 text-red-600 hover:bg-red-50 rounded-lg dark:text-red-400 dark:hover:bg-red-900/30 transition-colors" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah / Edit Hari Libur -->
<div id="modal-tambah-libur" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-tambah-libur')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white" id="judul-modal-libur">Tambah Hari Libur</h3>
                <button onclick="closeModal('modal-tambah-libur')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form action="master_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="simpan_hari_libur" value="1">
                <input type="hidden" name="id_libur" id="in-id-libur" value="">

                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tanggal Mulai <span class="text-rose-500">*</span></label>
                        <input type="date" name="tanggal_mulai" id="in-tanggal-libur" required
                               class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>

                    <div id="wrap-tanggal-selesai">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai"
                               class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                        <p class="text-xs text-slate-500 mt-1">Kosongkan untuk satu hari saja. Isi untuk libur beruntun (mis. cuti bersama Idul Fitri).</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Keterangan <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama" id="in-nama-libur" required maxlength="150"
                               placeholder="Contoh: Hari Raya Idul Fitri 1447 Hijriah"
                               class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jenis</label>
                        <select name="jenis" id="in-jenis-libur"
                                class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                            <option value="Nasional">Libur Nasional</option>
                            <option value="Cuti Bersama">Cuti Bersama</option>
                            <option value="Perusahaan">Libur Perusahaan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cakupan Cabang</label>
                        <select name="id_cabang" id="in-cabang-libur"
                                class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                            <option value="">Semua Cabang</option>
                            <?php if ($res_cabang) { $res_cabang->data_seek(0); while ($c = $res_cabang->fetch_assoc()): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nama_cabang']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-tambah-libur')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                    <button type="submit" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl shadow-sm shadow-brand-500/30 transition-colors text-sm font-medium">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function editLibur(id, tanggal, nama, jenis, idCabang) {
    document.getElementById('judul-modal-libur').textContent = 'Edit Hari Libur';
    document.getElementById('in-id-libur').value = id;
    document.getElementById('in-tanggal-libur').value = tanggal;
    document.getElementById('in-nama-libur').value = nama;
    document.getElementById('in-jenis-libur').value = jenis;
    document.getElementById('in-cabang-libur').value = idCabang;
    // Rentang tanggal hanya untuk penambahan baru
    document.getElementById('wrap-tanggal-selesai').style.display = 'none';
    openModal('modal-tambah-libur');
}

// Reset modal ke mode tambah
document.querySelector('[onclick="openModal(\'modal-tambah-libur\')"]').addEventListener('click', function () {
    document.getElementById('judul-modal-libur').textContent = 'Tambah Hari Libur';
    document.getElementById('in-id-libur').value = '';
    document.getElementById('in-nama-libur').value = '';
    document.getElementById('in-tanggal-libur').value = '';
    document.getElementById('in-jenis-libur').value = 'Nasional';
    document.getElementById('in-cabang-libur').value = '';
    document.getElementById('wrap-tanggal-selesai').style.display = '';
});

// Konfirmasi hapus
document.querySelectorAll('.form-hapus-libur').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (typeof Swal === 'undefined') return;
        e.preventDefault();
        Swal.fire({
            title: 'Hapus hari libur ini?',
            text: 'Tanggal tersebut akan kembali dihitung sebagai hari kerja normal.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });
});
</script>

<?php include 'admin_footer.php'; ?>
