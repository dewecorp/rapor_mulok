<?php
require_once 'config/database.php';
$conn = getConnection();

echo "Table: kelas\n";
$res = $conn->query("DESCRIBE kelas");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\nData: kelas\n";
$res = $conn->query("SELECT * FROM kelas LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
