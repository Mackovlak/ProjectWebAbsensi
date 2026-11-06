<?php
/** Shared database reads for Laporan previews and Excel downloads. Call after authorization. */
function laporanKaryawan($conn, $cabang_id, $status) {
    $query = "SELECT k.id_karyawan as id, k.nama_karyawan as nama_lengkap, k.jenis_kelamin, IFNULL(GROUP_CONCAT(u.username SEPARATOR ', '), '-') as username, j.nama_jabatan, c.nama_cabang, k.tanggal_resign, k.status 
              FROM karyawan k 
              LEFT JOIN jabatan j ON k.id_jabatan = j.id 
              LEFT JOIN cabang c ON k.id_cabang = c.id 
              LEFT JOIN users u ON k.id_karyawan = u.id_karyawan
              WHERE 1=1";
    $params = [];
    $types = "";

    if ($status !== 'all') {
        $query .= " AND k.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($cabang_id !== 'all') {
        $query .= " AND k.id_cabang = ?";
        $params[] = $cabang_id;
        $types .= "i";
    }
    $query .= " GROUP BY k.id ORDER BY c.nama_cabang ASC, k.nama_karyawan ASC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

function laporanAbsensiLog($conn, $cabang_id, $start_date, $end_date) {
    $query = "SELECT a.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', a.id_karyawan, ')')) as nama_lengkap, k.id_cabang, c.nama_cabang, TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) AS durasi_menit
              FROM absensi a 
              LEFT JOIN karyawan k ON a.id_karyawan = k.id_karyawan 
              LEFT JOIN cabang c ON k.id_cabang = c.id
              WHERE a.tanggal BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $types = "ss";

    if ($cabang_id !== 'all') {
        $query .= " AND k.id_cabang = ?";
        $params[] = $cabang_id;
        $types .= "i";
    }
    $query .= " ORDER BY a.tanggal DESC, k.nama_karyawan ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res;
}

function laporanAbsensiStatistik($conn, $cabang_id, $start_date, $end_date) {
    $mysql_dow_overtime = daftarHariOvertimeMysqlDow($conn);
    $query = "SELECT k.id_karyawan as id, k.nama_karyawan as nama_lengkap, c.nama_cabang, 
              SUM(CASE WHEN a.keterangan = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
              SUM(CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Tepat Waktu' THEN 1 ELSE 0 END) as total_tepat_waktu,
              SUM(CASE WHEN a.keterangan = 'Hadir' AND a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) as total_telat,
              SUM(CASE 
                  WHEN a.keterangan = 'Hadir' AND (
                      (a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00' AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) < 330)
                      OR ((a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00') AND a.tanggal < CURDATE())
                  )
                  THEN 1 ELSE 0 
              END) as total_setengah_hari,
              SUM(CASE 
                  WHEN a.jam_pulang IS NOT NULL AND a.jam_pulang != '00:00:00'
                  AND TIMESTAMPDIFF(MINUTE, a.jam_masuk, a.jam_pulang) >= 330
                  -- Lembur hanya dihitung bila tanggalnya tercakup izin lembur berstatus Disetujui
                  AND EXISTS (SELECT 1 FROM pengajuan_lembur pl WHERE pl.id_karyawan = a.id_karyawan
                      AND pl.status = 'Disetujui' AND a.tanggal BETWEEN pl.tanggal_mulai AND pl.tanggal_selesai)
                  AND a.jam_pulang > (
                      SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang
                      ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1
                  )
                  THEN 
                      CASE WHEN (TIME_TO_SEC(a.jam_pulang) - TIME_TO_SEC((SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1))) < 2100 THEN 0.5 ELSE 1 END
                  ELSE 0 
              END) as total_overtime,
              SUM(CASE WHEN DAYOFWEEK(a.tanggal) IN ($mysql_dow_overtime) AND a.keterangan = 'Hadir' THEN 1 ELSE 0 END) as total_minggu,
              SUM(CASE WHEN a.keterangan = 'OFF' THEN 1 ELSE 0 END) as total_off,
              SUM(CASE WHEN a.keterangan = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
              SUM(CASE WHEN a.keterangan = 'Cuti' THEN 1 ELSE 0 END) as total_cuti,
              SUM(CASE WHEN a.keterangan = 'Dinas Luar' THEN 1 ELSE 0 END) as total_dinas_luar,
              SUM(CASE WHEN a.keterangan = 'Alpha' THEN 1 ELSE 0 END) as total_alpha
              FROM karyawan k 
              LEFT JOIN cabang c ON k.id_cabang = c.id
              LEFT JOIN absensi a ON k.id_karyawan = a.id_karyawan AND a.tanggal BETWEEN ? AND ? 
              WHERE 1=1";

    $params = [$start_date, $end_date];
    $types = "ss";

    if ($cabang_id !== 'all') {
        $query .= " AND k.id_cabang = ?";
        $params[] = $cabang_id;
        $types .= "i";
    }
    $query .= " GROUP BY k.id_karyawan, k.nama_karyawan, c.nama_cabang ORDER BY k.nama_karyawan ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res;
}

function laporanGaji($conn, $tipe, $cabang_id, $user_id, $start_date, $end_date) {
    if ($tipe === 'rekap_gaji_divisi') {
        $query = "SELECT sg.bulan, sg.tahun, c.nama_cabang, 
                  SUM(sg.total_penghasilan) as total_penghasilan, 
                  SUM(sg.total_potongan) as total_potongan, 
                  SUM(sg.gaji_bersih) as gaji_bersih,
                  COUNT(*) as jumlah_slip,
                  SUM(CASE WHEN sg.status_admin_acc = 1 THEN 1 ELSE 0 END) as admin_disetujui,
                  SUM(CASE WHEN sg.status_owner_acc = 1 THEN 1 ELSE 0 END) as owner_disetujui 
                  FROM slip_gaji sg 
                  LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan 
                  LEFT JOIN cabang c ON k.id_cabang = c.id 
                  WHERE STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
                  AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')";
    } else {
        $query = "SELECT sg.*, COALESCE(k.nama_karyawan, CONCAT('Karyawan Dihapus (', sg.id_karyawan, ')')) as nama_lengkap, c.nama_cabang 
                  FROM slip_gaji sg 
                  LEFT JOIN karyawan k ON sg.id_karyawan = k.id_karyawan 
                  LEFT JOIN cabang c ON k.id_cabang = c.id 
                  WHERE STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') >= DATE_FORMAT(?, '%Y-%m-01') 
                  AND STR_TO_DATE(CONCAT(sg.tahun, '-', LPAD(sg.bulan, 2, '0'), '-01'), '%Y-%m-%d') <= DATE_FORMAT(?, '%Y-%m-01')";
    }

    $params = [$start_date, $end_date];
    $types = "ss";

    if (in_array($tipe, ['lap_gaji_divisi', 'rekap_gaji_divisi']) && !empty($cabang_id) && $cabang_id !== 'all') {
        $query .= " AND k.id_cabang = ?";
        $params[] = $cabang_id;
        $types .= "i";
    } elseif ($tipe === 'per_karyawan' && !empty($user_id)) {
        $query .= " AND sg.id_karyawan = ?";
        $params[] = $user_id;
        $types .= "s";
    }

    if ($tipe === 'per_karyawan') {
        $query .= " ORDER BY sg.tahun ASC, sg.bulan ASC";
    } elseif ($tipe === 'rekap_gaji_divisi') {
        $query .= " GROUP BY sg.tahun, sg.bulan, c.id, c.nama_cabang ORDER BY c.nama_cabang ASC, sg.tahun ASC, sg.bulan ASC";
    } else {
        $query .= " ORDER BY c.nama_cabang ASC, k.nama_karyawan ASC, sg.tahun ASC, sg.bulan ASC";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res;
}

// Fungsi untuk mendeteksi shift
if (!function_exists('detectCorrectShift')) {
    function detectCorrectShift($jam_masuk_karyawan, $shifts_data) {
        if (empty($jam_masuk_karyawan) || empty($shifts_data)) {
            return null;
        }
        $jam_masuk_ts = strtotime($jam_masuk_karyawan);
        $best_match = null;
        $min_diff = PHP_INT_MAX;
        foreach ($shifts_data as $shift) {
            $shift_masuk_ts = strtotime($shift['jam_masuk_akhir']);
            $diff = abs($jam_masuk_ts - $shift_masuk_ts);
            if ($diff < $min_diff) {
                $min_diff = $diff;
                $best_match = $shift;
            }
        }
        return $best_match;
    }
}

function laporanStatusPulang($row, $all_shifts) {
    $status_pulang = '-';
    $shifts_data = $all_shifts[$row['id_cabang']] ?? [];
    $detected_shift = detectCorrectShift($row['jam_masuk'], $shifts_data);
    $jam_pulang_standar = $detected_shift ? $detected_shift['jam_pulang'] : null;

    if ($row['jam_pulang'] && $row['jam_pulang'] != '00:00:00') {
        $durasi_menit = $row['durasi_menit'];
        if ($durasi_menit !== null && $durasi_menit > 0) {
            if ($durasi_menit < 330) {
                $status_pulang = 'Setengah Hari';
            } elseif (!empty($jam_pulang_standar) && strtotime($row['jam_pulang']) > strtotime($jam_pulang_standar)) {
                $status_pulang = 'Over Time';
            } else {
                $status_pulang = 'Normal';
            }
        }
    } else if ($row['keterangan'] == 'Hadir' && strtotime($row['tanggal']) < strtotime('today')) {
        $status_pulang = 'Belum Absen Pulang = Set. Hari';
    }
    if ($row['keterangan'] !== 'Hadir') return '-';
    return $status_pulang === '-' ? 'Belum Pulang' : $status_pulang;
}
