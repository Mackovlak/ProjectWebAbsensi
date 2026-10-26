<?php
/**
 * ==========================================
 * PROSES PENGAJUAN IZIN LEMBUR
 * ==========================================
 * Handler tunggal untuk seluruh aksi pengajuan lembur, mengikuti pola
 * dispatch-by-POST-key milik master_process.php / proses_pengajuan_izin.php.
 *
 * Beda dari pengajuan_izin: tidak ada materialisasi absensi saat disetujui -
 * pengajuan ini cuma IZIN untuk boleh lembur (dicek proses_absen.php secara
 * implisit lewat jam_pulang aktual, dan dihitung jamnya lewat
 * hitungJamLemburDisetujui() saat slip gaji dibuat). Tidak memotong
 * jatah_cuti sama sekali.
 *
 * Aksi yang didukung:
 *  - ajukan_lembur : karyawan (staff/supervisor/admin) mengajukan izin lembur
 *  - batal_lembur  : karyawan membatalkan pengajuannya sendiri
 *  - review_lembur : supervisor/admin/owner menyetujui atau menolak
 *  - tugaskan_lembur   : supervisor/admin menugaskan lembur ke satu/lebih karyawan
 *  - respon_penugasan  : karyawan menerima atau menolak (dengan alasan) penugasan
 *  - tarik_penugasan   : supervisor/admin menarik penugasan yang belum berjalan
 */

require 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(dashboardUntukRole($_SESSION['role'] ?? 'staff'));
}

verifyCSRFToken($_POST['csrf_token'] ?? '');

$is_self_service_action = isset($_POST['ajukan_lembur']) || isset($_POST['batal_lembur']) || isset($_POST['respon_penugasan']);

kedaluwarsakanPenugasanLembur($conn);
$redirect_default = $is_self_service_action ? 'staff_pengajuan_lembur.php' : 'kelola_pengajuan_lembur.php';

function requirePemilikLembur() {
    if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['staff', 'supervisor', 'admin'], true)) {
        redirect('login.php');
    }
    if (empty($_SESSION['id_karyawan'])) {
        $_SESSION['error_message'] = 'Akun Anda belum tertaut dengan data karyawan.';
        redirect(dashboardUntukRole($_SESSION['role']));
    }
}

/**
 * Simpan flash message lalu kembali ke halaman asal.
 */
function selesai($pesan, $sukses = true, $redirect = null) {
    global $redirect_default;
    if ($sukses) {
        $_SESSION['success_message'] = $pesan;
    } else {
        $_SESSION['error_message'] = $pesan;
    }
    header("Location: " . ($redirect ?: $redirect_default));
    exit();
}

// ==========================================================
// AJUKAN LEMBUR (staff / supervisor / admin yang tertaut ke karyawan)
// ==========================================================
if (isset($_POST['ajukan_lembur'])) {
    requirePemilikLembur();

    $id_karyawan = $_SESSION['id_karyawan'] ?? '';
    if (empty($id_karyawan)) {
        selesai("❌ Akun Anda belum tertaut dengan data karyawan. Hubungi admin.", false);
    }

    $tanggal_mulai   = sanitizeInput($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = sanitizeInput($_POST['tanggal_selesai'] ?? '');
    $keperluan       = sanitizeInput($_POST['keperluan'] ?? '');

    if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
        selesai("❌ Tanggal mulai dan tanggal selesai wajib diisi.", false);
    }

    $cek_mulai   = DateTime::createFromFormat('Y-m-d', $tanggal_mulai);
    $cek_selesai = DateTime::createFromFormat('Y-m-d', $tanggal_selesai);
    if (!$cek_mulai || $cek_mulai->format('Y-m-d') !== $tanggal_mulai ||
        !$cek_selesai || $cek_selesai->format('Y-m-d') !== $tanggal_selesai) {
        selesai("❌ Format tanggal tidak valid.", false);
    }

    if ($tanggal_mulai > $tanggal_selesai) {
        selesai("❌ Tanggal mulai tidak boleh melewati tanggal selesai.", false);
    }

    if (strlen($keperluan) < 5) {
        selesai("❌ Keperluan/proyek lembur wajib diisi minimal 5 karakter agar reviewer paham konteksnya.", false);
    }

    // Izin lembur harus diajukan ke depan, bukan mundur - beda dari Sakit
    // yang boleh diurus setelah kejadian.
    if ($tanggal_mulai < date('Y-m-d')) {
        selesai("❌ Pengajuan lembur harus diajukan sebelum tanggal pelaksanaan, tidak bisa mundur.", false);
    }

    // Batasi rentang supaya tidak ada pengajuan ekstrem (mis. lembur "sebulan penuh").
    $selisih_hari = (strtotime($tanggal_selesai) - strtotime($tanggal_mulai)) / 86400 + 1;
    if ($selisih_hari > 31) {
        selesai("❌ Rentang pengajuan lembur maksimal 31 hari dalam satu permohonan.", false);
    }

    $bentrok = cekTumpangTindihLembur($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai);
    if ($bentrok) {
        $rentang = formatRentangTanggal($bentrok['tanggal_mulai'], $bentrok['tanggal_selesai']);
        selesai("❌ Rentang tanggal bertabrakan dengan pengajuan lembur ({$bentrok['status']}) pada {$rentang}.", false);
    }

    $stmt_cabang = $conn->prepare("SELECT id_cabang FROM karyawan WHERE id_karyawan = ?");
    $stmt_cabang->bind_param("s", $id_karyawan);
    $stmt_cabang->execute();
    $row_cabang = $stmt_cabang->get_result()->fetch_assoc();
    $stmt_cabang->close();
    $id_cabang = $row_cabang ? (int)$row_cabang['id_cabang'] : null;

    $stmt = $conn->prepare("INSERT INTO pengajuan_lembur
        (id_karyawan, tanggal_mulai, tanggal_selesai, keperluan, status, id_cabang)
        VALUES (?, ?, ?, ?, 'Pending', ?)");
    $stmt->bind_param("ssssi", $id_karyawan, $tanggal_mulai, $tanggal_selesai, $keperluan, $id_cabang);

    if ($stmt->execute()) {
        $id_baru = $stmt->insert_id;
        $stmt->close();

        $rentang = formatRentangTanggal($tanggal_mulai, $tanggal_selesai);
        logActivity($conn, 'ajukan_lembur', "Mengajukan izin lembur {$rentang} - #{$id_baru}", $_SESSION['user_id']);

        selesai("✅ Pengajuan izin lembur untuk {$rentang} berhasil dikirim dan menunggu persetujuan atasan.");
    } else {
        $stmt->close();
        selesai("❌ Gagal menyimpan pengajuan. Silakan coba lagi.", false);
    }
}

// ==========================================================
// BATALKAN PENGAJUAN (pemilik pengajuan)
// ==========================================================
if (isset($_POST['batal_lembur'])) {
    requirePemilikLembur();

    $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
    $id_karyawan  = $_SESSION['id_karyawan'] ?? '';

    if ($id_pengajuan <= 0) {
        selesai("❌ Data pengajuan tidak valid.", false);
    }

    $stmt = $conn->prepare("SELECT * FROM pengajuan_lembur WHERE id = ? AND id_karyawan = ?");
    $stmt->bind_param("is", $id_pengajuan, $id_karyawan);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        selesai("❌ Pengajuan tidak ditemukan atau bukan milik Anda.", false);
    }

    // Penugasan dari atasan dijawab lewat Terima/Tolak; setelah diterima,
    // pembatalan harus lewat atasan yang menugaskan (tarik_penugasan).
    if (($data['sumber'] ?? 'Pengajuan') === 'Penugasan') {
        selesai("❌ Lembur ini adalah penugasan dari atasan. Gunakan tombol Tolak, atau hubungi atasan Anda untuk membatalkannya.", false);
    }

    if (!in_array($data['status'], ['Pending', 'Disetujui'], true)) {
        selesai("❌ Pengajuan berstatus {$data['status']} tidak dapat dibatalkan.", false);
    }

    $stmt_upd = $conn->prepare("UPDATE pengajuan_lembur SET status = 'Dibatalkan' WHERE id = ?");
    $stmt_upd->bind_param("i", $id_pengajuan);
    if ($stmt_upd->execute()) {
        $stmt_upd->close();
        $rentang = formatRentangTanggal($data['tanggal_mulai'], $data['tanggal_selesai']);
        logActivity($conn, 'batal_lembur', "Membatalkan pengajuan lembur {$rentang} - #{$id_pengajuan}", $_SESSION['user_id']);
        selesai("✅ Pengajuan lembur {$rentang} berhasil dibatalkan.");
    } else {
        $stmt_upd->close();
        selesai("❌ Gagal membatalkan pengajuan.", false);
    }
}

// ==========================================================
// REVIEW PENGAJUAN (supervisor / admin / owner)
// ==========================================================
if (isset($_POST['review_lembur'])) {
    requireApprover();

    $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
    $aksi         = sanitizeInput($_POST['aksi'] ?? '');
    $catatan      = sanitizeInput($_POST['catatan_reviewer'] ?? '');

    if ($id_pengajuan <= 0 || !in_array($aksi, ['setujui', 'tolak'], true)) {
        selesai("❌ Data review tidak valid.", false);
    }

    if ($aksi === 'tolak' && strlen($catatan) < 3) {
        selesai("❌ Penolakan wajib disertai alasan agar karyawan tahu penyebabnya.", false);
    }

    $cabang_reviewer = getCabangReviewer($conn, $_SESSION['user_id'], $_SESSION['role']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT p.*, k.nama_karyawan, k.id_cabang AS cabang_karyawan
                                FROM pengajuan_lembur p
                                JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                                WHERE p.id = ? FOR UPDATE");
        $stmt->bind_param("i", $id_pengajuan);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            throw new Exception("Pengajuan tidak ditemukan.");
        }

        // Admin/supervisor yang juga karyawan tidak boleh menyetujui pengajuan
        // lembur miliknya sendiri - sama seperti proteksi di review_izin.
        if (!empty($_SESSION['id_karyawan']) && $data['id_karyawan'] === $_SESSION['id_karyawan']) {
            throw new Exception("Anda tidak dapat mereview pengajuan lembur milik sendiri.");
        }

        if ($cabang_reviewer !== null && (int)$data['cabang_karyawan'] !== (int)$cabang_reviewer) {
            throw new Exception("Pengajuan ini berada di luar cabang yang Anda supervisi.");
        }

        if ($data['status'] !== 'Pending') {
            throw new Exception("Pengajuan ini sudah berstatus {$data['status']}.");
        }

        $rentang = formatRentangTanggal($data['tanggal_mulai'], $data['tanggal_selesai']);
        $status_baru = $aksi === 'setujui' ? 'Disetujui' : 'Ditolak';

        $stmt_upd = $conn->prepare("UPDATE pengajuan_lembur
            SET status = ?, reviewed_by = ?, reviewed_at = NOW(), catatan_reviewer = ?
            WHERE id = ?");
        $stmt_upd->bind_param("sisi", $status_baru, $_SESSION['user_id'], $catatan, $id_pengajuan);
        if (!$stmt_upd->execute()) {
            throw new Exception("Gagal menyimpan hasil review.");
        }
        $stmt_upd->close();

        $conn->commit();

        logActivity($conn, 'review_lembur',
            ($aksi === 'setujui' ? "Menyetujui" : "Menolak") . " pengajuan lembur {$data['nama_karyawan']} ({$rentang}) - #{$id_pengajuan}",
            $_SESSION['user_id']);

        $pesan = $aksi === 'setujui'
            ? "✅ Pengajuan lembur {$data['nama_karyawan']} ({$rentang}) disetujui. Jam lembur akan dihitung otomatis dari absensi aktual saat slip gaji dibuat."
            : "✅ Pengajuan lembur {$data['nama_karyawan']} ({$rentang}) ditolak.";
        selesai($pesan);

    } catch (Exception $e) {
        $conn->rollback();
        selesai("❌ " . $e->getMessage(), false);
    }
}

// ==========================================================
// TUGASKAN LEMBUR (supervisor / admin)
// ==========================================================
if (isset($_POST['tugaskan_lembur'])) {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'supervisor'], true)) {
        selesai("❌ Hanya Supervisor atau Admin yang dapat menugaskan lembur.", false);
    }

    $daftar_id       = array_values(array_unique(array_filter(array_map('sanitizeInput', (array)($_POST['id_karyawan'] ?? [])))));
    $tanggal_mulai   = sanitizeInput($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = sanitizeInput($_POST['tanggal_selesai'] ?? '');
    $jam_mulai       = sanitizeInput($_POST['jam_mulai'] ?? '');
    $jam_selesai     = sanitizeInput($_POST['jam_selesai'] ?? '');
    $keperluan       = sanitizeInput($_POST['keperluan'] ?? '');

    if (empty($daftar_id)) {
        selesai("❌ Pilih minimal satu karyawan yang ditugaskan.", false);
    }

    $cek_mulai   = DateTime::createFromFormat('Y-m-d', $tanggal_mulai);
    $cek_selesai = DateTime::createFromFormat('Y-m-d', $tanggal_selesai);
    if (!$cek_mulai || $cek_mulai->format('Y-m-d') !== $tanggal_mulai ||
        !$cek_selesai || $cek_selesai->format('Y-m-d') !== $tanggal_selesai) {
        selesai("❌ Format tanggal tidak valid.", false);
    }
    if ($tanggal_mulai > $tanggal_selesai) {
        selesai("❌ Tanggal mulai tidak boleh melewati tanggal selesai.", false);
    }
    if ($tanggal_mulai < date('Y-m-d')) {
        selesai("❌ Penugasan lembur tidak bisa untuk tanggal yang sudah lewat.", false);
    }
    if ((strtotime($tanggal_selesai) - strtotime($tanggal_mulai)) / 86400 + 1 > 31) {
        selesai("❌ Rentang penugasan lembur maksimal 31 hari.", false);
    }

    $cek_jam_mulai   = DateTime::createFromFormat('H:i', $jam_mulai);
    $cek_jam_selesai = DateTime::createFromFormat('H:i', $jam_selesai);
    if (!$cek_jam_mulai || $cek_jam_mulai->format('H:i') !== $jam_mulai ||
        !$cek_jam_selesai || $cek_jam_selesai->format('H:i') !== $jam_selesai) {
        selesai("❌ Jam mulai dan jam selesai lembur wajib diisi (format JJ:MM).", false);
    }
    if ($jam_selesai <= $jam_mulai) {
        selesai("❌ Jam selesai lembur harus setelah jam mulai (lembur melewati tengah malam belum didukung).", false);
    }
    $jam_mulai_db   = $jam_mulai . ':00';
    $jam_selesai_db = $jam_selesai . ':00';

    if (strlen($keperluan) < 5) {
        selesai("❌ Keperluan/pekerjaan lembur wajib diisi minimal 5 karakter.", false);
    }

    $cabang_penugas = getCabangReviewer($conn, $_SESSION['user_id'], $_SESSION['role']);
    $hari_kerja     = getHariKerja($conn);

    $stmt_kry = $conn->prepare("SELECT k.id_karyawan, k.nama_karyawan, k.id_cabang, k.status, u.id AS user_id
                                FROM karyawan k
                                LEFT JOIN users u ON u.id_karyawan = k.id_karyawan
                                    AND u.is_active = 1 AND u.role IN ('staff','supervisor','admin')
                                WHERE k.id_karyawan = ?");
    $stmt_ins = $conn->prepare("INSERT INTO pengajuan_lembur
        (id_karyawan, sumber, ditugaskan_oleh, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai,
         keperluan, status, id_cabang, reviewed_by, reviewed_at)
        VALUES (?, 'Penugasan', ?, ?, ?, ?, ?, ?, 'Menunggu Karyawan', ?, ?, NOW())");

    $berhasil = [];
    $gagal    = [];
    foreach ($daftar_id as $id_karyawan) {
        $stmt_kry->bind_param("s", $id_karyawan);
        $stmt_kry->execute();
        $kry = $stmt_kry->get_result()->fetch_assoc();

        if (!$kry || $kry['status'] !== 'aktif') {
            $gagal[] = safe_output($id_karyawan) . " (tidak ditemukan / nonaktif)";
            continue;
        }
        $nama = safe_output($kry['nama_karyawan']);
        if ($cabang_penugas !== null && (int)$kry['id_cabang'] !== (int)$cabang_penugas) {
            $gagal[] = "{$nama} (di luar cabang Anda)";
            continue;
        }
        if (!empty($_SESSION['id_karyawan']) && $id_karyawan === $_SESSION['id_karyawan']) {
            $gagal[] = "{$nama} (tidak bisa menugaskan diri sendiri)";
            continue;
        }
        if (empty($kry['user_id'])) {
            $gagal[] = "{$nama} (belum punya akun aktif untuk merespon)";
            continue;
        }

        // Hanya hari kerja normal: Sabtu/hari lembur mingguan sudah punya
        // jalur sendiri, dan hari libur tidak dihitung di slip gaji.
        $daftar_libur = getHariLibur($conn, $tanggal_mulai, $tanggal_selesai, (int)$kry['id_cabang']);
        $ada_hari_kerja = false;
        for ($ts = strtotime($tanggal_mulai); $ts <= strtotime($tanggal_selesai); $ts += 86400) {
            $tgl = date('Y-m-d', $ts);
            if (in_array((int)date('N', $ts), $hari_kerja, true) && !isset($daftar_libur[$tgl])) {
                $ada_hari_kerja = true;
                break;
            }
        }
        if (!$ada_hari_kerja) {
            $gagal[] = "{$nama} (rentang tanggal tidak memuat hari kerja)";
            continue;
        }

        $bentrok = cekTumpangTindihLembur($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai);
        if ($bentrok) {
            $gagal[] = "{$nama} (bentrok dengan lembur {$bentrok['status']} " . formatRentangTanggal($bentrok['tanggal_mulai'], $bentrok['tanggal_selesai']) . ")";
            continue;
        }

        $id_cabang = (int)$kry['id_cabang'];
        $stmt_ins->bind_param("sisssssii", $id_karyawan, $_SESSION['user_id'], $tanggal_mulai, $tanggal_selesai,
            $jam_mulai_db, $jam_selesai_db, $keperluan, $id_cabang, $_SESSION['user_id']);
        if ($stmt_ins->execute()) {
            $berhasil[] = $nama;
            logActivity($conn, 'tugaskan_lembur',
                "Menugaskan lembur {$kry['nama_karyawan']} " . formatRentangTanggal($tanggal_mulai, $tanggal_selesai)
                . " {$jam_mulai}-{$jam_selesai} - #{$stmt_ins->insert_id}", $_SESSION['user_id']);
        } else {
            $gagal[] = "{$nama} (gagal disimpan)";
        }
    }
    $stmt_kry->close();
    $stmt_ins->close();

    // Sebagian bisa berhasil dan sebagian gagal, jadi kedua flash message
    // dipakai sekaligus (alert_messages.php menampilkan keduanya).
    $rentang = formatRentangTanggal($tanggal_mulai, $tanggal_selesai);
    if (!empty($gagal)) {
        $_SESSION['error_message'] = "❌ Tidak ditugaskan: " . implode('; ', $gagal) . ".";
    }
    if (empty($berhasil)) {
        header("Location: kelola_pengajuan_lembur.php");
        exit();
    }
    selesai("✅ Lembur {$rentang} ({$jam_mulai}-{$jam_selesai}) ditugaskan ke " . implode(', ', $berhasil) . ". Menunggu jawaban karyawan.");
}

// ==========================================================
// RESPON PENUGASAN (karyawan yang ditugaskan)
// ==========================================================
if (isset($_POST['respon_penugasan'])) {
    requirePemilikLembur();

    $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
    $aksi         = sanitizeInput($_POST['aksi'] ?? '');
    $alasan       = sanitizeInput($_POST['alasan_karyawan'] ?? '');
    $id_karyawan  = $_SESSION['id_karyawan'] ?? '';

    if ($id_pengajuan <= 0 || !in_array($aksi, ['terima', 'tolak'], true)) {
        selesai("❌ Data tidak valid.", false);
    }
    if ($aksi === 'tolak' && strlen($alasan) < 5) {
        selesai("❌ Penolakan wajib disertai alasan (minimal 5 karakter) agar atasan tahu penyebabnya.", false);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM pengajuan_lembur
                                WHERE id = ? AND id_karyawan = ? AND sumber = 'Penugasan' FOR UPDATE");
        $stmt->bind_param("is", $id_pengajuan, $id_karyawan);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            throw new Exception("Penugasan tidak ditemukan atau bukan untuk Anda.");
        }
        if ($data['status'] !== 'Menunggu Karyawan') {
            throw new Exception("Penugasan ini sudah berstatus {$data['status']}.");
        }

        if ($aksi === 'terima') {
            $bentrok = cekTumpangTindihLembur($conn, $id_karyawan, $data['tanggal_mulai'], $data['tanggal_selesai'], $id_pengajuan);
            if ($bentrok) {
                throw new Exception("Rentang tanggal bertabrakan dengan lembur lain ({$bentrok['status']}). Batalkan yang lain terlebih dahulu.");
            }
        }

        $status_baru = $aksi === 'terima' ? 'Disetujui' : 'Ditolak Karyawan';
        $stmt_upd = $conn->prepare("UPDATE pengajuan_lembur
            SET status = ?, alasan_karyawan = ?, direspon_at = NOW()
            WHERE id = ?");
        $stmt_upd->bind_param("ssi", $status_baru, $alasan, $id_pengajuan);
        if (!$stmt_upd->execute()) {
            throw new Exception("Gagal menyimpan jawaban Anda.");
        }
        $stmt_upd->close();
        $conn->commit();

        $rentang = formatRentangTanggal($data['tanggal_mulai'], $data['tanggal_selesai']);
        logActivity($conn, 'respon_penugasan_lembur',
            ($aksi === 'terima' ? "Menerima" : "Menolak") . " penugasan lembur {$rentang} - #{$id_pengajuan}",
            $_SESSION['user_id']);

        selesai($aksi === 'terima'
            ? "✅ Penugasan lembur {$rentang} diterima. Jam lembur dihitung dari absen pulang Anda, maksimal sampai jam selesai yang ditugaskan."
            : "✅ Penugasan lembur {$rentang} ditolak. Alasan Anda sudah diteruskan ke atasan.");

    } catch (Exception $e) {
        $conn->rollback();
        selesai("❌ " . $e->getMessage(), false);
    }
}

// ==========================================================
// TARIK PENUGASAN (supervisor / admin)
// ==========================================================
if (isset($_POST['tarik_penugasan'])) {
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'supervisor'], true)) {
        selesai("❌ Hanya Supervisor atau Admin yang dapat menarik penugasan lembur.", false);
    }

    $id_pengajuan    = intval($_POST['id_pengajuan'] ?? 0);
    $cabang_penugas  = getCabangReviewer($conn, $_SESSION['user_id'], $_SESSION['role']);

    $stmt = $conn->prepare("SELECT p.*, k.nama_karyawan, k.id_cabang AS cabang_karyawan
                            FROM pengajuan_lembur p
                            JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                            WHERE p.id = ? AND p.sumber = 'Penugasan'");
    $stmt->bind_param("i", $id_pengajuan);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        selesai("❌ Penugasan tidak ditemukan.", false);
    }
    if ($cabang_penugas !== null && (int)$data['cabang_karyawan'] !== (int)$cabang_penugas) {
        selesai("❌ Penugasan ini berada di luar cabang yang Anda supervisi.", false);
    }

    // Yang sudah diterima hanya boleh ditarik sebelum hari pelaksanaan, supaya
    // jam lembur yang sudah dikerjakan tidak hilang dari slip gaji.
    $boleh_tarik = $data['status'] === 'Menunggu Karyawan'
        || ($data['status'] === 'Disetujui' && $data['tanggal_mulai'] > date('Y-m-d'));
    if (!$boleh_tarik) {
        selesai("❌ Penugasan berstatus {$data['status']} pada tanggal ini tidak dapat ditarik lagi.", false);
    }

    $stmt_upd = $conn->prepare("UPDATE pengajuan_lembur SET status = 'Dibatalkan' WHERE id = ?");
    $stmt_upd->bind_param("i", $id_pengajuan);
    $stmt_upd->execute();
    $stmt_upd->close();

    $rentang = formatRentangTanggal($data['tanggal_mulai'], $data['tanggal_selesai']);
    logActivity($conn, 'tarik_penugasan_lembur',
        "Menarik penugasan lembur {$data['nama_karyawan']} ({$rentang}) - #{$id_pengajuan}", $_SESSION['user_id']);
    selesai("✅ Penugasan lembur {$data['nama_karyawan']} ({$rentang}) ditarik.");
}

// Tidak ada aksi yang cocok
selesai("❌ Aksi tidak dikenali.", false);
