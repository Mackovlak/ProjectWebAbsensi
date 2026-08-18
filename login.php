<?php
require 'config.php';

// Initialize login attempts in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Rate limiting check
$max_attempts = 5;
$lockout_time = 900; // 15 minutes

if ($_SESSION['login_attempts'] >= $max_attempts) {
    $time_since_last = time() - $_SESSION['last_attempt_time'];
    if ($time_since_last < $lockout_time) {
        $remaining_time = ceil(($lockout_time - $time_since_last) / 60);
        $error = "Terlalu banyak percobaan login gagal. Silakan coba lagi dalam $remaining_time menit.";
    } else {
        // Reset attempts after lockout period
        $_SESSION['login_attempts'] = 0;
    }
}

// Cek jumlah admin yang sudah ada
$sql_admin_count = "SELECT COUNT(*) as total_admin FROM users WHERE role = 'admin'";
$res_admin_count = $conn->query($sql_admin_count);
$admin_count = $res_admin_count->fetch_assoc()['total_admin'];

// Jika sudah login, redirect ke dashboard yang sesuai
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($_SESSION['role'] == 'owner') {
        header("Location: owner_dashboard.php");
    } else {
        header("Location: staff_dashboard.php");
    }
    exit();
}

if (!isset($error)) {
    $error = '';
}
if (isset($_SESSION['error_message_login'])) {
    $error = $_SESSION['error_message_login'];
    unset($_SESSION['error_message_login']);
}
if (isset($_GET['message']) && $_GET['message'] == 'owner_deleted') {
    $success_message = "Akun owner dihapus. Admin dapat membuat akun owner baru.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['login_attempts'] < $max_attempts) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = $conn->real_escape_string(sanitizeInput($_POST['username']));
        $password = $_POST['password'];
        
        // Coba cari berdasarkan username (exact match)
        $sql = "SELECT u.id, u.nama, u.username, u.password, u.role, u.id_karyawan, u.jenis_kelamin, u.foto_profil, k.status as status_karyawan 
                FROM users u 
                LEFT JOIN karyawan k ON u.id_karyawan = k.id_karyawan 
                WHERE u.username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Jika tidak ditemukan, coba cari berdasarkan id_karyawan
            // Prioritaskan role 'staff' jika Karyawan memiliki lebih dari 1 akun (misal Admin & Staff)
            $sql2 = "SELECT u.id, u.nama, u.username, u.password, u.role, u.id_karyawan, u.jenis_kelamin, u.foto_profil, k.status as status_karyawan 
                    FROM users u 
                    LEFT JOIN karyawan k ON u.id_karyawan = k.id_karyawan 
                    WHERE u.id_karyawan = ?
                    ORDER BY CASE WHEN u.role = 'staff' THEN 1 ELSE 2 END LIMIT 1";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("s", $username);
            $stmt2->execute();
            $result = $stmt2->get_result();
        }
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Validasi Status Karyawan jika akun terikat pada ID Karyawan
            if (!empty($user['id_karyawan']) && $user['status_karyawan'] !== 'aktif') {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = "Akses ditolak! Akun Anda dinonaktifkan (Status Karyawan: Tidak Aktif / Resign).";
            } else if (password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['login_attempts'] = 0; // Reset attempts
                
                // Regenerate session ID for security
                regenerateSession();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['jenis_kelamin'] = $user['jenis_kelamin'] ?? 'L';
                $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;
                $_SESSION['login_time'] = time();
                
                // Set id_karyawan for staff
                if ($user['role'] == 'staff' && $user['id_karyawan']) {
                    $_SESSION['id_karyawan'] = $user['id_karyawan'];
                }
                
                // Log successful login (optional)
                $log_sql = "INSERT INTO login_logs (user_id, ip_address, login_time, status) VALUES (?, ?, NOW(), 'success')";
                $log_stmt = $conn->prepare($log_sql);
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("is", $user['id'], $ip);
                $log_stmt->execute();
                
                // Redirect berdasarkan role
                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] == 'owner') {
                    header("Location: owner_dashboard.php");
                } else {
                    header("Location: staff_dashboard.php");
                }
                exit();
            } else {
                // Password salah
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = "Username atau Password salah!";
            }
        } else {
            // TAMBAHAN: Username tidak ditemukan
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $error = "Username atau Password salah!";
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#fdf4ff', 100: '#fae8ff', 400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf', 900: '#701a75' }
                    },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    animation: {
                        blob: "blob 7s infinite",
                    },
                    keyframes: {
                        blob: {
                            "0%": { transform: "translate(0px, 0px) scale(1)" },
                            "33%": { transform: "translate(30px, -50px) scale(1.1)" },
                            "66%": { transform: "translate(-20px, 20px) scale(0.9)" },
                            "100%": { transform: "translate(0px, 0px) scale(1)" }
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        /* Loading Overlay */
        .loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        .loader {
            width: 50px;
            height: 50px;
            border: 3px solid #334155;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-white dark:bg-slate-900 min-h-screen md:h-screen flex flex-col md:flex-row transition-colors duration-300 overflow-x-hidden">
    
    <!-- Loader -->
    <div class="loader-wrapper" id="loaderWrapper">
        <div class="loader"></div>
    </div>

    <!-- Theme Toggle Saklar -->
    <button onclick="toggleTheme()" aria-label="Toggle Theme" class="absolute top-4 right-4 sm:top-6 sm:right-6 w-16 h-8 rounded-full bg-slate-200 dark:bg-slate-700 shadow-inner flex items-center p-1 transition-colors duration-300 z-50 border border-slate-300 dark:border-slate-600 hover:ring-2 hover:ring-brand-500/50 outline-none group">
        <!-- Background Track Icons -->
        <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
            <i class="fa-solid fa-sun text-slate-400 text-[10px] opacity-100 dark:opacity-50 transition-opacity"></i>
            <i class="fa-solid fa-moon text-slate-400 dark:text-slate-500 text-[10px] opacity-50 dark:opacity-100 transition-opacity"></i>
        </div>
        <!-- Knob -->
        <div class="w-6 h-6 rounded-full bg-white dark:bg-slate-800 shadow flex items-center justify-center transform transition-transform duration-300 dark:translate-x-8 relative z-10 border border-slate-100 dark:border-slate-600 group-hover:shadow-md">
            <i class="fa-solid fa-sun text-amber-500 text-[11px] dark:hidden"></i>
            <i class="fa-solid fa-moon text-fuchsia-400 text-[11px] hidden dark:block"></i>
        </div>
    </button>

    <!-- Sisi Kiri/Atas: Visual Background Foto -->
    <div class="w-full h-72 md:h-auto flex-shrink-0 md:flex-1 relative md:flex-shrink bg-cover bg-center z-0 transition-all duration-500 ease-in-out">
      
        <!-- Slideshow Layers -->
         <div id="bg-slideshow" class="absolute inset-0 z-0">
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('assets/images/javag-team.jpg');"></div>
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('assets/images/javag-team1.jpg');"></div>
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('assets/images/javag-team2.jpg');"></div>
         </div>

         <!-- Overlay gelap -->
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-slate-900/80 mix-blend-multiply transition-colors"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-slate-900/90 via-slate-900/40 to-transparent"></div>
        
        <!-- Top Left Logo -->
        <div class="absolute top-6 left-6 sm:top-10 sm:left-10 z-20 flex items-center gap-3 sm:gap-3.5">
            <div class="bg-white/90 backdrop-blur-md p-2 sm:p-2.5 rounded-2xl inline-flex shadow-lg border border-white/20">
                 <img src="assets/images/logo.png" alt="Javag Logo" class="h-8 sm:h-10 w-auto" onerror="this.style.display='none'">
            </div>
            <div class="flex flex-col justify-center -space-y-0.5">
                <h2 class="text-xl sm:text-2xl font-bold text-white tracking-tight leading-none">
                    Absen<span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-cyan-400">Kita</span>
                </h2>
                <span class="text-xs sm:text-sm text-slate-300 font-medium tracking-wide leading-none ml-0.5 pt-0.5">
                    PT JAVA ABADI GEMILANG
                </span>
            </div>
        </div>

        <!-- Center Left Headline -->
        <div class="absolute top-[55%] left-6 sm:left-10 md:left-16 -translate-y-1/2 z-20 pr-12 hidden md:block">
            <h1 class="text-4xl lg:text-5xl xl:text-6xl font-bold text-white leading-tight mb-6 tracking-tight">
                Kelola Absensi <br/>
                Lebih <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-cyan-400 to-fuchsia-500">Mudah</span> & <br/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 via-cyan-400 to-fuchsia-500">Terkontrol</span>
            </h1>
            <p class="text-slate-300 text-lg leading-relaxed max-w-md font-light">
                Solusi digital terintegrasi untuk rekapitulasi kehadiran dan pemantauan aktivitas karyawan di <br> PT JAVA ABADI GEMILANG.
            </p>
        </div>
        
        <!-- Bottom Left Location -->
        <div class="absolute bottom-6 left-6 sm:bottom-10 sm:left-10 z-20 hidden md:flex items-center gap-3 text-slate-300 text-sm font-medium">
            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center backdrop-blur-sm">
                <i class="fa-solid fa-location-dot text-brand-400"></i> 
            </div>
            PT Java Abadi Gemilang
        </div>
    </div>

   <!-- Sisi Kanan/Bawah: Form Login -->
<div id="loginPanel" class="w-full md:w-[45%] md:min-w-0 relative flex-shrink-0 transition-all duration-500 ease-in-out z-20 md:-ml-8">
    <!-- Toggle Arrow -->
    <button type="button" onclick="togglePanel()" 
        class="hidden md:flex absolute top-1/2 -left-5 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-white dark:bg-slate-800 shadow-lg border border-slate-200 dark:border-slate-700 items-center justify-center hover:scale-110 transition-transform duration-200">
        <i id="togglePanelIcon" class="fa-solid fa-chevron-left text-slate-500 dark:text-slate-300 transition-transform duration-500"></i>
    </button>

    <!-- Kotak visual form: overflow-y-auto ADA di sini, bukan di wrapper -->
    <div id="loginPanelBox" class="w-full h-full flex flex-col justify-center p-8 sm:p-12 lg:p-16 xl:p-20 bg-white dark:bg-slate-900 overflow-y-auto md:rounded-l-[2.5rem] shadow-[-15px_0_40px_rgba(0,0,0,0.15)] transition-all duration-500 ease-in-out">
        <div id="loginPanelContent" class="w-full max-w-md mx-auto mt-8 md:mt-0 transition-opacity duration-300">
            <!-- Teks Brand Baru -->
            <div class="mb-10 text-left">
                <h2 class="text-3xl sm:text-4xl font-bold text-slate-800 dark:text-white mb-3 tracking-tight">Selamat Datang</h2>
                <p class="text-slate-500 dark:text-slate-400 text-base">Silakan masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="flex items-start gap-3 p-4 mb-6 text-sm text-red-800 border border-red-300 rounded-xl bg-red-50 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800 animate-pulse">
                    <i class="fa-solid fa-circle-exclamation mt-0.5 text-lg"></i>
                    <div>
                        <span class="font-bold">Error!</span> <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="flex items-start gap-3 p-4 mb-6 text-sm text-emerald-800 border border-emerald-300 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800">
                    <i class="fa-solid fa-check-circle mt-0.5 text-lg"></i>
                    <div>
                        <span class="font-bold">Berhasil!</span> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($_SESSION['login_attempts'] >= 3 && $_SESSION['login_attempts'] < $max_attempts): ?>
                <div class="flex items-start gap-3 p-4 mb-6 text-sm text-amber-800 border border-amber-300 rounded-xl bg-amber-50 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-lg"></i>
                    <div>
                        <span class="font-bold">Peringatan!</span> Sisa <?php echo ($max_attempts - $_SESSION['login_attempts']); ?> percobaan login sebelum dikunci sementara.
                    </div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <?php $prefill_username = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : ''; ?>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">ID / Username</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                            <i class="fa-solid fa-user text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" name="username" id="username" value="<?php echo $prefill_username; ?>" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-brand-500 focus:bg-white dark:focus:bg-slate-800 transition-all duration-200" placeholder="Masukkan ID / Username" required autocomplete="username" <?php echo empty($prefill_username) ? 'autofocus' : ''; ?>>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Password</label>
                    </div>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                            <i class="fa-solid fa-lock text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="password" id="password" name="password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-brand-500 focus:bg-white dark:focus:bg-slate-800 transition-all duration-200" placeholder="••••••••" required autocomplete="current-password" <?php echo !empty($prefill_username) ? 'autofocus' : ''; ?>>
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

               <button type="submit" 
                    class="w-full py-4 px-4 mt-2 bg-gradient-to-r from-blue-600 to-slate-900 disabled:from-slate-400 disabled:to-slate-500 disabled:cursor-not-allowed text-white font-bold rounded-2xl shadow-lg shadow-blue-500/30 hover:shadow-xl hover:shadow-blue-500/50 transform hover:-translate-y-1 transition-all duration-300 ease-out flex items-center justify-center gap-2"
                    <?php echo ($_SESSION['login_attempts'] >= $max_attempts) ? 'disabled' : ''; ?>>
                    Masuk <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
            </form>

            <?php if ($admin_count < 1): ?>
                <div class="mt-8">
                    <a href="buat_akun.php" class="w-full py-3 px-4 flex items-center justify-center gap-2 text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-800 bg-brand-50 dark:bg-brand-900/30 hover:bg-brand-100 dark:hover:bg-brand-900/50 rounded-2xl font-semibold transition-colors">
                        <i class="fas fa-user-plus"></i> Buat Master Admin
                    </a>
                </div>
            <?php endif; ?>

            <div class="mt-10 text-center text-xs font-medium text-slate-400 dark:text-slate-500">
                <p>&copy; <?php echo date('Y'); ?> Absensi PT Javag Team. </p>
            </div>
        </div><!-- /#loginPanelContent -->
    </div>
</div>

<script>
    // Loader
    window.addEventListener('load', function() {
        const loader = document.getElementById('loaderWrapper');
        if (loader) {
            setTimeout(function() {
                loader.style.opacity = '0';
                setTimeout(function() {
                    loader.style.display = 'none';
                }, 300);
            }, 500);
        }
    });

    // Theme Toggle Logic
    function setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
    }

    function toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            setTheme('light');
        } else {
            setTheme('dark');
        }
    }

    // Panel Toggle Logic
   // Panel Toggle Logic
function togglePanel() {
    const panel = document.getElementById('loginPanel');       
    const box = document.getElementById('loginPanelBox');    
    const content = document.getElementById('loginPanelContent');
    const icon = document.getElementById('togglePanelIcon');
    const isOpen = !panel.classList.contains('panel-closed');

    if (isOpen) {
        // Tutup
        panel.classList.add('panel-closed');
        panel.classList.remove('md:w-[45%]', 'md:-ml-8');
        panel.classList.add('md:!w-0', '!ml-0');

        box.classList.remove('p-8', 'sm:p-12', 'lg:p-16', 'xl:p-20');
        box.classList.add('!p-0', '!shadow-none');

        content.classList.add('opacity-0', 'pointer-events-none');
        icon.style.transform = 'rotate(180deg)';
    } else {
        // Buka
        panel.classList.remove('panel-closed', 'md:!w-0', '!ml-0');
        panel.classList.add('md:w-[45%]', 'md:-ml-8');

        box.classList.remove('!p-0', '!shadow-none');
        box.classList.add('p-8', 'sm:p-12', 'lg:p-16', 'xl:p-20');

        content.classList.remove('opacity-0', 'pointer-events-none');
        icon.style.transform = 'rotate(0deg)';
    }
}
    // Initialize Theme from LocalStorage or OS Preference
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        setTheme('dark');
    } else {
        setTheme('light');
    }

    // Toggle Password Visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
            
            // Auto hide again after 3 seconds
            setTimeout(() => {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }, 3000);
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }

    // Form Submission Logic
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        if(!btn.disabled) {
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
            btn.disabled = true;
            this.submit(); // Submit the form
        }
    });

    // Slideshow Background Logic
    document.addEventListener('DOMContentLoaded', function() {
        const slides = document.querySelectorAll('#bg-slideshow > div');
        let currentSlide = 0;
        const totalSlides = slides.length;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.style.opacity = (i === index) ? '1' : '0';
                slide.style.transition = 'opacity 1s ease-in-out';
            });
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }

        // Initialize first slide
        showSlide(currentSlide);
        setInterval(nextSlide, 5000); // Change slide every 5 seconds
    });
</script>
</body>
</html>
