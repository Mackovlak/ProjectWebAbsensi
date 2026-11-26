<?php
return [
    'name' => 'Login tetap tersimpan: tabel user_remember_tokens (token "ingat perangkat ini")',
    'up' => function ($conn, MigrationLog $log) {
        if (tabelAda($conn, 'user_remember_tokens')) {
            $log->skip("Tabel <code>user_remember_tokens</code> sudah ada.");
            return;
        }

        // Satu baris = satu perangkat/browser yang memilih "Tetap masuk".
        // Cookie berisi "selector:validator"; yang disimpan hanya SHA-256 dari
        // validator, sehingga bocornya tabel ini tidak membuat siapa pun bisa
        // login. Lihat remember_functions.php.
        $sql = "CREATE TABLE `user_remember_tokens` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `selector` CHAR(24) COLLATE utf8mb4_general_ci NOT NULL,
            `validator_hash` CHAR(64) COLLATE utf8mb4_general_ci NOT NULL,
            `user_agent` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `ip_address` VARCHAR(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_remember_selector` (`selector`),
            KEY `idx_remember_user` (`user_id`),
            KEY `idx_remember_expires` (`expires_at`),
            CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        if ($conn->query($sql)) {
            $log->ok("Tabel <code>user_remember_tokens</code> berhasil dibuat.");
        } else {
            $log->error("Gagal membuat user_remember_tokens: " . $conn->error);
        }
    },
];
