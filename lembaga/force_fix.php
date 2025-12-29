<?php
/**
 * Script AGRESIF untuk menghapus unique constraint
 * Akses: http://localhost/rapor-mulok/lembaga/force_fix.php
 * Script ini akan menghapus constraint dengan berbagai metode
 * Setelah selesai, HAPUS file ini untuk keamanan!
 */

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$results = [];

// Fungsi untuk menjalankan query (tidak peduli error)
function forceRunQuery($conn, $query, $description) {
    global $results;
    
    try {
        if ($conn->query($query)) {
            $results[] = ['type' => 'success', 'message' => "✓ $description"];
            return true;
        } else {
            $error = $conn->error;
            $error_code = $conn->errno;
            // Abaikan error jika index tidak ada (error code 1091 atau 42000)
            if ($error_code == 1091 || strpos($error, "doesn't exist") !== false || strpos($error, "Unknown key") !== false) {
                $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah tidak ada)"];
                return true; // Anggap sukses karena index memang tidak ada
            }
            // Abaikan error SQL syntax untuk IF EXISTS (MySQL versi lama)
            if (strpos($error, "SQL syntax") !== false && strpos($query, "IF EXISTS") !== false) {
                $results[] = ['type' => 'info', 'message' => "ℹ $description (MySQL versi lama tidak support IF EXISTS - akan dicoba metode lain)"];
                return false;
            }
            $results[] = ['type' => 'info', 'message' => "ℹ $description (dilewati: " . substr($error, 0, 50) . "...)"];
            return false;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Abaikan error jika index tidak ada
        if (strpos($error, "doesn't exist") !== false || strpos($error, "Unknown key") !== false) {
            $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah tidak ada)"];
            return true;
        }
        $results[] = ['type' => 'info', 'message' => "ℹ $description (dilewati: " . substr($error, 0, 50) . "...)"];
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

// METODE 1: Hapus semua index dengan DROP INDEX (tanpa IF EXISTS untuk kompatibilitas)
$index_names = ['kode_mulok', 'uk_kode_mulok', 'unique_kode_mulok', 'idx_kode_mulok', 
                'kategori_mulok', 'uk_kategori_mulok', 'unique_kategori_mulok', 'idx_kategori_mulok'];

foreach ($index_names as $index_name) {
    // Cek dulu apakah index ada sebelum drop
    try {
        $check_result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Key_name = '$index_name'");
        if ($check_result && $check_result->num_rows > 0) {
            forceRunQuery($conn, "DROP INDEX `$index_name` ON `materi_mulok`", "Hapus index '$index_name'");
        } else {
            $results[] = ['type' => 'info', 'message' => "ℹ Index '$index_name' tidak ada (dilewati)"];
        }
    } catch (Exception $e) {
        // Coba drop langsung (untuk MySQL versi baru)
        forceRunQuery($conn, "DROP INDEX `$index_name` ON `materi_mulok`", "Hapus index '$index_name'");
    }
}

// METODE 2: Cari dan hapus semua unique index yang ada
if ($has_kode_mulok) {
    try {
        $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name = 'kode_mulok' AND Non_unique = 0");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Key_name'] != 'PRIMARY') {
                    forceRunQuery($conn, "DROP INDEX `{$row['Key_name']}` ON `materi_mulok`", "Hapus unique index '{$row['Key_name']}' dari kode_mulok");
                }
            }
        }
    } catch (Exception $e) {
        // Continue
    }
}

if ($has_kategori_mulok) {
    try {
        $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name = 'kategori_mulok' AND Non_unique = 0");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Key_name'] != 'PRIMARY') {
                    forceRunQuery($conn, "DROP INDEX `{$row['Key_name']}` ON `materi_mulok`", "Hapus unique index '{$row['Key_name']}' dari kategori_mulok");
                }
            }
        }
    } catch (Exception $e) {
        // Continue
    }
}

// METODE 3: Ubah kolom menjadi nullable (menghapus NOT NULL dan unique)
if ($has_kode_mulok) {
    forceRunQuery($conn, "ALTER TABLE `materi_mulok` MODIFY COLUMN `kode_mulok` VARCHAR(255) NULL DEFAULT NULL", "Ubah kode_mulok menjadi nullable");
}

if ($has_kategori_mulok) {
    forceRunQuery($conn, "ALTER TABLE `materi_mulok` MODIFY COLUMN `kategori_mulok` VARCHAR(255) NULL DEFAULT NULL", "Ubah kategori_mulok menjadi nullable");
}

// METODE 4: Cek apakah masih ada unique constraint
$remaining_constraints = [];
try {
    $result = $conn->query("SHOW INDEX FROM materi_mulok WHERE Column_name IN ('kode_mulok', 'kategori_mulok') AND Non_unique = 0");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['Key_name'] != 'PRIMARY') {
                $remaining_constraints[] = $row['Key_name'];
            }
        }
    }
} catch (Exception $e) {
    // Continue
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Fix Unique Constraint</title>
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
            max-width: 900px;
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
            font-family: 'Courier New', monospace;
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
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2d5016;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            background: #1e350e;
        }
        .code {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 10px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Force Fix Unique Constraint - Materi Mulok</h1>
        
        <?php if (count($results) > 0): ?>
            <h2>Hasil Eksekusi:</h2>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo $result['type']; ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="warning-box">
            <strong>ℹ Info:</strong><br>
            - Kolom kode_mulok: <?php echo $has_kode_mulok ? '✓ Ada' : '✗ Tidak ada'; ?><br>
            - Kolom kategori_mulok: <?php echo $has_kategori_mulok ? '✓ Ada' : '✗ Tidak ada'; ?><br>
            <?php if (count($remaining_constraints) > 0): ?>
                <br><strong style="color: #dc3545;">⚠ Masih ada constraint yang tersisa:</strong><br>
                <?php foreach ($remaining_constraints as $constraint): ?>
                    - <?php echo htmlspecialchars($constraint); ?><br>
                <?php endforeach; ?>
                <br>
                <strong>Jalankan query ini di phpMyAdmin untuk menghapusnya:</strong>
                <div class="code">
                    <?php foreach ($remaining_constraints as $constraint): ?>
                        DROP INDEX `<?php echo htmlspecialchars($constraint); ?>` ON `materi_mulok`;<br>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <br><strong style="color: #28a745;">✓ Tidak ada unique constraint yang tersisa!</strong>
            <?php endif; ?>
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
        
        <?php if (count($remaining_constraints) == 0): ?>
            <div class="success-box">
                <strong>✅ BERHASIL!</strong><br>
                Semua unique constraint sudah dihapus. Sekarang Anda bisa menambahkan/edit kategori yang sama tanpa error duplicate entry.<br><br>
                <strong style="color: #dc3545;">⚠ PENTING: Hapus file ini (force_fix.php) untuk keamanan!</strong>
            </div>
            <a href="materi.php" class="btn">Kembali ke Materi Mulok</a>
        <?php else: ?>
            <div class="warning-box" style="background: #f8d7da; border-color: #dc3545;">
                <strong>⚠ Masih ada constraint yang perlu dihapus manual!</strong><br>
                Lihat query di atas dan jalankan di phpMyAdmin.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

