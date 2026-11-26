<?php
require 'config.php';

// Cabut token "Tetap masuk" perangkat ini, kalau tidak sesi akan langsung
// dipulihkan lagi oleh config.php pada request berikutnya.
rememberCabutTokenIni($conn);

// Hancurkan semua data session
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();

// Redirect ke halaman login
header("Location: login.php");
exit;
