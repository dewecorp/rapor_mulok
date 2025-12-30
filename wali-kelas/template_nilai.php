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
use PhpOffice\PhpSpreadsheet\Style\Color;

try {
    // Ambil parameter dari URL
    $materi_id = isset($_GET['materi_id']) ? intval($_GET['materi_id']) : 0;
    $kelas_nama = isset($_GET['kelas_nama']) ? trim($_GET['kelas_nama']) : '';
    
    if ($materi_id <= 0) {
        die('Parameter materi_id tidak valid');
    }
    
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Ambil data materi
    $materi_data = null;
    $stmt_materi = $conn->prepare("SELECT m.*, mm.kelas_id, k.nama_kelas
                                   FROM materi_mulok m
                                   INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                                   LEFT JOIN kelas k ON mm.kelas_id = k.id
                                   WHERE m.id = ? AND mm.guru_id = ?
                                   LIMIT 1");
    $stmt_materi->bind_param("ii", $materi_id, $user_id);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();
    $materi_data = $result_materi ? $result_materi->fetch_assoc() : null;
    
    if (!$materi_data) {
        die('Data materi tidak ditemukan');
    }
    
    // Ambil nama materi dengan case yang benar
    $stmt_nama_binary = $conn->prepare("SELECT BINARY nama_mulok as nama_mulok FROM materi_mulok WHERE id = ?");
    $stmt_nama_binary->bind_param("i", $materi_id);
    $stmt_nama_binary->execute();
    $result_nama_binary = $stmt_nama_binary->get_result();
    if ($result_nama_binary && $result_nama_binary->num_rows > 0) {
        $row_nama = $result_nama_binary->fetch_assoc();
        $materi_data['nama_mulok'] = (string)$row_nama['nama_mulok'];
    }
    
    $nama_materi = $materi_data['nama_mulok'];
    $nama_kelas = $materi_data['nama_kelas'] ?? $kelas_nama;
    
    // Ambil data siswa dari kelas yang diampu
    $kelas_id_for_materi = $materi_data['kelas_id'] ?? 0;
    
    // Jika kelas_id dari mengampu_materi tidak ada, gunakan kelas_id dari wali_kelas
    if (!$kelas_id_for_materi) {
        $stmt_kelas_wali = $conn->prepare("SELECT id FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmt_kelas_wali->bind_param("i", $user_id);
        $stmt_kelas_wali->execute();
        $result_kelas_wali = $stmt_kelas_wali->get_result();
        if ($result_kelas_wali && $result_kelas_wali->num_rows > 0) {
            $kelas_wali = $result_kelas_wali->fetch_assoc();
            $kelas_id_for_materi = $kelas_wali['id'];
        }
    }
    
    // Ambil profil untuk semester dan tahun ajaran
    $profil = null;
    $semester = '1';
    $tahun_ajaran = '';
    try {
        $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
        $result_profil = $conn->query($query_profil);
        if ($result_profil && $result_profil->num_rows > 0) {
            $profil = $result_profil->fetch_assoc();
            $semester = $profil['semester_aktif'] ?? '1';
            $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
        }
    } catch (Exception $e) {
        // Tabel belum ada, gunakan default
        $semester = '1';
        $tahun_ajaran = '';
    }
    
    // Ambil data siswa
    $siswa_list = [];
    if ($kelas_id_for_materi > 0) {
        $stmt_siswa = $conn->prepare("SELECT id, nisn, nama, jenis_kelamin FROM siswa WHERE kelas_id = ? ORDER BY nama ASC");
        $stmt_siswa->bind_param("i", $kelas_id_for_materi);
        $stmt_siswa->execute();
        $result_siswa = $stmt_siswa->get_result();
        while ($siswa = $result_siswa->fetch_assoc()) {
            $siswa_list[] = $siswa;
        }
    }
    
    $conn->close();
    
    // Buat spreadsheet baru
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set header
    $headers = ['No', 'NISN', 'Nama Siswa', 'L/P', 'Nilai'];
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
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
    
    // Isi data siswa jika ada
    $row = 2;
    foreach ($siswa_list as $index => $siswa) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $siswa['nisn']);
        $sheet->setCellValue('C' . $row, $siswa['nama']);
        $sheet->setCellValue('D' . $row, $siswa['jenis_kelamin'] == 'L' ? 'L' : 'P');
        $sheet->setCellValue('E' . $row, ''); // Nilai kosong untuk diisi
        
        $row++;
    }
    
    // Jika tidak ada siswa, buat contoh data
    if (empty($siswa_list)) {
        $exampleData = [
            [1, '1234567890', 'Contoh Siswa 1', 'L', ''],
            [2, '0987654321', 'Contoh Siswa 2', 'P', ''],
        ];
        $sheet->fromArray($exampleData, NULL, 'A2');
    }
    
    // Set column width
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(8);
    $sheet->getColumnDimension('E')->setWidth(12);
    
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
    $maxRow = max(2, $row - 1);
    $sheet->getStyle('A2:E' . $maxRow)->applyFromArray($dataStyle);
    
    // Set alignment untuk kolom tertentu
    $sheet->getStyle('A2:A' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B2:B' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D2:D' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E2:E' . $maxRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Set format untuk kolom Nilai (number)
    $sheet->getStyle('E2:E' . $maxRow)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Tambahkan catatan di bawah tabel
    $catatan = 'Catatan: Isi kolom Nilai dengan angka 0-100. Predikat dan Deskripsi akan dihitung otomatis.';
    $sheet->setCellValue('A' . ($maxRow + 2), $catatan);
    $sheet->mergeCells('A' . ($maxRow + 2) . ':E' . ($maxRow + 2));
    $sheet->getStyle('A' . ($maxRow + 2))->getFont()->setItalic(true);
    $sheet->getStyle('A' . ($maxRow + 2))->getFont()->getColor()->setRGB('808080');
    $sheet->getStyle('A' . ($maxRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . ($maxRow + 2))->getAlignment()->setWrapText(true);
    $sheet->getRowDimension($maxRow + 2)->setRowHeight(30);
    
    // Set header row height
    $sheet->getRowDimension(1)->setRowHeight(25);
    
    // Bersihkan nama materi dan kelas dari karakter yang tidak valid untuk filename
    $nama_materi_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nama_materi);
    $nama_kelas_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nama_kelas);
    
    // Output file dengan nama yang menyertakan materi dan kelas
    $filename = 'template_nilai_' . $nama_materi_clean;
    if (!empty($nama_kelas_clean)) {
        $filename .= '_' . $nama_kelas_clean;
    }
    $filename .= '.xlsx';
    
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

