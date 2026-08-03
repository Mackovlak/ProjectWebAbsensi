<?php
include 'admin_header.php';

// Fetch existing user data
$user_id = $_SESSION['user_id'];
$sql_user = "SELECT nama, jenis_kelamin, foto_profil, ttd_path, wa_token FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$ttd = $user_data['ttd_path'] ? 'assets/uploads/' . $user_data['ttd_path'] : null;
$foto_profil = $user_data['foto_profil'] ? 'assets/uploads/' . $user_data['foto_profil'] : 'https://ui-avatars.com/api/?name=' . urlencode($user_data['nama'] ?? 'Admin') . '&background=f43f5e&color=fff';
$nama = $user_data['nama'] ?? '';
$jenis_kelamin = $user_data['jenis_kelamin'] ?? '';
$wa_token = $user_data['wa_token'] ?? '';
?>

<div class="max-w-4xl mx-auto mt-4 px-4 sm:px-0">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Pengaturan Akun</h2>
        <p class="text-slate-500 dark:text-slate-400">Atur Tanda Tangan Digital untuk pengesahan slip gaji.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Profil Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b w-full pb-2">Profil Pengguna</h3>
            
            <div class="flex flex-col items-center mb-6">
                <div class="relative">
                    <div class="relative w-28 h-28 mb-3 group cursor-pointer rounded-full flex items-center justify-center overflow-hidden border-4 border-slate-100 dark:border-slate-700 shadow-sm" onclick="toggleDropdownFoto(event, 'dropdownFotoAdmin')">
                        <img id="preview_foto_profil" src="<?php echo htmlspecialchars($foto_profil); ?>" alt="Foto Profil" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fa-solid fa-camera text-white text-2xl"></i>
                        </div>
                    </div>
                    
                    <div id="dropdownFotoAdmin" class="hidden absolute top-16 -right-16 w-32 bg-[#333333] rounded-xl shadow-xl z-50 overflow-hidden border border-gray-600 text-[13px]">
                        <button type="button" onclick="lihatFotoProfil('<?php echo htmlspecialchars($foto_profil); ?>'); hideDropdownFoto('dropdownFotoAdmin');" class="w-full px-3 py-2.5 text-gray-200 hover:bg-[#444] text-center transition-colors">Lihat Foto</button>
                        <div class="border-t border-gray-600 mx-3"></div>
                        <button type="button" onclick="document.getElementById('input_foto_profil').click(); hideDropdownFoto('dropdownFotoAdmin');" class="w-full px-3 py-2.5 text-gray-200 hover:bg-[#444] text-center transition-colors">Ganti Foto</button>
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 text-center mt-1">Klik foto untuk mengubah.<br>Format PNG/JPG. Maks 5MB.</p>
                <?php if ($user_data['foto_profil']): ?>
                <button type="button" onclick="hapusFile('foto_profil')" class="text-xs text-rose-500 hover:text-rose-700 mt-2 font-semibold">Hapus Foto</button>
                <?php endif; ?>
                
                <form id="form-foto_profil" enctype="multipart/form-data">
                    <input type="file" id="input_foto_profil" name="foto_profil" accept="image/png, image/jpeg" class="hidden" onchange="uploadFile('foto_profil')">
                </form>
            </div>
            
            <div class="space-y-4 flex-1">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Lengkap</label>
                    <input type="text" value="<?php echo htmlspecialchars($nama); ?>" class="w-full px-4 py-2 bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-500 dark:text-slate-400 focus:outline-none cursor-not-allowed" readonly>
                    <p class="text-[11px] text-slate-400 mt-1">*Nama lengkap tidak dapat diubah (paten dari awal pendaftaran).</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Jenis Kelamin</label>
                    <select id="jenis_kelamin" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="L" <?php echo ($jenis_kelamin == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="P" <?php echo ($jenis_kelamin == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Token API WhatsApp (Fonnte)</label>
                    <input type="text" id="wa_token" value="<?php echo htmlspecialchars($wa_token); ?>" placeholder="Masukkan Token Fonnte..." class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all">
                    <p class="text-[11px] text-slate-400 mt-1">Kosongkan jika tidak ingin menggunakan notifikasi otomatis. <a href="https://fonnte.com" target="_blank" class="text-brand-500 hover:underline">Daftar Fonnte</a></p>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
                <button onclick="simpanProfil()" class="w-full py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-semibold transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </div>

        <!-- Tanda Tangan Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col items-center">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b w-full pb-2 text-center">Tanda Tangan Digital</h3>
            
            <div class="relative w-full h-40 mb-4 group cursor-pointer border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl flex items-center justify-center overflow-hidden" onclick="document.getElementById('input_ttd').click();">
                <?php if ($ttd): ?>
                    <img id="preview_ttd" src="<?php echo htmlspecialchars($ttd); ?>" alt="Tanda Tangan" class="h-full object-contain p-2 relative z-10">
                <?php else: ?>
                    <div id="placeholder_ttd" class="text-slate-400 flex flex-col items-center">
                        <i class="fa-solid fa-signature text-4xl mb-2"></i>
                        <span class="text-sm">Upload TTD (PNG)</span>
                    </div>
                <?php endif; ?>
                
                <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                    <i class="fa-solid fa-upload text-white text-3xl"></i>
                </div>
            </div>
            
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 text-center">Format PNG dengan background transparan. Maks 5MB.</p>
            
            <form id="form-ttd" enctype="multipart/form-data">
                <input type="file" id="input_ttd" name="ttd" accept="image/png" class="hidden" onchange="uploadFile('ttd')">
            </form>

            <?php if ($ttd): ?>
            <button onclick="hapusFile('ttd')" class="px-4 py-2 bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white rounded-lg transition-colors text-sm font-semibold w-full">
                <i class="fa-solid fa-trash mr-1"></i> Hapus Tanda Tangan
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function uploadFile(type) {
        const fileInput = document.getElementById('input_' + type);
        const file = fileInput.files[0];
        if (!file) return;

        let allowedType = type === 'ttd' ? 'image/png' : '';
        if(type === 'foto_profil'){
            if(file.type !== 'image/png' && file.type !== 'image/jpeg' && file.type !== 'image/jpg'){
                Swal.fire({icon: 'error', title: 'Format Salah', text: 'Hanya file PNG/JPG yang diperbolehkan.', customClass: { popup: 'rounded-3xl' }});
                fileInput.value = '';
                return;
            }
        }else if (file.type !== allowedType) {
            Swal.fire({icon: 'error', title: 'Format Salah', text: 'Hanya file PNG yang diperbolehkan.', customClass: { popup: 'rounded-3xl' }});
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({icon: 'error', title: 'Ukuran Terlalu Besar', text: 'Maksimal ukuran file adalah 5MB.', customClass: { popup: 'rounded-3xl' }});
            fileInput.value = '';
            return;
        }

        const formData = new FormData();
        formData.append(type, file);
        formData.append('action', 'upload_' + type);

        Swal.fire({ title: 'Mengunggah...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        fetch('proses_upload_ttd.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let titleName = type === 'ttd' ? 'Tanda Tangan' : 'Foto Profil';
                Swal.fire({ icon: 'success', title: 'Berhasil', text: titleName + ' berhasil diperbarui.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, customClass: { popup: 'rounded-3xl' }});
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', customClass: { popup: 'rounded-3xl' }});
        });
    }

    function hapusFile(type) {
        let titleName = type === 'ttd' ? 'Tanda Tangan' : 'Foto Profil';
        Swal.fire({
            title: 'Hapus ' + titleName + '?',
            text: titleName + ' Anda akan dihapus dari sistem.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-3xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menghapus...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

                const formData = new FormData();
                formData.append('action', 'hapus_' + type);

                fetch('proses_upload_ttd.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Terhapus!', text: titleName + ' telah dihapus.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, customClass: { popup: 'rounded-3xl' }});
                    }
                })
                .catch(error => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', customClass: { popup: 'rounded-3xl' }});
                });
            }
        });
    }

    function simpanProfil() {
        let jk = document.getElementById('jenis_kelamin').value;
        let wa_token = document.getElementById('wa_token').value;
        if (!jk) {
            Swal.fire({icon: 'warning', title: 'Perhatian', text: 'Silakan pilih Jenis Kelamin.', customClass: { popup: 'rounded-3xl' }});
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_profil');
        formData.append('jenis_kelamin', jk);
        formData.append('wa_token', wa_token);

        Swal.fire({ title: 'Menyimpan...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        fetch('proses_upload_ttd.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Profil berhasil diperbarui.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, customClass: { popup: 'rounded-3xl' }});
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', customClass: { popup: 'rounded-3xl' }});
        });
    }
    function toggleDropdownFoto(e, id) {
        e.stopPropagation();
        const dropdown = document.getElementById(id);
        dropdown.classList.toggle('hidden');
    }

    function hideDropdownFoto(id) {
        const dropdown = document.getElementById(id);
        if (dropdown) dropdown.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const dropdowns = document.querySelectorAll('[id^="dropdownFoto"]');
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target) && !e.target.closest('.group')) {
                dropdown.classList.add('hidden');
            }
        });
    });

    function lihatFotoProfil(imageUrl) {
        Swal.fire({
            imageUrl: imageUrl,
            imageAlt: 'Foto Profil',
            customClass: { popup: 'rounded-3xl' },
            showConfirmButton: false,
            showCloseButton: true
        });
    }
</script>

<?php include 'admin_footer.php'; ?>
