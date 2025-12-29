<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
$label_kategori = $use_kategori ? 'Kategori Mulok' : 'Kode Mulok';

// Ambil data (case-insensitive sorting)
$query = "SELECT * FROM materi_mulok ORDER BY LOWER($kolom_kategori) ASC, LOWER(nama_mulok) ASC";
$result = $conn->query($query);

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="materi_mulok_' . date('Y-m-d') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>No</th><th>$label_kategori</th><th>Nama Mulok</th><th>Jumlah Jam</th></tr>";
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row[$kolom_kategori] ?? '') . "</td>";
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
    echo "<tr><th>No</th><th>$label_kategori</th><th>Nama Mulok</th><th>Jumlah Jam</th></tr>";
    $no = 1;
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row[$kolom_kategori] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_mulok']) . "</td>";
        echo "<td>" . htmlspecialchars($row['jumlah_jam']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>


