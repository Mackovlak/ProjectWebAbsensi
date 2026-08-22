<?php
/**
 * ==========================================
 * PROSES PENGAJUAN IZIN / CUTI / DINAS LUAR
 * ==========================================
 * Handler tunggal untuk seluruh aksi pengajuan izin, mengikuti pola
 * dispatch-by-POST-key milik master_process.php.
 *
 * Aksi yang didukung:
 *  - ajukan_izin  : karyawan (staff) mengajukan izin untuk rentang tanggal
 *  - batal_izin   : karyawan membatalkan pengajuannya sendiri
 *  - review_izin  : supervisor/admin/owner menyetujui atau menolak
 */

require 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(dashboardUntukRole($_SESSION['role'] ?? 'staff'));
}

verifyCSRFToken($_POST['csrf_token'] ?? '');

$redirect_default = isStaff() ? 'staff_pengajuan_izin.php' : 'kelola_pengajuan_izin.php';

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
// AJUKAN IZIN (staff)
// ==========================================================
if (isset($_POST['ajukan_izin'])) {
    requireStaff();

    $id_karyawan = $_SESSION['id_karyawan'] ?? '';
    if (empty($id_karyawan)) {
        selesai("❌ Akun Anda belum tertaut dengan data karyawan. Hubungi admin.", false);
    }

    $jenis           = sanitizeInput($_POST['jenis'] ?? '');
    $tanggal_mulai   = sanitizeInput($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai = sanitizeInput($_POST['tanggal_selesai'] ?? '');
    $keperluan       = sanitizeInput($_POST['keperluan'] ?? '');

    // ---------- Validasi dasar ----------
    if (!in_array($jenis, IZIN_JENIS_VALID, true)) {
        selesai("❌ Jenis pengajuan tidak valid.", false);
    }

    if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
        selesai("❌ Tanggal mulai dan tanggal selesai wajib diisi.", false);
    }

    // Pastikan format tanggal benar-benar Y-m-d
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
        selesai("❌ Keperluan wajib diisi minimal 5 karakter agar reviewer paham konteksnya.", false);
    }

    // Kuota dihitung per tahun kalender, jadi satu pengajuan tidak boleh
    // memotong dua tahun sekaligus.
    if (date('Y', strtotime($tanggal_mulai)) !== date('Y', strtotime($tanggal_selesai))) {
        selesai("❌ Pengajuan tidak boleh melewati pergantian tahun. Silakan buat dua pengajuan terpisah.", false);
    }

    // Batasi rentang agar tidak ada pengajuan ekstrem (mis. satu tahun penuh)
    $selisih_hari = (strtotime($tanggal_selesai) - strtotime($tanggal_mulai)) / 86400 + 1;
    if ($selisih_hari > 31) {
        selesai("❌ Rentang pengajuan maksimal 31 hari dalam satu permohonan.", false);
    }

    // Sakit boleh mundur (baru bisa diurus setelah sembuh), jenis lain harus ke depan.
    $hari_ini = date('Y-m-d');
    if ($jenis !== 'Sakit' && $tanggal_mulai < $hari_ini) {
        selesai("❌ Pengajuan {$jenis} harus diajukan sebelum tanggal pelaksanaan, tidak bisa mundur.", false);
    }
    if ($jenis === 'Sakit' && $tanggal_mulai < date('Y-m-d', strtotime('-14 days'))) {
        selesai("❌ Pengajuan Sakit hanya bisa mundur maksimal 14 hari ke belakang.", false);
    }

    // ---------- Cek tumpang tindih ----------
    $bentrok = cekTumpangTindihIzin($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai);
    if ($bentrok) {
        $rentang = formatRentangTanggal($bentrok['tanggal_mulai'], $bentrok['tanggal_selesai']);
        selesai("❌ Rentang tanggal bertabrakan dengan pengajuan {$bentrok['jenis']} ({$bentrok['status']}) pada {$rentang}.", false);
    }

    // ---------- Hitung hari efektif & kuota ----------
    // Deteksi kehadiran lampiran di sini saja (belum divalidasi/dipindah) supaya
    // Sakit + bukti bisa dibebaskan dari kuota SEBELUM cek kuota di bawah.
    // Upload sungguhannya baru terjadi nanti di blok "Lampiran" agar tidak ada
    // file yatim bila pengajuan ditolak duluan oleh cek kuota.
    $ada_lampiran = isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == 0 && $_FILES['lampiran']['size'] > 0;

    $rincian = hitungHariIzin($conn, $id_karyawan, $tanggal_mulai, $tanggal_selesai);
    $potong_kuota = izinPotongKuota($jenis, $ada_lampiran) ? 1 : 0;

    if ($rincian['hari_efektif'] < 1) {
        selesai("❌ Tidak ada hari kerja efektif pada rentang tersebut (semuanya hari libur, akhir pekan, atau sudah memiliki absensi).", false);
    }

    if ($potong_kuota) {
        $kuota = getRingkasanKuotaIzin($conn, $id_karyawan, (int)date('Y', strtotime($tanggal_mulai)));
        if ($rincian['hari_efektif'] > $kuota['tersedia']) {
            selesai("❌ Kuota tidak mencukupi. Pengajuan ini butuh {$rincian['hari_efektif']} hari, "
                . "sisa kuota yang bisa dipakai tahun {$kuota['tahun']} tinggal {$kuota['tersedia']} hari "
                . "(terpakai {$kuota['terpakai']}, menunggu persetujuan {$kuota['tertahan']} dari jatah {$kuota['jatah']}).", false);
        }
    }

    // ---------- Lampiran (opsional) ----------
    $lampiran = null;
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] == 0) {
        $upload_dir = __DIR__ . '/assets/uploads/izin/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($ext, $allowed_ext)) {
            selesai("❌ Lampiran harus berupa file JPG, PNG, atau PDF.", false);
        }
        if ($_FILES['lampiran']['size'] > 6 * 1024 * 1024) {
            selesai("❌ Ukuran lampiran maksimal 6 MB.", false);
        }

        $lampiran = $id_karyawan . '_izin_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($_FILES['lampiran']['tmp_name'], $upload_dir . $lampiran)) {
            selesai("❌ Gagal mengunggah lampiran. Silakan coba lagi.", false);
        }
    }

    // ---------- Simpan ----------
    $stmt_cabang = $conn->prepare("SELECT id_cabang FROM karyawan WHERE id_karyawan = ?");
    $stmt_cabang->bind_param("s", $id_karyawan);
    $stmt_cabang->execute();
    $row_cabang = $stmt_cabang->get_result()->fetch_assoc();
    $stmt_cabang->close();
    $id_cabang = $row_cabang ? (int)$row_cabang['id_cabang'] : null;

    $stmt = $conn->prepare("INSERT INTO pengajuan_izin
        (id_karyawan, jenis, tanggal_mulai, tanggal_selesai, jumlah_hari, jumlah_hari_kerja,
         keperluan, lampiran, status, potong_kuota, id_cabang)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)");
    $stmt->bind_param(
        "ssssiissii",
        $id_karyawan, $jenis, $tanggal_mulai, $tanggal_selesai,
        $rincian['hari_kalender'], $rincian['hari_efektif'],
        $keperluan, $lampiran, $potong_kuota, $id_cabang
    );

    if ($stmt->execute()) {
        $id_baru = $stmt->insert_id;
        $stmt->close();

        $rentang = formatRentangTanggal($tanggal_mulai, $tanggal_selesai);
        logActivity($conn, 'ajukan_izin',
            "Mengajukan {$jenis} {$rentang} ({$rincian['hari_efektif']} hari kerja) - #{$id_baru}",
            $_SESSION['user_id']);

        $ket_kuota = (!$potong_kuota && $jenis === 'Sakit')
            ? " Karena dilampiri bukti, pengajuan ini <b>tidak memotong kuota tahunan</b> Anda."
            : "";
        selesai("✅ Pengajuan {$jenis} untuk {$rentang} berhasil dikirim dan menunggu persetujuan Supervisor.{$ket_kuota}");
    } else {
        $stmt->close();
        // Bersihkan lampiran yang terlanjur naik supaya tidak jadi file yatim
        if ($lampiran && file_exists(__DIR__ . '/assets/uploads/izin/' . $lampiran)) {
            unlink(__DIR__ . '/assets/uploads/izin/' . $lampiran);
        }
        selesai("❌ Gagal menyimpan pengajuan. Silakan coba lagi.", false);
    }
}

// ==========================================================
// BATALKAN PENGAJUAN (staff, miliknya sendiri)
// ==========================================================
if (isset($_POST['batal_izin'])) {
    requireStaff();

    $id_pengajuan = intval($_POST['id_pengajuan'] ?? 0);
    $id_karyawan  = $_SESSION['id_karyawan'] ?? '';

    if ($id_pengajuan <= 0) {
        selesai("❌ Data pengajuan tidak valid.", false);
    }

    $stmt = $conn->prepare("SELECT * FROM pengajuan_izin WHERE id = ? AND id_karyawan = ?");
    $stmt->bind_param("is", $id_pengajuan, $id_karyawan);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        selesai("❌ Pengajuan tidak ditemukan atau bukan milik Anda.", false);
    }

    if (!in_array($data['status'], ['Pending', 'Disetujui'], true)) {
        selesai("❌ Pengajuan berstatus {$data['status']} tidak dapat dibatalkan.", false);
    }

    // Pengajuan yang sudah disetujui hanya boleh dibatalkan bila belum berjalan,
    // supaya riwayat absensi yang sudah terpakai tidak berubah di belakang.
    if ($data['status'] === 'Disetujui' && $data['tanggal_mulai'] <= date('Y-m-d')) {
        selesai("❌ Pengajuan yang sudah disetujui dan sudah berjalan tidak bisa dibatalkan sendiri. Hubungi Supervisor atau Admin.", false);
    }

    $conn->begin_transaction();
    try {
        if ($data['status'] === 'Disetujui') {
            batalkanMaterialisasiIzin($conn, $id_pengajuan);
        }

        $stmt_upd = $conn->prepare("UPDATE pengajuan_izin SET status = 'Dibatalkan' WHERE id = ?");
        $stmt_upd->bind_param("i", $id_pengajuan);
        if (!$stmt_upd->execute()) {
            throw new Exception("Gagal memperbarui status pengajuan.");
        }
        $stmt_upd->close();

        $conn->commit();

        $rentang = formatRentangTanggal($data['tanggal_mulai'], $data['tanggal_selesai']);
        logActivity($conn, 'batal_izin', "Membatalkan pengajuan {$data['jenis']} {$rentang} - #{$id_pengajuan}", $_SESSION['user_id']);

        selesai("✅ Pengajuan {$data['jenis']} untuk {$rentang} telah dibatalkan. Kuota Anda dikembalikan.");
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Batal izin error: " . $e->getMessage());
        selesai("❌ Gagal membatalkan pengajuan: " . $e->getMessage(), false);
    }
}

// ==========================================================
// REVIEW PENGAJUAN (supervisor / admin / owner)
// ==========================================================
if (isset($_POST['review_izin'])) {
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

    // Scoping cabang: supervisor hanya boleh menyentuh cabangnya sendiri
    $cabang_reviewer = getCabangReviewer($conn, $_SESSION['user_id'], $_SESSION['role']);

    $conn->begin_transaction();
    try {
        // Kunci baris pengajuan supaya dua reviewer tidak memproses bersamaan
        $stmt = $conn->prepare("SELECT p.*, k.nama_karyawan, k.id_cabang AS cabang_karyawan
                                FROM pengajuan_izin p
                                JOIN karyawan k ON p.id_karyawan = k.id_karyawan
                                WHERE p.id = ? FOR UPDATE");
        $stmt->bind_param("i", $id_pengajuan);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            throw new Exception("Pengajuan tidak ditemukan.");
        }

        if ($cabang_reviewer !== null && (int)$data['cabang_karyawan'] !== (int)$cabang_reviewer) {
            throw new Exception("Pengajuan ini berada di luar cabang yang Anda supervisi.");
        }

        if ($data['status'] !== 'Pending') {
            throw new Exception("Pengajuan ini sudah berstatus {$data['status']}.");
        }

        $rentang = formatRentangTanggal($data['tanggal_mulai'], $data['tanggal_selesai']);

        if ($aksi === 'tolak') {
            $stmt_upd = $conn->prepare("UPDATE pengajuan_izin
                SET status = 'Ditolak', reviewed_by = ?, reviewed_at = NOW(), catatan_reviewer = ?
                WHERE id = ?");
            $stmt_upd->bind_param("isi", $_SESSION['user_id'], $catatan, $id_pengajuan);
            if (!$stmt_upd->execute()) {
                throw new Exception("Gagal menyimpan penolakan.");
            }
            $stmt_upd->close();

            $conn->commit();
            logActivity($conn, 'tolak_izin',
                "Menolak pengajuan {$data['jenis']} {$data['nama_karyawan']} ({$rentang}) - #{$id_pengajuan}",
                $_SESSION['user_id']);

            selesai("✅ Pengajuan {$data['jenis']} milik {$data['nama_karyawan']} ({$rentang}) telah ditolak.");
        }

        // ---------- Persetujuan ----------
        // Hitung ulang hari efektif SAAT approval: kondisi absensi bisa berubah
        // sejak pengajuan dibuat (mis. admin menambah libur bersama).
        $rincian = hitungHariIzin($conn, $data['id_karyawan'], $data['tanggal_mulai'], $data['tanggal_selesai']);

        if ($rincian['hari_efektif'] < 1) {
            throw new Exception("Tidak ada lagi hari kerja efektif pada rentang ini (sudah terisi absensi atau libur). Pengajuan sebaiknya ditolak.");
        }

        // Verifikasi ulang kuota terhadap kondisi terkini
        if ($data['potong_kuota']) {
            $kuota = getRingkasanKuotaIzin($conn, $data['id_karyawan'], (int)date('Y', strtotime($data['tanggal_mulai'])));
            // Pengajuan ini sendiri masih terhitung 'tertahan', jadi dikembalikan dulu
            $tersedia = $kuota['tersedia'] + (int)$data['jumlah_hari_kerja'];
            if ($rincian['hari_efektif'] > $tersedia) {
                throw new Exception("Kuota karyawan tidak mencukupi ({$rincian['hari_efektif']} hari dibutuhkan, tersisa {$tersedia} hari).");
            }
        }

        $data['id'] = $id_pengajuan;
        $jumlah_baris = materialisasiIzin($conn, $data);

        $stmt_upd = $conn->prepare("UPDATE pengajuan_izin
            SET status = 'Disetujui', jumlah_hari_kerja = ?, reviewed_by = ?, reviewed_at = NOW(), catatan_reviewer = ?
            WHERE id = ?");
        $stmt_upd->bind_param("iisi", $rincian['hari_efektif'], $_SESSION['user_id'], $catatan, $id_pengajuan);
        if (!$stmt_upd->execute()) {
            throw new Exception("Gagal menyimpan persetujuan.");
        }
        $stmt_upd->close();

        $conn->commit();

        logActivity($conn, 'acc_izin',
            "Menyetujui pengajuan {$data['jenis']} {$data['nama_karyawan']} ({$rentang}, {$rincian['hari_efektif']} hari) - #{$id_pengajuan}",
            $_SESSION['user_id']);

        $info_absensi = $jumlah_baris > 0
            ? " {$jumlah_baris} hari absensi otomatis tercatat."
            : " Karyawan tetap melakukan absensi seperti biasa di lokasi tugas.";

        selesai("✅ Pengajuan {$data['jenis']} milik {$data['nama_karyawan']} ({$rentang}) disetujui." . $info_absensi);

    } catch (Exception $e) {
        $conn->rollback();
        selesai("❌ " . $e->getMessage(), false);
    }
}

// Tidak ada aksi yang cocok
selesai("❌ Aksi tidak dikenali.", false);
?>
