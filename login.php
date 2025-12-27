<?php
// Start session hanya jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';
require_once 'config/database.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$conn = getConnection();

// Ambil foto login dari pengaturan (handle jika tabel belum ada)
$foto_login = 'login-bg.jpg';
try {
    $query_profil = "SELECT foto_login FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    if ($result_profil && $result_profil->num_rows > 0) {
        $profil = $result_profil->fetch_assoc();
        $foto_login = $profil['foto_login'] ?? 'login-bg.jpg';
    }
} catch (Exception $e) {
    // Tabel belum ada, gunakan default
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            // Debug mode - uncomment untuk debugging
            // error_log("Login attempt: username=$username");
            
            // Untuk guru dan wali_kelas, cek NUPTK terlebih dahulu, jika tidak ada cek username
            // Untuk proktor, gunakan username
            $query = "SELECT * FROM pengguna WHERE (nuptk = ? OR username = ?)";
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                $error = 'Error prepare query: ' . $conn->error;
                error_log("Login error: " . $conn->error);
            } else {
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    // Verifikasi password
                    if (password_verify($password, $user['password'])) {
                        // Set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['nama'] = $user['nama'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['foto'] = $user['foto'] ?? 'default.png';
                        
                        // Pastikan session tersimpan
                        session_write_close();
                        
                        // Redirect ke dashboard
                        header('Location: index.php');
                        exit();
                    } else {
                        // Cek apakah password menggunakan hash lama (md5 atau plain)
                        if ($user['password'] === md5($password) || $user['password'] === $password) {
                            // Update ke password_hash yang baru
                            $new_hash = password_hash($password, PASSWORD_DEFAULT);
                            $update_stmt = $conn->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
                            $update_stmt->bind_param("si", $new_hash, $user['id']);
                            $update_stmt->execute();
                            
                            // Set session setelah update password
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['nama'] = $user['nama'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['foto'] = $user['foto'] ?? 'default.png';
                            
                            session_write_close();
                            header('Location: index.php');
                            exit();
                        } else {
                            $error = 'Password salah! Silakan reset password di: <a href="reset_admin.php" style="color: #2d5016; text-decoration: underline;">reset_admin.php</a>';
                        }
                    }
                } else {
                    $error = 'Username tidak ditemukan! Pastikan database sudah diimport dengan benar.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            error_log("Login exception: " . $e->getMessage());
        }
    } else {
        $error = 'Username dan password harus diisi!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --hijau-kemenag: #2d5016;
            --hijau-kemenag-light: #4a7c2a;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }
        
        .login-image {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
        }
        
        .login-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .login-form {
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo img {
            height: 80px;
            margin-bottom: 15px;
        }
        
        .login-logo h2 {
            color: var(--hijau-kemenag);
            font-weight: bold;
            margin: 0;
        }
        
        .login-logo p {
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ddd;
        }
        
        .form-control:focus {
            border-color: var(--hijau-kemenag);
            box-shadow: 0 0 0 0.2rem rgba(45, 80, 22, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px;
            color: white;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45, 80, 22, 0.3);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        
        .form-control.input-with-icon {
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0">
                <div class="col-md-6 login-image">
                    <img src="uploads/<?php echo htmlspecialchars($foto_login); ?>" alt="Login Image" onerror="this.onerror=null; this.style.display='none';">
                </div>
                <div class="col-md-6 login-form">
                    <div class="login-logo">
                        <?php
                        $logo = 'logo.png';
                        try {
                            $query_logo = "SELECT logo FROM profil_madrasah LIMIT 1";
                            $result_logo = $conn->query($query_logo);
                            if ($result_logo && $result_logo->num_rows > 0) {
                                $logo_data = $result_logo->fetch_assoc();
                                $logo = $logo_data['logo'] ?? 'logo.png';
                            }
                        } catch (Exception $e) {
                            // Tabel belum ada, gunakan default
                        }
                        ?>
                        <img src="uploads/<?php echo htmlspecialchars($logo); ?>" alt="Logo" onerror="this.onerror=null; this.style.display='none';">
                        <h2><?php echo APP_SHORT; ?></h2>
                        <p><?php echo APP_NAME; ?></p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control input-with-icon" id="username" name="username" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control input-with-icon" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt"></i> Masuk
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#2d5016',
            timer: 5000,
            timerProgressBar: true,
            showConfirmButton: true
        });
        <?php endif; ?>
    </script>
</body>
</html>

