<?php
require 'config.php';

// $secret_salt = "DINIA_SUPERVISOR_SECRET_2026";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id_karyawan = trim($_POST['id_karyawan'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $kode_rahasia = strtoupper(trim($_POST['kode_rahasia'] ?? ''));

    
    // $current_token = substr(strtoupper(md5(date('Y-m-d H') . $secret_salt)), 0, 6);
    // $previous_token = substr(strtoupper(md5(date('Y-m-d H', strtotime('-1 hour')) . $secret_salt)), 0, 6);

    if ($id_karyawan === '' || $username === '' || $password === '' || $confirm_password === '' || $kode_rahasia === '') {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
        exit();
    }
    if (strlen($username) < 4 || preg_match('/\s/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username minimal 4 karakter dan tidak boleh mengandung spasi.']);
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
    // if (!hash_equals($current_token, $kode_rahasia) && !hash_equals($previous_token, $kode_rahasia)) {
    //     echo json_encode(['success' => false, 'message' => 'Kode Rahasia tidak valid atau sudah kedaluwarsa.']);
    //     exit();
    // }
    if(!verifyRegistrationToken('supervisor', $kode_rahasia)){
        echo json_encode([
            'success' => false,
            'message' => 'Kode rahasia tidak valid atau sudah kedaluwarsa.'
        ]);
        exit();   
    }

    // Data identitas dan cakupan cabang wajib berasal dari data karyawan aktif.
    $stmt_karyawan = $conn->prepare("SELECT k.nama_karyawan, k.jenis_kelamin, k.id_cabang
                                     FROM karyawan k
                                     LEFT JOIN users u ON u.id_karyawan = k.id_karyawan AND u.role = 'supervisor'
                                     WHERE k.id_karyawan = ? AND k.status = 'aktif' AND u.id IS NULL");
    $stmt_karyawan->bind_param('s', $id_karyawan);
    $stmt_karyawan->execute();
    $data_karyawan = $stmt_karyawan->get_result()->fetch_assoc();
    $stmt_karyawan->close();
    if (!$data_karyawan || empty($data_karyawan['id_cabang'])) {
        echo json_encode(['success' => false, 'message' => 'Karyawan tidak tersedia, sudah memiliki akun Supervisor, atau belum memiliki cabang.']);
        exit();
    }
    $nama = $data_karyawan['nama_karyawan'];
    $jenis_kelamin = in_array($data_karyawan['jenis_kelamin'], ['L', 'P'], true) ? $data_karyawan['jenis_kelamin'] : 'L';
    $id_cabang = (int) $data_karyawan['id_cabang'];

    $stmt_check = $conn->prepare('SELECT id FROM users WHERE username = ?');
    $stmt_check->bind_param('s', $username);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan, silakan pilih yang lain.']);
        exit();
    }
    $stmt_check->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'supervisor';
    $stmt_insert = $conn->prepare('INSERT INTO users (nama, username, password, role, jenis_kelamin, id_karyawan, id_cabang) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt_insert->bind_param('ssssssi', $nama, $username, $hashed_password, $role, $jenis_kelamin, $id_karyawan, $id_cabang);

    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Akun Supervisor berhasil dibuat.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat membuat akun Supervisor.']);
    }
    $stmt_insert->close();
    exit();
}

$res_karyawan = $conn->query("SELECT k.id_karyawan, k.nama_karyawan, c.nama_cabang
                              FROM karyawan k
                              LEFT JOIN cabang c ON c.id = k.id_cabang
                              LEFT JOIN users u ON u.id_karyawan = k.id_karyawan AND u.role = 'supervisor'
                              WHERE k.status = 'aktif' AND u.id IS NULL
                              ORDER BY k.nama_karyawan ASC");
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pendaftaran Supervisor - Absensi Javag</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { brand: { 50: '#f0fdfa', 100: '#ccfbf1', 400: '#2dd4bf', 500: '#14b8a6', 600: '#0d9488', 700: '#0f766e', 900: '#134e4a' } },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }</style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 min-h-screen flex items-center justify-center p-4 sm:p-6 transition-colors duration-300">
    <button type="button" onclick="toggleTheme()" aria-label="Ganti tema" class="fixed top-4 right-4 sm:top-6 sm:right-6 w-16 h-8 rounded-full bg-slate-200 dark:bg-slate-700 shadow-inner flex items-center p-1 transition-colors duration-300 z-50 border border-slate-300 dark:border-slate-600 hover:ring-2 hover:ring-brand-500/50 outline-none group">
        <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
            <i class="fa-solid fa-sun text-slate-400 text-[10px]"></i>
            <i class="fa-solid fa-moon text-slate-400 text-[10px]"></i>
        </div>
        <div class="w-6 h-6 rounded-full bg-white dark:bg-slate-800 shadow flex items-center justify-center transform transition-transform duration-300 dark:translate-x-8 relative z-10 border border-slate-100 dark:border-slate-600">
            <i class="fa-solid fa-sun text-amber-500 text-[11px] dark:hidden"></i>
            <i class="fa-solid fa-moon text-teal-400 text-[11px] hidden dark:block"></i>
        </div>
    </button>

    <main class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl border border-slate-100 dark:border-slate-700/50 overflow-hidden">
        <div class="p-6 pb-0 sm:p-8 sm:pb-0 text-center">
            <div class="w-20 h-20 bg-gradient-to-br from-teal-500 to-emerald-600 text-white rounded-3xl flex items-center justify-center text-3xl shadow-lg shadow-teal-500/30 mx-auto mb-5">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Registrasi Supervisor Baru</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm px-4">Pilih data karyawan Anda. Cabang Supervisor akan otomatis mengikuti cabang karyawan.</p>
        </div>

        <div class="p-6 sm:p-8">
            <form id="registerSupervisorForm" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="id_karyawan">Data Karyawan</label>
                    <div class="relative group">
                        <i class="fa-solid fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        <select id="id_karyawan" name="id_karyawan" required class="block w-full pl-11 pr-10 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 appearance-none">
                            <option value="">-- Pilih Data Karyawan --</option>
                            <?php if ($res_karyawan): while ($karyawan = $res_karyawan->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($karyawan['id_karyawan']); ?>"><?php echo htmlspecialchars($karyawan['nama_karyawan'] . ' — ' . ($karyawan['nama_cabang'] ?? 'Cabang belum diset')); ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Nama, gender, dan cabang diambil langsung dari biodata karyawan.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="username">Username</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="username" name="username" required minlength="4" pattern="[^\s]+" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Minimal 4 karakter, tanpa spasi">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="password">Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" id="password" name="password" required minlength="8" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Minimal 8 karakter">
                        <button type="button" onclick="togglePassword('password', 'eyePassword')" aria-label="Tampilkan password" class="absolute inset-y-0 right-0 px-4 flex items-center text-slate-400 hover:text-brand-600 focus:outline-none">
                            <i id="eyePassword" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="confirmPassword">Konfirmasi Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="password" id="confirmPassword" name="confirm_password" required minlength="8" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500" placeholder="Ketik ulang password">
                        <button type="button" onclick="togglePassword('confirmPassword', 'eyeConfirmPassword')" aria-label="Tampilkan konfirmasi password" class="absolute inset-y-0 right-0 px-4 flex items-center text-slate-400 hover:text-brand-600 focus:outline-none">
                            <i id="eyeConfirmPassword" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-700 border-dashed pt-5">
                    <label class="block text-sm font-bold text-slate-800 dark:text-white mb-2" for="kode_rahasia">Kode Rahasia Keamanan</label>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Masukkan kode 6 karakter yang diberikan Admin. Kode berubah setiap jam.</p>
                    <div class="relative">
                        <i class="fa-solid fa-shield-halved absolute left-4 top-1/2 -translate-y-1/2 text-teal-500"></i>
                        <input type="text" id="kode_rahasia" name="kode_rahasia" required maxlength="6" autocomplete="off" class="block w-full pl-11 pr-4 py-4 bg-teal-50 dark:bg-teal-900/20 border-2 border-teal-200 dark:border-teal-800/50 rounded-2xl text-slate-800 dark:text-white focus:outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-500/20 font-mono text-center tracking-widest text-lg font-bold uppercase" placeholder="XXXXXX">
                    </div>
                </div>

                <button type="submit" id="btnSubmit" class="w-full py-4 mt-6 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white font-bold rounded-2xl shadow-xl shadow-teal-600/20 transform hover:-translate-y-1 transition-all duration-200 flex items-center justify-center gap-2">
                    Buat Akun Sekarang <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="mt-8 text-center pb-2">
                <a href="login.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> Batal &amp; Kembali ke Login
                </a>
            </div>
        </div>
    </main>

    <script>
        function setTheme(theme) {
            document.documentElement.classList.toggle('dark', theme === 'dark');
            localStorage.setItem('theme', theme);
        }
        function toggleTheme() {
            setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
        }
        setTheme(localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');

        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        }

        document.getElementById('registerSupervisorForm').addEventListener('submit', async function (event) {
            event.preventDefault();
            const password = document.getElementById('password').value;
            const confirmation = document.getElementById('confirmPassword').value;
            if (password !== confirmation) {
                await Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Password dan konfirmasi password tidak sama.' });
                document.getElementById('confirmPassword').focus();
                return;
            }

            const button = document.getElementById('btnSubmit');
            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memproses...';

            try {
                const response = await fetch('daftar_supervisor.php', { method: 'POST', body: new FormData(this) });
                const data = await response.json();
                if (!data.success) throw new Error(data.message || 'Pendaftaran gagal.');
                await Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, confirmButtonColor: '#0d9488' });
                window.location.href = 'login.php';
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Pendaftaran Gagal', text: error.message || 'Tidak dapat terhubung ke server.' });
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    </script>
</body>
</html>
