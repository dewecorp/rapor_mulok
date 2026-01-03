<?php
// Set HTTP headers untuk prevent cache (harus sebelum output apapun)
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
}

// Aktifkan output buffering untuk mencegah masalah redirect
if (ob_get_level() == 0) {
    ob_start();
}

// Set timezone ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

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
    // Pastikan session aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Validasi role dari database hanya sekali per request (gunakan flag)
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && !isset($_SESSION['_role_validated'])) {
        try {
            require_once __DIR__ . '/database.php';
            $conn = getConnection();
            $user_id = $_SESSION['user_id'];
            
            $stmt = $conn->prepare("SELECT role FROM pengguna WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $user_db = $result->fetch_assoc();
                $role_db = $user_db['role'] ?? null;
                
                // Jika role di database berbeda dengan session, update session
                if ($role_db && $_SESSION['role'] != $role_db) {
                    $_SESSION['role'] = $role_db;
                }
            }
            
            // Set flag bahwa role sudah divalidasi untuk request ini
            $_SESSION['_role_validated'] = true;
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // Jika error, gunakan role dari session
        }
    }
    
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
    if (!hasRole($role)) {
        // Pastikan tidak ada output sebelum redirect
        if (ob_get_level() > 0) {
            ob_clean();
        }
        // Gunakan path absolut ke root untuk menghindari masalah redirect di subdirektori
        $redirect_url = '/index.php';
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $redirect_url);
        } else {
            header('Location: ' . $redirect_url);
        }
        exit();
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

// Fungsi untuk set page title (disimpan di session)
function setPageTitle($title) {
    // Pastikan session sudah aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Pastikan title tidak kosong
    if (empty($title)) {
        $title = APP_SHORT;
    }
    $_SESSION['page_title'] = trim($title);
}

// Fungsi untuk get page title
function getPageTitle() {
    return $_SESSION['page_title'] ?? APP_SHORT;
}

// Fungsi untuk logging aktivitas pengguna
// Parameter opsional $existing_conn: jika disediakan, gunakan koneksi yang sudah ada (tidak akan ditutup)
function logAktivitas($jenis_aktivitas, $deskripsi = '', $tabel_target = null, $record_id = null, $existing_conn = null) {
    // Pastikan session aktif
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Jika belum login, skip logging
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['nama']) || !isset($_SESSION['role'])) {
        error_log("logAktivitas ERROR: Session tidak tersedia");
        error_log("  - user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
        error_log("  - nama: " . (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'not set'));
        error_log("  - role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'not set'));
        error_log("  - session_status: " . session_status());
        return false;
    }
    
    // Pastikan tidak ada output yang sudah dikirim
    if (headers_sent()) {
        error_log("logAktivitas WARNING: Headers already sent, but continuing...");
    }
    
    $conn = null;
    $should_close = true;
    try {
        // Gunakan koneksi yang sudah ada jika disediakan
        if ($existing_conn !== null) {
            $conn = $existing_conn;
            $should_close = false;
        } else {
            require_once __DIR__ . '/database.php';
            $conn = getConnection();
            $should_close = true;
        }
        
        // Buat tabel aktivitas_pengguna jika belum ada (migrasi dari aktivitas_login)
        $conn->query("CREATE TABLE IF NOT EXISTS `aktivitas_pengguna` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `nama` varchar(255) NOT NULL,
            `role` varchar(50) NOT NULL,
            `jenis_aktivitas` varchar(50) NOT NULL,
            `deskripsi` text DEFAULT NULL,
            `tabel_target` varchar(100) DEFAULT NULL,
            `record_id` int(11) DEFAULT NULL,
            `ip_address` varchar(50) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `waktu` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_waktu` (`waktu`),
            KEY `idx_role` (`role`),
            KEY `idx_jenis_aktivitas` (`jenis_aktivitas`),
            KEY `idx_tabel_target` (`tabel_target`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Hapus aktivitas yang lebih dari 24 jam
        $conn->query("DELETE FROM aktivitas_pengguna WHERE waktu < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        
        // Insert aktivitas
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500); // Batasi panjang user_agent
        
        // Siapkan nilai untuk tabel_target dan record_id (handle null)
        $tabel_target_sql = $tabel_target !== null ? "'" . $conn->real_escape_string($tabel_target) . "'" : "NULL";
        $record_id_sql = $record_id !== null ? (int)$record_id : "NULL";
        
        // Escape deskripsi dengan benar
        $deskripsi_escaped = $conn->real_escape_string($deskripsi);
        
        // Gunakan query langsung dengan escape yang aman untuk menghindari masalah bind_param dengan null
        $sql = "INSERT INTO aktivitas_pengguna (user_id, nama, role, jenis_aktivitas, deskripsi, tabel_target, record_id, ip_address, user_agent) 
                VALUES (
                    " . (int)$_SESSION['user_id'] . ",
                    '" . $conn->real_escape_string($_SESSION['nama']) . "',
                    '" . $conn->real_escape_string($_SESSION['role']) . "',
                    '" . $conn->real_escape_string($jenis_aktivitas) . "',
                    '" . $deskripsi_escaped . "',
                    $tabel_target_sql,
                    $record_id_sql,
                    '" . $conn->real_escape_string($ip_address) . "',
                    '" . $conn->real_escape_string($user_agent) . "'
                )";
        
        // Jalankan query
        error_log("logAktivitas: Executing SQL query");
        $result = $conn->query($sql);
        
        // Cek error jika ada
        if (!$result) {
            $error_msg = $conn->error ?? 'Unknown error';
            $error_code = $conn->errno ?? 0;
            error_log("logAktivitas ERROR [$error_code]: " . $error_msg);
            error_log("SQL: " . $sql);
            error_log("User ID: " . ($_SESSION['user_id'] ?? 'not set'));
            error_log("Jenis Aktivitas: " . $jenis_aktivitas);
            error_log("Connection error: " . ($conn->error ?? 'none'));
            
            // Hanya tutup koneksi jika kita yang membuatnya
            if ($should_close && $conn) {
                $conn->close();
            }
            return false;
        }
        
        error_log("logAktivitas: Query executed successfully");
        
        // Hanya tutup koneksi jika kita yang membuatnya
        if ($should_close && $conn) {
            $conn->close();
        }
        
        return true;
    } catch (Exception $e) {
        // Log error tapi jangan gagalkan operasi utama
        error_log("Error logging activity: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        if ($should_close && $conn) {
            $conn->close();
        }
        return false;
    } catch (Error $e) {
        // Tangani fatal error juga
        error_log("Fatal error logging activity: " . $e->getMessage());
        if ($should_close && $conn) {
            $conn->close();
        }
        return false;
    }
}
