<?php
/**
 * Script untuk test koneksi database
 * Akses melalui: http://localhost/rapor-mulok/config/test_connection.php
 * HAPUS FILE INI SETELAH SELESAI!
 */

require_once __DIR__ . '/database.php';

echo "<h2>Test Koneksi Database</h2>";
echo "<hr>";

try {
    $conn = getConnection();
    echo "<p style='color: green;'>✓ Koneksi database berhasil!</p>";
    
    // Cek tabel pengguna
    echo "<h3>1. Cek Tabel Pengguna</h3>";
    $tables = $conn->query("SHOW TABLES LIKE 'pengguna'");
    if ($tables->num_rows > 0) {
        echo "<p style='color: green;'>✓ Tabel 'pengguna' ada</p>";
        
        // Cek data pengguna
        $users = $conn->query("SELECT id, nama, username, role FROM pengguna");
        echo "<p>Jumlah pengguna: " . $users->num_rows . "</p>";
        
        if ($users->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Nama</th><th>Username</th><th>Role</th></tr>";
            while ($user = $users->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $user['id'] . "</td>";
                echo "<td>" . htmlspecialchars($user['nama']) . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Test password
            echo "<h3>2. Test Password Admin</h3>";
            $admin = $conn->query("SELECT * FROM pengguna WHERE username = 'admin'")->fetch_assoc();
            if ($admin) {
                echo "<p>Username: " . htmlspecialchars($admin['username']) . "</p>";
                echo "<p>Password Hash: " . substr($admin['password'], 0, 30) . "...</p>";
                
                $test_password = 'admin123';
                if (password_verify($test_password, $admin['password'])) {
                    echo "<p style='color: green;'>✓ Password 'admin123' BENAR!</p>";
                } else {
                    echo "<p style='color: red;'>✗ Password 'admin123' SALAH!</p>";
                    echo "<p>Silakan jalankan script fix_login.php atau fix_admin_password.sql</p>";
                }
            } else {
                echo "<p style='color: red;'>✗ User admin tidak ditemukan!</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Belum ada data pengguna. Silakan import database/schema.sql</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Tabel 'pengguna' tidak ada. Silakan import database/schema.sql</p>";
    }
    
    // Cek tabel profil_madrasah
    echo "<h3>3. Cek Tabel Profil Madrasah</h3>";
    $tables_profil = $conn->query("SHOW TABLES LIKE 'profil_madrasah'");
    if ($tables_profil->num_rows > 0) {
        echo "<p style='color: green;'>✓ Tabel 'profil_madrasah' ada</p>";
        $profil = $conn->query("SELECT COUNT(*) as total FROM profil_madrasah")->fetch_assoc();
        echo "<p>Jumlah data: " . $profil['total'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Tabel 'profil_madrasah' tidak ada. Silakan import database/schema.sql</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='fix_login.php'>→ Perbaiki Login</a> | <a href='../login.php'>→ Coba Login</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Pastikan konfigurasi database di config/database.php sudah benar!</p>";
}


