<?php
// Pastikan tidak ada output sebelum header
ob_start();

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

// Load PhpSpreadsheet
require_once '../vendor/autoload.php';

// Clear output buffer
ob_end_clean();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

try {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    $materi_id = isset($_GET['materi_id']) ? intval($_GET['materi_id']) : 0;
    
    if ($materi_id <= 0) {
        die('Materi tidak ditemukan');
    }
    
    // Ambil kelas yang diampu oleh wali kelas ini
    $kelas_id = 0;
    $nama_kelas = '';
    try {
        $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmt_kelas->bind_param("i", $user_id);
        $stmt_kelas->execute();
        $result_kelas = $stmt_kelas->get_result();
        $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
        if ($kelas_data) {
            $kelas_id = $kelas_data['id'];
            $nama_kelas = $kelas_data['nama_kelas'];
        }
        $stmt_kelas->close();
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
    
    if ($kelas_id <= 0) {
        die('Kelas tidak ditemukan');
    }
    
    // Ambil nama materi
    $nama_materi = '';
    try {
        $stmt_materi = $conn->prepare("SELECT BINARY nama_mulok as nama_mulok FROM materi_mulok WHERE id = ?");
        $stmt_materi->bind_param("i", $materi_id);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        if ($result_materi && $result_materi->num_rows > 0) {
            $materi_row = $result_materi->fetch_assoc();
            $nama_materi = isset($materi_row['nama_mulok']) ? (string)$materi_row['nama_mulok'] : '';
        }
        $stmt_materi->close();
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
    
    if (empty($nama_materi)) {
        die('Materi tidak ditemukan');
    }
    
    // Ambil data siswa di kelas
    $siswa_data = [];
    try {
        $stmt_siswa = $conn->prepare("SELECT s.* FROM siswa s WHERE s.kelas_id = ? ORDER BY s.nama");
        $stmt_siswa->bind_param("i", $kelas_id);
        $stmt_siswa->execute();
        $result_siswa = $stmt_siswa->get_result();
        
        if ($result_siswa) {
            while ($siswa = $result_siswa->fetch_assoc()) {
                $siswa_data[] = $siswa;
            }
        }
        $stmt_siswa->close();
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
    
    if (empty($siswa_data)) {
        die('Tidak ada siswa di kelas ini');
    }
    
    // Buat spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set header
    $headers = ['NISN', 'Nama Siswa', 'Nilai'];
    $sheet->fromArray($headers, NULL, 'A1');
    
    // Style header
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2d5016'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
    
    // Set data siswa
    $row = 2;
    foreach ($siswa_data as $siswa) {
        $sheet->setCellValueExplicit('A' . $row, $siswa['nisn'] ?? '', DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row, $siswa['nama'] ?? '');
        $sheet->setCellValue('C' . $row, ''); // Kolom nilai kosong untuk diisi
        $row++;
    }
    
    // Set format TEKS untuk kolom A (NISN)
    $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
    
    // Set format angka untuk kolom C (Nilai)
    $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
    
    // Set column width
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(15);
    
    // Style data rows
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $sheet->getStyle('A2:C' . $lastRow)->applyFromArray($dataStyle);
    }
    
    // Tambahkan catatan di bawah tabel
    $catatan = 'Catatan: Isi kolom Nilai dengan angka 0-100. Predikat dan deskripsi akan dihitung otomatis.';
    $sheet->setCellValue('A' . $row, $catatan);
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
    $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('808080');
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true);
    $sheet->getRowDimension($row)->setRowHeight(30);
    
    // Set header row height
    $sheet->getRowDimension(1)->setRowHeight(25);
    
    // Output file dengan nama yang menyertakan materi dan kelas
    $filename = 'template_import_nilai';
    
    // Bersihkan nama materi dari karakter yang tidak valid untuk filename
    if (!empty($nama_materi)) {
        $nama_materi_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nama_materi);
        $filename .= '_' . $nama_materi_clean;
    }
    
    // Bersihkan nama kelas dari karakter yang tidak valid untuk filename
    if (!empty($nama_kelas)) {
        $nama_kelas_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nama_kelas);
        $filename .= '_' . $nama_kelas_clean;
    }
    
    $filename .= '.xlsx';
    
    $conn->close();
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    die('Error creating template: ' . $e->getMessage());
}
?>

