<?php
return [
    'name' => 'users.wa_token - token WhatsApp (Fonnte) per user, untuk reminder absen',
    'up' => function ($conn, MigrationLog $log) {
        if (!kolomAda($conn, 'users', 'wa_token')) {
            if ($conn->query("ALTER TABLE `users` ADD `wa_token` VARCHAR(255) NULL DEFAULT NULL AFTER `ttd_path`")) {
                $log->ok("Kolom <code>users.wa_token</code> berhasil ditambahkan.");
            } else {
                $log->error("Gagal menambah kolom users.wa_token: " . $conn->error);
            }
        } else {
            $log->skip("Kolom <code>users.wa_token</code> sudah ada.");
        }
    },
];
