<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';

// Cek kolom yang ada
$has_kategori_mulok = false;
$has_kode_mulok = false;
$has_kelas_id = false;
$has_semester = false;
$has_jumlah_jam = false;

try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kategori_mulok') $has_kategori_mulok = true;
            if ($col['Field'] == 'kode_mulok') $has_kode_mulok = true;
            if ($col['Field'] == 'kelas_id') $has_kelas_id = true;
            if ($col['Field'] == 'semester') $has_semester = true;
            if ($col['Field'] == 'jumlah_jam') $has_jumlah_jam = true;
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Tentukan kolom untuk ORDER BY
$order_by = $has_kategori_mulok ? 'kategori_mulok' : ($has_kode_mulok ? 'kode_mulok' : 'id');
$kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
$use_kelas_semester = ($has_kelas_id && $has_semester);

// Ambil parameter filter
$filter_kategori = isset($_GET['filter_kategori']) ? trim($_GET['filter_kategori']) : '';
$filter_nama = isset($_GET['filter_nama']) ? trim($_GET['filter_nama']) : '';
$filter_kelas = isset($_GET['filter_kelas']) ? intval($_GET['filter_kelas']) : 0;
$filter_semester = isset($_GET['filter_semester']) ? trim($_GET['filter_semester']) : '';

// Build query dengan filter
$params = [];
$types = '';

if ($use_kelas_semester) {
    $query = "SELECT m.*, k.nama_kelas 
              FROM materi_mulok m 
              LEFT JOIN kelas k ON m.kelas_id = k.id 
              WHERE 1=1";
    
    // Filter kategori/kode
    if (!empty($filter_kategori)) {
        $query .= " AND m.$kolom_kategori LIKE ?";
        $params[] = '%' . $filter_kategori . '%';
        $types .= 's';
    }
    
    // Filter nama mulok
    if (!empty($filter_nama)) {
        $query .= " AND m.nama_mulok LIKE ?";
        $params[] = '%' . $filter_nama . '%';
        $types .= 's';
    }
    
    // Filter kelas
    if ($filter_kelas > 0) {
        $query .= " AND m.kelas_id = ?";
        $params[] = $filter_kelas;
        $types .= 'i';
    }
    
    // Filter semester
    if (!empty($filter_semester)) {
        $query .= " AND m.semester = ?";
        $params[] = $filter_semester;
        $types .= 's';
    }
    
    $query .= " ORDER BY m.$order_by ASC";
} else {
    $query = "SELECT * FROM materi_mulok WHERE 1=1";
    
    // Filter kategori/kode
    if (!empty($filter_kategori)) {
        $query .= " AND $kolom_kategori LIKE ?";
        $params[] = '%' . $filter_kategori . '%';
        $types .= 's';
    }
    
    // Filter nama mulok
    if (!empty($filter_nama)) {
        $query .= " AND nama_mulok LIKE ?";
        $params[] = '%' . $filter_nama . '%';
        $types .= 's';
    }
    
    $query .= " ORDER BY $order_by ASC";
}

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="materi_mulok_' . date('Y-m-d') . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 8px; text-align: left; }';
    echo 'th { background-color: #2d5016; color: white; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2 style="text-align: center;">DATA MATERI MULOK</h2>';
    echo '<p style="text-align: center;">Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>' . ($has_kategori_mulok ? 'Kategori' : 'Kode') . ' Mulok</th>';
    echo '<th>Nama Mulok</th>';
    if ($use_kelas_semester) {
        echo '<th>Kelas</th>';
        echo '<th>Semester</th>';
    } elseif ($has_jumlah_jam) {
        echo '<th>Jumlah Jam</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row[$kolom_kategori] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['nama_mulok'] ?? '') . '</td>';
        if ($use_kelas_semester) {
            echo '<td>' . htmlspecialchars($row['nama_kelas'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($row['semester'] ?? '-') . '</td>';
        } elseif ($has_jumlah_jam) {
            echo '<td>' . htmlspecialchars($row['jumlah_jam'] ?? '0') . ' Jam</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
} else {
    // PDF Export
    header('Content-Type: text/html; charset=UTF-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Data Materi Mulok</title>';
    echo '<style>';
    echo '@media print {';
    echo '  @page { size: A4; margin: 1cm; }';
    echo '  body { margin: 0; padding: 10px; }';
    echo '}';
    echo 'body { font-family: Arial, sans-serif; font-size: 9pt; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 10px; }';
    echo 'th, td { border: 1px solid #000; padding: 6px; text-align: left; font-size: 8pt; }';
    echo 'th { background-color: #2d5016; color: white; font-weight: bold; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo 'h2 { text-align: center; margin: 5px 0; font-size: 14pt; }';
    echo 'p { text-align: center; margin: 5px 0; font-size: 10pt; }';
    echo '</style>';
    echo '<script>';
    echo 'window.onload = function() { window.print(); };';
    echo '</script>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>DATA MATERI MULOK</h2>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>' . ($has_kategori_mulok ? 'Kategori' : 'Kode') . ' Mulok</th>';
    echo '<th>Nama Mulok</th>';
    if ($use_kelas_semester) {
        echo '<th>Kelas</th>';
        echo '<th>Semester</th>';
    } elseif ($has_jumlah_jam) {
        echo '<th>Jumlah Jam</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $result->data_seek(0);
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row[$kolom_kategori] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['nama_mulok'] ?? '') . '</td>';
        if ($use_kelas_semester) {
            echo '<td>' . htmlspecialchars($row['nama_kelas'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($row['semester'] ?? '-') . '</td>';
        } elseif ($has_jumlah_jam) {
            echo '<td>' . htmlspecialchars($row['jumlah_jam'] ?? '0') . ' Jam</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

$conn->close();
?>


