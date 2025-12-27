<?php
/**
 * Reset Admin Password
 * Akses: http://localhost/rapor-mulok/reset_admin.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$conn = getConnection();
$success = false;
$message = '';
$admin_info = null;

// Cek user admin
$admin = $conn->query("SELECT * FROM pengguna WHERE username = 'admin'");
if ($admin->num_rows > 0) {
    $admin_info = $admin->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? 'admin123';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    if ($admin_info) {
        // Update password
        $stmt = $conn->prepare("UPDATE pengguna SET password = ? WHERE username = 'admin'");
        $stmt->bind_param("s", $password_hash);
        
        if ($stmt->execute()) {
            $success = true;
            $message = "Password admin berhasil direset menjadi: <strong>" . htmlspecialchars($password) . "</strong>";
            
            // Test password
            if (password_verify($password, $password_hash)) {
                $message .= "<br>✓ Password hash sudah benar dan bisa digunakan untuk login.";
            }
        } else {
            $message = "Error: " . $conn->error;
        }
    } else {
        // Buat user admin jika belum ada
        $stmt = $conn->prepare("INSERT INTO pengguna (nama, username, password, role, is_proktor_utama) VALUES ('Administrator', 'admin', ?, 'proktor', 1)");
        $stmt->bind_param("s", $password_hash);
        
        if ($stmt->execute()) {
            $success = true;
            $message = "User admin berhasil dibuat dengan password: <strong>" . htmlspecialchars($password) . "</strong>";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Admin Password</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        input, button { padding: 10px; margin: 5px 0; width: 100%; }
        button { background: #2d5016; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Reset Admin Password</h1>
    
    <?php if ($admin_info): ?>
        <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;">
            <strong>Info User Admin:</strong><br>
            ID: <?php echo $admin_info['id']; ?><br>
            Nama: <?php echo htmlspecialchars($admin_info['nama']); ?><br>
            Username: <?php echo htmlspecialchars($admin_info['username']); ?><br>
            Role: <?php echo htmlspecialchars($admin_info['role']); ?><br>
            Password Hash (20 karakter pertama): <?php echo substr($admin_info['password'], 0, 20); ?>...
        </div>
    <?php else: ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;">
            <strong>⚠ User admin belum ada!</strong> Klik tombol di bawah untuk membuat user admin baru.
        </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="<?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <label>Password Baru:</label>
        <input type="text" name="password" value="admin123" required>
        <button type="submit"><?php echo $admin_info ? 'Reset Password' : 'Buat User Admin'; ?></button>
    </form>
    
    <hr>
    <p><strong>Setelah reset, gunakan kredensial berikut untuk login:</strong></p>
    <ul>
        <li>Username: <code>admin</code></li>
        <li>Password: <code>admin123</code> (atau password yang Anda masukkan di atas)</li>
    </ul>
    
    <hr>
    <p><a href="login.php">→ Kembali ke Login</a></p>
</body>
</html>

