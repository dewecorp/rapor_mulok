<?php
// Test langsung untuk melihat filename yang akan dibuat
require_once '../config/config.php';
require_once '../config/database.php';

$conn = getConnection();

// Ambil kelas_id dari GET parameter
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

echo "<h2>Test Filename Generation</h2>";
echo "<p><strong>GET kelas_id:</strong> " . (isset($_GET['kelas_id']) ? $_GET['kelas_id'] : 'tidak ada') . "</p>";
echo "<p><strong>Kelas ID (int):</strong> $kelas_id</p>";

// Variabel untuk nama kelas
$kelas_nama = 'Semua Kelas';
$kelas_nama_untuk_file = 'SemuaKelas';

// JIKA kelas_id > 0, AMBIL NAMA KELAS DARI DATABASE
if ($kelas_id > 0) {
    echo "<p style='color:blue;'>→ Kelas ID > 0, mencari di database...</p>";
    
    // Query langsung
    $query = "SELECT nama_kelas FROM kelas WHERE id = $kelas_id LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $kelas_nama = $row['nama_kelas'];
        echo "<p style='color:green;'>✓ Kelas ditemukan: <strong>$kelas_nama</strong></p>";
        
        // Bersihkan untuk nama file
        $kelas_nama_untuk_file = trim($kelas_nama);
        $kelas_nama_untuk_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = preg_replace('/_+/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = trim($kelas_nama_untuk_file, '_');
        
        echo "<p>Nama setelah dibersihkan: <strong>$kelas_nama_untuk_file</strong></p>";
        
        // Jika kosong, gunakan ID
        if (empty($kelas_nama_untuk_file)) {
            $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
            echo "<p style='color:orange;'>→ Nama kosong setelah dibersihkan, menggunakan: <strong>$kelas_nama_untuk_file</strong></p>";
        }
    } else {
        echo "<p style='color:red;'>✗ Kelas tidak ditemukan di database!</p>";
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
        echo "<p>Menggunakan fallback: <strong>$kelas_nama_untuk_file</strong></p>";
    }
} else {
    echo "<p style='color:gray;'>→ Kelas ID = 0 (Semua Kelas)</p>";
}

// BUAT FILENAME
$filename = 'Template_Import_Siswa_' . date('Y-m-d');

echo "<hr>";
echo "<h3>Pembuatan Filename:</h3>";
echo "<p>Base filename: <strong>$filename</strong></p>";

// TAMBAHKAN NAMA KELAS DI BELAKANG
if ($kelas_id > 0) {
    echo "<p style='color:blue;'>→ Kelas ID > 0, menambahkan nama kelas...</p>";
    
    // PASTIKAN tidak kosong
    if (empty($kelas_nama_untuk_file) || $kelas_nama_untuk_file == 'SemuaKelas') {
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
        echo "<p style='color:orange;'>→ Nama masih kosong, menggunakan: <strong>$kelas_nama_untuk_file</strong></p>";
    }
    
    // PASTI DITAMBAHKAN
    $filename .= '_' . $kelas_nama_untuk_file;
    echo "<p style='color:green;'>✓ Nama kelas ditambahkan: <strong>$kelas_nama_untuk_file</strong></p>";
} else {
    $filename .= '_SemuaKelas';
    echo "<p style='color:gray;'>→ Menambahkan: <strong>_SemuaKelas</strong></p>";
}

$filename .= '.xlsx';

echo "<hr>";
echo "<h2 style='color:blue;'>FILENAME FINAL:</h2>";
echo "<h1 style='color:green; font-size:24px;'>$filename</h1>";

echo "<hr>";
echo "<h3>Test dengan URL:</h3>";
echo "<ul>";
echo "<li><a href='?kelas_id=0'>?kelas_id=0</a> (Semua Kelas)</li>";

// Tampilkan semua kelas untuk test
$all = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY id LIMIT 10");
if ($all) {
    while ($r = $all->fetch_assoc()) {
        echo "<li><a href='?kelas_id={$r['id']}'>?kelas_id={$r['id']}</a> ({$r['nama_kelas']})</li>";
    }
}
echo "</ul>";

echo "<hr>";
echo "<p><a href='import.php'>← Kembali ke Import</a></p>";
?>

