/*
 * Izin perangkat (kamera & lokasi) untuk browser, PWA terpasang, dan aplikasi
 * Android/iOS (cangkang Capacitor di mobile/). Dimuat dari pwa_head.php.
 *
 *   AbsenIzin.platform            'android-app' | 'ios-app' | 'ios-web' | 'android-web' | 'desktop'
 *   AbsenIzin.ambilLokasi(opsi)   Promise<{latitude, longitude, accuracy}>; error.kode =
 *                                 'denied' | 'unavailable' | 'timeout' | 'imprecise'
 *   AbsenIzin.cekIzin(jenis)      Promise<'granted'|'denied'|'prompt'|'unknown'>, jenis 'kamera'|'lokasi'
 *   AbsenIzin.panduan(jenis)      Teks langkah mengizinkan ulang sesuai platform.
 *   AbsenIzin.errorKamera(err)    Error getUserMedia -> Error berpesan jelas (err.izin = 'kamera').
 *
 * Di aplikasi native, lokasi lewat plugin @capacitor/geolocation bila terpasang
 * (izin OS sekali, tanpa prompt situs tambahan di iOS); kamera tetap lewat
 * getUserMedia, izinnya diteruskan ke izin OS oleh Capacitor.
 */
(function () {
    'use strict';

    var ua = navigator.userAgent;
    var isApp = ua.indexOf('AbsenKitaApp/') !== -1;
    var isIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var isAndroid = /android/i.test(ua);

    var platform = isApp ? (isIOS ? 'ios-app' : 'android-app')
        : isIOS ? 'ios-web'
        : isAndroid ? 'android-web'
        : 'desktop';

    var NAMA = { kamera: 'Kamera', lokasi: 'Lokasi' };

    function panduan(jenis) {
        var n = NAMA[jenis] || jenis;
        switch (platform) {
            case 'android-app':
                return jenis === 'lokasi'
                    ? 'Buka Pengaturan HP > Aplikasi > AbsenKita > Izin > Lokasi, pilih "Izinkan hanya saat aplikasi digunakan" dan aktifkan "Gunakan lokasi akurat". Pastikan GPS/Lokasi HP menyala, lalu kembali ke aplikasi.'
                    : 'Buka Pengaturan HP > Aplikasi > AbsenKita > Izin > Kamera, pilih "Izinkan hanya saat aplikasi digunakan", lalu kembali ke aplikasi.';
            case 'ios-app':
                return jenis === 'lokasi'
                    ? 'Buka Pengaturan iPhone > AbsenKita > Lokasi, pilih "Saat Menggunakan App" dan aktifkan "Lokasi Akurat". Pastikan Pengaturan > Privasi & Keamanan > Layanan Lokasi menyala.'
                    : 'Buka Pengaturan iPhone > AbsenKita, lalu aktifkan Kamera.';
            case 'ios-web':
                return jenis === 'lokasi'
                    ? 'Buka Pengaturan iPhone > Privasi & Keamanan > Layanan Lokasi: pastikan menyala dan "Situs Web Safari" = "Saat Menggunakan App". Lalu Pengaturan > Safari > Lokasi = "Tanya" atau "Izinkan". Muat ulang halaman.'
                    : 'Buka Pengaturan iPhone > Safari > Kamera = "Tanya" atau "Izinkan" (atau di Safari ketuk "aA" > Pengaturan Situs Web > Kamera). Muat ulang halaman.';
            case 'android-web':
                return 'Ketuk ikon di sebelah kiri alamat situs > Izin (Setelan situs) > ' + n + ' > Izinkan, lalu muat ulang halaman. Bila masih gagal: Pengaturan HP > Aplikasi > Chrome > Izin > ' + n + ' > Izinkan.'
                    + (jenis === 'lokasi' ? ' Pastikan GPS/Lokasi HP menyala.' : '');
            default:
                return 'Klik ikon gembok/pengaturan di sebelah kiri alamat situs, izinkan ' + n + ', lalu muat ulang halaman.';
        }
    }

    function errLokasi(kode, pesan) {
        var e = new Error(pesan);
        e.kode = kode;
        e.izin = 'lokasi';
        return e;
    }

    function plugin(nama) {
        var C = window.Capacitor;
        if (!isApp || !C || !C.isPluginAvailable || !C.isPluginAvailable(nama)) return null;
        return C.Plugins && C.Plugins[nama] || null;
    }

    function lokasiNative(G, opsi) {
        return G.checkPermissions().then(function (st) {
            if (st.location === 'granted' || st.coarseLocation === 'granted') return st;
            return G.requestPermissions({ permissions: ['location', 'coarseLocation'] });
        }).then(function (st) {
            if (st.location !== 'granted') {
                if (st.coarseLocation === 'granted') {
                    throw errLokasi('imprecise', 'Hanya lokasi perkiraan yang diizinkan. Absen membutuhkan lokasi akurat.');
                }
                throw errLokasi('denied', 'Izin lokasi ditolak.');
            }
            return G.getCurrentPosition(opsi);
        }).then(function (pos) {
            return { latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy };
        }, function (e) {
            if (e && e.kode) throw e;
            var m = String(e && e.message || '');
            if (/denied|permission/i.test(m)) throw errLokasi('denied', 'Izin lokasi ditolak.');
            if (/time ?out/i.test(m)) throw errLokasi('timeout', 'Waktu mendeteksi lokasi habis.');
            throw errLokasi('unavailable', 'Lokasi tidak tersedia. Pastikan GPS/Lokasi HP menyala.');
        });
    }

    function lokasiWeb(opsi) {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(errLokasi('unavailable', 'Browser ini tidak mendukung deteksi lokasi.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(function (pos) {
                resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy });
            }, function (e) {
                if (e.code === e.PERMISSION_DENIED) reject(errLokasi('denied', 'Izin lokasi ditolak.'));
                else if (e.code === e.TIMEOUT) reject(errLokasi('timeout', 'Waktu mendeteksi lokasi habis.'));
                else reject(errLokasi('unavailable', 'Lokasi tidak tersedia. Pastikan GPS/Lokasi HP menyala.'));
            }, opsi);
        });
    }

    function ambilLokasi(opsi) {
        opsi = opsi || { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 };
        var G = plugin('Geolocation');
        return G ? lokasiNative(G, opsi) : lokasiWeb(opsi);
    }

    function cekIzin(jenis) {
        var G = jenis === 'lokasi' ? plugin('Geolocation') : null;
        if (G) {
            return G.checkPermissions().then(function (st) {
                return st.location === 'granted' ? 'granted' : (st.location === 'denied' ? 'denied' : 'prompt');
            }).catch(function () { return 'unknown'; });
        }
        if (!navigator.permissions || !navigator.permissions.query) return Promise.resolve('unknown');
        var name = jenis === 'lokasi' ? 'geolocation' : 'camera';
        return navigator.permissions.query({ name: name })
            .then(function (r) { return r.state; })
            .catch(function () { return 'unknown'; }); // mis. Firefox belum kenal 'camera'
    }

    function errorKamera(err) {
        var name = err && err.name;
        var pesan;
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
            pesan = 'Izin kamera ditolak. ' + panduan('kamera');
        } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') {
            pesan = 'Kamera depan tidak ditemukan di perangkat ini.';
        } else if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError') {
            pesan = 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi kamera/video call lalu coba lagi.';
        } else if (!window.isSecureContext) {
            pesan = 'Kamera hanya bisa dipakai lewat HTTPS. Hubungi admin sistem.';
        } else {
            pesan = 'Gagal mengakses kamera. Pastikan izin kamera diberikan. ' + panduan('kamera');
        }
        var e = new Error(pesan);
        e.izin = 'kamera';
        e.cause = err;
        return e;
    }

    window.AbsenIzin = {
        platform: platform,
        isApp: isApp,
        ambilLokasi: ambilLokasi,
        cekIzin: cekIzin,
        panduan: panduan,
        errorKamera: errorKamera
    };
})();
