<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';

// Ambil data guru
$query = "SELECT p.*, 
          (SELECT nama_kelas FROM kelas WHERE wali_kelas_id = p.id LIMIT 1) as wali_kelas_nama
          FROM pengguna p 
          WHERE p.role IN ('guru', 'wali_kelas')
          ORDER BY p.nama";
$result = $conn->query($query);

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="data_guru_' . date('Y-m-d') . '.xls"');
    
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
    
    echo '<h2 style="text-align: center;">DATA GURU</h2>';
    echo '<p style="text-align: center;">Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    echo '<br>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Nama</th>';
    echo '<th>Jenis Kelamin</th>';
    echo '<th>Tempat Lahir</th>';
    echo '<th>Tanggal Lahir</th>';
    echo '<th>Pendidikan</th>';
    echo '<th>NUPTK</th>';
    echo '<th>Password</th>';
    echo '<th>Wali Kelas</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        // Ambil password dari password_plain, atau cek apakah default
        $password_plain = $row['password_plain'] ?? '';
        $password_hash = $row['password'] ?? '';
        
        // Jika password_plain kosong, cek apakah password masih default
        if (empty($password_plain) && !empty($password_hash)) {
            if (password_verify('123456', $password_hash)) {
                $password_plain = '123456';
            } else {
                $password_plain = 'Tidak Diketahui';
            }
        } elseif (empty($password_plain)) {
            $password_plain = 'Tidak Diketahui';
        }
        
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nama'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan') . '</td>';
        echo '<td>' . htmlspecialchars($row['tempat_lahir'] ?? '-') . '</td>';
        echo '<td>' . (!empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['pendidikan'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['nuptk'] ?? $row['username'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($password_plain) . '</td>';
        echo '<td>' . htmlspecialchars($row['wali_kelas_nama'] ?? '-') . '</td>';
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
    echo '<title>Data Guru</title>';
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
    
    echo '<h2>DATA GURU</h2>';
    echo '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
    
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>No</th>';
    echo '<th>Nama</th>';
    echo '<th>Jenis Kelamin</th>';
    echo '<th>Tempat Lahir</th>';
    echo '<th>Tanggal Lahir</th>';
    echo '<th>Pendidikan</th>';
    echo '<th>NUPTK</th>';
    echo '<th>Password</th>';
    echo '<th>Wali Kelas</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $result->data_seek(0);
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        // Ambil password dari password_plain, atau cek apakah default
        $password_plain = $row['password_plain'] ?? '';
        $password_hash = $row['password'] ?? '';
        
        // Jika password_plain kosong, cek apakah password masih default
        if (empty($password_plain) && !empty($password_hash)) {
            if (password_verify('123456', $password_hash)) {
                $password_plain = '123456';
            } else {
                $password_plain = 'Tidak Diketahui';
            }
        } elseif (empty($password_plain)) {
            $password_plain = 'Tidak Diketahui';
        }
        
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nama'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan') . '</td>';
        echo '<td>' . htmlspecialchars($row['tempat_lahir'] ?? '-') . '</td>';
        echo '<td>' . (!empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['pendidikan'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['nuptk'] ?? $row['username'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($password_plain) . '</td>';
        echo '<td>' . htmlspecialchars($row['wali_kelas_nama'] ?? '-') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}
?>

