<?php
/**
 * ==========================================
 * ALERT MESSAGES - IMPROVED VERSION
 * Support HTML rendering dengan aman
 * ==========================================
 */

if (isset($_SESSION['success_message'])) {
    // PERBAIKAN: Gunakan strip_tags untuk keamanan, tapi izinkan beberapa tag aman
    $safe_message = strip_tags($_SESSION['success_message'], '<strong><b><em><i><span>');
    echo '<div class="alert alert-success">' . $safe_message . '</div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    // PERBAIKAN: Gunakan strip_tags untuk keamanan, tapi izinkan beberapa tag aman
    $safe_message = strip_tags($_SESSION['error_message'], '<strong><b><em><i><span>');
    echo '<div class="alert alert-danger">' . $safe_message . '</div>';
    unset($_SESSION['error_message']);
}
?>
