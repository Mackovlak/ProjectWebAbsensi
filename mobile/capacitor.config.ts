import type { CapacitorConfig } from '@capacitor/cli';

/*
 * Cangkang native AbsenKita: aplikasi Android/iOS ini TIDAK membawa salinan
 * halaman PHP. WebView-nya langsung memuat server produksi (server.url), jadi
 * setiap perubahan di server PHP langsung berlaku tanpa rilis ulang aplikasi.
 * Rilis ulang hanya perlu bila isi folder mobile/ (izin, ikon, plugin) berubah.
 *
 * Nilai dibaca dari environment saat `npx cap sync` (lihat MOBILE.md):
 *   ABSENKITA_URL     wajib. URL server, mis. https://absen.perusahaan.co.id/
 *                     (boleh subfolder). http:// hanya untuk uji lokal, mis.
 *                     http://10.0.2.2:8080/ dari emulator Android ke Docker.
 *   ABSENKITA_APP_ID  opsional. ID paket permanen di Play Store / App Store.
 *                     Tentukan SEKALI sebelum rilis pertama; tidak bisa diganti.
 */
const url = process.env.ABSENKITA_URL;
if (!url) {
  throw new Error('ABSENKITA_URL belum diisi. Contoh: ABSENKITA_URL=https://absen.perusahaan.co.id/ npx cap sync');
}
const parsed = new URL(url);
const isHttp = parsed.protocol === 'http:';

const config: CapacitorConfig = {
  appId: process.env.ABSENKITA_APP_ID || 'com.javag.absenkita',
  appName: 'AbsenKita',
  // Hanya berisi halaman cadangan; halaman sebenarnya dari server.url.
  webDir: 'www',
  // Penanda yang dibaca server (isAplikasiNative() di remember_functions.php)
  // dan JS (pwa.js, izin-perangkat.js). Jangan diubah tanpa mengubah keduanya.
  appendUserAgent: 'AbsenKitaApp/1.0',
  server: {
    url,
    // Ditampilkan bila server tidak bisa dimuat (tidak ada internet / server mati).
    errorPath: 'offline.html',
    cleartext: isHttp,
  },
  android: {
    allowMixedContent: false,
  },
  ios: {
    contentInset: 'automatic',
    // Tautan ke domain lain (wa.me, Google Maps) dibuka di browser/aplikasi luar.
    limitsNavigationsToAppBoundDomains: false,
  },
};

export default config;
