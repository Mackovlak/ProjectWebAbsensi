<?php
require 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: text/plain; charset=UTF-8');

$active_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($active_column->num_rows === 0) {
    $conn->query("ALTER TABLE users
                  ADD is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
                  ADD KEY idx_users_active (is_active)");
    echo "Kolom status aktif user berhasil ditambahkan.\n";
} else {
    echo "Kolom status aktif user sudah ada; tidak ada perubahan.\n";
}

$duplicate_links = $conn->query("SELECT id_karyawan, COUNT(*) AS total
                                 FROM users
                                 WHERE id_karyawan IS NOT NULL
                                 GROUP BY id_karyawan
                                 HAVING COUNT(*) > 1");

if ($duplicate_links->num_rows > 0) {
    http_response_code(409);
    echo "Migrasi dibatalkan: masih ada karyawan yang ditautkan ke lebih dari satu user.\n";
    echo "Perbaiki tautan ganda melalui Setting Users, lalu jalankan migrasi ini kembali.\n";
    exit();
}

$index = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'uniq_users_id_karyawan'");
if ($index->num_rows === 0) {
    $conn->query("ALTER TABLE users
                  ADD UNIQUE KEY uniq_users_id_karyawan (id_karyawan)");
    echo "Index unik users.id_karyawan berhasil ditambahkan.\n";
} else {
    echo "Index unik users.id_karyawan sudah ada; tidak ada perubahan.\n";
}

$conn->close();
