<?php
// Test sederhana untuk melihat parameter yang diterima
require_once '../config/config.php';
require_once '../config/database.php';

$conn = getConnection();

echo "<h2>Test Download Template</h2>";
echo "<p>GET kelas_id: " . (isset($_GET['kelas_id']) ? $_GET['kelas_id'] : 'tidak ada') . "</p>";

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
echo "<p>Kelas ID (int): $kelas_id</p>";

if ($kelas_id > 0) {
    $query = "SELECT id, nama_kelas FROM kelas WHERE id = $kelas_id LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p style='color:green;'>✓ Kelas ditemukan!</p>";
        echo "<p>ID: {$row['id']}</p>";
        echo "<p>Nama: {$row['nama_kelas']}</p>";
        
        // Bersihkan untuk nama file
        $kelas_nama_untuk_file = trim($row['nama_kelas']);
        $kelas_nama_untuk_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = preg_replace('/_+/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = trim($kelas_nama_untuk_file, '_');
        
        echo "<p>Nama untuk file: $kelas_nama_untuk_file</p>";
        
        $filename = 'Template_Import_Siswa_' . date('Y-m-d') . '_' . $kelas_nama_untuk_file . '.xlsx';
        echo "<p style='font-weight:bold; color:blue;'>Filename yang akan dibuat: $filename</p>";
    } else {
        echo "<p style='color:red;'>✗ Kelas tidak ditemukan!</p>";
    }
} else {
    echo "<p>Kelas ID = 0 (Semua Kelas)</p>";
    $filename = 'Template_Import_Siswa_' . date('Y-m-d') . '_SemuaKelas.xlsx';
    echo "<p style='font-weight:bold; color:blue;'>Filename yang akan dibuat: $filename</p>";
}

echo "<hr>";
echo "<h3>Semua Kelas di Database:</h3>";
$all = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY id");
if ($all) {
    echo "<ul>";
    while ($r = $all->fetch_assoc()) {
        echo "<li>ID: {$r['id']}, Nama: '{$r['nama_kelas']}'</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='import.php'>Kembali ke Import</a></p>";
echo "<p>Test dengan: <a href='?kelas_id=1'>?kelas_id=1</a> | <a href='?kelas_id=2'>?kelas_id=2</a> | <a href='?kelas_id=0'>?kelas_id=0</a></p>";
?>

