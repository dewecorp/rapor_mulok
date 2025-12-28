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

// Fungsi untuk mendapatkan base URL absolut
function getBaseUrlPath() {
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/';
    }
    return '/';
}

$basePath = getBasePath();
$baseUrlPath = getBaseUrlPath();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME . ' - ' . APP_SHORT; ?></title>
    
    <!-- Favicon menggunakan logo sekolah -->
    <?php if (!empty($profil['logo'])): ?>
        <link rel="icon" type="image/png" href="<?php echo $baseUrlPath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
        <link rel="shortcut icon" type="image/png" href="<?php echo $baseUrlPath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
        <link rel="apple-touch-icon" href="<?php echo $baseUrlPath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?php echo $baseUrlPath; ?>uploads/logo.png">
        <link rel="shortcut icon" type="image/png" href="<?php echo $baseUrlPath; ?>uploads/logo.png">
    <?php endif; ?>
    
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
            font-size: 18px;
        }
        
        /* Perbesar semua font untuk keterbacaan yang lebih baik */
        * {
            font-size: inherit;
        }
        
        h1 { font-size: 2.5rem; }
        h2 { font-size: 2rem; }
        h3 { font-size: 1.75rem; }
        h4 { font-size: 1.5rem; }
        h5 { font-size: 1.25rem; }
        h6 { font-size: 1.1rem; }
        
        p, span, div, label {
            font-size: 18px;
        }
        
        input, select, textarea, button {
            font-size: 18px !important;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: white !important;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .navbar-brand-content {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .navbar-brand-app-name {
            font-size: 20px;
            font-weight: bold;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .navbar-brand-school-name {
            font-size: 14px;
            font-weight: normal;
            opacity: 0.9;
            margin: 0;
            margin-top: 2px;
        }
        
        .navbar-brand-academic-info {
            font-size: 13px;
            font-weight: normal;
            opacity: 0.85;
            padding: 2px 8px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
            white-space: nowrap;
        }
        
        .navbar-brand-academic-info {
            font-size: 13px;
            font-weight: normal;
            opacity: 0.85;
            padding: 2px 8px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
            white-space: nowrap;
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
            font-size: 18px;
            margin: 0;
        }
        
        .user-details .user-role {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
        }
        
        .datetime-info {
            color: white;
            text-align: center;
            font-size: 17px;
            margin: 0 auto;
            padding: 0;
            flex: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .datetime-info #datetime {
            white-space: nowrap;
        }
        
        .navbar .container-fluid {
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
        }
        
        .navbar-brand {
            flex: 0 0 auto;
        }
        
        .navbar .ms-auto {
            margin-left: auto !important;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            flex: 0 0 auto;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: linear-gradient(180deg, #e8f5e9 0%, #c8e6c9 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .sidebar .nav-link {
            color: #2d5016;
            padding: 14px 20px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 2px 8px;
            border-radius: 8px;
            font-size: 18px;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(45, 80, 22, 0.1);
            border-left-color: var(--hijau-kemenag-light);
            color: var(--hijau-kemenag-dark);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%) !important;
            border-left-color: #fff !important;
            color: white !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 8px rgba(45, 80, 22, 0.3) !important;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active i {
            color: white !important;
        }
        
        /* Pastikan style aktif tidak tertimpa oleh hover */
        .sidebar .nav-link.active:hover {
            background: linear-gradient(135deg, var(--hijau-kemenag-dark) 0%, var(--hijau-kemenag) 100%) !important;
            color: white !important;
        }
        
        .sidebar .nav-link.active:hover i {
            color: white !important;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
            color: inherit;
            transition: color 0.3s ease;
        }
        
        .sidebar .nav-link:hover i {
            color: var(--hijau-kemenag-dark);
        }
        
        /* Style untuk submenu */
        .sidebar .nav-link.ps-5 {
            padding-left: 3rem !important;
            font-size: 17px;
            margin-left: 20px;
        }
        
        .sidebar .nav-link.ps-5.active {
            background: linear-gradient(135deg, var(--hijau-kemenag-light) 0%, var(--hijau-kemenag) 100%) !important;
            border-left-color: #fff !important;
            color: white !important;
            font-weight: 600 !important;
        }
        
        .sidebar .nav-link.ps-5.active i {
            color: white !important;
        }
        
        .sidebar .nav-link.ps-5.active:hover {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-dark) 100%) !important;
            color: white !important;
        }
        
        .sidebar .nav-link.ps-5.active:hover i {
            color: white !important;
        }
        
        /* Style untuk menu collapse */
        .sidebar .collapse .nav-link {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar .collapse .nav-link:hover {
            background-color: rgba(45, 80, 22, 0.15);
        }
        
        /* Style untuk parent menu yang memiliki child aktif */
        .sidebar .nav-link.has-active-child {
            background: linear-gradient(135deg, rgba(45, 80, 22, 0.2) 0%, rgba(74, 124, 42, 0.2) 100%) !important;
            border-left-color: var(--hijau-kemenag) !important;
            color: var(--hijau-kemenag-dark) !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(45, 80, 22, 0.2);
        }
        
        .sidebar .nav-link.has-active-child i:not(.fa-chevron-down) {
            color: var(--hijau-kemenag-dark) !important;
        }
        
        /* Pastikan chevron icon pada parent menu yang aktif terlihat jelas */
        .sidebar .nav-link.has-active-child .fa-chevron-down {
            color: var(--hijau-kemenag-dark) !important;
        }
        
        /* Style hover untuk parent menu yang memiliki child aktif */
        .sidebar .nav-link.has-active-child:hover {
            background: linear-gradient(135deg, rgba(45, 80, 22, 0.3) 0%, rgba(74, 124, 42, 0.3) 100%) !important;
            transform: translateX(5px);
        }
        
        /* Style untuk menu logout */
        .sidebar .nav-link.text-danger {
            color: #d32f2f !important;
            border-top: 1px solid rgba(0,0,0,0.1);
            margin-top: 10px;
            padding-top: 16px;
        }
        
        .sidebar .nav-link.text-danger:hover {
            background-color: rgba(211, 47, 47, 0.1);
            color: #b71c1c !important;
        }
        
        .sidebar .nav-link.text-danger.active {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white !important;
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
            font-size: 20px;
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
            font-size: 18px;
        }
        
        .table th {
            font-size: 18px;
            font-weight: 600;
        }
        
        .table td {
            font-size: 18px;
        }
        
        .badge {
            padding: 7px 14px;
            font-weight: 500;
            font-size: 16px;
        }
        
        .form-label {
            font-size: 18px;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            font-size: 18px;
            padding: 10px 15px;
        }
        
        .btn {
            font-size: 18px;
            padding: 10px 20px;
        }
        
        /* Style untuk tombol icon-only */
        .btn i {
            font-size: 16px;
        }
        
        .btn-sm {
            padding: 8px 12px;
        }
        
        .btn-sm i {
            font-size: 16px;
        }
        
        /* Tooltip styling */
        [title] {
            cursor: help;
        }
        
        /* Pastikan tombol icon-only memiliki ukuran konsisten */
        .btn:not(:has(span)):not(:has(strong)):not(:has(b)) {
            min-width: 38px;
        }
        
        .btn-sm:not(:has(span)):not(:has(strong)):not(:has(b)) {
            min-width: 34px;
        }
        
        .navbar-brand {
            font-size: 22px;
        }
        
        /* Perbesar font untuk elemen lainnya */
        .alert {
            font-size: 18px;
        }
        
        .modal-title {
            font-size: 22px;
        }
        
        .modal-body {
            font-size: 18px;
        }
        
        .dropdown-menu {
            font-size: 18px;
        }
        
        .dropdown-item {
            font-size: 18px;
            padding: 10px 20px;
        }
        
        .pagination {
            font-size: 18px;
        }
        
        .page-link {
            font-size: 18px;
            padding: 10px 15px;
        }
        
        .list-group-item {
            font-size: 18px;
        }
        
        .card-body {
            font-size: 18px;
        }
        
        .card-title {
            font-size: 20px;
        }
        
        .card-text {
            font-size: 18px;
        }
        
        small, .small {
            font-size: 16px;
        }
        
        .text-muted {
            font-size: 17px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $baseUrlPath; ?>index.php">
                <img src="<?php echo $baseUrlPath; ?>uploads/<?php echo htmlspecialchars($profil['logo'] ?? 'logo.png'); ?>" alt="Logo" onerror="this.onerror=null; this.style.display='none';">
                <div class="navbar-brand-content">
                    <div class="navbar-brand-app-name">
                        <?php echo APP_SHORT; ?>
                        <?php if (!empty($profil['tahun_ajaran_aktif']) || !empty($profil['semester_aktif'])): ?>
                            <span class="navbar-brand-academic-info">
                                <?php echo htmlspecialchars($profil['tahun_ajaran_aktif'] ?? '-'); ?> / Semester <?php echo htmlspecialchars($profil['semester_aktif'] ?? '-'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="navbar-brand-school-name"><?php echo htmlspecialchars($profil['nama_madrasah'] ?? 'Nama Sekolah'); ?></div>
                </div>
            </a>
            <div class="datetime-info">
                <div id="datetime"></div>
            </div>
            <div class="ms-auto d-flex align-items-center">
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($user['nama']); ?></div>
                        <div class="user-role"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></div>
                    </div>
                    <img src="<?php echo $baseUrlPath; ?>uploads/<?php echo htmlspecialchars($user['foto'] ?? 'default.png'); ?>" alt="User" class="user-avatar" onerror="this.onerror=null; this.style.display='none';">
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <nav class="nav flex-column mt-3">
                    <?php if ($user['role'] == 'proktor'): ?>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#lembagaMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-school"></i> Lembaga <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="lembagaMenu" data-bs-parent=".sidebar">
                        <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>lembaga/profil.php">
                            <i class="fas fa-circle"></i> Profil Madrasah
                        </a>
                        <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>lembaga/materi.php">
                            <i class="fas fa-circle"></i> Materi Mulok
                        </a>
                        <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>lembaga/kelas.php">
                            <i class="fas fa-circle"></i> Kelas
                        </a>
                        </div>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#guruMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-chalkboard-teacher"></i> Guru <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="guruMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>guru/data.php">
                                <i class="fas fa-circle"></i> Data Guru
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>guru/mengampu.php">
                                <i class="fas fa-circle"></i> Mengampu Materi
                            </a>
                        </div>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>siswa/index.php">
                            <i class="fas fa-user-graduate"></i> Siswa
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#raporMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-file-alt"></i> Rapor <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="raporMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>rapor/kkm.php">
                                <i class="fas fa-circle"></i> Nilai KKM
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>rapor/pengaturan-cetak.php">
                                <i class="fas fa-circle"></i> Pengaturan Cetak
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>rapor/status-nilai.php">
                                <i class="fas fa-circle"></i> Status Nilai
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>rapor/cetak.php">
                                <i class="fas fa-circle"></i> Cetak Rapor
                            </a>
                        </div>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>pengguna/index.php">
                            <i class="fas fa-users"></i> Pengguna
                        </a>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>pengaturan/index.php">
                            <i class="fas fa-cog"></i> Pengaturan
                        </a>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>backup/index.php">
                            <i class="fas fa-database"></i> Backup & Restore
                        </a>
                    <?php elseif ($user['role'] == 'wali_kelas'): ?>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>wali-kelas/materi.php">
                            <i class="fas fa-book"></i> Materi Mulok
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#waliMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-user-tie"></i> Wali Kelas <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="waliMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>wali-kelas/siswa.php">
                                <i class="fas fa-circle"></i> Data Siswa
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>wali-kelas/status-nilai.php">
                                <i class="fas fa-circle"></i> Status Nilai
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $baseUrlPath; ?>wali-kelas/rapor.php">
                                <i class="fas fa-circle"></i> Rapor
                            </a>
                        </div>
                    <?php elseif ($user['role'] == 'guru'): ?>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a class="nav-link" href="<?php echo $baseUrlPath; ?>guru/materi-diampu.php">
                            <i class="fas fa-book"></i> Materi yang Diampu
                        </a>
                    <?php endif; ?>
                    <a class="nav-link text-danger" href="<?php echo $baseUrlPath; ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            <div class="col-md-9 col-lg-10 content-wrapper">

