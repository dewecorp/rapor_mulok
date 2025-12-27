<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
try {
    $stmt = $conn->prepare("SELECT * FROM pengguna WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
} catch (Exception $e) {
    $user = null;
}

// Ambil profil madrasah untuk logo
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
}

// Fungsi untuk mendapatkan path relatif ke root
function getBasePath() {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $scriptDir = dirname($scriptPath);
    
    // Normalize path separators
    $scriptDir = str_replace('\\', '/', $scriptDir);
    
    // Jika di root, return empty string
    if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') {
        return '';
    }
    
    // Hitung kedalaman direktori
    $parts = explode('/', trim($scriptDir, '/'));
    $parts = array_filter($parts, function($p) { return $p !== '' && $p !== '.'; });
    $depth = count($parts);
    
    // Jika depth = 0 atau 1, berarti di root atau satu level
    if ($depth <= 1) {
        return '';
    }
    
    return str_repeat('../', $depth - 1);
}
$basePath = getBasePath();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME . ' - ' . APP_SHORT; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <style>
        :root {
            --hijau-kemenag: #2d5016;
            --hijau-kemenag-light: #4a7c2a;
            --hijau-kemenag-dark: #1a3009;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: white !important;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
        
        .user-details {
            color: white;
            text-align: right;
        }
        
        .user-details .user-name {
            font-weight: 600;
            font-size: 14px;
            margin: 0;
        }
        
        .user-details .user-role {
            font-size: 12px;
            opacity: 0.9;
            margin: 0;
        }
        
        .datetime-info {
            color: white;
            text-align: right;
            font-size: 13px;
            margin-left: 20px;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #f0f7f0;
            border-left-color: var(--hijau-kemenag);
            color: var(--hijau-kemenag);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .content-wrapper {
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--hijau-kemenag);
            border-color: var(--hijau-kemenag);
        }
        
        .btn-primary:hover {
            background-color: var(--hijau-kemenag-dark);
            border-color: var(--hijau-kemenag-dark);
        }
        
        .btn-success {
            background-color: var(--hijau-kemenag-light);
            border-color: var(--hijau-kemenag-light);
        }
        
        .btn-success:hover {
            background-color: var(--hijau-kemenag);
            border-color: var(--hijau-kemenag);
        }
        
        .table {
            font-size: 14px;
        }
        
        .badge {
            padding: 5px 10px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $basePath ? $basePath : ''; ?>index.php">
                <img src="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo'] ?? 'logo.png'); ?>" alt="Logo" onerror="this.onerror=null; this.style.display='none';">
                <?php echo APP_SHORT; ?>
            </a>
            <div class="ms-auto d-flex align-items-center">
                <div class="datetime-info">
                    <div id="datetime" style="min-width: 250px;"><?php 
                        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                        $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        $now = new DateTime();
                        echo $hari[$now->format('w')] . ', ' . $now->format('d') . ' ' . $bulan[$now->format('n')-1] . ' ' . $now->format('Y') . ' | ' . $now->format('H:i:s');
                    ?></div>
                </div>
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                        <div class="user-role"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></div>
                    </div>
                    <img src="uploads/<?php echo htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="User" class="user-avatar" onerror="this.onerror=null; this.style.display='none';">
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <nav class="nav flex-column mt-3">
                    <?php if ($user['role'] == 'proktor'): ?>
                        <a class="nav-link" href="<?php echo $basePath ? $basePath : ''; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#lembagaMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-school"></i> Lembaga <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="lembagaMenu" data-bs-parent=".sidebar">
                        <a class="nav-link ps-5" href="<?php echo $basePath; ?>lembaga/profil.php">
                            <i class="fas fa-circle"></i> Profil Madrasah
                        </a>
                        <a class="nav-link ps-5" href="<?php echo $basePath; ?>lembaga/materi.php">
                            <i class="fas fa-circle"></i> Materi Mulok
                        </a>
                        <a class="nav-link ps-5" href="<?php echo $basePath; ?>lembaga/kelas.php">
                            <i class="fas fa-circle"></i> Kelas
                        </a>
                        </div>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#guruMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-chalkboard-teacher"></i> Guru <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="guruMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>guru/data.php">
                                <i class="fas fa-circle"></i> Data Guru
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>guru/mengampu.php">
                                <i class="fas fa-circle"></i> Mengampu Materi
                            </a>
                        </div>
                        <a class="nav-link" href="<?php echo $basePath; ?>siswa/index.php">
                            <i class="fas fa-user-graduate"></i> Siswa
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#raporMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-file-alt"></i> Rapor <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="raporMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/kkm.php">
                                <i class="fas fa-circle"></i> Nilai KKM
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/pengaturan-cetak.php">
                                <i class="fas fa-circle"></i> Pengaturan Cetak
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/status-nilai.php">
                                <i class="fas fa-circle"></i> Status Nilai
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/cetak.php">
                                <i class="fas fa-circle"></i> Cetak Rapor
                            </a>
                        </div>
                        <a class="nav-link" href="<?php echo $basePath; ?>pengguna/index.php">
                            <i class="fas fa-users"></i> Pengguna
                        </a>
                        <a class="nav-link" href="<?php echo $basePath; ?>pengaturan/index.php">
                            <i class="fas fa-cog"></i> Pengaturan
                        </a>
                        <a class="nav-link" href="<?php echo $basePath; ?>backup/index.php">
                            <i class="fas fa-database"></i> Backup & Restore
                        </a>
                    <?php elseif ($user['role'] == 'wali_kelas'): ?>
                        <a class="nav-link" href="<?php echo $basePath ? $basePath : ''; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="<?php echo $basePath; ?>wali-kelas/materi.php">
                            <i class="fas fa-book"></i> Materi Mulok
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#waliMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-user-tie"></i> Wali Kelas <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="waliMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/siswa.php">
                                <i class="fas fa-circle"></i> Data Siswa
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/status-nilai.php">
                                <i class="fas fa-circle"></i> Status Nilai
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/rapor.php">
                                <i class="fas fa-circle"></i> Rapor
                            </a>
                        </div>
                    <?php elseif ($user['role'] == 'guru'): ?>
                        <a class="nav-link" href="<?php echo $basePath ? $basePath : ''; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="<?php echo $basePath; ?>guru/materi-diampu.php">
                            <i class="fas fa-book"></i> Materi yang Diampu
                        </a>
                    <?php endif; ?>
                    <a class="nav-link text-danger" href="<?php echo $basePath; ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            <div class="col-md-9 col-lg-10 content-wrapper">

