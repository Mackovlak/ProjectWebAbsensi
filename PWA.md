# AbsenKita Javag sebagai PWA

Aplikasi bisa dipasang (install) seperti aplikasi native dan mengirim **notifikasi push**
walau aplikasi sedang ditutup. Absensi, slip gaji, dan pengajuan tetap **online-only** —
tidak ada antrean absensi offline maupun sinkronisasi latar belakang.

## Komponen

| File | Fungsi |
|---|---|
| `manifest.webmanifest` | Nama, start URL, scope, warna, ikon aplikasi. |
| `pwa_head.php` | Disertakan di `<head>` setiap halaman: manifest, ikon, meta mobile, meta `csrf-token` (hanya saat login), dan `assets/js/pwa.js`. |
| `sw.js` | Service worker (harus di root agar scope mencakup semua halaman): menampilkan push, menangani klik notifikasi, cache aset statis publik, halaman offline. |
| `offline.html` | Ditampilkan saat navigasi gagal karena tidak ada jaringan. |
| `assets/js/pwa.js` | Registrasi SW, tombol install, berlangganan/berhenti push, sinkron langganan per sesi login, lepas langganan saat logout. |
| `pwa_notif_card.php` | Kartu "Notifikasi & Aplikasi" di halaman Pengaturan semua peran. |
| `push_subscribe.php` | Endpoint JSON: `key`, `subscribe`, `unsubscribe`, `detach`, `test`. Wajib login + header `X-CSRF-Token`. |
| `push_functions.php` | Pengirim push (`pushAntre*`, `pushKirimKeUsers`) dan pencari penerima. Dimuat oleh `config.php`. |
| `cron_push_reminder.php` | Pengingat absen masuk/pulang (CLI, lewat cron). |
| `generate_vapid_keys.php` | Pembuat kunci VAPID (CLI). |
| `migrations/015_push_subscriptions.php` | Tabel `push_subscriptions`. |

## Setup (sekali per lingkungan)

1. `composer install --working-dir=assets` (membawa `minishlink/web-push`).
2. `php migrate.php migrate --yes` (membuat `push_subscriptions`).
3. `php generate_vapid_keys.php`, tempel tiga baris hasilnya ke `.env`
   (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`).
   **Jangan ganti kunci** setelah ada yang berlangganan — semua langganan lama menjadi tidak valid
   dan harus diaktifkan ulang oleh tiap pengguna.
4. Layani lewat **HTTPS** (di `localhost` HTTP juga boleh). Service worker, install, dan push tidak
   jalan di HTTP biasa / IP LAN.
5. Ekstensi PHP `curl`, `mbstring`, `openssl` wajib; `gmp` sangat disarankan (Docker image sudah
   memuatnya setelah `docker compose up -d --build`).
6. Nginx: `sw.js` jangan di-cache lama (lihat `DEPLOYMENT.md` §8); pastikan `webmanifest` punya MIME
   `application/manifest+json`. Apache: sudah diatur di `.htaccess`.

Tanpa kunci VAPID aplikasi tetap berjalan normal; kartu notifikasi hanya menampilkan
"belum dikonfigurasi di server".

## Notifikasi yang dikirim

| Peristiwa | Penerima | Dibuka ke |
|---|---|---|
| Pengajuan izin/cuti/sakit/dinas baru (form atau kiosk absen) | Admin + Supervisor cabang | `kelola_pengajuan_izin.php` |
| Izin disetujui / ditolak | Pengaju | `staff_pengajuan_izin.php` |
| Pengajuan lembur baru | Admin + Supervisor cabang | `kelola_pengajuan_lembur.php` |
| Lembur disetujui / ditolak | Pengaju | `staff_pengajuan_lembur.php` |
| Penugasan lembur | Karyawan yang ditugaskan | `staff_pengajuan_lembur.php` |
| Penugasan lembur diterima / ditolak | Atasan yang menugaskan | `kelola_pengajuan_lembur.php` |
| Dinas Luar dadakan menunggu ACC | Admin + Supervisor cabang | `kelola_pengajuan_izin.php` |
| Dinas Luar di-ACC / ditolak | Karyawan | beranda sesuai peran |
| Izin pulang cepat diajukan | Admin + Supervisor cabang | `kelola_pengajuan_izin.php` |
| Izin pulang cepat di-ACC / ditolak | Karyawan | beranda sesuai peran |
| Slip gaji di-ACC admin | Semua Owner | `owner_approval_gaji.php` |
| Slip gaji di-ACC owner | Karyawan pemilik slip | `staff_laporan_gaji.php` |
| Pengingat absen masuk / pulang (cron) | Karyawan yang belum absen | beranda sesuai peran |

Isi notifikasi **tidak memuat nominal gaji** karena bisa tampil di layar terkunci.

Menambah notifikasi baru: panggil `pushAntreKaryawan()`, `pushAntreApprover()`, atau `pushAntre()`
**setelah** transaksi database di-commit. Semuanya best-effort (tidak pernah melempar exception) dan
dikirim saat request selesai; di PHP-FPM respons dilepas dulu lewat `fastcgi_finish_request()`.
`url` bersifat relatif terhadap root aplikasi, sehingga aman di subdirektori.

## Perilaku penting

- **Per perangkat, bukan per akun.** Satu baris `push_subscriptions` = satu browser. Saat akun lain
  login di perangkat yang sama dan sesi itu memuat halaman, langganan dipindahkan ke akun tersebut.
  Klik logout melepas langganan perangkat dari akun yang keluar sebelum navigasi.
- Langganan yang ditolak push service (HTTP 404/410) dihapus otomatis saat pengiriman.
- Cache service worker hanya untuk aset statis publik (`assets/js/*.js`, ikon, logo, `style.css`).
  Halaman PHP, JSON, foto karyawan, dan upload **tidak pernah** di-cache. Naikkan `CACHE_VERSION`
  di `sw.js` bila daftar/strategi cache berubah.
- **iPhone/iPad**: push hanya jalan (iOS/iPadOS 16.4+) bila aplikasi sudah dipasang lewat
  Safari → Bagikan → *Tambah ke Layar Utama* dan dibuka dari layar utama. Kartu notifikasi
  menampilkan petunjuk ini otomatis.
- Pengguna mengelola notifikasi dari **Pengaturan** (Aktifkan / Nonaktifkan / Kirim Notifikasi Uji /
  Pasang Aplikasi). Belum ada preferensi per jenis notifikasi.

## Cron pengingat

```cron
45 7  * * 1-6 php /path/ke/cron_push_reminder.php masuk
15 17 * * 1-6 php /path/ke/cron_push_reminder.php pulang
```

Hanya terkirim di hari kerja non-libur ke akun aktif yang punya langganan, dan melewati karyawan
yang sudah punya baris absensi, izin/cuti/sakit disetujui, atau Dinas Luar disetujui. Tidak
bergantung pada `cron_reminder_absensi.php` (WhatsApp).

## Verifikasi

- DevTools → **Application → Manifest / Service Workers**: SW `activated`, tanpa error manifest.
- Login → Pengaturan → **Aktifkan Notifikasi** → izinkan → **Kirim Notifikasi Uji**.
- Tutup tab/aplikasi lalu kirim uji lagi; notifikasi harus tetap muncul. Klik harus membuka
  halaman tujuan.
- Matikan jaringan lalu muat ulang halaman: harus muncul `offline.html`, bukan halaman error browser.
- Ajukan izin sebagai staff → admin/supervisor cabang yang berlangganan menerima notifikasi.
- Setelah mengganti ikon/branding, pasang ulang aplikasi bila OS masih menampilkan ikon lama.
