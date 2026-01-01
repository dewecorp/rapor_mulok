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
    
    // Validasi dan sinkronisasi role dari database
    // Pastikan role di session sesuai dengan role di database
    if ($user && isset($user['role'])) {
        $role_db = $user['role'];
        
        // Jika role di session berbeda dengan database, update session
        if (!isset($_SESSION['role']) || $_SESSION['role'] != $role_db) {
            $_SESSION['role'] = $role_db;
        }
        
        // Update user array dengan role yang benar
        $user['role'] = $_SESSION['role'];
    }
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

// Ambil materi yang diampu oleh wali kelas di semester aktif
$materi_wali_kelas = [];
$materi_wali_kelas_by_kategori = [];
$materi_guru = [];
$materi_guru_by_kategori = [];

// Cek apakah kolom kategori_mulok ada (untuk semua role)
$has_kategori_mulok = false;
try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kategori_mulok') {
                $has_kategori_mulok = true;
                break;
            }
        }
    }
} catch (Exception $e) {
    // Ignore
}

if ($user && $user['role'] == 'wali_kelas') {
    try {
        $semester_aktif = $profil['semester_aktif'] ?? '1';
        // Ambil materi yang diampu oleh wali kelas di semester aktif
        // Filter berdasarkan kelas yang diampu oleh wali kelas tersebut
        $kolom_kategori = $has_kategori_mulok ? 'm.kategori_mulok' : 'm.kode_mulok';
        $stmt_materi = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok, k.nama_kelas, $kolom_kategori as kategori
                      FROM mengampu_materi mm
                      INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                      INNER JOIN kelas k ON mm.kelas_id = k.id
                      WHERE mm.guru_id = ? AND m.semester = ? AND k.wali_kelas_id = ?
                      ORDER BY kategori, m.nama_mulok");
        $stmt_materi->bind_param("isi", $user_id, $semester_aktif, $user_id);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        if ($result_materi) {
            while ($row_materi = $result_materi->fetch_assoc()) {
                $materi_wali_kelas[] = $row_materi;
                
                // Kelompokkan berdasarkan kategori
                $kategori = $row_materi['kategori'] ?? 'Lainnya';
                if (!isset($materi_wali_kelas_by_kategori[$kategori])) {
                    $materi_wali_kelas_by_kategori[$kategori] = [];
                }
                $materi_wali_kelas_by_kategori[$kategori][] = $row_materi;
            }
        }
        $stmt_materi->close();
    } catch (Exception $e) {
        $materi_wali_kelas = [];
        $materi_wali_kelas_by_kategori = [];
    }
} elseif ($user && $user['role'] == 'guru') {
    try {
        $semester_aktif = $profil['semester_aktif'] ?? '1';
        // Ambil materi yang diampu oleh guru di semester aktif
        $kolom_kategori = $has_kategori_mulok ? 'm.kategori_mulok' : 'm.kode_mulok';
        $stmt_materi = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok, k.nama_kelas, k.id as kelas_id, $kolom_kategori as kategori
                      FROM mengampu_materi mm
                      INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                      INNER JOIN kelas k ON mm.kelas_id = k.id
                      WHERE mm.guru_id = ? AND m.semester = ?
                      ORDER BY kategori, m.nama_mulok");
        $stmt_materi->bind_param("is", $user_id, $semester_aktif);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        if ($result_materi) {
            while ($row_materi = $result_materi->fetch_assoc()) {
                $materi_guru[] = $row_materi;
                
                // Kelompokkan berdasarkan kategori
                $kategori = $row_materi['kategori'] ?? 'Lainnya';
                if (!isset($materi_guru_by_kategori[$kategori])) {
                    $materi_guru_by_kategori[$kategori] = [];
                }
                $materi_guru_by_kategori[$kategori][] = $row_materi;
            }
        }
        $stmt_materi->close();
    } catch (Exception $e) {
        $materi_guru = [];
        $materi_guru_by_kategori = [];
    }
}

// Fungsi untuk mendapatkan path relatif ke root aplikasi secara konsisten
// Menggunakan session untuk menyimpan base path agar konsisten
function getBasePath() {
    // Gunakan fungsi getRelativePath() dari config.php untuk konsistensi
    return getRelativePath();
}
$basePath = getBasePath();

// Ambil page title - prioritas: variabel lokal $page_title > session > default
// Variabel lokal $page_title harus di-set sebelum include header.php
if (isset($page_title) && !empty($page_title)) {
    $page_title_value = $page_title;
} elseif (isset($_SESSION['page_title']) && !empty($_SESSION['page_title'])) {
    $page_title_value = $_SESSION['page_title'];
    // Clear session setelah digunakan
    unset($_SESSION['page_title']);
} else {
    $page_title_value = APP_SHORT;
}

// Format title: Page Title - APP_NAME
$full_title = $page_title_value . ' - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Prevent browser cache untuk development -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($full_title); ?></title>
    
    <!-- Favicon menggunakan logo sekolah -->
    <?php if (!empty($profil['logo'])): ?>
        <link rel="icon" type="image/png" href="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
        <link rel="shortcut icon" type="image/png" href="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
        <link rel="apple-touch-icon" href="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?php echo $basePath; ?>uploads/logo.png">
        <link rel="shortcut icon" type="image/png" href="<?php echo $basePath; ?>uploads/logo.png">
    <?php endif; ?>
    
    <!-- Cache busting version - gunakan APP_VERSION saja untuk production -->
    <?php 
    // Untuk development, gunakan timestamp. Untuk production, gunakan APP_VERSION saja
    // Ubah ke time() jika perlu force refresh saat development
    $cache_version = APP_VERSION; // Gunakan APP_VERSION untuk cache yang lebih baik
    // $cache_version = APP_VERSION . '.' . time(); // Uncomment untuk development
    ?>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css?v=<?php echo $cache_version; ?>" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css?v=<?php echo $cache_version; ?>">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css?v=<?php echo $cache_version; ?>">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css?v=<?php echo $cache_version; ?>">
    
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css?v=<?php echo $cache_version; ?>">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <style>
        :root {
            --hijau-kemenag: #2d5016;
            --hijau-kemenag-light: #4a7c2a;
            --hijau-kemenag-dark: #1a3009;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            font-size: 14px;
            /* Optimasi rendering */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Perbesar semua font untuk keterbacaan yang lebih baik */
        * {
            font-size: inherit;
        }
        
        h1 { font-size: 2rem; }
        h2 { font-size: 1.75rem; }
        h3 { font-size: 1.5rem; }
        h4 { font-size: 1.25rem; }
        h5 { font-size: 1.1rem; }
        h6 { font-size: 1rem; }
        
        p, span, div, label {
            font-size: 14px;
        }
        
        input, select, textarea, button {
            font-size: 14px !important;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--hijau-kemenag) 0%, var(--hijau-kemenag-light) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1030;
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
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .navbar-brand-school-name {
            font-size: 12px;
            font-weight: normal;
            opacity: 0.9;
            margin: 0;
            margin-top: 2px;
        }
        
        .navbar-brand-academic-info {
            font-size: 11px;
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
            flex: 0 0 auto;
            margin-right: 15px;
            min-width: 0;
            max-width: 200px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
            text-align: right;
            min-width: 0;
            flex: 0 0 auto;
            max-width: 100%;
            overflow: hidden;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 15px;
            color: white;
            margin: 0;
            padding: 0;
            white-space: nowrap;
            text-align: right;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            padding: 0;
            justify-content: flex-end;
            min-width: 0;
        }
        
        .user-role-text {
            font-size: 13px;
            color: white;
            opacity: 0.9;
            white-space: nowrap;
            margin: 0;
            padding: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-avatar-wrapper {
            flex-shrink: 0 !important;
            width: 40px !important;
            height: 40px !important;
            position: relative !important;
            margin-left: auto !important;
            flex: 0 0 40px !important;
            order: 999 !important;
            overflow: visible !important;
        }
        
        .user-avatar {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            cursor: pointer;
            flex-shrink: 0;
            display: block !important;
        }
        
        .user-avatar:hover {
            border-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
        }
        
        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 1050;
            display: none;
        }
        
        .user-dropdown-menu.show {
            display: block;
        }
        
        .user-dropdown-item {
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .user-dropdown-item:hover {
            background-color: #f5f5f5;
            color: #2d5016;
        }
        
        .user-dropdown-item.logout {
            color: #dc3545;
        }
        
        .user-dropdown-item.logout:hover {
            background-color: #fff5f5;
            color: #c82333;
        }
        
        .datetime-info {
            color: white;
            text-align: center;
            font-size: 14px;
            margin: 0;
            padding: 0;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .datetime-info #datetime {
            white-space: nowrap;
        }
        
        /* Sembunyikan jam di mode mobile dan tablet */
        @media (max-width: 991px) {
            .datetime-info {
                display: none !important;
            }
        }
        
        .navbar .container-fluid {
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
            justify-content: space-between;
            padding-right: 15px;
        }
        
        .navbar-brand {
            flex: 0 0 auto;
            z-index: 1;
        }
        
        .navbar .ms-auto {
            margin-left: auto !important;
            display: flex !important;
            align-items: center !important;
            gap: 15px !important;
            width: auto !important;
            flex-wrap: nowrap !important;
            flex-shrink: 0 !important;
            z-index: 1 !important;
            justify-content: flex-end !important;
            min-width: fit-content !important;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: linear-gradient(180deg, #e8f5e9 0%, #c8e6c9 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-x: hidden;
            transition: width 0.3s ease;
            position: relative;
        }
        
        .sidebar.collapsed {
            width: 70px !important;
        }
        
        .sidebar.collapsed .nav-link {
            padding: 10px;
            justify-content: center;
            text-align: center;
        }
        
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .nav-link .float-end {
            display: none !important;
        }
        
        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 18px;
        }
        
        .sidebar.collapsed .collapse {
            display: none !important;
        }
        
        @media (min-width: 992px) {
            .sidebar.collapsed {
                width: 70px !important;
                flex: 0 0 70px !important;
                max-width: 70px !important;
            }
            
            .row .sidebar.collapsed ~ .content-wrapper {
                flex: 0 0 calc(100% - 70px) !important;
                max-width: calc(100% - 70px) !important;
                width: calc(100% - 70px) !important;
            }
        }
        
        @media (max-width: 991px) {
            .sidebar.collapsed {
                width: 70px !important;
            }
        }
        
        .sidebar-toggle-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(45, 80, 22, 0.1);
            border: none;
            color: #2d5016;
            padding: 8px 10px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 10;
            transition: background-color 0.2s ease;
        }
        
        .sidebar-toggle-btn:hover {
            background: rgba(45, 80, 22, 0.2);
        }
        
        .sidebar.collapsed .sidebar-toggle-btn {
            right: 5px;
            left: 5px;
            width: auto;
        }
        
        .sidebar.collapsed .sidebar-toggle-btn i {
            transform: rotate(180deg);
        }
        
        .sidebar .nav-link {
            min-width: 0;
            color: #2d5016;
            padding: 10px 20px;
            border-left: 4px solid transparent;
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            font-weight: 500;
            margin: 1px 8px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
            /* Optimasi rendering */
            will-change: background-color, border-color, transform;
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
            padding: 8px 15px 8px 2.5rem !important;
            font-size: 13px;
            margin-left: 15px;
            margin-top: 0;
            margin-bottom: 0;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
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
        .sidebar .collapse {
            margin-top: 0;
            margin-bottom: 0;
        }
        
        .sidebar .collapse .nav-link {
            background-color: rgba(255, 255, 255, 0.5);
            margin-top: 0;
            margin-bottom: 0;
        }
        
        .sidebar .collapse .nav-link:hover {
            background-color: rgba(45, 80, 22, 0.15);
        }
        
        /* Pastikan submenu tidak menumpuk */
        .sidebar .collapse .nav-link.ps-5 {
            padding-top: 6px !important;
            padding-bottom: 6px !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
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
            font-size: 16px;
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
        
        .table th {
            font-size: 14px;
            font-weight: 600;
        }
        
        .table td {
            font-size: 14px;
        }
        
        .badge {
            padding: 7px 14px;
            font-weight: 500;
            font-size: 13px;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            font-size: 14px;
            padding: 10px 15px;
        }
        
        .btn {
            font-size: 14px;
            padding: 10px 20px;
        }
        
        /* Style untuk tombol icon-only */
        .btn i {
            font-size: 13px;
        }
        
        .btn-sm {
            padding: 8px 12px;
        }
        
        .btn-sm i {
            font-size: 13px;
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
            font-size: 18px;
        }
        
        /* Perbesar font untuk elemen lainnya */
        .alert {
            font-size: 14px;
        }
        
        .modal-title {
            font-size: 18px;
        }
        
        .modal-body {
            font-size: 14px;
        }
        
        .dropdown-menu {
            font-size: 14px;
        }
        
        .dropdown-item {
            font-size: 14px;
            padding: 10px 20px;
        }
        
        .pagination {
            font-size: 14px;
        }
        
        .page-link {
            font-size: 14px;
            padding: 10px 15px;
        }
        
        .list-group-item {
            font-size: 14px;
        }
        
        .card-body {
            font-size: 14px;
        }
        
        .card-title {
            font-size: 16px;
        }
        
        .card-text {
            font-size: 14px;
        }
        
        small, .small {
            font-size: 13px;
        }
        
        .text-muted {
            font-size: 14px;
        }
        
        /* Responsive untuk navbar user info */
        @media (max-width: 991px) {
            .user-info {
                margin-right: 10px;
                max-width: 150px;
            }
            
            .user-name {
                font-size: 14px;
            }
            
            .user-role-text {
                font-size: 12px;
            }
            
            .user-avatar-wrapper {
                width: 36px;
                height: 36px;
            }
            
            .user-avatar {
                width: 36px;
                height: 36px;
            }
        }
        
        @media (max-width: 768px) {
            .user-details {
                display: none;
            }
            
            .user-info {
                margin-right: 10px;
                max-width: none;
            }
        }
        
        /* Pastikan tidak ada overlap */
        .navbar .ms-auto > * {
            position: relative;
            z-index: 1;
        }
        
        .user-avatar-wrapper {
            z-index: 2 !important;
        }
        
        /* Pastikan user-info dan avatar tidak overlap */
        .navbar .ms-auto {
            min-width: 0;
        }
        
        /* ============================================
           RESPONSIVE DESIGN - MEDIA QUERIES
           ============================================ */
        
        /* Sidebar toggle button */
        .sidebar-toggle {
            display: none;
            border: none;
            background: transparent;
            color: white;
            padding: 8px;
            margin-right: 10px;
        }
        
        .sidebar-toggle:focus {
            outline: none;
            box-shadow: none;
        }
        
        /* Tablet dan Desktop Kecil (max-width: 991px) */
        @media (max-width: 991px) {
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar {
                position: fixed;
                top: 56px;
                left: -250px;
                width: 250px;
                z-index: 1000;
                transition: left 0.2s ease;
                height: calc(100vh - 56px);
                overflow-y: auto;
                will-change: left;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .sidebar-overlay {
                display: none;
            }
            
            .card-header h5 {
                font-size: 1rem;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .btn-group {
                flex-wrap: wrap;
            }
            
            .btn-group .btn {
                margin-bottom: 5px;
            }
        }
        
        /* Tablet (max-width: 768px) */
        @media (max-width: 768px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .card {
                margin-bottom: 15px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .card-header {
                padding: 10px 15px;
            }
            
            .card-header .btn {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 8px 6px;
            }
            
            .btn {
                font-size: 12px;
                padding: 8px 12px;
            }
            
            .btn-sm {
                padding: 6px 10px;
                font-size: 11px;
            }
            
            .form-control,
            .form-select {
                font-size: 14px;
                padding: 8px 12px;
            }
            
            .navbar-brand {
                font-size: 14px;
            }
            
            .navbar-brand-app-name {
                font-size: 14px;
            }
            
            .navbar-brand-school-name {
                font-size: 11px;
            }
            
            .navbar-brand-academic-info {
                font-size: 10px;
            }
            
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-content {
                border-radius: 8px;
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 15px;
            }
            
            /* Tabel responsive dengan scroll horizontal */
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table-responsive table {
                width: 100%;
                max-width: 100%;
                margin-bottom: 0;
            }
            
            /* Form responsive */
            .row {
                margin-left: -10px;
                margin-right: -10px;
            }
            
            .row > * {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            /* Card columns untuk mobile */
            .card-columns {
                column-count: 1;
            }
        }
        
        /* Mobile (max-width: 576px) */
        @media (max-width: 576px) {
            body {
                font-size: 13px;
            }
            
            .container,
            .container-fluid {
                padding-left: 8px;
                padding-right: 8px;
            }
            
            .card {
                border-radius: 8px;
                margin-bottom: 10px;
            }
            
            .card-header {
                padding: 8px 12px;
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .card-header h5 {
                font-size: 0.95rem;
                margin-bottom: 8px;
            }
            
            .card-header .btn-group,
            .card-header .d-flex {
                width: 100%;
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .card-header .btn {
                flex: 1 1 auto;
                min-width: calc(50% - 5px);
                font-size: 11px;
                padding: 6px 8px;
            }
            
            .card-body {
                padding: 12px;
            }
            
            .table {
                font-size: 11px;
            }
            
            .table th,
            .table td {
                padding: 6px 4px;
                white-space: nowrap;
            }
            
            .table th {
                font-size: 11px;
            }
            
            .btn {
                font-size: 11px;
                padding: 6px 10px;
            }
            
            .btn-sm {
                padding: 4px 8px;
                font-size: 10px;
            }
            
            .btn i {
                font-size: 11px;
            }
            
            .form-label {
                font-size: 12px;
                margin-bottom: 5px;
            }
            
            .form-control,
            .form-select {
                font-size: 14px;
                padding: 8px 10px;
            }
            
            .input-group {
                flex-wrap: wrap;
            }
            
            .input-group .form-control,
            .input-group .btn {
                flex: 1 1 100%;
                margin-bottom: 5px;
            }
            
            .navbar {
                padding: 8px 10px;
            }
            
            .navbar-brand {
                font-size: 12px;
            }
            
            .navbar-brand img {
                height: 30px;
                margin-right: 8px;
            }
            
            .navbar-brand-app-name {
                font-size: 12px;
            }
            
            .navbar-brand-school-name {
                font-size: 10px;
            }
            
            .navbar-brand-academic-info {
                font-size: 9px;
            }
            
            .navbar-toggler {
                padding: 4px 8px;
                font-size: 14px;
            }
            
            .user-info {
                display: none;
            }
            
            .user-avatar-wrapper {
                width: 32px;
                height: 32px;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
            }
            
            .modal-dialog {
                margin: 5px;
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 12px;
            }
            
            .modal-title {
                font-size: 16px;
            }
            
            .alert {
                padding: 10px 12px;
                font-size: 12px;
            }
            
            .badge {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            .pagination {
                font-size: 12px;
            }
            
            .page-link {
                padding: 6px 10px;
                font-size: 12px;
            }
            
            .dropdown-menu {
                font-size: 12px;
            }
            
            .dropdown-item {
                padding: 8px 15px;
                font-size: 12px;
            }
            
            /* Tabel dengan scroll horizontal di mobile */
            .table-responsive {
                border: none;
            }
            
            .table-responsive table {
                min-width: 600px;
            }
            
            /* Form grid responsive */
            .row.g-3 > *,
            .row.g-2 > * {
                margin-bottom: 10px;
            }
            
            /* Card deck menjadi single column */
            .card-deck {
                display: flex;
                flex-direction: column;
            }
            
            .card-deck .card {
                margin-bottom: 15px;
            }
            
            /* Button group menjadi vertical di mobile */
            .btn-group-vertical {
                width: 100%;
            }
            
            .btn-group-vertical .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }
        
        /* Extra Small Mobile (max-width: 400px) */
        @media (max-width: 400px) {
            .card-header .btn {
                min-width: 100%;
                margin-bottom: 5px;
            }
            
            .table {
                font-size: 10px;
            }
            
            .table th,
            .table td {
                padding: 4px 3px;
            }
            
            .btn {
                font-size: 10px;
                padding: 5px 8px;
            }
        }
        
        /* Desktop Large (min-width: 1200px) */
        @media (min-width: 1200px) {
            .container {
                max-width: 1140px;
            }
            
            .card {
                margin-bottom: 20px;
            }
        }
        
        /* Print Media */
        @media print {
            .sidebar,
            .navbar,
            .btn,
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .card {
                border: none;
                box-shadow: none;
            }
        }
        
        /* Landscape Orientation */
        @media (orientation: landscape) and (max-height: 500px) {
            .navbar {
                padding: 5px 10px;
            }
            
            .card-header {
                padding: 8px 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <button class="navbar-toggler d-lg-none sidebar-toggle" type="button" aria-label="Toggle sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="<?php echo $basePath; ?>index.php">
                <img src="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo'] ?? 'logo.png'); ?>" alt="Logo" onerror="this.onerror=null; this.style.display='none';">
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
                        <div class="user-role">
                            <span class="user-role-text"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                        </div>
                    </div>
                </div>
                <div class="user-avatar-wrapper">
                    <?php 
                    $user_foto = $user['foto'] ?? '';
                    $user_role = $user['role'] ?? '';
                    $user_nama = $user['nama'] ?? 'User';
                    $foto_path = !empty($user_foto) ? __DIR__ . '/../uploads/' . $user_foto : '';
                    $foto_exists = !empty($user_foto) && file_exists($foto_path);
                    
                    // Admin tetap menggunakan logo madrasah
                    if ($user_role == 'proktor') {
                        // Admin tetap logo madrasah
                        $avatar_src = $basePath . 'uploads/' . htmlspecialchars($profil['logo'] ?? 'logo.png');
                        $avatar_alt = 'Logo Madrasah';
                        $onerror_handler = "this.onerror=null; this.style.display='none';";
                    } elseif ($foto_exists) {
                        // User dengan foto: gunakan foto user
                        $avatar_src = $basePath . 'uploads/' . htmlspecialchars($user_foto);
                        $avatar_alt = htmlspecialchars($user_nama);
                        // Jika foto gagal load, gunakan avatar dengan inisial
                        $inisial = strtoupper(substr($user_nama, 0, 1));
                        $avatar_fallback = 'data:image/svg+xml;base64,' . base64_encode('<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="20" fill="#2d5016"/><text x="20" y="20" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inisial) . '</text></svg>');
                        $onerror_handler = "this.onerror=null; this.src='" . $avatar_fallback . "';";
                    } else {
                        // User tanpa foto: gunakan avatar dengan inisial
                        $inisial = strtoupper(substr($user_nama, 0, 1));
                        $avatar_src = 'data:image/svg+xml;base64,' . base64_encode('<svg width="40" height="40" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="20" fill="#2d5016"/><text x="20" y="20" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inisial) . '</text></svg>');
                        $avatar_alt = htmlspecialchars($user_nama);
                        $onerror_handler = '';
                    }
                    ?>
                    <img src="<?php echo $avatar_src; ?>" alt="<?php echo $avatar_alt; ?>" class="user-avatar" id="userAvatarDropdown" onerror="<?php echo $onerror_handler; ?>">
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="#" class="user-dropdown-item logout" onclick="logout(); return false;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar p-0" id="sidebar">
                <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <nav class="nav flex-column mt-3">
                    <?php if ($user['role'] == 'proktor'): ?>
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php">
                            <i class="fas fa-home"></i> <span>Dashboard</span>
                        </a>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#lembagaMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-school"></i> <span>Lembaga</span> <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="lembagaMenu" data-bs-parent=".sidebar">
                        <a class="nav-link ps-5" href="<?php echo $basePath; ?>lembaga/profil.php">
                            <i class="fas fa-circle"></i> <span>Profil Madrasah</span>
                        </a>
                        <a class="nav-link ps-5" href="<?php echo $basePath; ?>lembaga/materi.php">
                            <i class="fas fa-circle"></i> <span>Materi Mulok</span>
                        </a>
                        <a class="nav-link ps-5" href="<?php echo $basePath; ?>lembaga/kelas.php">
                            <i class="fas fa-circle"></i> <span>Kelas</span>
                        </a>
                        </div>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#guruMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-chalkboard-teacher"></i> <span>Guru</span> <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="guruMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>guru/data.php">
                                <i class="fas fa-circle"></i> <span>Data Guru</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>guru/mengampu.php">
                                <i class="fas fa-circle"></i> <span>Mengampu Materi</span>
                            </a>
                        </div>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#siswaMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-user-graduate"></i> <span>Siswa</span> <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="siswaMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/index.php">
                                <i class="fas fa-circle"></i> <span>Data Siswa</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/pindah-kelas.php">
                                <i class="fas fa-circle"></i> <span>Pindah Kelas</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/naik-kelas.php">
                                <i class="fas fa-circle"></i> <span>Naik Kelas</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/alumni.php">
                                <i class="fas fa-circle"></i> <span>Data Alumni</span>
                            </a>
                        </div>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#raporMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-file-alt"></i> <span>Rapor</span> <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="raporMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/pengaturan-cetak.php">
                                <i class="fas fa-circle"></i> <span>Pengaturan Cetak</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/status-nilai.php">
                                <i class="fas fa-circle"></i> <span>Status Nilai</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>rapor/cetak.php">
                                <i class="fas fa-circle"></i> <span>Cetak Rapor</span>
                            </a>
                        </div>
                        <a class="nav-link" href="<?php echo $basePath; ?>pengguna/index.php">
                            <i class="fas fa-users"></i> <span>Pengguna</span>
                        </a>
                        <a class="nav-link" href="<?php echo $basePath; ?>pengaturan/index.php">
                            <i class="fas fa-cog"></i> <span>Pengaturan</span>
                        </a>
                        <a class="nav-link" href="<?php echo $basePath; ?>backup/index.php">
                            <i class="fas fa-database"></i> <span>Backup & Restore</span>
                        </a>
                    <?php elseif ($user['role'] == 'wali_kelas'): ?>
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php">
                            <i class="fas fa-home"></i> <span>Dashboard</span>
                        </a>
                        <?php if (!empty($materi_wali_kelas_by_kategori)): ?>
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#materiMenu" onclick="event.stopPropagation();">
                                <i class="fas fa-book"></i> <span>Materi Mulok</span> <i class="fas fa-chevron-down float-end"></i>
                            </a>
                            <div class="collapse" id="materiMenu" data-bs-parent=".sidebar">
                                <?php foreach ($materi_wali_kelas_by_kategori as $kategori => $materi_list): ?>
                                    <a class="nav-link ps-5" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#kategoriMenu<?php echo md5($kategori); ?>" onclick="event.stopPropagation();">
                                        <i class="fas fa-folder"></i> <span><?php echo htmlspecialchars($kategori); ?></span> <i class="fas fa-chevron-down float-end"></i>
                                    </a>
                                    <div class="collapse" id="kategoriMenu<?php echo md5($kategori); ?>">
                                        <?php foreach ($materi_list as $materi): ?>
                                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/materi.php?id=<?php echo $materi['id']; ?>" style="padding-left: 3rem !important;">
                                                <i class="fas fa-circle"></i> <span><?php echo htmlspecialchars($materi['nama_mulok']); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (!empty($materi_wali_kelas)): ?>
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#materiMenu" onclick="event.stopPropagation();">
                                <i class="fas fa-book"></i> <span>Materi Mulok</span> <i class="fas fa-chevron-down float-end"></i>
                            </a>
                            <div class="collapse" id="materiMenu" data-bs-parent=".sidebar">
                                <?php foreach ($materi_wali_kelas as $materi): ?>
                                    <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/materi.php?id=<?php echo $materi['id']; ?>">
                                        <i class="fas fa-circle"></i> <span><?php echo htmlspecialchars($materi['nama_mulok']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <a class="nav-link" href="<?php echo $basePath; ?>wali-kelas/materi.php">
                                <i class="fas fa-book"></i> <span>Materi Mulok</span>
                            </a>
                        <?php endif; ?>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#waliMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-user-tie"></i> <span>Wali Kelas</span> <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="waliMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/siswa.php">
                                <i class="fas fa-circle"></i> <span>Data Siswa</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/status-nilai.php">
                                <i class="fas fa-circle"></i> <span>Status Nilai</span>
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>wali-kelas/rapor.php">
                                <i class="fas fa-circle"></i> <span>Rapor</span>
                            </a>
                        </div>
                    <?php elseif ($user['role'] == 'guru'): ?>
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php">
                            <i class="fas fa-home"></i> <span>Dashboard</span>
                        </a>
                        <?php if (!empty($materi_guru_by_kategori)): ?>
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#materiMenuGuru" onclick="event.stopPropagation();">
                                <i class="fas fa-book"></i> <span>Materi Mulok</span> <i class="fas fa-chevron-down float-end"></i>
                            </a>
                            <div class="collapse" id="materiMenuGuru" data-bs-parent=".sidebar">
                                <?php foreach ($materi_guru_by_kategori as $kategori => $materi_list): ?>
                                    <a class="nav-link ps-5" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#kategoriMenuGuru<?php echo md5($kategori); ?>" onclick="event.stopPropagation();">
                                        <i class="fas fa-folder"></i> <span><?php echo htmlspecialchars($kategori); ?></span> <i class="fas fa-chevron-down float-end"></i>
                                    </a>
                                    <div class="collapse" id="kategoriMenuGuru<?php echo md5($kategori); ?>">
                                        <?php foreach ($materi_list as $materi): ?>
                                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>guru/penilaian.php?materi_id=<?php echo $materi['id']; ?>" style="padding-left: 3rem !important;">
                                                <i class="fas fa-circle"></i> <span><?php echo htmlspecialchars($materi['nama_mulok']); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (!empty($materi_guru)): ?>
                            <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#materiMenuGuru" onclick="event.stopPropagation();">
                                <i class="fas fa-book"></i> <span>Materi Mulok</span> <i class="fas fa-chevron-down float-end"></i>
                            </a>
                            <div class="collapse" id="materiMenuGuru" data-bs-parent=".sidebar">
                                <?php foreach ($materi_guru as $materi): ?>
                                    <a class="nav-link ps-5" href="<?php echo $basePath; ?>guru/penilaian.php?materi_id=<?php echo $materi['id']; ?>">
                                        <i class="fas fa-circle"></i> <span><?php echo htmlspecialchars($materi['nama_mulok']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <a class="nav-link" href="<?php echo $basePath; ?>guru/materi-diampu.php">
                                <i class="fas fa-book"></i> <span>Materi yang Diampu</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a class="nav-link text-danger" href="<?php echo $basePath; ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </nav>
            </div>
            <div class="col-md-9 col-lg-10 content-wrapper">

