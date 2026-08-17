<?php
include 'staff_header.php';

// Fetch existing bio data
$id_karyawan = $_SESSION['id_karyawan'];
$sql_bio = "SELECT k.foto, k.tempat_lahir, k.tanggal_lahir, k.agama, k.no_whatsapp, k.alamat_lengkap, j.nama_jabatan, c.nama_cabang 
            FROM karyawan k 
            LEFT JOIN jabatan j ON k.id_jabatan = j.id
            LEFT JOIN cabang c ON k.id_cabang = c.id
            WHERE k.id_karyawan = ?";
$stmt_bio = $conn->prepare($sql_bio);
$stmt_bio->bind_param("s", $id_karyawan);
$stmt_bio->execute();
$bio_data = $stmt_bio->get_result()->fetch_assoc();
$stmt_bio->close();

$nama_jabatan = $bio_data['nama_jabatan'] ?? 'Staff';
$nama_cabang = $bio_data['nama_cabang'] ?? 'Pusat';
$role_label = strtoupper($nama_jabatan . " - " . $nama_cabang);

$foto = $bio_data['foto'] ? 'assets/images/foto_karyawan/' . $bio_data['foto'] : $avatar_src;

// Fetch TTD
$user_id = $_SESSION['user_id'];
$sql_ttd = "SELECT ttd_path FROM users WHERE id = ?";
$stmt_ttd = $conn->prepare($sql_ttd);
$stmt_ttd->bind_param("i", $user_id);
$stmt_ttd->execute();
$ttd_data = $stmt_ttd->get_result()->fetch_assoc();
$stmt_ttd->close();
$ttd = $ttd_data['ttd_path'] ? 'assets/uploads/' . $ttd_data['ttd_path'] : null;
?>

<div class="max-w-5xl mx-auto mt-4 px-4 sm:px-0">
    <!-- Header Page -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Pengaturan Akun & Biodata</h2>
        <p class="text-slate-500 dark:text-slate-400">Atur kredensial login dan lengkapi profil biodata diri Anda.</p>
    </div>

    <!-- Top Row: Photo, TTD, Banner -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 items-stretch">
        
        <!-- Photo Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col items-center justify-center h-full">
            <div class="relative">
                <div class="relative w-28 h-28 mb-4 group cursor-pointer" onclick="toggleDropdownFoto(event, 'dropdownFotoStaff')">
                    <img id="preview_foto" src="<?php echo htmlspecialchars($foto); ?>" alt="Foto Karyawan" class="w-full h-full object-cover rounded-full border-4 border-slate-100 dark:border-slate-700 shadow-md">
                    <div class="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fa-solid fa-camera text-white text-2xl"></i>
                    </div>
                </div>
                
                <div id="dropdownFotoStaff" class="hidden absolute top-16 -right-16 w-32 bg-[#333333] rounded-xl shadow-xl z-50 overflow-hidden border border-gray-600 text-[13px]">
                    <button type="button" onclick="lihatFotoProfil('<?php echo htmlspecialchars($foto); ?>'); hideDropdownFoto('dropdownFotoStaff');" class="w-full px-3 py-2.5 text-gray-200 hover:bg-[#444] text-center transition-colors">Lihat Foto</button>
                    <div class="border-t border-gray-600 mx-3"></div>
                    <button type="button" onclick="document.getElementById('input_foto').click(); hideDropdownFoto('dropdownFotoStaff');" class="w-full px-3 py-2.5 text-gray-200 hover:bg-[#444] text-center transition-colors">Ganti Foto</button>
                    <div class="border-t border-gray-600 mx-3"></div>
                    <button type="button" onclick="hapusFoto(); hideDropdownFoto('dropdownFotoStaff');" class="w-full px-3 py-2.5 text-rose-400 hover:bg-[#444] text-center transition-colors">Hapus Foto</button>
                </div>
            </div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-1 text-center"><?php echo htmlspecialchars($nama_karyawan_display); ?></h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Maks 5MB (JPG, PNG)</p>
            <form id="form-foto" enctype="multipart/form-data">
                <input type="file" id="input_foto" name="foto" accept="image/jpeg, image/png, image/jpg" class="hidden" onchange="uploadFoto()">
            </form>
        </div>

        <!-- Tanda Tangan Card -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 flex flex-col h-full">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-3 text-center border-b border-slate-100 dark:border-slate-700 pb-2">Tanda Tangan Digital</h3>
            <div class="relative w-full flex-1 min-h-[100px] mb-3 group cursor-pointer border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl flex items-center justify-center overflow-hidden bg-slate-50 dark:bg-slate-900/50 shrink-0" onclick="document.getElementById('input_ttd').click();">
                <?php if ($ttd): ?>
                    <img id="preview_ttd" src="<?php echo htmlspecialchars($ttd); ?>" alt="Tanda Tangan" class="h-full max-h-[100px] object-contain p-2 relative z-10">
                <?php else: ?>
                    <div id="placeholder_ttd" class="text-slate-400 flex flex-col items-center">
                        <i class="fa-solid fa-signature text-2xl mb-1"></i>
                        <span class="text-[10px]">Upload TTD (PNG)</span>
                    </div>
                <?php endif; ?>
                
                <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                    <i class="fa-solid fa-upload text-white text-xl"></i>
                </div>
            </div>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-3 text-center">Format PNG dengan background transparan. Maks 5MB.</p>
            <form id="form-ttd" enctype="multipart/form-data">
                <input type="file" id="input_ttd" name="ttd" accept="image/png" class="hidden" onchange="uploadTTD()">
            </form>
            <div class="mt-auto">
                <?php if ($ttd): ?>
                <button onclick="hapusTTD()" class="w-full py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white rounded-lg transition-colors text-xs font-semibold flex justify-center items-center gap-1 border border-rose-100 dark:border-rose-900/50">
                    <i class="fa-solid fa-trash"></i> Hapus TTD
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-br from-fuchsia-600 to-brand-500 rounded-2xl shadow-sm p-6 flex flex-col justify-center text-white relative overflow-hidden h-full">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/20 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-black/10 rounded-full blur-xl pointer-events-none"></div>
            <i class="fa-solid fa-id-card text-3xl mb-3 text-white/80 relative z-10"></i>
            <h3 class="text-lg font-bold mb-2 relative z-10">ID Card & QR Code</h3>
            <p class="text-sm text-white/90 relative z-10 leading-relaxed font-medium mb-4">
                Lihat dan unduh ID Card kamu yang berisi QR Code untuk keperluan absensi harian.
            </p>
            <button onclick="openQrModal()" class="w-full py-2 bg-white/20 hover:bg-white/30 text-white rounded-xl transition-colors text-sm font-semibold flex items-center justify-center gap-2 relative z-10 backdrop-blur-sm border border-white/30">
                <i class="fa-solid fa-qrcode"></i> Lihat ID Card
            </button>
        </div>
        
    </div>

    <!-- Bottom Row: Keamanan & Biodata -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Keamanan Akun Card -->
        <div class="lg:col-span-1 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-700 pb-2">Keamanan Akun</h3>
                <form id="form-ganti-password" onsubmit="event.preventDefault(); submitGantiPassword();">
                    <div class="space-y-4">
                        <!-- Username -->
                        <div>
                            <label for="username" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username Baru</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-user text-slate-400 text-xs"></i>
                                </div>
                                <input type="text" id="username" value="<?php echo htmlspecialchars($_SESSION['username']); ?>"
                                    class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-500 text-sm"
                                    placeholder="Username (opsional)">
                            </div>
                        </div>

                        <!-- Password Baru -->
                        <div>
                            <label for="password_baru" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Password Baru (opsional)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-lock text-slate-400 text-xs"></i>
                                </div>
                                <input type="password" id="password_baru" minlength="6"
                                    class="w-full pl-9 pr-9 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-500 text-sm"
                                    placeholder="Kosongkan jika tidak diubah">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePassword('password_baru', 'icon-password-baru')">
                                    <i id="icon-password-baru" class="fa-solid fa-eye text-slate-400 hover:text-slate-600 text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Konfirmasi Password -->
                        <div>
                            <label for="konfirmasi_password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Konfirmasi Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fa-solid fa-check-double text-slate-400 text-xs"></i>
                                </div>
                                <input type="password" id="konfirmasi_password" minlength="6"
                                    class="w-full pl-9 pr-9 py-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-500 text-sm"
                                    placeholder="Ulangi password">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePassword('konfirmasi_password', 'icon-konfirmasi')">
                                    <i id="icon-konfirmasi" class="fa-solid fa-eye text-slate-400 hover:text-slate-600 text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" id="btn-submit-pwd" class="w-full py-2 bg-slate-800 dark:bg-slate-700 hover:bg-slate-900 dark:hover:bg-slate-600 text-white font-semibold rounded-lg transition-all text-sm flex justify-center items-center gap-2">
                            <i class="fa-solid fa-key"></i> Update Keamanan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Biodata Card -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-6 md:p-8">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-6 border-b border-slate-100 dark:border-slate-700 pb-2">Informasi Biodata</h3>
                <form id="form-biodata" onsubmit="event.preventDefault(); submitBiodata();">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tempat Lahir -->
                        <div>
                            <label for="tempat_lahir" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tempat Lahir</label>
                            <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?php echo htmlspecialchars($bio_data['tempat_lahir'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all"
                                placeholder="Contoh: Jakarta">
                        </div>

                        <!-- Tanggal Lahir -->
                        <div>
                            <label for="tanggal_lahir" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Tanggal Lahir</label>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo htmlspecialchars($bio_data['tanggal_lahir'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all">
                        </div>

                        <!-- Agama -->
                        <div>
                            <label for="agama" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Agama</label>
                            <select id="agama" name="agama" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all appearance-none cursor-pointer">
                                <option value="">~ Pilih Agama ~</option>
                                <?php
                                $agamas = ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
                                foreach($agamas as $ag) {
                                    $sel = ($bio_data['agama'] == $ag) ? 'selected' : '';
                                    echo "<option value=\"$ag\" $sel>$ag</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- No WhatsApp -->
                        <div>
                            <label for="no_whatsapp" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nomor WhatsApp</label>
                            <input type="text" id="no_whatsapp" name="no_whatsapp" value="<?php echo htmlspecialchars($bio_data['no_whatsapp'] ?? ''); ?>"
                                class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all"
                                placeholder="Contoh: 081234567890">
                        </div>

                        <!-- Alamat Lengkap -->
                        <div class="md:col-span-2">
                            <label for="alamat_lengkap" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Alamat Lengkap</label>
                            <textarea id="alamat_lengkap" name="alamat_lengkap" rows="3"
                                class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all"
                                placeholder="Alamat tempat tinggal saat ini..."><?php echo htmlspecialchars($bio_data['alamat_lengkap'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-end gap-4">
                        <button type="submit" id="btn-submit-bio" class="px-6 py-2.5 bg-fuchsia-600 hover:bg-fuchsia-700 text-white font-semibold rounded-xl shadow-md shadow-fuchsia-500/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-save"></i> Simpan Biodata
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

<!-- MODAL POPUP: TAMPILAN ID CARD / QR CODE -->
<div id="employeeQrModal" class="fixed inset-0 z-[60] hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden transform transition-all">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">Preview ID Card</h3>
            <button onclick="closeQrModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body: ID Card Layout -->
        <div class="p-6 bg-slate-100 dark:bg-slate-900 flex justify-center">
            <!-- ID Card Print Area -->
            <div id="idCardPrintArea" class="bg-white w-64 rounded-xl shadow-md overflow-hidden border border-slate-200 relative">
                <!-- Header Card -->
                <div class="bg-purple-700 text-white text-center py-3 border-b-4 border-purple-900">
                    <i class="fa-solid fa-user-shield text-2xl mb-1 text-white"></i>
                    <h4 class="text-sm font-bold tracking-widest text-white">ABSENSI JAVAG</h4>
                </div>
                
                <!-- Content Card -->
                <div class="p-4 flex flex-col items-center">
                    <h2 id="modalEmpName" class="text-lg font-bold text-slate-800 text-center uppercase mb-1 leading-tight"><?php echo htmlspecialchars($nama_karyawan_display); ?></h2>
                    <p id="modalEmpRole" class="text-xs text-purple-700 font-semibold mb-4 text-center"><?php echo htmlspecialchars($role_label); ?></p>
                    
                    <!-- QR Code -->
                    <div class="w-40 h-40 bg-white border-2 border-slate-200 rounded-lg flex items-center justify-center p-2 mb-3">
                        <img src="" crossorigin="anonymous" alt="QR Code" class="w-full h-full object-contain opacity-90" id="modalQrImage">
                    </div>
                    
                    <p class="text-[10px] text-slate-400 uppercase tracking-widest">ID Karyawan</p>
                    <p id="modalEmpId" class="text-sm font-mono font-bold text-slate-700"><?php echo htmlspecialchars($id_karyawan); ?></p>
                </div>
                
                <div class="bg-slate-50 text-[10px] text-center py-2 text-slate-500 border-t border-slate-100">
                    Scan QR ini saat tiba di lokasi kerja.
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-white dark:bg-slate-800 border-t border-slate-100 dark:border-slate-700 flex justify-between gap-3">
            <button onclick="closeQrModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl font-medium transition-colors text-sm">Tutup</button>
            
            <button onclick="downloadSinglePng(event)" class="flex-1 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-medium transition-colors text-sm shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Unduh PNG
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
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

    function hapusFoto() {
        Swal.fire({
            title: 'Hapus Foto Profil?',
            text: 'Foto akan dihapus dan diganti dengan avatar default.',
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
                formData.append('action', 'hapus_foto');

                fetch('proses_biodata.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('preview_foto').src = data.default_avatar;
                        const headerAvatar = document.getElementById('header_avatar_img');
                        if (headerAvatar) {
                            headerAvatar.src = data.default_avatar;
                        }
                        Swal.fire({ icon: 'success', title: 'Terhapus!', text: 'Foto profil telah dihapus.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }});
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

    function uploadFoto() {
        const fileInput = document.getElementById('input_foto');
        const file = fileInput.files[0];
        if (!file) return;

        // Validasi ukuran (Max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({icon: 'error', title: 'Ukuran Terlalu Besar', text: 'Maksimal ukuran foto adalah 5MB.', customClass: { popup: 'rounded-3xl' }});
            fileInput.value = '';
            return;
        }

        // Preview langsung
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview_foto').src = e.target.result;
        }
        reader.readAsDataURL(file);

        // Upload via AJAX
        const formData = new FormData();
        formData.append('foto', file);
        formData.append('action', 'upload_foto');

        Swal.fire({ title: 'Mengunggah...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        fetch('proses_biodata.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update avatar di header navbar juga
                const headerAvatar = document.getElementById('header_avatar_img');
                if (headerAvatar) {
                    headerAvatar.src = 'assets/images/foto_karyawan/' + data.foto;
                }
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Foto berhasil diperbarui.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }});
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, customClass: { popup: 'rounded-3xl' }});
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal menghubungi server.', customClass: { popup: 'rounded-3xl' }});
        });
    }

    function submitBiodata() {
        const form = document.getElementById('form-biodata');
        const formData = new FormData(form);
        formData.append('action', 'update_biodata');

        const btnSubmit = document.getElementById('btn-submit-bio');
        const originalText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
        btnSubmit.disabled = true;

        fetch('proses_biodata.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, confirmButtonColor: '#10b981', customClass: { popup: 'rounded-3xl' }});
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, confirmButtonColor: '#d946ef', customClass: { popup: 'rounded-3xl' }});
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem.', confirmButtonColor: '#d946ef', customClass: { popup: 'rounded-3xl' }});
        })
        .finally(() => {
            btnSubmit.innerHTML = originalText;
            btnSubmit.disabled = false;
        });
    }

    // Fungsi Password sama seperti sebelumnya
    function submitGantiPassword() {
        const username = document.getElementById('username').value;
        const passwordBaru = document.getElementById('password_baru').value;
        const konfirmasiPassword = document.getElementById('konfirmasi_password').value;

        if (passwordBaru !== '' || konfirmasiPassword !== '') {
            if (passwordBaru !== konfirmasiPassword) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Password baru dan konfirmasi tidak sama!', confirmButtonColor: '#d946ef', customClass: { popup: 'rounded-3xl' }});
                return;
            }
            if (passwordBaru.length < 6) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Password minimal 6 karakter!', confirmButtonColor: '#d946ef', customClass: { popup: 'rounded-3xl' }});
                return;
            }
        }

        const formData = new FormData();
        formData.append('username', username);
        formData.append('password_baru', passwordBaru);
        formData.append('konfirmasi_password', konfirmasiPassword);

        const btnSubmit = document.getElementById('btn-submit-pwd');
        const originalText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
        btnSubmit.disabled = true;

        fetch('proses_ganti_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, confirmButtonColor: '#10b981', customClass: { popup: 'rounded-3xl' }}).then(() => {
                    document.getElementById('password_baru').value = '';
                    document.getElementById('konfirmasi_password').value = '';
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, confirmButtonColor: '#d946ef', customClass: { popup: 'rounded-3xl' }});
            }
        })
        .catch(error => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan sistem.', confirmButtonColor: '#d946ef', customClass: { popup: 'rounded-3xl' }});
        })
        .finally(() => {
            btnSubmit.innerHTML = originalText;
            btnSubmit.disabled = false;
        });
    }

    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function uploadTTD() {
        const fileInput = document.getElementById('input_ttd');
        const file = fileInput.files[0];
        if (!file) return;

        if (file.type !== 'image/png') {
            Swal.fire({icon: 'error', title: 'Format Salah', text: 'Hanya file PNG yang diperbolehkan.', customClass: { popup: 'rounded-3xl' }});
            fileInput.value = '';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({icon: 'error', title: 'Ukuran Terlalu Besar', text: 'Maksimal ukuran file adalah 2MB.', customClass: { popup: 'rounded-3xl' }});
            fileInput.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('ttd', file);
        formData.append('action', 'upload_ttd');

        Swal.fire({ title: 'Mengunggah...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        fetch('proses_upload_ttd.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Tanda tangan berhasil diperbarui.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
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

    function hapusTTD() {
        Swal.fire({
            title: 'Hapus Tanda Tangan?',
            text: 'Tanda tangan digital Anda akan dihapus.',
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
                formData.append('action', 'hapus_ttd');

                fetch('proses_upload_ttd.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Terhapus!', text: 'Tanda tangan telah dihapus.', timer: 1500, showConfirmButton: false, customClass: { popup: 'rounded-3xl' }}).then(() => {
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

    // Modal QR Code Functions
    function openQrModal() {
        const id_karyawan = '<?php echo addslashes($id_karyawan); ?>';
        const qrContent = "<?php echo "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); ?>/absen.php?id=" + id_karyawan;
        
        document.getElementById('modalQrImage').src = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" + encodeURIComponent(qrContent);
        document.getElementById('employeeQrModal').classList.remove('hidden');
    }

    function closeQrModal() {
        document.getElementById('employeeQrModal').classList.add('hidden');
    }

    function downloadSinglePng(event) {
        const cardElement = document.getElementById('idCardPrintArea');
        const empName = '<?php echo addslashes(preg_replace('/\s+/', '_', $nama_karyawan_display)); ?>';
        
        const btn = event.currentTarget;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
        btn.disabled = true;

        html2canvas(cardElement, { scale: 3, useCORS: true, backgroundColor: "#ffffff" }).then(canvas => {
            const imageURI = canvas.toDataURL("image/png");
            
            const downloadLink = document.createElement("a");
            downloadLink.href = imageURI;
            downloadLink.download = `ID_Card_Dinia_${empName}.png`;
            
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);

            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }).catch(err => {
            console.error(err);
            alert("Gagal mengunduh gambar PNG.");
            btn.innerHTML = originalHTML; 
            btn.disabled = false;
        });
    }
</script>

<?php include 'staff_footer.php'; ?>
