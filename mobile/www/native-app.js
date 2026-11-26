/*
 * Penyesuaian halaman saat dibuka di aplikasi Android (cangkang Capacitor).
 * Disuntikkan oleh MainActivity.java ke SETIAP halaman server (document-start,
 * sebelum skrip halaman berjalan, hanya untuk origin server.url), termasuk
 * halaman cetak/export yang tidak memuat pwa_head.php. Karena itu file ini ada
 * di aplikasi (mobile/www), bukan di server: mengubahnya butuh rilis aplikasi.
 *
 * WebView native tidak menangani beberapa hal yang di browser "gratis":
 *  1. Unduhan: form export (POST, .xlsx) dan tautan data:/blob: (PNG ID card,
 *     CSV) tidak melakukan apa-apa. Di sini file diambil lewat fetch (cookie
 *     sesi ikut) lalu diserahkan ke native untuk disimpan di Download/AbsenKita.
 *  2. window.print(): diteruskan ke dialog cetak/Simpan PDF Android.
 *  3. window.open / target=_blank ke situs sendiri: dibuka di tampilan yang
 *     sama. Browser luar tidak punya sesi login aplikasi.
 *
 * Jembatan native: window.AbsenKitaAndroid.postMessage(JSON) dari
 * WebViewCompat.addWebMessageListener (MainActivity.java), yang oleh WebView
 * hanya dipasang untuk origin server. Pesan: {aksi:'simpan', data (base64),
 * nama, mime} | {aksi:'cetak', judul} | {aksi:'pesan', teks}.
 * Di iOS jembatan ini belum ada, jadi skrip tidak mengubah apa pun di sana.
 */
(function () {
    'use strict';

    var jembatan = window.AbsenKitaAndroid;
    if (!jembatan || window.AbsenKitaNative) return;
    function kirim(pesan) { jembatan.postMessage(JSON.stringify(pesan)); }
    var A = {
        simpanFile: function (data, nama, mime) { kirim({ aksi: 'simpan', data: data, nama: nama, mime: mime }); },
        cetak: function (judul) { kirim({ aksi: 'cetak', judul: judul }); },
        pesan: function (teks) { kirim({ aksi: 'pesan', teks: teks }); }
    };

    function sameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; } catch (e) { return false; }
    }

    function namaDariHeader(res, cadangan) {
        var cd = res.headers.get('Content-Disposition') || '';
        var m = /filename\*=UTF-8''([^;]+)/i.exec(cd) || /filename="?([^";]+)"?/i.exec(cd);
        if (m) { try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; } }
        return cadangan;
    }

    function simpanBlob(blob, nama) {
        return new Promise(function (resolve, reject) {
            var r = new FileReader();
            r.onload = function () {
                var s = String(r.result);
                A.simpanFile(s.slice(s.indexOf(',') + 1), nama || 'unduhan', blob.type || 'application/octet-stream');
                resolve();
            };
            r.onerror = function () { reject(r.error); };
            r.readAsDataURL(blob);
        });
    }

    function gagal(e) {
        console.error('[native-app] unduhan gagal', e);
        A.pesan('Gagal mengunduh file. Periksa koneksi lalu coba lagi.');
    }

    // Dipanggil juga dari DownloadListener native untuk unduhan GET biasa.
    function unduh(url, opsi, namaCadangan) {
        return fetch(url, Object.assign({ credentials: 'same-origin' }, opsi || {})).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var nama = namaDariHeader(res, namaCadangan || decodeURIComponent(new URL(url, location.href).pathname.split('/').pop()) || 'unduhan');
            return res.blob().then(function (b) { return simpanBlob(b, nama); });
        });
    }

    window.AbsenKitaNative = { unduh: function (url) { unduh(url).catch(gagal); } };

    // ---- 1a. Form export (POST) ------------------------------------------------
    var EXPORT = /\/export_[a-z_]+\.php/i;

    function kirimExport(form, submitter) {
        var fd = new FormData(form);
        if (submitter && submitter.name) fd.append(submitter.name, submitter.value);
        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        var url = form.action;
        var opsi = { method: method };
        if (method === 'POST') opsi.body = fd;
        else url += (url.indexOf('?') === -1 ? '?' : '&') + new URLSearchParams(fd).toString();

        A.pesan('Menyiapkan file...');
        fetch(url, Object.assign({ credentials: 'same-origin' }, opsi)).then(function (res) {
            var cd = res.headers.get('Content-Disposition') || '';
            if (res.ok && /attachment/i.test(cd)) {
                return res.blob().then(function (b) { return simpanBlob(b, namaDariHeader(res, 'export')); });
            }
            // Bukan file (mis. format PDF = halaman cetak HTML): buka seperti biasa.
            form.target = '_self';
            asliSubmit.call(form);
        }).catch(gagal);
    }

    var asliSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (EXPORT.test(this.action)) { kirimExport(this); return; }
        return asliSubmit.call(this);
    };
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form || !EXPORT.test(form.action)) return;
        e.preventDefault();
        kirimExport(form, e.submitter);
    });

    // ---- 1b. Tautan <a download> data:/blob: ------------------------------------
    // Banyak halaman membuat <a> lalu memanggil .click() tanpa memasangnya ke DOM,
    // jadi yang dibungkus adalah HTMLAnchorElement.prototype.click.
    function tanganiAnchor(a) {
        if (!a || !a.hasAttribute('download')) return false;
        var href = a.href || '';
        var nama = a.getAttribute('download') || 'unduhan';
        if (/^(data|blob):/i.test(href)) {
            fetch(href).then(function (r) { return r.blob(); }).then(function (b) { return simpanBlob(b, nama); }).catch(gagal);
            return true;
        }
        if (sameOrigin(href)) { unduh(href, null, nama).catch(gagal); return true; }
        return false;
    }
    var asliClick = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function () {
        if (tanganiAnchor(this)) return;
        return asliClick.call(this);
    };
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a');
        if (!a || e.defaultPrevented) return;
        if (tanganiAnchor(a)) { e.preventDefault(); return; }
        // 3. target=_blank ke situs sendiri: tetap di aplikasi.
        if (a.target === '_blank' && sameOrigin(a.href)) { e.preventDefault(); location.href = a.href; }
    });

    // ---- 2. Cetak -----------------------------------------------------------------
    window.print = function () { A.cetak(document.title || 'AbsenKita'); };

    // ---- 3. window.open -------------------------------------------------------------
    var asliOpen = window.open;
    window.open = function (url) {
        if (url && sameOrigin(url)) { location.href = new URL(url, location.href).href; return window; }
        return asliOpen.apply(window, arguments);
    };
})();
