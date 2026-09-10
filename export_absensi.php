<?php
/**
 * EXPORT REKAP ABSENSI - Excel (.xlsx) format Talenta + PDF (cetak browser)
 *
 * Format Excel sengaja meniru urutan kolom export attendance asli Talenta
 * (Employee ID, Full Name, Date, Check In, Check Out, Late In, Early Out,
 * Schedule/Actual/Real Working Hour, Overtime Duration Before/After, Hourly
 * Time Off Taken) supaya admin bisa langsung membandingkan data kedua sistem
 * berdampingan, ditambah dua kolom Status Masuk & Keterangan versi Javag.
 * Kolom Overtime Duration Before & Hourly Time Off Taken masih selalu 0:00 -
 * keduanya baru akan terisi nyata setelah fitur approval lembur SPV dan
 * konversi izin setengah hari (Tidak Absen Masuk) diimplementasikan.
 *
 * Berbeda dari export_slip_gaji_list.php (yang menerima data yang sudah
 * dihitung penuh oleh halaman pemanggil lewat POST JSON), file ini query
 * ulang langsung ke DB berdasarkan id_cabang + rentang tanggal supaya semua
 * kolom turunan (jam kerja shift, keterlambatan, dst.) dihitung di satu
 * tempat saja, tidak diduplikasi di setiap halaman pemanggil.
 */
require 'config.php';
require 'xlsx_writer.php';

// PERBAIKAN: Izinkan akses untuk 'admin' dan 'owner'
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'owner'])) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.");
}

/** Menit (bisa null/negatif) -> teks durasi "H:MM"; null/negatif -> string kosong. */
function formatDurasiMenit($menit) {
    if ($menit === null) return '';
    $menit = (int)$menit;
    if ($menit < 0) $menit = 0;
    $jam = intdiv($menit, 60);
    $sisaMenit = $menit % 60;
    return $jam . ':' . str_pad((string)$sisaMenit, 2, '0', STR_PAD_LEFT);
}

/** Jam desimal (hasil hitungJamKerja, dibulatkan 0,5) -> teks durasi "H:MM". */
function formatDurasiJam($jam) {
    if ($jam === null) return '';
    return formatDurasiMenit((int)round($jam * 60));
}

/**
 * Cari shift jam_kerja yang paling cocok untuk satu baris absensi. Dicocokkan
 * lewat jam_masuk (bila ada) atau jam_pulang sebagai fallback (kasus "Tidak
 * Absen Masuk" - baris hanya punya jam_pulang) supaya tetap bisa menampilkan
 * jam kerja terjadwal walau tidak ada jam masuk untuk dibandingkan.
 */
function detectShiftUntukBaris($jam_masuk, $jam_pulang, $shifts_data) {
    if (empty($shifts_data)) return null;
    $referensi = !empty($jam_masuk) ? $jam_masuk : $jam_pulang;
    if (empty($referensi)) return null;
    $bandingkanKe = !empty($jam_masuk) ? 'jam_masuk_akhir' : 'jam_pulang';
    $ref_ts = strtotime($referensi);
    $best = null;
    $min_diff = PHP_INT_MAX;
    foreach ($shifts_data as $shift) {
        $diff = abs($ref_ts - strtotime($shift[$bandingkanKe]));
        if ($diff < $min_diff) {
            $min_diff = $diff;
            $best = $shift;
        }
    }
    return $best;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Pastikan CSRF token ada sebelum diverifikasi
    if (isset($_POST['csrf_token'])) {
        verifyCSRFToken($_POST['csrf_token']);
    }

    $format = $_POST['format'];
    $cabang = $_POST['cabang'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    if ($format == 'excel') {
        $id_cabang = (int)($_POST['id_cabang'] ?? 0);
        $search_name = isset($_POST['search_name']) ? trim($_POST['search_name']) : '';

        if ($id_cabang <= 0) {
            die("Cabang tidak valid untuk export Excel.");
        }

        $KETERANGAN_HADIR_LIKE = ['Hadir', 'Dinas Luar', 'Pending Dinas'];

        // Shift jam_kerja cabang ini, untuk deteksi jadwal per baris.
        $stmt_shifts = $conn->prepare("SELECT nama_shift, jam_masuk_akhir, jam_pulang FROM jam_kerja WHERE id_cabang = ? ORDER BY jam_pulang ASC");
        $stmt_shifts->bind_param("i", $id_cabang);
        $stmt_shifts->execute();
        $shifts_data = $stmt_shifts->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_shifts->close();

        $sql = "SELECT a.id_karyawan, a.tanggal, a.jam_masuk, a.jam_pulang, a.keterangan,
                       a.status_masuk, a.menit_terlambat, a.dikonversi_izin_setengah_hari, k.nama_karyawan
                FROM absensi a
                JOIN karyawan k ON a.id_karyawan = k.id_karyawan
                WHERE k.id_cabang = ?
                AND a.tanggal BETWEEN ? AND ?";
        if ($search_name !== '') {
            $sql .= " AND k.nama_karyawan LIKE ?";
        }
        $sql .= " ORDER BY a.tanggal ASC, k.nama_karyawan ASC";

        $stmt = $conn->prepare($sql);
        if ($search_name !== '') {
            $like = '%' . $search_name . '%';
            $stmt->bind_param("isss", $id_cabang, $start_date, $end_date, $like);
        } else {
            $stmt->bind_param("iss", $id_cabang, $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $headers = [
            'Employee ID', 'Full Name', 'Date', 'Check In', 'Check Out',
            'Late In', 'Early Out', 'Schedule Working Hour', 'Actual Working Hour', 'Real Working Hour',
            'Overtime Duration Before', 'Overtime Duration After', 'Hourly Time Off Taken',
            'Status Masuk', 'Keterangan',
        ];

        $xlsx = new SimpleXLSXWriter('Absensi ' . $cabang);
        $xlsx->setColumnWidths([14, 24, 12, 10, 10, 9, 9, 12, 12, 12, 12, 12, 12, 16, 14]);
        $xlsx->addTitleRow('REKAP ABSENSI (FORMAT TALENTA)', count($headers));
        $xlsx->addRow(['Cabang: ' . $cabang]);
        $xlsx->addRow(['Periode: ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date))]);
        $xlsx->addRow(['Tanggal Cetak: ' . date('d-m-Y H:i')]);
        $xlsx->addRow(['']);
        $xlsx->addHeaderRow($headers);

        while ($row = $result->fetch_assoc()) {
            $is_hadir_like = in_array($row['keterangan'], $KETERANGAN_HADIR_LIKE, true);
            $is_hari_kerja = isHariKerja($conn, $row['tanggal']);

            $jam_masuk = !empty($row['jam_masuk']) ? $row['jam_masuk'] : null;
            $jam_pulang = (!empty($row['jam_pulang']) && $row['jam_pulang'] !== '00:00:00') ? $row['jam_pulang'] : null;

            $check_in = $jam_masuk ? date('H:i', strtotime($jam_masuk)) : '';
            $check_out = $jam_pulang ? date('H:i', strtotime($jam_pulang)) : '';

            $late_in = '';
            $early_out = '';
            $schedule_wh = '';
            $actual_wh = '';
            $real_wh = '';
            $overtime_after = '';

            if ($is_hadir_like) {
                // Late In: hanya terisi untuk absensi yang dicatat SETELAH migrasi
                // menit_terlambat (007) berjalan - baris lama tetap kosong, bukan
                // ditebak, karena tidak ada sumber data mentahnya lagi.
                $late_in = formatDurasiMenit($row['menit_terlambat']);

                if ($jam_masuk && $jam_pulang) {
                    $real_wh = formatDurasiJam(hitungJamKerja($jam_masuk, $jam_pulang));
                }

                // Jadwal/Early Out/Overtime After hanya relevan di hari kerja normal
                // dengan shift tetap - hari lembur (mis. Sabtu) tidak punya jam
                // pulang baku untuk dibandingkan (lihat CLAUDE.md: hitung dari
                // durasi aktual, bukan dibandingkan ke jam_pulang shift).
                if ($is_hari_kerja) {
                    $shift = detectShiftUntukBaris($jam_masuk, $jam_pulang, $shifts_data);
                    if ($shift) {
                        $jadwal_menit = (int)round((strtotime($shift['jam_pulang']) - strtotime($shift['jam_masuk_akhir'])) / 60);
                        $schedule_wh = formatDurasiMenit($jadwal_menit);

                        // Actual Working Hour cuma masuk akal kalau memang ada jam
                        // masuk untuk diukur - baris "Tidak Absen Masuk" tidak punya
                        // titik acuan, jadi dibiarkan kosong (bukan dianggap 0 menit
                        // telat yang menyesatkan seolah kerja penuh).
                        if ($jam_masuk) {
                            $menit_terlambat_efektif = (int)($row['menit_terlambat'] ?? 0);
                            $actual_wh = formatDurasiMenit(max(0, $jadwal_menit - $menit_terlambat_efektif));
                        }

                        if ($jam_pulang) {
                            $selisih_pulang = (int)round((strtotime($jam_pulang) - strtotime($shift['jam_pulang'])) / 60);
                            if ($selisih_pulang > 0) {
                                $overtime_after = formatDurasiMenit($selisih_pulang);
                            } elseif ($selisih_pulang < 0) {
                                $early_out = formatDurasiMenit(abs($selisih_pulang));
                            }
                        }
                    }
                }
            }

            $status_masuk_text = $is_hadir_like
                ? (empty($jam_masuk)
                    ? (!empty($row['dikonversi_izin_setengah_hari']) ? 'Izin Setengah Hari' : 'Tidak Absen Masuk')
                    : ($row['status_masuk'] ?? '-'))
                : '-';

            $xlsx->addRow([
                $row['id_karyawan'],
                html_entity_decode($row['nama_karyawan'], ENT_QUOTES, 'UTF-8'),
                date('d-m-Y', strtotime($row['tanggal'])),
                $check_in,
                $check_out,
                $late_in,
                $early_out,
                $schedule_wh,
                $actual_wh,
                $real_wh,
                '0:00', // Overtime Duration Before - menunggu fitur approval lembur SPV
                $overtime_after,
                '0:00', // Hourly Time Off Taken - menunggu fitur konversi izin setengah hari
                $status_masuk_text,
                $row['keterangan'],
            ]);
        }
        $stmt->close();

        $xlsx->output('Absensi_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $cabang) . '_' . date('Y-m-d') . '.xlsx');

    } elseif ($format == 'pdf') {
        $data = json_decode($_POST['data'], true);
        // Export ke PDF menggunakan HTML
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Rekap Absensi - <?php echo htmlspecialchars($cabang); ?></title>
            <style>
                @page {
                    size: A4 landscape;
                    margin: 15mm;
                }
                
                body {
                    font-family: 'Arial', sans-serif;
                    font-size: 10px;
                    line-height: 1.5;
                    color: #333;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 25px;
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 15px;
                }
                
                .header h1 {
                    margin: 0;
                    font-size: 20px;
                    color: #667eea;
                    text-transform: uppercase;
                }
                
                .header h2 {
                    margin: 5px 0 0 0;
                    font-size: 16px;
                    font-weight: normal;
                }
                
                .info-box {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    border-left: 4px solid #667eea;
                }

                .info-box p {
                    margin: 3px 0;
                }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }
                
                th {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 10px 8px;
                    text-align: left;
                    font-weight: bold;
                    font-size: 11px;
                    border: 1px solid #667eea;
                }

                th:first-child { text-align: center; width: 5%; }
                th:nth-child(n+3) { text-align: center; }
                
                td {
                    padding: 8px;
                    border: 1px solid #dee2e6;
                }

                td:first-child { text-align: center; }
                td:nth-child(n+3) { text-align: center; }
                
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                
                /* Badge untuk Status Pulang */
                .status-setengah-hari {
                    color: #ff9800;
                    font-weight: 600;
                }
                
                .status-overtime {
                    color: #ffa726;
                    font-weight: 600;
                }
                
                .status-normal {
                    color: #4caf50;
                    font-weight: 600;
                }
                
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 9px;
                    color: #777;
                    border-top: 1px solid #dee2e6;
                    padding-top: 10px;
                }
                
                @media print {
                    body {
                        print-color-adjust: exact;
                        -webkit-print-color-adjust: exact;
                    }
                }
            </style>
            <script>
                window.onload = function() {
                    window.print();
                }
            </script>
        </head>
        <body>
            <div class="header">
                <h1>Rekap Histori Absensi</h1>
                <h2>JAVAG TEAM</h2>
            </div>
            
            <div class="info-box">
                <p><strong>Cabang:</strong> <?php echo htmlspecialchars($cabang); ?></p>
                <p><strong>Periode:</strong> <?php echo date('d F Y', strtotime($start_date)); ?> s/d <?php echo date('d F Y', strtotime($end_date)); ?></p>
                <p><strong>Tanggal Cetak:</strong> <?php echo date('d F Y H:i'); ?></p>
                <p><strong>Dicetak oleh:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Karyawan</th>
                        <th>Tanggal</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status Masuk</th>
                        <th>Status Pulang</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $row['no']; ?></td>
                            <td><?php echo htmlspecialchars(html_entity_decode($row['nama'], ENT_QUOTES, 'UTF-8')); ?></td>
                            <td><?php echo $row['tanggal']; ?></td>
                            <td><?php echo $row['jam_masuk']; ?></td>
                            <td><?php echo $row['jam_keluar']; ?></td>
                            <td><?php echo htmlspecialchars($row['status_masuk']); ?></td>
                            <td>
                                <?php 
                                $status_pulang = $row['status_pulang'];
                                if ($status_pulang == 'Setengah Hari') {
                                    echo '<span class="status-setengah-hari">Setengah Hari</span>';
                                } elseif ($status_pulang == 'Over Time') {
                                    echo '<span class="status-overtime">Over Time</span>';
                                } elseif ($status_pulang == 'Normal') {
                                    echo '<span class="status-normal">Normal</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">Tidak ada data untuk diekspor</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="footer">
                <p>Dokumen ini dicetak secara otomatis dari Sistem Absensi Javag Team</p>
                <p>&copy; <?php echo date('Y'); ?> Javag Team - All Rights Reserved</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
