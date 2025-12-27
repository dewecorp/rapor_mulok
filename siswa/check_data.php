<?php
require_once '../config/database.php';

$conn = getConnection();

echo "<h2>Data Siswa di Database</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>NISN</th><th>Nama</th><th>Kelas ID</th><th>Kelas Nama</th></tr>";

$query = "SELECT s.*, k.nama_kelas 
          FROM siswa s 
          LEFT JOIN kelas k ON s.kelas_id = k.id 
          ORDER BY s.id DESC 
          LIMIT 20";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nisn']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td>" . ($row['kelas_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kelas'] ?? '-') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>Tidak ada data</td></tr>";
}

echo "</table>";

echo "<h2>Daftar Kelas</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nama Kelas</th><th>Jumlah Siswa</th></tr>";

$query_kelas = "SELECT * FROM kelas ORDER BY id";
$result_kelas = $conn->query($query_kelas);

if ($result_kelas && $result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kelas']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jumlah_siswa']) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>Tidak ada kelas</td></tr>";
}

echo "</table>";
?>

