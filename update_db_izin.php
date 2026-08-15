<?php
/**
 * ==========================================
 * MIGRASI: Pengajuan Izin / Cuti / Dinas Luar
 * ==========================================
 * Skrip migrasi idempotent (aman dijalankan berulang kali), mengikuti pola
 * update_db.php: cek dulu apakah objek sudah ada, baru ALTER/CREATE.
 *
 * Jalankan sekali lewat browser (http://localhost/update_db_izin.php)
 * atau CLI: php update_db_izin.php
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
// 1. Tabel pengajuan_izin (satu baris = satu pengajuan/rentang)
// ==========================================================
if (!tabelAda($conn, 'pengajuan_izin')) {
    $sql = "CREATE TABLE `pengajuan_izin` (
        `id` int NOT NULL AUTO_INCREMENT,
        `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
        `jenis` enum('Cuti','Sakit','Izin','Dinas Luar') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Cuti',
        `tanggal_mulai` date NOT NULL,
        `tanggal_selesai` date NOT NULL,
        `jumlah_hari` int NOT NULL DEFAULT '0' COMMENT 'Total hari kalender dalam rentang',
        `jumlah_hari_kerja` int NOT NULL DEFAULT '0' COMMENT 'Hari efektif yang memotong kuota (tanpa Minggu/libur)',
        `keperluan` text COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Catatan/alasan dari karyawan',
        `lampiran` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nama file bukti (surat dokter dll)',
        `status` enum('Pending','Disetujui','Ditolak','Dibatalkan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
        `potong_kuota` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=memotong jatah cuti tahunan',
        `id_cabang` int DEFAULT NULL COMMENT 'Snapshot cabang saat pengajuan (untuk scoping supervisor)',
        `reviewed_by` int DEFAULT NULL COMMENT 'users.id yang menyetujui/menolak',
        `reviewed_at` datetime DEFAULT NULL,
        `catatan_reviewer` text COLLATE utf8mb4_general_ci,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_izin_karyawan` (`id_karyawan`),
        KEY `idx_izin_status` (`status`),
        KEY `idx_izin_cabang` (`id_cabang`),
        KEY `idx_izin_rentang` (`tanggal_mulai`,`tanggal_selesai`),
        KEY `idx_izin_karyawan_status` (`id_karyawan`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if ($conn->query($sql)) {
        migrasi_info($log, "Tabel <code>pengajuan_izin</code> berhasil dibuat.");
    } else {
        migrasi_info($log, "Gagal membuat tabel pengajuan_izin: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Tabel <code>pengajuan_izin</code> sudah ada, dilewati.", 'skip');
}

// ==========================================================
// 2. users.role: tambah 'supervisor'
// ==========================================================
$tipe_role = definisiKolom($conn, 'users', 'role');
if ($tipe_role !== null && strpos($tipe_role, "'supervisor'") === false) {
    $sql = "ALTER TABLE `users` MODIFY `role`
            enum('admin','staff','owner','supervisor')
            COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'staff'";
    if ($conn->query($sql)) {
        migrasi_info($log, "Enum <code>users.role</code> diperluas dengan nilai <b>supervisor</b>.");
    } else {
        migrasi_info($log, "Gagal memperluas enum users.role: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Enum <code>users.role</code> sudah mendukung supervisor, dilewati.", 'skip');
}

// ==========================================================
// 3. users.id_cabang: cakupan cabang untuk supervisor
// ==========================================================
if (!kolomAda($conn, 'users', 'id_cabang')) {
    $sql = "ALTER TABLE `users` ADD `id_cabang` int DEFAULT NULL
            COMMENT 'Cabang yang disupervisi (khusus role supervisor)' AFTER `id_karyawan`";
    if ($conn->query($sql)) {
        $conn->query("ALTER TABLE `users` ADD KEY `idx_users_cabang` (`id_cabang`)");
        migrasi_info($log, "Kolom <code>users.id_cabang</code> berhasil ditambahkan.");
    } else {
        migrasi_info($log, "Gagal menambah kolom users.id_cabang: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Kolom <code>users.id_cabang</code> sudah ada, dilewati.", 'skip');
}

// ==========================================================
// 4. karyawan.jatah_cuti: kuota tahunan per karyawan (default 12)
// ==========================================================
if (!kolomAda($conn, 'karyawan', 'jatah_cuti')) {
    $sql = "ALTER TABLE `karyawan` ADD `jatah_cuti` int NOT NULL DEFAULT '12'
            COMMENT 'Jatah izin/cuti per tahun' AFTER `tanggal_resign`";
    if ($conn->query($sql)) {
        migrasi_info($log, "Kolom <code>karyawan.jatah_cuti</code> berhasil ditambahkan (default 12).");
    } else {
        migrasi_info($log, "Gagal menambah kolom karyawan.jatah_cuti: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Kolom <code>karyawan.jatah_cuti</code> sudah ada, dilewati.", 'skip');
}

// ==========================================================
// 5. absensi.id_pengajuan: penanda baris hasil approval
// ==========================================================
if (!kolomAda($conn, 'absensi', 'id_pengajuan')) {
    $sql = "ALTER TABLE `absensi` ADD `id_pengajuan` int DEFAULT NULL
            COMMENT 'Referensi pengajuan_izin.id bila baris ini dibuat dari approval izin'";
    if ($conn->query($sql)) {
        $conn->query("ALTER TABLE `absensi` ADD KEY `idx_absensi_pengajuan` (`id_pengajuan`)");
        migrasi_info($log, "Kolom <code>absensi.id_pengajuan</code> berhasil ditambahkan.");
    } else {
        migrasi_info($log, "Gagal menambah kolom absensi.id_pengajuan: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Kolom <code>absensi.id_pengajuan</code> sudah ada, dilewati.", 'skip');
}

// ==========================================================
// 6. absensi.keterangan: tambah nilai 'Izin'
// ==========================================================
$tipe_ket = definisiKolom($conn, 'absensi', 'keterangan');
if ($tipe_ket !== null && strpos($tipe_ket, "'Izin'") === false) {
    $sql = "ALTER TABLE `absensi` MODIFY `keterangan`
            enum('Hadir','OFF','Sakit','Cuti','Izin','Alpha','Pending Dinas','Dinas Luar')
            COLLATE utf8mb4_general_ci DEFAULT NULL";
    if ($conn->query($sql)) {
        migrasi_info($log, "Enum <code>absensi.keterangan</code> diperluas dengan nilai <b>Izin</b>.");
    } else {
        migrasi_info($log, "Gagal memperluas enum absensi.keterangan: " . $conn->error, 'error');
    }
} else {
    migrasi_info($log, "Enum <code>absensi.keterangan</code> sudah mendukung Izin, dilewati.", 'skip');
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
    <title>Migrasi Pengajuan Izin - AbsenSlip Dinia</title>
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
        <h1><?php echo $ada_error ? '⚠️ Migrasi selesai dengan error' : '✅ Migrasi Pengajuan Izin selesai'; ?></h1>
        <p class="sub">Skrip ini aman dijalankan berulang kali &mdash; langkah yang sudah pernah dijalankan otomatis dilewati.</p>
        <ul>
            <?php foreach ($log as $baris): ?>
                <li class="<?php echo $baris['status']; ?>"><?php echo $baris['pesan']; ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="footer">
            Langkah berikutnya: buat akun Supervisor lewat menu <b>Setting Users</b> (khusus admin), lalu
            karyawan dapat mengajukan izin lewat menu <b>Pengajuan Izin</b>.
            <br><br>
            <a href="login.php">&larr; Kembali ke halaman login</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
