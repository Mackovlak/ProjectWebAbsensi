<?php
return [
    'name' => 'Pulang cepat tidak lagi menunggu ACC: bersihkan pengajuan Pending lama + perbarui komentar kolom',
    'up' => function ($conn, MigrationLog $log) {
        // Dulu absen pulang sebelum jam pulang shift DIBLOKIR sampai izin pulang
        // cepat Disetujui, dan antrean tinjau hanya menampilkan tanggal hari ini.
        // Sekarang absen pulang langsung tercatat lalu ditinjau belakangan (lihat
        // jamPulangCepatPerluTinjau() di izin_functions.php), sehingga antrean tidak
        // lagi dibatasi CURDATE() - baris 'Pending' lama yang tak pernah ditinjau
        // akan ikut muncul. Baris itu basi: dengan alur lama karyawan tidak mungkin
        // absen pulang lebih awal tanpa ACC, jadi mereka pulang normal atau tidak
        // absen pulang sama sekali.
        //
        // Kondisinya sengaja meniru alur baru (bukan sekadar tanggal < hari ini)
        // supaya aman dijalankan kapan pun, juga setelah fitur baru sudah dipakai:
        // pulang cepat yang benar-benar terjadi (jam_pulang < jam pulang shift)
        // TIDAK disentuh. Status Disetujui/Ditolak juga tidak disentuh.
        $sql = "UPDATE absensi a
                JOIN karyawan k ON a.id_karyawan = k.id_karyawan
                SET a.izin_pulang_cepat = NULL
                WHERE a.izin_pulang_cepat = 'Pending'
                  AND a.tanggal < CURDATE()
                  AND (
                      a.jam_pulang IS NULL OR a.jam_pulang = '00:00:00'
                      OR a.jam_pulang >= (
                          SELECT jk.jam_pulang FROM jam_kerja jk WHERE jk.id_cabang = k.id_cabang
                          ORDER BY ABS(TIMESTAMPDIFF(MINUTE, a.jam_masuk, jk.jam_masuk_akhir)) ASC LIMIT 1
                      )
                  )";
        if ($conn->query($sql)) {
            $n = $conn->affected_rows;
            if ($n > 0) {
                $log->ok("$n pengajuan pulang cepat <code>Pending</code> lama (tidak jadi pulang cepat) dikosongkan.");
            } else {
                $log->skip("Tidak ada pengajuan pulang cepat Pending lama yang perlu dibersihkan.");
            }
        } else {
            $log->error("Gagal membersihkan pengajuan pulang cepat Pending lama: " . $conn->error);
            return;
        }

        // Komentar kolom dari migrasi 005 menyebut "wajib Disetujui sebelum absen
        // pulang diterima" - sudah tidak berlaku. Definisi kolomnya sendiri sama.
        $sql = "ALTER TABLE `absensi` MODIFY `izin_pulang_cepat`
                enum('Pending','Disetujui','Ditolak') COLLATE utf8mb4_general_ci DEFAULT NULL
                COMMENT 'Status tinjauan pulang lebih awal dari jam pulang shift. Tidak menahan absen pulang - Pending = menunggu ditinjau atasan'";
        if ($conn->query($sql)) {
            $log->ok("Komentar kolom <code>absensi.izin_pulang_cepat</code> diperbarui.");
        } else {
            $log->warn("Gagal memperbarui komentar kolom absensi.izin_pulang_cepat (tidak kritis): " . $conn->error);
        }
    },
];
