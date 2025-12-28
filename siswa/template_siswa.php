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
use PhpOffice\PhpSpreadsheet\Style\Color;

try {
    // Buat spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Ambil nama kelas dari filter jika ada (untuk ditampilkan di template)
    $nama_kelas_template = '';
    if (isset($_GET['kelas']) && !empty($_GET['kelas'])) {
        $conn = getConnection();
        $kelas_id = intval($_GET['kelas']);
        $query_kelas = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $query_kelas->bind_param("i", $kelas_id);
        $query_kelas->execute();
        $result_kelas = $query_kelas->get_result();
        if ($result_kelas && $result_kelas->num_rows > 0) {
            $nama_kelas_template = $result_kelas->fetch_assoc()['nama_kelas'];
        }
        $query_kelas->close();
        $conn->close();
    }
    
    // Set header (tanpa kolom Kelas karena sudah dipilih di filter)
    $headers = ['NISN', 'Nama', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Orangtua/Wali'];
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
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
    
    // Set contoh data (tanpa kolom kelas)
    $exampleData = [
        ['1234567890', 'Contoh Siswa 1', 'L', 'Jepara', '', 'Nama Orangtua/Wali 1'],
        ['0987654321', 'Contoh Siswa 2', 'P', 'Jakarta', '', 'Nama Orangtua/Wali 2'],
    ];
    $sheet->fromArray($exampleData, NULL, 'A2');
    
    // Set format tanggal untuk kolom E (Tanggal Lahir)
    $sheet->getStyle('E2:E3')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    
    // Set nilai tanggal sebagai Excel date serial number untuk contoh
    try {
        $date1 = new DateTime('2010-01-15');
        $excelDate1 = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date1);
        $sheet->setCellValue('E2', $excelDate1);
        
        $date2 = new DateTime('2010-05-20');
        $excelDate2 = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date2);
        $sheet->setCellValue('E3', $excelDate2);
    } catch (Exception $e) {
        // Fallback: set sebagai string jika gagal
        $sheet->setCellValue('E2', '2010-01-15');
        $sheet->setCellValue('E3', '2010-05-20');
    }
    
    // Set column width
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    
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
    $sheet->getStyle('A2:F3')->applyFromArray($dataStyle);
    
    // Tambahkan catatan di bawah tabel
    $sheet->setCellValue('A4', 'Catatan: Semua siswa yang diimpor akan otomatis masuk ke kelas yang dipilih di filter.');
    $sheet->mergeCells('A4:F4');
    $sheet->getStyle('A4')->getFont()->setItalic(true);
    $sheet->getStyle('A4')->getFont()->getColor()->setRGB('808080');
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A4')->getAlignment()->setWrapText(true);
    $sheet->getRowDimension(4)->setRowHeight(30);
    
    // Set header row height
    $sheet->getRowDimension(1)->setRowHeight(25);
    
    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="template_import_siswa.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    die('Error creating template: ' . $e->getMessage());
}
?>
