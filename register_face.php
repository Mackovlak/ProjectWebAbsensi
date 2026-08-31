<?php
require 'config.php';
requireLogin();

$role = $_SESSION['role'] ?? '';
$attendance_roles = ['staff', 'supervisor', 'admin'];
if (!in_array($role, $attendance_roles, true)) {
    $_SESSION['error_message'] = 'Akses registrasi wajah hanya tersedia untuk Karyawan, Supervisor, dan Admin.';
    redirect(dashboardUntukRole($role));
}

$dashboard_url = dashboardUntukRole($role);
$csrf_token = generateCSRFToken();

$stmt_account = $conn->prepare("SELECT id_karyawan FROM users WHERE id = ? AND role = ?");
$stmt_account->bind_param("is", $_SESSION['user_id'], $role);
$stmt_account->execute();
$account_data = $stmt_account->get_result()->fetch_assoc();
$stmt_account->close();
$id_karyawan = $account_data['id_karyawan'] ?? '';

if ($id_karyawan === '') {
    $_SESSION['error_message'] = 'Akun Anda belum tertaut ke data karyawan, sehingga belum dapat mendaftarkan wajah untuk absensi.';
    redirect($dashboard_url);
}

// SECURITY: Check face reset permission (WITH FALLBACK)
try {
    $stmt = $conn->prepare("SELECT face_descriptor, face_registered_at, face_reset_allowed FROM users WHERE id = ? AND id_karyawan = ?");
    $stmt->bind_param("is", $_SESSION['user_id'], $id_karyawan);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    $sudah_registrasi = !empty($user_data['face_descriptor']);
    $izin_reset = isset($user_data['face_reset_allowed']) ? ($user_data['face_reset_allowed'] == 1) : true;
    $can_register = !$sudah_registrasi || ($sudah_registrasi && $izin_reset);
} catch (Exception $e) {
    $stmt = $conn->prepare("SELECT face_descriptor, face_registered_at FROM users WHERE id = ? AND id_karyawan = ?");
    $stmt->bind_param("is", $_SESSION['user_id'], $id_karyawan);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    $sudah_registrasi = !empty($user_data['face_descriptor']);
    $izin_reset = true;
    $can_register = true;
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registrasi Wajah - Absensi Javag</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#fdf4ff',
                            500: '#d946ef',
                            600: '#c026d3',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Face API -->
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js"></script>
    <script src="assets/js/face-recognition.js"></script>
    
    <style>
        .video-container {
            position: relative;
            width: 100%;
            background: #000;
            border-radius: 1rem;
            overflow: hidden;
            aspect-ratio: 4/3;
        }

        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }

        #canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            transform: scaleX(-1);
        }

        .guide-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            aspect-ratio: 1;
            border: 3px dashed rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            pointer-events: none;
            box-shadow: 0 0 0 4000px rgba(0,0,0,0.3);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 font-sans antialiased min-h-screen flex flex-col items-center justify-center p-4">

    <!-- Loading Screen -->
    <div id="loading-screen" class="fixed inset-0 bg-slate-900/90 backdrop-blur-sm z-50 hidden flex-col items-center justify-center text-white">
        <i class="fa-solid fa-circle-notch fa-spin text-5xl text-brand-500 mb-4"></i>
        <p class="text-lg font-medium">Memuat sistem face recognition...</p>
    </div>

    <div class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-3xl shadow-xl shadow-brand-500/10 border border-slate-200 dark:border-slate-700 overflow-hidden relative">
        
        <!-- Header Section -->
        <div class="bg-gradient-to-br from-brand-600 to-purple-700 p-6 sm:p-8 text-center text-white relative overflow-hidden">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiNmZmZmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNSIvPjwvc3ZnPg==')] opacity-30"></div>
            <div class="relative z-10">
                <div class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-4 border border-white/30 shadow-lg">
                    <i class="fas fa-user-shield text-2xl text-white"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold mb-1">Registrasi Wajah</h1>
                <p class="text-brand-100 text-sm">Sistem Keamanan Absensi Javag</p>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            
            <!-- STATUS BADGE -->
            <div class="flex justify-center mb-6">
                <?php if ($sudah_registrasi && !$izin_reset): ?>
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 font-semibold text-sm border border-emerald-200 dark:border-emerald-800">
                        <i class="fas fa-check-circle"></i> Wajah Sudah Terdaftar
                    </div>
                <?php elseif ($sudah_registrasi && $izin_reset): ?>
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-semibold text-sm border border-amber-200 dark:border-amber-800 animate-pulse-slow">
                        <i class="fas fa-unlock"></i> Izin Reset Diberikan
                    </div>
                <?php else: ?>
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 font-semibold text-sm border border-rose-200 dark:border-rose-800 animate-pulse-slow">
                        <i class="fas fa-exclamation-circle"></i> Belum Terdaftar
                    </div>
                <?php endif; ?>
            </div>

            <!-- ALERT BOX -->
            <div id="library-error" class="hidden mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 dark:bg-rose-900/20 dark:border-rose-800/50 dark:text-rose-400 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle mt-0.5"></i>
                <div>
                    <strong class="block font-semibold mb-1">Library Error:</strong>
                    <span id="library-error-message" class="text-sm"></span>
                </div>
            </div>

            <?php if ($sudah_registrasi && !$izin_reset): ?>
                <div class="space-y-3 mb-6">
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800/50 dark:text-emerald-400 flex items-start gap-3">
                        <i class="fas fa-check-circle mt-0.5"></i>
                        <div>
                            <strong class="block font-semibold mb-0.5">Wajah Anda sudah terdaftar!</strong>
                            <span class="text-xs opacity-80">Terdaftar pada: <?php echo date('d/m/Y H:i', strtotime($user_data['face_registered_at'])); ?></span>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-fuchsia-50 border border-fuchsia-200 text-fuchsia-800 dark:bg-fuchsia-900/20 dark:border-fuchsia-800/50 dark:text-fuchsia-400 flex items-start gap-3 text-sm">
                        <i class="fas fa-info-circle mt-0.5 text-lg"></i>
                        <div>
                            <strong class="block font-semibold mb-0.5">Ingin memperbarui data wajah?</strong>
                            <span class="opacity-80">Silakan hubungi <strong>Admin</strong> untuk membuka akses registrasi ulang.</span>
                        </div>
                    </div>
                </div>
            <?php elseif ($sudah_registrasi && $izin_reset): ?>
                <div class="p-4 mb-6 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800/50 dark:text-amber-400 flex items-start gap-3 text-sm">
                    <i class="fas fa-unlock-alt mt-0.5 text-lg"></i>
                    <div>
                        <strong class="block font-semibold mb-0.5">Izin reset diberikan!</strong>
                        <span class="opacity-80">Anda dapat mendaftarkan ulang wajah sekarang. Data lama akan ditimpa.</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- INSTRUCTIONS -->
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 sm:p-5 mb-6 border border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-info-circle text-brand-500"></i> Petunjuk Registrasi
                </h3>
                <ul class="text-sm text-slate-600 dark:text-slate-400 space-y-2 ml-1">
                    <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Pencahayaan ruangan cukup terang</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Posisikan wajah di dalam lingkaran</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Hadapkan wajah lurus ke kamera</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-check text-emerald-500 mt-1"></i> Lepas kacamata hitam dan masker</li>
                </ul>
            </div>

            <!-- CAMERA SECTION -->
            <div id="video-container" class="video-container hidden mb-6 shadow-inner">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas"></canvas>
                <div class="guide-overlay"></div>
            </div>

            <!-- PROGRESS -->
            <div id="progress-container" class="hidden mb-6">
                <div class="h-2 w-full bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div id="progress-fill" class="h-full bg-gradient-to-r from-brand-500 to-purple-500 w-0 transition-all duration-300"></div>
                </div>
            </div>

            <!-- STATUS BOX -->
            <div id="status-box" class="hidden mb-6 space-y-3 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                    <div id="status-icon-model" class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <span id="status-text-model">Memuat model AI...</span>
                </div>
                <div class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                    <div id="status-icon-camera" class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <span id="status-text-camera">Menunggu kamera...</span>
                </div>
                <div class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                    <div id="status-icon-capture" class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <span id="status-text-capture">Menunggu foto (0/5)</span>
                </div>
            </div>

            <!-- BUTTONS -->
            <div class="space-y-3">
                <?php if ($can_register): ?>
                    <button id="btn-start" onclick="startRegistration()" class="w-full py-3.5 px-4 rounded-xl font-bold text-white shadow-lg transition-transform active:scale-95 flex items-center justify-center gap-2 <?php echo ($sudah_registrasi && $izin_reset) ? 'bg-gradient-to-r from-amber-500 to-orange-500 shadow-amber-500/30' : 'bg-gradient-to-r from-brand-600 to-purple-600 shadow-brand-500/30'; ?>">
                        <i class="fas <?php echo ($sudah_registrasi && $izin_reset) ? 'fa-redo' : 'fa-camera'; ?>"></i> 
                        <?php echo ($sudah_registrasi && $izin_reset) ? 'Daftar Ulang Wajah' : 'Daftarkan Wajah Anda Sekarang'; ?>
                    </button>
                <?php else: ?>
                    <button class="w-full py-3.5 px-4 rounded-xl font-bold text-white bg-slate-400 dark:bg-slate-600 cursor-not-allowed flex items-center justify-center gap-2" disabled>
                        <i class="fas fa-lock"></i> Terkunci
                    </button>
                <?php endif; ?>

                <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="w-full py-3.5 px-4 rounded-xl font-bold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors flex items-center justify-center gap-2 block text-center">
                    <i class="fas fa-arrow-left"></i> Kembali 
                </a>
            </div>

        </div>
    </div>

    <script>
        // Check library loading
        window.addEventListener('DOMContentLoaded', function() {
            const errorDiv = document.getElementById('library-error');
            const errorMsg = document.getElementById('library-error-message');
            const btnStart = document.getElementById('btn-start');
            
            let hasError = false;
            let errors = [];
            
            if (typeof faceapi === 'undefined') {
                errors.push('Library face-api.js gagal dimuat. Periksa koneksi internet Anda.');
                hasError = true;
            }
            
            if (typeof FaceRecognitionSystem === 'undefined') {
                errors.push('File face-recognition.js tidak ditemukan. Hubungi administrator.');
                hasError = true;
            }
            
            if (hasError && btnStart && !btnStart.disabled) {
                errorMsg.innerHTML = errors.join('<br>');
                errorDiv.classList.remove('hidden');
                btnStart.disabled = true;
                btnStart.className = 'w-full py-3.5 px-4 rounded-xl font-bold text-white bg-rose-500 cursor-not-allowed flex items-center justify-center gap-2';
                btnStart.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Sistem Tidak Siap';
            }
        });

        let faceSystem = null;
        let capturedDescriptors = [];
        const REQUIRED_IMAGES = 5;

        async function startRegistration() {
            const canRegister = <?php echo $can_register ? 'true' : 'false'; ?>;
            if (!canRegister) {
                alert('❌ Anda tidak memiliki izin untuk registrasi wajah.\nHubungi administrator.');
                return;
            }

            if (typeof FaceRecognitionSystem === 'undefined') {
                alert('❌ Sistem face recognition belum siap. Refresh halaman dan coba lagi.');
                return;
            }

            try {
                document.getElementById('loading-screen').classList.remove('hidden');
                document.getElementById('loading-screen').classList.add('flex');
                document.getElementById('btn-start').disabled = true;
                document.getElementById('status-box').classList.remove('hidden');

                faceSystem = new FaceRecognitionSystem();

                updateStatus('model', 'loading', 'Memuat model AI...');
                await faceSystem.loadModels();
                updateStatus('model', 'success', 'Model AI siap');

                updateStatus('camera', 'loading', 'Mengaktifkan kamera...');
                await faceSystem.startCamera('video');
                document.getElementById('video-container').classList.remove('hidden');
                document.getElementById('progress-container').classList.remove('hidden');
                updateStatus('camera', 'success', 'Kamera aktif');

                document.getElementById('loading-screen').classList.add('hidden');
                document.getElementById('loading-screen').classList.remove('flex');

                updateStatus('capture', 'loading', 'Posisikan wajah Anda...');
                setTimeout(() => captureLoop(), 2000);

            } catch (error) {
                console.error('Error:', error);
                alert('❌ ' + error.message);
                document.getElementById('loading-screen').classList.add('hidden');
                document.getElementById('btn-start').disabled = false;
                if (faceSystem) faceSystem.stopCamera();
            }
        }

        async function captureLoop() {
            if (capturedDescriptors.length >= REQUIRED_IMAGES) {
                await saveToServer();
                return;
            }

            try {
                const result = await faceSystem.captureFaceDescriptor();
                
                const quality = faceSystem.validateFaceQuality(result);
                if (!quality.valid) {
                    updateStatus('capture', 'error', quality.message);
                    setTimeout(captureLoop, 1000);
                    return;
                }

                capturedDescriptors.push(result.descriptor);
                const progress = (capturedDescriptors.length / REQUIRED_IMAGES) * 100;
                document.getElementById('progress-fill').style.width = progress + '%';
                
                updateStatus('capture', 'success', `Foto ${capturedDescriptors.length}/${REQUIRED_IMAGES} berhasil!`);

                const canvas = document.getElementById('canvas');
                canvas.width = faceSystem.videoElement.videoWidth;
                canvas.height = faceSystem.videoElement.videoHeight;
                faceSystem.drawDetection(result, canvas);

                setTimeout(captureLoop, 1500);

            } catch (error) {
                updateStatus('capture', 'error', error.message);
                setTimeout(captureLoop, 1000);
            }
        }

        async function saveToServer() {
            try {
                updateStatus('capture', 'loading', 'Menyimpan data...');

                const formData = new FormData();
                formData.append('descriptors', JSON.stringify(capturedDescriptors));
                formData.append('csrf_token', <?php echo json_encode($csrf_token); ?>);

                const response = await fetch('process_face_register.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    updateStatus('capture', 'success', 'Registrasi berhasil!');
                    faceSystem.stopCamera();
                    
                    alert('✅ Wajah berhasil didaftarkan!\n\nAnda sekarang dapat menggunakan face recognition untuk absensi.');
                    setTimeout(() => {
                        window.location.href = <?php echo json_encode($dashboard_url); ?>;
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Gagal menyimpan data');
                }

            } catch (error) {
                alert('❌ ' + error.message);
                updateStatus('capture', 'error', 'Gagal menyimpan. Coba lagi.');
                capturedDescriptors = [];
                document.getElementById('progress-fill').style.width = '0%';
                setTimeout(captureLoop, 2000);
            }
        }

        function updateStatus(type, status, text) {
            const iconContainer = document.getElementById(`status-icon-${type}`);
            const textEl = document.getElementById(`status-text-${type}`);

            iconContainer.className = 'w-8 h-8 rounded-full flex items-center justify-center shrink-0 ' + 
                (status === 'loading' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30' : 
                 status === 'success' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30' : 
                 'bg-rose-100 text-rose-600 dark:bg-rose-900/30');
            
            if (status === 'loading') {
                iconContainer.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            } else if (status === 'success') {
                iconContainer.innerHTML = '<i class="fas fa-check"></i>';
            } else if (status === 'error') {
                iconContainer.innerHTML = '<i class="fas fa-times"></i>';
            }

            textEl.textContent = text;
        }

        window.addEventListener('beforeunload', () => {
            if (faceSystem) faceSystem.stopCamera();
        });
    </script>
</body>
</html>
