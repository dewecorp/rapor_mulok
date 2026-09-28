<?php
/**
 * Konfigurasi Integrasi SIMAD
 * Website: https://simad.misultanfattah.sch.id/
 *
 * Hosting: pastikan URL di bawah mengarah ke domain SIMAD yang sama dengan sumber data resmi
 * (bukan lingkungan uji / salinan DB lama). Pastikan tabel pengguna di MySQL utf8mb4.
 */

require_once __DIR__ . '/database.php';

// URL API SIMAD Default
$simad_url_default = 'https://simad.misultanfattah.sch.id/api/v1/students.php';
$simad_teachers_url_default = 'https://simad.misultanfattah.sch.id/api/v1/teachers.php';
$simad_key_default = 'SIS_CENTRAL_HUB_SECRET_2026';

$simad_url = null;
$simad_teachers_url = null;
$simad_key = null;

try {
    $conn_simad = getConnection();
    if ($conn_simad) {
        $res_simad = $conn_simad->query("SELECT simad_api_url, simad_teachers_api_url, simad_api_key FROM pengaturan_aplikasi LIMIT 1");
        if ($res_simad && $row_simad = $res_simad->fetch_assoc()) {
            if (!empty($row_simad['simad_api_url'])) $simad_url = trim($row_simad['simad_api_url']);
            if (!empty($row_simad['simad_teachers_api_url'])) $simad_teachers_url = trim($row_simad['simad_teachers_api_url']);
            if (!empty($row_simad['simad_api_key'])) $simad_key = trim($row_simad['simad_api_key']);
        }
    }
} catch (Throwable $e) {
    // Fallback to default
}

// URL API SIMAD (v1 students.php)
if (!defined('SIMAD_API_URL')) {
    define('SIMAD_API_URL', $simad_url ?: $simad_url_default);
}

// URL API SIMAD — data guru (Central Hub).
if (!defined('SIMAD_TEACHERS_API_URL')) {
    define('SIMAD_TEACHERS_API_URL', $simad_teachers_url ?: $simad_teachers_url_default);
}

// Token/API Key
if (!defined('SIMAD_API_KEY')) {
    define('SIMAD_API_KEY', $simad_key ?: $simad_key_default);
}

// Aktifkan sinkronisasi otomatis
if (!defined('SIMAD_AUTO_SYNC')) {
    define('SIMAD_AUTO_SYNC', true);
}

/**
 * Pemetaan kolom SIMAD ke kolom database Rapor Mulok
 * Kunci adalah kolom SIMAD, nilai adalah kolom lokal
 */
function getSimadMapping() {
    return [
        'nisn' => 'nisn',
        'nama_siswa' => 'nama',
        'jenis_kelamin' => 'jenis_kelamin', // L/P
        'tempat_lahir' => 'tempat_lahir',
        'tanggal_lahir' => 'tanggal_lahir',
        'wali' => 'orangtua_wali',
        'nama_kelas' => 'kelas'
    ];
}
