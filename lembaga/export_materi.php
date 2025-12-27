<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';

// Ambil data
$query = "SELECT * FROM materi_mulok ORDER BY kode_mulok";
$result = $conn->query($query);

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="materi_mulok_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>No</th><th>Kode Mulok</th><th>Nama Mulok</th><th>Jumlah Jam</th></tr>";
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['kode_mulok']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_mulok']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jumlah_jam']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    // PDF placeholder - bisa menggunakan library seperti TCPDF atau FPDF
    header('Content-Type: text/html');
    echo "<h2>Export PDF</h2>";
    echo "<p>Fitur export PDF memerlukan library tambahan seperti TCPDF atau FPDF.</p>";
    echo "<p>Data yang akan diekspor:</p>";
    echo "<table border='1'>";
    echo "<tr><th>No</th><th>Kode Mulok</th><th>Nama Mulok</th><th>Jumlah Jam</th></tr>";
    $no = 1;
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['kode_mulok']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_mulok']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jumlah_jam']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>

