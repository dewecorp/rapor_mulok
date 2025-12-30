<?php
// Disable error display untuk mencegah output sebelum JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set header JSON sebelum output apapun
header('Content-Type: application/json; charset=utf-8');

// Start output buffering untuk menangkap error
ob_start();

// Fungsi untuk mengirim error response
function sendErrorResponse($message, $code = 500) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'success_count' => 0,
        'error_count' => 0,
        'duplicate_count' => 0
    ], JSON_UNESCAPED_UNICODE);
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
    requireRole('proktor');
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
        
        $file_path = $upload_dir . 'import_guru_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            sendErrorResponse('Gagal memindahkan file. Periksa permission folder uploads/temp.');
        }
        
        // Baca file Excel/CSV
        $data = [];
        $success_count = 0;
        $error_count = 0;
        $duplicate_count = 0;
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
                
                // Debug: Log informasi Excel
                error_log("Excel file - Highest Row: $highestRow, Highest Column: $highestColumn (Index: $highestColumnIndex)");
                
                // Baca semua baris dengan eksplisit - baca sampai kolom H (index 8) untuk memastikan semua kolom terbaca
                $maxColumns = max(8, $highestColumnIndex); // Minimal 8 kolom
                $rows = [];
                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($colIndex = 1; $colIndex <= $maxColumns; $colIndex++) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                        $cell = $worksheet->getCell($colLetter . $row);
                        $cellValue = $cell->getValue();
                        
                        // Kolom D (index 4) adalah kolom tanggal lahir - perlu penanganan khusus
                        $isTanggalLahirColumn = ($colIndex == 4);
                        
                        // Jika cell adalah tanggal Excel, konversi ke format string
                        if ($cellValue !== null) {
                            // Cek apakah cell adalah tanggal dengan beberapa metode
                            $isDate = false;
                            $dateValue = null;
                            
                            // Metode 1: Cek menggunakan isDateTime()
                            try {
                                if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                                    $isDate = true;
                                    $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                                    error_log("Row $row, Col $colLetter: Detected as date using isDateTime()");
                                }
                            } catch (Exception $e) {
                                error_log("Row $row, Col $colLetter: isDateTime() error: " . $e->getMessage());
                            }
                            
                            // Metode 2: Cek jika nilai numerik dan dalam range tanggal Excel (1 = 1900-01-01)
                            // Untuk kolom tanggal lahir, lebih agresif dalam mendeteksi tanggal
                            if (!$isDate && is_numeric($cellValue)) {
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
                            
                            // Metode 3: Cek format cell (jika cell diformat sebagai tanggal)
                            if (!$isDate) {
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
                    }
                    $rows[] = $rowData;
                }
                
                // Debug: Log jumlah baris yang dibaca
                error_log("Total rows read from Excel: " . count($rows));
                
                // Skip header (baris pertama)
                array_shift($rows);
                
                // Debug: Log jumlah baris yang dibaca
                error_log('Total rows after header skip: ' . count($rows));
                
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
                
                foreach ($rows as $rowIndex => $row) {
                    // Skip baris yang benar-benar kosong (semua cell kosong)
                    if (empty($row) || !is_array($row)) {
                        error_log("Row $rowIndex skipped: empty or not array");
                        continue;
                    }
                    
                    // Pastikan row adalah array dan reset keys
                    $row = array_values($row); // Reset array keys
                    
                    // Filter baris kosong (semua cell kosong atau hanya whitespace)
                    $row_has_data = false;
                    foreach ($row as $cell) {
                        if (!empty(trim($cell ?? ''))) {
                            $row_has_data = true;
                            break;
                        }
                    }
                    
                    if (!$row_has_data) {
                        error_log("Row $rowIndex skipped: all cells empty");
                        continue;
                    }
                    
                    // Debug: Log jumlah kolom
                    error_log("Row $rowIndex has " . count($row) . " columns");
                    
                    // Pastikan minimal ada 8 kolom, jika kurang tambahkan kolom kosong
                    while (count($row) < 8) {
                        $row[] = '';
                    }
                    
                    // Sanitize dan trim data - HAPUS semua karakter yang tidak valid
                    $nama = trim($row[0] ?? '');
                    $nuptk = trim($row[5] ?? '');
                    
                    // Bersihkan data dari karakter kontrol dan karakter berbahaya
                    $nama = preg_replace('/[\x00-\x1F\x7F]/', '', $nama); // Hapus karakter kontrol
                    $nama = filter_var($nama, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
                    $nuptk = preg_replace('/[\x00-\x1F\x7F]/', '', $nuptk);
                    $nuptk = filter_var($nuptk, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
                    
                    // Debug: Log data yang dibaca
                    error_log("Row $rowIndex - Nama: '$nama', NUPTK: '$nuptk'");
                    
                    // Skip jika nama atau NUPTK kosong (kolom wajib)
                    if (empty($nama) || empty($nuptk)) {
                        error_log("Row $rowIndex skipped: empty nama or nuptk");
                        continue;
                    }
                    
                    // Normalisasi data dengan sanitasi
                    $jenis_kelamin_raw = trim($row[1] ?? 'L');
                    $jenis_kelamin_raw = preg_replace('/[\x00-\x1F\x7F]/', '', $jenis_kelamin_raw);
                    $jenis_kelamin = $normalizeJenisKelamin($jenis_kelamin_raw);
                    
                    // Normalisasi tanggal lahir (kolom index 3, Excel column D)
                    // Catatan: Tanggal sudah dikonversi ke YYYY-MM-DD saat membaca Excel
                    $tanggal_lahir_raw = $row[3] ?? '';
                    
                    // Debug: Log nilai tanggal yang dibaca
                    error_log("Row $rowIndex - Tanggal lahir raw: '$tanggal_lahir_raw'");
                    
                    $tanggal_lahir = $normalizeTanggal($tanggal_lahir_raw);
                    
                    // Debug: Log hasil normalisasi
                    error_log("Row $rowIndex - Tanggal lahir normalized: " . ($tanggal_lahir ?? 'NULL'));
                    
                    // Sanitize semua field lainnya
                    $tempat_lahir = trim($row[2] ?? '');
                    $tempat_lahir = preg_replace('/[\x00-\x1F\x7F]/', '', $tempat_lahir);
                    $tempat_lahir = filter_var($tempat_lahir, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
                    
                    $pendidikan = trim($row[4] ?? '');
                    $pendidikan = preg_replace('/[\x00-\x1F\x7F]/', '', $pendidikan);
                    $pendidikan = filter_var($pendidikan, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
                    
                    // Baca password dari kolom 6 (index 6) - simpan apa adanya, kosong jika kosong
                    $password = isset($row[6]) ? trim((string)$row[6]) : '';
                    // Hapus karakter kontrol
                    if ($password) {
                        $password = preg_replace('/[\x00-\x1F\x7F]/', '', $password);
                    }
                    
                    $role = trim($row[7] ?? 'guru');
                    $role = preg_replace('/[\x00-\x1F\x7F]/', '', $role);
                    
                    $data[] = [
                        'nama' => $nama,
                        'jenis_kelamin' => $jenis_kelamin,
                        'tempat_lahir' => $tempat_lahir,
                        'tanggal_lahir' => $tanggal_lahir,
                        'pendidikan' => $pendidikan,
                        'nuptk' => $nuptk,
                        'password' => $password,
                        'role' => $role
                    ];
                    
                    error_log("Row $rowIndex added to data array");
                }
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                throw new Exception('Gagal membaca file Excel: ' . $e->getMessage());
            }
            
            // Cek apakah kolom NUPTK dan pendidikan ada
            $check_columns = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'nuptk'");
            if ($check_columns->num_rows == 0) {
                $conn->query("ALTER TABLE pengguna ADD COLUMN nuptk VARCHAR(50) UNIQUE AFTER username");
            }
            $check_pendidikan = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'pendidikan'");
            if ($check_pendidikan->num_rows == 0) {
                $conn->query("ALTER TABLE pengguna ADD COLUMN pendidikan VARCHAR(100) AFTER tanggal_lahir");
            }
            
            // Debug: Log jumlah data yang akan diimport
            error_log('Total data to import: ' . count($data));
            
            // Import data
            foreach ($data as $index => $row_data) {
                error_log("Processing data $index: " . json_encode($row_data));
                
                if (empty($row_data['nama']) || empty($row_data['nuptk'])) {
                    error_log("Data $index skipped: empty nama or nuptk");
                    $error_count++;
                    continue;
                }
                
                // Trim data sebelum insert
                $nama = trim($row_data['nama']);
                $nuptk_clean = trim($row_data['nuptk']);
                $username = $nuptk_clean; // Username sama dengan NUPTK
                
                // Prepare data
                $tempat_lahir_null = empty($row_data['tempat_lahir']) ? null : $row_data['tempat_lahir'];
                $tanggal_lahir_null = empty($row_data['tanggal_lahir']) ? null : $row_data['tanggal_lahir'];
                $pendidikan_null = empty($row_data['pendidikan']) ? null : $row_data['pendidikan'];
                $role = in_array($row_data['role'], ['guru', 'wali_kelas', 'proktor']) ? $row_data['role'] : 'guru';
                
                // Pastikan jenis_kelamin hanya 'L' atau 'P'
                $jenis_kelamin = in_array($row_data['jenis_kelamin'], ['L', 'P']) ? $row_data['jenis_kelamin'] : 'L';
                
                try {
                    // Cek apakah NUPTK sudah ada
                    $check_nuptk = $conn->prepare("SELECT id, password FROM pengguna WHERE nuptk = ?");
                    $check_nuptk->bind_param("s", $nuptk_clean);
                    $check_nuptk->execute();
                    $result_check = $check_nuptk->get_result();
                    
                    if ($result_check->num_rows > 0) {
                        // Jika sudah ada, UPDATE (gunakan password yang ada di tabel jika password Excel kosong)
                        $existing_user = $result_check->fetch_assoc();
                        $user_id = $existing_user['id'];
                        $existing_password = $existing_user['password'];
                        
                        // Jangan update jika user adalah proktor utama
                        $check_proktor = $conn->prepare("SELECT is_proktor_utama FROM pengguna WHERE id = ?");
                        $check_proktor->bind_param("i", $user_id);
                        $check_proktor->execute();
                        $result_proktor = $check_proktor->get_result();
                        $proktor_data = $result_proktor->fetch_assoc();
                        
                        if ($proktor_data && $proktor_data['is_proktor_utama'] == 1) {
                            error_log("Data $index skipped: Cannot update proktor utama - " . $nuptk_clean);
                            $error_count++;
                            continue;
                        }
                        
                        // Logika sederhana: SELALU update password jika ada di Excel (tanpa pengecekan)
                        $password_excel = isset($row_data['password']) ? trim((string)$row_data['password']) : '';
                        
                        // SELALU update password jika ada nilai (tanpa pengecekan kompleks)
                        if ($password_excel) {
                            $password_to_use = password_hash($password_excel, PASSWORD_DEFAULT);
                        } else {
                            $password_to_use = $existing_password;
                        }
                        
                        // UPDATE data yang sudah ada - PASTIKAN password selalu di-update jika ada di Excel
                        $foto_default = 'default.png';
                        $stmt = $conn->prepare("UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?, username=?, password=?, foto=?, role=? WHERE nuptk=? AND is_proktor_utama=0");
                        if (!$stmt) {
                            $error_count++;
                            error_log("Data $index prepare UPDATE failed: " . $conn->error);
                            continue;
                        }
                        $stmt->bind_param("ssssssssss", 
                            $nama,
                            $jenis_kelamin,
                            $tempat_lahir_null,
                            $tanggal_lahir_null,
                            $pendidikan_null,
                            $username,
                            $password_to_use,
                            $foto_default,
                            $role,
                            $nuptk_clean
                        );
                        
                        if ($stmt->execute()) {
                            $success_count++;
                            $duplicate_count++; // Hitung sebagai duplicate yang di-replace
                            error_log("Data $index updated successfully - NUPTK: {$nuptk_clean}, Password updated: " . ($password_excel ? 'YES' : 'NO'));
                        } else {
                            $error_count++;
                            error_log("Data $index update failed: " . $stmt->error);
                        }
                    } else {
                        // Jika belum ada, INSERT data baru
                        // Hash password untuk data baru
                        // Jika password kosong, gunakan default '123456' untuk sementara (nanti proktor akan ubah manual)
                        if (!empty($row_data['password'])) {
                            $password = password_hash($row_data['password'], PASSWORD_DEFAULT);
                        } else {
                            // Password kosong, gunakan default '123456' untuk sementara (nanti proktor akan ubah manual)
                            $password = password_hash('123456', PASSWORD_DEFAULT);
                        }
                        
                        $foto_default = 'default.png';
                        $stmt = $conn->prepare("INSERT INTO pengguna (nama, jenis_kelamin, tempat_lahir, tanggal_lahir, pendidikan, username, nuptk, password, foto, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssssss", 
                            $nama,
                            $jenis_kelamin,
                            $tempat_lahir_null,
                            $tanggal_lahir_null,
                            $pendidikan_null,
                            $username,
                            $nuptk_clean,
                            $password,
                            $foto_default,
                            $role
                        );
                        
                        if ($stmt->execute()) {
                            $success_count++;
                            error_log("Data $index inserted successfully: " . $nama);
                        } else {
                            $error_count++;
                            error_log("Data $index insert failed: " . $stmt->error);
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    $error_count++;
                    error_log("Data $index SQL Exception: " . $e->getMessage());
                } catch (Exception $e) {
                    $error_count++;
                    error_log("Data $index Exception: " . $e->getMessage());
                }
            }
            
            // Hapus file temporary
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // Debug: Log hasil akhir
            error_log("Import completed - Success: $success_count, Error: $error_count, Duplicate: $duplicate_count");
            
            // Clear output buffer sebelum mengirim JSON
            ob_end_clean();
            
            // Buat pesan yang lebih informatif
            $message = '';
            if ($success_count > 0) {
                $message = "Berhasil mengimpor $success_count data guru";
                if ($duplicate_count > 0) {
                    $message .= " ($duplicate_count data diupdate karena duplikat)";
                }
                if ($error_count > 0) {
                    $message .= ". $error_count data gagal.";
                }
            } else {
                $message = "Tidak ada data yang berhasil diimpor";
                if ($error_count > 0) {
                    $message .= ". $error_count data gagal.";
                }
                if ($duplicate_count > 0) {
                    $message .= " $duplicate_count data duplikat.";
                }
                if ($error_count == 0 && $duplicate_count == 0) {
                    $message .= " Pastikan file Excel memiliki format yang benar dan minimal 6 kolom (Nama, Jenis Kelamin, Tempat Lahir, Tanggal Lahir, Pendidikan, NUPTK).";
                }
            }
            
            echo json_encode([
                'success' => $success_count > 0,
                'message' => $message,
                'success_count' => $success_count,
                'error_count' => $error_count,
                'duplicate_count' => $duplicate_count
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            // Log error untuk debugging
            error_log('Import Guru Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            sendErrorResponse('Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        } catch (Error $e) {
            // Log fatal error
            error_log('Import Guru Fatal Error: ' . $e->getMessage());
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
ob_end_flush();
?>

