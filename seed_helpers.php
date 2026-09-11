<?php
/**
 * ==========================================
 * HELPER SEED / BACKFILL ABSENSI
 * ==========================================
 * Dipakai bersama oleh seed_dummy_data.php (seed penuh: cabang, jam kerja,
 * karyawan, akun, DAN riwayat absensi) dan seed_backfill_absensi.php (HANYA
 * riwayat absensi, untuk karyawan/user yang SUDAH ada di database - dipakai
 * di database yang datanya sudah nyata/production, supaya dashboard &
 * laporan tidak kosong tanpa membuat karyawan/akun palsu).
 */

function ambilSettingSeed($conn, $key, $default) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

// Aritmatika detik-sejak-tengah-malam murni (tanpa strtotime/date yang
// timezone-aware) - mencampur strtotime(...UTC) dengan date() lokal
// (Asia/Jakarta, UTC+7) akan menggeser jam 7 jam dan membungkus ke tengah malam.
function jamKeDetik($jam) {
    list($h, $m, $s) = array_map('intval', explode(':', $jam));
    return $h * 3600 + $m * 60 + $s;
}
function detikKeJam($detik) {
    $detik = (($detik % 86400) + 86400) % 86400;
    return sprintf('%02d:%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60), $detik % 60);
}

function lokasiAcak($koordinat, $jakarta_fallback) {
    $base = $koordinat ?? $jakarta_fallback;
    // jitter kecil (~0-150m) supaya tidak semua titik identik tapi tetap dalam radius wajar
    $jitter_lat = (mt_rand(-150, 150) / 1000000) * 10; // ~0-0.0015 derajat
    $jitter_lng = (mt_rand(-150, 150) / 1000000) * 10;
    return round($base[0] + $jitter_lat, 8) . ',' . round($base[1] + $jitter_lng, 8);
}

/**
 * Backfill riwayat absensi untuk SEMUA karyawan status 'aktif' yang SUDAH
 * ada di database (tidak pernah membuat karyawan/user baru - itu urusan
 * seed_dummy_data.php, bukan fungsi ini). Melewati tanggal yang sudah punya
 * baris absensi apa pun sumbernya (kiosk asli, approval izin, atau backfill
 * sebelumnya), jadi tidak pernah menimpa/menduplikasi data asli.
 *
 * Dua aturan keamanan yang tidak bisa dimatikan lewat parameter:
 * - TIDAK PERNAH menyentuh hari ini. proses_absen.php menentukan mode
 *   masuk/pulang dari "apakah baris hari ini sudah ada", bukan dari
 *   jam_masuk-nya - backfill hari ini bisa membuat karyawan yang belum
 *   sempat absen dianggap sistem sudah "pulang", memblokir absen asli
 *   mereka. Rentang berhenti di kemarin.
 * - Baris yang dibuat SELALU ditandai is_manual_entry=1 + manual_entry_by
 *   (termasuk baris "Hadir", yang sebelumnya tidak ditandai sama sekali)
 *   supaya bisa dibedakan dari absensi kiosk sungguhan dan diaudit/
 *   dikecualikan secara manual di kemudian hari kalau perlu (mis. dari
 *   penghitungan slip gaji) - fungsi ini sendiri TIDAK mengecualikannya
 *   secara otomatis dari perhitungan mana pun.
 *
 * @return array{absensi:int, absensi_gagal:int}
 */
function backfillRiwayatAbsensi($conn, $confirmed, MigrationLog $log, string $sumberLabel, int $hariKeBelakang = 90, ?array $idKaryawanFilter = null) {
    $hasil = ['absensi' => 0, 'absensi_gagal' => 0];

    $hari_kerja = array_map('intval', explode(',', ambilSettingSeed($conn, 'hari_kerja', '1,2,3,4,5')));
    $hari_overtime = array_map('intval', explode(',', ambilSettingSeed($conn, 'hari_overtime', '6')));

    // Berhenti di KEMARIN, bukan hari ini - lihat catatan keamanan di docblock.
    $tanggal_akhir = date('Y-m-d', strtotime('-1 day'));
    $tanggal_awal = date('Y-m-d', strtotime("-{$hariKeBelakang} days"));

    $hari_libur_map = [];
    $stmt_libur = $conn->prepare("SELECT tanggal FROM hari_libur WHERE tanggal BETWEEN ? AND ?");
    $stmt_libur->bind_param("ss", $tanggal_awal, $tanggal_akhir);
    $stmt_libur->execute();
    $res_libur = $stmt_libur->get_result();
    while ($row = $res_libur->fetch_assoc()) {
        $hari_libur_map[$row['tanggal']] = true;
    }
    $stmt_libur->close();

    // Filter opsional ke id_karyawan tertentu - kalau diisi, hanya karyawan
    // itu yang diproses (tetap harus status 'aktif'). ID yang tidak
    // ditemukan/tidak aktif dilaporkan, bukan dilewati diam-diam.
    $idKaryawanFilter = $idKaryawanFilter !== null
        ? array_values(array_unique(array_filter(array_map('trim', $idKaryawanFilter))))
        : null;

    $roster = [];
    $sql_roster = "SELECT k.id_karyawan, k.id_cabang, k.id_jabatan, COALESCE(j.overtime_sabtu, 0) AS overtime_sabtu
                   FROM karyawan k
                   LEFT JOIN jabatan j ON j.id = k.id_jabatan
                   WHERE k.status = 'aktif'";
    if ($idKaryawanFilter !== null && !empty($idKaryawanFilter)) {
        $placeholders = implode(',', array_fill(0, count($idKaryawanFilter), '?'));
        $sql_roster .= " AND k.id_karyawan IN ($placeholders)";
        $stmt_roster = $conn->prepare($sql_roster);
        $stmt_roster->bind_param(str_repeat('s', count($idKaryawanFilter)), ...$idKaryawanFilter);
        $stmt_roster->execute();
        $res_roster = $stmt_roster->get_result();
        while ($row = $res_roster->fetch_assoc()) {
            $roster[] = $row;
        }
        $stmt_roster->close();

        $ditemukan = array_column($roster, 'id_karyawan');
        $tidakDitemukan = array_diff($idKaryawanFilter, $ditemukan);
        if (!empty($tidakDitemukan)) {
            $log->warn("ID karyawan berikut dilewati (tidak ditemukan atau statusnya bukan 'aktif'): "
                . implode(', ', array_map('htmlspecialchars', $tidakDitemukan)));
        }
    } else {
        $res_roster = $conn->query($sql_roster);
        while ($row = $res_roster->fetch_assoc()) {
            $roster[] = $row;
        }
    }

    if (empty($roster)) {
        $log->error("Tidak ada karyawan aktif yang cocok untuk di-backfill - tidak ada yang dilakukan.");
        return $hasil;
    }

    $jam_kerja_cabang = [];
    $res_jk = $conn->query("SELECT id_cabang, jam_masuk_akhir, jam_pulang FROM jam_kerja");
    while ($row = $res_jk->fetch_assoc()) {
        if (!isset($jam_kerja_cabang[$row['id_cabang']])) {
            $jam_kerja_cabang[$row['id_cabang']] = ['masuk_akhir' => $row['jam_masuk_akhir'], 'pulang' => $row['jam_pulang']];
        }
    }

    $koordinat_cabang = [];
    $res_koor = $conn->query("SELECT id, latitude, longitude FROM cabang");
    while ($row = $res_koor->fetch_assoc()) {
        $koordinat_cabang[$row['id']] = ($row['latitude'] !== null && $row['longitude'] !== null)
            ? [(float)$row['latitude'], (float)$row['longitude']]
            : null;
    }
    $JAKARTA_FALLBACK = [-6.200000, 106.816666];

    if (!$confirmed) {
        $hariKerjaCount = 0;
        $d = strtotime($tanggal_awal);
        $akhir_ts = strtotime($tanggal_akhir);
        while ($d <= $akhir_ts) {
            $wd = (int)date('N', $d);
            if (in_array($wd, $hari_kerja, true) && !isset($hari_libur_map[date('Y-m-d', $d)])) $hariKerjaCount++;
            $d = strtotime('+1 day', $d);
        }
        $log->warn("[Dry-run] Akan menghasilkan riwayat absensi ~{$hariKerjaCount} hari kerja x " . count($roster)
            . " karyawan aktif (tanggal {$tanggal_awal} s.d. {$tanggal_akhir}, tidak termasuk hari ini), "
            . "melewati tanggal yang sudah punya data.");
        return $hasil;
    }

    // Ambil semua tanggal yang SUDAH ada di rentang ini, per karyawan, sekali saja (hindari query per hari)
    $existing = [];
    $stmt_exist = $conn->prepare("SELECT id_karyawan, tanggal FROM absensi WHERE tanggal BETWEEN ? AND ?");
    $stmt_exist->bind_param("ss", $tanggal_awal, $tanggal_akhir);
    $stmt_exist->execute();
    $res_exist = $stmt_exist->get_result();
    while ($row = $res_exist->fetch_assoc()) {
        $existing[$row['id_karyawan'] . '|' . $row['tanggal']] = true;
    }
    $stmt_exist->close();

    $stmt_hadir = $conn->prepare(
        "INSERT INTO absensi
            (id_karyawan, tanggal, jam_masuk, jam_pulang, lokasi_masuk, lokasi_pulang,
             keterangan, status_masuk, face_verified, face_confidence, input_method,
             is_manual_entry, manual_entry_by)
         VALUES (?, ?, ?, ?, ?, ?, 'Hadir', ?, 1, ?, 'qr_scan', 1, ?)"
    );
    $stmt_khusus = $conn->prepare(
        "INSERT INTO absensi (id_karyawan, tanggal, keterangan, is_manual_entry, manual_entry_by)
         VALUES (?, ?, ?, 1, ?)"
    );

    $conn->begin_transaction();
    try {
        foreach ($roster as $r) {
            $id_karyawan = $r['id_karyawan'];
            $shift = $jam_kerja_cabang[$r['id_cabang']] ?? ['masuk_akhir' => '08:00:00', 'pulang' => '17:00:00'];
            $koor = $koordinat_cabang[$r['id_cabang']] ?? null;

            $d = strtotime($tanggal_awal);
            $akhir_ts = strtotime($tanggal_akhir);
            while ($d <= $akhir_ts) {
                $tanggal = date('Y-m-d', $d);
                $key = $id_karyawan . '|' . $tanggal;
                $d = strtotime('+1 day', $d);

                if (isset($existing[$key])) continue; // jangan duplikasi data yang sudah ada
                if (isset($hari_libur_map[$tanggal])) continue; // hari libur: tidak ada baris

                $wd = (int)date('N', strtotime($tanggal));
                $roll = mt_rand(1, 100);

                if (in_array($wd, $hari_kerja, true)) {
                    // Hari kerja normal: distribusi status
                    if ($roll <= 78) {
                        // Hadir (sebagian Terlambat)
                        $masuk_akhir_detik = jamKeDetik($shift['masuk_akhir']);
                        $pulang_detik = jamKeDetik($shift['pulang']);

                        $telat = mt_rand(1, 100) <= 18;
                        if ($telat) {
                            $jam_masuk = detikKeJam($masuk_akhir_detik + mt_rand(60, 3600)); // 1-60 menit lewat batas
                            $status_masuk = 'Terlambat';
                        } else {
                            $jam_masuk = detikKeJam($masuk_akhir_detik - mt_rand(0, 1800)); // sampai 30 menit lebih awal
                            $status_masuk = 'Tepat Waktu';
                        }
                        $jam_pulang = detikKeJam($pulang_detik + mt_rand(-600, 2400)); // -10 menit s.d. +40 menit

                        $lokasi_masuk = lokasiAcak($koor, $JAKARTA_FALLBACK);
                        $lokasi_pulang = lokasiAcak($koor, $JAKARTA_FALLBACK);
                        $confidence = round(mt_rand(6500, 9800) / 100, 2);

                        $stmt_hadir->bind_param(
                            "sssssssds",
                            $id_karyawan, $tanggal, $jam_masuk, $jam_pulang,
                            $lokasi_masuk, $lokasi_pulang, $status_masuk, $confidence, $sumberLabel
                        );
                        $ok = $stmt_hadir->execute();
                    } else if ($roll <= 84) {
                        $ket = 'Sakit';
                        $stmt_khusus->bind_param("ssss", $id_karyawan, $tanggal, $ket, $sumberLabel);
                        $ok = $stmt_khusus->execute();
                    } else if ($roll <= 89) {
                        $ket = 'Izin';
                        $stmt_khusus->bind_param("ssss", $id_karyawan, $tanggal, $ket, $sumberLabel);
                        $ok = $stmt_khusus->execute();
                    } else if ($roll <= 93) {
                        $ket = 'Cuti';
                        $stmt_khusus->bind_param("ssss", $id_karyawan, $tanggal, $ket, $sumberLabel);
                        $ok = $stmt_khusus->execute();
                    } else if ($roll <= 97) {
                        $ket = 'Alpha';
                        $stmt_khusus->bind_param("ssss", $id_karyawan, $tanggal, $ket, $sumberLabel);
                        $ok = $stmt_khusus->execute();
                    } else {
                        // sisa 3%: hari kerja tanpa baris sama sekali (belum sempat absen input apa pun)
                        continue;
                    }
                    if ($ok) { $hasil['absensi']++; } else { $hasil['absensi_gagal']++; }
                } else if (in_array($wd, $hari_overtime, true)) {
                    // Sabtu/hari overtime: hanya untuk jabatan yang eligible, dan tidak selalu masuk
                    if ((int)$r['overtime_sabtu'] === 1 && mt_rand(1, 100) <= 50) {
                        $masuk_detik = jamKeDetik('08:00:00') + mt_rand(0, 3600);
                        $durasi_detik = mt_rand(2 * 3600, 6 * 3600); // 2-6 jam lembur, durasi aktual
                        $jam_masuk = detikKeJam($masuk_detik);
                        $jam_pulang = detikKeJam($masuk_detik + $durasi_detik);
                        $status_masuk = 'Tepat Waktu'; // Sabtu tidak dihitung Terlambat

                        $lokasi_masuk = lokasiAcak($koor, $JAKARTA_FALLBACK);
                        $lokasi_pulang = lokasiAcak($koor, $JAKARTA_FALLBACK);
                        $confidence = round(mt_rand(6500, 9800) / 100, 2);

                        $stmt_hadir->bind_param(
                            "sssssssds",
                            $id_karyawan, $tanggal, $jam_masuk, $jam_pulang,
                            $lokasi_masuk, $lokasi_pulang, $status_masuk, $confidence, $sumberLabel
                        );
                        if ($stmt_hadir->execute()) { $hasil['absensi']++; } else { $hasil['absensi_gagal']++; }
                    }
                }
                // Hari selain hari kerja & hari overtime (mis. Minggu): tidak ada baris.
            }
        }
        $conn->commit();
        $log->ok("Riwayat absensi berhasil dibuat: <b>{$hasil['absensi']}</b> baris baru untuk " . count($roster)
            . " karyawan aktif (tanggal {$tanggal_awal} s.d. {$tanggal_akhir}, tidak termasuk hari ini).");
        if ($hasil['absensi_gagal'] > 0) {
            $log->error("{$hasil['absensi_gagal']} baris absensi GAGAL diinsert (lihat error mysqli): " . $conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        $log->error("Gagal generate absensi, rollback: " . $e->getMessage());
    }
    $stmt_hadir->close();
    $stmt_khusus->close();

    return $hasil;
}
