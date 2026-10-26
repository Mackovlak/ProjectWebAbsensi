<?php
/**
 * ==========================================
 * KELOLA PENGAJUAN IZIN LEMBUR - Supervisor/Admin/Owner
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

// Owner hanya memantau; persetujuan & penugasan adalah wewenang supervisor & admin.
$boleh_review   = ($role === 'supervisor' || $role === 'admin');
$boleh_tugaskan = $boleh_review;

kedaluwarsakanPenugasanLembur($conn);

// null = semua cabang (admin/owner), selain itu dibatasi id cabang
$cabang_reviewer = getCabangReviewer($conn, $_SESSION['user_id'], $role);

// ---------- Filter ----------
$opsi_status   = array_merge(LEMBUR_STATUS_VALID, ['Semua']);
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'Pending';
if (!in_array($filter_status, $opsi_status, true)) {
    $filter_status = 'Pending';
}
$filter_sumber = isset($_GET['sumber']) ? sanitizeInput($_GET['sumber']) : 'Semua';
if (!in_array($filter_sumber, ['Semua', 'Pengajuan', 'Penugasan'], true)) {
    $filter_sumber = 'Semua';
}

// ---------- Query daftar pengajuan ----------
$sql = "SELECT p.*, k.nama_karyawan, k.id_cabang, c.nama_cabang, j.nama_jabatan,
               u.nama AS nama_reviewer, ut.nama AS nama_penugas
        FROM pengajuan_lembur p
        JOIN karyawan k ON p.id_karyawan = k.id_karyawan
        LEFT JOIN cabang c ON k.id_cabang = c.id
        LEFT JOIN jabatan j ON k.id_jabatan = j.id
        LEFT JOIN users u ON p.reviewed_by = u.id
        LEFT JOIN users ut ON p.ditugaskan_oleh = ut.id
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
if ($filter_sumber !== 'Semua') {
    $sql .= " AND p.sumber = ?";
    $params[] = $filter_sumber;
    $types   .= 's';
}

$sql .= " ORDER BY FIELD(p.status, 'Pending', 'Menunggu Karyawan', 'Disetujui', 'Ditolak Karyawan', 'Ditolak', 'Kedaluwarsa', 'Dibatalkan'), p.created_at DESC LIMIT 300";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Statistik ringkas ----------
$sql_stat = "SELECT p.status, COUNT(*) AS jml
             FROM pengajuan_lembur p
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
$stat = array_fill_keys(LEMBUR_STATUS_VALID, 0);
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

// ---------- Karyawan yang bisa ditugaskan ----------
// Aktif, punya akun aktif yang bisa membuka staff_pengajuan_lembur.php untuk
// merespon, di cabang penugas (bila supervisor), dan bukan diri sendiri.
$kandidat_tugas = [];
if ($boleh_tugaskan) {
    $sql_kandidat = "SELECT k.id_karyawan, k.nama_karyawan, j.nama_jabatan, c.nama_cabang
                     FROM karyawan k
                     JOIN users u ON u.id_karyawan = k.id_karyawan
                         AND u.is_active = 1 AND u.role IN ('staff','supervisor','admin')
                     LEFT JOIN jabatan j ON k.id_jabatan = j.id
                     LEFT JOIN cabang c ON k.id_cabang = c.id
                     WHERE k.status = 'aktif' AND k.id_karyawan <> ?";
    $id_diri = $_SESSION['id_karyawan'] ?? '';
    $params_kandidat = [$id_diri];
    $types_kandidat  = 's';
    if ($cabang_reviewer !== null) {
        $sql_kandidat .= " AND k.id_cabang = ?";
        $params_kandidat[] = $cabang_reviewer;
        $types_kandidat   .= 'i';
    }
    $sql_kandidat .= " ORDER BY c.nama_cabang, k.nama_karyawan";
    $stmt_kandidat = $conn->prepare($sql_kandidat);
    $stmt_kandidat->bind_param($types_kandidat, ...$params_kandidat);
    $stmt_kandidat->execute();
    $kandidat_tugas = $stmt_kandidat->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_kandidat->close();
}
?>

<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 dark:text-white tracking-tight">Kelola Pengajuan Izin Lembur</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
        <?php if ($cabang_reviewer !== null): ?>
            Pengajuan izin lembur untuk cabang
            <b><?php echo $nama_cabang_reviewer ? safe_output($nama_cabang_reviewer) : 'yang Anda supervisi'; ?></b>.
        <?php else: ?>
            Seluruh pengajuan izin lembur dari semua cabang.
        <?php endif; ?>
        <?php if (!$boleh_review): ?>
            <span class="text-amber-600 dark:text-amber-400 font-semibold">Mode pantau &mdash; persetujuan dilakukan oleh Supervisor atau Admin.</span>
        <?php endif; ?>
    </p>
  </div>
  <?php if ($boleh_tugaskan): ?>
    <button type="button" id="btn-buka-tugas"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-semibold shadow-lg shadow-amber-600/20 hover:shadow-amber-600/40 hover:-translate-y-0.5 transition-all">
        <i class="ph-bold ph-user-plus"></i> Tugaskan Lembur
    </button>
  <?php endif; ?>
</div>

<?php include 'alert_messages.php'; ?>

<?php if ($boleh_tugaskan): ?>
<!-- Form Penugasan Lembur -->
<div id="panel-tugas" class="hidden bg-white dark:bg-slate-800 rounded-2xl border border-amber-200 dark:border-amber-800/50 shadow-sm mb-8 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <i class="ph-duotone ph-user-plus text-xl text-amber-600 dark:text-amber-400"></i>
            <h2 class="font-bold text-slate-800 dark:text-white">Tugaskan Lembur ke Karyawan</h2>
        </div>
        <button type="button" id="btn-tutup-tugas" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i class="ph-bold ph-x text-lg"></i></button>
    </div>

    <form action="proses_pengajuan_lembur.php" method="POST" class="p-6" id="form-tugas">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="tugaskan_lembur" value="1">

        <div class="mb-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">
                    Karyawan <span id="jml-dipilih" class="ml-1 text-xs font-bold text-amber-600 dark:text-amber-400">(0 dipilih)</span>
                </label>
                <input type="text" id="cari-kandidat" placeholder="Cari nama / ID..."
                       class="w-full sm:w-64 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <?php if (empty($kandidat_tugas)): ?>
                <p class="text-sm text-slate-400 px-4 py-6 text-center rounded-xl border border-dashed border-slate-200 dark:border-slate-600">Tidak ada karyawan aktif dengan akun yang bisa ditugaskan.</p>
            <?php else: ?>
                <div class="max-h-64 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-600 divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($kandidat_tugas as $kd): ?>
                        <label class="baris-kandidat flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-amber-50/60 dark:hover:bg-slate-700/40"
                               data-cari="<?php echo safe_output(strtolower($kd['nama_karyawan'] . ' ' . $kd['id_karyawan'])); ?>">
                            <input type="checkbox" name="id_karyawan[]" value="<?php echo safe_output($kd['id_karyawan']); ?>"
                                   class="cek-kandidat w-4 h-4 rounded accent-amber-600">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200 truncate"><?php echo safe_output($kd['nama_karyawan']); ?></span>
                                <span class="block text-xs text-slate-400 truncate">
                                    <?php echo safe_output($kd['id_karyawan']); ?>
                                    <?php if (!empty($kd['nama_jabatan'])): ?> &middot; <?php echo safe_output($kd['nama_jabatan']); ?><?php endif; ?>
                                    <?php if ($cabang_reviewer === null && !empty($kd['nama_cabang'])): ?> &middot; <?php echo safe_output($kd['nama_cabang']); ?><?php endif; ?>
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" id="tugas-mulai" required min="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" id="tugas-selesai" required min="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jam Mulai</label>
                <input type="time" name="jam_mulai" id="tugas-jam-mulai" required value="17:00"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jam Selesai</label>
                <input type="time" name="jam_selesai" id="tugas-jam-selesai" required value="20:00"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">
            </div>
        </div>

        <div class="mb-5">
            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Pekerjaan / Keperluan</label>
            <textarea name="keperluan" rows="3" required minlength="5"
                      placeholder="Contoh: Stock opname gudang akhir bulan."
                      class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition resize-none"></textarea>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-xs text-slate-400 max-w-xl">
                Hanya hari kerja (<?php echo safe_output(labelHariKerja($conn)); ?>) yang dihitung; hari libur dan <?php echo safe_output(labelHariOvertime($conn)); ?> dilewati.
                Jam lembur di slip gaji dihitung dari absen pulang aktual, maksimal sampai Jam Selesai. Karyawan harus menerima sebelum tanggal mulai lewat.
            </p>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 text-white text-sm font-semibold shadow-lg shadow-amber-600/20 hover:shadow-amber-600/40 hover:-translate-y-0.5 transition-all">
                <i class="ph-bold ph-paper-plane-tilt"></i> Kirim Penugasan
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Statistik -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <?php
    $kartu = [
        ['label' => 'Menunggu Review',   'nilai' => $stat['Pending'],           'ikon' => 'ph-hourglass-medium', 'warna' => 'amber'],
        ['label' => 'Menunggu Karyawan', 'nilai' => $stat['Menunggu Karyawan'], 'ikon' => 'ph-user-focus',       'warna' => 'sky'],
        ['label' => 'Disetujui',         'nilai' => $stat['Disetujui'],         'ikon' => 'ph-check-circle',     'warna' => 'emerald'],
        ['label' => 'Ditolak',           'nilai' => $stat['Ditolak'] + $stat['Ditolak Karyawan'], 'ikon' => 'ph-x-circle', 'warna' => 'rose'],
        ['label' => 'Batal / Kedaluwarsa', 'nilai' => $stat['Dibatalkan'] + $stat['Kedaluwarsa'], 'ikon' => 'ph-prohibit', 'warna' => 'slate'],
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
            <?php foreach ($opsi_status as $opsi): ?>
                <option value="<?php echo $opsi; ?>" <?php echo $filter_status === $opsi ? 'selected' : ''; ?>><?php echo $opsi; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Sumber</label>
        <select name="sumber" onchange="this.form.submit()"
                class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm outline-none">
            <?php foreach (['Semua' => 'Semua', 'Pengajuan' => 'Diajukan Karyawan', 'Penugasan' => 'Ditugaskan Atasan'] as $nilai => $label): ?>
                <option value="<?php echo $nilai; ?>" <?php echo $filter_sumber === $nilai ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<!-- Daftar Pengajuan -->
<?php if (empty($daftar)): ?>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 px-6 py-20 text-center shadow-sm">
        <i class="ph-duotone ph-tray text-5xl text-slate-300 dark:text-slate-600"></i>
        <p class="text-sm text-slate-400 mt-3">Tidak ada pengajuan lembur dengan filter ini.</p>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($daftar as $row): ?>
            <?php
            $is_pending   = ($row['status'] === 'Pending');
            $is_penugasan = (($row['sumber'] ?? 'Pengajuan') === 'Penugasan');
            $bisa_tarik   = $is_penugasan && $boleh_tugaskan
                && ($row['status'] === 'Menunggu Karyawan'
                    || ($row['status'] === 'Disetujui' && $row['tanggal_mulai'] > date('Y-m-d')));
            $jendela_jam  = formatJendelaJamLembur($row['jam_mulai'] ?? null, $row['jam_selesai'] ?? null);
            ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border <?php echo $is_pending ? 'border-amber-200 dark:border-amber-800/50' : 'border-slate-200 dark:border-slate-700'; ?> shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border uppercase tracking-wide <?php echo badgeStatusLembur($row['status']); ?>">
                                    <?php echo safe_output($row['status']); ?>
                                </span>
                                <?php if ($is_penugasan): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold border bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50">
                                        <i class="ph-bold ph-user-plus"></i> Ditugaskan
                                    </span>
                                <?php endif; ?>
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
                            <?php if ($jendela_jam !== ''): ?>
                                <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 mt-0.5"><i class="ph-bold ph-clock"></i> <?php echo $jendela_jam; ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <?php if ($is_penugasan): ?>
                                    Ditugaskan<?php echo !empty($row['nama_penugas']) ? ' oleh ' . safe_output($row['nama_penugas']) : ''; ?>
                                <?php else: ?>
                                    Diajukan
                                <?php endif; ?>
                                <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                            </p>
                        </div>
                    </div>

                    <div class="px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50 mb-4">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">Keperluan / Proyek</p>
                        <p class="text-sm text-slate-700 dark:text-slate-200"><?php echo safe_output($row['keperluan']); ?></p>
                    </div>

                    <?php if ($is_penugasan && !empty($row['direspon_at'])): ?>
                        <div class="px-4 py-3 rounded-xl <?php echo $row['status'] === 'Ditolak Karyawan' ? 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800/50' : 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50'; ?> border mb-4">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wide mb-1">
                                Jawaban <?php echo safe_output($row['nama_karyawan']); ?> &middot; <?php echo date('d/m/Y H:i', strtotime($row['direspon_at'])); ?>
                            </p>
                            <p class="text-sm text-slate-700 dark:text-slate-200">
                                <?php echo $row['status'] === 'Ditolak Karyawan' ? 'Menolak' : 'Menerima'; ?><?php echo !empty($row['alasan_karyawan']) ? ': ' . safe_output($row['alasan_karyawan']) : ''; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($bisa_tarik): ?>
                        <form action="proses_pengajuan_lembur.php" method="POST" class="form-tarik flex justify-end border-t border-slate-100 dark:border-slate-700 pt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="tarik_penugasan" value="1">
                            <input type="hidden" name="id_pengajuan" value="<?php echo (int)$row['id']; ?>">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-700 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 text-sm font-semibold hover:bg-rose-50 dark:hover:bg-rose-900/30 transition">
                                <i class="ph-bold ph-arrow-u-up-left"></i> Tarik Penugasan
                            </button>
                        </form>
                    <?php endif; ?>

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
                        <form action="proses_pengajuan_lembur.php" method="POST" class="form-review border-t border-slate-100 dark:border-slate-700 pt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="review_lembur" value="1">
                            <input type="hidden" name="id_pengajuan" value="<?php echo (int)$row['id']; ?>">
                            <input type="hidden" name="aksi" class="input-aksi" value="">

                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">
                                Catatan <span class="font-semibold normal-case tracking-normal text-slate-400">(wajib bila menolak)</span>
                            </label>
                            <div class="flex flex-wrap gap-3">
                                <input type="text" name="catatan_reviewer" maxlength="255"
                                       placeholder="Contoh: Disetujui, mohon simpan bukti hasil kerja."
                                       class="flex-1 min-w-[240px] px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none transition">

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
(function () {
    const panel = document.getElementById('panel-tugas');
    if (!panel) return;

    document.getElementById('btn-buka-tugas').addEventListener('click', function () {
        panel.classList.remove('hidden');
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    document.getElementById('btn-tutup-tugas').addEventListener('click', function () {
        panel.classList.add('hidden');
    });

    const mulaiEl = document.getElementById('tugas-mulai');
    const selesaiEl = document.getElementById('tugas-selesai');
    mulaiEl.addEventListener('change', function () {
        selesaiEl.min = mulaiEl.value;
        if (!selesaiEl.value || selesaiEl.value < mulaiEl.value) selesaiEl.value = mulaiEl.value;
    });

    const jmlEl = document.getElementById('jml-dipilih');
    const cekList = panel.querySelectorAll('.cek-kandidat');
    cekList.forEach(function (cb) {
        cb.addEventListener('change', function () {
            jmlEl.textContent = '(' + panel.querySelectorAll('.cek-kandidat:checked').length + ' dipilih)';
        });
    });

    const cariEl = document.getElementById('cari-kandidat');
    cariEl.addEventListener('input', function () {
        const q = cariEl.value.trim().toLowerCase();
        panel.querySelectorAll('.baris-kandidat').forEach(function (baris) {
            baris.style.display = baris.dataset.cari.indexOf(q) === -1 ? 'none' : '';
        });
    });

    document.getElementById('form-tugas').addEventListener('submit', function (e) {
        let pesan = '';
        if (panel.querySelectorAll('.cek-kandidat:checked').length === 0) {
            pesan = 'Pilih minimal satu karyawan.';
        } else if (document.getElementById('tugas-jam-selesai').value <= document.getElementById('tugas-jam-mulai').value) {
            pesan = 'Jam selesai harus setelah jam mulai.';
        }
        if (pesan) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Periksa kembali', text: pesan, confirmButtonColor: '#d97706' });
            } else {
                alert(pesan);
            }
        }
    });
})();

document.querySelectorAll('.form-tarik').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (typeof Swal === 'undefined') return;
        e.preventDefault();
        Swal.fire({
            title: 'Tarik penugasan lembur ini?',
            text: 'Penugasan akan dibatalkan dan tidak dihitung ke slip gaji.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, tarik',
            cancelButtonText: 'Kembali'
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });
});

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
                title: aksi === 'setujui' ? 'Setujui pengajuan lembur ini?' : 'Tolak pengajuan lembur ini?',
                text: aksi === 'setujui'
                    ? 'Jam lembur akan dihitung otomatis dari absen aktual karyawan pada rentang tanggal ini.'
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
