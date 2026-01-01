<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (!hasRole('proktor') && !hasRole('wali_kelas')) {
    header('Location: /index.php');
    exit();
}

$conn = getConnection();

// Ambil parameter
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

if ($kelas_id <= 0) {
    die('Kelas tidak valid');
}

// Ambil data profil madrasah
$profil_madrasah = null;
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    if ($result_profil && $result_profil->num_rows > 0) {
        $profil_madrasah = $result_profil->fetch_assoc();
    }
} catch (Exception $e) {
    // Handle error
}

$semester_aktif = $profil_madrasah['semester_aktif'] ?? '1';
$tahun_ajaran = $profil_madrasah['tahun_ajaran_aktif'] ?? '';

// Ambil data kelas
$kelas_data = null;
if ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $kelas_data = $result->fetch_assoc();
    $stmt->close();
}

// Ambil data siswa
$siswa_list = [];
if ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT s.* FROM siswa s WHERE s.kelas_id = ? ORDER BY s.nama");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $nama_siswa = trim($row['nama'] ?? '');
        if (!empty($nama_siswa)) {
            $nama_lower = strtolower($nama_siswa);
            if ($nama_lower !== 'administrator' && $nama_lower !== 'admin' && $nama_lower !== 'proktor') {
                $siswa_list[] = $row;
            }
        }
    }
    $stmt->close();
}

// Cek apakah kolom kategori_mulok ada
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

// Ambil semua materi untuk kelas dan semester ini
$materi_list = [];
if ($kelas_id > 0) {
    $materi_query = "SELECT m.* FROM materi_mulok m WHERE m.kelas_id = ? AND m.semester = ? ORDER BY ";
    if ($has_kategori_mulok) {
        $materi_query .= "m.kategori_mulok, ";
    }
    $materi_query .= "m.nama_mulok";
    
    $stmt_materi = $conn->prepare($materi_query);
    $stmt_materi->bind_param("is", $kelas_id, $semester_aktif);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();
    
    while ($materi = $result_materi->fetch_assoc()) {
        $materi_list[] = $materi;
    }
    $stmt_materi->close();
}

// Ambil semua nilai untuk siswa di kelas ini
$nilai_data = [];
if (!empty($siswa_list) && !empty($materi_list)) {
    $siswa_ids = array_column($siswa_list, 'id');
    $materi_ids = array_column($materi_list, 'id');
    
    if (!empty($siswa_ids) && !empty($materi_ids)) {
        $placeholders_siswa = implode(',', array_fill(0, count($siswa_ids), '?'));
        $placeholders_materi = implode(',', array_fill(0, count($materi_ids), '?'));
        
        $query_nilai = "SELECT * FROM nilai_siswa WHERE siswa_id IN ($placeholders_siswa) AND materi_mulok_id IN ($placeholders_materi) AND semester = ? AND tahun_ajaran = ?";
        $stmt_nilai = $conn->prepare($query_nilai);
        
        $params = array_merge($siswa_ids, $materi_ids);
        $params[] = $semester_aktif;
        $params[] = $tahun_ajaran;
        
        $types = str_repeat('i', count($siswa_ids)) . str_repeat('i', count($materi_ids)) . 'ss';
        $stmt_nilai->bind_param($types, ...$params);
        $stmt_nilai->execute();
        $result_nilai = $stmt_nilai->get_result();
        
        while ($nilai = $result_nilai->fetch_assoc()) {
            $key = $nilai['siswa_id'] . '_' . $nilai['materi_mulok_id'];
            $nilai_data[$key] = $nilai;
        }
        $stmt_nilai->close();
    }
}

$conn->close();

// Konversi semester
function getSemesterText($semester) {
    return ($semester == '1') ? 'Gasal' : 'Genap';
}

// Export Excel
if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="legger_' . htmlspecialchars($kelas_data['nama_kelas'] ?? '') . '_' . date('Y-m-d') . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #000; padding: 5px; text-align: center; }';
    echo 'th { background-color: #f0f0f0; font-weight: bold; }';
    echo '.text-left { text-align: left; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Header
    echo '<h2 style="text-align: center;">LEGGER NILAI MULOK KHUSUS</h2>';
    echo '<h3 style="text-align: center;">' . htmlspecialchars($profil_madrasah['nama_madrasah'] ?? 'MI SULTAN FATTAH SUKOSONO') . '</h3>';
    echo '<p style="text-align: center;">Kelas: ' . htmlspecialchars($kelas_data['nama_kelas'] ?? '-') . ' | Semester: ' . getSemesterText($semester_aktif) . ' | Tahun Ajaran: ' . htmlspecialchars($tahun_ajaran) . '</p>';
    echo '<br>';
    
    // Tabel
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th rowspan="2" style="width: 30px;">No</th>';
    echo '<th rowspan="2" style="width: 150px;">NISN</th>';
    echo '<th rowspan="2" style="width: 200px;">Nama Siswa</th>';
    echo '<th colspan="' . count($materi_list) . '">Materi Mulok</th>';
    echo '<th rowspan="2" style="width: 80px;">Rata-rata</th>';
    echo '</tr>';
    echo '<tr>';
    foreach ($materi_list as $materi) {
        echo '<th style="width: 100px;">' . htmlspecialchars($materi['nama_mulok']) . '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    foreach ($siswa_list as $siswa) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($siswa['nisn'] ?? '-') . '</td>';
        echo '<td class="text-left">' . htmlspecialchars($siswa['nama'] ?? '-') . '</td>';
        
        $total_nilai = 0;
        $count_nilai = 0;
        
        foreach ($materi_list as $materi) {
            $key = $siswa['id'] . '_' . $materi['id'];
            $nilai = $nilai_data[$key] ?? null;
            $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
            
            if (!empty($nilai_value)) {
                $total_nilai += floatval($nilai_value);
                $count_nilai++;
            }
            
            echo '<td>' . htmlspecialchars($nilai_value ?: '-') . '</td>';
        }
        
        $rata_rata = $count_nilai > 0 ? round($total_nilai / $count_nilai, 0) : 0;
        echo '<td><strong>' . $rata_rata . '</strong></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

// Export PDF
if ($format == 'pdf') {
    header('Content-Type: text/html; charset=UTF-8');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Legger Nilai Mulok Khusus</title>';
    echo '<style>';
    echo '@media print {';
    echo '  @page { size: A4 landscape; margin: 1cm; }';
    echo '  body { margin: 0; padding: 10px; }';
    echo '}';
    echo 'body { font-family: Arial, sans-serif; font-size: 9pt; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 10px; }';
    echo 'th, td { border: 1px solid #000; padding: 4px; text-align: center; font-size: 8pt; }';
    echo 'th { background-color: #f0f0f0; font-weight: bold; }';
    echo '.text-left { text-align: left; }';
    echo 'h2 { text-align: center; margin: 5px 0; font-size: 14pt; }';
    echo 'h3 { text-align: center; margin: 5px 0; font-size: 12pt; }';
    echo 'p { text-align: center; margin: 5px 0; font-size: 10pt; }';
    echo '.logo-container { text-align: center; margin-bottom: 10px; }';
    echo '.logo { width: 80px; height: 80px; display: inline-block; padding: 5px; background-color: #fff; }';
    echo '.logo img { width: 100%; height: 100%; object-fit: contain; }';
    echo '</style>';
    echo '<script>';
    echo 'window.onload = function() { window.print(); };';
    echo '</script>';
    echo '</head>';
    echo '<body>';
    
    // Logo
    echo '<div class="logo-container">';
    echo '<div class="logo">';
    if (!empty($profil_madrasah['logo'])) {
        echo '<img src="../uploads/' . htmlspecialchars($profil_madrasah['logo']) . '" alt="Logo Madrasah">';
    } else {
        echo '<div style="text-align: center; padding-top: 20px; color: #2d5016; font-weight: bold;">';
        echo '<div style="font-size: 8pt;">MADRASAH</div>';
        echo '<div style="font-size: 8pt;">IBTIDAIYAH</div>';
        echo '<div style="font-size: 10pt; margin-top: 2px;">SULTAN</div>';
        echo '<div style="font-size: 10pt;">FATTAH</div>';
        echo '<div style="font-size: 7pt; margin-top: 2px;">SUKOSONO</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Header
    echo '<h2>LEGGER NILAI MULOK KHUSUS</h2>';
    echo '<h3>' . htmlspecialchars(strtoupper($profil_madrasah['nama_madrasah'] ?? 'MI SULTAN FATTAH SUKOSONO')) . '</h3>';
    echo '<p>Kelas: ' . htmlspecialchars($kelas_data['nama_kelas'] ?? '-') . ' | Semester: ' . getSemesterText($semester_aktif) . ' | Tahun Ajaran: ' . htmlspecialchars($tahun_ajaran) . '</p>';
    
    // Tabel
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th rowspan="2" style="width: 30px;">No</th>';
    echo '<th rowspan="2" style="width: 100px;">NISN</th>';
    echo '<th rowspan="2" style="width: 150px;">Nama Siswa</th>';
    echo '<th colspan="' . count($materi_list) . '">Materi Mulok</th>';
    echo '<th rowspan="2" style="width: 60px;">Rata-rata</th>';
    echo '</tr>';
    echo '<tr>';
    foreach ($materi_list as $materi) {
        echo '<th style="width: 80px; font-size: 7pt;">' . htmlspecialchars($materi['nama_mulok']) . '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    foreach ($siswa_list as $siswa) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($siswa['nisn'] ?? '-') . '</td>';
        echo '<td class="text-left" style="font-size: 8pt;">' . htmlspecialchars($siswa['nama'] ?? '-') . '</td>';
        
        $total_nilai = 0;
        $count_nilai = 0;
        
        foreach ($materi_list as $materi) {
            $key = $siswa['id'] . '_' . $materi['id'];
            $nilai = $nilai_data[$key] ?? null;
            $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
            
            if (!empty($nilai_value)) {
                $total_nilai += floatval($nilai_value);
                $count_nilai++;
            }
            
            echo '<td>' . htmlspecialchars($nilai_value ?: '-') . '</td>';
        }
        
        $rata_rata = $count_nilai > 0 ? round($total_nilai / $count_nilai, 0) : 0;
        echo '<td><strong>' . $rata_rata . '</strong></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

