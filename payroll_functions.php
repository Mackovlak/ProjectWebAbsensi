<?php

const PAYROLL_SCHEME_STANDARD = 'STANDARD_ATTENDANCE';
const PAYROLL_SCHEME_JAVAG_FLAT = 'JAVAG_FLAT';

function normalisasiSkemaPayroll($scheme)
{
    return $scheme === PAYROLL_SCHEME_JAVAG_FLAT
        ? PAYROLL_SCHEME_JAVAG_FLAT
        : PAYROLL_SCHEME_STANDARD;
}

function getSalaryProfileForPeriod($conn, $idKaryawan, $bulan, $tahun)
{
    $tanggalPeriode = sprintf('%04d-%02d-01', (int)$tahun, (int)$bulan);
    $stmt = $conn->prepare("SELECT * FROM employee_salary_profile
        WHERE id_karyawan = ?
          AND effective_from <= ?
          AND (effective_to IS NULL OR effective_to >= ?)
        ORDER BY effective_from DESC, id DESC LIMIT 1");
    $stmt->bind_param('sss', $idKaryawan, $tanggalPeriode, $tanggalPeriode);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $profile ?: null;
}

function saveAnnualSalaryProfile($conn, $idKaryawan, $tahun, $scheme, $gajiPokok, $transportTetap, $uangMakanTetap, $userId)
{
    $effectiveFrom = sprintf('%04d-01-01', (int)$tahun);
    $effectiveTo = sprintf('%04d-12-31', (int)$tahun);
    $scheme = normalisasiSkemaPayroll($scheme);
    $stmt = $conn->prepare("INSERT INTO employee_salary_profile
        (id_karyawan, scheme_code, effective_from, effective_to, gaji_pokok, transport_tetap, uang_makan_tetap, approved_by, approved_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            scheme_code = VALUES(scheme_code), effective_to = VALUES(effective_to),
            gaji_pokok = VALUES(gaji_pokok), transport_tetap = VALUES(transport_tetap),
            uang_makan_tetap = VALUES(uang_makan_tetap), approved_by = VALUES(approved_by),
            approved_at = NOW(), updated_at = NOW()" );
    $stmt->bind_param('ssssdddi', $idKaryawan, $scheme, $effectiveFrom, $effectiveTo, $gajiPokok, $transportTetap, $uangMakanTetap, $userId);
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        throw new Exception('Gagal menyimpan profil gaji: ' . $message);
    }
    $profileId = $conn->insert_id;
    $stmt->close();

    if (!$profileId) {
        $stmt = $conn->prepare('SELECT id FROM employee_salary_profile WHERE id_karyawan = ? AND effective_from = ? LIMIT 1');
        $stmt->bind_param('ss', $idKaryawan, $effectiveFrom);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $profileId = (int)($row['id'] ?? 0);
    }
    return $profileId ?: null;
}
