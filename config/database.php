<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rapor_mulok');

// Koneksi Database
function getConnection() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Koneksi gagal: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        // Set collation untuk memastikan case sensitivity
        $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_bin");
        // Set timeout untuk menghindari loading terlalu lama
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        $conn->options(MYSQLI_OPT_READ_TIMEOUT, 5);
        return $conn;
    } catch (mysqli_sql_exception $e) {
        throw new Exception("Koneksi gagal: " . $e->getMessage());
    }
}
