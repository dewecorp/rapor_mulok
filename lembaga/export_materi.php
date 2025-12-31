<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';

// Cek kolom yang ada
$has_kategori_mulok = false;
$has_kode_mulok = false;
try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kategori_mulok') $has_kategori_mulok = true;
            if ($col['Field'] == 'kode_mulok') $has_kode_mulok = true;
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Tentukan kolom untuk ORDER BY
$order_by = $has_kategori_mulok ? 'kategori_mulok' : ($has_kode_mulok ? 'kode_mulok' : 'id');

// Ambil data
$query = "SELECT * FROM materi_mulok ORDER BY $order_by";
$result = $conn->query($query);

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="materi_mulok_' . date('Y-m-d') . '.xls"');
    
    $kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
    
    echo "<table border='1'>";
    echo "<tr><th>No</th><th>" . ($has_kategori_mulok ? 'Kategori' : 'Kode') . " Mulok</th><th>Nama Mulok</th>";
    if (isset($row['jumlah_jam'])) {
        echo "<th>Jumlah Jam</th>";
    }
    echo "</tr>";
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row[$kolom_kategori] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_mulok']) . "</td>";
        if (isset($row['jumlah_jam'])) {
            echo "<td>" . htmlspecialchars($row['jumlah_jam']) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    // PDF placeholder - bisa menggunakan library seperti TCPDF atau FPDF
    header('Content-Type: text/html');
    echo "<h2>Export PDF</h2>";
    echo "<p>Fitur export PDF memerlukan library tambahan seperti TCPDF atau FPDF.</p>";
    $kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
    
    echo "<p>Data yang akan diekspor:</p>";
    echo "<table border='1'>";
    echo "<tr><th>No</th><th>" . ($has_kategori_mulok ? 'Kategori' : 'Kode') . " Mulok</th><th>Nama Mulok</th>";
    if (isset($row['jumlah_jam'])) {
        echo "<th>Jumlah Jam</th>";
    }
    echo "</tr>";
    $no = 1;
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row[$kolom_kategori] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_mulok']) . "</td>";
        if (isset($row['jumlah_jam'])) {
            echo "<td>" . htmlspecialchars($row['jumlah_jam']) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>


