<?php
// Memulai session dan memastikan hanya role 'staff' yang bisa akses
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'staff') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_karyawan_display = $_SESSION['username']; // Default
$has_face_registered = false; // Default

// Query untuk mendapatkan id_karyawan, nama, status face registration, dan foto
$sql_get_data = "SELECT u.id_karyawan, k.nama_karyawan, k.jenis_kelamin, k.foto, u.face_descriptor, u.face_registered_at
                 FROM users u
                 LEFT JOIN karyawan k ON u.id_karyawan = k.id_karyawan 
                 WHERE u.id = ?";
$stmt = $conn->prepare($sql_get_data);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $_SESSION['id_karyawan'] = $data['id_karyawan'];
    $nama_karyawan_display = $data['nama_karyawan'] ? $data['nama_karyawan'] : $_SESSION['username'];
    $has_face_registered = !empty($data['face_descriptor']);
    $staff_jk = $data['jenis_kelamin'] ?? 'L';
    $staff_foto = $data['foto'] ?? '';
} else {
    echo "<script>alert('Error: Akun staff Anda tidak terhubung dengan data karyawan. Hubungi Admin.');</script>";
    session_destroy();
    header("Location: login.php");
    exit();
}
$stmt->close();

$current_page = basename($_SERVER['PHP_SELF']);
$avatar_src = !empty($staff_foto) ? 'assets/images/foto_karyawan/' . $staff_foto : (($staff_jk == 'P') ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2');
$avatar_bg = ($staff_jk == 'P') ? 'bg-pink-100' : 'bg-fuchsia-100';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <title>Dashboard Karyawan - Absensi Dinia</title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Konfigurasi Tema Tailwind -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fdf4ff',
                            100: '#fae8ff',
                            500: '#d946ef',
                            600: '#c026d3',
                            900: '#701a75',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome untuk Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Phosphor Icons (Modern UI) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        /* Scrollbar kustom untuk sidebar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Animasi Face Registration Pulse */
        @keyframes pulse-ring {
            0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .pulse-badge {
            animation: pulse-ring 2s infinite;
        }

        /* Prevent FOUC transitions */
        .preload * { transition: none !important; }
        
        /* Prevent DataTables FOUC (Flash of Unstyled Content) and jumping */
        body.preload table:not(.dataTable) tbody tr:nth-child(n+6) { display: none !important; }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        }
        window.addEventListener('load', () => {
            setTimeout(() => document.body.classList.remove('preload'), 100);
        });
    </script>
    <style>
        /* Sidebar Collapse Animation for Desktop */
        #sidebar {
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            overflow-x: hidden;
        }
        #sidebar span.text-sm, 
        #sidebar .logo-text-container,
        #sidebar p.tracking-widest {
            transition: opacity 0.2s ease-in-out, width 0.3s ease-in-out;
            white-space: nowrap;
        }
        
        @media (min-width: 768px) {
            body.sidebar-collapsed #sidebar {
                width: 5.5rem !important;
            }
            body.sidebar-collapsed #sidebar .logo-text-container {
                opacity: 0;
                width: 0;
                visibility: hidden;
                margin: 0;
            }
            body.sidebar-collapsed #sidebar span.text-sm {
                opacity: 0;
                width: 0;
                visibility: hidden;
                margin: 0;
            }
            body.sidebar-collapsed #sidebar p.tracking-widest {
                opacity: 0;
                height: 0;
                margin: 0;
                padding: 0;
                visibility: hidden;
            }
            body.sidebar-collapsed #sidebar .fa-chevron-down {
                display: none;
            }
            body.sidebar-collapsed #sidebar a.group, 
            body.sidebar-collapsed #sidebar button.group {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
            body.sidebar-collapsed #sidebar .pulse-badge,
            body.sidebar-collapsed #sidebar span.text-\[10px\] {
                display: none !important;
            }
            body.sidebar-collapsed #sidebar [x-show="open"] {
                display: none !important;
            }
            body.sidebar-collapsed #sidebar .h-20 {
                justify-content: center;
                padding-left: 0;
                padding-right: 0;
            }
        }
    </style>
</head>
<body class="preload bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200 h-screen flex overflow-hidden transition-colors duration-200">

    <!-- SIDEBAR (LabFlow Style - Selalu Gelap) -->
    <aside id="sidebar" class="w-64 bg-[#0b172a] border-r border-[#1e293b] flex flex-col transition-all duration-300 fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 z-50 h-screen shadow-2xl md:shadow-[4px_0_24px_rgba(0,0,0,0.15)] dark:md:shadow-[4px_0_24px_rgba(0,0,0,0.4)]">
        
        <!-- Top Left Logo -->
        <div class="h-20 flex items-center gap-3 px-5 border-b border-[#1e293b] shrink-0 bg-slate-950">
            <div class="bg-white/10 p-1.5 rounded-xl shadow-lg border border-white/10">
                <img src="Dinia-Logo.png" alt="Dinia Logo" class="h-8 w-auto" onerror="this.style.display='none'">
            </div>
            <div class="flex flex-col justify-center space-y-0.5 logo-text-container">
                <h2 class="text-2xl font-bold tracking-tight text-white leading-none">
                    Absen<span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-200">Slip</span>
                </h2>
                <span class="text-xs text-slate-300 font-medium tracking-wide leading-none ml-0.5 pt-0.5">
                    Dinia House Of Hijab
                </span>
            </div>

        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-6 space-y-2 no-scrollbar">
            <p class="px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 mt-2">Menu Karyawan</p>
            
            <a href="staff_dashboard.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'staff_dashboard.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-clock-counter-clockwise text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'staff_dashboard.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Histori Absensi</span>
            </a>

            <!-- Riwayat Ranking Pribadi -->
            <a href="staff_ranking_history.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'staff_ranking_history.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-medal text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'staff_ranking_history.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Riwayat Ranking</span>
            </a>

            <!-- Laporan Gaji Pribadi -->
            <a href="staff_laporan_gaji.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'staff_laporan_gaji.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-receipt text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'staff_laporan_gaji.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Informasi Gaji</span>
            </a>

            <!-- Pengajuan Izin / Cuti -->
            <a href="staff_pengajuan_izin.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'staff_pengajuan_izin.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-calendar-plus text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'staff_pengajuan_izin.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Pengajuan Izin</span>
            </a>

            <!-- Face Registration -->
            <a href="register_face.php" class="flex items-center justify-between px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'register_face.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <div class="flex items-center gap-3">
                    <i class="ph-duotone ph-shield-check text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'register_face.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                    <span class="font-medium text-sm">Registrasi Wajah</span>
                </div>
                <?php if ($has_face_registered): ?>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-emerald-500/20 text-emerald-400 px-2.5 py-0.5 rounded-full border border-emerald-500/30 uppercase">
                        <i class="fa-solid fa-check"></i> Aktif
                    </span>
                <?php else: ?>
                    <span class="pulse-badge inline-flex items-center gap-1 text-[10px] font-bold bg-rose-500/20 text-rose-400 px-2.5 py-0.5 rounded-full border border-rose-500/30 uppercase shadow-sm shadow-rose-500/20">
                        <i class="fa-solid fa-exclamation"></i> Belum
                    </span>
                <?php endif; ?>
            </a>

            <a href="logout.php" onclick="confirmLogout(event, this.href);" class="flex items-center gap-3 px-4 py-3 mx-4 text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 rounded-xl transition-all duration-300 group mt-4">
                <i class="ph-duotone ph-sign-out text-xl w-6 flex items-center justify-center opacity-70 group-hover:opacity-100 transition-opacity"></i>
                <span class="font-medium text-sm">Logout</span>
            </a>

        </nav>
        
    </aside>

    <!-- Overlay untuk Sidebar di Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden md:hidden opacity-0 transition-opacity duration-300"></div>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full bg-slate-50 dark:bg-slate-900">
        
        <!-- HEADER -->
        <header class="h-20 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 sm:px-6 z-30 transition-colors duration-200 shrink-0">
            <!-- Left: Mobile Menu Toggle -->
            <div class="flex items-center gap-3">
                <button id="openSidebar" class="text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 transition-colors p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Right: Dark Mode Toggle & Profile Dropdown -->
            <div class="flex items-center gap-2 sm:gap-4">
                
                <!-- Dark Mode Toggle (Pill Switch) -->
                <button @click="
                        darkMode = !darkMode;
                        if (darkMode) {
                            document.documentElement.classList.add('dark');
                            localStorage.setItem('theme', 'dark');
                        } else {
                            document.documentElement.classList.remove('dark');
                            localStorage.setItem('theme', 'light');
                        }
                    " 
                    x-data="{ darkMode: document.documentElement.classList.contains('dark') }"
                    class="relative w-14 h-7 sm:w-16 sm:h-8 rounded-full bg-slate-200 dark:bg-slate-700 shadow-inner flex items-center p-1 transition-colors duration-300 border border-slate-300 dark:border-slate-600 hover:ring-2 hover:ring-brand-500/50 outline-none group shrink-0">
                    <!-- Background Track Icons -->
                    <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
                        <i class="fa-solid fa-sun text-slate-400 text-[10px] transition-opacity" :class="darkMode ? 'opacity-50' : 'opacity-100'"></i>
                        <i class="fa-solid fa-moon text-slate-400 dark:text-slate-500 text-[10px] transition-opacity" :class="darkMode ? 'opacity-100' : 'opacity-50'"></i>
                    </div>
                    <!-- Knob -->
                    <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-white dark:bg-slate-800 shadow flex items-center justify-center transform transition-transform duration-300 relative z-10 border border-slate-100 dark:border-slate-600 group-hover:shadow-md" :class="darkMode ? 'translate-x-7 sm:translate-x-8' : 'translate-x-0'">
                        <i class="fa-solid fa-sun text-amber-500 text-[10px] sm:text-[11px]" x-show="!darkMode"></i>
                        <i class="fa-solid fa-moon text-fuchsia-400 text-[10px] sm:text-[11px]" x-show="darkMode" style="display: none;"></i>
                    </div>
                </button>

                <!-- Divider -->
                <div class="hidden sm:block w-px h-8 bg-slate-200 dark:bg-slate-700 mx-1"></div>

                <!-- Profile Dropdown -->
                <div x-data="{ openProfile: false }" class="relative">
                    <button @click="openProfile = !openProfile" @click.away="openProfile = false" class="flex items-center gap-3 p-1 pr-3 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-600">
                        <img id="header_avatar_img" src="<?php echo $avatar_src; ?>" alt="Profile" class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-600 object-cover">
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-800 dark:text-white"><?php echo htmlspecialchars($nama_karyawan_display); ?></span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
                        </div>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div x-show="openProfile" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 py-2 z-50 overflow-hidden" 
                         style="display: none;">
                        
                        <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/50">
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($nama_karyawan_display); ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Karyawan</p>
                        </div>
                        
                        <div class="py-1">
                            <a href="staff_pengaturan.php" class="w-full text-left px-5 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex items-center gap-3 transition-colors">
                                <i class="fa-solid fa-user-cog w-4 text-center text-slate-400"></i> Pengaturan Akun
                            </a>
                        </div>
                        
                        <div class="border-t border-slate-100 dark:border-slate-700/50 py-1">
                            <a href="logout.php" onclick="confirmLogout(event, this.href);" class="block px-5 py-2.5 text-sm text-rose-600 dark:text-rose-400 font-medium hover:bg-rose-50 dark:hover:bg-rose-500/10 flex items-center gap-3 transition-colors">
                                <i class="fa-solid fa-right-from-bracket w-4 text-center text-rose-500 dark:text-rose-400"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Alpine.js untuk fitur interaktif (submenu dll) -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

        <!-- Modal Ganti Password Header -->
        <div id="modal-ganti-password-header" class="fixed inset-0 z-[60] hidden">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModalHeader('modal-ganti-password-header')"></div>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div class="relative bg-white dark:bg-slate-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-md w-full border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white">Ganti Password</h3>
                        <button onclick="closeModalHeader('modal-ganti-password-header')" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                    </div>
                    <form id="form-ganti-password-header" action="master_process.php" method="POST">
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="id_user" value="<?php echo $_SESSION['user_id']; ?>">
                        <div class="px-6 py-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-500 dark:text-slate-400 text-sm focus:outline-none cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password Baru <span class="text-red-500">*</span></label>
                                <input type="password" name="password" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors">
                                <p class="text-xs text-slate-500 mt-1">Minimal 6 karakter.</p>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                            <button type="button" onclick="closeModalHeader('modal-ganti-password-header')" class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Batal</button>
                            <button type="submit" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl shadow-sm shadow-purple-500/30 transition-colors text-sm font-medium">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        function openModalHeader(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        function closeModalHeader(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // Logic to open/close sidebar
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const openBtn = document.getElementById('openSidebar');
            const closeBtn = document.getElementById('closeSidebar');
            const overlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                if (window.innerWidth < 768) {
                    // Mobile
                    sidebar.classList.toggle('-translate-x-full');
                    if (overlay.classList.contains('hidden')) {
                        overlay.classList.remove('hidden');
                        setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                    } else {
                        overlay.classList.add('opacity-0');
                        setTimeout(() => overlay.classList.add('hidden'), 300);
                    }
                } else {
                    // Desktop
                    document.body.classList.toggle('sidebar-collapsed');
                }
            }

            if(openBtn) openBtn.addEventListener('click', toggleSidebar);
            if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
            if(overlay) overlay.addEventListener('click', toggleSidebar);

            // AJAX form handler for Header password change
            const formGantiPassword = document.getElementById('form-ganti-password-header');
            if (formGantiPassword) {
                formGantiPassword.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Memproses...',
                            text: 'Menyimpan password baru...',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                    }
                    
                    const formData = new FormData(this);
                    formData.append('is_ajax', '1');
                    
                    fetch(this.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (typeof Swal !== 'undefined') {
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
                        } else {
                            if (data.status === 'success') {
                                alert(data.message);
                                window.location.reload();
                            } else {
                                alert(data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Terjadi Kesalahan!',
                                text: 'Gagal terhubung ke server.'
                            });
                        } else {
                            alert('Gagal terhubung ke server.');
                        }
                    });
                });
            }
        });
        </script>

        <!-- SCROLLABLE CONTENT AREA -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 relative">
