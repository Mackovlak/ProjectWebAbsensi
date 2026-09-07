<?php
return [
    'name' => 'users.is_active + unique constraint satu user per karyawan',
    'up' => function ($conn, MigrationLog $log) {
        if (!kolomAda($conn, 'users', 'is_active')) {
            if ($conn->query("ALTER TABLE `users`
                              ADD `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`,
                              ADD KEY `idx_users_active` (`is_active`)")) {
                $log->ok("Kolom <code>users.is_active</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom users.is_active: " . $conn->error);
                return; // langkah berikutnya butuh kolom ini konsisten - jangan lanjut
            }
        } else {
            $log->skip("Kolom <code>users.is_active</code> sudah ada.");
        }

        // Unique key butuh data bersih dulu - satu karyawan tidak boleh
        // tertaut ke lebih dari satu akun user.
        $dup = $conn->query("SELECT id_karyawan, COUNT(*) AS total
                             FROM users WHERE id_karyawan IS NOT NULL
                             GROUP BY id_karyawan HAVING COUNT(*) > 1");
        if ($dup && $dup->num_rows > 0) {
            $log->error("Masih ada karyawan yang ditautkan ke lebih dari satu user - perbaiki dulu lewat "
                . "menu <b>Setting Users</b>, lalu jalankan migrasi ini lagi.");
            return;
        }

        if (!indexAda($conn, 'users', 'uniq_users_id_karyawan')) {
            if ($conn->query("ALTER TABLE `users` ADD UNIQUE KEY `uniq_users_id_karyawan` (`id_karyawan`)")) {
                $log->ok("Index unik <code>users.id_karyawan</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah index unik users.id_karyawan: " . $conn->error);
            }
        } else {
            $log->skip("Index unik <code>users.id_karyawan</code> sudah ada.");
        }
    },
];
