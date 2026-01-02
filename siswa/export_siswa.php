<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;

// Validasi kelas
if ($kelas_id <= 0) {
    die('Kelas tidak valid');
}

// Ambil data kelas
$kelas_data = null;
$stmt_kelas = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt_kelas->bind_param("i", $kelas_id);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
$kelas_data = $result_kelas->fetch_assoc();
$stmt_kelas->close();

// Ambil data siswa
$query = "SELECT s.*, k.nama_kelas
          FROM siswa s
          LEFT JOIN kelas k ON s.kelas_id = k.id
          WHERE s.kelas_id = ?
          ORDER BY s.nama";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $kelas_id);
$stmt->execute();
$result = $stmt->get_result();

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="data_siswa_' . htmlspecialchars($kelas_data['nama_kelas'] ?? '') . '_' . date('Y-m-d') . '.xls"');
    
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
    
    echo '<h2 style="text-align: center;">DATA SISWA</h2>';
    echo '<h3 style="text-align: center;">Kelas: ' . htmlspecialchars($kelas_data['nama_kelas'] ?? '-') . '</h3>';
    echo '<p style="text-align: center;">Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>NISN</th>';
    echo '<th>Nama</th>';
    echo '<th>Jenis Kelamin</th>';
    echo '<th>Tempat Lahir</th>';
    echo '<th>Tanggal Lahir</th>';
    echo '<th>Orangtua/Wali</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nisn'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['nama'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan') . '</td>';
        echo '<td>' . htmlspecialchars($row['tempat_lahir'] ?? '-') . '</td>';
        echo '<td>' . (!empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['orangtua_wali'] ?? '-') . '</td>';
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
    echo '<title>Data Siswa</title>';
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
    echo 'h3 { text-align: center; margin: 5px 0; font-size: 12pt; }';
    echo 'p { text-align: center; margin: 5px 0; font-size: 10pt; }';
    echo '</style>';
    echo '<script>';
    echo 'window.onload = function() { window.print(); };';
    echo '</script>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>DATA SISWA</h2>';
    echo '<h3>Kelas: ' . htmlspecialchars($kelas_data['nama_kelas'] ?? '-') . '</h3>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>NISN</th>';
    echo '<th>Nama</th>';
    echo '<th>Jenis Kelamin</th>';
    echo '<th>Tempat Lahir</th>';
    echo '<th>Tanggal Lahir</th>';
    echo '<th>Orangtua/Wali</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $result->data_seek(0);
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nisn'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['nama'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan') . '</td>';
        echo '<td>' . htmlspecialchars($row['tempat_lahir'] ?? '-') . '</td>';
        echo '<td>' . (!empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['orangtua_wali'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

$stmt->close();
$conn->close();
?>

