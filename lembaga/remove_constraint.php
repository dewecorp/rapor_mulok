<?php
/**
 * Script untuk menghapus unique constraint dari materi_mulok
 * Jalankan file ini sekali saja melalui browser
 * Setelah selesai, hapus file ini untuk keamanan
 */

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success_messages = [];
$error_messages = [];

// Fungsi untuk menjalankan query
function executeQuery($conn, $query, $description) {
    global $success_messages, $error_messages;
    
    try {
        if ($conn->query($query)) {
            $success_messages[] = "✓ $description berhasil";
            return true;
        } else {
            $error_messages[] = "✗ $description gagal: " . $conn->error;
            return false;
        }
    } catch (Exception $e) {
        $error_messages[] = "✗ $description error: " . $e->getMessage();
        return false;
    }
}

// Cek kolom yang ada
$has_kode_mulok = false;
$has_kategori_mulok = false;

try {
    $result = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kode_mulok'");
    $has_kode_mulok = ($result && $result->num_rows > 0);
    
    $result = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $has_kategori_mulok = ($result && $result->num_rows > 0);
} catch (Exception $e) {
    $error_messages[] = "Error mengecek kolom: " . $e->getMessage();
}

// Hapus unique constraint dari kode_mulok
if ($has_kode_mulok) {
    // Cek index yang ada
    $indexes = [];
    try {
        $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name = 'kode_mulok'");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Non_unique'] == 0) { // Unique index
                    $indexes[] = $row['Key_name'];
                }
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    // Hapus semua unique index
    foreach ($indexes as $index_name) {
        if ($index_name != 'PRIMARY') {
            executeQuery($conn, "DROP INDEX `$index_name` ON `materi_mulok`", "Hapus index $index_name");
        }
    }
    
    // Ubah kolom menjadi nullable
    executeQuery($conn, "ALTER TABLE `materi_mulok` MODIFY COLUMN `kode_mulok` VARCHAR(255) NULL", "Ubah kode_mulok menjadi nullable");
}

// Hapus unique constraint dari kategori_mulok
if ($has_kategori_mulok) {
    // Cek index yang ada
    $indexes = [];
    try {
        $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name = 'kategori_mulok'");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Non_unique'] == 0) { // Unique index
                    $indexes[] = $row['Key_name'];
                }
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    // Hapus semua unique index
    foreach ($indexes as $index_name) {
        if ($index_name != 'PRIMARY') {
            executeQuery($conn, "DROP INDEX `$index_name` ON `materi_mulok`", "Hapus index $index_name");
        }
    }
    
    // Ubah kolom menjadi nullable
    executeQuery($conn, "ALTER TABLE `materi_mulok` MODIFY COLUMN `kategori_mulok` VARCHAR(255) NULL", "Ubah kategori_mulok menjadi nullable");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Hapus Unique Constraint</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Hapus Unique Constraint dari Materi Mulok</h1>
    
    <?php if (count($success_messages) > 0): ?>
        <div class="success">
            <h3>Berhasil:</h3>
            <ul>
                <?php foreach ($success_messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (count($error_messages) > 0): ?>
        <div class="error">
            <h3>Error:</h3>
            <ul>
                <?php foreach ($error_messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (count($success_messages) > 0 && count($error_messages) == 0): ?>
        <div class="info">
            <strong>✓ Selesai!</strong><br>
            Unique constraint sudah dihapus. Sekarang Anda bisa menambahkan kategori yang sama tanpa error.<br>
            <strong>PENTING: Hapus file ini (remove_constraint.php) untuk keamanan!</strong>
        </div>
    <?php endif; ?>
    
    <div class="info">
        <strong>Info:</strong><br>
        - Kolom kode_mulok: <?php echo $has_kode_mulok ? 'Ada' : 'Tidak ada'; ?><br>
        - Kolom kategori_mulok: <?php echo $has_kategori_mulok ? 'Ada' : 'Tidak ada'; ?>
    </div>
</body>
</html>










