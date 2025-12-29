<?php
/**
 * Script untuk menghapus unique constraint dari materi_mulok
 * Akses: http://localhost/rapor-mulok/lembaga/fix_constraint.php
 * Setelah selesai, HAPUS file ini untuk keamanan!
 */

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$results = [];

// Fungsi untuk menjalankan query
function runQuery($conn, $query, $description) {
    global $results;
    
    try {
        if ($conn->query($query)) {
            $results[] = ['type' => 'success', 'message' => "✓ $description"];
            return true;
        } else {
            $error = $conn->error;
            // Abaikan error jika index tidak ada
            if (strpos($error, "doesn't exist") !== false || strpos($error, "Unknown key") !== false) {
                $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah tidak ada)"];
                return true;
            }
            $results[] = ['type' => 'error', 'message' => "✗ $description: $error"];
            return false;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Abaikan error jika index tidak ada
        if (strpos($error, "doesn't exist") !== false || strpos($error, "Unknown key") !== false) {
            $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah tidak ada)"];
            return true;
        }
        $results[] = ['type' => 'error', 'message' => "✗ $description: $error"];
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
    $results[] = ['type' => 'error', 'message' => "Error mengecek kolom: " . $e->getMessage()];
}

// Hapus unique constraint dari kode_mulok
if ($has_kode_mulok) {
    // Cek semua index yang ada
    $indexes_to_drop = [];
    try {
        $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name = 'kode_mulok' AND Non_unique = 0");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Key_name'] != 'PRIMARY') {
                    $indexes_to_drop[] = $row['Key_name'];
                }
            }
        }
    } catch (Exception $e) {
        // Continue
    }
    
    // Hapus semua unique index
    foreach ($indexes_to_drop as $index_name) {
        runQuery($conn, "DROP INDEX `$index_name` ON `materi_mulok`", "Hapus unique index '$index_name' dari kode_mulok");
    }
    
    // Jika tidak ada index yang ditemukan, coba hapus dengan nama umum
    if (empty($indexes_to_drop)) {
        runQuery($conn, "DROP INDEX IF EXISTS `kode_mulok` ON `materi_mulok`", "Hapus index 'kode_mulok'");
        runQuery($conn, "DROP INDEX IF EXISTS `uk_kode_mulok` ON `materi_mulok`", "Hapus index 'uk_kode_mulok'");
        runQuery($conn, "DROP INDEX IF EXISTS `unique_kode_mulok` ON `materi_mulok`", "Hapus index 'unique_kode_mulok'");
        runQuery($conn, "DROP INDEX IF EXISTS `idx_kode_mulok` ON `materi_mulok`", "Hapus index 'idx_kode_mulok'");
    }
    
    // Ubah kolom menjadi nullable (menghapus NOT NULL jika ada)
    runQuery($conn, "ALTER TABLE `materi_mulok` MODIFY COLUMN `kode_mulok` VARCHAR(255) NULL", "Ubah kode_mulok menjadi nullable");
}

// Hapus unique constraint dari kategori_mulok
if ($has_kategori_mulok) {
    // Cek semua index yang ada
    $indexes_to_drop = [];
    try {
        $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name = 'kategori_mulok' AND Non_unique = 0");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Key_name'] != 'PRIMARY') {
                    $indexes_to_drop[] = $row['Key_name'];
                }
            }
        }
    } catch (Exception $e) {
        // Continue
    }
    
    // Hapus semua unique index
    foreach ($indexes_to_drop as $index_name) {
        runQuery($conn, "DROP INDEX `$index_name` ON `materi_mulok`", "Hapus unique index '$index_name' dari kategori_mulok");
    }
    
    // Jika tidak ada index yang ditemukan, coba hapus dengan nama umum
    if (empty($indexes_to_drop)) {
        runQuery($conn, "DROP INDEX IF EXISTS `kategori_mulok` ON `materi_mulok`", "Hapus index 'kategori_mulok'");
        runQuery($conn, "DROP INDEX IF EXISTS `uk_kategori_mulok` ON `materi_mulok`", "Hapus index 'uk_kategori_mulok'");
        runQuery($conn, "DROP INDEX IF EXISTS `unique_kategori_mulok` ON `materi_mulok`", "Hapus index 'unique_kategori_mulok'");
        runQuery($conn, "DROP INDEX IF EXISTS `idx_kategori_mulok` ON `materi_mulok`", "Hapus index 'idx_kategori_mulok'");
    }
    
    // Ubah kolom menjadi nullable
    runQuery($conn, "ALTER TABLE `materi_mulok` MODIFY COLUMN `kategori_mulok` VARCHAR(255) NULL", "Ubah kategori_mulok menjadi nullable");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Unique Constraint</title>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #2d5016;
            margin-bottom: 20px;
            border-bottom: 3px solid #2d5016;
            padding-bottom: 10px;
        }
        .result {
            padding: 12px 15px;
            border-radius: 5px;
            margin: 8px 0;
            font-size: 14px;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .result.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .info-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box strong {
            color: #856404;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2d5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #1e350e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Unique Constraint - Materi Mulok</h1>
        
        <?php if (count($results) > 0): ?>
            <h2>Hasil:</h2>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo $result['type']; ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>ℹ Info:</strong><br>
            - Kolom kode_mulok: <?php echo $has_kode_mulok ? '✓ Ada' : '✗ Tidak ada'; ?><br>
            - Kolom kategori_mulok: <?php echo $has_kategori_mulok ? '✓ Ada' : '✗ Tidak ada'; ?>
        </div>
        
        <?php 
        $has_errors = false;
        foreach ($results as $result) {
            if ($result['type'] === 'error') {
                $has_errors = true;
                break;
            }
        }
        ?>
        
        <?php if (!$has_errors && count($results) > 0): ?>
            <div class="info-box" style="background: #d4edda; border-color: #28a745;">
                <strong>✅ Selesai!</strong><br>
                Unique constraint sudah dihapus. Sekarang Anda bisa menambahkan/edit kategori yang sama tanpa error duplicate entry.<br><br>
                <strong style="color: #dc3545;">⚠ PENTING: Hapus file ini (fix_constraint.php) untuk keamanan!</strong>
            </div>
            <a href="materi.php" class="btn">Kembali ke Materi Mulok</a>
        <?php endif; ?>
    </div>
</body>
</html>

