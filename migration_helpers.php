<?php
/**
 * ==========================================
 * HELPER MIGRASI
 * ==========================================
 * Fungsi pengecekan skema yang sebelumnya diduplikasi di setiap
 * update_db_*.php (sekarang dihapus, digantikan migrate.php + migrations/) -
 * satu sumber, dipakai bersama oleh migrate.php dan semua file migrations/.
 */

function kolomAda($conn, $tabel, $kolom) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->bind_param("ss", $tabel, $kolom);
    $stmt->execute();
    $jml = $stmt->get_result()->fetch_assoc()['jml'];
    $stmt->close();
    return $jml > 0;
}

function tabelAda($conn, $tabel) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->bind_param("s", $tabel);
    $stmt->execute();
    $jml = $stmt->get_result()->fetch_assoc()['jml'];
    $stmt->close();
    return $jml > 0;
}

/** Cek index ada. $kolom opsional - kalau diisi, ikut memastikan index itu mencakup kolom tsb. */
function indexAda($conn, $tabel, $indexName, $kolom = null) {
    if ($kolom !== null) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM information_schema.STATISTICS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND COLUMN_NAME = ?");
        $stmt->bind_param("sss", $tabel, $indexName, $kolom);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS jml FROM information_schema.STATISTICS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $stmt->bind_param("ss", $tabel, $indexName);
    }
    $stmt->execute();
    $jml = $stmt->get_result()->fetch_assoc()['jml'];
    $stmt->close();
    return $jml > 0;
}

function definisiKolom($conn, $tabel, $kolom) {
    $stmt = $conn->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->bind_param("ss", $tabel, $kolom);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? $res['COLUMN_TYPE'] : null;
}

/** Apakah enum $tabel.$kolom sudah mengandung nilai $nilai? */
function enumMengandung($conn, $tabel, $kolom, $nilai) {
    $tipe = definisiKolom($conn, $tabel, $kolom);
    return $tipe !== null && strpos($tipe, "'{$nilai}'") !== false;
}

/**
 * Info koneksi DB saat ini + tebakan kasar "apakah ini lokal" - dipakai
 * migrate.php dan seed_dummy_data.php supaya keduanya menampilkan database
 * mana yang akan disentuh SEBELUM mengeksekusi apa pun, dan supaya aksi
 * yang menulis data (migrasi, seed) bisa minta konfirmasi ekstra kalau
 * host-nya bukan localhost/lingkungan dev yang dikenal.
 */
function infoKoneksiDb($conn) {
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbName = $conn->query("SELECT DATABASE() AS d")->fetch_assoc()['d'];
    $isLocal = in_array($host, ['localhost', '127.0.0.1', 'db'], true);
    return ['host' => $host, 'database' => $dbName, 'is_local' => $isLocal];
}

/**
 * Log satu migrasi. Beda dari array log lama (migrasi_info by-ref) supaya
 * migrate.php bisa tahu "apa migrasi ini gagal?" lewat hasError(), tanpa
 * setiap file migrations/*.php harus mengulang logikanya sendiri.
 */
class MigrationLog {
    public array $entries = [];
    private bool $error = false;

    public function ok($pesan) { $this->entries[] = ['status' => 'ok', 'pesan' => $pesan]; }
    public function skip($pesan) { $this->entries[] = ['status' => 'skip', 'pesan' => $pesan]; }
    public function warn($pesan) { $this->entries[] = ['status' => 'warn', 'pesan' => $pesan]; }
    public function error($pesan) { $this->entries[] = ['status' => 'error', 'pesan' => $pesan]; $this->error = true; }
    public function hasError() { return $this->error; }
}
