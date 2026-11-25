/*
 * Klien PWA AbsenKita Javag: registrasi service worker, tombol install, dan
 * langganan Web Push. Dimuat dari pwa_head.php di setiap halaman.
 *
 * Kartu pengaturan (pwa_notif_card.php) memakai atribut data-pwa-*; seluruh
 * logikanya ada di sini supaya markup-nya tetap sederhana.
 */
(function () {
    'use strict';

    var scriptSrc = document.currentScript && document.currentScript.src;
    var ROOT = scriptSrc ? new URL('../../', scriptSrc) : new URL('./', location.href);
    var SW_URL = new URL('sw.js', ROOT).href;
    var ENDPOINT = new URL('push_subscribe.php', ROOT).href;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';
    var loggedIn = !!csrf;

    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var swSupported = 'serviceWorker' in navigator && window.isSecureContext;
    var pushSupported = swSupported && 'PushManager' in window && 'Notification' in window;

    var deferredInstall = null;
    var serverConfigured = null; // null = belum dicek

    function post(payload) {
        return fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json().catch(function () { return { success: false, message: 'Respons server tidak valid.' }; });
        });
    }

    function urlBase64ToUint8Array(base64) {
        var padding = '='.repeat((4 - base64.length % 4) % 4);
        var raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function getPublicKey() {
        return fetch(ENDPOINT + '?action=key', { credentials: 'same-origin' }).then(function (r) {
            if (r.status === 503) { serverConfigured = false; throw new Error('Notifikasi push belum dikonfigurasi di server.'); }
            return r.json();
        }).then(function (d) {
            if (!d.success) throw new Error(d.message || 'Gagal mengambil kunci push.');
            serverConfigured = true;
            return d.publicKey;
        });
    }

    var registrationPromise = null;
    function register() {
        if (!swSupported) return Promise.resolve(null);
        if (!registrationPromise) {
            registrationPromise = navigator.serviceWorker.register(SW_URL).then(function () {
                return navigator.serviceWorker.ready;
            }).catch(function (e) {
                console.warn('[pwa] service worker gagal didaftarkan:', e);
                return null;
            });
        }
        return registrationPromise;
    }

    function currentSubscription() {
        return register().then(function (reg) {
            return reg ? reg.pushManager.getSubscription() : null;
        });
    }

    function sendSubscription(sub) {
        var json = sub.toJSON();
        return post({ action: 'subscribe', endpoint: json.endpoint, keys: json.keys });
    }

    // ---- API publik ------------------------------------------------------

    // 'unsupported' | 'ios-install' | 'unconfigured' | 'denied' | 'off' | 'on'
    function getState() {
        if (!pushSupported) {
            return Promise.resolve(isIOS && !isStandalone ? 'ios-install' : 'unsupported');
        }
        if (isIOS && !isStandalone) return Promise.resolve('ios-install');
        if (Notification.permission === 'denied') return Promise.resolve('denied');
        var cek = (serverConfigured === null && loggedIn) ? getPublicKey().catch(function () { /* serverConfigured terisi */ }) : Promise.resolve();
        return cek.then(function () {
            return serverConfigured === false ? null : currentSubscription();
        }).then(function (sub) {
            if (serverConfigured === false) return 'unconfigured';
            return (sub && Notification.permission === 'granted') ? 'on' : 'off';
        });
    }

    function enablePush() {
        if (!loggedIn) return Promise.reject(new Error('Silakan login terlebih dahulu.'));
        // requestPermission harus dipanggil langsung dari gestur pengguna (klik), sebelum await lain.
        var permission = Notification.requestPermission();
        return Promise.resolve(permission).then(function (result) {
            if (result !== 'granted') throw new Error('Izin notifikasi tidak diberikan.');
            return Promise.all([register(), getPublicKey()]);
        }).then(function (res) {
            var reg = res[0];
            if (!reg) throw new Error('Service worker tidak tersedia di browser ini.');
            var wanted = urlBase64ToUint8Array(res[1]);
            return reg.pushManager.getSubscription().then(function (existing) {
                if (!existing) return null;
                var have = existing.options && existing.options.applicationServerKey;
                var same = have && have.byteLength === wanted.length &&
                    new Uint8Array(have).every(function (b, i) { return b === wanted[i]; });
                if (same) return existing;
                // Langganan lama dibuat dengan kunci VAPID berbeda: buang dan buat ulang.
                return existing.unsubscribe().then(function () { return null; });
            }).then(function (sub) {
                return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: wanted });
            });
        }).then(function (sub) {
            return sendSubscription(sub);
        }).then(function (d) {
            if (!d.success) throw new Error(d.message || 'Gagal menyimpan langganan.');
            notify();
        });
    }

    function disablePush() {
        return currentSubscription().then(function (sub) {
            if (!sub) return null;
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () { return post({ action: 'unsubscribe', endpoint: endpoint }); });
        }).then(function () { notify(); });
    }

    function sendTest() {
        return post({ action: 'test' });
    }

    function promptInstall() {
        if (!deferredInstall) return Promise.resolve(false);
        var evt = deferredInstall;
        deferredInstall = null;
        evt.prompt();
        return evt.userChoice.then(function (c) { notify(); return c.outcome === 'accepted'; });
    }

    function canInstall() { return !!deferredInstall && !isStandalone; }

    window.AbsenPWA = {
        getState: getState, enablePush: enablePush, disablePush: disablePush, sendTest: sendTest,
        promptInstall: promptInstall, canInstall: canInstall,
        isStandalone: isStandalone, isIOS: isIOS
    };

    function notify() { document.dispatchEvent(new CustomEvent('pwa:change')); }

    // ---- Install ---------------------------------------------------------

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstall = e;
        notify();
    });
    window.addEventListener('appinstalled', function () { deferredInstall = null; notify(); });

    // ---- Sinkronisasi & logout ---------------------------------------------

    // Satu perangkat bisa dipakai bergantian beberapa akun: tiap sesi login, klaim
    // ulang langganan ke akun yang sedang aktif (sekaligus menangkap rotasi endpoint).
    function syncOncePerSession() {
        if (!loggedIn || !pushSupported || Notification.permission !== 'granted') return;
        var flag = 'pwa-push-synced-' + csrf.slice(0, 12);
        try { if (sessionStorage.getItem(flag)) return; } catch (e) { /* abaikan */ }
        currentSubscription().then(function (sub) {
            if (!sub) return null;
            return sendSubscription(sub).then(function (d) {
                if (d.success) { try { sessionStorage.setItem(flag, '1'); } catch (e) { /* abaikan */ } }
            });
        }).catch(function () { /* sinkronisasi best-effort */ });
    }

    // Lepas langganan dari akun ini sebelum logout agar notifikasi akun lama
    // tidak muncul di perangkat yang kini dipakai orang lain.
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href*="logout.php"]');
        if (!a || !loggedIn || !pushSupported) return;
        currentSubscription().then(function (sub) {
            if (!sub) return;
            fetch(ENDPOINT, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ action: 'detach', endpoint: sub.endpoint })
            });
        });
    }, true);

    if (swSupported) {
        navigator.serviceWorker.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'push-subscription-change') {
                try { sessionStorage.removeItem('pwa-push-synced-' + csrf.slice(0, 12)); } catch (err) { /* abaikan */ }
                syncOncePerSession();
            }
        });
    }

    // ---- Kartu pengaturan ----------------------------------------------------

    var STATE_TEXT = {
        unsupported: ['Browser ini belum mendukung notifikasi push.', 'Gunakan Chrome, Edge, Firefox, atau Safari versi terbaru lewat HTTPS.'],
        'ios-install': ['Pasang aplikasi dulu di iPhone/iPad.', 'Buka menu Bagikan di Safari → "Tambah ke Layar Utama", lalu buka aplikasinya dari layar utama dan aktifkan notifikasi.'],
        unconfigured: ['Notifikasi belum dikonfigurasi di server.', 'Hubungi admin sistem (kunci VAPID belum diisi).'],
        denied: ['Notifikasi diblokir di browser ini.', 'Buka pengaturan situs di browser, ubah Notifikasi menjadi "Izinkan", lalu muat ulang halaman.'],
        off: ['Notifikasi nonaktif di perangkat ini.', 'Aktifkan untuk menerima kabar persetujuan, penugasan, dan pengingat absen.'],
        on: ['Notifikasi aktif di perangkat ini.', 'Anda akan menerima kabar terbaru meski aplikasi sedang ditutup.']
    };

    function renderCard() {
        var card = document.getElementById('pwa-notif-card');
        if (!card) return;
        var $ = function (s) { return card.querySelector(s); };

        getState().then(function (state) {
            var text = STATE_TEXT[state];
            $('[data-pwa-title]').textContent = text[0];
            $('[data-pwa-desc]').textContent = text[1];
            var dot = $('[data-pwa-dot]');
            dot.className = 'inline-block w-2.5 h-2.5 rounded-full ' + (state === 'on' ? 'bg-emerald-500' : (state === 'off' ? 'bg-amber-400' : 'bg-slate-400'));

            var toggle = $('[data-pwa-toggle]');
            var actionable = state === 'on' || state === 'off';
            toggle.classList.toggle('hidden', !actionable);
            toggle.textContent = state === 'on' ? 'Nonaktifkan' : 'Aktifkan Notifikasi';
            toggle.dataset.mode = state;

            $('[data-pwa-test]').classList.toggle('hidden', state !== 'on');
            $('[data-pwa-install]').classList.toggle('hidden', !canInstall());
        });
    }

    function bindCard() {
        var card = document.getElementById('pwa-notif-card');
        if (!card) return;
        var msg = card.querySelector('[data-pwa-msg]');
        function say(text, ok) {
            msg.textContent = text;
            msg.className = 'text-sm mt-3 ' + (ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400');
        }
        function busy(btn, on) { btn.disabled = on; btn.classList.toggle('opacity-60', on); }

        card.querySelector('[data-pwa-toggle]').addEventListener('click', function () {
            var btn = this;
            busy(btn, true);
            var job = btn.dataset.mode === 'on' ? disablePush() : enablePush();
            job.then(function () { say(btn.dataset.mode === 'on' ? 'Notifikasi dinonaktifkan.' : 'Notifikasi diaktifkan.', true); })
               .catch(function (e) { say(e.message || 'Gagal mengubah pengaturan notifikasi.', false); })
               .then(function () { busy(btn, false); renderCard(); });
        });
        card.querySelector('[data-pwa-test]').addEventListener('click', function () {
            var btn = this;
            busy(btn, true);
            sendTest().then(function (d) { say(d.message, !!d.success); })
                .catch(function () { say('Gagal mengirim notifikasi uji.', false); })
                .then(function () { busy(btn, false); });
        });
        card.querySelector('[data-pwa-install]').addEventListener('click', function () { promptInstall(); });

        document.addEventListener('pwa:change', renderCard);
        renderCard();
    }

    register().then(syncOncePerSession);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bindCard);
    else bindCard();
})();
