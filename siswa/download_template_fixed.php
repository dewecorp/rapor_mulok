<?php
// VERSI FIXED - Pastikan nama kelas selalu muncul di belakang
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
date_default_timezone_set('Asia/Jakarta');

// Ambil kelas_id dari GET parameter
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;

// Variabel untuk nama kelas
$kelas_nama = 'Semua Kelas';
$kelas_nama_untuk_file = 'SemuaKelas';

// JIKA kelas_id > 0, AMBIL NAMA KELAS DARI DATABASE
if ($kelas_id > 0) {
    // Query langsung - sederhana dan jelas
    $query = "SELECT nama_kelas FROM kelas WHERE id = $kelas_id LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $kelas_nama = $row['nama_kelas'];
        
        // Bersihkan untuk nama file
        $kelas_nama_untuk_file = trim($kelas_nama);
        $kelas_nama_untuk_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = preg_replace('/_+/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = trim($kelas_nama_untuk_file, '_');
        
        // Jika kosong, gunakan ID
        if (empty($kelas_nama_untuk_file)) {
            $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
        }
    } else {
        // Jika tidak ditemukan, gunakan ID
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
    }
}

// BUAT FILENAME - PASTIKAN NAMA KELAS SELALU DITAMBAHKAN JIKA kelas_id > 0
$filename = 'Template_Import_Siswa_' . date('Y-m-d');

// TAMBAHKAN NAMA KELAS DI BELAKANG
if ($kelas_id > 0) {
    $filename .= '_' . $kelas_nama_untuk_file; // PASTI DITAMBAHKAN
} else {
    $filename .= '_SemuaKelas';
}

$filename .= '.xlsx';

// Simpan debug
@file_put_contents('../uploads/temp/debug_fixed.txt', 
    "Kelas ID: $kelas_id\n" .
    "Kelas Nama: $kelas_nama\n" .
    "Kelas Nama File: $kelas_nama_untuk_file\n" .
    "Filename: $filename\n" .
    "GET kelas_id: " . ($_GET['kelas_id'] ?? 'tidak ada') . "\n"
);

// Load PhpSpreadsheet
require_once '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Template Import Siswa');
    
    // Header
    $sheet->setCellValue('A1', 'TEMPLATE IMPORT DATA SISWA');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Kelas: ' . $kelas_nama);
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getFont()->setBold(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Header kolom
    $headers = ['A5' => 'NISN', 'B5' => 'Nama', 'C5' => 'Jenis Kelamin', 'D5' => 'Tempat Lahir', 'E5' => 'Tanggal Lahir', 'F5' => 'Kelas'];
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5016']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
    ];
    $sheet->getStyle('A5:F5')->applyFromArray($headerStyle);
    
    // Set lebar kolom
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    
    // Contoh data
    $sheet->setCellValue('C6', 'L atau P');
    $sheet->setCellValue('E6', 'YYYY-MM-DD');
    if ($kelas_id > 0) {
        $sheet->setCellValue('F6', $kelas_nama);
    }
    
    // Baris kosong
    for ($i = 7; $i <= 20; $i++) {
        if ($kelas_id > 0) {
            $sheet->setCellValue('F' . $i, $kelas_nama);
        }
    }
    
    // Clean output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set header dengan filename yang sudah benar
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: import.php');
    exit;
}

