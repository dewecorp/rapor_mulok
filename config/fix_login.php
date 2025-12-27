<?php
/**
 * Script untuk memperbaiki masalah login
 * Akses melalui: http://localhost/rapor-mulok/config/fix_login.php
 * HAPUS FILE INI SETELAH SELESAI!
 */

require_once __DIR__ . '/database.php';

$conn = getConnection();
$success = '';
$error = '';

// Cek apakah tabel pengguna ada
$tables_check = $conn->query("SHOW TABLES LIKE 'pengguna'");
if ($tables_check->num_rows == 0) {
    $error = "Tabel pengguna belum ada. Silakan import database/schema.sql terlebih dahulu!";
} else {
    // Cek apakah ada user admin
    $admin_check = $conn->query("SELECT * FROM pengguna WHERE username = 'admin'");
    
    if ($admin_check->num_rows == 0) {
        // Buat user admin baru
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO pengguna (nama, username, password, role, is_proktor_utama) VALUES (?, ?, ?, ?, ?)");
        $nama = 'Administrator';
        $username = 'admin';
        $role = 'proktor';
        $is_proktor = 1;
        $stmt->bind_param("ssssi", $nama, $username, $password, $role, $is_proktor);
        
        if ($stmt->execute()) {
            $success = "User admin berhasil dibuat! Username: admin, Password: admin123";
        } else {
            $error = "Gagal membuat user admin: " . $conn->error;
        }
    } else {
        // Reset password admin
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE pengguna SET password = ? WHERE username = 'admin'");
        $stmt->bind_param("s", $password);
        
        if ($stmt->execute()) {
            $success = "Password admin berhasil direset! Username: admin, Password: admin123";
        } else {
            $error = "Gagal reset password: " . $conn->error;
        }
    }
    
    // Cek apakah tabel profil_madrasah ada dan ada datanya
    $profil_check = $conn->query("SELECT COUNT(*) as total FROM profil_madrasah");
    $profil_count = $profil_check->fetch_assoc()['total'];
    
    if ($profil_count == 0) {
        // Buat data profil default
        $conn->query("INSERT INTO profil_madrasah (nama_madrasah, tahun_ajaran_aktif, semester_aktif) VALUES ('MI Sultan Fattah Sukosono', '2024/2025', '1')");
        $success .= " | Data profil madrasah berhasil dibuat!";
    }
}

// Tampilkan semua user yang ada
$users = $conn->query("SELECT id, nama, username, role FROM pengguna");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Login - RMK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Fix Login - Rapor Mulok Khusus</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>Berhasil:</strong> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h5>Daftar Pengguna:</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <div class="mt-3">
                            <a href="../login.php" class="btn btn-primary">Coba Login</a>
                            <a href="fix_login.php" class="btn btn-secondary">Refresh</a>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <strong>PENTING:</strong> Hapus file ini setelah selesai untuk keamanan!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

