<?php
// Aktifkan output buffering untuk mencegah masalah redirect
if (ob_get_level() == 0) {
    ob_start();
}

// Start session hanya jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Aplikasi
define('APP_NAME', 'Rapor Mulok Khusus');
define('APP_SHORT', 'RMK');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/rapor-mulok/');

// Path
define('BASE_PATH', __DIR__ . '/../');
define('UPLOAD_PATH', BASE_PATH . 'uploads/');
define('BACKUP_PATH', BASE_PATH . 'backups/');

// Role
define('ROLE_PROKTOR', 'proktor');
define('ROLE_WALI_KELAS', 'wali_kelas');
define('ROLE_GURU', 'guru');

// Cek Login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Cek Role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] == $role;
}

// Fungsi untuk mendapatkan path relatif ke root
function getRelativePath() {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $scriptDir = dirname($scriptPath);
    
    // Normalize path separators
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Jika di root, return empty string
    if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') {
        return '';
    }
    
    // Hitung kedalaman direktori
    $parts = explode('/', trim($scriptDir, '/'));
    $parts = array_filter($parts, function($p) { return $p !== '' && $p !== '.'; });
    $depth = count($parts);
    
    // Jika depth = 0 atau 1, berarti di root atau satu level
    if ($depth <= 1) {
        return '';
    }
    
    return str_repeat('../', $depth - 1);
}

// Redirect jika belum login
function requireLogin() {
    // Pastikan session aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isLoggedIn()) {
        $path = getRelativePath();
        // Pastikan tidak ada output sebelum redirect
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: ' . $path . 'login.php');
        exit();
    }
}

// Redirect berdasarkan role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $path = getRelativePath();
        // Pastikan tidak ada output sebelum redirect
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: ' . $path . 'index.php');
        exit();
    }
}

// Fungsi helper untuk redirect yang aman
function redirect($url, $useRelativePath = true) {
    // Pastikan tidak ada output sebelum redirect
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if ($useRelativePath) {
        $path = getRelativePath();
        header('Location: ' . $path . $url);
    } else {
        header('Location: ' . $url);
    }
    exit();
}

// Format tanggal Indonesia
function tglIndo($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}

// Format tanggal waktu Indonesia
function tglWaktuIndo($datetime) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $hari = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    $pecah = explode(' ', $datetime);
    $tgl = explode('-', $pecah[0]);
    $waktu = $pecah[1];
    $hariIndex = date('w', strtotime($pecah[0]));
    return $hari[$hariIndex] . ', ' . $tgl[2] . ' ' . $bulan[(int)$tgl[1]] . ' ' . $tgl[0] . ' ' . $waktu;
}
