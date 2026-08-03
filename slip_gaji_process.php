<?php
/**
 * SLIP GAJI PROCESS - Backend Save
 * Handle form submission and save to database
 */

require 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    redirect("slip_gaji.php");
}

// Verify CSRF
verifyCSRFToken($_POST['csrf_token']);

// Get form data
$id_karyawan = sanitizeInput($_POST['id_karyawan']);
$slip_id = !empty($_POST['slip_id']) ? (int)$_POST['slip_id'] : null;
$is_edit = !empty($_POST['is_edit']);

// Periode
$bulan = (int)$_POST['bulan'];
$tahun = (int)$_POST['tahun'];

// Penghasilan manual
$gaji_pokok = (float)$_POST['gaji_pokok'];
$tunjangan_cs = isset($_POST['tunjangan_cs']) ? (float)$_POST['tunjangan_cs'] : 0;
$akomodasi = 0; // Removed from UI, always 0

// Transport (AUTO)
$transport_nominal = (float)$_POST['transport_nominal'];

// Get absensi data via raw SQL
$sql_absensi = "SELECT 
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') THEN a.id END) as total_hadir_raw,
    COUNT(DISTINCT CASE 
        WHEN a.keterangan = 'Hadir' AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as total_setengah_hari,
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') AND a.status_masuk = 'Terlambat' THEN a.id END) as total_terlambat,
    SUM(CASE 
        WHEN a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
        AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) >= 330
        AND a.jam_pulang > (
            SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1
        )
        THEN 
            CASE WHEN (TIME_TO_SEC(a.jam_pulang) - TIME_TO_SEC((SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1))) < 2100 THEN 0.5 ELSE 1 END
        ELSE 0 
    END) as total_overtime,
    COUNT(DISTINCT CASE WHEN a.keterangan IN ('Hadir', 'Dinas Luar') AND DAYOFWEEK(a.tanggal) = 1 THEN a.id END) as total_ahad_full_raw,
    COUNT(DISTINCT CASE 
        WHEN a.keterangan IN ('Hadir', 'Dinas Luar') AND DAYOFWEEK(a.tanggal) = 1
        AND (
            (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
            OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
        )
        THEN a.id 
    END) as total_ahad_setengah
FROM karyawan k
LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan 
    AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
WHERE k.id_karyawan = ?";

$stmt = $conn->prepare($sql_absensi);
$stmt->bind_param("iis", $bulan, $tahun, $id_karyawan);
$stmt->execute();
$raw_absensi = $stmt->get_result()->fetch_assoc();
$stmt->close();

$absensi = [
    'total_hari_hadir' => ($raw_absensi['total_hadir_raw'] ?? 0) - (($raw_absensi['total_setengah_hari'] ?? 0) * 0.5),
    'total_terlambat' => $raw_absensi['total_terlambat'] ?? 0,
    'total_overtime' => $raw_absensi['total_overtime'] ?? 0,
    'total_ahad_full' => ($raw_absensi['total_ahad_full_raw'] ?? 0) - ($raw_absensi['total_ahad_setengah'] ?? 0),
    'total_ahad_setengah' => $raw_absensi['total_ahad_setengah'] ?? 0
];

$transport_hari = isset($_POST['transport_hari']) ? (float)$_POST['transport_hari'] : ($absensi['total_hari_hadir'] ?? 0);
$transport_total = $transport_nominal * $transport_hari;

// Overtime (AUTO)
$overtime_nominal = (float)$_POST['overtime_nominal'];
$overtime_jam = isset($_POST['overtime_jam']) ? (float)$_POST['overtime_jam'] : ($absensi['total_overtime'] ?? 0);
$overtime_total = $overtime_nominal * $overtime_jam;

// Insentif Ahad (AUTO)
$insentif_ahad_nominal = (float)$_POST['insentif_ahad_nominal'];
$ahad_full = $absensi['total_ahad_full'] ?? 0;
$ahad_half = $absensi['total_ahad_setengah'] ?? 0;
$insentif_ahad_hari = isset($_POST['insentif_ahad_hari']) ? (float)$_POST['insentif_ahad_hari'] : ($ahad_full + ($ahad_half * 0.5));
$insentif_ahad_total = $insentif_ahad_nominal * $insentif_ahad_hari;

// Keterlambatan (AUTO - Potongan)
$keterlambatan_nominal = (float)$_POST['keterlambatan_nominal'];
$keterlambatan_jumlah = isset($_POST['keterlambatan_jumlah']) ? (float)$_POST['keterlambatan_jumlah'] : ($absensi['total_terlambat'] ?? 0);
$keterlambatan_total = $keterlambatan_nominal * $keterlambatan_jumlah;

// Digenapkan
$digenapkan = (float)$_POST['digenapkan'];

// Calculate penghasilan extra
$penghasilan_extra_total = 0;
if (!empty($_POST['penghasilan_nom'])) {
    foreach ($_POST['penghasilan_nom'] as $nom) {
        $penghasilan_extra_total += (float)$nom;
    }
}

// Calculate potongan extra
$potongan_extra_total = 0;
if (!empty($_POST['potongan_nom'])) {
    foreach ($_POST['potongan_nom'] as $nom) {
        $potongan_extra_total += (float)$nom;
    }
}

// Total penghasilan
$total_penghasilan = $gaji_pokok + $tunjangan_cs + $akomodasi + 
                     $transport_total + $overtime_total + $insentif_ahad_total +
                     $penghasilan_extra_total;

// Total potongan
$total_potongan = $keterlambatan_total + $potongan_extra_total;

// Gaji bersih
$gaji_bersih = $total_penghasilan - $total_potongan + $digenapkan;

// Start transaction
$conn->begin_transaction();

try {
    if ($is_edit && $slip_id) {
        // Cek lock 5 hari
        $stmt_check = $conn->prepare("SELECT created_at FROM slip_gaji WHERE id = ?");
        $stmt_check->bind_param("i", $slip_id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if (!empty($res_check['created_at'])) {
            if ((time() - strtotime($res_check['created_at'])) > (5 * 24 * 60 * 60)) {
                throw new Exception("Slip gaji sudah terkunci (melewati batas 5 hari) dan tidak dapat diubah lagi.");
            }
        }
        
        // UPDATE existing slip
        $stmt = $conn->prepare("
            UPDATE slip_gaji SET
                bulan = ?, tahun = ?, tanggal_cetak = NOW(),
                gaji_pokok = ?, tunjangan_cs = ?, akomodasi = ?,
                transport_nominal = ?, transport_hari = ?, transport_total = ?,
                overtime_nominal = ?, overtime_jam = ?, overtime_total = ?,
                insentif_ahad_nominal = ?, insentif_ahad_hari = ?, insentif_ahad_total = ?,
                keterlambatan_nominal = ?, keterlambatan_jumlah = ?, keterlambatan_total = ?,
                total_penghasilan = ?, total_potongan = ?,
                digenapkan = ?, gaji_bersih = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("iidddddddddddddddddddi",
            $bulan, $tahun,
            $gaji_pokok, $tunjangan_cs, $akomodasi,
            $transport_nominal, $transport_hari, $transport_total,
            $overtime_nominal, $overtime_jam, $overtime_total,
            $insentif_ahad_nominal, $insentif_ahad_hari, $insentif_ahad_total,
            $keterlambatan_nominal, $keterlambatan_jumlah, $keterlambatan_total,
            $total_penghasilan, $total_potongan,
            $digenapkan, $gaji_bersih,
            $slip_id
        );
        $stmt->execute();
        $stmt->close();
        
        // Delete old extras
        $stmt_del1 = $conn->prepare("DELETE FROM slip_gaji_penghasilan WHERE id_slip_gaji = ?");
        $stmt_del1->bind_param("i", $slip_id);
        $stmt_del1->execute();
        $stmt_del1->close();
        
        $stmt_del2 = $conn->prepare("DELETE FROM slip_gaji_potongan WHERE id_slip_gaji = ?");
        $stmt_del2->bind_param("i", $slip_id);
        $stmt_del2->execute();
        $stmt_del2->close();
        
    } else {
        // INSERT new slip
        $stmt = $conn->prepare("
            INSERT INTO slip_gaji (
                id_karyawan, bulan, tahun,
                gaji_pokok, tunjangan_cs, akomodasi,
                transport_nominal, transport_hari, transport_total,
                overtime_nominal, overtime_jam, overtime_total,
                insentif_ahad_nominal, insentif_ahad_hari, insentif_ahad_total,
                keterlambatan_nominal, keterlambatan_jumlah, keterlambatan_total,
                total_penghasilan, total_potongan,
                digenapkan, gaji_bersih,
                created_by, dibuat_oleh
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?
            )
        ");
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];
        
        $stmt->bind_param("siidddddddddddddddddddis",
            $id_karyawan, $bulan, $tahun,
            $gaji_pokok, $tunjangan_cs, $akomodasi,
            $transport_nominal, $transport_hari, $transport_total,
            $overtime_nominal, $overtime_jam, $overtime_total,
            $insentif_ahad_nominal, $insentif_ahad_hari, $insentif_ahad_total,
            $keterlambatan_nominal, $keterlambatan_jumlah, $keterlambatan_total,
            $total_penghasilan, $total_potongan,
            $digenapkan, $gaji_bersih,
            $user_id, $username
        );
        $stmt->execute();
        $slip_id = $conn->insert_id;
        $stmt->close();
    }
    
    // Insert penghasilan extra
    if (!empty($_POST['penghasilan_ket'])) {
        $stmt = $conn->prepare("INSERT INTO slip_gaji_penghasilan (id_slip_gaji, keterangan, rate, qty, nominal, urutan) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($_POST['penghasilan_ket'] as $idx => $ket) {
            if (!empty($ket)) {
                $rate = !empty($_POST['penghasilan_rate'][$idx]) ? (float)str_replace(['.', ','], ['', '.'], $_POST['penghasilan_rate'][$idx]) : 0;
                $qty = !empty($_POST['penghasilan_qty'][$idx]) ? (float)$_POST['penghasilan_qty'][$idx] : 1;
                $nom = (float)$_POST['penghasilan_nom'][$idx];
                
                if ($rate == 0) $rate = $nom;
                
                $stmt->bind_param("isdddi", $slip_id, $ket, $rate, $qty, $nom, $idx);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
    
    // Insert potongan extra
    if (!empty($_POST['potongan_ket'])) {
        $stmt = $conn->prepare("INSERT INTO slip_gaji_potongan (id_slip_gaji, keterangan, rate, qty, nominal, urutan) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($_POST['potongan_ket'] as $idx => $ket) {
            if (!empty($ket)) {
                $rate = !empty($_POST['potongan_rate'][$idx]) ? (float)str_replace(['.', ','], ['', '.'], $_POST['potongan_rate'][$idx]) : 0;
                $qty = !empty($_POST['potongan_qty'][$idx]) ? (float)$_POST['potongan_qty'][$idx] : 1;
                $nom = (float)$_POST['potongan_nom'][$idx];
                
                if ($rate == 0) $rate = $nom;
                
                $stmt->bind_param("isdddi", $slip_id, $ket, $rate, $qty, $nom, $idx);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
    
    // Commit
    $conn->commit();
    
    if (isset($_POST['is_ajax'])) {
        echo json_encode(['status' => 'success', 'message' => "Slip gaji berhasil " . ($is_edit ? "diupdate" : "disimpan") . "!"]);
        exit();
    }
    
    $_SESSION['success_message'] = "Slip gaji berhasil " . ($is_edit ? "diupdate" : "disimpan") . "!";
    redirect("slip_gaji_form.php?id_karyawan=$id_karyawan&bulan=$bulan&tahun=$tahun");
    
} catch (Exception $e) {
    $conn->rollback();
    
    if (isset($_POST['is_ajax'])) {
        echo json_encode(['status' => 'error', 'message' => "Error: " . $e->getMessage()]);
        exit();
    }
    
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    redirect("slip_gaji_form.php?id_karyawan=$id_karyawan&bulan=$bulan&tahun=$tahun");
}
?>
