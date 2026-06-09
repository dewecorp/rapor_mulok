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

// 1. Diagnosa Dasar
if (!function_exists('exec')) {
    echo json_encode([
        'success' => false,
        'message' => 'Fungsi PHP "exec" dinonaktifkan di hosting Anda. Fitur update otomatis tidak dapat berjalan.',
        'detail' => 'Hubungi provider hosting untuk mengaktifkan fungsi exec().'
    ]);
    exit;
}

if (!is_dir($base_path . '/.git')) {
    echo json_encode([
        'success' => false,
        'message' => 'Folder aplikasi di hosting bukan merupakan repository Git.',
        'detail' => 'Anda mungkin mengunggah file secara manual via File Manager sehingga folder .git tidak ada. Fitur ini memerlukan folder .git untuk berfungsi.'
    ]);
    exit;
}

// 2. Cek Git
$git_version = [];
exec('git --version 2>&1', $git_version, $git_status);

if ($git_status !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Perintah "git" tidak dikenali di server hosting.',
        'detail' => 'Pesan sistem: ' . implode(' ', $git_version)
    ]);
    exit;
}

// 3. Jalankan Git Pull
chdir($base_path);
$output = [];
$return_var = 0;

// Set environment variable untuk menghindari masalah interaktif
putenv('GIT_TERMINAL_PROMPT=0');

// Jalankan pull
exec('git pull origin master 2>&1', $output, $return_var);

if ($return_var === 0) {
    // 4. Bersihkan Cache
    if (file_exists('clear_cache.php')) {
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
    // Jika gagal, berikan detail log untuk diagnosa
    $error_detail = implode("\n", $output);
    
    // Cek jika ada masalah permission atau conflict
    if (strpos($error_detail, 'Permission denied') !== false) {
        $advice = 'Masalah izin akses (Permission). Pastikan SSH Key sudah terpasang di hosting atau gunakan HTTPS.';
    } elseif (strpos($error_detail, 'local changes') !== false) {
        $advice = 'Ada perubahan file di hosting yang belum di-commit. Git menolak menimpa file tersebut.';
    } else {
        $advice = 'Periksa log detail di bawah untuk informasi lebih lanjut.';
    }

    echo json_encode([
        'success' => false,
        'message' => 'Gagal menarik data dari GitHub.',
        'advice' => $advice,
        'detail' => $error_detail,
        'log' => $output
    ]);
}
exit;
