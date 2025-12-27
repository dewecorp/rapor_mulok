<?php
// Debug langsung untuk melihat apa yang terjadi
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

echo "<h2>Debug Download Template</h2>";
echo "<pre>";

// Ambil kelas_id dari GET parameter
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

echo "GET kelas_id: " . (isset($_GET['kelas_id']) ? $_GET['kelas_id'] : 'tidak ada') . "\n";
echo "Kelas ID (int): $kelas_id\n\n";

// Variabel untuk nama kelas
$kelas_nama = 'Semua Kelas';
$kelas_nama_untuk_file = 'SemuaKelas';

// JIKA kelas_id > 0, AMBIL NAMA KELAS DARI DATABASE
if ($kelas_id > 0) {
    echo "→ Kelas ID > 0, mencari di database...\n";
    
    // Gunakan prepared statement
    $stmt = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $kelas_nama = $row['nama_kelas'];
        echo "✓ Kelas ditemukan: $kelas_nama\n";
        
        // Bersihkan untuk nama file
        $kelas_nama_untuk_file = trim($kelas_nama);
        $kelas_nama_untuk_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = preg_replace('/_+/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = trim($kelas_nama_untuk_file, '_');
        
        echo "Nama setelah dibersihkan: $kelas_nama_untuk_file\n";
        
        // Jika kosong, gunakan ID
        if (empty($kelas_nama_untuk_file)) {
            $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
            echo "→ Nama kosong, menggunakan: $kelas_nama_untuk_file\n";
        }
    } else {
        echo "✗ Kelas tidak ditemukan!\n";
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
        echo "Menggunakan fallback: $kelas_nama_untuk_file\n";
    }
    $stmt->close();
} else {
    echo "→ Kelas ID = 0 (Semua Kelas)\n";
}

// BUAT FILENAME
$filename = 'Template_Import_Siswa_' . date('Y-m-d');
echo "\nBase filename: $filename\n";

// TAMBAHKAN NAMA KELAS DI BELAKANG
if ($kelas_id > 0) {
    echo "→ Kelas ID > 0, menambahkan nama kelas...\n";
    
    // PASTIKAN tidak kosong
    if (empty($kelas_nama_untuk_file) || $kelas_nama_untuk_file == 'SemuaKelas') {
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
        echo "→ Nama masih kosong, menggunakan: $kelas_nama_untuk_file\n";
    }
    
    // PASTI DITAMBAHKAN
    $filename .= '_' . $kelas_nama_untuk_file;
    echo "✓ Nama kelas ditambahkan: $kelas_nama_untuk_file\n";
} else {
    $filename .= '_SemuaKelas';
    echo "→ Menambahkan: _SemuaKelas\n";
}

$filename .= '.xlsx';

echo "\n";
echo "========================================\n";
echo "FILENAME FINAL: $filename\n";
echo "========================================\n";

echo "</pre>";

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
echo "<p><a href='download_template.php?kelas_id=$kelas_id' target='_blank'>Download Template dengan kelas_id=$kelas_id</a></p>";
?>

