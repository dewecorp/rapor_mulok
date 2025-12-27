<?php
require_once '../config/database.php';

$conn = getConnection();

echo "<h2>Debug Import Siswa</h2>";

// Cek semua siswa
echo "<h3>Semua Siswa di Database:</h3>";
$query_all = "SELECT s.*, k.nama_kelas 
              FROM siswa s 
              LEFT JOIN kelas k ON s.kelas_id = k.id 
              ORDER BY s.id DESC 
              LIMIT 20";
$result_all = $conn->query($query_all);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>NISN</th><th>Nama</th><th>Kelas ID</th><th>Kelas Nama</th><th>Created At</th></tr>";

if ($result_all && $result_all->num_rows > 0) {
    while ($row = $result_all->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nisn']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td>" . ($row['kelas_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kelas'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at'] ?? '-') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='6'>Tidak ada data</td></tr>";
}

echo "</table>";

// Cek kelas
echo "<h3>Daftar Kelas:</h3>";
$query_kelas = "SELECT * FROM kelas ORDER BY id";
$result_kelas = $conn->query($query_kelas);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Nama Kelas</th><th>Jumlah Siswa</th></tr>";

if ($result_kelas && $result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        // Hitung jumlah siswa sebenarnya
        $count_query = "SELECT COUNT(*) as total FROM siswa WHERE kelas_id = " . $row['id'];
        $count_result = $conn->query($count_query);
        $count_row = $count_result->fetch_assoc();
        $actual_count = $count_row['total'];
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kelas']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jumlah_siswa']) . " (actual: $actual_count)</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>Tidak ada kelas</td></tr>";
}

echo "</table>";

// Test query untuk kelas tertentu
if (isset($_GET['kelas'])) {
    $kelas_id = intval($_GET['kelas']);
    echo "<h3>Test Query untuk Kelas ID: $kelas_id</h3>";
    
    $test_query = "SELECT s.*, k.nama_kelas
                   FROM siswa s
                   LEFT JOIN kelas k ON s.kelas_id = k.id
                   WHERE s.kelas_id = $kelas_id
                   ORDER BY s.nama";
    
    echo "<p>Query: <code>$test_query</code></p>";
    
    $test_result = $conn->query($test_query);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>NISN</th><th>Nama</th><th>Kelas ID</th><th>Kelas Nama</th></tr>";
    
    if ($test_result && $test_result->num_rows > 0) {
        echo "<tr><td colspan='5'><strong>Ditemukan " . $test_result->num_rows . " siswa</strong></td></tr>";
        while ($row = $test_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nisn']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
            echo "<td>" . ($row['kelas_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_kelas'] ?? '-') . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='5'>Tidak ada siswa untuk kelas ini</td></tr>";
    }
    
    echo "</table>";
}
?>

