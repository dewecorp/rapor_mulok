<?php
/**
 * Konfigurasi Integrasi SIMAD
 * Website: https://simad.misultanfattah.sch.id/
 */

// URL API SIMAD (v1 students.php)
define('SIMAD_API_URL', 'https://simad.misultanfattah.sch.id/api/v1/students.php');

// Token/API Key
define('SIMAD_API_KEY', 'SIS_CENTRAL_HUB_SECRET_2026');

// Aktifkan sinkronisasi otomatis
define('SIMAD_AUTO_SYNC', true);

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
