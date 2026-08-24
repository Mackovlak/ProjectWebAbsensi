<?php
/**
 * ==========================================
 * KELOLA PENGAJUAN IZIN - Supervisor/Admin/Owner
 * ==========================================
 * Satu halaman untuk semua role reviewer; chrome (header/footer) dipilih
 * mengikuti role yang login, dan supervisor otomatis dibatasi pada cabangnya.
 */

require_once 'config.php';
requireApprover();

$role = $_SESSION['role'];
$header_file = $role === 'supervisor' ? 'supervisor_header.php'
             : ($role === 'owner' ? 'owner_header.php' : 'admin_header.php');
$footer_file = $role === 'supervisor' ? 'supervisor_footer.php'
             : ($role === 'owner' ? 'owner_footer.php' : 'admin_footer.php');

include $header_file;

$csrf_token = generateCSRFToken();

// Owner hanya memantau; persetujuan adalah wewenang supervisor & admin.
$boleh_review = ($role === 'supervisor' || $role === 'admin');

// null = semua cabang (admin/owner), selain itu dibatasi id cabang
$cabang_reviewer = getCabangReviewer($conn, $_SESSION['user_id'], $role);

// ---------- Filter ----------
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'Pending';
if (!in_array($filter_status, ['Pending', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Semua'], true)) {
    $filter_status = 'Pending';
}

$filter_jenis = isset($_GET['jenis']) ? sanitizeInput($_GET['jenis']) : 'Semua';
if (!in_array($filter_jenis, IZIN_JENIS_VALID, true)) {
    $filter_jenis = 'Semua';
}

// ---------- Query daftar pengajuan ----------
$sql = "SELECT p.*, k.nama_karyawan, k.id_cabang, c.nama_cabang, j.nama_jabatan,
               k.jatah_cuti, u.nama AS nama_reviewer
        FROM pengajuan_izin p
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan
        LEFT JOIN cabang c ON k.id_cabang = c.id
        LEFT JOIN jabatan j ON k.id_jabatan = j.id
        LEFT JOIN users u ON p.reviewed_by = u.id
        WHERE 1=1";

$params = [];
$types  = '';

if ($cabang_reviewer !== null) {
    $sql .= " AND k.id_cabang = ?";
    $params[] = $cabang_reviewer;
    $types   .= 'i';
}
if ($filter_status !== 'Semua') {
    $sql .= " AND p.status = ?";
    $params[] = $filter_status;
    $types   .= 's';
}
if ($filter_jenis !== 'Semua') {
    $sql .= " AND p.jenis = ?";
    $params[] = $filter_jenis;
    $types   .= 's';
}

// Pending selalu di atas, lalu yang paling baru
$sql .= " ORDER BY FIELD(p.status, 'Pending', 'Disetujui', 'Ditolak', 'Dibatalkan'), p.created_at DESC LIMIT 300";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Statistik ringkas ----------
$sql_stat = "SELECT p.status, COUNT(*) AS jml
             FROM pengajuan_izin p
             JOIN karyawan k ON p.id_karyawan = k.id_karyawan
             WHERE YEAR(p.tanggal_mulai) = ?";
$params_stat = [(int)date('Y')];
$types_stat  = 'i';

if ($cabang_reviewer !== null) {
    $sql_stat .= " AND k.id_cabang = ?";
    $params_stat[] = $cabang_reviewer;
    $types_stat   .= 'i';
}
$sql_stat .= " GROUP BY p.status";

$stmt_stat = $conn->prepare($sql_stat);
$stmt_stat->bind_param($types_stat, ...$params_stat);
$stmt_stat->execute();
$res_stat = $stmt_stat->get_result();
$stat = ['Pending' => 0, 'Disetujui' => 0, 'Ditolak' => 0, 'Dibatalkan' => 0];
while ($row = $res_stat->fetch_assoc()) {
    $stat[$row['status']] = (int)$row['jml'];
}
$stmt_stat->close();

$nama_cabang_reviewer = null;
if ($cabang_reviewer !== null && $cabang_reviewer > 0) {
    $stmt_c = $conn->prepare("SELECT nama_cabang FROM cabang WHERE id = ?");
    $stmt_c->bind_param("i", $cabang_reviewer);
    $stmt_c->execute();
    $row_c = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();
    $nama_cabang_reviewer = $row_c['nama_cabang'] ?? null;
}
?>

<div class="mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Kelola Pengajuan Izin</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        <?php if ($cabang_reviewer !== null): ?>
            Pengajuan izin, cuti, dan dinas luar untuk cabang
            <b><?php echo $nama_cabang_reviewer ? safe_output($nama_cabang_reviewer) : 'yang Anda supervisi'; ?></b>.
        <?php else: ?>
            Seluruh pengajuan izin, cuti, dan dinas luar dari semua cabang.
        <?php endif; ?>
        <?php if (!$boleh_review): ?>
            <span class="text-amber-600 dark:text-amber-400 font-semibold">Mode pantau &mdash; persetujuan dilakukan oleh Supervisor atau Admin.</span>
        <?php endif; ?>
    </p>
</div>

<?php include 'alert_messages.php'; ?>

<!-- Statistik -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php
    $kartu = [
        ['label' => 'Menunggu Review', 'nilai' => $stat['Pending'],    'ikon' => 'ph-hourglass-medium', 'warna' => 'amber'],
        ['label' => 'Disetujui',       'nilai' => $stat['Disetujui'],  'ikon' => 'ph-check-circle',     'warna' => 'emerald'],
        ['label' => 'Ditolak',         'nilai' => $stat['Ditolak'],    'ikon' => 'ph-x-circle',         'warna' => 'rose'],
        ['label' => 'Dibatalkan',      'nilai' => $stat['Dibatalkan'], 'ikon' => 'ph-prohibit',         'warna' => 'slate'],
    ];
    foreach ($kartu as $k):
    ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $k['label']; ?></p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?php echo $k['nilai']; ?></p>
                </div>
                <i class="ph-duotone <?php echo $k['ikon']; ?> text-3xl text-<?php echo $k['warna']; ?>-500"></i>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Status</label>
        <select name="status" onchange="this.form.submit()"
                class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm outline-none">
            <?php foreach (['Pending', 'Disetujui', 'Ditolak', 'Dibatalkan', 'Semua'] as $opsi): ?>
                <option value="<?php echo $opsi; ?>" <?php echo $filter_status === $opsi ? 'selected' : ''; ?>><?php echo $opsi; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Jenis</label>
        <select name="jenis" onchange="this.form.submit()"
                class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm outline-none">
            <option value="Semua">Semua Jenis</option>
            <?php foreach (IZIN_JENIS_VALID as $opsi): ?>
                <option value="<?php echo $opsi; ?>" <?php echo $filter_jenis === $opsi ? 'selected' : ''; ?>><?php echo $opsi; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<!-- Daftar Pengajuan -->
<?php if (empty($daftar)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-6 py-20 text-center shadow-sm">
        <i class="ph-duotone ph-tray text-5xl text-slate-300 dark:text-slate-600"></i>
        <p class="text-sm text-slate-400 mt-3">Tidak ada pengajuan dengan filter ini.</p>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($daftar as $row): ?>
            <?php
                $kuota_kar = getRingkasanKuotaIzin($conn, $row['id_karyawan'], (int)date('Y', strtotime($row['tanggal_mulai'])));
                $rincian   = hitungHariIzin($conn, $row['id_karyawan'], $row['tanggal_mulai'], $row['tanggal_selesai']);
                $is_pending = ($row['status'] === 'Pending');
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border <?php echo $is_pending ? 'border-amber-200 dark:border-amber-800/50' : 'border-slate-200 dark:border-slate-700'; ?> shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo badgeJenisIzin($row['jenis']); ?>">
                                    <?php echo safe_output($row['jenis']); ?>
                                </span>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo badgeStatusIzin($row['status']); ?>">
                                    <?php echo safe_output($row['status']); ?>
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white"><?php echo safe_output($row['nama_karyawan']); ?></h3>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <?php echo safe_output($row['id_karyawan']); ?>
                                <?php if (!empty($row['nama_jabatan'])): ?> &middot; <?php echo safe_output($row['nama_jabatan']); ?><?php endif; ?>
                                <?php if (!empty($row['nama_cabang'])): ?> &middot; <?php echo safe_output($row['nama_cabang']); ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo formatRentangTanggal($row['tanggal_mulai'], $row['tanggal_selesai']); ?></p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <?php echo (int)$row['jumlah_hari']; ?> hari kalender &middot;
                                <b><?php echo $is_pending ? (int)$rincian['hari_efektif'] : (int)$row['jumlah_hari_kerja']; ?> hari kerja</b>
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">Diajukan <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></p>
                        </div>
                    </div>

                    <div class="px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50 mb-4">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">Keperluan</p>
                        <p class="text-sm text-slate-700 dark:text-slate-200"><?php echo safe_output($row['keperluan']); ?></p>
                        <?php if (!empty($row['lampiran'])): ?>
                            <a href="assets/uploads/izin/<?php echo urlencode($row['lampiran']); ?>" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs font-semibold text-fuchsia-600 dark:text-fuchsia-400 hover:underline mt-2">
                                <i class="ph-bold ph-paperclip"></i> Lihat lampiran
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Konteks kuota, supaya reviewer tidak perlu membuka halaman lain -->
                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-xs mb-4">
                        <span class="text-slate-500 dark:text-slate-400">
                            Kuota <?php echo $kuota_kar['tahun']; ?>:
                            <b class="text-slate-700 dark:text-slate-200"><?php echo $kuota_kar['terpakai']; ?>/<?php echo $kuota_kar['jatah']; ?></b> terpakai
                        </span>
                        <span class="text-slate-500 dark:text-slate-400">
                            Sisa: <b class="text-emerald-600 dark:text-emerald-400"><?php echo $kuota_kar['sisa']; ?> hari</b>
                        </span>
                        <?php if (!$row['potong_kuota']): ?>
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                <i class="ph-bold ph-check-circle"></i>
                                <?php echo ($row['jenis'] === 'Sakit') ? 'Sakit + bukti - tidak memotong kuota' : 'Tidak memotong kuota'; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($is_pending && $rincian['hari_efektif'] !== (int)$row['jumlah_hari_kerja']): ?>
                            <span class="text-amber-600 dark:text-amber-400 font-semibold">
                                <i class="ph-bold ph-warning"></i>
                                Hari kerja berubah sejak diajukan (<?php echo (int)$row['jumlah_hari_kerja']; ?> &rarr; <?php echo (int)$rincian['hari_efektif']; ?>)
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($rincian['tanggal_dilewati']) && $is_pending): ?>
                            <span class="text-slate-400">
                                <?php echo count($rincian['tanggal_dilewati']); ?> hari dilewati (Minggu/libur/sudah absen)
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($row['catatan_reviewer'])): ?>
                        <div class="px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50 mb-4">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">
                                Catatan <?php echo !empty($row['nama_reviewer']) ? safe_output($row['nama_reviewer']) : 'Reviewer'; ?>
                                <?php if (!empty($row['reviewed_at'])): ?>
                                    &middot; <?php echo date('d/m/Y H:i', strtotime($row['reviewed_at'])); ?>
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-slate-600 dark:text-slate-300"><?php echo safe_output($row['catatan_reviewer']); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_pending && $boleh_review): ?>
                        <form action="proses_pengajuan_izin.php" method="POST" class="form-review border-t border-slate-100 dark:border-slate-700 pt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="review_izin" value="1">
                            <input type="hidden" name="id_pengajuan" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="aksi" class="input-aksi" value="">

                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                                Catatan <span class="font-semibold normal-case tracking-normal text-slate-400">(wajib bila menolak)</span>
                            </label>
                            <div class="flex flex-wrap gap-3">
                                <input type="text" name="catatan_reviewer" maxlength="255"
                                       placeholder="Contoh: Disetujui, pastikan handover ke rekan shift."
                                       class="flex-1 min-w-[240px] px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent outline-none transition">

                                <button type="submit" data-aksi="setujui"
                                        class="btn-review inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition">
                                    <i class="ph-bold ph-check"></i> Setujui
                                </button>
                                <button type="submit" data-aksi="tolak"
                                        class="btn-review inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white dark:bg-slate-700 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 text-sm font-semibold hover:bg-rose-50 dark:hover:bg-rose-900/30 transition">
                                    <i class="ph-bold ph-x"></i> Tolak
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.form-review').forEach(function (form) {
    const inputAksi = form.querySelector('.input-aksi');
    const catatan   = form.querySelector('input[name="catatan_reviewer"]');

    form.querySelectorAll('.btn-review').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const aksi = btn.dataset.aksi;
            inputAksi.value = aksi;

            if (aksi === 'tolak' && catatan.value.trim().length < 3) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Alasan wajib diisi',
                        text: 'Tuliskan alasan penolakan agar karyawan tahu penyebabnya.',
                        confirmButtonColor: '#c026d3'
                    }).then(function () { catatan.focus(); });
                } else {
                    alert('Tuliskan alasan penolakan terlebih dahulu.');
                    catatan.focus();
                }
                return;
            }

            if (typeof Swal === 'undefined') { form.submit(); return; }

            Swal.fire({
                title: aksi === 'setujui' ? 'Setujui pengajuan ini?' : 'Tolak pengajuan ini?',
                text: aksi === 'setujui'
                    ? 'Absensi karyawan pada tanggal tersebut akan tercatat otomatis.'
                    : 'Karyawan akan melihat alasan penolakan Anda.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: aksi === 'setujui' ? '#059669' : '#e11d48',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: aksi === 'setujui' ? 'Ya, setujui' : 'Ya, tolak',
                cancelButtonText: 'Batal'
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>

<?php include $footer_file; ?>
