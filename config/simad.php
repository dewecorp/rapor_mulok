<?php
/**
 * Konfigurasi Integrasi SIMAD
 * Website: https://simad.misultanfattah.sch.id/
 *
 * Hosting: pastikan URL di bawah mengarah ke domain SIMAD yang sama dengan sumber data resmi
 * (bukan lingkungan uji / salinan DB lama). Pastikan tabel pengguna di MySQL utf8mb4.
 */

// URL API SIMAD (v1 students.php)
define('SIMAD_API_URL', 'https://simad.misultanfattah.sch.id/api/v1/students.php');

// URL API SIMAD — data guru (Central Hub). Sesuaikan path file di server SIMAD jika berbeda.
define('SIMAD_TEACHERS_API_URL', 'https://simad.misultanfattah.sch.id/api/v1/teachers.php');

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
