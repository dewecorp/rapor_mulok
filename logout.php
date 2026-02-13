<?php
// Pastikan tidak ada output sebelum ini
if (ob_get_level() > 0) {
    ob_clean();
}

require_once 'config/config.php';

// Pastikan session aktif sebelum destroy
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Catat aktivitas logout sebelum session dihancurkan
if (isset($_SESSION['user_id'])) {
    logAktivitas('logout', 'User logout dari sistem');
}

// Hapus semua session
session_unset();
session_destroy();

// Hapus cookie session jika ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect ke halaman login menggunakan path yang benar
$path = getRelativePath();
header('Location: ' . $path . 'login.php');
exit();

