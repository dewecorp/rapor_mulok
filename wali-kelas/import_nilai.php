<?php
// Disable error display untuk mencegah output sebelum JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Pastikan tidak ada output sebelumnya
while (ob_get_level() > 0) {
    ob_end_clean();
}

ob_start();

// Set header JSON sebelum output apapun
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Fungsi untuk mengirim error response
function sendErrorResponse($message, $code = 500) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    echo json_encode([
        'success' => false,
        'message' => $message,
        'success_count' => 0,
        'failed_count' => 0
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    exit;
}

// Fungsi untuk menghitung predikat berdasarkan nilai
function hitungPredikat($nilai) {
    $nilai_float = floatval($nilai);
    if ($nilai_float <= 60) return 'D';
    elseif ($nilai_float <= 69) return 'C';
    elseif ($nilai_float <= 89) return 'B';
    elseif ($nilai_float <= 100) return 'A';
    return '-';
}

// Fungsi untuk menghitung deskripsi berdasarkan predikat dan nama materi
function hitungDeskripsi($predikat, $nama_materi) {
    if (empty($predikat) || $predikat == '-') return '-';
    
    switch ($predikat) {
        case 'A':
            return 'Sangat baik dalam ' . $nama_materi;
        case 'B':
            return 'Baik dalam ' . $nama_materi;
        case 'C':
            return 'Cukup dalam ' . $nama_materi;
        case 'D':
            return 'Kurang dalam ' . $nama_materi;
        default:
            return '-';
    }
}

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    
    // Cek login dan role
    if (!isLoggedIn()) {
        sendErrorResponse('Anda harus login terlebih dahulu!', 401);
    }
    if (!hasRole('wali_kelas')) {
        sendErrorResponse('Anda tidak memiliki akses untuk melakukan import!', 403);
    }
} catch (Exception $e) {
    sendErrorResponse('Error loading configuration: ' . $e->getMessage());
}

try {
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
} catch (Exception $e) {
    sendErrorResponse('Error koneksi database: ' . $e->getMessage());
}

// Ambil materi_id dari POST
$materi_id = isset($_POST['materi_id']) ? intval($_POST['materi_id']) : 0;
if ($materi_id <= 0) {
    sendErrorResponse('Materi tidak ditemukan!');
}

// Ambil kelas yang diampu oleh wali kelas
$kelas_id = 0;
try {
    $stmt_kelas = $conn->prepare("SELECT id FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    $stmt_kelas->close();
} catch (Exception $e) {
    sendErrorResponse('Error: ' . $e->getMessage());
}

if ($kelas_id <= 0) {
    sendErrorResponse('Kelas tidak ditemukan!');
}

// Ambil semester aktif dan tahun ajaran aktif
$semester_aktif = '1';
$tahun_ajaran = '';
try {
    $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $semester_aktif = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    $semester_aktif = '1';
    $tahun_ajaran = '';
}

// Ambil nama materi untuk deskripsi
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
    sendErrorResponse('Error: ' . $e->getMessage());
}

// Cek apakah nilai sudah dikirim
$status_kirim_check = 'belum';
try {
    $stmt_check_kirim = $conn->prepare("SELECT status FROM nilai_kirim_status 
                                       WHERE materi_mulok_id = ? 
                                       AND kelas_id = ? 
                                       AND semester = ? 
                                       AND tahun_ajaran = ? 
                                       AND status = 'terkirim'");
    $stmt_check_kirim->bind_param("iiss", $materi_id, $kelas_id, $semester_aktif, $tahun_ajaran);
    $stmt_check_kirim->execute();
    $result_check_kirim = $stmt_check_kirim->get_result();
    if ($result_check_kirim && $result_check_kirim->num_rows > 0) {
        $status_kirim_check = 'terkirim';
    }
    $stmt_check_kirim->close();
} catch (Exception $e) {
    // Ignore error
}

if ($status_kirim_check == 'terkirim') {
    sendErrorResponse('Nilai sudah dikirim. Silakan batalkan pengiriman terlebih dahulu untuk mengubah nilai.');
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
        
        if (!is_writable($upload_dir)) {
            sendErrorResponse('Folder upload tidak dapat ditulis. Periksa permission folder uploads/temp.');
        }
        
        $file_path = $upload_dir . 'import_nilai_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            sendErrorResponse('Gagal memindahkan file. Periksa permission folder uploads/temp.');
        }
        
        // Baca file Excel
        $data = [];
        $success_count = 0;
        $failed_count = 0;
        $errors = [];
        
        try {
            require_once '../vendor/autoload.php';
            
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            
            // Baca data dari baris 2 (skip header)
            for ($row = 2; $row <= $highestRow; $row++) {
                $nisn = trim($worksheet->getCell('A' . $row)->getValue() ?? '');
                $nama_siswa = trim($worksheet->getCell('B' . $row)->getValue() ?? '');
                $nilai = trim($worksheet->getCell('C' . $row)->getValue() ?? '');
                
                // Skip baris kosong
                if (empty($nisn) && empty($nama_siswa)) {
                    continue;
                }
                
                // Validasi
                if (empty($nisn)) {
                    $errors[] = "Baris $row: NISN tidak boleh kosong";
                    $failed_count++;
                    continue;
                }
                
                if (empty($nilai) || $nilai === '') {
                    $errors[] = "Baris $row: Nilai tidak boleh kosong";
                    $failed_count++;
                    continue;
                }
                
                $nilai_float = floatval($nilai);
                if ($nilai_float < 0 || $nilai_float > 100) {
                    $errors[] = "Baris $row: Nilai harus antara 0-100";
                    $failed_count++;
                    continue;
                }
                
                $data[] = [
                    'nisn' => $nisn,
                    'nama' => $nama_siswa,
                    'nilai' => $nilai_float
                ];
            }
            
            // Hapus file temporary
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Import data
            if (!empty($data)) {
                $conn->begin_transaction();
                
                try {
                    foreach ($data as $row_data) {
                        // Cari siswa berdasarkan NISN
                        $stmt_siswa = $conn->prepare("SELECT id FROM siswa WHERE nisn = ? AND kelas_id = ? LIMIT 1");
                        $stmt_siswa->bind_param("si", $row_data['nisn'], $kelas_id);
                        $stmt_siswa->execute();
                        $result_siswa = $stmt_siswa->get_result();
                        $siswa = $result_siswa ? $result_siswa->fetch_assoc() : null;
                        $stmt_siswa->close();
                        
                        if (!$siswa) {
                            $errors[] = "NISN {$row_data['nisn']}: Siswa tidak ditemukan di kelas ini";
                            $failed_count++;
                            continue;
                        }
                        
                        $siswa_id = $siswa['id'];
                        $nilai_float = $row_data['nilai'];
                        
                        // Hitung predikat dan deskripsi
                        $predikat = hitungPredikat($nilai_float);
                        $deskripsi = hitungDeskripsi($predikat, $nama_materi);
                        
                        // Cek apakah nilai sudah ada
                        $stmt_check = $conn->prepare("SELECT id FROM nilai_siswa 
                                                     WHERE siswa_id = ? 
                                                     AND materi_mulok_id = ? 
                                                     AND kelas_id = ? 
                                                     AND semester = ? 
                                                     AND tahun_ajaran = ?");
                        $stmt_check->bind_param("iiiss", $siswa_id, $materi_id, $kelas_id, $semester_aktif, $tahun_ajaran);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        $existing = $result_check->fetch_assoc();
                        $stmt_check->close();
                        
                        if ($existing) {
                            // Update nilai yang sudah ada
                            $stmt_update = $conn->prepare("UPDATE nilai_siswa 
                                                          SET nilai_pengetahuan = ?, predikat = ?, deskripsi = ?, guru_id = ? 
                                                          WHERE id = ?");
                            $stmt_update->bind_param("dssii", $nilai_float, $predikat, $deskripsi, $user_id, $existing['id']);
                            $stmt_update->execute();
                            $stmt_update->close();
                        } else {
                            // Insert nilai baru
                            $stmt_insert = $conn->prepare("INSERT INTO nilai_siswa 
                                                           (siswa_id, materi_mulok_id, kelas_id, guru_id, semester, tahun_ajaran, nilai_pengetahuan, predikat, deskripsi) 
                                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_insert->bind_param("iiiissdss", $siswa_id, $materi_id, $kelas_id, $user_id, $semester_aktif, $tahun_ajaran, $nilai_float, $predikat, $deskripsi);
                            $stmt_insert->execute();
                            $stmt_insert->close();
                        }
                        
                        $success_count++;
                    }
                    
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    sendErrorResponse('Error saat menyimpan data: ' . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            sendErrorResponse('Error membaca file Excel: ' . $e->getMessage());
        }
        
        // Bersihkan output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Kirim response
        $message = "Berhasil mengimpor $success_count nilai";
        if ($failed_count > 0) {
            $message .= ", $failed_count gagal";
        }
        if (!empty($errors)) {
            $message .= ". Error: " . implode(', ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= ' dan ' . (count($errors) - 5) . ' error lainnya';
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'success_count' => $success_count,
            'failed_count' => $failed_count
        ], JSON_UNESCAPED_UNICODE);
        
        exit;
    }
} else {
    sendErrorResponse('File tidak ditemukan atau request tidak valid!');
}
?>

