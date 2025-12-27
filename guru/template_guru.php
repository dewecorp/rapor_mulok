<?php
// Pastikan tidak ada output sebelum header
ob_start();

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

// Load PhpSpreadsheet
require_once '../vendor/autoload.php';

// Clear output buffer
ob_end_clean();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    // Buat spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set header
    $headers = ['Nama', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Pendidikan', 'NUPTK', 'Password', 'Role'];
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
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    
    // Set contoh data (tanpa kolom tanggal dulu)
    $exampleData = [
        ['Contoh Guru 1', 'L', 'Jepara', '', 'S1', '1234567890', '123456', 'guru'],
        ['Contoh Guru 2', 'P', 'Jakarta', '', 'S2', '0987654321', '123456', 'wali_kelas'],
    ];
    $sheet->fromArray($exampleData, NULL, 'A2');
    
    // Set format tanggal untuk kolom D (Tanggal Lahir)
    $sheet->getStyle('D2:D3')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    
    // Set nilai tanggal sebagai Excel date serial number untuk contoh
    try {
        $date1 = new DateTime('1990-01-15');
        $excelDate1 = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date1);
        $sheet->setCellValue('D2', $excelDate1);
        
        $date2 = new DateTime('1985-05-20');
        $excelDate2 = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date2);
        $sheet->setCellValue('D3', $excelDate2);
    } catch (Exception $e) {
        // Fallback: set sebagai string jika gagal
        $sheet->setCellValue('D2', '1990-01-15');
        $sheet->setCellValue('D3', '1985-05-20');
    }
    
    // Set column width
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(15);
    
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
    $sheet->getStyle('A2:H3')->applyFromArray($dataStyle);
    
    // Set header row height
    $sheet->getRowDimension(1)->setRowHeight(25);
    
    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="template_import_guru.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    die('Error creating template: ' . $e->getMessage());
}
?>
