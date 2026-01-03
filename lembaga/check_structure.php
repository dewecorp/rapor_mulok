<?php
/**
 * Script untuk cek struktur tabel materi_mulok
 * Akses: http://localhost/rapor-mulok/lembaga/check_structure.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Check Structure</title>";
echo "<style>body{font-family:Arial;padding:20px;} table{border-collapse:collapse;width:100%;margin:20px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#2d5016;color:white;}</style></head><body>";
echo "<h1>Struktur Tabel materi_mulok</h1>";

// Cek kolom yang ada
try {
    $result = $conn->query("SHOW COLUMNS FROM materi_mulok");
    echo "<h2>Kolom yang ada:</h2>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Cek index
try {
    $result = $conn->query("SHOW INDEX FROM materi_mulok");
    echo "<h2>Index yang ada:</h2>";
    echo "<table>";
    echo "<tr><th>Key_name</th><th>Column_name</th><th>Non_unique</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Column_name']) . "</td>";
        echo "<td>" . ($row['Non_unique'] ? 'Ya' : 'Tidak (Unique)') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Cek sample data
try {
    $result = $conn->query("SELECT * FROM materi_mulok LIMIT 5");
    echo "<h2>Sample Data (5 pertama):</h2>";
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        $first = true;
        while ($row = $result->fetch_assoc()) {
            if ($first) {
                echo "<tr>";
                foreach (array_keys($row) as $key) {
                    echo "<th>" . htmlspecialchars($key) . "</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Tidak ada data</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='materi.php'>Kembali ke Materi Mulok</a></p>";
echo "</body></html>";






