<?php
require 'config.php';
$query = "SHOW COLUMNS FROM users LIKE 'wa_token'";
$result = $conn->query($query);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD wa_token VARCHAR(255) NULL DEFAULT NULL AFTER ttd_path");
    echo "Kolom wa_token berhasil ditambahkan.";
} else {
    echo "Kolom wa_token sudah ada.";
}
$conn->close();
?>
