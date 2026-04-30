<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$conn = getConnection();
$format = $_GET['format'] ?? 'excel';
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;

if ($kelas_id <= 0) {
    die('Kelas tidak valid');
}

$kelas_data = null;
$stmt_kelas = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt_kelas->bind_param("i", $kelas_id);
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
$kelas_data = $result_kelas->fetch_assoc();
$stmt_kelas->close();

$query = "SELECT s.*, k.nama_kelas
          FROM siswa s
          LEFT JOIN kelas k ON s.kelas_id = k.id
          WHERE s.kelas_id = ?
          ORDER BY s.nama";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $kelas_id);
$stmt->execute();
$result = $stmt->get_result();

$nama_kelas_safe = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '_', $kelas_data['nama_kelas'] ?? 'kelas');

if ($format == 'excel') {
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Siswa');

        $sheet->setCellValue('A1', 'DATA SISWA');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'Kelas: ' . ($kelas_data['nama_kelas'] ?? '-'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A3')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['No', 'NISN', 'Nama', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Orangtua/Wali'];
        $sheet->fromArray($headers, null, 'A5');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2d5016'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];
        $sheet->getStyle('A5:G5')->applyFromArray($headerStyle);

        $rowIdx = 6;
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $sheet->setCellValue('A' . $rowIdx, $no++);

            $nisn = isset($row['nisn']) ? trim((string) $row['nisn']) : '';
            $sheet->setCellValueExplicit('B' . $rowIdx, $nisn !== '' ? $nisn : '-', DataType::TYPE_STRING);

            $sheet->setCellValue('C' . $rowIdx, $row['nama'] ?? '-');
            $jk = (($row['jenis_kelamin'] ?? 'L') == 'L') ? 'Laki-laki' : 'Perempuan';
            $sheet->setCellValue('D' . $rowIdx, $jk);
            $sheet->setCellValue('E' . $rowIdx, $row['tempat_lahir'] ?? '-');

            if (!empty($row['tanggal_lahir'])) {
                try {
                    $dt = new DateTime($row['tanggal_lahir']);
                    $sheet->setCellValue('F' . $rowIdx, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt));
                    $sheet->getStyle('F' . $rowIdx)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
                } catch (Exception $e) {
                    $sheet->setCellValue('F' . $rowIdx, $row['tanggal_lahir']);
                }
            } else {
                $sheet->setCellValue('F' . $rowIdx, '-');
            }

            $sheet->setCellValue('G' . $rowIdx, $row['orangtua_wali'] ?? '-');
            $rowIdx++;
        }

        // Kolom NISN sebagai teks (pertahankan nol di depan)
        if ($rowIdx > 6) {
            $last = $rowIdx - 1;
            $sheet->getStyle('B6:B' . $last)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="data_siswa_' . $nama_kelas_safe . '_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $stmt->close();
        $conn->close();
        exit;
    } catch (Exception $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Ekspor Excel gagal: ' . htmlspecialchars($e->getMessage());
        $stmt->close();
        $conn->close();
        exit;
    }
}

// PDF (HTML print)
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
    echo '<td>' . htmlspecialchars($row['nisn'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['nama'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['tempat_lahir'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . (!empty($row['tanggal_lahir']) ? htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_lahir'])), ENT_QUOTES, 'UTF-8') : '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['orangtua_wali'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';

$stmt->close();
$conn->close();
