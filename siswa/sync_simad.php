<?php
// Mencegah output apapun sebelum header JSON
ob_start();

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/simad.php';

// Hapus output apapun yang mungkin terjadi saat include (spasi, BOM, dll)
ob_clean();

// Only proktor can sync
if (!hasRole('proktor')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses!']);
    exit;
}

$conn = getConnection();
// Coba ambil dari POST, jika tidak ada coba dari REQUEST
$kelas_id_raw = isset($_POST['kelas_id']) ? $_POST['kelas_id'] : (isset($_REQUEST['kelas_id']) ? $_REQUEST['kelas_id'] : '');

if ($kelas_id_raw === '') {
    // Log error ke file untuk debugging jika memungkinkan
    $debug_info = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'post' => $_POST,
        'get' => $_GET,
        'request' => $_REQUEST,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
    ];
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID Kelas tidak terkirim! Detail Debug: ' . json_encode($debug_info)]);
    exit;
}

// Ambil nama kelas untuk parameter pencarian di SIMAD
// Gunakan prepared statement untuk mencari ID baik sebagai integer maupun string
$stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE id = ? OR nama_kelas = ?");
$stmt_kelas->bind_param("ss", $kelas_id_raw, $kelas_id_raw);
$stmt_kelas->execute();
$res_kelas = $stmt_kelas->get_result();
$kelas_data = $res_kelas->fetch_assoc();

if (!$kelas_data) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Kelas tidak valid atau tidak ditemukan di database (ID/Nama: ' . htmlspecialchars($kelas_id_raw) . ')!']);
    exit;
}

$kelas_id = $kelas_data['id']; // ID asli dari database (bisa int atau string)
$nama_kelas_target = trim($kelas_data['nama_kelas']);

/**
 * FUNGSI PENGAMBILAN DATA DARI SIMAD
 * Menggunakan endpoint asli yang diberikan user
 */
function fetchFromSimad() {
    $apiUrl = SIMAD_API_URL . "?api_key=" . SIMAD_API_KEY;
    
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $apiUrl); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $response = curl_exec($ch); 
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => 'CURL Error: ' . $error_msg];
    }
    
    curl_close($ch); 
    
    if (empty($response)) {
        return ['success' => false, 'message' => "Respon dari SIMAD kosong (HTTP: $httpCode). Silakan periksa koneksi internet atau API Key."];
    }
    
    $result = json_decode($response, true); 
    
    if (isset($result['status']) && $result['status'] === 'success') {
        return ['success' => true, 'data' => $result['data']];
    } 
    elseif (is_array($result) && !isset($result['status']) && count($result) > 0) {
        return ['success' => true, 'data' => $result];
    }
    else {
        error_log("SIMAD API FAIL: " . $response);
        // Pastikan tidak ada karakter HTML yang merusak JSON
        $clean_response = strip_tags(substr($response, 0, 150));
        return ['success' => false, 'message' => ($result['message'] ?? 'Gagal mengambil data dari SIMAD (Status bukan success)') . " | HTTP: $httpCode | Debug: " . $clean_response];
    }
}

try {
    $simad_response = fetchFromSimad();
    
    if (!$simad_response['success']) {
        throw new Exception($simad_response['message'] ?? 'Gagal menghubungi server SIMAD');
    }
    
    $all_students = $simad_response['data'];
    $students_in_class = [];
    
    // Filter data per kelas
    foreach ($all_students as $student) {
        if (isset($student['nama_kelas']) && trim($student['nama_kelas']) === $nama_kelas_target) {
            $students_in_class[] = $student;
        }
    }
    
    if (empty($students_in_class)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Tidak ditemukan data siswa untuk kelas ' . $nama_kelas_target . ' di sistem SIMAD.'
        ]);
        exit;
    }
    
    $success_count = 0;
    $update_count = 0;
    
    foreach ($students_in_class as $student) {
        $nisn = preg_replace('/[^0-9]/', '', $student['nisn']);
        $nama = $student['nama_siswa'];
        $jk = $student['jenis_kelamin'] ?? 'L';
        $tmp_lhr = $student['tempat_lahir'] ?? '';
        $tgl_lhr = $student['tanggal_lahir'] ?? null;
        $ortu = $student['wali'] ?? '';
        
        // Cek apakah siswa sudah ada (berdasarkan NISN)
        $check = $conn->prepare("SELECT id FROM siswa WHERE nisn = ?");
        $check->bind_param("s", $nisn);
        $check->execute();
        $res_check = $check->get_result();
        
        if ($res_check->num_rows > 0) {
            // Ambil data lama untuk pengecekan data kosong
            $stmt_old = $conn->prepare("SELECT * FROM siswa WHERE nisn = ?");
            $stmt_old->bind_param("s", $nisn);
            $stmt_old->execute();
            $existing = $stmt_old->get_result()->fetch_assoc();
            $id = $existing['id'];
            
            // Fungsi pembantu untuk mengecek apakah data dari SIMAD "bernilai" (bukan kosong atau hanya tanda strip)
            $isValid = function($value) {
                $v = trim($value);
                return !empty($v) && $v !== '-' && $v !== '--' && $v !== '0';
            };
            
            // Logika Proteksi: Gunakan data SIMAD hanya jika datanya valid/lengkap
            // Jika data di SIMAD kosong atau hanya "-", tetap gunakan data lama yang sudah lengkap di Rapor
            $final_nama = $isValid($nama) ? $nama : $existing['nama'];
            $final_jk = $isValid($jk) ? $jk : $existing['jenis_kelamin'];
            $final_tmp_lhr = $isValid($tmp_lhr) ? $tmp_lhr : $existing['tempat_lahir'];
            $final_tgl_lhr = ($isValid($tgl_lhr) && $tgl_lhr !== '0000-00-00') ? $tgl_lhr : $existing['tanggal_lahir'];
            $final_ortu = $isValid($ortu) ? $ortu : $existing['orangtua_wali'];
            
            $type_kelas = is_numeric($kelas_id) ? "i" : "s";
            $stmt = $conn->prepare("UPDATE siswa SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, orangtua_wali=?, kelas_id=? WHERE id=?");
            $stmt->bind_param("sssss" . $type_kelas . "i", $final_nama, $final_jk, $final_tmp_lhr, $final_tgl_lhr, $final_ortu, $kelas_id, $id);
            if ($stmt->execute()) {
                $update_count++;
            }
        } else {
            // Insert siswa baru
            $type_kelas = is_numeric($kelas_id) ? "i" : "s";
            $stmt = $conn->prepare("INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, orangtua_wali, kelas_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss" . $type_kelas, $nisn, $nama, $jk, $tmp_lhr, $tgl_lhr, $ortu, $kelas_id);
            if ($stmt->execute()) {
                $success_count++;
            }
        }
    }
    
    // Update jumlah siswa di kelas
    $update_query = is_numeric($kelas_id) ? 
        "UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id" :
        "UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = '$kelas_id') WHERE id = '$kelas_id'";
    $conn->query($update_query);
    
    // Log Aktivitas
    $deskripsi = "Sinkronisasi SIMAD untuk kelas $nama_kelas_target: $success_count baru, $update_count diperbarui.";
    $user_id = $_SESSION['user_id'];
    $user_nama = $_SESSION['nama'];
    $user_role = $_SESSION['role'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Pastikan tabel log ada
    $conn->query("CREATE TABLE IF NOT EXISTS `aktivitas_pengguna` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `nama` varchar(255) NOT NULL,
        `role` varchar(50) NOT NULL,
        `jenis_aktivitas` varchar(50) NOT NULL,
        `deskripsi` text DEFAULT NULL,
        `tabel_target` varchar(100) DEFAULT NULL,
        `record_id` int(11) DEFAULT NULL,
        `ip_address` varchar(50) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `waktu` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt_log = $conn->prepare("INSERT INTO aktivitas_pengguna (user_id, nama, role, jenis_aktivitas, deskripsi, tabel_target, ip_address) VALUES (?, ?, ?, 'sync', ?, 'siswa', ?)");
    $stmt_log->bind_param("issss", $user_id, $user_nama, $user_role, $deskripsi, $ip);
    $stmt_log->execute();
    
    // Matikan buffer dan buang semua output (warning/notice) yang mungkin muncul selama proses
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Kirim header JSON di akhir untuk memastikan tidak terganggu
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => true, 
        'message' => "Sinkronisasi Selesai untuk kelas $nama_kelas_target! $success_count siswa baru ditambahkan, $update_count siswa diperbarui."
    ]);
    exit;

} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    exit;
}
