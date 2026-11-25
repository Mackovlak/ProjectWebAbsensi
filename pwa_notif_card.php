<?php
// Kartu "Notifikasi & Aplikasi" untuk halaman pengaturan semua peran.
// Seluruh perilakunya ada di assets/js/pwa.js (cari #pwa-notif-card).
?>
<div id="pwa-notif-card" class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 shrink-0 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
            <i class="fa-solid fa-bell text-lg"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <span data-pwa-dot class="inline-block w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                Notifikasi &amp; Aplikasi
            </h3>
            <p data-pwa-title class="text-sm font-medium text-slate-700 dark:text-slate-200 mt-1">Memeriksa status notifikasi…</p>
            <p data-pwa-desc class="text-sm text-slate-500 dark:text-slate-400 mt-0.5"></p>

            <div class="flex flex-wrap gap-2 mt-4">
                <button type="button" data-pwa-toggle class="hidden px-4 py-2 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white transition-colors"></button>
                <button type="button" data-pwa-test class="hidden px-4 py-2 rounded-xl text-sm font-semibold border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Kirim Notifikasi Uji</button>
                <button type="button" data-pwa-install class="hidden px-4 py-2 rounded-xl text-sm font-semibold border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <i class="fa-solid fa-download mr-1"></i> Pasang Aplikasi
                </button>
            </div>
            <p data-pwa-msg class="text-sm mt-3" role="status"></p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-3">Pengaturan ini berlaku per perangkat/browser. Keluar dari akun otomatis menghentikan notifikasi di perangkat tersebut.</p>
        </div>
    </div>
</div>
