<?php
/**
 * ==========================================
 * MIGRASI: Cuti Khusus + Izin Pulang Cepat
 * ==========================================
 * Skrip migrasi idempotent (aman dijalankan berulang kali), mengikuti pola
 * update_db_izin.php: cek dulu apakah objek sudah ada, baru ALTER.
 *
 * Menambahkan:
 * - 4 jenis pengajuan_izin baru: Menikah, Menikahkan Anak, Melahirkan, Duka
 *   Cita (cuti khusus, tidak memotong jatah cuti tahunan, lama pengajuan
 *   diserahkan ke keputusan admin/supervisor).
 * - absensi.izin_pulang_cepat + absensi.alasan_pulang_cepat: mekanisme izin
 *   pulang lebih awal di hari-H (mirip Pending Dinas), harus disetujui
 *   Admin/Supervisor sebelum absen pulang sebelum jam pulang shift diterima.
 *
 * Jalankan sekali lewat browser (http://localhost/update_db_izin_khusus.php)
 * atau CLI: php update_db_izin_khusus.php
 * Harus dijalankan setelah update_db_izin.php.
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

function definisiKolom($conn, $tabel, $kolom) {
    $stmt = $conn->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->bind_param("ss", $tabel, $kolom);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res ? $res['COLUMN_TYPE'] : null;
}

// ==========================================================
// 1. pengajuan_izin.jenis: tambah 4 jenis cuti khusus
// ==========================================================
$tipe_jenis = definisiKolom($conn, 'pengajuan_izin', 'jenis');
if ($tipe_jenis !== null && strpos($tipe_jenis, "'Duka Cita'") === false) {
    $sql = "ALTER TABLE `pengajuan_izin` MODIFY `jenis`
            enum('Cuti','Sakit','Izin','Dinas Luar','Menikah','Menikahkan Anak','Melahirkan','Duka Cita')
            COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Cuti'";
    if ($conn->query($sql)) {
        migrasi_info($log, "Enum <code>pengajuan_izin.jenis</code> diperluas dengan 4 jenis cuti khusus (Menikah, Menikahkan Anak, Melahirkan, Duka Cita).");
    } else {
        migrasi_info($log, "Gagal memperluas enum pengajuan_izin.jenis: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Enum <code>pengajuan_izin.jenis</code> sudah mendukung cuti khusus, dilewati.", 'skip');
}

// ==========================================================
// 2. absensi.keterangan: tambah 4 nilai cuti khusus
// ==========================================================
$tipe_ket = definisiKolom($conn, 'absensi', 'keterangan');
if ($tipe_ket !== null && strpos($tipe_ket, "'Duka Cita'") === false) {
    $sql = "ALTER TABLE `absensi` MODIFY `keterangan`
            enum('Hadir','OFF','Sakit','Cuti','Izin','Alpha','Pending Dinas','Dinas Luar','Menikah','Menikahkan Anak','Melahirkan','Duka Cita')
            COLLATE utf8mb4_general_ci DEFAULT NULL";
    if ($conn->query($sql)) {
        migrasi_info($log, "Enum <code>absensi.keterangan</code> diperluas dengan 4 jenis cuti khusus.");
    } else {
        migrasi_info($log, "Gagal memperluas enum absensi.keterangan: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Enum <code>absensi.keterangan</code> sudah mendukung cuti khusus, dilewati.", 'skip');
}

// ==========================================================
// 3. absensi.izin_pulang_cepat + alasan_pulang_cepat
// ==========================================================
if (!kolomAda($conn, 'absensi', 'izin_pulang_cepat')) {
    $sql = "ALTER TABLE `absensi` ADD `izin_pulang_cepat`
            enum('Pending','Disetujui','Ditolak') COLLATE utf8mb4_general_ci DEFAULT NULL
            COMMENT 'Status izin pulang lebih awal hari ini, wajib Disetujui sebelum absen pulang diterima sebelum jam pulang shift'";
    if ($conn->query($sql)) {
        migrasi_info($log, "Kolom <code>absensi.izin_pulang_cepat</code> berhasil ditambahkan.");
    } else {
        migrasi_info($log, "Gagal menambah kolom absensi.izin_pulang_cepat: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Kolom <code>absensi.izin_pulang_cepat</code> sudah ada, dilewati.", 'skip');
}

if (!kolomAda($conn, 'absensi', 'alasan_pulang_cepat')) {
    $sql = "ALTER TABLE `absensi` ADD `alasan_pulang_cepat` varchar(255)
            COLLATE utf8mb4_general_ci DEFAULT NULL
            COMMENT 'Alasan izin pulang lebih awal hari ini'";
    if ($conn->query($sql)) {
        migrasi_info($log, "Kolom <code>absensi.alasan_pulang_cepat</code> berhasil ditambahkan.");
    } else {
        migrasi_info($log, "Gagal menambah kolom absensi.alasan_pulang_cepat: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Kolom <code>absensi.alasan_pulang_cepat</code> sudah ada, dilewati.", 'skip');
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
    <title>Migrasi Cuti Khusus & Pulang Cepat - AbsenSlip Javag</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f1f5f9; padding: 40px 20px; color: #0f172a; }
        .box { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 28px 32px; box-shadow: 0 10px 30px rgba(15,23,42,.08); }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p.sub { color: #64748b; font-size: 14px; margin: 0 0 22px; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { padding: 10px 14px; border-radius: 9px; margin-bottom: 8px; font-size: 14px; border: 1px solid transparent; }
        li.ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        li.skip { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
        li.error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        code { background: rgba(15,23,42,.07); padding: 1px 5px; border-radius: 4px; font-size: 13px; }
        .footer { margin-top: 22px; font-size: 13px; color: #64748b; }
        a { color: #c026d3; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <h1><?php echo $ada_error ? '⚠️ Migrasi selesai dengan error' : '✅ Migrasi Cuti Khusus & Pulang Cepat selesai'; ?></h1>
        <p class="sub">Skrip ini aman dijalankan berulang kali &mdash; langkah yang sudah pernah dijalankan otomatis dilewati.</p>
        <ul>
            <?php foreach ($log as $baris): ?>
                <li class="<?php echo $baris['status']; ?>"><?php echo $baris['pesan']; ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="footer">
            <a href="login.php">&larr; Kembali ke halaman login</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
