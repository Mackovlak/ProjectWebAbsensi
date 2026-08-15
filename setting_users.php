<?php
require 'config.php';
include 'admin_header.php';

// Ambil semua data pengguna dengan informasi tambahan
$sql_users = "SELECT u.id, u.username, u.role, u.id_karyawan, 
                 k.nama_karyawan, u.face_descriptor, u.face_registered_at, u.face_reset_allowed,
                 c.nama_cabang AS cabang_supervisi
                 FROM users u 
                 LEFT JOIN karyawan k ON u.id_karyawan = k.id_karyawan 
                 LEFT JOIN cabang c ON u.id_cabang = c.id
                 ORDER BY FIELD(u.role, 'admin', 'owner', 'supervisor', 'staff'), u.username ASC";

// Daftar cabang untuk form pembuatan akun supervisor
$res_cabang_supervisor = $conn->query("SELECT id, nama_cabang FROM cabang ORDER BY nama_cabang ASC");
$result_users = $conn->query($sql_users);

// Ambil data karyawan yang BELUM memiliki akun
$sql_karyawan_tanpa_akun = "SELECT k.id, k.nama_karyawan, k.id_karyawan 
                            FROM karyawan k 
                            LEFT JOIN users u ON k.id_karyawan = u.id_karyawan AND u.role = 'staff'
                            WHERE u.id IS NULL AND k.status = 'aktif'
                            ORDER BY k.nama_karyawan ASC";
$res_karyawan = $conn->query($sql_karyawan_tanpa_akun);

$current_user_id = $_SESSION['user_id'];

// Dapatkan role user yang sedang login
$sql_current_role = "SELECT role FROM users WHERE id = '$current_user_id'";
$result_current_role = $conn->query($sql_current_role);
$current_user_role = $result_current_role->fetch_assoc()['role'];

// Hitung jumlah admin
$sql_admin_count = "SELECT COUNT(*) as total_admin FROM users WHERE role = 'admin'";
$res_admin_count = $conn->query($sql_admin_count);
$admin_count = $res_admin_count->fetch_assoc()['total_admin'];
?>

<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Manajemen User</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola akun pengguna, hak akses, dan status registrasi wajah.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
        <button onclick="openModal('modal-tambah-staff')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-brand-500/30 w-full sm:w-auto whitespace-nowrap">
            <i class="fa-solid fa-user-plus"></i> Buat Akun Staff
        </button>
        
        <?php
        // Cek apakah sudah ada owner
        $sql_owner_count = "SELECT COUNT(*) as total_owner FROM users WHERE role = 'owner'";
        $res_owner_count = $conn->query($sql_owner_count);
        $owner_count = $res_owner_count->fetch_assoc()['total_owner'];
        ?>
        
        <button onclick="openModal('modal-tambah-admin')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-blue-500/30 w-full sm:w-auto whitespace-nowrap">
            <i class="fa-solid fa-user-shield"></i> Buat Akun Admin
        </button>

        <button onclick="openModal('modal-tambah-supervisor')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-teal-500/30 w-full sm:w-auto whitespace-nowrap">
            <i class="fa-solid fa-user-check"></i> Buat Akun Supervisor
        </button>

        <button onclick="openModal('modal-tambah-owner')" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl transition-colors font-medium text-sm shadow-sm shadow-purple-500/30 w-full sm:w-auto whitespace-nowrap">
            <i class="fa-solid fa-user-tie"></i> Buat Akun Owner
        </button>
    </div>
</div>

<?php include 'alert_messages.php'; ?>

<!-- Table Card Container -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden flex flex-col mb-8">
    
    <!-- Table Toolbar (Search & Entries) -->
    <div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between gap-4">
        <!-- Search Area -->
        <div class="relative w-full sm:w-96">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
            </div>
            <input type="text" id="searchInput" onkeyup="filterTable()" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari username, nama atau status...">
        </div>

        <!-- Pilihan Jumlah Tampilan -->
        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 self-start sm:self-center">
            <span>Tampilkan</span>
            <select id="entriesSelect" onchange="changeEntries()" class="border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 px-3 py-2 outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                <option value="5" selected>5</option>
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="30">30</option>
                <option value="all">Semua</option>
            </select>
            <span>data</span>
        </div>
    </div>

    <!-- Table Wrapper -->
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse" id="usersTable">
            <thead class="sticky top-0 z-20">
                <tr class="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 shadow-sm">
                    <th class="px-6 py-4 font-semibold w-16 text-center">No</th>
                    <th class="px-6 py-4 font-semibold">Username</th>
                    <th class="px-6 py-4 font-semibold">Nama Karyawan</th>
                    <th class="px-6 py-4 font-semibold text-center">Status</th>
                    <th class="px-6 py-4 font-semibold text-center">Face Status</th>
                    <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700" id="tableBody">
                <?php if ($result_users->num_rows > 0): ?>
                    <?php $no = 1; while($row = $result_users->fetch_assoc()): ?>
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors group">
                        <td class="px-6 py-4 whitespace-nowrap text-center font-medium text-slate-500 dark:text-slate-400">
                            <?php echo $no++; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800 dark:text-white search-target">
                            <?php echo htmlspecialchars($row['username']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300 search-target">
                            <?php echo $row['nama_karyawan'] ? htmlspecialchars($row['nama_karyawan']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php
                            $roleClass = '';
                            if($row['role'] == 'admin') $roleClass = 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800/50';
                            else if($row['role'] == 'staff') $roleClass = 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-400 border-fuchsia-200 dark:border-fuchsia-800/50';
                            else if($row['role'] == 'owner') $roleClass = 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border-purple-200 dark:border-purple-800/50';
                            else if($row['role'] == 'supervisor') $roleClass = 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400 border-teal-200 dark:border-teal-800/50';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border <?php echo $roleClass; ?> search-target uppercase tracking-wide">
                                <?php echo htmlspecialchars($row['role']); ?>
                            </span>
                            <?php if ($row['role'] == 'supervisor'): ?>
                                <span class="block text-[11px] text-slate-400 mt-1 search-target">
                                    <?php echo $row['cabang_supervisi'] ? htmlspecialchars($row['cabang_supervisi']) : 'Cabang belum diset'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <?php 
                            $has_face = !empty($row['face_descriptor']);
                            $reset_allowed = ($row['face_reset_allowed'] == 1);
                            
                            if ($row['role'] == 'staff'):
                                if ($has_face): 
                            ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Registered
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Not Registered
                                </span>
                            <?php 
                                endif;
                                if ($reset_allowed): 
                            ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border border-purple-200 dark:border-purple-800/50 mt-1 sm:mt-0 sm:ml-1">
                                    <i class="fa-solid fa-unlock text-[10px]"></i> Reset Allowed
                                </span>
                            <?php endif; ?>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 dark:text-slate-500">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <?php 
                            $is_own_account = ($row['id'] == $current_user_id);
                            $is_last_admin = ($row['role'] == 'admin' && $admin_count <= 1);
                            $is_other_admin = ($row['role'] == 'admin' && !$is_own_account && $current_user_role == 'admin');
                            ?>
                            <div class="flex items-center justify-end gap-2">
                                
                                <?php if ($row['role'] == 'staff'): ?>
                                    <?php if ($has_face && !$reset_allowed): ?>
                                        <button onclick="toggleFacePermission('<?php echo $row['id_karyawan']; ?>', 'allow_reset', '<?php echo htmlspecialchars($row['username']); ?>')" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors" title="Izinkan Reset Wajah">
                                            <i class="fa-solid fa-unlock-keyhole"></i>
                                        </button>
                                        <button onclick="toggleFacePermission('<?php echo $row['id_karyawan']; ?>', 'delete_face', '<?php echo htmlspecialchars($row['username']); ?>')" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg dark:text-rose-400 dark:hover:bg-rose-900/30 transition-colors" title="Hapus Wajah">
                                            <i class="fa-solid fa-user-xmark"></i>
                                        </button>
                                    <?php elseif ($has_face && $reset_allowed): ?>
                                        <button onclick="toggleFacePermission('<?php echo $row['id_karyawan']; ?>', 'lock_face', '<?php echo htmlspecialchars($row['username']); ?>')" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg dark:text-emerald-400 dark:hover:bg-emerald-900/30 transition-colors" title="Kunci Registrasi Wajah">
                                            <i class="fa-solid fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($row['role'] == 'staff'): ?>
                                    <button onclick="openEditUserModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(addslashes($row['username'])); ?>')" class="p-2 text-fuchsia-600 hover:bg-fuchsia-50 rounded-lg dark:text-fuchsia-400 dark:hover:bg-fuchsia-900/30 transition-colors" title="Ganti Password">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <a href="master_process.php?hapus_user=<?php echo $row['id']; ?>" onclick="event.preventDefault(); handleDeleteUserAction(this.href, 'Hapus Akun Staff?', 'Yakin hapus akun staff ini?');" class="p-2 text-red-600 hover:bg-red-50 rounded-lg dark:text-red-400 dark:hover:bg-red-900/30 transition-colors" title="Hapus Staff">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                <?php elseif ($row['role'] == 'owner'): ?>
                                    <button onclick="openEditUserModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(addslashes($row['username'])); ?>')" class="p-2 text-fuchsia-600 hover:bg-fuchsia-50 rounded-lg dark:text-fuchsia-400 dark:hover:bg-fuchsia-900/30 transition-colors" title="Ganti Password Owner">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr id="noResultsRow">
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                            <i class="fa-solid fa-users-slash text-5xl mb-4 opacity-50"></i>
                            <p class="text-lg font-medium text-slate-800 dark:text-white">Tidak ada data pengguna</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="p-5 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/20">
        <span id="tableInfo" class="text-sm text-slate-500 dark:text-slate-400">Menampilkan 0 hingga 0 dari 0 data</span>
        <div id="paginationControls" class="flex gap-1">
            <!-- Buttons injected by JS -->
        </div>
    </div>
</div>

<!-- Modal Tambah Akun Staff -->
<div id="modal-tambah-staff" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-tambah-staff')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Tambah Akun Staff Baru</h3>
                <button onclick="closeModal('modal-tambah-staff')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            
            <?php if ($res_karyawan->num_rows > 0): ?>
            <form action="master_process.php" method="POST" id="formTambahStaff">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Pilih Karyawan <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="hidden" name="id_karyawan_selected" id="karyawan_select_for_user" required>
                            <div id="btnPilihKaryawanStaff" onclick="openKaryawanModalStaff()" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-500 text-left text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors cursor-pointer flex justify-between items-center">
                                <span id="textPilihKaryawanStaff">-- Pilih Karyawan --</span>
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div id="staff-credentials" class="hidden space-y-4">
                        <div class="p-3 bg-fuchsia-50 dark:bg-fuchsia-900/30 text-fuchsia-700 dark:text-fuchsia-400 rounded-lg text-sm border border-fuchsia-100 dark:border-fuchsia-800/50">
                            <i class="fa-solid fa-circle-info mr-1"></i> Username akan otomatis menggunakan ID Karyawan yang dipilih.
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Username (Otomatis)</label>
                            <input type="text" name="username" id="username_staff" readonly class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-500 dark:text-slate-400 text-sm focus:outline-none cursor-not-allowed">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" id="password_staff" required minlength="8" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                            <p class="text-xs text-slate-500 mt-1">Minimal 8 karakter.</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                            <input type="password" id="confirm_password_staff" required minlength="8" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors">
                            <p id="password_match_msg" class="text-xs mt-1 hidden"></p>
                        </div>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-tambah-staff')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                    <button type="submit" name="tambah_staff" id="btnSimpanStaff" class="hidden px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl shadow-sm shadow-brand-500/30 transition-colors text-sm font-medium">Buat Akun</button>
                </div>
            </form>
            <?php else: ?>
                <div class="p-6">
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-xl border border-amber-200 dark:border-amber-800/50 text-sm flex gap-3 items-start">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5 text-lg"></i>
                        <div>
                            <p class="font-bold">Perhatian</p>
                            <p>Tidak ada karyawan yang belum memiliki akun. Silakan tambah data karyawan terlebih dahulu di menu Master Data.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="button" onclick="closeModal('modal-tambah-staff')" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-200 transition-colors text-sm font-medium">Tutup</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Supervisor -->
<div id="modal-tambah-supervisor" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-tambah-supervisor')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Buat Akun Supervisor</h3>
                <button onclick="closeModal('modal-tambah-supervisor')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <form id="formTambahSupervisor" action="master_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="px-6 py-5 space-y-4">
                    <div class="bg-teal-50 dark:bg-teal-900/20 p-4 rounded-xl border border-teal-100 dark:border-teal-800/50 text-sm text-teal-700 dark:text-teal-300">
                        Supervisor hanya dapat meninjau dan menyetujui pengajuan izin karyawan pada cabang yang dipilih.
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" name="nama_supervisor" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Cabang yang Disupervisi <span class="text-rose-500">*</span></label>
                        <select name="id_cabang_supervisor" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 transition-colors">
                            <option value="">-- Pilih Cabang --</option>
                            <?php if ($res_cabang_supervisor) { $res_cabang_supervisor->data_seek(0); while ($cbg = $res_cabang_supervisor->fetch_assoc()): ?>
                                <option value="<?php echo (int)$cbg['id']; ?>"><?php echo htmlspecialchars($cbg['nama_cabang']); ?></option>
                            <?php endwhile; } ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Username <span class="text-rose-500">*</span></label>
                        <input type="text" name="username_supervisor" required minlength="4" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 transition-colors">
                        <p class="text-xs text-slate-500 mt-1">Minimal 4 karakter, tanpa spasi.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password <span class="text-rose-500">*</span></label>
                        <input type="password" name="password_supervisor" required minlength="8" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 transition-colors">
                        <p class="text-xs text-slate-500 mt-1">Minimal 8 karakter.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin_supervisor" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 transition-colors">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-tambah-supervisor')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                    <button type="submit" name="tambah_supervisor" class="px-6 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl shadow-sm shadow-teal-500/30 transition-colors text-sm font-medium">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Owner (QR Code) -->
<div id="modal-tambah-owner" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-tambah-owner')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Akses Pendaftaran Owner</h3>
                <button onclick="closeModal('modal-tambah-owner')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <?php
                $secret_salt = "DINIA_OWNER_SECRET_2026";
                $current_owner_token = substr(strtoupper(md5(date('Y-m-d H') . $secret_salt)), 0, 6);
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                $host = $_SERVER['HTTP_HOST'];
                $dir = dirname($_SERVER['PHP_SELF']);
                $owner_reg_link = $protocol . "://" . $host . $dir . "/buat_akun_owner.php";
                ?>
                <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-xl border border-blue-100 dark:border-blue-800/50 mb-5 text-sm text-blue-700 dark:text-blue-300">
                    <p>Bagikan QR Code ini kepada calon Owner, <strong>beserta Kode Rahasia</strong> di bawah.</p>
                </div>
                
                <div class="flex justify-center mb-5">
                    <div class="p-3 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center w-[216px] h-[216px]" id="visibleQRCodeContainer">
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">OTP Code (Bersifat Rahasia!)</label>
                        <div class="flex gap-2">
                            <input type="text" id="tokenOwner" value="<?php echo $current_owner_token; ?>" readonly class="w-full px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-200 dark:border-amber-800/50 rounded-xl text-amber-800 dark:text-amber-400 font-mono text-center tracking-widest text-2xl font-bold outline-none cursor-pointer" onclick="copyToClipboard('tokenOwner', 'Kode Rahasia')">
                            <button type="button" onclick="copyToClipboard('tokenOwner', 'Kode Rahasia')" class="px-5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-xl transition-colors shadow-sm" title="Salin Kode">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Link Pendaftaran</label>
                        <div class="flex gap-2">
                            <input type="text" id="linkOwner" value="<?php echo $owner_reg_link; ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-600 dark:text-slate-300 text-sm outline-none text-ellipsis">
                            <button type="button" onclick="copyToClipboard('linkOwner', 'Link Pendaftaran')" class="px-5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-xl transition-colors shadow-sm" title="Salin Link">
                                <i class="fa-solid fa-link"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-center">
                    <button type="button" onclick="downloadQRCodeCard()" class="w-full px-6 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl shadow-md shadow-brand-500/30 transition-colors text-sm font-bold flex items-center justify-center gap-2">
                        <i class="fa-solid fa-download"></i> Unduh QR Code
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Card for Download -->
<div id="downloadCardWrapper" class="fixed top-[-9999px] left-[-9999px]">
    <div id="downloadCard" class="bg-gradient-to-br from-slate-900 to-slate-800 w-[400px] rounded-3xl p-8 text-center border border-slate-700 shadow-2xl relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute -top-20 -right-20 w-40 h-40 bg-fuchsia-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-brand-500 rounded-full blur-3xl opacity-20"></div>
        
        <div class="relative z-10">
            <!-- Header -->
            <div class="inline-flex items-center justify-center w-16 h-16 bg-brand-500 rounded-2xl mb-4 text-white text-3xl shadow-lg shadow-brand-500/50">
                <i class="fa-solid fa-crown"></i>
            </div>
            <h2 class="text-2xl font-bold text-white mb-1">Akses Owner</h2>
            <p class="text-slate-400 text-sm mb-6">Scan QR Code ini untuk mendaftarkan akun Owner Anda.</p>
            
            <!-- QR Code Area -->
            <div class="bg-white p-4 rounded-2xl inline-block mb-6 shadow-xl flex items-center justify-center w-[224px] h-[224px] mx-auto" id="hiddenQRCodeContainer">
            </div>
            
            <!-- Secret Code -->
            <div class="bg-slate-800/80 border border-slate-600 rounded-xl p-4 shadow-inner">
                <p class="text-xs text-slate-400 uppercase tracking-widest font-semibold mb-1">OTP / Kode Rahasia</p>
                <p class="text-3xl font-mono font-bold text-amber-400 tracking-[0.2em] my-2"><?php echo $current_owner_token; ?></p>
                <p class="text-[10px] text-slate-500">*Kode ini akan berubah setiap 1 Jam</p>
            </div>
            
            <div class="mt-6 border-t border-slate-700 pt-4">
                <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">AbsenSlip Dinia House Of Hijab</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Admin Rahasia -->
<div id="modal-tambah-admin" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-tambah-admin')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Akses Pendaftaran Admin</h3>
                <button onclick="closeModal('modal-tambah-admin')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <?php
                $admin_secret_salt = "DINIA_ADMIN_SECRET_2026";
                $current_admin_token = substr(strtoupper(md5(date('Y-m-d H') . $admin_secret_salt)), 0, 6);
                $admin_reg_link = $protocol . "://" . $host . $dir . "/daftar_admin.php";
                ?>
                <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-xl border border-blue-100 dark:border-blue-800/50 mb-5 text-sm text-blue-700 dark:text-blue-300">
                    <p>Bagikan QR Code ini kepada calon Admin, <strong>beserta Kode Rahasia</strong> di bawah.</p>
                </div>
                
                <div class="flex justify-center mb-5">
                    <div class="p-3 bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center justify-center w-[216px] h-[216px]" id="visibleQRCodeAdminContainer">
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">OTP Code (Bersifat Rahasia!)</label>
                        <div class="flex gap-2">
                            <input type="text" id="tokenAdmin" value="<?php echo $current_admin_token; ?>" readonly class="w-full px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800/50 rounded-xl text-blue-800 dark:text-blue-400 font-mono text-center tracking-widest text-2xl font-bold outline-none cursor-pointer" onclick="copyToClipboard('tokenAdmin', 'Kode Rahasia Admin')">
                            <button type="button" onclick="copyToClipboard('tokenAdmin', 'Kode Rahasia Admin')" class="px-5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-xl transition-colors shadow-sm" title="Salin Kode">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Link Pendaftaran</label>
                        <div class="flex gap-2">
                            <input type="text" id="linkAdmin" value="<?php echo $admin_reg_link; ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-600 dark:text-slate-300 text-sm outline-none text-ellipsis">
                            <button type="button" onclick="copyToClipboard('linkAdmin', 'Link Pendaftaran Admin')" class="px-5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-xl transition-colors shadow-sm" title="Salin Link">
                                <i class="fa-solid fa-link"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-center">
                    <button type="button" onclick="downloadAdminQRCodeCard()" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md shadow-blue-500/30 transition-colors text-sm font-bold flex items-center justify-center gap-2">
                        <i class="fa-solid fa-download"></i> Unduh QR Code
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Card for Download Admin -->
<div id="downloadCardAdminWrapper" class="fixed top-[-9999px] left-[-9999px]">
    <div id="downloadCardAdmin" class="bg-gradient-to-br from-slate-900 to-slate-800 w-[400px] rounded-3xl p-8 text-center border border-slate-700 shadow-2xl relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute -top-20 -right-20 w-40 h-40 bg-blue-500 rounded-full blur-3xl opacity-20"></div>
        <div class="absolute -bottom-20 -left-20 w-40 h-40 bg-indigo-500 rounded-full blur-3xl opacity-20"></div>
        
        <div class="relative z-10">
            <!-- Header -->
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-500 rounded-2xl mb-4 text-white text-3xl shadow-lg shadow-blue-500/50">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <h2 class="text-2xl font-bold text-white mb-1">Akses Admin</h2>
            <p class="text-slate-400 text-sm mb-6">Scan QR Code ini untuk mendaftarkan akun Admin Anda.</p>
            
            <!-- QR Code Area -->
            <div class="bg-white p-4 rounded-2xl inline-block mb-6 shadow-xl flex items-center justify-center w-[224px] h-[224px] mx-auto" id="hiddenQRCodeAdminContainer">
            </div>
            
            <!-- Secret Code -->
            <div class="bg-slate-800/80 border border-slate-600 rounded-xl p-4 shadow-inner">
                <p class="text-xs text-slate-400 uppercase tracking-widest font-semibold mb-1">OTP / Kode Rahasia</p>
                <p class="text-3xl font-mono font-bold text-blue-400 tracking-[0.2em] my-2"><?php echo $current_admin_token; ?></p>
                <p class="text-[10px] text-slate-500">*Kode ini akan berubah setiap 1 Jam</p>
            </div>
            
            <div class="mt-6 border-t border-slate-700 pt-4">
                <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">AbsenSlip Dinia House Of Hijab</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Password User -->
<div id="modal-edit-user" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-edit-user')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Ganti Password</h3>
                <button onclick="closeModal('modal-edit-user')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <form action="master_process.php" method="POST" id="form-edit-user">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="id_user" id="edit-id-user">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
                        <input type="text" id="edit-username-user" readonly class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-500 dark:text-slate-400 text-sm focus:outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password Baru <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="edit-password-user" name="password" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-brand-500 transition-colors" onclick="togglePasswordVisibility('edit-password-user', this)">
                                <i class="fa-regular fa-eye-slash"></i>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Minimal 6 karakter.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" id="edit-password-user-confirm" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-brand-500 transition-colors" onclick="togglePasswordVisibility('edit-password-user-confirm', this)">
                                <i class="fa-regular fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('modal-edit-user')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                    <button type="submit" class="px-6 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-xl shadow-sm shadow-brand-500/30 transition-colors text-sm font-medium">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pilih Karyawan (Staff) -->
<div id="modal-pilih-karyawan-staff" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeKaryawanModalStaff()"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-2xl w-full border border-slate-200 dark:border-slate-700 flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50 shrink-0">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white">Pilih Karyawan</h3>
                <button onclick="closeKaryawanModalStaff()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
                    </div>
                    <input type="text" id="searchKaryawanStaff" onkeyup="filterKaryawanStaff()" class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 dark:border-slate-600 rounded-xl leading-5 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 sm:text-sm transition-colors" placeholder="Cari nama atau ID karyawan...">
                </div>
            </div>
            <div class="overflow-y-auto p-0 flex-1">
                <table class="w-full text-left border-collapse" id="tableKaryawanStaff">
                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-900 shadow-sm z-10">
                        <tr class="text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                            <th class="px-4 py-3 font-semibold w-12 text-center">No</th>
                            <th class="px-4 py-3 font-semibold">ID Karyawan</th>
                            <th class="px-4 py-3 font-semibold">Nama Karyawan</th>
                            <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <?php 
                        mysqli_data_seek($res_karyawan, 0);
                        if ($res_karyawan->num_rows > 0): 
                            $no_k = 1;
                            while($k = $res_karyawan->fetch_assoc()): 
                        ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors karyawan-row-staff">
                                <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-slate-500 dark:text-slate-400"><?php echo $no_k++; ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-slate-800 dark:text-white id-cell"><?php echo htmlspecialchars($k['id_karyawan']); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300 nama-cell"><?php echo htmlspecialchars($k['nama_karyawan']); ?></td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                    <button type="button" onclick="pilihKaryawanStaff('<?php echo htmlspecialchars($k['id_karyawan']); ?>', '<?php echo htmlspecialchars(addslashes($k['nama_karyawan'])); ?>')" class="px-3 py-1.5 bg-brand-50 hover:bg-brand-100 text-brand-600 dark:bg-brand-900/30 dark:hover:bg-brand-900/50 dark:text-brand-400 rounded-lg transition-colors">
                                        Pilih
                                    </button>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400 text-sm">Tidak ada karyawan yang tersedia.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Owner QR Code
    const ownerLink = document.getElementById("linkOwner") ? document.getElementById("linkOwner").value : null;
    if (ownerLink) {
        new QRCode(document.getElementById("visibleQRCodeContainer"), {
            text: ownerLink, width: 192, height: 192, colorDark : "#0f172a", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H
        });
        new QRCode(document.getElementById("hiddenQRCodeContainer"), {
            text: ownerLink, width: 192, height: 192, colorDark : "#0f172a", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H
        });
    }

    // Admin QR Code
    const adminLink = document.getElementById("linkAdmin") ? document.getElementById("linkAdmin").value : null;
    if (adminLink) {
        new QRCode(document.getElementById("visibleQRCodeAdminContainer"), {
            text: adminLink, width: 192, height: 192, colorDark : "#0f172a", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H
        });
        new QRCode(document.getElementById("hiddenQRCodeAdminContainer"), {
            text: adminLink, width: 192, height: 192, colorDark : "#0f172a", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H
        });
    }
});

// Download Admin QR Code Card Function
function downloadAdminQRCodeCard() {
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
    btn.disabled = true;

    const card = document.getElementById('downloadCardAdmin');
    html2canvas(card, {
        backgroundColor: null,
        scale: 2
    }).then(canvas => {
        let link = document.createElement('a');
        link.download = 'Akses_Admin_Dinia.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if(typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Diunduh!',
                text: 'QR Code Admin telah disimpan.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }).catch(err => {
        console.error('Error generating canvas:', err);
        btn.innerHTML = originalText;
        btn.disabled = false;
        if(typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Gagal memproses gambar.', 'error');
        }
    });
}

// Download QR Code Card Function (Owner)
function downloadQRCodeCard() {
    const btn = event.currentTarget;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
    btn.disabled = true;

    const card = document.getElementById('downloadCard');
    html2canvas(card, {
        backgroundColor: null,
        scale: 2,
        useCORS: true // Memastikan gambar API luar bisa dirender
    }).then(canvas => {
        let link = document.createElement('a');
        link.download = 'Akses_Owner_Dinia.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if(typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Diunduh!',
                text: 'QR Code dan OTP telah disimpan sebagai gambar PNG.',
                timer: 2000,
                showConfirmButton: false
            });
        }
    }).catch(err => {
        console.error('Error generating canvas:', err);
        btn.innerHTML = originalText;
        btn.disabled = false;
        if(typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Gagal memproses gambar.', 'error');
        }
    });
}

// Copy to Clipboard Function
function copyToClipboard(elementId, typeName) {
    var copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(copyText.value).then(() => {
        if(typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Tersalin!',
                text: typeName + ' berhasil disalin ke clipboard.',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            alert(typeName + ' tersalin!');
        }
    });
}

// Toggle Password Visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById('toggleIcon-' + inputId);
    
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

// Logic for Add Staff form matching passwords
document.addEventListener('DOMContentLoaded', function() {
    const karyawanSelect = document.getElementById('karyawan_select_for_user');
    const credentialsDiv = document.getElementById('staff-credentials');
    const usernameInput = document.getElementById('username_staff');
    const btnSimpan = document.getElementById('btnSimpanStaff');
    const passInput = document.getElementById('password_staff');
    const confirmInput = document.getElementById('confirm_password_staff');
    const msg = document.getElementById('password_match_msg');
    const form = document.getElementById('formTambahStaff');

    function checkPassword() {
        if (!passInput.value && !confirmInput.value) {
            msg.classList.add('hidden');
            btnSimpan.disabled = false;
            btnSimpan.classList.remove('opacity-50', 'cursor-not-allowed');
            return;
        }

        msg.classList.remove('hidden');
        if (passInput.value === confirmInput.value && passInput.value.length >= 8) {
            msg.textContent = 'Password cocok dan memenuhi syarat';
            msg.className = 'text-xs mt-1 text-emerald-500';
            btnSimpan.disabled = false;
            btnSimpan.classList.remove('opacity-50', 'cursor-not-allowed');
        } else if (passInput.value.length < 8) {
            msg.textContent = 'Password minimal 8 karakter';
            msg.className = 'text-xs mt-1 text-rose-500';
            btnSimpan.disabled = true;
            btnSimpan.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            msg.textContent = 'Password tidak cocok';
            msg.className = 'text-xs mt-1 text-rose-500';
            btnSimpan.disabled = true;
            btnSimpan.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    if(passInput && confirmInput) {
        passInput.addEventListener('input', checkPassword);
        confirmInput.addEventListener('input', checkPassword);
    }
});



// Face toggle permission
function toggleFacePermission(id_karyawan, action, username) {
    let message = '';
    let title = 'Konfirmasi';
    let confirmColor = '#4f46e5'; // indigo
    
    if (action === 'allow_reset') {
        message = 'Izinkan ' + username + ' untuk mendaftarkan ulang wajahnya di perangkat?';
    } else if (action === 'lock_face') {
        message = 'Kunci kembali registrasi wajah untuk ' + username + '?';
        confirmColor = '#059669'; // emerald
    } else if (action === 'delete_face') {
        title = 'PERINGATAN';
        message = 'Hapus permanen data wajah ' + username + '? Karyawan harus mendaftar ulang.';
        confirmColor = '#e11d48'; // rose
    }
    
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: action === 'delete_face' ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Oke',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch('master_process.php?action=' + action + '&id_karyawan=' + id_karyawan + '&is_ajax=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                fetch(window.location.href)
                                    .then(res => res.text())
                                    .then(html => {
                                        const parser = new DOMParser();
                                        const doc = parser.parseFromString(html, 'text/html');
                                        document.querySelector('#usersTable tbody').innerHTML = doc.querySelector('#usersTable tbody').innerHTML;
                                        filterTable();
                                    });
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan!',
                            text: 'Gagal terhubung ke server.'
                        });
                    });
            }
        });
    } else {
        if (confirm(message)) {
            window.location.href = 'master_process.php?action=' + action + '&id_karyawan=' + id_karyawan;
        }
    }
}

function handleDeleteUserAction(url, title, text) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Oke, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch(url + '&is_ajax=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    fetch(window.location.href)
                                        .then(res => res.text())
                                        .then(html => {
                                            const parser = new DOMParser();
                                            const doc = parser.parseFromString(html, 'text/html');
                                            document.querySelector('#usersTable tbody').innerHTML = doc.querySelector('#usersTable tbody').innerHTML;
                                            filterTable();
                                        });
                                });
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan!',
                            text: 'Gagal terhubung ke server.'
                        });
                    });
            }
        });
    } else {
        if (confirm(text)) {
            window.location.href = url;
        }
    }
}

// Handler untuk form AJAX simpan data
function handleFormAjax(formId, processText, confirmText) {
    const form = document.getElementById(formId);
    if(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (formId === 'form-edit-user') {
                const pwd = document.getElementById('edit-password-user').value;
                const confirmPwd = document.getElementById('edit-password-user-confirm').value;
                if (pwd !== confirmPwd) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validasi Gagal',
                        text: 'Konfirmasi password tidak cocok!',
                        customClass: { popup: 'rounded-3xl' }
                    });
                    return;
                }
            }
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Konfirmasi',
                    text: confirmText,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Ya, Simpan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Memproses...',
                            text: processText,
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        const formData = new FormData(this);
                        formData.append('is_ajax', '1');
                        // Ensure button name is appended since it is used in PHP isset
                        if (formId === 'formTambahStaff') formData.append('tambah_staff', '1');
                        if (formId === 'formTambahOwner') formData.append('tambah_owner', '1');
                        if (formId === 'formTambahSupervisor') formData.append('tambah_supervisor', '1');
                        if (formId === 'form-edit-user') formData.append('edit_user', '1');
                        
                        fetch(this.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data.message
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Terjadi Kesalahan!',
                                text: 'Gagal terhubung ke server.'
                            });
                        });
                    }
                });
            } else {
                if (confirm(confirmText)) {
                    this.submit();
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    handleFormAjax('formTambahStaff', 'Membuat akun staff...', 'Apakah Anda yakin ingin menyimpan akun staff ini?');
    handleFormAjax('formTambahOwner', 'Membuat akun owner...', 'Apakah Anda yakin ingin membuat akun owner ini?');
    handleFormAjax('formTambahSupervisor', 'Membuat akun supervisor...', 'Apakah Anda yakin ingin membuat akun supervisor ini?');
    handleFormAjax('form-edit-user', 'Menyimpan password...', 'Apakah Anda yakin ingin mengganti password user ini?');
});

// Search
let currentPage = 1;
let entriesPerPage = 5;

document.addEventListener('DOMContentLoaded', () => {
    filterTable();
});

function changeEntries() {
    const val = document.getElementById('entriesSelect').value;
    entriesPerPage = val === 'all' ? Number.MAX_SAFE_INTEGER : parseInt(val);
    currentPage = 1;
    filterTable();
}

function goToPage(page) {
    currentPage = page;
    filterTable();
}

function filterTable() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('usersTable');
    if(!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    let filteredRows = [];

    // Filter baris
    rows.forEach(row => {
        // Skip baris "Tidak ada data"
        if (row.id === 'noResultsRow' || row.querySelector('td[colspan]')) return;

        let matchSearch = false;
        const targets = row.querySelectorAll('.search-target');
        
        if (targets.length > 0) {
            targets.forEach(target => {
                if (target.textContent.toLowerCase().includes(searchInput)) {
                    matchSearch = true;
                }
            });
        } else {
            // Fallback jika tidak ada class search-target
            matchSearch = row.textContent.toLowerCase().includes(searchInput);
        }

        if (matchSearch) {
            filteredRows.push(row);
        }
        row.style.display = 'none'; // Sembunyikan semua dulu
    });

    const totalEntries = filteredRows.length;
    const totalPages = Math.ceil(totalEntries / entriesPerPage);
    
    // Pastikan current page valid
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIndex = (currentPage - 1) * entriesPerPage;
    const endIndex = Math.min(startIndex + entriesPerPage, totalEntries);

    // Update Nomor Urut dan Tampilkan baris sesuai halaman
    let displayIndex = startIndex + 1;
    for (let i = startIndex; i < endIndex; i++) {
        filteredRows[i].style.display = '';
        // Update kolom pertama (No) jika bukan th
        const firstTd = filteredRows[i].querySelector('td:first-child');
        if (firstTd && !firstTd.hasAttribute('colspan')) {
            firstTd.innerHTML = displayIndex++;
        }
    }

    // Tampilkan pesan tidak ada hasil jika total entries = 0
    let noResultsRow = document.getElementById('noResultsRow');
    if (totalEntries === 0) {
        if (!noResultsRow && searchInput !== '') {
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'noResultsRow';
            noResultsRow.innerHTML = `
                <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                    <i class="fa-solid fa-users-slash text-5xl mb-4 opacity-50"></i>
                    <p class="text-lg font-medium text-slate-800 dark:text-white">Tidak ada data pengguna yang cocok</p>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        } else if (noResultsRow) {
            noResultsRow.style.display = '';
        }
    } else {
        if (noResultsRow) noResultsRow.style.display = 'none';
    }

    // Update Info
    const infoSpan = document.getElementById('tableInfo');
    if (totalEntries === 0) {
        infoSpan.textContent = 'Menampilkan 0 hingga 0 dari 0 data';
    } else {
        infoSpan.textContent = `Menampilkan ${startIndex + 1} hingga ${endIndex} dari ${totalEntries} data`;
    }

    // Update Pagination Buttons
    const paginationControls = document.getElementById('paginationControls');
    paginationControls.innerHTML = '';

    if (totalPages > 1) {
        // Prev button
        const prevBtn = document.createElement('button');
        prevBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}`;
        prevBtn.textContent = 'Previous';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => goToPage(currentPage - 1);
        paginationControls.appendChild(prevBtn);

        // Page buttons (simplified: show all if few, or just surrounding pages if many)
        for (let i = 1; i <= totalPages; i++) {
            // Batasi tombol yang tampil agar tidak kepanjangan
            if (totalPages > 7) {
                if (i !== 1 && i !== totalPages && (i < currentPage - 1 || i > currentPage + 1)) {
                    if (i === currentPage - 2 || i === currentPage + 2) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'px-2 py-1 text-slate-500';
                        ellipsis.innerHTML = '&hellip;';
                        paginationControls.appendChild(ellipsis);
                    }
                    continue;
                }
            }

            const pageBtn = document.createElement('button');
            if (i === currentPage) {
                pageBtn.className = 'px-3 py-1 border border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400 rounded-md font-medium';
            } else {
                pageBtn.className = 'px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors';
            }
            pageBtn.textContent = i;
            pageBtn.onclick = () => goToPage(i);
            paginationControls.appendChild(pageBtn);
        }

        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.className = `px-3 py-1 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-800 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}`;
        nextBtn.textContent = 'Next';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => goToPage(currentPage + 1);
        paginationControls.appendChild(nextBtn);
    }
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        icon.classList.add('text-brand-500');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        icon.classList.remove('text-brand-500');
    }
}

// Modal Pilih Karyawan Logic
function openKaryawanModalStaff() {
    document.getElementById('modal-pilih-karyawan-staff').classList.remove('hidden');
    setTimeout(() => { document.getElementById('searchKaryawanStaff').focus(); }, 100);
}
function closeKaryawanModalStaff() {
    document.getElementById('modal-pilih-karyawan-staff').classList.add('hidden');
}
function filterKaryawanStaff() {
    let input = document.getElementById('searchKaryawanStaff');
    let filter = input.value.toLowerCase();
    let rows = document.getElementsByClassName('karyawan-row-staff');
    for (let i = 0; i < rows.length; i++) {
        let idText = rows[i].querySelector('.id-cell').textContent.toLowerCase();
        let namaText = rows[i].querySelector('.nama-cell').textContent.toLowerCase();
        if (idText.includes(filter) || namaText.includes(filter)) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }
    }
}
function pilihKaryawanStaff(id_karyawan, nama_karyawan) {
    document.getElementById('karyawan_select_for_user').value = id_karyawan;
    
    let btnText = document.getElementById('textPilihKaryawanStaff');
    btnText.textContent = nama_karyawan + " (ID: " + id_karyawan + ")";
    btnText.classList.remove('text-slate-500');
    btnText.classList.add('text-slate-800', 'dark:text-white', 'font-medium');
    
    closeKaryawanModalStaff();
    
    // Show credentials div
    const credentialsDiv = document.getElementById('staff-credentials');
    const usernameInput = document.getElementById('username_staff');
    const btnSimpan = document.getElementById('btnSimpanStaff');
    
    if (id_karyawan) {
        credentialsDiv.classList.remove('hidden');
        usernameInput.value = id_karyawan;
        btnSimpan.classList.remove('hidden');
    }
}
</script>
<?php include 'admin_footer.php'; ?>

