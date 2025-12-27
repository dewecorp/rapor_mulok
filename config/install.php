<?php
/**
 * File Installer - Hapus file ini setelah instalasi selesai
 * Akses melalui: http://localhost/rapor-mulok/config/install.php
 */

// Cek apakah sudah terinstall
if (file_exists(__DIR__ . '/installed.lock')) {
    die('Aplikasi sudah terinstall. Hapus file install.php jika ingin menginstall ulang.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 1) {
        // Step 1: Cek koneksi database
        $host = $_POST['db_host'] ?? 'localhost';
        $user = $_POST['db_user'] ?? 'root';
        $pass = $_POST['db_pass'] ?? '';
        $name = $_POST['db_name'] ?? 'rapor_mulok';
        
        $conn = @new mysqli($host, $user, $pass);
        
        if ($conn->connect_error) {
            $error = 'Koneksi database gagal: ' . $conn->connect_error;
        } else {
            // Buat database jika belum ada
            $conn->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db($name);
            
            // Import schema
            $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
            $queries = explode(';', $schema);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $conn->query($query);
                }
            }
            
            // Update config
            $config_content = file_get_contents(__DIR__ . '/database.php');
            $config_content = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', '$host');", $config_content);
            $config_content = str_replace("define('DB_USER', 'root');", "define('DB_USER', '$user');", $config_content);
            $config_content = str_replace("define('DB_PASS', '');", "define('DB_PASS', '$pass');", $config_content);
            $config_content = str_replace("define('DB_NAME', 'rapor_mulok');", "define('DB_NAME', '$name');", $config_content);
            file_put_contents(__DIR__ . '/database.php', $config_content);
            
            // Buat lock file
            file_put_contents(__DIR__ . '/installed.lock', date('Y-m-d H:i:s'));
            
            $success = 'Instalasi berhasil! Silakan login dengan username: admin, password: admin123';
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Installer - RMK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Installer Rapor Mulok Khusus</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <a href="../login.php" class="btn btn-primary">Login Sekarang</a>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database User</label>
                                    <input type="text" class="form-control" name="db_user" value="root" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database Password</label>
                                    <input type="password" class="form-control" name="db_pass">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" class="form-control" name="db_name" value="rapor_mulok" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Install</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

