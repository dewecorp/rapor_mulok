<?php
/**
 * Script untuk update aplikasi langsung dari GitHub (AJAX Handler)
 * Hanya dapat diakses oleh role Proktor (Admin)
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Set header JSON
header('Content-Type: application/json');

// Proteksi: Hanya proktor yang bisa akses
if (!isLoggedIn() || !hasRole('proktor')) {
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Anda harus login sebagai Admin.'
    ]);
    exit;
}

// Pastikan base path benar
$base_path = realpath(__DIR__);

// 1. Cek Git
$git_version = [];
exec('git --version 2>&1', $git_version, $git_status);

if ($git_status !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Git tidak terdeteksi di server. Hubungi admin server.'
    ]);
    exit;
}

// 2. Jalankan Git Pull
chdir($base_path);
$output = [];
$return_var = 0;

// Jalankan pull
exec('git pull origin master 2>&1', $output, $return_var);

if ($return_var === 0) {
    // 3. Bersihkan Cache
    if (file_exists('clear_cache.php')) {
        // Kita gunakan ob_start untuk menangkap output jika ada
        ob_start();
        include 'clear_cache.php';
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Aplikasi berhasil diperbarui ke versi terbaru dari GitHub.',
        'log' => $output
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat menarik data dari GitHub.',
        'log' => $output
    ]);
}
exit;
