<?php
// Membuat pasangan kunci VAPID untuk Web Push. Jalankan SEKALI per lingkungan:
//   php generate_vapid_keys.php
// lalu tempel hasilnya ke .env. Jangan ganti kunci setelah ada pengguna yang
// berlangganan - semua langganan lama akan tidak valid dan perlu diaktifkan ulang.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/assets/vendor/autoload.php';

$keys = Minishlink\WebPush\VAPID::createVapidKeys();
echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
echo "VAPID_SUBJECT=mailto:admin@domain-anda.com\n";
