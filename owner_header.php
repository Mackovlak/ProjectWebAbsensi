<?php
// Pastikan session_start() ada di file config.php dan dipanggil sebelum output apapun.
require_once 'config.php';

// Pengecekan akses Owner yang lebih aman
if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    // Jika session tidak ada atau role tidak sesuai, alihkan ke halaman login.
    header("Location: login.php");
    exit();
}

$owner_jk = $_SESSION['jenis_kelamin'] ?? 'L';
if (isset($_SESSION['foto_profil']) && !empty($_SESSION['foto_profil'])) {
    $avatar_src = 'assets/uploads/' . $_SESSION['foto_profil'];
} else {
    $avatar_src = ($owner_jk == 'P') ? 'assets/images/avatar_p.png?v=2' : 'assets/images/avatar_l.png?v=2';
}
$avatar_bg = ($owner_jk == 'P') ? 'bg-pink-100' : 'bg-fuchsia-100';

// --- Notifikasi Admin/Owner ---
$sql_dinas = "SELECT a.id, a.tanggal, a.alasan, k.nama_karyawan 
              FROM absensi a 
              JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
              WHERE a.keterangan = 'Pending Dinas' 
              ORDER BY a.tanggal DESC";
$result_dinas = $conn->query($sql_dinas);
$notif_dinas = [];
if ($result_dinas) {
    while($row = $result_dinas->fetch_assoc()) {
        $notif_dinas[] = $row;
    }
}

$sql_izin = "SELECT a.id, a.tanggal, a.keterangan, a.alasan, k.nama_karyawan 
             FROM absensi a 
             JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
             WHERE a.keterangan IN ('Sakit', 'Cuti') 
             AND a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
             ORDER BY a.tanggal DESC";
$result_izin = $conn->query($sql_izin);
$notif_izin = [];
if ($result_izin) {
    while($row = $result_izin->fetch_assoc()) {
        $notif_izin[] = $row;
    }
}

$sql_acc = "SELECT COUNT(s.id) as total_acc, GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') as admin_names 
            FROM slip_gaji s 
            JOIN karyawan k ON s.id_karyawan = k.id_karyawan 
            LEFT JOIN users u ON s.admin_id = u.id 
            WHERE k.status = 'aktif' AND s.status_admin_acc = 1 AND s.status_owner_acc = 0";
$result_acc = $conn->query($sql_acc);
$total_acc_gaji = 0;
$admin_names = 'Admin';
if ($result_acc) {
    $row = $result_acc->fetch_assoc();
    $total_acc_gaji = $row['total_acc'];
    if (!empty($row['admin_names'])) {
        $admin_names = $row['admin_names'];
    }
}

// Pengajuan izin/cuti/dinas luar yang menunggu review (semua cabang)
$notif_pengajuan_izin = [];
$res_pengajuan_izin = $conn->query("SELECT p.id, p.jenis, p.tanggal_mulai, p.tanggal_selesai, p.keperluan,
                                           p.jumlah_hari_kerja, k.nama_karyawan
                                    FROM pengajuan_izin p
                                    JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                                    WHERE p.status = 'Pending'
                                    ORDER BY p.created_at ASC
                                    LIMIT 20");
if ($res_pengajuan_izin) {
    while ($row = $res_pengajuan_izin->fetch_assoc()) {
        $notif_pengajuan_izin[] = $row;
    }
}
$pending_izin_count = hitungPendingIzin($conn);

$actionable_notif_count = count($notif_dinas) + $total_acc_gaji + $pending_izin_count;
$total_notif = count($notif_dinas) + count($notif_izin) + ($total_acc_gaji > 0 ? 1 : 0) + $pending_izin_count;
$is_admin_for_notif = false;
// ------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <title>Dashboard Owner - Absensi Javag</title>
    
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
                <img src="Javag-Logo.png" alt="Javag Logo" class="h-8 w-auto" onerror="this.style.display='none'">
            </div>
            <div class="flex flex-col justify-center space-y-0.5 logo-text-container">
                <h2 class="text-2xl font-bold tracking-tight text-white leading-none">
                    Absen<span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-amber-200">Kita</span>
                </h2>
                <span class="text-xs text-slate-300 font-medium tracking-wide leading-none ml-0.5 pt-0.5">
                    Java Abadi Gemilang
                </span>
            </div>

        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-6 space-y-2 no-scrollbar">
            <p class="px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 mt-2">Menu Owner</p>
            
            <a href="owner_dashboard.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'owner_dashboard.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-squares-four text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'owner_dashboard.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Dashboard</span>
            </a>

            <a href="owner_cabang.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'owner_cabang.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-buildings text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'owner_cabang.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Data Cabang</span>
            </a>

            <a href="owner_data_karyawan.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'owner_data_karyawan.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-users-three text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'owner_data_karyawan.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Data Karyawan</span>
            </a>

            <a href="owner_rekap_absensi.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'owner_rekap_absensi.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-calendar-check text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'owner_rekap_absensi.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Rekap Absensi</span>
            </a>

            <a href="kelola_pengajuan_izin.php" class="flex items-center justify-between px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'kelola_pengajuan_izin.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <div class="flex items-center gap-3">
                    <i class="ph-duotone ph-clipboard-text text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'kelola_pengajuan_izin.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                    <span class="font-medium text-sm">Pengajuan Izin</span>
                </div>
                <?php if ($pending_izin_count > 0): ?>
                    <span class="pulse-badge inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold bg-rose-500/20 text-rose-400 rounded-full border border-rose-500/30 shadow-sm shadow-rose-500/20">
                        <?php echo $pending_izin_count; ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="owner_approval_gaji.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'owner_approval_gaji.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-signature text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'owner_approval_gaji.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Persetujuan Gaji</span>
            </a>

            <a href="laporan.php" class="flex items-center gap-3 px-4 py-3 mx-4 <?php echo basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/20' : 'text-white hover:bg-[#1e293b]'; ?> rounded-xl transition-all duration-300 group">
                <i class="ph-duotone ph-chart-pie-slice text-xl w-6 flex items-center justify-center <?php echo basename($_SERVER['PHP_SELF']) == 'laporan.php' ? '' : 'opacity-70 group-hover:opacity-100 transition-opacity'; ?>"></i>
                <span class="font-medium text-sm">Pusat Laporan</span>
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

                
                <!-- Notification Bell -->
                <div x-data="{ 
                    openNotif: false, 
                    actionableCount: <?php echo $actionable_notif_count; ?>,
                    hasInfo: <?php echo count($notif_izin) > 0 ? 'true' : 'false'; ?>,
                    get showBadge() {
                        if (this.actionableCount > 0) return true;
                        return this.hasInfo && !sessionStorage.getItem('notif_opened');
                    }
                }" class="relative">
                    <button @click="openNotif = !openNotif; if(openNotif) { sessionStorage.setItem('notif_opened', 'true'); }" @click.away="openNotif = false" class="relative w-10 h-10 flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700/50 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-600">
                        <i class="fa-regular fa-bell text-lg"></i>
                        <span x-show="showBadge" style="display: none;" class="absolute top-1.5 right-1.5 flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                        </span>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div x-show="openNotif" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="absolute right-0 mt-2 w-80 sm:w-96 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 py-2 z-50 overflow-hidden" 
                         style="display: none;">
                        
                        <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/50 flex justify-between items-center bg-white dark:bg-slate-800 sticky top-0 z-10">
                            <p class="text-sm font-bold text-slate-800 dark:text-white">Menunggu Persetujuan</p>
                            <span class="text-xs font-semibold bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 px-2.5 py-0.5 rounded-full"><?php echo $total_notif; ?> Baru</span>
                        </div>
                        
                        <div class="max-h-[60vh] overflow-y-auto no-scrollbar relative">
                            <?php if ($total_notif == 0): ?>
                            <div class="py-8 text-center px-4">
                                <div class="w-12 h-12 rounded-full bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center mx-auto mb-3 border border-slate-100 dark:border-slate-700">
                                    <i class="fa-solid fa-check text-slate-300 dark:text-slate-500 text-xl"></i>
                                </div>
                                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Semua pengajuan sudah beres!</p>
                                <p class="text-xs text-slate-400 mt-1">Tidak ada tugas ACC saat ini.</p>
                            </div>
                            <?php else: ?>
                                <!-- ACC Gaji (Owner Only) -->
                                <?php if ($total_acc_gaji > 0): ?>
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-800/50 border-y border-slate-100 dark:border-slate-700/50 sticky top-0 z-10 backdrop-blur-sm">
                                    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Persetujuan Gaji</p>
                                </div>
                                <div class="p-4 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1">
                                            <p class="text-sm font-bold text-slate-800 dark:text-white">Ada <?php echo $total_acc_gaji; ?> Slip Gaji Menunggu ACC</p>
                                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1"><?php echo htmlspecialchars($admin_names); ?> telah memeriksa slip gaji dan menunggu pengesahan Anda.</p>
                                        </div>
                                        <div class="flex flex-col shrink-0">
                                            <a href="owner_approval_gaji.php" class="px-3 py-1.5 bg-brand-50 text-brand-600 hover:bg-brand-500 hover:text-white dark:bg-brand-500/10 dark:hover:bg-brand-500 dark:text-brand-400 dark:hover:text-white border border-brand-200 dark:border-brand-800/50 rounded-lg text-xs font-bold transition-colors text-center">Lihat</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($pending_izin_count > 0): ?>
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-800/50 border-y border-slate-100 dark:border-slate-700/50 sticky top-0 z-10 backdrop-blur-sm">
                                    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Pengajuan Izin &amp; Cuti Menunggu Review</p>
                                </div>
                                <?php foreach ($notif_pengajuan_izin as $np): ?>
                                <a href="kelola_pengajuan_izin.php?status=Pending" class="block p-4 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate"><?php echo htmlspecialchars($np['nama_karyawan']); ?></p>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full border font-bold shrink-0 <?php echo badgeJenisIzin($np['jenis']); ?>"><?php echo htmlspecialchars($np['jenis']); ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2"><?php echo htmlspecialchars($np['keperluan']); ?></p>
                                    <p class="text-[10px] font-medium text-slate-400 mt-2">
                                        <i class="fa-regular fa-calendar mr-1"></i>
                                        <?php echo formatRentangTanggal($np['tanggal_mulai'], $np['tanggal_selesai']); ?>
                                        &middot; <?php echo (int)$np['jumlah_hari_kerja']; ?> hari kerja
                                    </p>
                                </a>
                                <?php endforeach; endif; ?>

                                <!-- Loop Pending Dinas -->
                                <?php if (count($notif_dinas) > 0): ?>
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-800/50 border-y border-slate-100 dark:border-slate-700/50 sticky top-0 z-10 backdrop-blur-sm">
                                    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Pending Dinas Luar</p>
                                </div>
                                <?php foreach ($notif_dinas as $nd): ?>
                                <div class="p-4 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1">
                                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($nd['nama_karyawan']); ?></p>
                                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 line-clamp-2"><?php echo htmlspecialchars($nd['alasan']); ?></p>
                                            <p class="text-[10px] font-medium text-slate-400 mt-2"><i class="fa-regular fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($nd['tanggal'])); ?></p>
                                        </div>
                                        <?php if ($is_admin_for_notif): ?>
                                        <div class="flex flex-col gap-1.5 shrink-0 w-20">
                                            <form action="proses_persetujuan_dinas.php" method="POST" class="w-full">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="id_absensi" value="<?php echo $nd['id']; ?>">
                                                <input type="hidden" name="action" value="acc">
                                                <input type="hidden" name="redirect_url" value="<?php echo basename($_SERVER['PHP_SELF']); ?>">
                                                <button type="submit" class="w-full px-2 py-1.5 bg-emerald-50 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:bg-emerald-500/10 dark:hover:bg-emerald-500 dark:text-emerald-400 dark:hover:text-white border border-emerald-200 dark:border-emerald-800/50 rounded-lg text-xs font-bold transition-colors">ACC</button>
                                            </form>
                                            <form action="proses_persetujuan_dinas.php" method="POST" class="w-full">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                                                <input type="hidden" name="id_absensi" value="<?php echo $nd['id']; ?>">
                                                <input type="hidden" name="action" value="tolak">
                                                <input type="hidden" name="redirect_url" value="<?php echo basename($_SERVER['PHP_SELF']); ?>">
                                                <button type="submit" class="w-full px-2 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white dark:bg-rose-500/10 dark:hover:bg-rose-500 dark:text-rose-400 dark:hover:text-white border border-rose-200 dark:border-rose-800/50 rounded-lg text-xs font-bold transition-colors">Tolak</button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>

                                <!-- Loop Sakit / Cuti -->
                                <?php if (count($notif_izin) > 0): ?>
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-800/50 border-y border-slate-100 dark:border-slate-700/50 mt-2 sticky top-0 z-10 backdrop-blur-sm">
                                    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Informasi Sakit & Cuti (2 Hari Terakhir)</p>
                                </div>
                                <?php foreach ($notif_izin as $ni): 
                                    $badgeColor = $ni['keterangan'] == 'Sakit' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800/50' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800/50';
                                ?>
                                <div class="p-4 border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate"><?php echo htmlspecialchars($ni['nama_karyawan']); ?></p>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full border font-bold shrink-0 <?php echo $badgeColor; ?>"><?php echo $ni['keterangan']; ?></span>
                                        </div>
                                        <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2"><?php echo htmlspecialchars($ni['alasan'] ?? '-'); ?></p>
                                        <p class="text-[10px] font-medium text-slate-400 mt-1"><i class="fa-regular fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($ni['tanggal'])); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="hidden sm:block w-px h-8 bg-slate-200 dark:bg-slate-700 mx-1"></div>

                <!-- Profile Dropdown -->
                <div x-data="{ openProfile: false }" class="relative">
                    <button @click="openProfile = !openProfile" @click.away="openProfile = false" class="flex items-center gap-3 p-1 pr-3 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors border border-transparent hover:border-slate-200 dark:hover:border-slate-600">
                        <img src="<?php echo $avatar_src; ?>" alt="Profile" class="w-9 h-9 rounded-full border border-slate-200 dark:border-slate-600 object-cover">
                        <div class="hidden sm:flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-800 dark:text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
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
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Owner</p>
                        </div>
                        
                        <div class="py-1">
                            <button onclick="openModalHeader('modal-ganti-password-header')" class="w-full text-left px-5 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex items-center gap-3 transition-colors">
                                <i class="fa-solid fa-key w-4 text-center text-slate-400"></i> Ganti Password
                            </button>
                            <a href="owner_pengaturan.php" class="block px-5 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex items-center gap-3 transition-colors">
                                <i class="fa-solid fa-user-gear w-4 text-center text-slate-400"></i> Pengaturan Akun
                            </a>
                            <a href="owner_hapus_akun.php" onclick="confirmDeleteAkunOwnerHeader(event, this.href)" class="block w-full text-left px-5 py-2.5 text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 flex items-center gap-3 transition-colors">
                                <i class="fa-solid fa-user-xmark w-4 text-center text-rose-400"></i> Hapus Akun Saya
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
                            <div x-data="{ showPass: false }">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password Baru <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showPass ? 'text' : 'password'" name="password" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors pr-10">
                                    <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                        <i class="fa-solid fa-eye" x-show="!showPass"></i>
                                        <i class="fa-solid fa-eye-slash" x-show="showPass" style="display: none;"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Minimal 6 karakter.</p>
                            </div>
                            <div x-data="{ showPassConf: false }">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showPassConf ? 'text' : 'password'" name="confirm_password" required minlength="6" class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded-xl text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition-colors pr-10">
                                    <button type="button" @click="showPassConf = !showPassConf" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                        <i class="fa-solid fa-eye" x-show="!showPassConf"></i>
                                        <i class="fa-solid fa-eye-slash" x-show="showPassConf" style="display: none;"></i>
                                    </button>
                                </div>
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

        function confirmDeleteAkunOwnerHeader(event, url) {
            event.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Akun Owner?',
                    text: 'PERINGATAN: Akun owner Anda akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e11d48',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Ya, Hapus Permanen',
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
                        window.location.href = url;
                    }
                });
            } else {
                if (confirm('PERINGATAN: Akun owner Anda akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?')) {
                    window.location.href = url;
                }
            }
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
                    
                    const pass = this.querySelector('input[name="password"]').value;
                    const passConf = this.querySelector('input[name="confirm_password"]').value;
                    
                    if (pass !== passConf) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'Password dan Konfirmasi Password tidak cocok!'
                            });
                        } else {
                            alert('Password dan Konfirmasi Password tidak cocok!');
                        }
                        return;
                    }
                    
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
