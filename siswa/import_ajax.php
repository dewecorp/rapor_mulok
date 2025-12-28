<?php
// Disable error display untuk mencegah output sebelum JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Pastikan tidak ada output sebelumnya - bersihkan semua output buffer
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Start output buffering untuk menangkap error
ob_start();

// Set header JSON sebelum output apapun
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Fungsi untuk mengirim error response
function sendErrorResponse($message, $code = 500) {
    // Bersihkan semua output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Pastikan header belum dikirim
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    // Kirim JSON response
    echo json_encode([
        'success' => false,
        'message' => $message,
        'success_count' => 0,
        'error_count' => 0,
        'duplicate_count' => 0
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    // Pastikan exit
    exit;
}

// Error handler untuk menangkap error PHP (hanya error yang fatal)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Hanya tangkap error yang fatal, bukan warning atau notice
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log("PHP Fatal Error [$errno]: $errstr in $errfile on line $errline");
        sendErrorResponse("Fatal Error: $errstr (Line $errline)");
    } else {
        // Log warning/notice tapi jangan stop execution
        error_log("PHP Warning/Notice [$errno]: $errstr in $errfile on line $errline");
        return false; // Biarkan PHP menangani warning/notice secara default
    }
});

// Shutdown function untuk menangkap fatal error
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
        sendErrorResponse("Fatal Error: {$error['message']} (Line {$error['line']})");
    }
});

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    
    // Cek login dan role tanpa redirect (untuk AJAX)
    if (!isLoggedIn()) {
        sendErrorResponse('Anda harus login terlebih dahulu!', 401);
    }
    if (!hasRole('proktor')) {
        sendErrorResponse('Anda tidak memiliki akses untuk melakukan import!', 403);
    }
} catch (Exception $e) {
    error_log('Error loading config: ' . $e->getMessage());
    sendErrorResponse('Error loading configuration: ' . $e->getMessage());
} catch (Error $e) {
    error_log('Fatal Error loading config: ' . $e->getMessage());
    sendErrorResponse('Fatal Error: ' . $e->getMessage());
}

try {
    $conn = getConnection();
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    sendErrorResponse('Error koneksi database: ' . $e->getMessage());
} catch (Error $e) {
    error_log('Fatal Error database connection: ' . $e->getMessage());
    sendErrorResponse('Fatal Error: ' . $e->getMessage());
}

// Ambil kelas_id dari filter kelas (GET parameter)
// Logika baru: Import siswa berdasarkan kelas yang dipilih, bukan dari kolom Excel
$kelas_id_import = null;
if (isset($_GET['kelas']) && !empty($_GET['kelas'])) {
    $kelas_id_import = intval($_GET['kelas']);
    error_log("Import untuk kelas ID: $kelas_id_import");
    
    // Verifikasi kelas ada di database
    $check_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE id = ?");
    $check_kelas->bind_param("i", $kelas_id_import);
    $check_kelas->execute();
    $result_check = $check_kelas->get_result();
    if ($result_check->num_rows == 0) {
        sendErrorResponse("Kelas tidak ditemukan!");
    }
    $kelas_info = $result_check->fetch_assoc();
    error_log("Kelas yang dipilih untuk import: " . $kelas_info['nama_kelas'] . " (ID: $kelas_id_import)");
    $check_kelas->close();
} else {
    sendErrorResponse("Kelas harus dipilih terlebih dahulu!");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];
    
    // Cek error upload
    if ($file['error'] != 0) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension PHP'
        ];
        $error_msg = $error_messages[$file['error']] ?? 'Error upload file: ' . $file['error'];
        sendErrorResponse($error_msg);
    }
    
    if ($file['error'] == 0) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, ['xls', 'xlsx'])) {
            sendErrorResponse('Format file tidak didukung! Hanya file Excel (.xls, .xlsx) yang diperbolehkan');
        }
        
        $upload_dir = '../uploads/temp/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                sendErrorResponse('Gagal membuat folder upload. Pastikan folder uploads/temp dapat ditulis.');
            }
        }
        
        // Pastikan directory writable
        if (!is_writable($upload_dir)) {
            sendErrorResponse('Folder upload tidak dapat ditulis. Periksa permission folder uploads/temp.');
        }
        
        $file_path = $upload_dir . 'import_siswa_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            sendErrorResponse('Gagal memindahkan file. Periksa permission folder uploads/temp.');
        }
        
        // Baca file Excel/CSV
        $data = [];
        $success_count = 0;
        $error_count = 0;
        $duplicate_count = 0;
        $update_count = 0; // Hitung data yang di-update
        $errors = [];
        
        try {
            // Load PhpSpreadsheet autoloader
            require_once '../vendor/autoload.php';
            
            // Baca file Excel menggunakan PhpSpreadsheet
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
                // Set untuk membaca semua data, termasuk yang kosong
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
                $spreadsheet = $reader->load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Dapatkan range data yang sebenarnya
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                error_log("Excel file - Highest Row: $highestRow, Highest Column: $highestColumn (Index: $highestColumnIndex)");
                
                // Baca semua baris dengan eksplisit - baca sampai kolom E (index 5) untuk memastikan semua kolom terbaca
                // Gunakan metode yang lebih reliable untuk membaca semua baris
                $maxColumns = max(5, $highestColumnIndex); // Minimal 5 kolom (tanpa kolom kelas)
                $rows = [];
                
                // Baca menggunakan toArray untuk mendapatkan semua data sekaligus
                $allRows = $worksheet->toArray(null, true, true, true);
                error_log("Total rows from toArray: " . count($allRows));
                
                // Konversi ke format yang konsisten
                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    $rowHasData = false; // Flag untuk menandai apakah baris ini memiliki data
                    
                    // Gunakan data dari toArray jika ada, jika tidak baca langsung dari cell
                    $arrayRow = isset($allRows[$row]) ? $allRows[$row] : [];
                    
                    for ($colIndex = 1; $colIndex <= $maxColumns; $colIndex++) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                        
                        // Kolom A (index 1) adalah NISN - JANGAN pernah dibaca sebagai tanggal
                        // Kolom E (index 5) adalah kolom tanggal lahir - perlu penanganan khusus
                        $isNisnColumn = ($colIndex == 1);  // Kolom A = NISN
                        $isTanggalLahirColumn = ($colIndex == 5);  // Kolom E = Tanggal Lahir
                        
                        // Selalu baca cell untuk mendapatkan objek Cell yang lengkap
                        $cell = $worksheet->getCell($colLetter . $row);
                        
                        // Untuk kolom NISN, selalu baca sebagai teks/angka (bukan tanggal)
                        // Untuk kolom tanggal, selalu gunakan getValue() dari cell
                        // Untuk kolom lain, coba ambil dari array terlebih dahulu untuk performa
                        if ($isNisnColumn) {
                            // Kolom NISN: selalu baca sebagai teks, jangan pernah sebagai tanggal
                            $cellValue = $cell->getFormattedValue();  // Ambil nilai yang sudah diformat sebagai string
                            if (empty($cellValue)) {
                                $cellValue = $cell->getValue();  // Fallback ke getValue jika formatted kosong
                            }
                            // Pastikan tidak ada konversi tanggal
                            $cellValue = trim((string)$cellValue);
                            $rowData[] = $cellValue;
                            continue;  // Langsung skip ke kolom berikutnya, tidak perlu pengecekan tanggal
                        } else if ($isTanggalLahirColumn) {
                            $cellValue = $cell->getValue();
                        } else {
                            // Coba ambil dari array terlebih dahulu
                            if (isset($arrayRow[$colLetter]) && $arrayRow[$colLetter] !== null && $arrayRow[$colLetter] !== '') {
                                $cellValue = $arrayRow[$colLetter];
                            } else {
                                $cellValue = $cell->getValue();
                            }
                        }
                        
                        // Jika cell adalah tanggal Excel, konversi ke format string
                        // HANYA untuk kolom tanggal lahir, bukan untuk kolom lain
                        if ($cellValue !== null && $cellValue !== '') {
                            // Cek apakah cell adalah tanggal dengan beberapa metode
                            // HANYA untuk kolom tanggal lahir
                            $isDate = false;
                            $dateValue = null;
                            
                            // Metode 1: Cek menggunakan isDateTime() - hanya untuk kolom tanggal
                            if ($isTanggalLahirColumn) {
                                try {
                                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                                        $isDate = true;
                                        $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                                        error_log("Row $row, Col $colLetter: Detected as date using isDateTime()");
                                    }
                                } catch (Exception $e) {
                                    error_log("Row $row, Col $colLetter: isDateTime() error: " . $e->getMessage());
                                }
                            }
                            
                            // Metode 2: Cek jika nilai numerik dan dalam range tanggal Excel - HANYA untuk kolom tanggal
                            if (!$isDate && $isTanggalLahirColumn && is_numeric($cellValue)) {
                                // Range tanggal Excel: 1 (1900-01-01) sampai ~73000 (2099-12-31)
                                if ($cellValue >= 1 && $cellValue < 100000) {
                                    try {
                                        // Coba konversi sebagai tanggal Excel
                                        $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                                        // Validasi bahwa hasilnya masuk akal (antara 1900-2100)
                                        $year = (int)$dateValue->format('Y');
                                        if ($year >= 1900 && $year <= 2100) {
                                            $isDate = true;
                                            error_log("Row $row, Col $colLetter: Detected as date using numeric check (value: $cellValue, year: $year)");
                                        }
                                    } catch (Exception $e) {
                                        error_log("Row $row, Col $colLetter: excelToDateTimeObject() error: " . $e->getMessage());
                                    }
                                }
                            }
                            
                            // Metode 3: Cek format cell (jika cell diformat sebagai tanggal) - HANYA untuk kolom tanggal
                            if (!$isDate && $isTanggalLahirColumn) {
                                try {
                                    $formatCode = $cell->getStyle()->getNumberFormat()->getFormatCode();
                                    // Cek jika format mengandung karakter tanggal (d, m, y, h, s)
                                    if (preg_match('/[dmyhs]/i', $formatCode)) {
                                        if (is_numeric($cellValue) && $cellValue >= 1) {
                                            $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                                            $isDate = true;
                                            error_log("Row $row, Col $colLetter: Detected as date using format code: $formatCode");
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Ignore
                                }
                            }
                            
                            // Metode 4: Untuk kolom tanggal lahir khususnya, coba parsing sebagai tanggal jika format string
                            if (!$isDate && $isTanggalLahirColumn && is_string($cellValue)) {
                                $trimmedValue = trim($cellValue);
                                // Cek jika string sudah dalam format tanggal yang dikenal
                                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmedValue)) {
                                    // Sudah format YYYY-MM-DD
                                    $rowData[] = $trimmedValue;
                                    error_log("Row $row, Col $colLetter: Using string value as date (already YYYY-MM-DD): $trimmedValue");
                                    continue;
                                }
                            }
                            
                            if ($isDate && $dateValue) {
                                $formattedDate = $dateValue->format('Y-m-d');
                                $rowData[] = $formattedDate;
                                error_log("Row $row, Col $colLetter: Converted to date: $formattedDate");
                            } else {
                                // Konversi ke string dan trim
                                $stringValue = trim((string)$cellValue);
                                $rowData[] = $stringValue;
                                if ($isTanggalLahirColumn) {
                                    error_log("Row $row, Col $colLetter: Not detected as date, using as string: '$stringValue'");
                                }
                            }
                        } else {
                            $rowData[] = '';
                        }
                        
                        // Cek jika ada data di cell ini (kolom A dan B harus ada untuk valid)
                        $cellIndex = count($rowData) - 1;
                        if ($cellIndex >= 0 && !empty($rowData[$cellIndex])) {
                            // Khusus untuk kolom A (NISN) dan B (Nama), pastikan tidak kosong
                            if ($cellIndex == 0 || $cellIndex == 1) {
                                $rowHasData = true;
                            } else if ($rowHasData) {
                                // Jika sudah ada data di kolom A atau B, tetap tandai sebagai punya data
                                $rowHasData = true;
                            }
                        }
                    }
                    
                    // Cek apakah baris ini memiliki minimal NISN dan Nama (kolom A dan B)
                    $hasNisn = !empty(trim($rowData[0] ?? ''));
                    $hasNama = !empty(trim($rowData[1] ?? ''));
                    $isValidRow = $hasNisn && $hasNama;
                    
                    // Simpan semua baris, termasuk yang kosong (akan di-filter nanti)
                    $rows[] = [
                        'row_number' => $row,
                        'data' => $rowData,
                        'has_data' => $rowHasData,
                        'is_valid' => $isValidRow,
                        'has_nisn' => $hasNisn,
                        'has_nama' => $hasNama
                    ];
                    
                    error_log("Row $row read - Valid: " . ($isValidRow ? 'Yes' : 'No') . " - Has NISN: " . ($hasNisn ? 'Yes' : 'No') . " - Has Nama: " . ($hasNama ? 'Yes' : 'No') . " - Data count: " . count($rowData));
                }
                
                error_log("Total rows read from Excel: " . count($rows));
                
                // Skip header (baris pertama)
                if (count($rows) > 0) {
                    $headerRow = array_shift($rows);
                    error_log("Header row skipped: " . json_encode($headerRow));
                }
                
                error_log("Total data rows after skipping header: " . count($rows));
                
                // Fungsi normalisasi jenis kelamin
                $normalizeJenisKelamin = function($value) {
                    $value = strtoupper(trim($value ?? ''));
                    // Jika sudah 'L' atau 'P', langsung return
                    if ($value === 'L' || $value === 'P') {
                        return $value;
                    }
                    // Konversi berbagai format ke 'L' atau 'P'
                    if (in_array($value, ['LAKI-LAKI', 'LAKI', 'L', 'MALE', 'M', 'PRIA'])) {
                        return 'L';
                    }
                    if (in_array($value, ['PEREMPUAN', 'PEREMPUAN', 'P', 'FEMALE', 'F', 'WANITA'])) {
                        return 'P';
                    }
                    // Default ke 'L' jika tidak dikenali
                    return 'L';
                };
                
                // Fungsi normalisasi tanggal ke format MySQL (YYYY-MM-DD)
                // Catatan: Tanggal sudah dikonversi ke YYYY-MM-DD saat membaca Excel di atas
                $normalizeTanggal = function($value) {
                    // Sanitize value terlebih dahulu
                    $value = trim($value ?? '');
                    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value); // Hapus karakter kontrol
                    
                    // Jika kosong, return null
                    if (empty($value)) {
                        return null;
                    }
                    
                    // Jika sudah format MySQL YYYY-MM-DD, langsung return
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        return $value;
                    }
                    
                    // Coba parsing berbagai format tanggal
                    $formats = [
                        'Y-m-d',      // 2024-12-27
                        'd/m/Y',      // 27/12/2024
                        'd-m-Y',      // 27-12-2024
                        'd.m.Y',      // 27.12.2024
                        'Y/m/d',      // 2024/12/27
                        'd M Y',      // 27 Dec 2024
                        'd F Y',      // 27 December 2024
                    ];
                    
                    foreach ($formats as $format) {
                        $date = DateTime::createFromFormat($format, $value);
                        if ($date !== false) {
                            return $date->format('Y-m-d');
                        }
                    }
                    
                    // Coba dengan strtotime sebagai fallback
                    $timestamp = strtotime($value);
                    if ($timestamp !== false && $timestamp > 0) {
                        return date('Y-m-d', $timestamp);
                    }
                    
                    // Jika semua gagal, return null
                    return null;
                };
                
                foreach ($rows as $rowIndex => $rowInfo) {
                    // Handle struktur baru dengan row_number dan data
                    $rowNumber = isset($rowInfo['row_number']) ? $rowInfo['row_number'] : ($rowIndex + 2); // +2 karena header sudah di-skip
                    $row = isset($rowInfo['data']) ? $rowInfo['data'] : $rowInfo;
                    
                    // Skip baris kosong
                    if (empty($row) || !is_array($row)) {
                        error_log("Row $rowNumber (index $rowIndex) skipped: empty or not array");
                        continue;
                    }
                    
                    // Pastikan row adalah array dan memiliki minimal 5 kolom
                    $row = array_values($row); // Reset array keys
                    
                    // Validasi minimal kolom yang diperlukan (NISN, Nama, Jenis Kelamin, Tempat Lahir, Tanggal Lahir, Orangtua/Wali)
                    if (count($row) < 5) {
                        error_log("Row $rowNumber (index $rowIndex) skipped: less than 5 columns (found: " . count($row) . ")");
                        continue;
                    }
                    
                    // Trim dan validasi data
                    $nisn = trim($row[0] ?? '');
                    $nama = trim($row[1] ?? '');
                    
                    error_log("Row $rowNumber (index $rowIndex) - NISN: '$nisn', Nama: '$nama'");
                    
                    // Skip jika NISN atau nama kosong
                    if (empty($nisn) || empty($nama)) {
                        error_log("Row $rowNumber (index $rowIndex) skipped: NISN or Nama is empty");
                        continue;
                    }
                    
                    // Normalisasi data
                    $jenis_kelamin_raw = trim($row[2] ?? 'L');
                    $jenis_kelamin = $normalizeJenisKelamin($jenis_kelamin_raw);
                    
                    // Normalisasi tanggal lahir (kolom index 4, Excel column E)
                    // Catatan: Tanggal sudah dikonversi ke YYYY-MM-DD saat membaca Excel
                    $tanggal_lahir_raw = $row[4] ?? '';
                    
                    // Debug: Log nilai tanggal yang dibaca
                    error_log("Row $rowNumber (index $rowIndex) - Tanggal lahir raw: '$tanggal_lahir_raw'");
                    
                    $tanggal_lahir = $normalizeTanggal($tanggal_lahir_raw);
                    
                    // Debug: Log hasil normalisasi
                    error_log("Row $rowNumber (index $rowIndex) - Tanggal lahir normalized: " . ($tanggal_lahir ?? 'NULL'));
                    
                    // Kolom index 5 adalah orangtua/wali (bukan kelas lagi)
                    $orangtua_wali = trim($row[5] ?? '');
                    
                    $data[] = [
                        'nisn' => $nisn,
                        'nama' => $nama,
                        'jenis_kelamin' => $jenis_kelamin,
                        'tempat_lahir' => trim($row[3] ?? ''),
                        'tanggal_lahir' => $tanggal_lahir,
                        'orangtua_wali' => $orangtua_wali,
                        'row_number' => $rowNumber // Simpan nomor baris untuk tracking
                        // Kolom kelas tidak diperlukan lagi, menggunakan kelas_id dari filter
                    ];
                    
                    error_log("Row $rowNumber (index $rowIndex) - Data added to import list");
                }
                
                error_log("Total valid data rows to import: " . count($data));
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                throw new Exception('Gagal membaca file Excel: ' . $e->getMessage());
            }
            
            // Debug: Log jumlah data yang akan diimport
            error_log('Total data siswa to import: ' . count($data));
            
            // Catatan: NISN ganda di dalam file Excel akan di-handle dengan mengambil data terakhir
            // Data duplikat di database akan di-update dengan data baru
            
            // Import data
            foreach ($data as $index => $row_data) {
                error_log("Processing siswa data $index: " . json_encode($row_data));
                
                if (empty($row_data['nama']) || empty($row_data['nisn'])) {
                    error_log("Data siswa $index skipped: empty nama or nisn");
                    $error_count++;
                    continue;
                }
                
                // Trim data sebelum insert
                $nisn_clean = trim($row_data['nisn']);
                $nama_clean = trim($row_data['nama']);
                
                // Prepare data
                $tempat_lahir_null = empty($row_data['tempat_lahir']) ? null : $row_data['tempat_lahir'];
                $tanggal_lahir_null = empty($row_data['tanggal_lahir']) ? null : $row_data['tanggal_lahir'];
                $orangtua_wali_null = empty($row_data['orangtua_wali']) ? null : trim($row_data['orangtua_wali']);
                
                // Pastikan jenis_kelamin hanya 'L' atau 'P'
                $jenis_kelamin = in_array($row_data['jenis_kelamin'], ['L', 'P']) ? $row_data['jenis_kelamin'] : 'L';
                
                // Gunakan kelas_id dari filter yang dipilih (tidak perlu mapping dari Excel)
                $kelas_id = $kelas_id_import;
                error_log("Data siswa $index - Menggunakan kelas_id dari filter: $kelas_id");
                
                try {
                    // Cek apakah NISN sudah ada
                    $check_nisn = $conn->prepare("SELECT id FROM siswa WHERE nisn = ?");
                    $check_nisn->bind_param("s", $nisn_clean);
                    $check_nisn->execute();
                    $result_check = $check_nisn->get_result();
                    
                    if ($result_check->num_rows > 0) {
                        // Jika NISN sudah ada, UPDATE data yang ada
                        $existing_data = $result_check->fetch_assoc();
                        $existing_id = $existing_data['id'];
                        
                        error_log("Data siswa $index - NISN '$nisn_clean' already exists (ID: $existing_id), will UPDATE");
                        
                        // Ambil kelas lama sebelum update
                        $old_kelas_id = null;
                        try {
                            $stmt_old = $conn->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
                            $stmt_old->bind_param("i", $existing_id);
                            $stmt_old->execute();
                            $result_old = $stmt_old->get_result();
                            if ($result_old && $result_old->num_rows > 0) {
                                $old_data = $result_old->fetch_assoc();
                                $old_kelas_id = $old_data['kelas_id'];
                            }
                            $stmt_old->close();
                        } catch (Exception $e) {
                            error_log("Data siswa $index - Error getting old kelas_id: " . $e->getMessage());
                        }
                        
                        error_log("Data siswa $index - Old kelas_id: " . ($old_kelas_id ?? 'NULL') . ", New kelas_id: " . ($kelas_id ?? 'NULL'));
                        
                        // Handle kelas_id yang bisa null untuk UPDATE
                        if ($kelas_id === null) {
                            $stmt = $conn->prepare("UPDATE siswa SET nama = ?, jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?, orangtua_wali = ?, kelas_id = NULL WHERE nisn = ?");
                            $stmt->bind_param("ssssss", 
                                $nama_clean,
                                $jenis_kelamin,
                                $tempat_lahir_null,
                                $tanggal_lahir_null,
                                $orangtua_wali_null,
                                $nisn_clean
                            );
                        } else {
                            $stmt = $conn->prepare("UPDATE siswa SET nama = ?, jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?, orangtua_wali = ?, kelas_id = ? WHERE nisn = ?");
                            $stmt->bind_param("sssssis", 
                                $nama_clean,
                                $jenis_kelamin,
                                $tempat_lahir_null,
                                $tanggal_lahir_null,
                                $orangtua_wali_null,
                                $kelas_id,
                                $nisn_clean
                            );
                        }
                        
                        if ($stmt->execute()) {
                            $update_count++;
                            $success_count++; // Update juga dihitung sebagai success
                            error_log("Data siswa $index - UPDATE successful: $nama_clean");
                            
                            // Update jumlah siswa di kelas lama jika berbeda dengan kelas baru
                            if ($old_kelas_id && $old_kelas_id != $kelas_id) {
                                $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $old_kelas_id) WHERE id = $old_kelas_id");
                                error_log("Data siswa $index - Updated jumlah_siswa for old kelas_id: $old_kelas_id");
                            }
                            
                            // Update jumlah siswa di kelas baru
                            if ($kelas_id) {
                                $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
                                error_log("Data siswa $index - Updated jumlah_siswa for new kelas_id: $kelas_id");
                            }
                            
                            // Jika kelas_id diubah menjadi NULL, update kelas lama
                            if ($old_kelas_id && $kelas_id === null) {
                                $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $old_kelas_id) WHERE id = $old_kelas_id");
                                error_log("Data siswa $index - Updated jumlah_siswa for old kelas_id (now NULL): $old_kelas_id");
                            }
                        } else {
                            $error_count++;
                            error_log("Data siswa $index - UPDATE failed: " . $stmt->error);
                        }
                        
                        $check_nisn->close();
                        $stmt->close();
                        continue; // Lanjut ke data berikutnya
                    }
                    
                    $check_nisn->close();
                    
                    // Jika NISN belum ada, INSERT data baru
                    error_log("Data siswa $index - NISN '$nisn_clean' is new, will INSERT");
                    // Handle kelas_id yang bisa null
                    if ($kelas_id === null) {
                        $stmt = $conn->prepare("INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, orangtua_wali, kelas_id) VALUES (?, ?, ?, ?, ?, ?, NULL)");
                        $stmt->bind_param("ssssss", 
                            $nisn_clean,
                            $nama_clean,
                            $jenis_kelamin,
                            $tempat_lahir_null,
                            $tanggal_lahir_null,
                            $orangtua_wali_null
                        );
                    } else {
                        $stmt = $conn->prepare("INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, orangtua_wali, kelas_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssi", 
                            $nisn_clean,
                            $nama_clean,
                            $jenis_kelamin,
                            $tempat_lahir_null,
                            $tanggal_lahir_null,
                            $orangtua_wali_null,
                            $kelas_id
                        );
                    }
                    
                    if ($stmt->execute()) {
                        $success_count++;
                        error_log("Data siswa $index - INSERT successful: $nama_clean");
                        // Update jumlah siswa di kelas
                        if ($kelas_id) {
                            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
                        }
                    } else {
                        $error_count++;
                        error_log("Data siswa $index - INSERT failed: " . $stmt->error);
                        error_log("Data siswa $index - SQL: INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, kelas_id) VALUES ('$nisn_clean', '$nama_clean', '$jenis_kelamin', " . ($tempat_lahir_null ?? 'NULL') . ", " . ($tanggal_lahir_null ?? 'NULL') . ", " . ($kelas_id ?? 'NULL') . ")");
                    }
                    
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    $error_count++;
                    error_log("Data siswa $index - SQL Exception: " . $e->getMessage());
                    error_log("Data siswa $index - SQL Code: " . $e->getCode());
                } catch (Exception $e) {
                    $error_count++;
                    error_log("Data siswa $index - Exception: " . $e->getMessage());
                }
            }
            
            // Hapus file temporary
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Debug: Log hasil akhir
            error_log("Import siswa completed - Success: $success_count (Insert: " . ($success_count - $update_count) . ", Update: $update_count), Error: $error_count");
            
            // Clear output buffer sebelum mengirim JSON
            ob_end_clean();
            
            // Buat pesan yang lebih informatif
            $message = '';
            $insert_count = $success_count - $update_count;
            
            if ($success_count > 0) {
                $message = "Berhasil mengimpor $success_count data siswa";
                if ($insert_count > 0 && $update_count > 0) {
                    $message .= " ($insert_count data baru, $update_count data diupdate)";
                } else if ($update_count > 0) {
                    $message .= " ($update_count data diupdate)";
                } else if ($insert_count > 0) {
                    $message .= " ($insert_count data baru)";
                }
                if ($error_count > 0) {
                    $message .= ". $error_count data gagal.";
                }
            } else {
                $message = "Tidak ada data yang berhasil diimpor";
                if ($error_count > 0) {
                    $message .= ". $error_count data gagal.";
                }
                if ($error_count == 0) {
                    $message .= " Pastikan file Excel memiliki format yang benar dan minimal 5 kolom (NISN, Nama, Jenis Kelamin, Tempat Lahir, Tanggal Lahir).";
                }
            }
            
            // Ambil kelas_id dari data yang berhasil diimport untuk redirect
            $imported_kelas_ids = [];
            if ($success_count > 0) {
                // Ambil kelas_id dari data yang baru diimport (ambil dari 20 data terakhir yang memiliki kelas_id)
                // Gunakan subquery untuk menghindari error DISTINCT dengan ORDER BY
                $check_imported = $conn->query("SELECT DISTINCT kelas_id FROM (
                    SELECT kelas_id FROM siswa 
                    WHERE kelas_id IS NOT NULL 
                    ORDER BY id DESC 
                    LIMIT 20
                ) AS recent_siswa");
                if ($check_imported && $check_imported->num_rows > 0) {
                    while ($row_imported = $check_imported->fetch_assoc()) {
                        if (!in_array($row_imported['kelas_id'], $imported_kelas_ids)) {
                            $imported_kelas_ids[] = (int)$row_imported['kelas_id'];
                        }
                    }
                }
                error_log("Kelas IDs dari data yang baru diimport: " . json_encode($imported_kelas_ids));
            }
            
            // Clear output buffer sebelum mengirim JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Pastikan header belum dikirim
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-cache, must-revalidate');
            }
            
            // Kirim JSON response
            echo json_encode([
                'success' => $success_count > 0,
                'message' => $message,
                'success_count' => $success_count,
                'error_count' => $error_count,
                'duplicate_count' => $update_count, // Jumlah data yang di-update
                'update_count' => $update_count, // Jumlah data yang di-update
                'insert_count' => $success_count - $update_count, // Jumlah data baru
                'imported_kelas_ids' => $imported_kelas_ids // Kirim kelas_id yang berhasil diimport
            ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            // Pastikan exit
            exit;
            
        } catch (Exception $e) {
            // Log error untuk debugging
            error_log('Import Siswa Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            if (isset($file_path) && file_exists($file_path)) {
                @unlink($file_path);
            }
            
            sendErrorResponse('Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        } catch (Error $e) {
            // Log fatal error
            error_log('Import Siswa Fatal Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            if (isset($file_path) && file_exists($file_path)) {
                @unlink($file_path);
            }
            
            sendErrorResponse('Fatal Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    } else {
        sendErrorResponse('Error upload file!');
    }
} else {
    sendErrorResponse('Tidak ada file yang diupload!');
}

// Pastikan tidak ada output setelah JSON
// Jika sampai di sini tanpa exit, berarti ada masalah
sendErrorResponse('Unexpected end of script');