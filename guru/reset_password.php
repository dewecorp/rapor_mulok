<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $password = password_hash('123456', PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $password, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

