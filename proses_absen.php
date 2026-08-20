<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

function outputJSON($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $rate_check = checkRateLimit('absen', 5);
    if (!$rate_check['allowed']) {
        outputJSON([
            'success' => false,
            'message' => 'Tunggu ' . $rate_check['remaining'] . ' detik untuk absen lagi.'
        ]);
    }

    $id_karyawan = isset($_POST['id_karyawan']) ? sanitizeInput($_POST['id_karyawan']) : '';
    $lokasi = isset($_POST['lokasi']) ? sanitizeInput($_POST['lokasi']) : 'Lokasi tidak terdeteksi';
    $keterangan_param = isset($_POST['keterangan']) ? sanitizeInput($_POST['keterangan']) : '';
    $is_dinas_luar = isset($_POST['is_dinas_luar']) && $_POST['is_dinas_luar'] === 'true';
    
    $face_descriptor = isset($_POST['face_descriptor']) ? $_POST['face_descriptor'] : null;
    $face_confidence = isset($_POST['face_confidence']) ? floatval($_POST['face_confidence']) : null;
    
    $MIN_FACE_CONFIDENCE = 62.0;
    
    $tanggal = date('Y-m-d');
    $waktu = date('H:i:s');

    $response = ['success' => false, 'message' => 'Terjadi kesalahan.'];

    if (empty($id_karyawan)) {
        outputJSON(['success' => false, 'message' => 'ID Karyawan tidak boleh kosong.']);
    }

    if (!validateIDKaryawan($id_karyawan)) {
        outputJSON(['success' => false, 'message' => 'Format ID Karyawan tidak valid.']);
    }

    // Get karyawan data
    $stmt = $conn->prepare("SELECT k.id, k.id_cabang, u.face_descriptor FROM karyawan k LEFT JOIN users u ON k.id_karyawan = u.id_karyawan WHERE k.id_karyawan = ?");
    $stmt->bind_param("s", $id_karyawan);
    $stmt->execute();
    $result_karyawan = $stmt->get_result();
    
    if ($result_karyawan->num_rows == 0) {
        $stmt->close();
        outputJSON(['success' => false, 'message' => 'ID Karyawan tidak ditemukan.']);
    }

    $karyawan_data = $result_karyawan->fetch_assoc();
    $id_cabang = $karyawan_data['id_cabang'];
    $has_registered_face = !empty($karyawan_data['face_descriptor']);
    $stmt->close();

    // Pengajuan Dinas Luar yang sudah disetujui supervisor/admin untuk hari ini.
    // Bila ada, karyawan tidak perlu lagi mengajukan dinas dadakan: validasi
    // lokasi dilewati dan absensi langsung tercatat sebagai 'Dinas Luar'.
    $izin_dinas_hari_ini = getIzinDinasDisetujui($conn, $id_karyawan, $tanggal);
    if ($izin_dinas_hari_ini) {
        $is_dinas_luar = true;
    }

    // ========== PERBAIKAN: VALIDASI HANYA UNTUK "HADIR" ==========
    // Validasi GPS dan Face HANYA untuk keterangan "Hadir"
    if ($keterangan_param === 'Hadir') {
        
        // 0. CEK REGISTRASI WAJAH (WAJIB untuk Hadir)
        if (!$has_registered_face) {
            outputJSON([
                'success' => false,
                'message' => '❌ Registrasi Wajah Diperlukan!<br><br>Untuk absensi <strong>HADIR</strong>, Anda wajib melakukan registrasi wajah terlebih dahulu melalui menu profile/akun Anda.',
                'type' => 'face_registration_required'
            ]);
        }
        
        // 1. VALIDASI LOKASI GPS (wajib untuk Hadir, kecuali request Dinas Luar)
        $validasi_lokasi = validateLokasiAbsen($lokasi, $id_karyawan, $conn);
        if (!$is_dinas_luar && !$validasi_lokasi['valid'] && !isset($validasi_lokasi['bypass'])) {
            outputJSON([
                'success' => false,
                'message' => $validasi_lokasi['message'],
                'jarak' => $validasi_lokasi['jarak'],
                'radius' => $validasi_lokasi['radius'],
                'type' => 'location_error'
            ]);
        }
        
        // 2. VALIDASI WAJAH (wajib karena sudah pasti registrasi)
        if ($face_confidence === null || $face_descriptor === null) {
            outputJSON([
                'success' => false,
                'message' => '🔒 Verifikasi wajah wajib dilakukan untuk absensi Hadir. Silakan aktifkan kamera dan verifikasi wajah Anda.',
                'type' => 'face_required'
            ]);
        }
        
        // Cek confidence threshold
        if ($face_confidence < $MIN_FACE_CONFIDENCE) {
            outputJSON([
                'success' => false,
                'message' => "❌ Verifikasi wajah gagal! Confidence score terlalu rendah ({$face_confidence}%). Minimal {$MIN_FACE_CONFIDENCE}% diperlukan. Silakan coba lagi dengan pencahayaan yang lebih baik.",
                'type' => 'face_verification_failed',
                'confidence' => $face_confidence,
                'threshold' => $MIN_FACE_CONFIDENCE
            ]);
        }
    }
    // Untuk keterangan selain "Hadir" (OFF, Sakit, Cuti, Alpha): 
    // TIDAK ADA VALIDASI GPS atau FACE - langsung lanjut ke proses insert
    // =================================================================

    // Cek duplikasi
    $time_threshold = date('Y-m-d H:i:s', strtotime('-10 seconds'));
    $stmt_duplicate = $conn->prepare("SELECT id FROM absensi WHERE id_karyawan = ? AND tanggal = ? AND created_at > ?");
    $stmt_duplicate->bind_param("sss", $id_karyawan, $tanggal, $time_threshold);
    $stmt_duplicate->execute();
    if ($stmt_duplicate->get_result()->num_rows > 0) {
        $stmt_duplicate->close();
        outputJSON(['success' => false, 'message' => 'Absensi sedang diproses. Mohon tunggu.']);
    }
    $stmt_duplicate->close();

    // Cek status absensi hari ini
    $stmt_check = $conn->prepare("SELECT id, jam_masuk, jam_pulang FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
    $stmt_check->bind_param("ss", $id_karyawan, $tanggal);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $is_absen_pulang = $result_check->num_rows > 0;

    if ($is_absen_pulang) {
        // ============== PROSES ABSEN PULANG ==============
        $data_absen = $result_check->fetch_assoc();
        
        if ($data_absen['jam_pulang'] != NULL && $data_absen['jam_pulang'] != '00:00:00') {
            $stmt_check->close();
            outputJSON(['success' => false, 'message' => 'Anda sudah absen pulang hari ini.']);
        }

        // VALIDASI LOKASI untuk absen pulang (hanya jika keterangan aslinya adalah Hadir)
        $stmt_get_ket = $conn->prepare("SELECT keterangan FROM absensi WHERE id = ?");
        $stmt_get_ket->bind_param("i", $data_absen['id']);
        $stmt_get_ket->execute();
        $ket_data = $stmt_get_ket->get_result()->fetch_assoc();
        $stmt_get_ket->close();
        
        $is_hadir_masuk = ($ket_data['keterangan'] === 'Hadir');
        $is_pending_dinas = ($ket_data['keterangan'] === 'Pending Dinas');
        $is_dinas_luar_status = ($ket_data['keterangan'] === 'Dinas Luar');
        
        if ($is_pending_dinas) {
            $stmt_check->close();
            outputJSON(['success' => false, 'message' => 'Absensi masuk Anda masih menunggu persetujuan Dinas Luar. Harap tunggu Admin menyetujui, atau periksa kembali statusnya.']);
        }
        
        if ($is_hadir_masuk) {
            // Validasi GPS untuk pulang
            $validasi_lokasi = validateLokasiAbsen($lokasi, $id_karyawan, $conn);
            if (!$validasi_lokasi['valid'] && !isset($validasi_lokasi['bypass'])) {
                $stmt_check->close();
                outputJSON([
                    'success' => false,
                    'message' => $validasi_lokasi['message'],
                    'jarak' => $validasi_lokasi['jarak'],
                    'radius' => $validasi_lokasi['radius'],
                    'type' => 'location_error'
                ]);
            }
        }

        if ($is_hadir_masuk || $is_dinas_luar_status) {
            // Validasi Face untuk pulang (WAJIB jika masuknya Hadir atau Dinas Luar)
            if ($face_confidence === null || $face_confidence < $MIN_FACE_CONFIDENCE) {
                $stmt_check->close();
                outputJSON([
                    'success' => false,
                    'message' => '🔒 Verifikasi wajah wajib dilakukan untuk absen pulang. Confidence score minimal ' . $MIN_FACE_CONFIDENCE . '% diperlukan.',
                    'type' => 'face_required'
                ]);
            }
        }

        // ============== CEK OVERTIME =================
        // Pada hari lembur (mis. Sabtu) seluruh jam kerjanya memang sudah
        // dihitung lembur lewat getLemburHariSabtu(), jadi form alasan+foto
        // overtime tidak perlu diminta lagi.
        $is_hari_lembur = isHariOvertime($conn, $tanggal);

        $is_overtime_request = false;
        if ($is_hadir_masuk && !$is_hari_lembur) {
            $stmt_jam_pulang = $conn->prepare("
                SELECT jk.jam_pulang 
                FROM jam_kerja jk 
                WHERE jk.id_cabang = ? 
                ORDER BY ABS(TIMESTAMPDIFF(MINUTE, ?, jk.jam_masuk_akhir)) ASC 
                LIMIT 1
            ");
            $stmt_jam_pulang->bind_param("is", $id_cabang, $data_absen['jam_masuk']);
            $stmt_jam_pulang->execute();
            $result_jam_pulang = $stmt_jam_pulang->get_result();
            if ($result_jam_pulang->num_rows > 0) {
                $target_jam_pulang = $result_jam_pulang->fetch_assoc()['jam_pulang'];
                if ($waktu > $target_jam_pulang) {
                    $is_overtime_request = true;
                }
            }
            $stmt_jam_pulang->close();
        }

        $alasan_pulang = isset($_POST['alasan_pulang']) ? sanitizeInput($_POST['alasan_pulang']) : null;
        $foto_pulang_name = null;
        
        if ($is_overtime_request) {
            if (empty($alasan_pulang) || empty($_FILES['foto_pulang']['name'])) {
                $stmt_check->close();
                outputJSON([
                    'success' => false,
                    'message' => 'Anda terdeteksi melakukan Overtime. Silakan isi alasan dan unggah foto bukti Overtime terlebih dahulu.',
                    'type' => 'overtime_form_required'
                ]);
            }
            
            if (isset($_FILES['foto_pulang']) && $_FILES['foto_pulang']['error'] == 0) {
                $upload_dir = __DIR__ . '/assets/uploads/absensi/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['foto_pulang']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                if (in_array($ext, $allowed_ext) && $_FILES['foto_pulang']['size'] <= 6 * 1024 * 1024) {
                    $foto_pulang_name = $id_karyawan . '_overtime_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['foto_pulang']['tmp_name'], $upload_dir . $foto_pulang_name);
                } else {
                    $stmt_check->close();
                    outputJSON([
                        'success' => false,
                        'message' => 'Format foto Overtime tidak valid atau ukuran terlalu besar (Max 6MB).'
                    ]);
                }
            }
        }
        // ==============================================

        // Update dengan data face jika ada
        $face_verified = (!empty($face_descriptor) && !empty($face_confidence) && $face_confidence >= $MIN_FACE_CONFIDENCE) ? 1 : 0;
        
        if ($face_verified) {
            $stmt_update = $conn->prepare("UPDATE absensi SET jam_pulang = ?, lokasi_pulang = ?, face_verified = 1, face_confidence = ?, alasan_pulang = ?, foto_pulang = ? WHERE id = ?");
            $stmt_update->bind_param("ssdssi", $waktu, $lokasi, $face_confidence, $alasan_pulang, $foto_pulang_name, $data_absen['id']);
        } else {
            $stmt_update = $conn->prepare("UPDATE absensi SET jam_pulang = ?, lokasi_pulang = ?, alasan_pulang = ?, foto_pulang = ? WHERE id = ?");
            $stmt_update->bind_param("ssssi", $waktu, $lokasi, $alasan_pulang, $foto_pulang_name, $data_absen['id']);
        }
        
        if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
            $log_message = "Absen pulang jam $waktu" . ($face_verified ? " (Face Verified: {$face_confidence}%)" : "");
            logActivity($conn, 'absen_pulang', $log_message, $id_karyawan);
            
            if ($face_verified) {
                $stmt_face_log = $conn->prepare(
                    "INSERT INTO face_recognition_logs (id_karyawan, attempt_type, status, confidence_score, ip_address) 
                     VALUES (?, 'verification', 'success', ?, ?)"
                );
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt_face_log->bind_param("sds", $id_karyawan, $face_confidence, $ip);
                $stmt_face_log->execute();
                $stmt_face_log->close();
            }
            
            $stmt_update->close();
            $stmt_check->close();
            
            outputJSON([
                'success' => true, 
                'message' => "Absensi pulang berhasil direkam pada jam $waktu." . ($face_verified ? " <br><small style='color: #28a745;'>🛡️ Wajah terverifikasi ({$face_confidence}%)</small>" : ""), 
                'title' => "Selamat Beristirahat!"
            ]);
        } else {
            $stmt_update->close();
            $stmt_check->close();
            outputJSON(['success' => false, 'message' => 'Gagal merekam absensi pulang.']);
        }

    } else {
        // ============== PROSES ABSEN MASUK ==============
        // Dinas luar yang sudah disetujui di muka langsung final; dinas dadakan
        // tetap masuk antrean 'Pending Dinas' untuk di-ACC admin di hari-H.
        if ($izin_dinas_hari_ini) {
            $keterangan = 'Dinas Luar';
        } else {
            $keterangan = $is_dinas_luar ? 'Pending Dinas' : $keterangan_param;
        }
        $status_masuk = 'Tepat Waktu';

        // Keterlambatan hanya berlaku pada HARI KERJA normal. Pada hari lembur
        // (mis. Sabtu) jam masuk memang tidak tetap - bisa 10:00 tergantung
        // penugasan - sehingga membandingkannya dengan jam_masuk_akhir shift
        // akan salah menandai 'Terlambat' dan memotong gaji lewat
        // rate_keterlambatan. Hari libur nasional diperlakukan sama.
        $hari_kerja_normal = isHariKerja($conn, $tanggal)
            && empty(getHariLibur($conn, $tanggal, $tanggal, $id_cabang));

        // Cek status keterlambatan HANYA untuk 'Hadir' (Pending Dinas kita anggap Tepat Waktu sementara, atau bisa juga terlambat jika mau)
        if ($keterangan == 'Hadir' && $hari_kerja_normal) {
            $stmt_jam = $conn->prepare("SELECT jam_masuk_akhir FROM jam_kerja WHERE id_cabang = ?");
            $stmt_jam->bind_param("i", $id_cabang);
            $stmt_jam->execute();
            $rules = $stmt_jam->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_jam->close();

            if (!empty($rules)) {
                $target_rule = null;
                if (count($rules) == 1) {
                    $target_rule = $rules[0];
                } else {
                    $min_diff = PHP_INT_MAX;
                    foreach ($rules as $rule) {
                        $diff = abs(strtotime($waktu) - strtotime($rule['jam_masuk_akhir']));
                        if ($diff < $min_diff) {
                            $min_diff = $diff;
                            $target_rule = $rule;
                        }
                    }
                }
                if ($target_rule && $waktu > $target_rule['jam_masuk_akhir']) {
                    $status_masuk = 'Terlambat';
                }
            }
        }

        // Face verified status
        $face_verified = (!empty($face_descriptor) && !empty($face_confidence) && $face_confidence >= $MIN_FACE_CONFIDENCE) ? 1 : 0;

        // Insert absensi masuk
        $conn->begin_transaction();
        try {
            $stmt_final_check = $conn->prepare("SELECT id FROM absensi WHERE id_karyawan = ? AND tanggal = ? FOR UPDATE");
            $stmt_final_check->bind_param("ss", $id_karyawan, $tanggal);
            $stmt_final_check->execute();
            
            if ($stmt_final_check->get_result()->num_rows > 0) {
                $conn->rollback();
                $stmt_final_check->close();
                $stmt_check->close();
                outputJSON(['success' => false, 'message' => 'Absensi sudah tercatat.']);
            }
            $stmt_final_check->close();

            // Handle Alasan dan Foto Bukti
            $alasan = isset($_POST['alasan']) ? sanitizeInput($_POST['alasan']) : null;
            
            $foto_bukti_name = null;
            if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
                $upload_dir = __DIR__ . '/assets/uploads/absensi/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                if (in_array($ext, $allowed_ext) && $_FILES['foto_bukti']['size'] <= 6 * 1024 * 1024) {
                    $foto_bukti_name = $id_karyawan . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $foto_bukti_name);
                }
            }
            
            $waktu_alasan = ($alasan !== null) ? date('Y-m-d H:i:s') : null;

            // Insert data
            if ($face_verified) {
                $stmt_insert = $conn->prepare(
                    "INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, lokasi_masuk, keterangan, status_masuk, face_verified, face_confidence, alasan, foto_bukti, waktu_alasan) 
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)"
                );
                $stmt_insert->bind_param("ssssssdsss", $id_karyawan, $tanggal, $waktu, $lokasi, $keterangan, $status_masuk, $face_confidence, $alasan, $foto_bukti_name, $waktu_alasan);
            } else {
                $stmt_insert = $conn->prepare(
                    "INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, lokasi_masuk, keterangan, status_masuk, alasan, foto_bukti, waktu_alasan) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_insert->bind_param("sssssssss", $id_karyawan, $tanggal, $waktu, $lokasi, $keterangan, $status_masuk, $alasan, $foto_bukti_name, $waktu_alasan);
            }
            
            if ($stmt_insert->execute()) {
                $log_message = "Absen $keterangan ($status_masuk)" . ($face_verified ? " - Face Verified: {$face_confidence}%" : "");
                logActivity($conn, 'absen_masuk', $log_message, $id_karyawan);

                if ($face_verified) {
                    $stmt_face_log = $conn->prepare(
                        "INSERT INTO face_recognition_logs (id_karyawan, attempt_type, status, confidence_score, ip_address) 
                         VALUES (?, 'verification', 'success', ?, ?)"
                    );
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $stmt_face_log->bind_param("sds", $id_karyawan, $face_confidence, $ip);
                    $stmt_face_log->execute();
                    $stmt_face_log->close();
                }

                $conn->commit();
                $stmt_insert->close();
                $stmt_check->close();

                // Pesan sukses dinamis
                $pesan_sukses = "Absensi masuk Anda ($keterangan) berhasil direkam pada jam $waktu.";
                $judul_sukses = "Terima Kasih!";

                switch ($keterangan) {
                    case 'Sakit':
                        $judul_sukses = "Semoga Lekas Sembuh";
                        $pesan_sukses = "Absensi SAKIT telah kami catat. Prioritaskan kesehatan dan istirahatlah yang cukup.";
                        break;
                    case 'Cuti':
                        $judul_sukses = "Selamat Menikmati Waktu Cuti";
                        $pesan_sukses = "Absensi CUTI telah disetujui. Gunakan waktu cuti Anda sebaik-baiknya.";
                        break;
                    case 'OFF':
                        $judul_sukses = "Selamat Libur!";
                        $pesan_sukses = "Absensi OFF telah dicatat. Selamat menikmati hari libur.";
                        break;
                    case 'Alpha':
                        $judul_sukses = "Konfirmasi Absen";
                        $pesan_sukses = "Status ALPHA telah dicatat. Silakan konfirmasi dengan atasan jika ada kendala.";
                        break;
                    case 'Pending Dinas':
                        $judul_sukses = "Permintaan Dinas Terkirim";
                        $pesan_sukses = "Permohonan Dinas Luar Anda telah dikirim dan menunggu persetujuan Admin.";
                        if ($face_verified) {
                            $pesan_sukses .= "<br><small style='color: #28a745;'>🛡️ Wajah terverifikasi ({$face_confidence}%)</small>";
                        }
                        break;
                    case 'Hadir':
                        $judul_sukses = "Selamat Bekerja!";
                        $pesan_sukses = "Terima kasih, absensi masuk berhasil direkam pada jam $waktu.";
                        if ($face_verified) {
                            $pesan_sukses .= "<br><small style='color: #28a745;'>🛡️ Wajah terverifikasi ({$face_confidence}%)</small>";
                        }
                        break;
                }

                outputJSON([
                    'success' => true,
                    'message' => $pesan_sukses,
                    'title' => $judul_sukses,
                    'face_verified' => $face_verified
                ]);
            } else {
                $conn->rollback();
                $stmt_insert->close();
                $stmt_check->close();
                outputJSON(['success' => false, 'message' => 'Gagal merekam absensi masuk: ' . $conn->error]);
            }

        } catch (Exception $e) {
            $conn->rollback();
            $stmt_check->close();
            error_log("Error absensi: " . $e->getMessage());
            outputJSON(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
        }
    }

    $stmt_check->close();

} catch (Exception $e) {
    error_log("Fatal error in proses_absen.php: " . $e->getMessage());
    outputJSON([
        'success' => false, 
        'message' => 'Terjadi kesalahan fatal pada server. Silakan coba lagi atau hubungi administrator.',
        'error_detail' => $e->getMessage()
    ]);
}
?>
