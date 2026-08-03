<?php
// Mulai session
session_start();

// Hancurkan semua data session
$_SESSION = array();
session_destroy();

// Redirect ke halaman login
header("Location: login.php");
exit;
?>
