<?php
// File test untuk melihat parameter yang diterima
echo "<h2>Test Download Template</h2>";
echo "<pre>";
echo "GET Parameters:\n";
print_r($_GET);
echo "\n\nkelas_id: " . (isset($_GET['kelas_id']) ? $_GET['kelas_id'] : 'tidak ada');
echo "\nkelas_id (int): " . (isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0);
echo "</pre>";

if (isset($_GET['kelas_id']) && (int)$_GET['kelas_id'] > 0) {
    require_once '../config/config.php';
    require_once '../config/database.php';
    $conn = getConnection();
    
    $kelas_id = (int)$_GET['kelas_id'];
    $stmt = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p>Kelas ditemukan: ID = {$row['id']}, Nama = {$row['nama_kelas']}</p>";
        
        // Test nama file
        $kelas_nama_file = trim($row['nama_kelas']);
        $kelas_nama_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas_nama_file);
        $kelas_nama_file = preg_replace('/_+/', '_', $kelas_nama_file);
        $kelas_nama_file = trim($kelas_nama_file, '_');
        
        $filename = 'Template_Import_Siswa_' . date('Y-m-d') . '_' . $kelas_nama_file . '.xlsx';
        echo "<p>Nama file yang akan dibuat: <strong>$filename</strong></p>";
    } else {
        echo "<p>Kelas tidak ditemukan!</p>";
    }
} else {
    echo "<p>Kelas ID tidak dipilih atau 0</p>";
    $filename = 'Template_Import_Siswa_' . date('Y-m-d') . '_Semua_Kelas.xlsx';
    echo "<p>Nama file yang akan dibuat: <strong>$filename</strong></p>";
}
?>

