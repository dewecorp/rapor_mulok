<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

echo "<h2>Test Query Kelas</h2>";
echo "<pre>";

// Test dengan ID 1
$kelas_id_test = isset($_GET['id']) ? (int)$_GET['id'] : 1;

echo "Testing dengan Kelas ID: $kelas_id_test\n\n";

// Query langsung
$sql = "SELECT id, nama_kelas FROM kelas WHERE id = " . (int)$kelas_id_test;
echo "SQL: $sql\n\n";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Kelas DITEMUKAN:\n";
    echo "  ID: {$row['id']}\n";
    echo "  Nama: '{$row['nama_kelas']}'\n\n";
    
    // Test nama file
    $nama_kelas = trim($row['nama_kelas']);
    $nama_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $nama_kelas);
    $nama_file = preg_replace('/_+/', '_', $nama_file);
    $nama_file = trim($nama_file, '_');
    
    $filename = 'Template_Import_Siswa_' . date('Y-m-d') . '_' . $nama_file . '.xlsx';
    echo "Nama file yang akan dibuat: $filename\n";
} else {
    echo "Kelas TIDAK DITEMUKAN!\n\n";
    
    // Tampilkan semua kelas yang ada
    echo "Daftar semua kelas di database:\n";
    $all = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY id");
    if ($all) {
        while ($r = $all->fetch_assoc()) {
            echo "  ID: {$r['id']}, Nama: '{$r['nama_kelas']}'\n";
        }
    } else {
        echo "  Error query: " . $conn->error . "\n";
    }
}

echo "\n\n";
echo "Test dengan URL: ?id=1, ?id=2, dll\n";
echo "</pre>";
?>

