<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - AbsenKita Javag</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-heading { font-family: 'Montserrat', sans-serif; }

        /* Slide-up animations ala PPT */
        @keyframes slideInUp {
            0% {
                opacity: 0;
                transform: translateY(40px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-slide-up-1 {
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }
        
        .animate-slide-up-2 {
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.15s forwards;
            opacity: 0;
        }
        
        .animate-slide-up-3 {
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.3s forwards;
            opacity: 0;
        }
        
        .animate-slide-up-4 {
            animation: slideInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.45s forwards;
            opacity: 0;
        }
    </style>
</head>
<body class="relative min-h-screen bg-slate-900 text-white font-sans selection:bg-fuchsia-500 selection:text-white flex flex-col">

    <!-- Background Image with Sophisticated Overlay -->
    <div class="absolute inset-0 z-0">
        <img src="assets/images/javag-team3.jpg" alt="Gedung Perusahaan" class="w-full h-full object-cover opacity-60 mix-blend-overlay" onerror="this.style.display='none'">
        <!-- Elegant gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-b from-slate-900/50 via-slate-900/80 to-slate-900"></div>
    </div>

    <!-- Header / Navbar -->
    <header class="relative z-20 w-full py-6 px-8 sm:px-12 flex items-center justify-between">
        <div class="flex items-center gap-3 bg-white px-3 py-2 rounded-xl">
            <img src="/assets/images/hp.png" alt="HP Logo" class="h-10 sm:h-12 w-auto" onerror="this.style.display='none'">
            <img src="/assets/images/logo.png" alt="Javag Logo" class="h-10 sm:h-12 w-auto" onerror="this.style.display='none'">
        </div>
    </header>

    <!-- Main Hero Content -->
    <main class="relative z-10 flex-grow flex flex-col items-center justify-center px-6 sm:px-12 text-center pb-20">
        
        <!-- Subtle Pill Badge -->
        <div class="animate-slide-up-1 inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-blue-500/30 bg-blue-500/10 text-blue-300 text-xs sm:text-sm font-medium mb-8 shadow-sm backdrop-blur-md">
            <span class="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></span>
            PT Java Abadi Gemilang
        </div>

        <!-- Main Headline (No 'Selamat Datang') -->
        <h1 class="animate-slide-up-2 font-heading text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold mb-6 tracking-tight leading-tight max-w-4xl drop-shadow-lg">
            Sistem Absensi <br class="hidden sm:block" />
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-400 to-fuchsia-500">Cerdas & Terintegrasi</span>
        </h1>
        
        <!-- Description -->
        <p class="animate-slide-up-3 text-base sm:text-lg md:text-xl text-slate-300 mb-12 leading-relaxed tracking-wide font-light max-w-2xl mx-auto drop-shadow-md">
            Solusi inovatif untuk manajemen kehadiran yang akurat dan efisien. Pantau aktivitas tim Anda secara real-time dengan teknologi modern terpercaya.
        </p>
        
        <!-- Primary Call to Action -->
        <a href="login.php" class="animate-slide-up-4 inline-flex items-center justify-center px-8 py-4 text-base sm:text-lg font-medium text-white bg-blue-600 hover:bg-cyan-500 rounded-full shadow-[0_0_20px_rgba(192,38,211,0.3)] transition-all duration-300 transform hover:-translate-y-1 hover:shadow-[0_0_30px_rgba(192,38,211,0.5)] gap-3 group">
            Masuk
            <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
            </svg>
        </a>

    </main>

    <!-- Simple Footer -->
    <footer class="relative z-10 py-2 text-center text-slate-500 text-sm">
        &copy; <?php echo date('Y'); ?> Javag Team. All rights reserved.
    </footer>

</body>
</html>

