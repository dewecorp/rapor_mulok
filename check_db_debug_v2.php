<?php
require_once 'config/database.php';
$conn = getConnection();

echo "Table: kelas\n";
$res = $conn->query("DESCRIBE kelas");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\nData (ID and Nama): kelas\n";
$res = $conn->query("SELECT id, nama_kelas FROM kelas LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Nama: " . $row['nama_kelas'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
