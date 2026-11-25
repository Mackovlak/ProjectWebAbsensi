<?php
return [
    'name' => 'Web Push: tabel push_subscriptions (langganan notifikasi per perangkat)',
    'up' => function ($conn, MigrationLog $log) {
        if (tabelAda($conn, 'push_subscriptions')) {
            $log->skip("Tabel <code>push_subscriptions</code> sudah ada.");
            return;
        }

        // Satu baris = satu browser/perangkat. endpoint_hash (SHA-256 endpoint)
        // dipakai sebagai kunci unik karena endpoint bisa sangat panjang.
        // Perangkat yang sama dipakai bergantian oleh beberapa akun, jadi baris
        // dipindah-kepemilikan (upsert) saat akun lain berlangganan.
        $sql = "CREATE TABLE `push_subscriptions` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `endpoint` TEXT COLLATE utf8mb4_general_ci NOT NULL,
            `endpoint_hash` CHAR(64) COLLATE utf8mb4_general_ci NOT NULL,
            `p256dh` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
            `auth` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
            `user_agent` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_success_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_push_endpoint` (`endpoint_hash`),
            KEY `idx_push_user` (`user_id`),
            CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        if ($conn->query($sql)) {
            $log->ok("Tabel <code>push_subscriptions</code> berhasil dibuat.");
        } else {
            $log->error("Gagal membuat push_subscriptions: " . $conn->error);
        }
    },
];
