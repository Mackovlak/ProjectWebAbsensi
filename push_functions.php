<?php
/**
 * Web Push (notifikasi PWA) - helper pengiriman.
 *
 * Semua fungsi di sini "best effort": kegagalan push TIDAK boleh menggagalkan
 * aksi bisnis yang memicunya (persetujuan izin, ACC slip gaji, dst). Karena itu
 * tidak ada yang melempar exception ke pemanggil; masalah hanya dicatat lewat
 * error_log().
 *
 * Pengiriman diantrekan (pushAntre*) dan baru dikirim saat request selesai
 * (shutdown). Di PHP-FPM respons ke browser dilepas dulu lewat
 * fastcgi_finish_request(), jadi pengguna tidak menunggu push service.
 *
 * Kunci VAPID dibaca dari .env: VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY,
 * VAPID_SUBJECT. Buat dengan `php generate_vapid_keys.php`.
 */

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function pushKonfigurasi(): ?array {
    $public  = trim((string)($_ENV['VAPID_PUBLIC_KEY'] ?? ''));
    $private = trim((string)($_ENV['VAPID_PRIVATE_KEY'] ?? ''));
    if ($public === '' || $private === '' || !class_exists(WebPush::class)) {
        return null;
    }
    return [
        'public'  => $public,
        'private' => $private,
        'subject' => trim((string)($_ENV['VAPID_SUBJECT'] ?? '')) ?: 'mailto:admin@example.com',
    ];
}

function pushTersedia(): bool {
    return pushKonfigurasi() !== null;
}

/** Antrean payload: [ [user_id, payload], ... ] */
function &pushAntrean(): array {
    static $antrean = [];
    return $antrean;
}

/**
 * Antrekan notifikasi untuk satu/lebih user_id (users.id).
 *
 * @param array $payload  title, body, url (relatif terhadap root aplikasi), tag
 */
function pushAntre(mysqli $conn, array $user_ids, array $payload): void {
    if (!pushTersedia()) {
        return;
    }
    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    if (!$user_ids) {
        return;
    }

    $antrean = &pushAntrean();
    $perlu_daftar = empty($antrean);
    $antrean[] = [$user_ids, $payload];

    if ($perlu_daftar) {
        // $conn tetap hidup saat shutdown function berjalan.
        register_shutdown_function('pushFlushAntrean', $conn);
    }
}

function pushFlushAntrean(mysqli $conn): void {
    $antrean = &pushAntrean();
    if (!$antrean) {
        return;
    }
    $items = $antrean;
    $antrean = [];

    // Lepas respons ke browser lebih dulu (PHP-FPM). Di mod_php fungsi ini tidak
    // ada - pengiriman tetap jalan, hanya respons yang ikut menunggu.
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    ignore_user_abort(true);

    try {
        foreach ($items as [$user_ids, $payload]) {
            pushKirimKeUsers($conn, $user_ids, $payload);
        }
    } catch (Throwable $e) {
        error_log('[push] flush gagal: ' . $e->getMessage());
    }
}

/**
 * Kirim langsung (sinkron). Mengembalikan jumlah perangkat yang berhasil.
 * Langganan yang ditolak push service (404/410 = kedaluwarsa/dicabut) dihapus.
 */
function pushKirimKeUsers(mysqli $conn, array $user_ids, array $payload): int {
    $config = pushKonfigurasi();
    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    if (!$config || !$user_ids) {
        return 0;
    }

    $in = implode(',', array_fill(0, count($user_ids), '?'));
    $stmt = $conn->prepare("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id IN ($in)");
    if (!$stmt) {
        error_log('[push] query langganan gagal: ' . $conn->error);
        return 0;
    }
    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    $stmt->execute();
    $langganan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (!$langganan) {
        return 0;
    }

    // Teks dari sanitizeInput() sudah ter-HTML-escape; notifikasi itu teks polos.
    $dekode = fn($t) => html_entity_decode((string)$t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $isi = json_encode([
        'title' => $dekode($payload['title'] ?? 'AbsenKita'),
        'body'  => $dekode($payload['body'] ?? ''),
        'url'   => (string)($payload['url'] ?? './'),
        'tag'   => (string)($payload['tag'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        // '@': library memicu E_USER_NOTICE bila ekstensi GMP/BCMath tidak ada. Tanpa
        // ini notice tercetak ke respons JSON saat display_errors menyala (dev).
        $webPush = @new WebPush(['VAPID' => [
            'subject'    => $config['subject'],
            'publicKey'  => $config['public'],
            'privateKey' => $config['private'],
        ]], ['TTL' => 3600, 'urgency' => 'normal'], 5);
        $webPush->setReuseVAPIDHeaders(true);

        $per_endpoint = [];
        foreach ($langganan as $row) {
            $per_endpoint[$row['endpoint']] = (int)$row['id'];
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $row['endpoint'],
                'keys'     => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
            ]), $isi);
        }

        $berhasil = 0;
        $hapus = [];
        $sukses = [];
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $id = $per_endpoint[$endpoint] ?? 0;
            if ($report->isSuccess()) {
                $berhasil++;
                $sukses[] = $id;
            } elseif ($report->isSubscriptionExpired()) {
                $hapus[] = $id;
            } else {
                error_log('[push] gagal kirim: ' . $report->getReason());
            }
        }

        pushUpdateLangganan($conn, $sukses, $hapus);
        return $berhasil;
    } catch (Throwable $e) {
        error_log('[push] kirim gagal: ' . $e->getMessage());
        return 0;
    }
}

function pushUpdateLangganan(mysqli $conn, array $sukses, array $hapus): void {
    $sukses = array_values(array_filter($sukses));
    $hapus  = array_values(array_filter($hapus));
    if ($sukses) {
        $in = implode(',', array_fill(0, count($sukses), '?'));
        $stmt = $conn->prepare("UPDATE push_subscriptions SET last_success_at = NOW() WHERE id IN ($in)");
        $stmt->bind_param(str_repeat('i', count($sukses)), ...$sukses);
        $stmt->execute();
        $stmt->close();
    }
    if ($hapus) {
        $in = implode(',', array_fill(0, count($hapus), '?'));
        $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE id IN ($in)");
        $stmt->bind_param(str_repeat('i', count($hapus)), ...$hapus);
        $stmt->execute();
        $stmt->close();
    }
}

// ---------------------------------------------------------------------------
// Pencari penerima
// ---------------------------------------------------------------------------

/** users.id untuk satu karyawan (akun aktif), atau null. */
function pushUserIdKaryawan(mysqli $conn, ?string $id_karyawan): ?int {
    if ($id_karyawan === null || $id_karyawan === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT id FROM users WHERE id_karyawan = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $id_karyawan);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

function pushNamaKaryawan(mysqli $conn, ?string $id_karyawan): string {
    $stmt = $conn->prepare("SELECT nama_karyawan FROM karyawan WHERE id_karyawan = ?");
    $stmt->bind_param("s", $id_karyawan);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['nama_karyawan'] ?? 'Karyawan';
}

/**
 * Semua admin + supervisor cabang tersebut (penerima pengajuan yang butuh
 * persetujuan). $kecuali = users.id yang tidak perlu dinotifikasi (pengaju sendiri).
 */
function pushPenerimaApprover(mysqli $conn, ?int $id_cabang, array $kecuali = []): array {
    $stmt = $conn->prepare("SELECT id FROM users
        WHERE is_active = 1 AND (role = 'admin' OR (role = 'supervisor' AND id_cabang = ?))");
    $cabang = (int)$id_cabang;
    $stmt->bind_param("i", $cabang);
    $stmt->execute();
    $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
    $stmt->close();
    return array_values(array_diff($ids, array_map('intval', $kecuali)));
}

/** Semua owner aktif. */
function pushPenerimaOwner(mysqli $conn): array {
    $res = $conn->query("SELECT id FROM users WHERE is_active = 1 AND role = 'owner'");
    return $res ? array_map('intval', array_column($res->fetch_all(MYSQLI_ASSOC), 'id')) : [];
}

// ---------------------------------------------------------------------------
// Jalan pintas untuk pemanggil
// ---------------------------------------------------------------------------

/** Beritahu satu karyawan (lewat id_karyawan). */
function pushAntreKaryawan(mysqli $conn, ?string $id_karyawan, string $title, string $body, string $url, string $tag = ''): void {
    $uid = pushUserIdKaryawan($conn, $id_karyawan);
    if ($uid) {
        pushAntre($conn, [$uid], compact('title', 'body', 'url', 'tag'));
    }
}

/** Beritahu admin + supervisor cabang. */
function pushAntreApprover(mysqli $conn, ?int $id_cabang, string $title, string $body, string $url, string $tag = '', array $kecuali = []): void {
    $uids = pushPenerimaApprover($conn, $id_cabang, $kecuali);
    if ($uids) {
        pushAntre($conn, $uids, compact('title', 'body', 'url', 'tag'));
    }
}
