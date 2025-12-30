<?php
ob_start();
require_once '../config/config.php';
require_once '../config/database.php';
requireRole(['proktor', 'wali_kelas']);

$conn = getConnection();

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';

// Ambil parameter
$format = $_GET['format'] ?? 'excel';
$kelas_id = $_GET['kelas'] ?? 0;

if ($kelas_id <= 0) {
    die('Kelas tidak valid');
}

// Ambil data kelas
$stmt_kelas = $conn->prepare("SELECT k.*, p.nama as wali_kelas_nama FROM kelas k LEFT JOIN pengguna p ON k.wali_kelas_id = p.id WHERE k.id = ?");
$stmt_kelas->bind_param("i", $kelas_id);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
$kelas_data = $result_kelas->fetch_assoc();

if (!$kelas_data) {
    die('Kelas tidak ditemukan');
}

// Ambil data profil madrasah
$profil = null;
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
}

// Ambil semester dan tahun ajaran aktif
$semester_aktif = '1';
$tahun_ajaran_aktif = '';
if ($profil) {
    $semester_aktif = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran_aktif = $profil['tahun_ajaran_aktif'] ?? '';
}

// Format semester
$semester_text = ($semester_aktif == '1') ? 'Gasal' : 'Genap';

// Ambil semua siswa di kelas
$siswa_data = [];
$stmt_siswa = $conn->prepare("SELECT s.* FROM siswa s WHERE s.kelas_id = ? ORDER BY s.nama ASC");
$stmt_siswa->bind_param("i", $kelas_id);
$stmt_siswa->execute();
$result_siswa = $stmt_siswa->get_result();
if ($result_siswa) {
    while ($row = $result_siswa->fetch_assoc()) {
        $siswa_data[] = $row;
    }
}

// Ambil semua materi yang diampu di kelas ini untuk semester aktif
$materi_list = [];
$query_materi = "SELECT DISTINCT m.id, m.nama_mulok, m.$kolom_kategori as kategori
                 FROM mengampu_materi mm
                 INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                 WHERE mm.kelas_id = ? AND m.semester = ?
                 ORDER BY m.nama_mulok ASC";
$stmt_materi = $conn->prepare($query_materi);
$stmt_materi->bind_param("is", $kelas_id, $semester_aktif);
$stmt_materi->execute();
$result_materi = $stmt_materi->get_result();
if ($result_materi) {
    while ($materi = $result_materi->fetch_assoc()) {
        $kategori = $materi['kategori'] ?? '';
        $materi_list[] = [
            'id' => $materi['id'],
            'nama' => $materi['nama_mulok'],
            'kategori' => $kategori,
            'display' => !empty($kategori) ? $kategori . ' ' . $materi['nama_mulok'] : $materi['nama_mulok']
        ];
    }
}

// Ambil nilai untuk semua siswa
$nilai_data = [];
foreach ($siswa_data as $siswa) {
    $siswa_id = $siswa['id'];
    $nilai_data[$siswa_id] = [];
    
    foreach ($materi_list as $materi) {
        $query_nilai = "SELECT nilai_pengetahuan, predikat FROM nilai_siswa 
                       WHERE siswa_id = ? AND materi_mulok_id = ? AND semester = ? AND tahun_ajaran = ?";
        $stmt_nilai = $conn->prepare($query_nilai);
        $stmt_nilai->bind_param("iiss", $siswa_id, $materi['id'], $semester_aktif, $tahun_ajaran_aktif);
        $stmt_nilai->execute();
        $result_nilai = $stmt_nilai->get_result();
        $nilai_row = $result_nilai->fetch_assoc();
        
        $nilai_data[$siswa_id][$materi['id']] = [
            'nilai' => $nilai_row['nilai_pengetahuan'] ?? '',
            'predikat' => $nilai_row['predikat'] ?? ''
        ];
    }
}

if ($format == 'excel') {
    // Export Excel
    $vendor_path = '../vendor/autoload.php';
    if (!file_exists($vendor_path)) {
        $vendor_path = '../../vendor/autoload.php';
    }
    
    if (!file_exists($vendor_path)) {
        die('PhpSpreadsheet library tidak ditemukan. Silakan install melalui composer: composer require phpoffice/phpspreadsheet');
    }
    
    require_once $vendor_path;
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Fill;
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set judul
    $sheet->setCellValue('A1', 'LEGGER NILAI MULOK KHUSUS');
    $sheet->mergeCells('A1:' . chr(65 + count($materi_list) + 2) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Kelas: ' . $kelas_data['nama_kelas']);
    $sheet->mergeCells('A2:' . chr(65 + count($materi_list) + 2) . '2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A3', 'Semester: ' . $semester_text . ' | Tahun Ajaran: ' . $tahun_ajaran_aktif);
    $sheet->mergeCells('A3:' . chr(65 + count($materi_list) + 2) . '3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Header tabel
    $col = 'A';
    $row = 5;
    $sheet->setCellValue($col++ . $row, 'No');
    $sheet->setCellValue($col++ . $row, 'NISN');
    $sheet->setCellValue($col++ . $row, 'Nama Siswa');
    
    foreach ($materi_list as $materi) {
        $sheet->setCellValue($col++ . $row, $materi['display']);
    }
    
    // Style header
    $headerRange = 'A' . $row . ':' . chr(ord($col) - 1) . $row;
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Data siswa
    $row++;
    $no = 1;
    foreach ($siswa_data as $siswa) {
        $col = 'A';
        $sheet->setCellValue($col++ . $row, $no++);
        $sheet->setCellValue($col++ . $row, $siswa['nisn'] ?? '');
        $sheet->setCellValue($col++ . $row, $siswa['nama'] ?? '');
        
        foreach ($materi_list as $materi) {
            $nilai = $nilai_data[$siswa['id']][$materi['id']]['nilai'] ?? '';
            $predikat = $nilai_data[$siswa['id']][$materi['id']]['predikat'] ?? '';
            $display = !empty($nilai) ? $nilai . ($predikat ? ' (' . $predikat . ')' : '') : '';
            $sheet->setCellValue($col++ . $row, $display);
        }
        
        // Style baris
        $rowRange = 'A' . $row . ':' . chr(ord($col) - 1) . $row;
        $sheet->getStyle($rowRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
    }
    
    // Auto size columns
    foreach (range('A', chr(ord($col) - 1)) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Set filename
    $filename = 'Legger_Nilai_' . str_replace(' ', '_', $kelas_data['nama_kelas']) . '_' . $semester_text . '_' . date('Y-m-d') . '.xlsx';
    
    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} elseif ($format == 'pdf') {
    // Export PDF menggunakan HTML to PDF sederhana
    ob_clean();
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Legger Nilai</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 5px;
        }
        .info {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        .no {
            width: 30px;
            text-align: center;
        }
        .nisn {
            width: 100px;
        }
        .nama {
            width: 200px;
        }
    </style>
</head>
<body>
    <h2>LEGGER NILAI MULOK KHUSUS</h2>
    <div class="info">
        <strong>Kelas:</strong> ' . htmlspecialchars($kelas_data['nama_kelas']) . '<br>
        <strong>Semester:</strong> ' . htmlspecialchars($semester_text) . ' | <strong>Tahun Ajaran:</strong> ' . htmlspecialchars($tahun_ajaran_aktif) . '
    </div>
    <table>
        <thead>
            <tr>
                <th class="no">No</th>
                <th class="nisn">NISN</th>
                <th class="nama">Nama Siswa</th>';
    
    foreach ($materi_list as $materi) {
        $html .= '<th>' . htmlspecialchars($materi['display']) . '</th>';
    }
    
    $html .= '</tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach ($siswa_data as $siswa) {
        $html .= '<tr>
            <td class="no">' . $no++ . '</td>
            <td>' . htmlspecialchars($siswa['nisn'] ?? '') . '</td>
            <td>' . htmlspecialchars($siswa['nama'] ?? '') . '</td>';
        
        foreach ($materi_list as $materi) {
            $nilai = $nilai_data[$siswa['id']][$materi['id']]['nilai'] ?? '';
            $predikat = $nilai_data[$siswa['id']][$materi['id']]['predikat'] ?? '';
            $display = !empty($nilai) ? $nilai . ($predikat ? ' (' . $predikat . ')' : '') : '';
            $html .= '<td>' . htmlspecialchars($display) . '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
    </table>
</body>
</html>';
    
    // Output PDF
    $filename = 'Legger_Nilai_' . str_replace(' ', '_', $kelas_data['nama_kelas']) . '_' . $semester_text . '_' . date('Y-m-d') . '.pdf';
    
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

