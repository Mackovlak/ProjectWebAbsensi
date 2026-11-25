/*
 * Service worker AbsenKita Javag.
 *
 * Tugasnya sengaja sempit:
 *  1. Web Push: menampilkan notifikasi & membuka halaman yang relevan saat diklik.
 *  2. Mempercepat aset statis publik (ikon, JS, CSS) - stale-while-revalidate.
 *  3. Halaman offline yang rapi saat navigasi gagal karena tidak ada jaringan.
 *
 * Yang SENGAJA TIDAK di-cache: halaman PHP (berisi data sesi/gaji/absensi), respons
 * JSON, foto karyawan & upload, dan semua request non-GET. Absensi tetap online-only.
 *
 * File ini harus berada di root aplikasi supaya scope-nya mencakup semua halaman.
 * Naikkan CACHE_VERSION bila daftar PRECACHE atau strategi berubah.
 */
const CACHE_VERSION = 'v1';
const CACHE_NAME = 'absenkita-' + CACHE_VERSION;
const OFFLINE_URL = 'offline.html';
const PRECACHE = [
    OFFLINE_URL,
    'assets/images/logo.png',
    'assets/images/icons/icon-192.png',
    'assets/images/icons/icon-512.png',
];

// Hanya aset statis publik ini yang boleh masuk cache.
const CACHEABLE = [
    /assets\/js\/[^/]+\.js$/,
    /assets\/images\/icons\/[^/]+\.png$/,
    /assets\/images\/logo\.png$/,
    /style\.css$/,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('absenkita-') && k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Navigasi: selalu ke jaringan; cache HANYA dipakai sebagai halaman offline.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match(OFFLINE_URL).then((res) => res || Response.error()))
        );
        return;
    }

    if (CACHEABLE.some((re) => re.test(url.pathname))) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(req).then((cached) => {
                    const refresh = fetch(req).then((res) => {
                        if (res.ok) cache.put(req, res.clone());
                        return res;
                    }).catch(() => cached);
                    return cached || refresh;
                })
            )
        );
    }
});

// ---------------------------------------------------------------- Web Push

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'AbsenKita Javag';
    const options = {
        body: data.body || '',
        icon: 'assets/images/icons/icon-192.png',
        badge: 'assets/images/icons/icon-192.png',
        data: { url: data.url || './' },
        lang: 'id',
    };
    // Tag yang sama menggantikan notifikasi lama (mis. pengingat berulang) dan tetap berbunyi.
    if (data.tag) {
        options.tag = data.tag;
        options.renotify = true;
    }
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || './', self.registration.scope).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if (client.url.startsWith(self.registration.scope) && 'focus' in client) {
                    return client.focus().then((c) => (c && 'navigate' in c ? c.navigate(target) : c));
                }
            }
            return self.clients.openWindow(target);
        })
    );
});

// Browser merotasi langganan: beri tahu halaman terbuka agar langsung menyinkronkan ulang.
// (Bila tidak ada halaman terbuka, pwa.js menyinkronkan saat halaman berikutnya dimuat.)
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            windows.forEach((c) => c.postMessage({ type: 'push-subscription-change' }));
        })
    );
});
