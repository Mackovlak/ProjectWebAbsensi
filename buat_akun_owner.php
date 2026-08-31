<?php
require 'config.php';

// $secret_salt = "DINIA_OWNER_SECRET_2026";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    
    $nama = $conn->real_escape_string($_POST['nama']);
    $username = $conn->real_escape_string($_POST['username']);
    $jenis_kelamin = $conn->real_escape_string($_POST['jenis_kelamin'] ?? 'L');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $kode_rahasia = strtoupper(trim($_POST['kode_rahasia'] ?? ''));
    
    // Dynamic Token Calculation
    // $current_token = substr(strtoupper(md5(date('Y-m-d H') . $secret_salt)), 0, 6);
    // $previous_token = substr(strtoupper(md5(date('Y-m-d H', strtotime('-1 hour')) . $secret_salt)), 0, 6);
    
    if (empty($nama) || empty($username) || empty($password) || empty($jenis_kelamin) || empty($kode_rahasia)) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
        exit();
    }
    
    if (strlen($username) < 4) {
        echo json_encode(['success' => false, 'message' => 'Username minimal 4 karakter.']);
        exit();
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password minimal 8 karakter.']);
        exit();
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Password dan konfirmasi password tidak sama.']);
        exit();
    }
    
    // Validate Secret Token
    // if ($kode_rahasia !== $current_token && $kode_rahasia !== $previous_token) {
    //     echo json_encode(['success' => false, 'message' => 'Kode Rahasia tidak valid atau sudah kedaluwarsa.']);
    //     exit();
    // }
    if(!verifyRegistrationToken('owner', $kode_rahasia)){
        echo json_encode([
            'success' => false,
            'message' => 'Kode rahasia tidak valid atau sudah kedaluwarsa.'
        ]);
        exit();   
    }
    
    // Cek username
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan, silakan pilih yang lain.']);
        exit();
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'owner'; 
    
    $sql_insert = "INSERT INTO users (nama, username, password, role, jenis_kelamin) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("sssss", $nama, $username, $hashed_password, $role, $jenis_kelamin);
    
    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Selamat! Akun Owner berhasil dibuat. Silakan login.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $conn->error]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pendaftaran Owner - Absensi Javag</title>
    
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
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <style> 
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; } 
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex items-center justify-center p-4 sm:p-6 transition-colors duration-300">
    
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

    <div class="w-full max-w-md bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl border border-slate-100 dark:border-slate-700/50 overflow-hidden relative z-10">
        
        <!-- Header -->
        <div class="p-6 pb-0 sm:p-8 sm:pb-0 relative text-center">
            <div class="w-20 h-20 bg-gradient-to-br from-amber-400 to-orange-500 text-white rounded-3xl flex items-center justify-center text-3xl shadow-lg shadow-orange-500/30 mx-auto mb-5 rotate-3 hover:rotate-0 transition-transform">
                <i class="fa-solid fa-crown"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Registrasi Owner</h2>
            <p class="text-slate-500 dark:text-slate-400 text-sm px-4">
                Silakan isi data diri Anda dan masukkan kode rahasia yang diberikan oleh Admin.
            </p>
        </div>

        <div class="p-6 sm:p-8">
            <!-- Form -->
            <form id="registerOwnerForm" class="space-y-5">
                
                <!-- Nama -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Nama Lengkap</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                            <i class="fa-solid fa-id-card text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" name="nama" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="John Doe" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Username</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                                <i class="fa-solid fa-user text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <input type="text" name="username" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="user123" minlength="4" required>
                        </div>
                    </div>
                    
                    <!-- Jenis Kelamin -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Gender</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                                <i class="fa-solid fa-venus-mars text-slate-400 dark:text-slate-500"></i>
                            </div>
                            <select name="jenis_kelamin" required class="block w-full pl-10 pr-8 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all appearance-none cursor-pointer">
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-chevron-down text-xs text-slate-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Password Baru</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                            <i class="fa-solid fa-lock text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="password" id="password" name="password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="Min. 8 karakter" required minlength="8">
                        <button type="button" onclick="togglePassword('password', 'eyeIcon1')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none">
                            <i class="fa-solid fa-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                </div>

                <!-- Konfirmasi Password -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Konfirmasi Password</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-brand-500">
                            <i class="fa-solid fa-lock-alert text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="password" id="confirmPassword" name="confirm_password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition-all" placeholder="Ketik ulang password" required>
                        <button type="button" onclick="togglePassword('confirmPassword', 'eyeIcon2')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 focus:outline-none">
                            <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                        </button>
                    </div>
                </div>

                <div class="my-6 border-t border-slate-200 dark:border-slate-700 border-dashed"></div>

                <!-- Kode Rahasia -->
                <div>
                    <label class="block text-sm font-bold text-slate-800 dark:text-white mb-2">Kode Rahasia Keamanan</label>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Masukkan 6 digit kode yang diberikan oleh admin (berubah setiap jam).</p>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-orange-500">
                            <i class="fa-solid fa-shield-halved text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" name="kode_rahasia" class="block w-full pl-11 pr-4 py-4 bg-orange-50 dark:bg-orange-900/20 border-2 border-orange-200 dark:border-orange-800/50 rounded-2xl text-slate-800 dark:text-white placeholder-orange-300 dark:placeholder-orange-700 focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/20 font-mono text-center tracking-widest text-lg font-bold uppercase transition-all" placeholder="XXXXXX" required maxlength="6" autocomplete="off">
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" id="btnSubmit" class="w-full py-4 mt-6 bg-gradient-to-r from-slate-800 to-slate-900 hover:from-slate-900 hover:to-black dark:from-slate-700 dark:to-slate-600 text-white font-bold rounded-2xl shadow-xl shadow-slate-900/20 transform hover:-translate-y-1 transition-all duration-200 flex items-center justify-center gap-2">
                    Buat Akun Sekarang <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="mt-8 text-center pb-2">
                <a href="login.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> Batal & Kembali ke Login
                </a>
            </div>
        </div>
    </div>

    <script>
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

        // AJAX Form Submission
        document.getElementById('registerOwnerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const pwd = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirmPassword').value;
            
            if (pwd !== confirmPwd) {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Password dan Konfirmasi Password tidak sama!',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            const btn = document.getElementById('btnSubmit');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';
            btn.disabled = true;

            const formData = new FormData(this);

            fetch('buat_akun_owner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#10b981',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message,
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan pada server.',
                    confirmButtonColor: '#ef4444'
                });
            });
        });
    </script>
</body>
</html>
