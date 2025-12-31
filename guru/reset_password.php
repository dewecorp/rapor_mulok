<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $password_plain = '123456'; // Simpan password plain text untuk admin
    
    // Cek dan tambahkan kolom password_plain jika belum ada
    $check_password_plain = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'password_plain'");
    if ($check_password_plain->num_rows == 0) {
        $conn->query("ALTER TABLE pengguna ADD COLUMN password_plain VARCHAR(255) NULL AFTER password");
    }
    
    $stmt = $conn->prepare("UPDATE pengguna SET password = ?, password_plain = ? WHERE id = ?");
    $stmt->bind_param("ssi", $password, $password_plain, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>


