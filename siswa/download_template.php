<?php
// PASTIKAN TIDAK ADA OUTPUT SEBELUM HEADER!
// Start output buffering di PALING AWAL - SEBELUM APAPUN
if (ob_get_level() == 0) {
    ob_start();
}

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
    $stmt = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $kelas_nama = $row['nama_kelas'];
        
        // Bersihkan untuk nama file
        $kelas_nama_untuk_file = trim($kelas_nama);
        $kelas_nama_untuk_file = preg_replace('/[^A-Za-z0-9_-]/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = preg_replace('/_+/', '_', $kelas_nama_untuk_file);
        $kelas_nama_untuk_file = trim($kelas_nama_untuk_file, '_');
        
        // Jika kosong setelah dibersihkan, gunakan ID
        if (empty($kelas_nama_untuk_file)) {
            $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
        }
    } else {
        // Jika tidak ditemukan, gunakan ID
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
    }
    $stmt->close();
}

// BUAT FILENAME - PASTIKAN NAMA KELAS SELALU DITAMBAHKAN JIKA kelas_id > 0
$filename = 'Template_Import_Siswa_' . date('Y-m-d');

// TAMBAHKAN NAMA KELAS DI BELAKANG
if ($kelas_id > 0) {
    // PASTIKAN tidak kosong - jika masih kosong atau SemuaKelas, gunakan ID
    if (empty($kelas_nama_untuk_file) || $kelas_nama_untuk_file == 'SemuaKelas') {
        $kelas_nama_untuk_file = 'Kelas' . $kelas_id;
    }
    // PASTI DITAMBAHKAN - TIDAK ADA KONDISI LAIN
    $filename .= '_' . $kelas_nama_untuk_file;
} else {
    $filename .= '_SemuaKelas';
}

$filename .= '.xlsx';

// Simpan debug SEBELUM output apapun
$debug_dir = dirname(__FILE__) . '/../uploads/temp/';
if (!is_dir($debug_dir)) {
    @mkdir($debug_dir, 0777, true);
}
@file_put_contents($debug_dir . 'debug_final.txt', 
    "=== DEBUG DOWNLOAD TEMPLATE ===\n" .
    "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
    "GET kelas_id: " . (isset($_GET['kelas_id']) ? $_GET['kelas_id'] : 'tidak ada') . "\n" .
    "Kelas ID (int): $kelas_id\n" .
    "Kelas Nama: $kelas_nama\n" .
    "Kelas Nama File: $kelas_nama_untuk_file\n" .
    "Filename FINAL: $filename\n"
);

// CLEAN OUTPUT SEBELUM HEADER
while (ob_get_level() > 0) {
    ob_end_clean();
}

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
    
    $tanggal = date('d F Y');
    $sheet->setCellValue('A3', 'Tanggal: ' . $tanggal);
    $sheet->mergeCells('A3:F3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
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
    
    // Style contoh
    $exampleStyle = [
        'font' => ['italic' => true, 'color' => ['rgb' => '666666'], 'size' => 10],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F0F0']]
    ];
    $sheet->getStyle('A6:F6')->applyFromArray($exampleStyle);
    
    // Baris kosong
    for ($i = 7; $i <= 20; $i++) {
        if ($kelas_id > 0) {
            $sheet->setCellValue('F' . $i, $kelas_nama);
        }
    }
    
    // Catatan
    $row_note = 22;
    $sheet->setCellValue('A' . $row_note, 'CATATAN:');
    $sheet->getStyle('A' . $row_note)->getFont()->setBold(true);
    $row_note++;
    $sheet->setCellValue('A' . $row_note, '1. NISN dan Nama wajib diisi');
    $row_note++;
    $sheet->setCellValue('A' . $row_note, '2. Jenis Kelamin: L (Laki-laki) atau P (Perempuan)');
    $row_note++;
    $sheet->setCellValue('A' . $row_note, '3. Tanggal Lahir format: YYYY-MM-DD (contoh: 2010-01-15)');
    $row_note++;
    if ($kelas_id > 0) {
        $sheet->setCellValue('A' . $row_note, '4. Kolom Kelas sudah diisi dengan: ' . $kelas_nama);
    } else {
        $sheet->setCellValue('A' . $row_note, '4. Kolom Kelas: Isi dengan nama kelas yang sudah ada di sistem');
    }
    
    // CLEAN OUTPUT LAGI SEBELUM HEADER - SANGAT PENTING!
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set header dengan filename - GUNAKAN FORMAT YANG SAMA DENGAN FILE LAIN YANG BERHASIL
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    // Clean output sebelum error
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: import.php');
    exit;
}
