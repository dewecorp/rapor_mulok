<?php
require_once 'config/config.php';
require_once 'config/database.php';

$conn = getConnection();

// Cek tahun ajaran aktif
echo "<h3>Tahun Ajaran Aktif:</h3>";
$tahun_ajaran_aktif = '';
$result = $conn->query("SELECT tahun_ajaran_aktif FROM profil_madrasah LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $tahun_ajaran_aktif = $row['tahun_ajaran_aktif'];
    echo "<p><strong>$tahun_ajaran_aktif</strong></p>";
}

// Cek data siswa per tahun ajaran
echo "<h3>Data Siswa per Tahun Ajaran:</h3>";
$query = "SELECT tahun_ajaran, COUNT(*) as jumlah FROM siswa GROUP BY tahun_ajaran";
$result = $conn->query($query);
if ($result) {
    echo "<table border='1' cellpadding='10' cellspacing='0'>";
    echo "<tr><th>Tahun Ajaran</th><th>Jumlah Siswa</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['tahun_ajaran'] ?? 'NULL') . "</td><td>" . $row['jumlah'] . "</td></tr>";
    }
    echo "</table>";
}

// Cek 10 siswa pertama
echo "<h3>Contoh Data Siswa:</h3>";
$query = "SELECT s.id, s.nisn, s.nama, k.nama_kelas, s.tahun_ajaran FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id LIMIT 10";
$result = $conn->query($query);
if ($result) {
    echo "<table border='1' cellpadding='10' cellspacing='0'>";
    echo "<tr><th>ID</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Tahun Ajaran</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['nisn']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_kelas'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['tahun_ajaran'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>