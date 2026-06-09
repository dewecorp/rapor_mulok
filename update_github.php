<?php
/**
 * Script untuk update aplikasi langsung dari GitHub (AJAX Handler)
 * Versi Auto-Fix: Otomatis inisialisasi jika folder .git tidak ada
 */

require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('proktor')) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$base_path = realpath(__DIR__);
$repo_url = 'https://github.com/dewecorp/rapor_mulok.git';

// 1. Cek ketersediaan fungsi exec
if (!function_exists('exec')) {
    echo json_encode([
        'success' => false,
        'message' => 'Fungsi PHP "exec" dinonaktifkan di hosting.',
        'detail' => 'Hubungi provider hosting untuk mengaktifkan fungsi exec().'
    ]);
    exit;
}

// 2. Cek ketersediaan perintah git
exec('git --version 2>&1', $git_v, $git_s);
if ($git_s !== 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Perintah "git" tidak ditemukan di server.',
        'detail' => implode(' ', $git_v)
    ]);
    exit;
}

chdir($base_path);
$log = [];

// 3. AUTO-FIX: Inisialisasi Git jika folder .git hilang
if (!is_dir($base_path . '/.git')) {
    $log[] = "Folder .git tidak ditemukan. Melakukan inisialisasi ulang...";
    exec('git init 2>&1', $o, $s);
    exec("git remote add origin $repo_url 2>&1", $o, $s);
}

// 4. Proses Update (Fetch & Reset)
// Menggunakan fetch + reset --hard lebih ampuh daripada 'pull' untuk sinkronisasi pertama kali
$log[] = "Mengambil data terbaru dari GitHub...";
exec('git fetch --all 2>&1', $o1, $s1);
$log = array_merge($log, $o1);

$log[] = "Menyelaraskan file dengan versi GitHub (Master)...";
exec('git reset --hard origin/master 2>&1', $o2, $s2);
$log = array_merge($log, $o2);

if ($s2 === 0) {
    // 5. Bersihkan Cache
    if (file_exists('clear_cache.php')) {
        ob_start();
        include 'clear_cache.php';
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Aplikasi berhasil diupdate.',
        'log' => $log
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal melakukan update.',
        'detail' => implode("\n", $log),
        'log' => $log
    ]);
}
exit;
