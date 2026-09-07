<?php
/**
 * ==========================================
 * MIGRATE.PHP - Runner Migrasi Database
 * ==========================================
 * Menggantikan seluruh update_db_*.php lama (satu file lepas per fitur,
 * tidak ada catatan mana yang sudah dijalankan, gampang salah urutan) dengan
 * satu sistem: migrations/ berisi file bernomor urut, dijalankan tepat
 * sekali per file, tercatat di tabel `schema_migrations` supaya statusnya
 * selalu pasti - bukan ditebak dari "coba jalankan, lihat pesannya".
 *
 * Cara pakai - lihat MIGRATIONS.md untuk panduan lengkap (terutama sebelum
 * menjalankan ini di production).
 *
 *   Web   : buka migrate.php (login admin dulu) - selalu menampilkan
 *           status/pratinjau dulu, tidak pernah langsung mengubah apa pun.
 *           Klik tombol "Jalankan Migrasi" untuk benar-benar mengeksekusi.
 *   CLI   : php migrate.php            -> status/pratinjau
 *           php migrate.php migrate    -> pratinjau + peringatan (tidak dieksekusi)
 *           php migrate.php migrate --yes  -> benar-benar dieksekusi
 *
 * Berhenti di migrasi pertama yang gagal - migrasi sebelumnya yang sudah
 * sukses TETAP tercatat (tidak diulang lagi), migrasi yang gagal TIDAK
 * dicatat sebagai selesai (akan dicoba lagi otomatis di run berikutnya
 * setelah penyebabnya diperbaiki).
 *
 * CATATAN PENTING soal "rollback": MySQL/InnoDB meng-commit setiap statement
 * DDL (CREATE/ALTER TABLE) secara otomatis - tidak bisa dibungkus transaksi
 * dan dibatalkan seperti INSERT/UPDATE biasa. Jadi tidak ada "undo otomatis"
 * di sini. Mitigasinya: (1) setiap migrasi idempotent & aman diulang lewat
 * pengecekan SHOW COLUMNS/information_schema, (2) SELALU backup database
 * sebelum migrate di production (lihat MIGRATIONS.md), (3) berhenti-di-
 * kegagalan-pertama di atas supaya kerusakan tidak menjalar ke migrasi lain.
 */

require 'config.php';
require 'migration_helpers.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // Migrasi skema adalah aksi admin-level paling sensitif di aplikasi ini -
    // update_db_*.php lama tidak punya proteksi apa pun (siapa saja yang tahu
    // URL-nya bisa menjalankan ALTER TABLE di server manapun). Akses CLI
    // dianggap sudah punya kepercayaan lebih tinggi (perlu akses server),
    // jadi hanya jalur web yang di-gate di sini.
    requireAdmin();
}

function migrationsDir() {
    return __DIR__ . '/migrations';
}

function pastikanTabelMigrasi($conn) {
    if (!tabelAda($conn, 'schema_migrations')) {
        $conn->query("CREATE TABLE `schema_migrations` (
            `id` int NOT NULL AUTO_INCREMENT,
            `migration` varchar(191) COLLATE utf8mb4_general_ci NOT NULL,
            `batch` int NOT NULL,
            `applied_by` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'cli, atau username admin yang menjalankan lewat web',
            `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}

/** Daftar file migrations/*.php, terurut menurut nama file (nomor prefix). */
function daftarFileMigrasi() {
    $files = glob(migrationsDir() . '/*.php');
    sort($files, SORT_STRING);
    return $files;
}

function migrasiSudahDiterapkan($conn) {
    $hasil = [];
    $res = $conn->query("SELECT migration, batch, applied_by, applied_at FROM schema_migrations");
    while ($row = $res->fetch_assoc()) {
        $hasil[$row['migration']] = $row;
    }
    return $hasil;
}

/**
 * Susun rencana: setiap file migrations/*.php diberi status applied/pending
 * berdasarkan tabel schema_migrations. Tidak menjalankan apa pun.
 */
function rencanaMigrasi($conn) {
    pastikanTabelMigrasi($conn);
    $diterapkan = migrasiSudahDiterapkan($conn);
    $rencana = [];
    foreach (daftarFileMigrasi() as $path) {
        $nama_file = basename($path, '.php');
        $def = include $path;
        $rencana[] = [
            'file' => $nama_file,
            'nama' => $def['name'] ?? $nama_file,
            'applied' => $diterapkan[$nama_file] ?? null,
        ];
    }
    return $rencana;
}

/**
 * Jalankan semua migrasi yang belum tercatat, berurutan sesuai nama file.
 * Berhenti di kegagalan pertama. Mengembalikan array laporan per migrasi
 * yang benar-benar dieksekusi pada run ini.
 */
function jalankanMigrasiPending($conn, $appliedBy) {
    pastikanTabelMigrasi($conn);
    $diterapkan = migrasiSudahDiterapkan($conn);

    $batch = 1;
    $res = $conn->query("SELECT MAX(batch) AS m FROM schema_migrations");
    if ($res) {
        $row = $res->fetch_assoc();
        if (!empty($row['m'])) $batch = (int)$row['m'] + 1;
    }

    $laporan = [];
    foreach (daftarFileMigrasi() as $path) {
        $nama_file = basename($path, '.php');
        if (isset($diterapkan[$nama_file])) {
            continue; // sudah pernah dijalankan sukses - jangan diulang
        }

        $def = include $path;
        $log = new MigrationLog();

        try {
            $def['up']($conn, $log);
        } catch (\Throwable $e) {
            $log->error("Exception: " . $e->getMessage());
        }

        $laporan[] = ['file' => $nama_file, 'nama' => $def['name'] ?? $nama_file, 'log' => $log];

        if ($log->hasError()) {
            break; // berhenti di kegagalan pertama - jangan lanjut ke migrasi berikutnya
        }

        $stmt = $conn->prepare("INSERT INTO schema_migrations (migration, batch, applied_by) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $nama_file, $batch, $appliedBy);
        $stmt->execute();
        $stmt->close();
    }
    return $laporan;
}

// ==========================================================
// CLI
// ==========================================================
if ($isCli) {
    $argv = $_SERVER['argv'] ?? [];
    $action = $argv[1] ?? 'status';
    $konfirmasi = in_array('--yes', $argv, true);

    $info = infoKoneksiDb($conn);
    echo "DB: {$info['host']} / {$info['database']}" . ($info['is_local'] ? " (lokal)" : " *** BUKAN LOKAL ***") . "\n\n";

    $rencana = rencanaMigrasi($conn);
    $pending = array_filter($rencana, fn($r) => $r['applied'] === null);

    if ($action === 'status' || ($action === 'migrate' && !$konfirmasi)) {
        foreach ($rencana as $r) {
            if ($r['applied']) {
                echo "[applied]  {$r['file']} - {$r['nama']} (batch {$r['applied']['batch']}, {$r['applied']['applied_at']})\n";
            } else {
                echo "[pending]  {$r['file']} - {$r['nama']}\n";
            }
        }
        if (empty($pending)) {
            echo "\nSemua migrasi sudah diterapkan. Tidak ada yang perlu dijalankan.\n";
        } elseif ($action === 'status') {
            echo "\n" . count($pending) . " migrasi pending. Jalankan: php migrate.php migrate --yes\n";
        } else {
            echo "\nAkan menjalankan " . count($pending) . " migrasi di atas. Tambahkan --yes untuk benar-benar mengeksekusi:\n";
            echo "  php migrate.php migrate --yes\n";
        }
        exit(0);
    }

    if ($action === 'migrate' && $konfirmasi) {
        if (empty($pending)) {
            echo "Tidak ada migrasi pending.\n";
            exit(0);
        }
        $laporan = jalankanMigrasiPending($conn, 'cli:' . (get_current_user() ?: 'unknown'));
        $adaError = false;
        foreach ($laporan as $l) {
            echo "\n== {$l['file']} - {$l['nama']} ==\n";
            foreach ($l['log']->entries as $e) {
                echo "  [{$e['status']}] " . strip_tags($e['pesan']) . "\n";
                if ($e['status'] === 'error') $adaError = true;
            }
        }
        echo $adaError
            ? "\nBERHENTI karena ada kegagalan. Migrasi sebelum ini tetap tercatat selesai. Perbaiki lalu jalankan lagi.\n"
            : "\nSemua migrasi pending berhasil diterapkan.\n";
        exit($adaError ? 1 : 0);
    }

    echo "Penggunaan: php migrate.php [status|migrate] [--yes]\n";
    exit(1);
}

// ==========================================================
// WEB
// ==========================================================
$csrf_token = generateCSRFToken();
$hasil_run = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jalankan_migrasi'])) {
    verifyCSRFToken($_POST['csrf_token'] ?? '');
    $appliedBy = $_SESSION['username'] ?? ('admin#' . ($_SESSION['user_id'] ?? '?'));
    $hasil_run = jalankanMigrasiPending($conn, $appliedBy);
    if (function_exists('logActivity')) {
        $ringkas = count($hasil_run) . ' migrasi dieksekusi';
        logActivity($conn, 'migrate_database', $ringkas, $_SESSION['user_id'] ?? null);
    }
}

$info = infoKoneksiDb($conn);
$rencana = rencanaMigrasi($conn);
$pending = array_filter($rencana, fn($r) => $r['applied'] === null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Database - AbsenKita Javag</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f1f5f9; padding: 40px 20px; color: #0f172a; }
        .box { max-width: 820px; margin: 0 auto 20px; background: #fff; border-radius: 14px; padding: 28px 32px; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        h1 { font-size: 20px; margin: 0 0 6px; }
        h2 { font-size: 15px; margin: 0 0 12px; color: #334155; }
        p.sub { color: #64748b; font-size: 14px; margin: 0 0 22px; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { padding: 10px 14px; border-radius: 9px; margin-bottom: 8px; font-size: 14px; border: 1px solid transparent; line-height: 1.55; }
        li.ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        li.skip { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        li.warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        li.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        li.pending { background: #fdf4ff; border-color: #f5d0fe; color: #86198f; }
        li.applied { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        code { background: rgba(15,23,42,.07); padding: 1px 5px; border-radius: 4px; font-size: 13px; }
        .db-banner { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 18px; }
        .db-banner.local { background: #f0f9ff; color: #075985; border: 1px solid #bae6fd; }
        .db-banner.remote { background: #fef2f2; color: #991b1b; border: 2px solid #fca5a5; }
        button { padding: 10px 22px; background: #c026d3; color: #fff; border: none; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; }
        button:hover { background: #a21caf; }
        a { color: #c026d3; font-weight: 600; text-decoration: none; }
        .empty { color: #64748b; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Migrasi Database</h1>
        <p class="sub">Sistem migrasi terpusat - menggantikan update_db_*.php. Lihat <code>MIGRATIONS.md</code> untuk panduan lengkap.</p>

        <div class="db-banner <?php echo $info['is_local'] ? 'local' : 'remote'; ?>">
            <?php if ($info['is_local']): ?>
                🖥️ Terhubung ke <code><?php echo htmlspecialchars($info['host']); ?></code> / <code><?php echo htmlspecialchars($info['database']); ?></code> (lingkungan lokal)
            <?php else: ?>
                ⚠️ Terhubung ke <code><?php echo htmlspecialchars($info['host']); ?></code> / <code><?php echo htmlspecialchars($info['database']); ?></code>
                — <b>ini BUKAN localhost.</b> Pastikan sudah backup database sebelum menjalankan migrasi apa pun.
            <?php endif; ?>
        </div>

        <?php if ($hasil_run !== null): ?>
            <h2>Hasil eksekusi barusan</h2>
            <?php $ada_error_run = false; ?>
            <?php foreach ($hasil_run as $l): ?>
                <p style="font-weight:700;margin:14px 0 6px;"><?php echo htmlspecialchars($l['file'] . ' - ' . $l['nama']); ?></p>
                <ul>
                    <?php foreach ($l['log']->entries as $e): ?>
                        <li class="<?php echo $e['status']; ?>"><?php echo $e['pesan']; ?></li>
                        <?php if ($e['status'] === 'error') $ada_error_run = true; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <p class="sub">
                <?php echo $ada_error_run
                    ? '⚠️ Berhenti karena ada kegagalan. Migrasi sebelum ini tetap tercatat selesai. Perbaiki penyebabnya, lalu muat ulang halaman ini untuk mencoba lagi.'
                    : '✅ Semua migrasi yang tadinya pending berhasil diterapkan.'; ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Status migrasi</h2>
        <ul>
            <?php foreach ($rencana as $r): ?>
                <?php if ($r['applied']): ?>
                    <li class="applied">✅ <b><?php echo htmlspecialchars($r['file']); ?></b> — <?php echo htmlspecialchars($r['nama']); ?>
                        <br><span style="font-size:12px;color:#94a3b8;">diterapkan <?php echo htmlspecialchars($r['applied']['applied_at']); ?> oleh <?php echo htmlspecialchars($r['applied']['applied_by'] ?? '-'); ?> (batch <?php echo (int)$r['applied']['batch']; ?>)</span>
                    </li>
                <?php else: ?>
                    <li class="pending">⏳ <b><?php echo htmlspecialchars($r['file']); ?></b> — <?php echo htmlspecialchars($r['nama']); ?> <i>(pending)</i></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <?php if (empty($pending)): ?>
            <p class="empty" style="margin-top:16px;">Tidak ada migrasi pending. Database sudah mengikuti skema terbaru.</p>
        <?php else: ?>
            <form method="POST" style="margin-top:20px;" onsubmit="return confirm('Jalankan ' + <?php echo count($pending); ?> + ' migrasi pending pada database ' + <?php echo json_encode($info['host'] . '/' . $info['database']); ?> + '? Pastikan sudah backup.');">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="jalankan_migrasi" value="1">
                <button type="submit">Jalankan <?php echo count($pending); ?> Migrasi Pending</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="box">
        <p class="sub" style="margin:0;"><a href="login.php">&larr; Kembali ke halaman login</a></p>
    </div>
</body>
</html>
<?php $conn->close(); ?>
