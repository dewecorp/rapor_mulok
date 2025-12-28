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
            font-size: 13px;
        }
        
        /* Perbesar semua font untuk keterbacaan yang lebih baik */
        * {
            font-size: inherit;
        }
        
        h1 { font-size: 1.75rem; }
        h2 { font-size: 1.5rem; }
        h3 { font-size: 1.25rem; }
        h4 { font-size: 1.1rem; }
        h5 { font-size: 1rem; }
        h6 { font-size: 0.95rem; }
        
        p, span, div, label {
            font-size: 13px;
        }
        
        input, select, textarea, button {
            font-size: 13px !important;
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
            filter: drop-shadow(0 0 4px rgba(255, 255, 255, 0.8));
            -webkit-filter: drop-shadow(0 0 4px rgba(255, 255, 255, 0.8));
        }
        
        .navbar-brand-content {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .navbar-brand-app-name {
            font-size: 15px;
            font-weight: bold;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .navbar-brand-school-name {
            font-size: 11px;
            font-weight: normal;
            opacity: 0.9;
            margin: 0;
            margin-top: 2px;
        }
        
        .navbar-brand-academic-info {
            font-size: 10px;
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
        
        .madrasah-logo-navbar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            margin-right: 10px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .madrasah-logo-navbar:hover {
            transform: scale(1.1);
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.6);
        }
        
        .proktor-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .proktor-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            z-index: 1000;
            overflow: hidden;
        }
        
        .proktor-dropdown-menu.show {
            display: block;
        }
        
        .proktor-dropdown-menu .dropdown-item {
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.2s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-size: 13px;
        }
        
        .proktor-dropdown-menu .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .proktor-dropdown-menu .dropdown-item.logout {
            color: #dc3545;
            border-top: 1px solid #e9ecef;
        }
        
        .proktor-dropdown-menu .dropdown-item.logout:hover {
            background-color: #fff5f5;
        }
        
        .proktor-dropdown-menu .dropdown-item i {
            width: 18px;
            text-align: center;
        }
        
        .user-details {
            color: white;
            text-align: right;
        }
        
        .user-details .user-name {
            font-weight: 600;
            font-size: 13px;
            margin: 0;
        }
        
        .user-details .user-role {
            font-size: 11px;
            opacity: 0.9;
            margin: 0;
        }
        
        .datetime-info {
            color: white;
            text-align: center;
            font-size: 12px;
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
            min-width: 200px;
        }
        
        @media (min-width: 992px) {
            .sidebar {
                min-width: 210px;
            }
        }
        
        .sidebar .nav-link {
            color: #2d5016;
            padding: 10px 16px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 2px 8px;
            border-radius: 8px;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            background: transparent;
        }
        
        .sidebar .nav-link:hover:not(.active) {
            background-color: rgba(45, 80, 22, 0.1) !important;
            border-left-color: var(--hijau-kemenag-light) !important;
            color: var(--hijau-kemenag-dark) !important;
            transform: translateX(5px);
        }
        
        /* Style aktif untuk semua menu - konsisten dan menarik - menggunakan warna langsung */
        .sidebar .nav-link.active,
        .sidebar a.nav-link.active,
        .sidebar nav a.nav-link.active,
        .sidebar .nav a.nav-link.active {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-image: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-color: transparent !important;
            border-left: 4px solid #ffffff !important;
            border-left-color: #ffffff !important;
            border-left-width: 4px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4) !important;
            transform: translateX(5px) !important;
            position: relative !important;
            z-index: 1 !important;
        }
        
        .sidebar .nav-link.active::before,
        .sidebar a.nav-link.active::before,
        .sidebar nav a.nav-link.active::before,
        .sidebar .nav a.nav-link.active::before {
            content: '' !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 4px !important;
            background: #ffffff !important;
            border-radius: 0 4px 4px 0 !important;
            z-index: 2 !important;
        }
        
        .sidebar .nav-link.active i,
        .sidebar a.nav-link.active i,
        .sidebar nav a.nav-link.active i,
        .sidebar .nav a.nav-link.active i {
            color: #ffffff !important;
            transform: scale(1.1) !important;
            transition: transform 0.3s ease;
        }
        
        /* Pastikan style aktif tidak tertimpa oleh hover */
        .sidebar .nav-link.active:hover,
        .sidebar a.nav-link.active:hover,
        .sidebar nav a.nav-link.active:hover,
        .sidebar .nav a.nav-link.active:hover {
            background: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-image: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 6px 16px rgba(45, 80, 22, 0.5) !important;
            transform: translateX(5px) scale(1.02) !important;
        }
        
        .sidebar .nav-link.active:hover i,
        .sidebar a.nav-link.active:hover i,
        .sidebar nav a.nav-link.active:hover i,
        .sidebar .nav a.nav-link.active:hover i {
            color: #ffffff !important;
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
            font-size: 12px;
            margin-left: 20px;
        }
        
        /* Style aktif untuk submenu - SAMA PERSIS dengan menu utama */
        .sidebar .nav-link.ps-5.active,
        .sidebar a.nav-link.ps-5.active {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-image: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-color: transparent !important;
            border-left: 4px solid #ffffff !important;
            border-left-color: #ffffff !important;
            border-left-width: 4px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4) !important;
            transform: translateX(5px) !important;
            position: relative !important;
            z-index: 1 !important;
        }
        
        .sidebar .nav-link.ps-5.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #fff;
            border-radius: 0 4px 4px 0;
            z-index: 2;
        }
        
        .sidebar .nav-link.ps-5.active i,
        .sidebar a.nav-link.ps-5.active i {
            color: white !important;
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }
        
        .sidebar .nav-link.ps-5.active:hover,
        .sidebar a.nav-link.ps-5.active:hover {
            background: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-image: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 6px 16px rgba(45, 80, 22, 0.5) !important;
            transform: translateX(5px) scale(1.02) !important;
        }
        
        .sidebar .nav-link.ps-5.active:hover i,
        .sidebar a.nav-link.ps-5.active:hover i {
            color: white !important;
        }
        
        /* Style untuk menu collapse */
        .sidebar .collapse .nav-link {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar .collapse .nav-link:hover {
            background-color: rgba(45, 80, 22, 0.15);
        }
        
        /* Pastikan menu aktif di collapse juga memiliki style yang SAMA PERSIS */
        .sidebar .collapse .nav-link.active,
        .sidebar .collapse a.nav-link.active {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-image: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-color: transparent !important;
            border-left: 4px solid #ffffff !important;
            border-left-color: #ffffff !important;
            border-left-width: 4px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4) !important;
            transform: translateX(5px) !important;
            position: relative !important;
            z-index: 1 !important;
        }
        
        .sidebar .collapse .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #fff;
            border-radius: 0 4px 4px 0;
            z-index: 2;
        }
        
        .sidebar .collapse .nav-link.active i,
        .sidebar .collapse a.nav-link.active i {
            color: white !important;
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }
        
        .sidebar .collapse .nav-link.active:hover,
        .sidebar .collapse a.nav-link.active:hover {
            background: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-image: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 6px 16px rgba(45, 80, 22, 0.5) !important;
            transform: translateX(5px) scale(1.02) !important;
        }
        
        .sidebar .collapse .nav-link.active:hover i,
        .sidebar .collapse a.nav-link.active:hover i {
            color: white !important;
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
        
        /* FINAL OVERRIDE - Pastikan semua menu aktif memiliki style yang sama persis */
        .sidebar nav.flex-column a.nav-link.active,
        .sidebar .nav.flex-column .nav-link.active,
        .sidebar .nav a.nav-link.active,
        .sidebar .nav .nav-link.active {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%) !important;
            border-left: 4px solid #fff !important;
            border-left-color: #fff !important;
            border-left-width: 4px !important;
            color: white !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4) !important;
            transform: translateX(5px) !important;
            position: relative !important;
            z-index: 10 !important;
        }
        
        .sidebar nav.flex-column a.nav-link.active::before,
        .sidebar .nav.flex-column .nav-link.active::before,
        .sidebar .nav a.nav-link.active::before,
        .sidebar .nav .nav-link.active::before {
            content: '' !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 4px !important;
            background: #fff !important;
            border-radius: 0 4px 4px 0 !important;
            z-index: 11 !important;
        }
        
        .sidebar nav.flex-column a.nav-link.active i,
        .sidebar .nav.flex-column .nav-link.active i,
        .sidebar .nav a.nav-link.active i,
        .sidebar .nav .nav-link.active i {
            color: white !important;
            transform: scale(1.1) !important;
        }
        
        .sidebar nav.flex-column a.nav-link.active:hover,
        .sidebar .nav.flex-column .nav-link.active:hover,
        .sidebar .nav a.nav-link.active:hover,
        .sidebar .nav .nav-link.active:hover {
            background: linear-gradient(135deg, var(--hijau-kemenag-dark) 0%, var(--hijau-kemenag) 100%) !important;
            color: white !important;
            box-shadow: 0 6px 16px rgba(45, 80, 22, 0.5) !important;
            transform: translateX(5px) scale(1.02) !important;
        }
        
        .sidebar nav.flex-column a.nav-link.active:hover i,
        .sidebar .nav.flex-column .nav-link.active:hover i,
        .sidebar .nav a.nav-link.active:hover i,
        .sidebar .nav .nav-link.active:hover i {
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
            padding: 12px 18px;
            font-weight: 600;
            font-size: 15px;
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
            font-size: 13px;
        }
        
        .table th {
            font-size: 13px;
            font-weight: 600;
        }
        
        .table td {
            font-size: 13px;
        }
        
        .badge {
            padding: 4px 8px;
            font-weight: 500;
            font-size: 11px;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            font-size: 13px;
            padding: 6px 10px;
        }
        
        .btn {
            font-size: 13px;
            padding: 6px 14px;
        }
        
        /* Style untuk tombol icon-only */
        .btn i {
            font-size: 12px;
        }
        
        .btn-sm {
            padding: 5px 8px;
        }
        
        .btn-sm i {
            font-size: 11px;
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
            font-size: 15px;
        }
        
        /* Perbesar font untuk elemen lainnya */
        .alert {
            font-size: 13px;
        }
        
        .modal-title {
            font-size: 16px;
        }
        
        .modal-body {
            font-size: 13px;
        }
        
        .dropdown-menu {
            font-size: 13px;
        }
        
        .dropdown-item {
            font-size: 13px;
            padding: 8px 14px;
        }
        
        .pagination {
            font-size: 13px;
        }
        
        .page-link {
            font-size: 13px;
            padding: 6px 10px;
        }
        
        .list-group-item {
            font-size: 13px;
        }
        
        .card-body {
            font-size: 13px;
        }
        
        .card-title {
            font-size: 15px;
        }
        
        .card-text {
            font-size: 13px;
        }
        
        small, .small {
            font-size: 16px;
        }
        
        .text-muted {
            font-size: 17px;
        }
        /* ULTIMATE OVERRIDE - Pastikan Dashboard dan semua menu aktif memiliki style yang sama persis */
        .sidebar .nav.flex-column a.nav-link.active,
        .sidebar .nav.flex-column .nav-link.active,
        .sidebar nav a.nav-link.active,
        .sidebar nav .nav-link.active,
        .sidebar a.nav-link.active,
        .sidebar .nav-link.active,
        .sidebar .nav-link.ps-5.active,
        .sidebar a.nav-link.ps-5.active,
        .sidebar .collapse .nav-link.active,
        .sidebar .collapse a.nav-link.active {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-image: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-color: transparent !important;
            border-left: 4px solid #ffffff !important;
            border-left-color: #ffffff !important;
            border-left-width: 4px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4) !important;
            transform: translateX(5px) !important;
            position: relative !important;
            z-index: 100 !important;
        }
        
        .sidebar .nav.flex-column a.nav-link.active::before,
        .sidebar .nav.flex-column .nav-link.active::before,
        .sidebar nav a.nav-link.active::before,
        .sidebar nav .nav-link.active::before,
        .sidebar a.nav-link.active::before,
        .sidebar .nav-link.active::before,
        .sidebar .nav-link.ps-5.active::before,
        .sidebar a.nav-link.ps-5.active::before,
        .sidebar .collapse .nav-link.active::before,
        .sidebar .collapse a.nav-link.active::before {
            content: '' !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 4px !important;
            background: #ffffff !important;
            border-radius: 0 4px 4px 0 !important;
            z-index: 101 !important;
        }
        
        .sidebar .nav.flex-column a.nav-link.active i,
        .sidebar .nav.flex-column .nav-link.active i,
        .sidebar nav a.nav-link.active i,
        .sidebar nav .nav-link.active i,
        .sidebar a.nav-link.active i,
        .sidebar .nav-link.active i,
        .sidebar .nav-link.ps-5.active i,
        .sidebar a.nav-link.ps-5.active i,
        .sidebar .collapse .nav-link.active i,
        .sidebar .collapse a.nav-link.active i {
            color: #ffffff !important;
            transform: scale(1.1) !important;
        }
        
        .sidebar .nav.flex-column a.nav-link.active:hover,
        .sidebar .nav.flex-column .nav-link.active:hover,
        .sidebar nav a.nav-link.active:hover,
        .sidebar nav .nav-link.active:hover,
        .sidebar a.nav-link.active:hover,
        .sidebar .nav-link.active:hover,
        .sidebar .nav-link.ps-5.active:hover,
        .sidebar a.nav-link.ps-5.active:hover,
        .sidebar .collapse .nav-link.active:hover,
        .sidebar .collapse a.nav-link.active:hover {
            background: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-image: linear-gradient(135deg, #1a3509 0%, #2d5016 100%) !important;
            background-color: transparent !important;
            color: #ffffff !important;
            box-shadow: 0 6px 16px rgba(45, 80, 22, 0.5) !important;
            transform: translateX(5px) scale(1.02) !important;
        }
        
        .sidebar .nav.flex-column a.nav-link.active:hover i,
        .sidebar .nav.flex-column .nav-link.active:hover i,
        .sidebar nav a.nav-link.active:hover i,
        .sidebar nav .nav-link.active:hover i,
        .sidebar a.nav-link.active:hover i,
        .sidebar .nav-link.active:hover i,
        .sidebar .nav-link.ps-5.active:hover i,
        .sidebar a.nav-link.ps-5.active:hover i,
        .sidebar .collapse .nav-link.active:hover i,
        .sidebar .collapse a.nav-link.active:hover i {
            color: #ffffff !important;
        }
        /* ABSOLUTE FINAL - Force style untuk Dashboard dan semua menu aktif dengan body selector */
        body .sidebar .nav.flex-column a.nav-link.active,
        body .sidebar .nav.flex-column .nav-link.active,
        body .sidebar nav a.nav-link.active,
        body .sidebar nav .nav-link.active,
        body .sidebar a.nav-link.active,
        body .sidebar .nav-link.active {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-image: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%) !important;
            background-color: #2d5016 !important;
            border-left: 4px solid #ffffff !important;
            border-left-color: #ffffff !important;
            border-left-width: 4px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.4) !important;
            transform: translateX(5px) !important;
            position: relative !important;
            z-index: 1000 !important;
        }
        
        body .sidebar .nav.flex-column a.nav-link.active i,
        body .sidebar .nav.flex-column .nav-link.active i,
        body .sidebar nav a.nav-link.active i,
        body .sidebar nav .nav-link.active i,
        body .sidebar a.nav-link.active i,
        body .sidebar .nav-link.active i {
            color: #ffffff !important;
            transform: scale(1.1) !important;
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
                    <?php if ($user['role'] == 'proktor'): ?>
                        <?php 
                        // Gunakan logo dari profil madrasah, jika tidak ada gunakan logo default
                        $logo_madrasah = !empty($profil['logo']) ? $profil['logo'] : 'logo.png';
                        ?>
                        <div class="proktor-dropdown">
                            <img src="<?php echo $baseUrlPath; ?>uploads/<?php echo htmlspecialchars($logo_madrasah); ?>" alt="Logo Madrasah" class="madrasah-logo-navbar" id="proktorLogoBtn" onerror="this.onerror=null; this.style.display='none';">
                            <div class="proktor-dropdown-menu" id="proktorDropdownMenu">
                                <a href="<?php echo $baseUrlPath; ?>logout.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
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
                        <?php 
                        // Tentukan apakah kita di halaman dashboard
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $is_dashboard = ($current_page == 'index.php' && empty($_GET));
                        $dashboard_active = $is_dashboard ? 'active' : '';
                        ?>
                        <a class="nav-link <?php echo $dashboard_active; ?>" href="<?php echo $baseUrlPath; ?>index.php">
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
                        <?php 
                        // Tentukan apakah kita di halaman dashboard
                        $current_page = basename($_SERVER['PHP_SELF']);
                        $is_dashboard = ($current_page == 'index.php' && empty($_GET));
                        $dashboard_active = $is_dashboard ? 'active' : '';
                        ?>
                        <a class="nav-link <?php echo $dashboard_active; ?>" href="<?php echo $baseUrlPath; ?>index.php">
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

