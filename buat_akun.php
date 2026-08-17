<?php
require 'config.php';

// Cek jumlah admin
$sql_admin_count = "SELECT COUNT(*) as total_admin FROM users WHERE role = 'admin'";
$res_admin_count = $conn->query($sql_admin_count);
$admin_count = $res_admin_count->fetch_assoc()['total_admin'];

// Jika sudah ada 1 admin atau lebih, redirect
if ($admin_count >= 1) {
    $_SESSION['error_message_login'] = "Pendaftaran Master Admin sudah ditutup. Admin tambahan hanya bisa dibuat dari dalam sistem.";
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = $conn->real_escape_string($_POST['nama']);
    $username = $conn->real_escape_string($_POST['username']);
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin'] ?? 'L');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($nama) || empty($username) || empty($password) || empty($jenis_kelamin)) {
        $error = "Semua field harus diisi.";
    } else if (strlen($username) < 4) {
        $error = "Username minimal 4 karakter.";
    } else if (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } else if ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak sama.";
    } else {
        // Cek username sudah ada atau belum
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "Username sudah digunakan, silakan pilih yang lain.";
        } else {
            // Double check jumlah admin sebelum insert
            $res_admin_count = $conn->query($sql_admin_count);
            $admin_count = $res_admin_count->fetch_assoc()['total_admin'];
            
            if ($admin_count >= 2) {
                $error = "Pendaftaran admin sudah penuh.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'admin'; 
                
                // Insert dengan field 'nama' dan 'jenis_kelamin'
                $sql_insert = "INSERT INTO users (nama, username, password, role, jenis_kelamin) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("sssss", $nama, $username, $hashed_password, $role, $jenis_kelamin);
                
                if ($stmt_insert->execute()) {
                    $success = "Akun admin berhasil dibuat! Mengalihkan...";
                    // Redirect setelah 2 detik
                    header("refresh:2;url=login.php");
                } else {
                    $error = "Terjadi kesalahan: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun Admin - Absensi Javag</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#fdf4ff', 100: '#fae8ff', 400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf', 900: '#701a75' }
                    },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
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
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex items-center justify-center p-4 transition-colors duration-300">
    
    <!-- Loader -->
    <div class="loader-wrapper" id="loaderWrapper">
        <div class="loader"></div>
    </div>

    <!-- Theme Toggle Saklar -->
    <button onclick="toggleTheme()" aria-label="Toggle Theme" class="absolute top-4 right-4 sm:top-6 sm:right-6 w-16 h-8 rounded-full bg-slate-200 dark:bg-slate-700 shadow-inner flex items-center p-1 transition-colors duration-300 z-50 border border-slate-300 dark:border-slate-600 hover:ring-2 hover:ring-brand-500/50 outline-none group">
        <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
            <i class="fa-solid fa-sun text-slate-400 text-[10px] opacity-100 dark:opacity-50 transition-opacity"></i>
            <i class="fa-solid fa-moon text-slate-400 dark:text-slate-500 text-[10px] opacity-50 dark:opacity-100 transition-opacity"></i>
        </div>
        <div class="w-6 h-6 rounded-full bg-white dark:bg-slate-800 shadow flex items-center justify-center transform transition-transform duration-300 dark:translate-x-8 relative z-10 border border-slate-100 dark:border-slate-600 group-hover:shadow-md">
            <i class="fa-solid fa-sun text-amber-500 text-[11px] dark:hidden"></i>
            <i class="fa-solid fa-moon text-fuchsia-400 text-[11px] hidden dark:block"></i>
        </div>
    </button>

    <div class="w-full max-w-xl bg-white dark:bg-slate-800 rounded-3xl shadow-xl border border-slate-100 dark:border-slate-700 overflow-hidden relative z-10">
        
        <div class="p-8 sm:p-10 relative">
            <!-- Teks Brand -->
            <div class="mb-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-brand-500 to-purple-600 text-white rounded-2xl flex items-center justify-center text-2xl shadow-lg shadow-brand-500/30 mx-auto mb-4">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Buat Akun Admin</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm">
                    Slot tersedia: <span class="font-bold text-brand-500"><?php echo (2 - $admin_count); ?></span> dari 2 akun admin
                </p>
            </div>

            <!-- Pesan Error / Sukses -->
            <?php if (!empty($error)): ?>
                <div class="flex items-start gap-3 p-4 mb-6 text-sm text-red-800 border border-red-300 rounded-xl bg-red-50 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800 animate-pulse">
                    <i class="fa-solid fa-circle-exclamation mt-0.5 text-lg"></i>
                    <div><span class="font-bold">Error!</span> <?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="flex items-start gap-3 p-4 mb-6 text-sm text-emerald-800 border border-emerald-300 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800">
                    <i class="fa-solid fa-check-circle mt-0.5 text-lg"></i>
                    <div><span class="font-bold">Berhasil!</span> <?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php else: ?>

            <!-- Form -->
            <form action="buat_akun.php" method="POST" id="registerForm" class="space-y-5">
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nama Lengkap</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                            <i class="fa-solid fa-address-card text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" name="nama" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="Masukkan nama lengkap..." required autofocus>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Username</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                                <i class="fa-solid fa-user text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <input type="text" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="Username (min. 4)" minlength="4" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Jenis Kelamin</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                                <i class="fa-solid fa-venus-mars text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <select name="jenis_kelamin" required class="block w-full pl-11 pr-10 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all appearance-none cursor-pointer">
                                <option value="L" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'P') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-chevron-down text-sm text-slate-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Password</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                                <i class="fa-solid fa-lock text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <input type="password" id="password" name="password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="••••••••" required minlength="8">
                            <button type="button" onclick="togglePassword('password', 'eyeIcon1')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none">
                                <i class="fa-solid fa-eye" id="eyeIcon1"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Konfirmasi Password</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                                <i class="fa-solid fa-lock text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <input type="password" id="confirmPassword" name="confirm_password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="••••••••" required>
                            <button type="button" onclick="togglePassword('confirmPassword', 'eyeIcon2')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none">
                                <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" class="w-full py-4 px-4 mt-4 bg-gradient-to-r from-brand-600 to-brand-500 hover:from-brand-700 hover:to-brand-600 text-white font-bold rounded-2xl shadow-lg shadow-brand-500/30 transform hover:-translate-y-1 transition-all duration-200 flex items-center justify-center gap-2">
                    Daftar Admin <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-8 text-center">
                <a href="login.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Halaman Login
                </a>
            </div>
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

        // Theme Toggle
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
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            setTheme('dark');
        } else {
            setTheme('light');
        }

        // Form Validation
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const pwd = document.getElementById('password').value;
                const confirmPwd = document.getElementById('confirmPassword').value;
                if (pwd !== confirmPwd) {
                    e.preventDefault();
                    alert('Password dan Konfirmasi Password tidak sama!');
                    document.getElementById('confirmPassword').focus();
                    return;
                }
                const btn = this.querySelector('button[type="submit"]');
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
                btn.disabled = true;
            });
        }

        // Toggle Password
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
    </script>
</body>
</html>
