<?php
/**
 * Script Migrasi: Ubah kolom jumlah_jam menjadi kelas_id di tabel materi_mulok
 * Akses: http://localhost/rapor-mulok/lembaga/migrate_jumlah_jam_to_kelas_id.php
 * 
 * Script ini akan:
 * 1. Menambahkan kolom kelas_id jika belum ada
 * 2. Menambahkan foreign key constraint ke tabel kelas
 * 3. (Opsional) Menghapus kolom jumlah_jam setelah semua aplikasi di-update
 * 
 * Setelah selesai, HAPUS file ini untuk keamanan!
 */

// Enable error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
if (ob_get_level() == 0) {
    ob_start();
}

// Start session jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    
    // Script migrasi database - bisa diakses tanpa login
    // HAPUS file ini setelah migrasi selesai untuk keamanan!
    
    // Token keamanan sederhana (opsional, bisa dihapus jika tidak diperlukan)
    $migration_token = 'migrate_2024_jumlah_jam_to_kelas_id';
    $token_provided = isset($_GET['token']) && $_GET['token'] === $migration_token;
    
    // Untuk keamanan, bisa menggunakan token atau langsung akses
    // Jika ingin lebih aman, uncomment baris di bawah dan gunakan token
    // if (!$token_provided) {
    //     die('Error: Token migrasi diperlukan. Akses dengan: ?token=migrate_2024_jumlah_jam_to_kelas_id');
    // }
    
    $conn = getConnection();
    if (!$conn) {
        die('Error: Tidak dapat terhubung ke database. Pastikan konfigurasi database benar.');
    }
    
    $results = [];
} catch (Exception $e) {
    // Jika ada error, tampilkan error message
    ob_end_clean();
    die('Error: ' . htmlspecialchars($e->getMessage()) . '<br>File: ' . htmlspecialchars($e->getFile()) . '<br>Line: ' . $e->getLine());
}

// Fungsi untuk menjalankan query
function executeMigration($conn, $query, $description) {
    global $results;
    
    try {
        if ($conn->query($query)) {
            $results[] = ['type' => 'success', 'message' => "✓ $description"];
            return true;
        } else {
            $error = $conn->error;
            $error_code = $conn->errno;
            // Abaikan error jika kolom sudah ada (error code 1060)
            if ($error_code == 1060 || strpos($error, "Duplicate column name") !== false) {
                $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah ada)"];
                return true;
            }
            // Abaikan error jika constraint sudah ada (error code 1061 atau 1062)
            if ($error_code == 1061 || $error_code == 1062 || strpos($error, "Duplicate key name") !== false) {
                $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah ada)"];
                return true;
            }
            $results[] = ['type' => 'error', 'message' => "✗ $description - Error: " . substr($error, 0, 100)];
            return false;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Abaikan error jika kolom/constraint sudah ada
        if (strpos($error, "Duplicate column name") !== false || 
            strpos($error, "Duplicate key name") !== false ||
            strpos($error, "already exists") !== false) {
            $results[] = ['type' => 'info', 'message' => "ℹ $description (sudah ada)"];
            return true;
        }
        $results[] = ['type' => 'error', 'message' => "✗ $description - Error: " . substr($error, 0, 100)];
        return false;
    }
}

// Cek apakah kolom kelas_id sudah ada
$has_kelas_id = false;
$has_jumlah_jam = false;

try {
    $result = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
    $has_kelas_id = ($result && $result->num_rows > 0);
    
    $result = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'jumlah_jam'");
    $has_jumlah_jam = ($result && $result->num_rows > 0);
} catch (Exception $e) {
    $results[] = ['type' => 'error', 'message' => "Error mengecek kolom: " . $e->getMessage()];
}

// Jalankan migrasi hanya jika diminta atau jika kelas_id belum ada
$run_migration = isset($_GET['run_migration']) && $_GET['run_migration'] == '1';

// Step 1: Tambahkan kolom kelas_id jika belum ada
if (!$has_kelas_id || $run_migration) {
    if (!$has_kelas_id) {
        executeMigration($conn, "ALTER TABLE `materi_mulok` ADD COLUMN `kelas_id` INT(11) NULL DEFAULT NULL AFTER `nama_mulok`", 
                         "Tambahkan kolom kelas_id");
        // Refresh status setelah menambahkan kolom
        $result = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
        $has_kelas_id = ($result && $result->num_rows > 0);
    } else {
        $results[] = ['type' => 'info', 'message' => "ℹ Kolom kelas_id sudah ada"];
    }
}

// Step 2: Cek apakah foreign key constraint sudah ada
$has_fk = false;
try {
    $result = $conn->query("SELECT CONSTRAINT_NAME 
                            FROM information_schema.KEY_COLUMN_USAGE 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'materi_mulok' 
                            AND CONSTRAINT_NAME = 'fk_materi_mulok_kelas'");
    $has_fk = ($result && $result->num_rows > 0);
} catch (Exception $e) {
    // Ignore
}

// Step 3: Tambahkan foreign key constraint jika belum ada
if ((!$has_fk && $has_kelas_id) || ($run_migration && $has_kelas_id && !$has_fk)) {
    // Cek apakah tabel kelas ada
    $kelas_exists = false;
    try {
        $result = $conn->query("SHOW TABLES LIKE 'kelas'");
        $kelas_exists = ($result && $result->num_rows > 0);
    } catch (Exception $e) {
        // Ignore
    }
    
    if ($kelas_exists) {
        executeMigration($conn, "ALTER TABLE `materi_mulok` 
                                  ADD CONSTRAINT `fk_materi_mulok_kelas` 
                                  FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) 
                                  ON DELETE SET NULL ON UPDATE CASCADE", 
                         "Tambahkan foreign key constraint ke tabel kelas");
        // Refresh status setelah menambahkan constraint
        try {
            $result = $conn->query("SELECT CONSTRAINT_NAME 
                                    FROM information_schema.KEY_COLUMN_USAGE 
                                    WHERE TABLE_SCHEMA = DATABASE() 
                                    AND TABLE_NAME = 'materi_mulok' 
                                    AND CONSTRAINT_NAME = 'fk_materi_mulok_kelas'");
            $has_fk = ($result && $result->num_rows > 0);
        } catch (Exception $e) {
            // Ignore
        }
    } else {
        $results[] = ['type' => 'error', 'message' => "✗ Tabel kelas tidak ditemukan. Pastikan tabel kelas sudah dibuat terlebih dahulu."];
    }
} else if ($has_fk) {
    $results[] = ['type' => 'info', 'message' => "ℹ Foreign key constraint sudah ada"];
}

// Step 4: (Opsional) Hapus kolom jumlah_jam jika diminta
$drop_jumlah_jam = isset($_GET['drop_jumlah_jam']) && $_GET['drop_jumlah_jam'] == '1';

if ($drop_jumlah_jam && $has_jumlah_jam) {
    executeMigration($conn, "ALTER TABLE `materi_mulok` DROP COLUMN `jumlah_jam`", 
                     "Hapus kolom jumlah_jam");
} else if ($has_jumlah_jam && !$drop_jumlah_jam) {
    $results[] = ['type' => 'warning', 'message' => "⚠ Kolom jumlah_jam masih ada. Hapus dengan menambahkan parameter ?drop_jumlah_jam=1 di URL (setelah memastikan semua aplikasi sudah di-update)"];
}

// Pastikan output buffer bersih sebelum HTML
while (ob_get_level() > 0) {
    ob_end_clean();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Migrasi: jumlah_jam ke kelas_id</title>
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
        .result.warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .info-box {
            background: #e7f3ff;
            border: 2px solid #2196F3;
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
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Migrasi: jumlah_jam → kelas_id</h1>
        
        <?php 
        // Debug: Pastikan variabel sudah terdefinisi
        if (!isset($has_kelas_id)) $has_kelas_id = false;
        if (!isset($has_jumlah_jam)) $has_jumlah_jam = false;
        if (!isset($has_fk)) $has_fk = false;
        if (!isset($results)) $results = [];
        ?>
        
        <?php if (count($results) > 0): ?>
            <h2>Hasil Migrasi:</h2>
            <?php foreach ($results as $result): ?>
                <div class="result <?php echo htmlspecialchars($result['type']); ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="info-box">
                <strong>ℹ Informasi:</strong><br>
                Belum ada migrasi yang dijalankan. Script akan mengeksekusi migrasi saat halaman dimuat.
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>ℹ Status Kolom:</strong><br>
            - Kolom kelas_id: <?php echo $has_kelas_id ? '✓ Ada' : '✗ Tidak ada'; ?><br>
            - Kolom jumlah_jam: <?php echo $has_jumlah_jam ? '⚠ Masih ada' : '✓ Sudah dihapus'; ?><br>
            - Foreign key constraint: <?php echo $has_fk ? '✓ Ada' : '✗ Tidak ada'; ?><br>
        </div>
        
        <?php if ($has_kelas_id && $has_fk): ?>
            <div class="success-box">
                <strong>✅ Migrasi Berhasil!</strong><br>
                Kolom kelas_id sudah ditambahkan dan foreign key constraint sudah dibuat.<br><br>
                <?php if ($has_jumlah_jam): ?>
                    <strong>⚠ PERINGATAN:</strong> Kolom jumlah_jam masih ada. Hapus kolom ini hanya setelah memastikan:<br>
                    - Semua file PHP sudah di-update untuk menggunakan kelas_id<br>
                    - Semua data sudah di-migrate ke kelas_id<br>
                    - Tidak ada aplikasi lain yang masih menggunakan jumlah_jam<br><br>
                    <a href="?drop_jumlah_jam=1" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus kolom jumlah_jam? Pastikan semua aplikasi sudah di-update!')">Hapus Kolom jumlah_jam</a>
                <?php else: ?>
                    Kolom jumlah_jam sudah dihapus. Migrasi selesai!<br><br>
                    <strong style="color: #dc3545;">⚠ PENTING: Hapus file ini (migrate_jumlah_jam_to_kelas_id.php) untuk keamanan!</strong>
                <?php endif; ?>
            </div>
            <a href="materi.php" class="btn">Kembali ke Materi Mulok</a>
        <?php else: ?>
            <div class="info-box">
                <strong>ℹ Informasi:</strong><br>
                <?php if (!$has_kelas_id): ?>
                    Kolom kelas_id belum ada. Klik tombol di bawah untuk menjalankan migrasi.<br><br>
                    <a href="?run_migration=1" class="btn">Jalankan Migrasi Sekarang</a>
                <?php elseif (!$has_fk): ?>
                    Kolom kelas_id sudah ada, tetapi foreign key constraint belum dibuat. Klik tombol di bawah untuk menambahkan constraint.<br><br>
                    <a href="?run_migration=1" class="btn">Tambahkan Foreign Key Constraint</a>
                <?php else: ?>
                    Migrasi sedang diproses. Silakan refresh halaman ini.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

