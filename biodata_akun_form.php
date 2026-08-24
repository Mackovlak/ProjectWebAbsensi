<?php
// Komponen biodata untuk akun Admin/Supervisor yang tertaut ke karyawan.
$biodata_akun = null;
$stmt_biodata_akun = $conn->prepare("SELECT k.id_karyawan, k.tempat_lahir, k.tanggal_lahir, k.agama,
                                            k.no_whatsapp, k.alamat_lengkap, c.nama_cabang, j.nama_jabatan
                                     FROM users u
                                     LEFT JOIN karyawan k ON k.id_karyawan = u.id_karyawan
                                     LEFT JOIN cabang c ON c.id = k.id_cabang
                                     LEFT JOIN jabatan j ON j.id = k.id_jabatan
                                     WHERE u.id = ?");
$stmt_biodata_akun->bind_param('i', $_SESSION['user_id']);
$stmt_biodata_akun->execute();
$biodata_akun = $stmt_biodata_akun->get_result()->fetch_assoc();
$stmt_biodata_akun->close();
$akun_tertaut_karyawan = !empty($biodata_akun['id_karyawan']);
?>

<div class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-6 md:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6 border-b border-slate-100 dark:border-slate-700 pb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Informasi Biodata Karyawan</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Biodata ini terhubung langsung dengan master data karyawan.</p>
            </div>
            <?php if ($akun_tertaut_karyawan): ?>
                <span class="inline-flex self-start px-3 py-1 rounded-full bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400 text-xs font-semibold border border-teal-200 dark:border-teal-800/50">
                    <?php echo htmlspecialchars($biodata_akun['id_karyawan']); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!$akun_tertaut_karyawan): ?>
            <div class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20 p-4 text-sm text-amber-700 dark:text-amber-300">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>Akun ini belum tertaut ke data karyawan, sehingga biodata belum dapat diedit.
            </div>
        <?php else: ?>
            <div class="mb-5 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700"><i class="fa-solid fa-building mr-1"></i><?php echo htmlspecialchars($biodata_akun['nama_cabang'] ?? '-'); ?></span>
                <span class="px-3 py-1.5 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700"><i class="fa-solid fa-briefcase mr-1"></i><?php echo htmlspecialchars($biodata_akun['nama_jabatan'] ?? '-'); ?></span>
            </div>
            <form id="form-biodata-akun" onsubmit="event.preventDefault(); submitBiodataAkun();">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="bio_tempat_lahir" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tempat Lahir</label>
                        <input type="text" id="bio_tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($biodata_akun['tempat_lahir'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Contoh: Jakarta">
                    </div>
                    <div>
                        <label for="bio_tanggal_lahir" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Lahir</label>
                        <input type="date" id="bio_tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($biodata_akun['tanggal_lahir'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="bio_agama" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Agama</label>
                        <select id="bio_agama" name="agama" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 appearance-none">
                            <option value="">-- Pilih Agama --</option>
                            <?php foreach (['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $agama): ?>
                                <option value="<?php echo $agama; ?>" <?php echo ($biodata_akun['agama'] ?? '') === $agama ? 'selected' : ''; ?>><?php echo $agama; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="bio_no_whatsapp" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nomor WhatsApp</label>
                        <input type="text" id="bio_no_whatsapp" name="no_whatsapp" value="<?php echo htmlspecialchars($biodata_akun['no_whatsapp'] ?? ''); ?>" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Contoh: 081234567890">
                    </div>
                    <div class="md:col-span-2">
                        <label for="bio_alamat_lengkap" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Alamat Lengkap</label>
                        <textarea id="bio_alamat_lengkap" name="alamat_lengkap" rows="3" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Alamat tempat tinggal saat ini..."><?php echo htmlspecialchars($biodata_akun['alamat_lengkap'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="submit" id="btn-submit-biodata-akun" class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl shadow-md shadow-brand-500/20 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-save"></i> Simpan Biodata
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($akun_tertaut_karyawan): ?>
<script>
function submitBiodataAkun() {
    const form = document.getElementById('form-biodata-akun');
    const button = document.getElementById('btn-submit-biodata-akun');
    const original = button.innerHTML;
    const formData = new FormData(form);
    formData.append('action', 'update_biodata');
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';

    fetch('proses_biodata.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            Swal.fire({
                icon: data.success ? 'success' : 'error',
                title: data.success ? 'Berhasil' : 'Gagal',
                text: data.message,
                confirmButtonColor: data.success ? '#10b981' : '#ef4444',
                customClass: { popup: 'rounded-3xl' }
            });
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal terhubung ke server.' }))
        .finally(() => {
            button.disabled = false;
            button.innerHTML = original;
        });
}
</script>
<?php endif; ?>
