<?php
require_once 'config.php';
require 'supervisor_header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT k.*, j.nama_jabatan, c.nama_cabang
                        FROM karyawan k
                        LEFT JOIN jabatan j ON j.id = k.id_jabatan
                        LEFT JOIN cabang c ON c.id = k.id_cabang
                        WHERE k.id = ? AND k.id_cabang = ?");
$stmt->bind_param('ii', $id, $cabang_supervisor);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    $_SESSION['error_message'] = 'Data karyawan tidak ditemukan atau berada di luar cabang Anda.';
    header('Location: supervisor_data_karyawan.php');
    exit();
}

$jenis_kelamin = $data['jenis_kelamin'] ?? 'L';
$foto = !empty($data['foto']) ? 'assets/images/foto_karyawan/' . $data['foto'] : ($jenis_kelamin === 'P' ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2');
$kuota = getRingkasanKuotaIzin($conn, $data['id_karyawan'], (int)date('Y'));
$formatTanggal = static function ($date) {
    if (!$date) return '-';
    return date('d-m-Y', strtotime($date));
};
?>

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Detail Karyawan</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Biodata dan kuota cuti anggota cabang Anda.</p>
    </div>
    <a href="supervisor_data_karyawan.php" class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-medium"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 text-center">
            <img src="<?php echo htmlspecialchars($foto); ?>" alt="Foto Karyawan" class="w-40 h-40 mx-auto mb-5 object-cover rounded-2xl border-4 border-slate-100 dark:border-slate-700 shadow-md">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($data['nama_karyawan']); ?></h3>
            <p class="text-sm text-purple-600 dark:text-purple-400 mt-1"><?php echo htmlspecialchars($data['id_karyawan']); ?></p>
            <span class="inline-flex mt-4 px-3 py-1 rounded-full text-xs font-bold <?php echo $data['status'] === 'aktif' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?php echo htmlspecialchars(ucfirst($data['status'])); ?></span>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
            <div class="flex justify-between items-center"><h3 class="font-bold text-slate-800 dark:text-white"><i class="fa-solid fa-umbrella-beach text-sky-500 mr-2"></i>Kuota Cuti</h3><span class="text-xs text-slate-500"><?php echo (int)$kuota['tahun']; ?></span></div>
            <p class="text-3xl font-bold text-sky-600 dark:text-sky-400 mt-4"><?php echo (int)$kuota['tersedia']; ?><span class="text-sm font-medium text-slate-400"> / <?php echo (int)$kuota['jatah']; ?> hari tersedia</span></p>
            <p class="text-xs text-slate-500 mt-3">Terpakai <?php echo (int)$kuota['terpakai']; ?> hari &middot; Menunggu <?php echo (int)$kuota['tertahan']; ?> hari</p>
        </div>
    </div>
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50"><h3 class="font-bold text-slate-800 dark:text-white"><i class="fa-regular fa-address-card text-purple-500 mr-2"></i>Informasi Data Diri</h3></div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            $items = [
                'Nama Lengkap' => $data['nama_karyawan'],
                'Jabatan' => $data['nama_jabatan'] ?: '-',
                'Cabang' => $data['nama_cabang'] ?: '-',
                'Jenis Kelamin' => $jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                'Tempat Lahir' => $data['tempat_lahir'] ?: '-',
                'Tanggal Lahir' => $formatTanggal($data['tanggal_lahir'] ?? null),
                'Agama' => $data['agama'] ?: '-',
                'Nomor WhatsApp' => $data['no_whatsapp'] ?: '-'
            ];
            foreach ($items as $label => $value): ?>
                <div><span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo htmlspecialchars($label); ?></span><span class="text-slate-800 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($value); ?></span></div>
            <?php endforeach; ?>
            <div class="md:col-span-2"><span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Alamat Lengkap</span><div class="text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl whitespace-pre-wrap"><?php echo htmlspecialchars($data['alamat_lengkap'] ?: '-'); ?></div></div>
        </div>
    </div>
</div>

<?php require 'supervisor_footer.php'; ?>
