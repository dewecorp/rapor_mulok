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
define('APP_NAME', 'Rapor Mulok Digital');
define('APP_SHORT', 'RMD');
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

// Fungsi untuk mendapatkan path relatif ke root aplikasi secara konsisten
// Sederhana dan konsisten - menggunakan BASE_URL untuk menentukan root aplikasi
function getRelativePath() {
    // Gunakan SCRIPT_NAME untuk mendapatkan path script
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Normalize path separators
    $scriptName = str_replace('\\', '/', $scriptName);
    
    // Dapatkan direktori script (tanpa nama file)
    $scriptDir = dirname($scriptName);
    
    // Normalize: hapus leading slash
    $scriptDir = ltrim($scriptDir, '/');
    
    // Gunakan BASE_URL untuk menentukan root aplikasi
    // BASE_URL format: http://localhost/rapor-mulok/
    // Ekstrak path aplikasi dari BASE_URL
    $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/';
    $baseUrlPath = parse_url($baseUrl, PHP_URL_PATH);
    // Normalize: hapus leading dan trailing slash
    $baseUrlPath = trim($baseUrlPath, '/');
    
    // Jika BASE_URL path kosong, berarti aplikasi di root web server
    if ($baseUrlPath === '') {
        // Jika script di root web server, return empty string
        if ($scriptDir === '' || $scriptDir === '.') {
            return '';
        }
        // Hitung depth dari root web server
        $parts = explode('/', $scriptDir);
        $parts = array_filter($parts, function($p) { return $p !== '' && $p !== '.'; });
        $depth = count($parts);
        return $depth > 0 ? str_repeat('../', $depth) : '';
    }
    
    // Jika script di root aplikasi (sama dengan BASE_URL path), return empty string
    if ($scriptDir === $baseUrlPath) {
        return '';
    }
    
    // Jika script di dalam direktori aplikasi, hitung depth relatif
    if (strpos($scriptDir, $baseUrlPath . '/') === 0) {
        // Hapus base path dari script dir
        $relativePath = substr($scriptDir, strlen($baseUrlPath) + 1);
        $parts = explode('/', $relativePath);
        $parts = array_filter($parts, function($p) { return $p !== '' && $p !== '.'; });
        $depth = count($parts);
        return $depth > 0 ? str_repeat('../', $depth) : '';
    }
    
    // Fallback: hitung depth biasa
    $parts = explode('/', $scriptDir);
    $parts = array_filter($parts, function($p) { return $p !== '' && $p !== '.'; });
    $depth = count($parts);
    return $depth > 0 ? str_repeat('../', $depth) : '';
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
    
    // Jika role adalah array, cek apakah user memiliki salah satu role
    if (is_array($role)) {
        $has_access = false;
        foreach ($role as $r) {
            if (hasRole($r)) {
                $has_access = true;
                break;
            }
        }
        if (!$has_access) {
            // Pastikan tidak ada output sebelum redirect
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan BASE_URL untuk redirect yang benar
            $redirect_url = BASE_URL . 'index.php';
            header('Location: ' . $redirect_url);
            exit();
        }
    } else {
        // Jika role adalah string tunggal
        if (!hasRole($role)) {
            // Pastikan tidak ada output sebelum redirect
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan BASE_URL untuk redirect yang benar
            $redirect_url = BASE_URL . 'index.php';
            header('Location: ' . $redirect_url);
            exit();
        }
    }
}

// Fungsi helper untuk mendapatkan base URL aplikasi secara konsisten
function getBaseUrl() {
    // Gunakan konstanta BASE_URL jika tersedia
    if (defined('BASE_URL')) {
        return BASE_URL;
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Gunakan base path dari session jika tersedia untuk konsistensi
    if (isset($_SESSION['app_base_path'])) {
        $basePath = $_SESSION['app_base_path'];
        // Konversi relative path ke absolute URL
        if ($basePath === '') {
            return $protocol . '://' . $host . '/';
        } else {
            // Hitung path aplikasi dari base path
            $pathParts = explode('/', trim($basePath, '/'));
            $appPath = '';
            foreach ($pathParts as $part) {
                if ($part === '..') {
                    // Skip, akan dihitung dari root
                }
            }
            // Untuk sekarang, gunakan konstanta BASE_URL atau hitung dari root
            return $protocol . '://' . $host . '/';
        }
    }
    
    // Fallback: hitung dari script directory
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = dirname($scriptName);
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') {
        return $protocol . '://' . $host . '/';
    }
    
    $parts = explode('/', trim($scriptDir, '/'));
    $parts = array_filter($parts, function($p) { return $p !== '' && $p !== '.'; });
    $depth = count($parts);
    
    if ($depth <= 1) {
        return $protocol . '://' . $host . '/';
    }
    
    return $protocol . '://' . $host . '/';
}

// Fungsi helper untuk redirect yang aman
function redirect($url, $useAbsoluteUrl = false) {
    // Pastikan tidak ada output sebelum redirect
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    if ($useAbsoluteUrl) {
        // Gunakan absolute URL lengkap
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            // Pastikan URL dimulai dengan /
            if (strpos($url, '/') !== 0) {
                $url = '/' . $url;
            }
            header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $url);
        } else {
            header('Location: ' . $url);
        }
    } else {
        // Gunakan relative path
        $path = getRelativePath();
        header('Location: ' . $path . $url);
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
