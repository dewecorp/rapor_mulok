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
    
    // Ambil foto user, jika tidak ada atau default, gunakan avatar
    $user_foto = $user['foto'] ?? null;
    $user_avatar = '';
    if (empty($user_foto) || $user_foto == 'default.png' || !file_exists(__DIR__ . '/../uploads/' . $user_foto)) {
        // Gunakan avatar dengan inisial nama
        $nama = $user['nama'] ?? 'User';
        $inisial = strtoupper(substr($nama, 0, 1));
        $user_avatar = 'avatar'; // Flag untuk menggunakan avatar
    } else {
        $user_avatar = $user_foto;
    }
} catch (Exception $e) {
    $user = null;
    $user_avatar = 'avatar';
}

// Ambil materi mulok yang diampu oleh wali kelas (jika role adalah wali_kelas)
$materi_diampu_wali = [];
if ($user && $user['role'] == 'wali_kelas') {
    try {
        // Cek kolom kategori
        $use_kategori = false;
        $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
        $use_kategori = ($check_column && $check_column->num_rows > 0);
        $kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
        
        // Cek kolom kelas_id
        $has_kelas_id = false;
        $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
        $has_kelas_id = ($check_kelas_id && $check_kelas_id->num_rows > 0);
        
        // Cek kolom semester
        $has_semester = false;
        try {
            $check_semester = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'semester'");
            $has_semester = ($check_semester && $check_semester->num_rows > 0);
        } catch (Exception $e) {
            $has_semester = false;
        }
        
        // Ambil semester aktif dari profil
        $semester_aktif = '1';
        try {
            $stmt_profil = $conn->query("SELECT semester_aktif FROM profil_madrasah LIMIT 1");
            if ($stmt_profil && $stmt_profil->num_rows > 0) {
                $profil_data = $stmt_profil->fetch_assoc();
                $semester_aktif = $profil_data['semester_aktif'] ?? '1';
            }
        } catch (Exception $e) {
            // Use default
        }
        
        // Ambil semua kombinasi materi-kelas dengan GROUP BY untuk menghindari duplikasi
        if ($has_kelas_id) {
            if ($has_semester) {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ? AND m.semester = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            } else {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            }
        } else {
            if ($has_semester) {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ? AND m.semester = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            } else {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            }
        }
        $stmt_materi = $conn->prepare($query_materi);
        if ($has_semester) {
            $stmt_materi->bind_param("is", $user_id, $semester_aktif);
        } else {
            $stmt_materi->bind_param("i", $user_id);
        }
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        if ($result_materi) {
            while ($row = $result_materi->fetch_assoc()) {
                $materi_diampu_wali[] = $row;
            }
        }
    } catch (Exception $e) {
        $materi_diampu_wali = [];
    }
}

// Ambil materi mulok yang diampu oleh guru (jika role adalah guru)
$materi_diampu_guru = [];
if ($user && $user['role'] == 'guru') {
    try {
        // Cek kolom kategori
        $use_kategori = false;
        $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
        $use_kategori = ($check_column && $check_column->num_rows > 0);
        $kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
        
        // Cek kolom kelas_id
        $has_kelas_id = false;
        $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
        $has_kelas_id = ($check_kelas_id && $check_kelas_id->num_rows > 0);
        
        // Cek kolom semester
        $has_semester = false;
        try {
            $check_semester = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'semester'");
            $has_semester = ($check_semester && $check_semester->num_rows > 0);
        } catch (Exception $e) {
            $has_semester = false;
        }
        
        // Ambil semester aktif dari profil
        $semester_aktif = '1';
        try {
            $stmt_profil = $conn->query("SELECT semester_aktif FROM profil_madrasah LIMIT 1");
            if ($stmt_profil && $stmt_profil->num_rows > 0) {
                $profil_data = $stmt_profil->fetch_assoc();
                $semester_aktif = $profil_data['semester_aktif'] ?? '1';
            }
        } catch (Exception $e) {
            // Use default
        }
        
        // Ambil semua kombinasi materi-kelas dengan GROUP BY untuk menghindari duplikasi
        if ($has_kelas_id) {
            if ($has_semester) {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ? AND m.semester = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            } else {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            }
        } else {
            if ($has_semester) {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ? AND m.semester = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            } else {
                $query_materi = "SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori, k.nama_kelas, mm.kelas_id
                                FROM mengampu_materi mm
                                INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                INNER JOIN kelas k ON mm.kelas_id = k.id
                                WHERE mm.guru_id = ?
                                GROUP BY m.id, mm.kelas_id, k.nama_kelas
                                ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
            }
        }
        $stmt_materi = $conn->prepare($query_materi);
        if ($has_semester) {
            $stmt_materi->bind_param("is", $user_id, $semester_aktif);
        } else {
            $stmt_materi->bind_param("i", $user_id);
        }
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        if ($result_materi) {
            while ($row = $result_materi->fetch_assoc()) {
                $materi_diampu_guru[] = $row;
            }
        }
    } catch (Exception $e) {
        $materi_diampu_guru = [];
    }
}

// Ambil profil madrasah untuk logo
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
}

// Fungsi untuk mendapatkan path relatif ke root aplikasi secara konsisten
// Menggunakan session untuk menyimpan base path agar konsisten
function getBasePath() {
    // Gunakan fungsi getRelativePath() dari config.php untuk konsistensi
    return getRelativePath();
}
$basePath = getBasePath();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME . ' - ' . APP_SHORT; ?></title>
    
    <!-- Favicon menggunakan logo sekolah -->
    <?php if (!empty($profil['logo'])): ?>
        <link rel="icon" type="image/png" href="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
        <link rel="shortcut icon" type="image/png" href="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
        <link rel="apple-touch-icon" href="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($profil['logo']); ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?php echo $basePath; ?>uploads/logo.png">
        <link rel="shortcut icon" type="image/png" href="<?php echo $basePath; ?>uploads/logo.png">
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
    <!-- Select2 -->
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
            gap: 12px;
            min-width: 200px;
            position: relative;
            z-index: 1000;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: none;
            flex-shrink: 0;
            pointer-events: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: block;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .user-avatar[style*="display: flex"] {
            display: flex !important;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-avatar-dropdown button {
            cursor: pointer !important;
            pointer-events: auto !important;
        }
        
        #userDropdownBtn {
            cursor: pointer !important;
            pointer-events: auto !important;
        }
        
        .user-avatar-dropdown button:hover .user-avatar {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        
        .user-avatar-dropdown button:hover {
            cursor: pointer !important;
        }
        
        .user-avatar-dropdown {
            position: relative;
        }
        
        .user-avatar-dropdown .btn,
        .user-avatar-dropdown button,
        #userDropdownBtn,
        button#userDropdownBtn {
            background: transparent !important;
            border: 2px solid white !important;
            border-radius: 50% !important;
            padding: 0 !important;
            box-shadow: none !important;
            cursor: pointer !important;
            display: inline-block !important;
            position: relative !important;
            z-index: 1000 !important;
            pointer-events: auto !important;
            overflow: hidden !important;
        }
        
        .user-avatar-dropdown .btn:hover,
        .user-avatar-dropdown button:hover,
        #userDropdownBtn:hover,
        button#userDropdownBtn:hover {
            background: transparent !important;
            opacity: 0.9;
            cursor: pointer !important;
        }
        
        .user-avatar-dropdown .btn:focus,
        .user-avatar-dropdown button:focus,
        #userDropdownBtn:focus,
        button#userDropdownBtn:focus {
            box-shadow: none !important;
            outline: 2px solid rgba(255, 255, 255, 0.5) !important;
            outline-offset: 2px !important;
            cursor: pointer !important;
        }
        
        .user-avatar-dropdown .btn:active,
        .user-avatar-dropdown button:active,
        #userDropdownBtn:active,
        button#userDropdownBtn:active {
            background: transparent !important;
            border: none !important;
            opacity: 0.8;
            cursor: pointer !important;
        }
        
        #userDropdownBtn {
            cursor: pointer !important;
            pointer-events: auto !important;
        }
        
        /* Force cursor pointer untuk semua elemen di dalam dropdown */
        .user-avatar-dropdown * {
            cursor: pointer !important;
        }
        
        .user-avatar-dropdown img {
            cursor: pointer !important;
            pointer-events: none !important;
        }
        
        .dropdown-menu-user {
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: 1px solid #dee2e6;
            margin-top: 10px;
            z-index: 1050;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
        }
        
        .dropdown-menu-user.show {
            display: block !important;
        }
        
        .dropdown-menu-user .dropdown-header {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .dropdown-menu-user .dropdown-header .fw-bold {
            font-size: 14px;
            color: #2d5016;
        }
        
        .dropdown-menu-user .dropdown-header small {
            font-size: 12px;
            color: #6c757d;
        }
        
        .dropdown-menu-user .dropdown-item {
            padding: 10px 15px;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }
        
        .dropdown-menu-user .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-menu-user .dropdown-item.logout-item {
            color: #dc3545;
        }
        
        .dropdown-menu-user .dropdown-item.logout-item:hover {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .dropdown-menu-user .dropdown-item i {
            width: 20px;
            margin-right: 8px;
        }
        
        .user-details {
            color: white;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 0;
            align-items: flex-end;
        }
        
        .user-details .user-name {
            font-weight: 600;
            font-size: 14px;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: right;
        }
        
        .user-details .user-role {
            font-size: 13px;
            opacity: 0.9;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            white-space: nowrap;
            text-align: right;
        }
        
        .user-role-text {
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        
        .datetime-info {
            color: white;
            text-align: center;
            font-size: 14px;
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
            overflow-x: hidden;
        }
        
        .sidebar .nav-link {
            min-width: 0;
            color: #2d5016;
            padding: 10px 20px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 1px 8px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
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
        
        /* Force cursor pointer - CSS paling akhir untuk override semua */
        button#userDropdownBtn,
        #userDropdownBtn.btn,
        #userDropdownBtn.btn-link,
        .user-avatar-dropdown > button,
        .user-info .dropdown button,
        .user-info button {
            cursor: pointer !important;
            pointer-events: auto !important;
        }
    </style>
    <script>
        // Set cursor pointer secara langsung via JavaScript sebelum DOM ready
        (function() {
            var style = document.createElement('style');
            style.textContent = '#userDropdownBtn { cursor: pointer !important; pointer-events: auto !important; }';
            document.head.appendChild(style);
        })();
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
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
                    <div class="dropdown user-avatar-dropdown">
                        <button type="button" class="btn btn-link p-0 border-0" id="userDropdownBtn" onclick="toggleUserDropdown(event)" title="Klik untuk logout" style="background: transparent !important; border: 2px solid white !important; border-radius: 50% !important; padding: 0 !important; line-height: 1; cursor: pointer !important; display: inline-block !important; position: relative; z-index: 1000; width: 40px; height: 40px; min-width: 40px; min-height: 40px; overflow: hidden;">
                            <?php if ($user_avatar == 'avatar' || empty($user_avatar)): ?>
                                <!-- Avatar dengan inisial -->
                                <div class="user-avatar" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; font-size: 16px; width: 100%; height: 100%; border-radius: 50%;">
                                    <?php echo htmlspecialchars(strtoupper(substr($user['nama'] ?? 'U', 0, 1))); ?>
                                </div>
                            <?php else: ?>
                                <!-- Foto user -->
                                <img src="<?php echo $basePath; ?>uploads/<?php echo htmlspecialchars($user_avatar); ?>" alt="User Avatar" class="user-avatar" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';" style="pointer-events: none; width: 100%; height: 100%; object-fit: cover;">
                                <div class="user-avatar" style="display: none; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; font-size: 16px; width: 100%; height: 100%; border-radius: 50%;">
                                    <?php echo htmlspecialchars(strtoupper(substr($user['nama'] ?? 'U', 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-user" id="userDropdownMenu" style="display: none;">
                            <li>
                                <h6 class="dropdown-header">
                                    <div class="fw-bold"><?php echo htmlspecialchars($user['nama']); ?></div>
                                    <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></small>
                                </h6>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item logout-item" href="<?php echo $basePath; ?>logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar p-0" style="max-width: 220px;">
                <nav class="nav flex-column mt-3">
                    <?php if ($user['role'] == 'proktor'): ?>
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php">
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
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#siswaMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-user-graduate"></i> Siswa <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="siswaMenu" data-bs-parent=".sidebar">
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/index.php">
                                <i class="fas fa-circle"></i> Data Siswa
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/pindah-kelas.php">
                                <i class="fas fa-circle"></i> Pindah Kelas
                            </a>
                            <a class="nav-link ps-5" href="<?php echo $basePath; ?>siswa/naik-kelas.php">
                                <i class="fas fa-circle"></i> Naik Kelas
                            </a>
                        </div>
                        <a class="nav-link" href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#raporMenu" onclick="event.stopPropagation();">
                            <i class="fas fa-file-alt"></i> Rapor <i class="fas fa-chevron-down float-end"></i>
                        </a>
                        <div class="collapse" id="raporMenu" data-bs-parent=".sidebar">
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
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <?php if (count($materi_diampu_wali) > 0): ?>
                            <?php 
                            // Group by kombinasi materi_id dan kelas_id untuk menghindari duplikasi
                            $materi_grouped = [];
                            foreach ($materi_diampu_wali as $materi) {
                                // Gunakan kombinasi materi_id dan kelas_id sebagai key
                                $key = intval($materi['id']) . '_' . intval($materi['kelas_id'] ?? 0);
                                if (!isset($materi_grouped[$key])) {
                                    $materi_grouped[$key] = $materi;
                                }
                            }
                            // Sort by kelas, then by nama materi
                            usort($materi_grouped, function($a, $b) {
                                $kelas_a = $a['nama_kelas'] ?? '';
                                $kelas_b = $b['nama_kelas'] ?? '';
                                if ($kelas_a != $kelas_b) {
                                    return strcmp($kelas_a, $kelas_b);
                                }
                                return strcmp($a['nama_mulok'] ?? '', $b['nama_mulok'] ?? '');
                            });
                            foreach ($materi_grouped as $materi): 
                                $materi_id_safe = htmlspecialchars($materi['id']);
                                $materi_nama_safe = htmlspecialchars($materi['nama_mulok']);
                                $kelas_nama_safe = htmlspecialchars($materi['nama_kelas'] ?? '');
                            ?>
                                <a class="nav-link" href="<?php echo $basePath; ?>wali-kelas/materi.php?materi_id=<?php echo $materi_id_safe; ?>&kelas_nama=<?php echo urlencode($kelas_nama_safe); ?>" style="font-size: 0.85rem;">
                                    <i class="fas fa-book"></i> <span style="font-size: 0.9em;"><?php echo $materi_nama_safe; ?><?php if ($kelas_nama_safe): ?><span class="badge bg-info ms-1" style="font-size: 0.7em; padding: 0.2em 0.5em; vertical-align: middle; font-weight: 600;"><?php echo $kelas_nama_safe; ?></span><?php endif; ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a class="nav-link" href="<?php echo $basePath; ?>wali-kelas/materi.php">
                                <i class="fas fa-book"></i> Materi Mulok
                            </a>
                        <?php endif; ?>
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
                        <a class="nav-link" href="<?php echo $basePath; ?>index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <?php if (count($materi_diampu_guru) > 0): ?>
                            <?php 
                            // Group by kombinasi materi_id dan kelas_id untuk menghindari duplikasi
                            $materi_grouped_guru = [];
                            foreach ($materi_diampu_guru as $materi) {
                                // Gunakan kombinasi materi_id dan kelas_id sebagai key
                                $key = intval($materi['id']) . '_' . intval($materi['kelas_id'] ?? 0);
                                if (!isset($materi_grouped_guru[$key])) {
                                    $materi_grouped_guru[$key] = $materi;
                                }
                            }
                            // Sort by kelas, then by nama materi
                            usort($materi_grouped_guru, function($a, $b) {
                                $kelas_a = $a['nama_kelas'] ?? '';
                                $kelas_b = $b['nama_kelas'] ?? '';
                                if ($kelas_a != $kelas_b) {
                                    return strcmp($kelas_a, $kelas_b);
                                }
                                return strcmp($a['nama_mulok'] ?? '', $b['nama_mulok'] ?? '');
                            });
                            foreach ($materi_grouped_guru as $materi): 
                                $materi_id_safe = htmlspecialchars($materi['id']);
                                $materi_nama_safe = htmlspecialchars($materi['nama_mulok']);
                                $kelas_nama_safe = htmlspecialchars($materi['nama_kelas'] ?? '');
                            ?>
                                <a class="nav-link" href="<?php echo $basePath; ?>guru/materi.php?materi_id=<?php echo $materi_id_safe; ?>&kelas_nama=<?php echo urlencode($kelas_nama_safe); ?>" style="font-size: 0.85rem;">
                                    <i class="fas fa-book"></i> <span style="font-size: 0.9em;"><?php echo $materi_nama_safe; ?><?php if ($kelas_nama_safe): ?><span class="badge bg-info ms-1" style="font-size: 0.7em; padding: 0.2em 0.5em; vertical-align: middle; font-weight: 600;"><?php echo $kelas_nama_safe; ?></span><?php endif; ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a class="nav-link" href="<?php echo $basePath; ?>guru/materi-diampu.php">
                                <i class="fas fa-book"></i> Materi yang Diampu
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a class="nav-link text-danger" href="<?php echo $basePath; ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            <div class="col-md-9 col-lg-10 content-wrapper">
    <script>
        // Fungsi untuk toggle dropdown user avatar
        function toggleUserDropdown(event) {
            event.stopPropagation();
            event.preventDefault();
            
            var dropdownMenu = document.getElementById('userDropdownMenu');
            if (dropdownMenu) {
                if (dropdownMenu.style.display === 'none' || dropdownMenu.style.display === '') {
                    dropdownMenu.style.display = 'block';
                    dropdownMenu.classList.add('show');
                } else {
                    dropdownMenu.style.display = 'none';
                    dropdownMenu.classList.remove('show');
                }
            }
        }
        
        // Tutup dropdown saat klik di luar
        document.addEventListener('click', function(e) {
            var dropdownBtn = document.getElementById('userDropdownBtn');
            var dropdownMenu = document.getElementById('userDropdownMenu');
            
            if (dropdownBtn && dropdownMenu) {
                if (!dropdownBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                    dropdownMenu.classList.remove('show');
                }
            }
        });
    </script>

