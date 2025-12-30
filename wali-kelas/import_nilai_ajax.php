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
        'error_count' => 0
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    // Pastikan exit
    exit;
}

// Error handler untuk menangkap error PHP (hanya error yang fatal)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Hanya tangkap error yang fatal, bukan warning atau notice
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log("PHP Fatal Error [$errno]: $errstr in $errfile on line $errline");
        sendErrorResponse("Terjadi kesalahan sistem: $errstr");
    }
    return false;
});

// Exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage());
    sendErrorResponse("Terjadi kesalahan: " . $exception->getMessage());
});

require_once '../config/config.php';
require_once '../config/database.php';

// Cek session dan role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'wali_kelas') {
    sendErrorResponse('Akses ditolak. Silakan login terlebih dahulu.', 403);
}

$user_id = $_SESSION['user_id'];

// Cek apakah request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method tidak diizinkan', 405);
}

// Cek apakah ada file yang diupload
if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] != 0) {
    sendErrorResponse('File tidak ditemukan atau terjadi error saat upload');
}

$file = $_FILES['file_excel'];
$materi_id = isset($_POST['materi_id']) ? intval($_POST['materi_id']) : 0;
$kelas_nama = isset($_POST['kelas_nama']) ? trim($_POST['kelas_nama']) : '';

if ($materi_id <= 0) {
    sendErrorResponse('Parameter materi_id tidak valid');
}

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
$error_count = 0;
$errors = [];

try {
    require_once '../vendor/autoload.php';
    
    $conn = getConnection();
    
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
        unlink($file_path);
        sendErrorResponse('Data materi tidak ditemukan');
    }
    
    // Ambil nama materi dan kategori dengan case yang benar
    // Cek kolom kategori
    $use_kategori = false;
    try {
        $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
        $use_kategori = ($check_column && $check_column->num_rows > 0);
    } catch (Exception $e) {
        $use_kategori = false;
    }
    $kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
    
    $stmt_nama_binary = $conn->prepare("SELECT BINARY nama_mulok as nama_mulok, BINARY $kolom_kategori as kategori FROM materi_mulok WHERE id = ?");
    $stmt_nama_binary->bind_param("i", $materi_id);
    $stmt_nama_binary->execute();
    $result_nama_binary = $stmt_nama_binary->get_result();
    if ($result_nama_binary && $result_nama_binary->num_rows > 0) {
        $row_nama = $result_nama_binary->fetch_assoc();
        $materi_data['nama_mulok'] = (string)$row_nama['nama_mulok'];
        $materi_data['kategori'] = isset($row_nama['kategori']) ? (string)$row_nama['kategori'] : '';
    }
    
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
    
    // Cek apakah nilai sudah dikirim
    if ($kelas_id_for_materi > 0) {
        try {
            // Ambil semester dan tahun ajaran dulu untuk cek status
            $profil_temp = null;
            $semester_temp = '1';
            $tahun_ajaran_temp = '';
            try {
                $query_profil_temp = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
                $result_profil_temp = $conn->query($query_profil_temp);
                if ($result_profil_temp && $result_profil_temp->num_rows > 0) {
                    $profil_temp = $result_profil_temp->fetch_assoc();
                    $semester_temp = $profil_temp['semester_aktif'] ?? '1';
                    $tahun_ajaran_temp = $profil_temp['tahun_ajaran_aktif'] ?? '';
                }
            } catch (Exception $e) {
                // Use default
            }
            
            $stmt_check_status = $conn->prepare("SELECT status FROM status_kirim_nilai 
                                                WHERE materi_mulok_id = ? 
                                                AND kelas_id = ? 
                                                AND guru_id = ? 
                                                AND semester = ? 
                                                AND tahun_ajaran = ?");
            $stmt_check_status->bind_param("iiiss", $materi_id, $kelas_id_for_materi, $user_id, $semester_temp, $tahun_ajaran_temp);
            $stmt_check_status->execute();
            $result_check_status = $stmt_check_status->get_result();
            if ($result_check_status && $result_check_status->num_rows > 0) {
                $status_row = $result_check_status->fetch_assoc();
                if (intval($status_row['status']) == 1) {
                    unlink($file_path);
                    sendErrorResponse('Nilai sudah dikirim! Silakan batal kirim terlebih dahulu untuk mengimpor nilai.');
                }
            }
        } catch (Exception $e) {
            // Ignore error, lanjutkan proses
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
    
    // Baca file Excel menggunakan PhpSpreadsheet
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $spreadsheet = $reader->load($file_path);
    $worksheet = $spreadsheet->getActiveSheet();
    
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    
    // Skip header (baris pertama)
    for ($row = 2; $row <= $highestRow; $row++) {
        $nisn = trim($worksheet->getCell('B' . $row)->getValue() ?? '');
        $nilai_str = trim($worksheet->getCell('E' . $row)->getValue() ?? '');
        
        if (empty($nisn)) {
            continue; // Skip baris kosong
        }
        
        // Validasi nilai
        $nilai = null;
        if (!empty($nilai_str)) {
            $nilai_float = floatval($nilai_str);
            if ($nilai_float >= 0 && $nilai_float <= 100) {
                $nilai = $nilai_float;
            } else {
                $errors[] = "Baris $row: Nilai '$nilai_str' tidak valid (harus 0-100)";
                $error_count++;
                continue;
            }
        }
        
        // Cari siswa berdasarkan NISN
        $stmt_siswa = $conn->prepare("SELECT id FROM siswa WHERE nisn = ? AND kelas_id = ? LIMIT 1");
        $stmt_siswa->bind_param("si", $nisn, $kelas_id_for_materi);
        $stmt_siswa->execute();
        $result_siswa = $stmt_siswa->get_result();
        
        if (!$result_siswa || $result_siswa->num_rows == 0) {
            $errors[] = "Baris $row: Siswa dengan NISN '$nisn' tidak ditemukan di kelas ini";
            $error_count++;
            continue;
        }
        
        $siswa = $result_siswa->fetch_assoc();
        $siswa_id = $siswa['id'];
        
        if ($nilai !== null) {
            // Hitung predikat dan deskripsi
            $predikat = '';
            if ($nilai <= 60) {
                $predikat = 'D';
            } elseif ($nilai <= 69) {
                $predikat = 'C';
            } elseif ($nilai <= 89) {
                $predikat = 'B';
            } elseif ($nilai <= 100) {
                $predikat = 'A';
            }
            
            $deskripsi = '';
            if (!empty($predikat)) {
                // Ambil kategori dan nama materi
                $kategori_materi = isset($materi_data['kategori']) ? $materi_data['kategori'] : '';
                $nama_materi = $materi_data['nama_mulok'];
                
                // Gabungkan kategori dan nama materi
                $kategori_display = !empty($kategori_materi) ? trim($kategori_materi) . ' ' : '';
                $materi_display = trim($nama_materi);
                $full_materi = trim($kategori_display . $materi_display);
                
                switch ($predikat) {
                    case 'A':
                        $deskripsi = 'Sangat baik dalam ' . $full_materi;
                        break;
                    case 'B':
                        $deskripsi = 'Baik dalam ' . $full_materi;
                        break;
                    case 'C':
                        $deskripsi = 'Cukup dalam ' . $full_materi;
                        break;
                    case 'D':
                        $deskripsi = 'Kurang dalam ' . $full_materi;
                        break;
                }
            }
            
            // Cek apakah nilai sudah ada
            $stmt_check = $conn->prepare("SELECT id FROM nilai_siswa 
                                         WHERE siswa_id = ? AND materi_mulok_id = ? AND semester = ? AND tahun_ajaran = ?");
            $stmt_check->bind_param("iiss", $siswa_id, $materi_id, $semester, $tahun_ajaran);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check && $result_check->num_rows > 0) {
                // Update nilai yang sudah ada
                $stmt_update = $conn->prepare("UPDATE nilai_siswa 
                                              SET nilai_pengetahuan = ?, predikat = ?, deskripsi = ? 
                                              WHERE siswa_id = ? AND materi_mulok_id = ? AND semester = ? AND tahun_ajaran = ?");
                $stmt_update->bind_param("dssiiss", $nilai, $predikat, $deskripsi, $siswa_id, $materi_id, $semester, $tahun_ajaran);
                if ($stmt_update->execute()) {
                    $success_count++;
                } else {
                    $errors[] = "Baris $row: Gagal update nilai untuk NISN '$nisn'";
                    $error_count++;
                }
            } else {
                // Insert nilai baru
                $stmt_insert = $conn->prepare("INSERT INTO nilai_siswa 
                                              (siswa_id, materi_mulok_id, semester, tahun_ajaran, nilai_pengetahuan, predikat, deskripsi) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("iissdss", $siswa_id, $materi_id, $semester, $tahun_ajaran, $nilai, $predikat, $deskripsi);
                if ($stmt_insert->execute()) {
                    $success_count++;
                } else {
                    $errors[] = "Baris $row: Gagal insert nilai untuk NISN '$nisn'";
                    $error_count++;
                }
            }
        }
    }
    
    $conn->close();
    
    // Hapus file temporary
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Bersihkan output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Kirim response
    echo json_encode([
        'success' => true,
        'message' => "Import selesai. Berhasil: $success_count, Gagal: $error_count",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Hapus file temporary jika ada
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    error_log("Import Error: " . $e->getMessage());
    sendErrorResponse('Terjadi kesalahan saat memproses file: ' . $e->getMessage());
}
?>

