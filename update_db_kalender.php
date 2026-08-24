<?php
/**
 * ==========================================
 * MIGRASI: Kalender, Hari Libur & Hari Kerja
 * ==========================================
 * Skrip migrasi idempotent (aman dijalankan berulang kali), mengikuti pola
 * update_db.php / update_db_izin.php.
 *
 * Yang dibuat/diubah:
 *  1. Tabel `hari_libur`            - master hari libur untuk kalender
 *  2. Seed hari libur nasional 2026 - PERLU DIVERIFIKASI dengan SKB resmi
 *  3. system_settings               - hari_kerja (Senin-Jumat) & hari_overtime (Sabtu)
 *  4. jabatan.overtime_sabtu        - penanda jabatan yang boleh lembur Sabtu
 *
 * Jalankan sekali lewat browser (http://localhost/update_db_kalender.php)
 * atau CLI: php update_db_kalender.php
 */

require 'config.php';

$log = [];

function migrasi_info(&$log, $pesan, $status = 'ok') {
    $log[] = ['status' => $status, 'pesan' => $pesan];
}

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

// ==========================================================
// 1. Tabel hari_libur
// ==========================================================
if (!tabelAda($conn, 'hari_libur')) {
    $sql = "CREATE TABLE `hari_libur` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tanggal` date NOT NULL,
        `nama` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
        `jenis` enum('Nasional','Cuti Bersama','Perusahaan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Nasional',
        `id_cabang` int DEFAULT NULL COMMENT 'NULL = berlaku untuk semua cabang',
        `perlu_verifikasi` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = tanggal hasil seed otomatis, wajib dicek ke SKB resmi',
        `created_by` int DEFAULT NULL COMMENT 'users.id yang menambahkan',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_libur_tanggal_cabang` (`tanggal`,`id_cabang`),
        KEY `idx_libur_tanggal` (`tanggal`),
        KEY `idx_libur_cabang` (`id_cabang`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if ($conn->query($sql)) {
        migrasi_info($log, "Tabel <code>hari_libur</code> berhasil dibuat.");
    } else {
        migrasi_info($log, "Gagal membuat tabel hari_libur: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Tabel <code>hari_libur</code> sudah ada, dilewati.", 'skip');
}

// ==========================================================
// 2. Seed hari libur nasional 2026
// ==========================================================
// PENTING: tanggal libur yang mengikuti kalender Hijriah/Imlek/Saka
// (Isra Mikraj, Imlek, Nyepi, Idul Fitri, Waisak, Idul Adha, Tahun Baru Islam,
// Maulid Nabi) baru pasti setelah SKB 3 Menteri diterbitkan. Semua baris di
// bawah ditandai perlu_verifikasi = 1 dan bisa diedit/dihapus dari halaman
// Hari Libur oleh admin.
$libur_2026 = [
    ['2026-01-01', 'Tahun Baru 2026',                        'Nasional', 0],
    ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW',          'Nasional', 1],
    ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili',         'Nasional', 1],
    ['2026-03-19', 'Hari Suci Nyepi Tahun Baru Saka 1948',   'Nasional', 1],
    ['2026-03-20', 'Hari Raya Idul Fitri 1447 Hijriah',      'Nasional', 1],
    ['2026-03-21', 'Hari Raya Idul Fitri 1447 Hijriah',      'Nasional', 1],
    ['2026-04-03', 'Wafat Isa Al Masih',                     'Nasional', 0],
    ['2026-04-05', 'Kebangkitan Isa Al Masih (Paskah)',      'Nasional', 0],
    ['2026-05-01', 'Hari Buruh Internasional',               'Nasional', 0],
    ['2026-05-14', 'Kenaikan Isa Al Masih',                  'Nasional', 0],
    ['2026-05-27', 'Hari Raya Idul Adha 1447 Hijriah',       'Nasional', 1],
    ['2026-05-31', 'Hari Raya Waisak 2570 BE',               'Nasional', 1],
    ['2026-06-01', 'Hari Lahir Pancasila',                   'Nasional', 0],
    ['2026-06-16', 'Tahun Baru Islam 1448 Hijriah',          'Nasional', 1],
    ['2026-08-17', 'Hari Kemerdekaan Republik Indonesia',    'Nasional', 0],
    ['2026-08-25', 'Maulid Nabi Muhammad SAW',               'Nasional', 1],
    ['2026-12-25', 'Hari Raya Natal',                        'Nasional', 0],
];

if (tabelAda($conn, 'hari_libur')) {
    // INSERT IGNORE memakai UNIQUE (tanggal, id_cabang) sehingga tanggal yang
    // sudah pernah diisi/diperbaiki admin tidak akan tertimpa.
    $stmt = $conn->prepare("INSERT IGNORE INTO hari_libur (tanggal, nama, jenis, id_cabang, perlu_verifikasi)
                            VALUES (?, ?, ?, NULL, ?)");
    $ditambah = 0;
    foreach ($libur_2026 as $l) {
        $stmt->bind_param("sssi", $l[0], $l[1], $l[2], $l[3]);
        if ($stmt->execute() && $conn->affected_rows > 0) {
            $ditambah++;
        }
    }
    $stmt->close();

    if ($ditambah > 0) {
        migrasi_info($log, "Seed hari libur nasional 2026: <b>{$ditambah}</b> tanggal ditambahkan. "
            . "<b>Tanggal Hijriah/Imlek/Saka wajib diverifikasi dengan SKB 3 Menteri resmi</b> "
            . "(ditandai lencana <i>Perlu Verifikasi</i> di halaman Hari Libur).", 'warn');
    } else {
        migrasi_info($log, "Seed hari libur 2026 sudah pernah dijalankan, tidak ada tanggal baru.", 'skip');
    }
}

// ==========================================================
// 3. Pengaturan hari kerja & hari lembur
// ==========================================================
// Format: nomor hari ISO-8601 dipisah koma (1 = Senin ... 7 = Minggu)
$pengaturan_default = [
    ['hari_kerja',     '1,2,3,4,5', 'Hari kerja normal perusahaan (1=Senin ... 7=Minggu)'],
    ['hari_overtime',  '6',         'Hari yang dihitung sebagai hari lembur/overtime, bukan hari kerja normal'],
];

if (tabelAda($conn, 'system_settings')) {
    $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
                            VALUES (?, ?, ?)");
    $set_baru = 0;
    foreach ($pengaturan_default as $s) {
        $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
        if ($stmt->execute() && $conn->affected_rows > 0) {
            $set_baru++;
        }
    }
    $stmt->close();

    if ($set_baru > 0) {
        migrasi_info($log, "Pengaturan hari kerja ditambahkan: <b>Senin&ndash;Jumat</b> hari kerja, <b>Sabtu</b> hari lembur, <b>Minggu</b> libur.");
    } else {
        migrasi_info($log, "Pengaturan <code>hari_kerja</code>/<code>hari_overtime</code> sudah ada, dilewati.", 'skip');
    }
} else {
    // Tabel system_settings belum ada di database ini - buat sekalian
    $sql = "CREATE TABLE `system_settings` (
        `id` int NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(50) NOT NULL,
        `setting_value` text NOT NULL,
        `description` text,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if ($conn->query($sql)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        foreach ($pengaturan_default as $s) {
            $stmt->bind_param("sss", $s[0], $s[1], $s[2]);
            $stmt->execute();
        }
        $stmt->close();
        migrasi_info($log, "Tabel <code>system_settings</code> dibuat beserta pengaturan hari kerja Senin&ndash;Jumat.");
    } else {
        migrasi_info($log, "Gagal membuat tabel system_settings: " . $conn->error, 'error');
    }
}

// ==========================================================
// 4. jabatan.overtime_sabtu
// ==========================================================
// Tidak semua jabatan punya jatah lembur Sabtu, jadi kelayakannya disimpan
// per jabatan dan default-nya nonaktif (0) supaya tidak ada perubahan gaji
// yang terjadi diam-diam setelah migrasi.
if (!kolomAda($conn, 'jabatan', 'overtime_sabtu')) {
    $sql = "ALTER TABLE `jabatan` ADD `overtime_sabtu` tinyint(1) NOT NULL DEFAULT '0'
            COMMENT '1 = jabatan ini bisa ditugaskan lembur hari Sabtu'";
    if ($conn->query($sql)) {
        migrasi_info($log, "Kolom <code>jabatan.overtime_sabtu</code> ditambahkan (default nonaktif untuk semua jabatan).");
    } else {
        migrasi_info($log, "Gagal menambah kolom jabatan.overtime_sabtu: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Kolom <code>jabatan.overtime_sabtu</code> sudah ada, dilewati.", 'skip');
}

$ada_error = false;
foreach ($log as $baris) {
    if ($baris['status'] === 'error') $ada_error = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Migrasi Kalender &amp; Hari Kerja - AbsenSlip Javag</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f1f5f9; padding: 40px 20px; color: #0f172a; }
        .box { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 28px 32px; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p.sub { color: #64748b; font-size: 14px; margin: 0 0 22px; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { padding: 10px 14px; border-radius: 9px; margin-bottom: 8px; font-size: 14px; border: 1px solid transparent; line-height: 1.55; }
        li.ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        li.skip { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        li.warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        li.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        code { background: rgba(15,23,42,.07); padding: 1px 5px; border-radius: 4px; font-size: 13px; }
        .footer { margin-top: 22px; font-size: 13px; color: #64748b; line-height: 1.6; }
        a { color: #c026d3; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <h1><?php echo $ada_error ? '⚠️ Migrasi selesai dengan error' : '✅ Migrasi Kalender &amp; Hari Kerja selesai'; ?></h1>
        <p class="sub">Skrip ini aman dijalankan berulang kali &mdash; langkah yang sudah pernah dijalankan otomatis dilewati.</p>
        <ul>
            <?php foreach ($log as $baris): ?>
                <li class="<?php echo $baris['status']; ?>"><?php echo $baris['pesan']; ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="footer">
            <b>Langkah berikutnya:</b><br>
            1. Buka menu <b>Master Data &rarr; Hari Libur</b> dan cocokkan tanggal bertanda
               <i>Perlu Verifikasi</i> dengan SKB 3 Menteri resmi.<br>
            2. Pada <b>Data Jabatan</b>, aktifkan <i>Lembur Sabtu</i> untuk jabatan yang memang
               ditugaskan bekerja pada hari Sabtu.<br><br>
            <a href="login.php">&larr; Kembali ke halaman login</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
