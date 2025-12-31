<?php
/**
 * Script untuk mengecek perubahan yang mungkin hilang
 * Akses: http://localhost/rapor-mulok/check_missing_changes.php
 */

require_once 'config/config.php';
require_once 'config/database.php';
requireLogin();

$conn = getConnection();
$results = [];

// Cek file-file penting yang mungkin berubah
$important_files = [
    'guru/penilaian.php',
    'guru/mengampu.php',
    'guru/materi-diampu.php',
    'wali-kelas/rapor.php',
    'wali-kelas/status-nilai.php',
    'rapor/cetak.php',
    'lembaga/materi.php',
    'index.php',
    'includes/header.php',
    'includes/footer.php'
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Check Missing Changes</title>";
echo "<style>body{font-family:Arial;padding:20px;} .file{background:#f5f5f5;padding:10px;margin:10px 0;border-left:4px solid #2d5016;} .exists{color:green;} .missing{color:red;} .info{background:#e3f2fd;padding:10px;margin:10px 0;}</style></head><body>";
echo "<h1>Pengecekan Perubahan yang Mungkin Hilang</h1>";

// Cek apakah file ada
foreach ($important_files as $file) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($file)) : 'N/A';
    
    echo "<div class='file'>";
    echo "<strong>" . ($exists ? "<span class='exists'>✓</span>" : "<span class='missing'>✗</span>") . " $file</strong><br>";
    echo "Ukuran: " . number_format($size) . " bytes<br>";
    echo "Terakhir diubah: $modified<br>";
    echo "</div>";
}

// Cek fungsi-fungsi penting di file-file tertentu
echo "<div class='info'><h2>Fungsi-fungsi Penting:</h2>";

$functions_to_check = [
    'guru/penilaian.php' => ['hitungPredikat', 'hitungDeskripsi', 'simpan_nilai'],
    'guru/mengampu.php' => ['getConnection'],
    'index.php' => ['requireLogin'],
];

foreach ($functions_to_check as $file => $functions) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        echo "<h3>$file:</h3><ul>";
        foreach ($functions as $func) {
            $found = strpos($content, "function $func") !== false || strpos($content, "$func(") !== false;
            echo "<li>" . ($found ? "✓" : "✗") . " $func</li>";
        }
        echo "</ul>";
    }
}

// Cek query database penting
echo "<h2>Query Database Penting:</h2>";
try {
    $tables = ['nilai_siswa', 'materi_mulok', 'siswa', 'kelas', 'pengguna'];
    echo "<ul>";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $result && $result->num_rows > 0;
        echo "<li>" . ($exists ? "✓" : "✗") . " Tabel $table " . ($exists ? "ada" : "TIDAK ADA") . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Cek kolom penting di tabel nilai_siswa
if ($conn) {
    try {
        $result = $conn->query("SHOW COLUMNS FROM nilai_siswa");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        echo "<h2>Kolom di tabel nilai_siswa:</h2><ul>";
        $important_columns = ['harian', 'pas_pat', 'rapor', 'predikat', 'deskripsi', 'nilai_pengetahuan', 'nilai_keterampilan'];
        foreach ($important_columns as $col) {
            $exists = in_array($col, $columns);
            echo "<li>" . ($exists ? "✓" : "✗") . " Kolom $col " . ($exists ? "ada" : "TIDAK ADA") . "</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
}

echo "</div>";
echo "<p><a href='index.php'>Kembali ke Dashboard</a></p>";
echo "</body></html>";


