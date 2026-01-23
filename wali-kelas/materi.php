<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$materi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Cek dan tambahkan kolom predikat dan deskripsi jika belum ada
try {
    $check_predikat = $conn->query("SHOW COLUMNS FROM nilai_siswa LIKE 'predikat'");
    if (!$check_predikat || $check_predikat->num_rows == 0) {
        $conn->query("ALTER TABLE nilai_siswa ADD COLUMN predikat VARCHAR(10) DEFAULT NULL AFTER nilai_pengetahuan");
    }
    
    $check_deskripsi = $conn->query("SHOW COLUMNS FROM nilai_siswa LIKE 'deskripsi'");
    if (!$check_deskripsi || $check_deskripsi->num_rows == 0) {
        $conn->query("ALTER TABLE nilai_siswa ADD COLUMN deskripsi TEXT DEFAULT NULL AFTER predikat");
    }
    
    // Buat tabel nilai_kirim_status jika belum ada
    $check_table = $conn->query("SHOW TABLES LIKE 'nilai_kirim_status'");
    if (!$check_table || $check_table->num_rows == 0) {
        $conn->query("CREATE TABLE nilai_kirim_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            materi_mulok_id INT NOT NULL,
            kelas_id INT NOT NULL,
            semester VARCHAR(10) NOT NULL,
            tahun_ajaran VARCHAR(20) NOT NULL,
            status ENUM('terkirim', 'batal') DEFAULT 'terkirim',
            tanggal_kirim DATETIME DEFAULT CURRENT_TIMESTAMP,
            tanggal_batal DATETIME DEFAULT NULL,
            user_id INT NOT NULL,
            INDEX idx_materi_kelas (materi_mulok_id, kelas_id, semester, tahun_ajaran),
            FOREIGN KEY (materi_mulok_id) REFERENCES materi_mulok(id) ON DELETE CASCADE,
            FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES pengguna(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
} catch (Exception $e) {
    // Ignore jika kolom/tabel sudah ada atau error lainnya
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

// Fungsi untuk menghitung deskripsi berdasarkan predikat, kategori, dan nama materi
function hitungDeskripsi($predikat, $nama_materi, $kategori = '') {
    if (empty($predikat) || $predikat == '-') return '-';
    
    // Gabungkan kategori dan nama materi jika kategori ada
    $materi_full = $nama_materi;
    if (!empty($kategori)) {
        $materi_full = $kategori . ' ' . $nama_materi;
    }
    
    switch ($predikat) {
        case 'A':
            return 'Sangat baik dalam ' . $materi_full;
        case 'B':
            return 'Baik dalam ' . $materi_full;
        case 'C':
            return 'Cukup dalam ' . $materi_full;
        case 'D':
            return 'Kurang dalam ' . $materi_full;
        default:
            return '-';
    }
}

// Handle tambah nilai
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah_nilai') {
    $materi_id_post = intval($_POST['materi_id'] ?? 0);
    $kelas_id_post = intval($_POST['kelas_id'] ?? 0);
    $semester_post = trim($_POST['semester'] ?? '1');
    $tahun_ajaran_post = trim($_POST['tahun_ajaran'] ?? '');
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    $nilai_array = $_POST['nilai'] ?? [];
    
    // Cek apakah nilai sudah dikirim
    $status_kirim_check = 'belum';
    try {
        $stmt_check_kirim = $conn->prepare("SELECT status FROM nilai_kirim_status 
                                           WHERE materi_mulok_id = ? 
                                           AND kelas_id = ? 
                                           AND semester = ? 
                                           AND tahun_ajaran = ? 
                                           AND status = 'terkirim'");
        $stmt_check_kirim->bind_param("iiss", $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post);
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
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Nilai sudah dikirim. Silakan batalkan pengiriman terlebih dahulu untuk mengubah nilai.'
        ]);
        exit;
    }
    
    if ($materi_id_post > 0 && $kelas_id_post > 0 && !empty($tahun_ajaran_post) && !empty($siswa_ids) && !empty($nilai_array)) {
        $conn->begin_transaction();
        try {
            $saved_count = 0;
            foreach ($siswa_ids as $index => $siswa_id) {
                $siswa_id = intval($siswa_id);
                $nilai_value = trim($nilai_array[$index] ?? '');
                
                if ($siswa_id > 0 && $nilai_value !== '') {
                    $nilai_float = floatval($nilai_value);
                    
                    // Hitung predikat dari nilai
                    $predikat = hitungPredikat($nilai_float);
                    
                    // Ambil nama materi dan kategori untuk deskripsi
                    $nama_materi = '';
                    $kategori_materi = '';
                    // Cek apakah kolom kategori_mulok ada
                    $has_kategori_mulok = false;
                    try {
                        $check_cols = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
                        if ($check_cols && $check_cols->num_rows > 0) {
                            $has_kategori_mulok = true;
                        }
                    } catch (Exception $e) {
                        $has_kategori_mulok = false;
                    }
                    $kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
                    $stmt_materi_nama = $conn->prepare("SELECT BINARY nama_mulok as nama_mulok, $kolom_kategori as kategori FROM materi_mulok WHERE id = ?");
                    $stmt_materi_nama->bind_param("i", $materi_id_post);
                    $stmt_materi_nama->execute();
                    $result_materi_nama = $stmt_materi_nama->get_result();
                    if ($result_materi_nama && $result_materi_nama->num_rows > 0) {
                        $materi_row = $result_materi_nama->fetch_assoc();
                        $nama_materi = isset($materi_row['nama_mulok']) ? (string)$materi_row['nama_mulok'] : '';
                        $kategori_materi = isset($materi_row['kategori']) ? (string)$materi_row['kategori'] : '';
                    }
                    $stmt_materi_nama->close();
                    
                    // Hitung deskripsi dengan kategori dan nama materi
                    $deskripsi = hitungDeskripsi($predikat, $nama_materi, $kategori_materi);
                    
                    // Cek apakah nilai sudah ada
                    $stmt_check = $conn->prepare("SELECT id FROM nilai_siswa 
                                                 WHERE siswa_id = ? 
                                                 AND materi_mulok_id = ? 
                                                 AND kelas_id = ? 
                                                 AND semester = ? 
                                                 AND tahun_ajaran = ?");
                    $stmt_check->bind_param("iiiss", $siswa_id, $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post);
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
                        $stmt_insert->bind_param("iiiissdss", $siswa_id, $materi_id_post, $kelas_id_post, $user_id, $semester_post, $tahun_ajaran_post, $nilai_float, $predikat, $deskripsi);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }
                    $saved_count++;
                }
            }
            
            $conn->commit();
            
            // Log aktivitas - setelah commit berhasil
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
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_waktu` (`waktu`),
                KEY `idx_role` (`role`),
                KEY `idx_jenis_aktivitas` (`jenis_aktivitas`),
                KEY `idx_tabel_target` (`tabel_target`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500);
            $deskripsi_text = "Menyimpan nilai untuk $saved_count siswa";
            $waktu_sekarang = date('Y-m-d H:i:s');
            
            $sql_log = "INSERT INTO aktivitas_pengguna (user_id, nama, role, jenis_aktivitas, deskripsi, tabel_target, record_id, ip_address, user_agent, waktu) 
                        VALUES (
                            " . (int)$_SESSION['user_id'] . ",
                            '" . $conn->real_escape_string($_SESSION['nama']) . "',
                            '" . $conn->real_escape_string($_SESSION['role']) . "',
                            'update',
                            '" . $conn->real_escape_string($deskripsi_text) . "',
                            'nilai_siswa',
                            NULL,
                            '" . $conn->real_escape_string($ip_address) . "',
                            '" . $conn->real_escape_string($user_agent) . "',
                            '" . $waktu_sekarang . "'
                        )";
            $conn->query($sql_log);
            
            $success_message = "Berhasil menyimpan $saved_count nilai!";
            
            // Return JSON untuk AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $success_message
            ]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = 'Gagal menyimpan nilai: ' . $e->getMessage();
            
            // Return JSON untuk AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
            exit;
        }
    } else {
        $error_message = 'Data tidak lengkap!';
        
        // Return JSON untuk AJAX request
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
        exit;
    }
}

// Handle kirim nilai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'kirim_nilai') {
    $materi_id_post = intval($_POST['materi_id'] ?? 0);
    $kelas_id_post = intval($_POST['kelas_id'] ?? 0);
    $semester_post = trim($_POST['semester'] ?? '1');
    $tahun_ajaran_post = trim($_POST['tahun_ajaran'] ?? '');
    
    if ($materi_id_post > 0 && $kelas_id_post > 0 && !empty($tahun_ajaran_post)) {
        try {
            // Validasi: Cek apakah semua siswa sudah memiliki nilai
            $stmt_siswa_check = $conn->prepare("SELECT COUNT(*) as total_siswa FROM siswa WHERE kelas_id = ?");
            $stmt_siswa_check->bind_param("i", $kelas_id_post);
            $stmt_siswa_check->execute();
            $result_siswa_check = $stmt_siswa_check->get_result();
            $row_siswa_check = $result_siswa_check->fetch_assoc();
            $total_siswa = $row_siswa_check['total_siswa'] ?? 0;
            $stmt_siswa_check->close();
            
            // Cek jumlah nilai yang sudah diinput
            $stmt_nilai_check = $conn->prepare("SELECT COUNT(DISTINCT siswa_id) as total_nilai 
                                                FROM nilai_siswa 
                                                WHERE materi_mulok_id = ? 
                                                AND siswa_id IN (SELECT id FROM siswa WHERE kelas_id = ?)
                                                AND semester = ? 
                                                AND tahun_ajaran = ? 
                                                AND nilai_pengetahuan IS NOT NULL 
                                                AND nilai_pengetahuan != ''");
            $stmt_nilai_check->bind_param("iiss", $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post);
            $stmt_nilai_check->execute();
            $result_nilai_check = $stmt_nilai_check->get_result();
            $row_nilai_check = $result_nilai_check->fetch_assoc();
            $total_nilai = $row_nilai_check['total_nilai'] ?? 0;
            $stmt_nilai_check->close();
            
            // Jika ada siswa yang belum memiliki nilai, tolak pengiriman
            if ($total_siswa > 0 && $total_nilai < $total_siswa) {
                $siswa_belum_nilai = $total_siswa - $total_nilai;
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => "Tidak dapat mengirim nilai! Masih ada $siswa_belum_nilai siswa yang belum memiliki nilai. Silakan lengkapi semua nilai terlebih dahulu."
                ]);
                exit;
            }
            
            // Cek apakah sudah ada status terkirim
            $stmt_check = $conn->prepare("SELECT id FROM nilai_kirim_status 
                                         WHERE materi_mulok_id = ? 
                                         AND kelas_id = ? 
                                         AND semester = ? 
                                         AND tahun_ajaran = ? 
                                         AND status = 'terkirim'");
            $stmt_check->bind_param("iiss", $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $existing = $result_check->fetch_assoc();
            $stmt_check->close();
            
            if ($existing) {
                // Update status menjadi terkirim
                $stmt_update = $conn->prepare("UPDATE nilai_kirim_status 
                                              SET status = 'terkirim', 
                                                  tanggal_kirim = NOW(), 
                                                  tanggal_batal = NULL,
                                                  user_id = ? 
                                              WHERE id = ?");
                $stmt_update->bind_param("ii", $user_id, $existing['id']);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                // Insert status baru
                $stmt_insert = $conn->prepare("INSERT INTO nilai_kirim_status 
                                               (materi_mulok_id, kelas_id, semester, tahun_ajaran, status, tanggal_kirim, user_id) 
                                               VALUES (?, ?, ?, ?, 'terkirim', NOW(), ?)");
                $stmt_insert->bind_param("iissi", $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post, $user_id);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Nilai berhasil dikirim!'
            ]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengirim nilai: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak lengkap!'
        ]);
        exit;
    }
}

// Handle batal kirim nilai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batal_kirim_nilai') {
    $materi_id_post = intval($_POST['materi_id'] ?? 0);
    $kelas_id_post = intval($_POST['kelas_id'] ?? 0);
    $semester_post = trim($_POST['semester'] ?? '1');
    $tahun_ajaran_post = trim($_POST['tahun_ajaran'] ?? '');
    
    if ($materi_id_post > 0 && $kelas_id_post > 0 && !empty($tahun_ajaran_post)) {
        try {
            // Update status menjadi batal
            $stmt_update = $conn->prepare("UPDATE nilai_kirim_status 
                                          SET status = 'batal', 
                                              tanggal_batal = NOW() 
                                          WHERE materi_mulok_id = ? 
                                          AND kelas_id = ? 
                                          AND semester = ? 
                                          AND tahun_ajaran = ? 
                                          AND status = 'terkirim'");
            $stmt_update->bind_param("iiss", $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post);
            $stmt_update->execute();
            $affected = $stmt_update->affected_rows;
            $stmt_update->close();
            
            if ($affected > 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Pengiriman nilai berhasil dibatalkan!'
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Status pengiriman tidak ditemukan!'
                ]);
            }
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Gagal membatalkan pengiriman: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak lengkap!'
        ]);
        exit;
    }
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

// Ambil kelas yang diampu oleh wali kelas ini
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
    $kelas_id = 0;
}

// Cek apakah kolom kategori_mulok ada
$has_kategori_mulok = false;
try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kategori_mulok') {
                $has_kategori_mulok = true;
                break;
            }
        }
    }
} catch (Exception $e) {
    $has_kategori_mulok = false;
}

// Jika materi_id ada, tampilkan tabel nilai siswa
if ($materi_id > 0 && $kelas_id > 0) {
    // Ambil data materi
    $materi_data = null;
    try {
        $kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
        $stmt_materi = $conn->prepare("SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori
                      FROM materi_mulok m
                      INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                      WHERE m.id = ? AND mm.guru_id = ? AND mm.kelas_id = ? AND m.semester = ?");
        $stmt_materi->bind_param("iiis", $materi_id, $user_id, $kelas_id, $semester_aktif);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        $materi_data = $result_materi ? $result_materi->fetch_assoc() : null;
        $stmt_materi->close();
    } catch (Exception $e) {
        $materi_data = null;
    }
    
    // Cek status kirim nilai
    $status_kirim = 'belum';
    if ($materi_data && $kelas_id > 0 && !empty($tahun_ajaran)) {
        try {
            $stmt_status = $conn->prepare("SELECT status FROM nilai_kirim_status 
                                          WHERE materi_mulok_id = ? 
                                          AND kelas_id = ? 
                                          AND semester = ? 
                                          AND tahun_ajaran = ? 
                                          AND status = 'terkirim'");
            $stmt_status->bind_param("iiss", $materi_id, $kelas_id, $semester_aktif, $tahun_ajaran);
            $stmt_status->execute();
            $result_status = $stmt_status->get_result();
            if ($result_status && $result_status->num_rows > 0) {
                $status_kirim = 'terkirim';
            }
            $stmt_status->close();
        } catch (Exception $e) {
            $status_kirim = 'belum';
        }
    }
    
    // Ambil data siswa di kelas
    $siswa_data = [];
    $nilai_data = [];
    if ($materi_data && $kelas_id > 0) {
        try {
            // Ambil siswa di kelas
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
            
            // Ambil nilai siswa untuk materi ini
            if (!empty($siswa_data) && !empty($tahun_ajaran)) {
                $siswa_ids = array_column($siswa_data, 'id');
                if (!empty($siswa_ids)) {
                    $placeholders = str_repeat('?,', count($siswa_ids) - 1) . '?';
                    $query_nilai = "SELECT * FROM nilai_siswa 
                                   WHERE siswa_id IN ($placeholders) 
                                   AND materi_mulok_id = ? 
                                   AND semester = ? 
                                   AND tahun_ajaran = ?";
                    $stmt_nilai = $conn->prepare($query_nilai);
                    $params = array_merge($siswa_ids, [$materi_id, $semester_aktif, $tahun_ajaran]);
                    $types = str_repeat('i', count($siswa_ids)) . 'iss';
                    $stmt_nilai->bind_param($types, ...$params);
                    $stmt_nilai->execute();
                    $result_nilai = $stmt_nilai->get_result();
                    
                    if ($result_nilai) {
                        while ($nilai = $result_nilai->fetch_assoc()) {
                            $nilai_data[$nilai['siswa_id']] = $nilai;
                        }
                    }
                    $stmt_nilai->close();
                }
            }
        } catch (Exception $e) {
            $siswa_data = [];
            $nilai_data = [];
        }
    }
} else {
    // Jika tidak ada materi_id, tampilkan daftar materi
    $materi_data = null;
    $siswa_data = [];
    $nilai_data = [];
    
    // Ambil materi yang diampu oleh wali kelas ini di kelas tersebut dengan filter semester aktif
    $materi_list = [];
    if ($kelas_id > 0) {
        try {
            $stmt = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok, m.kode_mulok, m.jumlah_jam
              FROM mengampu_materi mm
              INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                      WHERE mm.guru_id = ? AND mm.kelas_id = ? AND m.semester = ?
                      ORDER BY m.nama_mulok");
            $stmt->bind_param("iis", $user_id, $kelas_id, $semester_aktif);
    $stmt->execute();
    $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $materi_id_check = $row['id'];
                    
                    // Cek apakah nilai sudah dikirim untuk materi ini
                    $status_nilai = 'belum';
                    if (!empty($tahun_ajaran)) {
                        $stmt_cek = $conn->prepare("SELECT status FROM nilai_kirim_status 
                                                   WHERE materi_mulok_id = ? 
                                                   AND kelas_id = ? 
                                                   AND semester = ? 
                                                   AND tahun_ajaran = ? 
                                                   AND status = 'terkirim'");
                        $stmt_cek->bind_param("iiss", $materi_id_check, $kelas_id, $semester_aktif, $tahun_ajaran);
                        $stmt_cek->execute();
                        $result_cek = $stmt_cek->get_result();
                        if ($result_cek && $result_cek->num_rows > 0) {
                            $status_nilai = 'terkirim';
                        }
                        $stmt_cek->close();
                    }
                    
                    $materi_list[] = [
                        'id' => $row['id'],
                        'kode_mulok' => $row['kode_mulok'],
                        'nama_mulok' => $row['nama_mulok'],
                        'jumlah_jam' => $row['jumlah_jam'],
                        'status_nilai' => $status_nilai
                    ];
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $materi_list = [];
        }
    }
}

// Set page title dinamis (variabel lokal)
if ($materi_id > 0 && isset($materi_data) && $materi_data) {
    $page_title = htmlspecialchars($materi_data['nama_mulok'] ?? 'Materi Mulok');
} else {
    $page_title = 'Materi Mulok';
}
?>
<?php include '../includes/header.php'; ?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($materi_id > 0 && $materi_data): ?>
    <!-- Tampilkan tabel nilai siswa untuk materi tertentu -->
<div class="card">
    <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-book"></i> 
                <?php echo htmlspecialchars($materi_data['kategori'] ?? '-'); ?> - <?php echo htmlspecialchars($materi_data['nama_mulok']); ?>
            </h5>
    </div>
    <div class="card-body">
            <div class="mb-3 d-flex gap-2">
                <button type="button" class="btn btn-primary" id="btnTambahNilai" onclick="tambahNilai()" <?php echo $status_kirim == 'terkirim' ? 'disabled' : ''; ?>>
                    <i class="fas fa-plus"></i> Tambah Nilai
                </button>
                <button type="button" class="btn btn-info" id="btnImporNilai" onclick="imporNilai()" <?php echo $status_kirim == 'terkirim' ? 'disabled' : ''; ?>>
                    <i class="fas fa-file-import"></i> Impor Nilai
                </button>
                <?php if ($status_kirim == 'terkirim'): ?>
                    <button type="button" class="btn btn-danger" id="btnBatalKirim" onclick="batalKirimNilai()">
                        <i class="fas fa-times-circle"></i> Batal Kirim
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-success" id="btnKirimNilai" onclick="kirimNilai()">
                        <i class="fas fa-paper-plane"></i> Kirim Nilai
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableNilai">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th width="60">L/P</th>
                            <th width="100">Nilai</th>
                            <th width="100">Predikat</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_data)): ?>
                        <?php 
                        $no = 1;
                            foreach ($siswa_data as $siswa): 
                                $nilai = $nilai_data[$siswa['id']] ?? null;
                                $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
                                $predikat = $nilai ? ($nilai['predikat'] ?? '') : '';
                                $deskripsi = $nilai ? ($nilai['deskripsi'] ?? '') : '';
                                
                                // Hitung predikat dari nilai jika belum ada
                                if (empty($predikat) && !empty($nilai_value)) {
                                    $predikat = hitungPredikat($nilai_value);
                                }
                                
                                // Hitung atau update deskripsi - selalu update untuk memastikan format baru dengan kategori
                                if (!empty($predikat) && $predikat != '-' && !empty($materi_data['nama_mulok'])) {
                                    // Ambil kategori dari materi_data
                                    $kategori_display = '';
                                    if (isset($materi_data['kategori_mulok']) && !empty($materi_data['kategori_mulok'])) {
                                        $kategori_display = (string)$materi_data['kategori_mulok'];
                                    } elseif (isset($materi_data['kode_mulok']) && !empty($materi_data['kode_mulok'])) {
                                        $kategori_display = (string)$materi_data['kode_mulok'];
                                    } elseif (isset($materi_data['kategori']) && !empty($materi_data['kategori'])) {
                                        $kategori_display = (string)$materi_data['kategori'];
                                    }
                                    // Selalu hitung ulang deskripsi untuk memastikan format baru dengan kategori
                                    $deskripsi = hitungDeskripsi($predikat, $materi_data['nama_mulok'], $kategori_display);
                                }
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                    <td><?php echo (($siswa['jenis_kelamin'] ?? '') == 'L') ? 'L' : 'P'; ?></td>
                                    <td><?php echo htmlspecialchars($nilai_value); ?></td>
                                    <td><?php echo htmlspecialchars($predikat); ?></td>
                                    <td><?php echo htmlspecialchars($deskripsi); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Tidak ada siswa di kelas ini</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Tampilkan daftar materi jika tidak ada materi_id -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-book"></i> Materi Mulok yang Diampu</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($materi_list)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableMateri">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Kode Mulok</th>
                                <th>Materi Mulok</th>
                                <th>Jumlah Jam</th>
                                <th>Status Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($materi_list as $materi): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($materi['kode_mulok']); ?></td>
                                    <td>
                                        <a href="materi.php?id=<?php echo $materi['id']; ?>">
                                            <?php echo htmlspecialchars($materi['nama_mulok']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($materi['jumlah_jam']); ?> Jam</td>
                                    <td>
                                        <?php if ($materi['status_nilai'] == 'terkirim'): ?>
                                            <span class="badge bg-success">Terkirim</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Belum</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada materi yang diampu.
            </div>
        <?php endif; ?>
    </div>
    </div>
<?php endif; ?>

<!-- Modal Tambah Nilai -->
<?php if ($materi_id > 0 && !empty($siswa_data)): ?>
<div class="modal fade" id="modalTambahNilai" tabindex="-1" aria-labelledby="modalTambahNilaiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahNilaiLabel">
                    <i class="fas fa-plus"></i> Tambah Nilai - <?php echo htmlspecialchars($materi_data['kategori'] ?? '-'); ?> - <?php echo htmlspecialchars($materi_data['nama_mulok']); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTambahNilai">
                <input type="hidden" name="action" value="tambah_nilai">
                <input type="hidden" name="materi_id" value="<?php echo $materi_id; ?>">
                <input type="hidden" name="kelas_id" value="<?php echo $kelas_id; ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester_aktif); ?>">
                <input type="hidden" name="tahun_ajaran" value="<?php echo htmlspecialchars($tahun_ajaran); ?>">
                <div class="modal-body">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="50">No</th>
                                    <th>NISN</th>
                                    <th>Nama Siswa</th>
                                    <th width="150">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($siswa_data as $siswa): 
                                    $nilai = $nilai_data[$siswa['id']] ?? null;
                                    $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                        <td>
                                            <input type="hidden" name="siswa_ids[]" value="<?php echo $siswa['id']; ?>">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   name="nilai[]" 
                                                   value="<?php echo htmlspecialchars($nilai_value); ?>"
                                                   min="0" 
                                                   max="100" 
                                                   step="0.01"
                                                   placeholder="0-100">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Nilai -->
<div class="modal fade" id="modalImportNilai" tabindex="-1" aria-labelledby="modalImportNilaiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #2d5016; color: white;">
                <h5 class="modal-title" id="modalImportNilaiLabel"><i class="fas fa-file-upload"></i> Upload Nilai</h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadTemplateNilai()">
                        <i class="fas fa-download"></i> Template Excel
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <!-- Upload Area -->
                <div class="upload-area" id="uploadAreaNilai" style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer; background-color: #f8f9fa; transition: all 0.3s;">
                    <input type="file" id="fileInputNilai" accept=".xls,.xlsx" style="display: none;" multiple>
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                    <p class="mb-0" style="color: #6c757d; font-size: 16px;">
                        Letakkan File atau Klik Disini untuk upload
                    </p>
</div>
                
                <!-- File List Table -->
                <div class="mt-4">
                    <table class="table table-bordered table-sm" id="fileTableNilai" style="display: table;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Progress</th>
                                <th>Sukses</th>
                                <th>Gagal</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fileTableBodyNilai">
                            <tr id="noDataRowNilai">
                                <td colspan="7" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle"></i> Belum ada file yang dipilih
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if ($materi_id > 0): ?>
            $('#tableNilai').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']],
                pageLength: 25
            });
        <?php else: ?>
        $('#tableMateri').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
            });
        <?php endif; ?>
        
        // Auto close alert setelah 3 detik
        $('.alert-success').each(function() {
            var alert = $(this);
            setTimeout(function() {
                alert.fadeOut('slow', function() {
                    $(this).alert('close');
                });
            }, 3000);
        });
    });
    
    function tambahNilai() {
        var modal = new bootstrap.Modal(document.getElementById('modalTambahNilai'));
        modal.show();
    }
    
    function imporNilai() {
        var modal = new bootstrap.Modal(document.getElementById('modalImportNilai'));
        modal.show();
    }
    
    function downloadTemplateNilai() {
        var materiId = <?php echo $materi_id; ?>;
        if (!materiId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Materi tidak ditemukan',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        window.location.href = 'template_nilai.php?materi_id=' + materiId;
    }
    
    function kirimNilai() {
        // Validasi: Cek apakah semua nilai sudah diisi dari tabel yang ditampilkan
        const tableNilai = document.getElementById('tableNilai');
        if (!tableNilai) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Tabel nilai tidak ditemukan',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        const tableRows = tableNilai.querySelectorAll('tbody tr');
        let nilaiKosong = [];
        let siswaMap = new Map(); // Gunakan Map untuk menghindari duplikasi
        
        tableRows.forEach((row) => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 5) {
                const namaSiswa = cells[2] ? cells[2].textContent.trim() : '';
                const nilaiCell = cells[4] ? cells[4].textContent.trim() : '';
                
                // Skip jika baris kosong atau pesan "tidak ada siswa"
                if (!namaSiswa || namaSiswa === 'Tidak ada siswa di kelas ini') {
                    return;
                }
                
                // Cek jika nilai kosong atau tidak ada
                if (!nilaiCell || nilaiCell === '' || nilaiCell === '-' || nilaiCell === '0') {
                    // Gunakan Map untuk menghindari duplikasi nama siswa
                    if (!siswaMap.has(namaSiswa)) {
                        siswaMap.set(namaSiswa, true);
                        nilaiKosong.push(namaSiswa);
                    }
                }
            }
        });
        
        // Jika ada nilai yang kosong, tampilkan peringatan
        if (nilaiKosong.length > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Nilai Belum Lengkap!',
                html: `Masih ada <strong>${nilaiKosong.length}</strong> siswa yang belum memiliki nilai:<br><br>` +
                      `<ul style="text-align: left; max-height: 200px; overflow-y: auto; padding-left: 20px;">` +
                      nilaiKosong.map(nama => `<li>${nama}</li>`).join('') +
                      `</ul><br>Silakan lengkapi semua nilai terlebih dahulu sebelum mengirim.`,
                confirmButtonColor: '#2d5016',
                width: '600px'
            });
            return;
        }
        
        Swal.fire({
            title: 'Kirim Nilai?',
            text: 'Apakah Anda yakin ingin mengirim nilai? Setelah dikirim, nilai tidak dapat diubah kecuali dibatalkan terlebih dahulu.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Kirim',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const materiId = <?php echo $materi_id; ?>;
                const kelasId = <?php echo $kelas_id; ?>;
                const semester = '<?php echo $semester_aktif; ?>';
                const tahunAjaran = '<?php echo $tahun_ajaran; ?>';
                
                Swal.fire({
                    title: 'Mengirim...',
                    text: 'Mohon tunggu',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const formData = new FormData();
                formData.append('action', 'kirim_nilai');
                formData.append('materi_id', materiId);
                formData.append('kelas_id', kelasId);
                formData.append('semester', semester);
                formData.append('tahun_ajaran', tahunAjaran);
                
                fetch('materi.php?id=<?php echo $materi_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            confirmButtonColor: '#2d5016'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message,
                            confirmButtonColor: '#2d5016'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan: ' + error.message,
                        confirmButtonColor: '#2d5016'
                    });
                });
            }
        });
    }
    
    function batalKirimNilai() {
        Swal.fire({
            title: 'Batal Kirim Nilai?',
            text: 'Apakah Anda yakin ingin membatalkan pengiriman nilai? Setelah dibatalkan, nilai dapat diubah kembali.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batal Kirim',
            cancelButtonText: 'Tidak'
        }).then((result) => {
            if (result.isConfirmed) {
                const materiId = <?php echo $materi_id; ?>;
                const kelasId = <?php echo $kelas_id; ?>;
                const semester = '<?php echo $semester_aktif; ?>';
                const tahunAjaran = '<?php echo $tahun_ajaran; ?>';
                
                Swal.fire({
                    title: 'Membatalkan...',
                    text: 'Mohon tunggu',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const formData = new FormData();
                formData.append('action', 'batal_kirim_nilai');
                formData.append('materi_id', materiId);
                formData.append('kelas_id', kelasId);
                formData.append('semester', semester);
                formData.append('tahun_ajaran', tahunAjaran);
                
                fetch('materi.php?id=<?php echo $materi_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            confirmButtonColor: '#2d5016'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message,
                            confirmButtonColor: '#2d5016'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan: ' + error.message,
                        confirmButtonColor: '#2d5016'
                    });
                });
            }
        });
    }
    
    // Handle upload area untuk import nilai
    const uploadAreaNilai = document.getElementById('uploadAreaNilai');
    const fileInputNilai = document.getElementById('fileInputNilai');
    const fileTableBodyNilai = document.getElementById('fileTableBodyNilai');
    
    if (uploadAreaNilai && fileInputNilai) {
        uploadAreaNilai.addEventListener('click', () => {
            fileInputNilai.click();
        });
        
        uploadAreaNilai.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadAreaNilai.style.backgroundColor = '#e9ecef';
            uploadAreaNilai.style.borderColor = '#2d5016';
        });
        
        uploadAreaNilai.addEventListener('dragleave', () => {
            uploadAreaNilai.style.backgroundColor = '#f8f9fa';
            uploadAreaNilai.style.borderColor = '#ccc';
        });
        
        uploadAreaNilai.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadAreaNilai.style.backgroundColor = '#f8f9fa';
            uploadAreaNilai.style.borderColor = '#ccc';
            
            const files = Array.from(e.dataTransfer.files).filter(file => 
                file.name.endsWith('.xls') || file.name.endsWith('.xlsx')
            );
            
            if (files.length > 0) {
                fileInputNilai.files = e.dataTransfer.files;
                handleFilesNilai(files);
            }
        });
        
        fileInputNilai.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                handleFilesNilai(files);
            }
        });
    }
    
    function handleFilesNilai(files) {
        // Simpan files di window untuk digunakan saat upload
        window.uploadFilesNilai = files;
        
        // Hapus row "no data"
        const noDataRow = document.getElementById('noDataRowNilai');
        if (noDataRow) {
            noDataRow.remove();
        }
        
        // Tambahkan row untuk setiap file
        files.forEach((file, index) => {
            const row = document.createElement('tr');
            row.id = 'fileRowNilai_' + index;
            
            let fileSize = '';
            if (file.size < 1024) {
                fileSize = file.size + ' B';
            } else if (file.size < 1024 * 1024) {
                fileSize = (file.size / 1024).toFixed(2) + ' KB';
            } else {
                fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
            
            row.innerHTML = `
                <td>${file.name}</td>
                <td>${fileSize}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="progressNilai_${index}" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </td>
                <td id="successNilai_${index}" style="text-align: center; font-weight: bold; color: #28a745;">0</td>
                <td id="failedNilai_${index}" style="text-align: center; font-weight: bold; color: #dc3545;">0</td>
                <td id="statusNilai_${index}">
                    <span class="badge bg-secondary">Menunggu</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="uploadFileNilai(${index})">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </td>
            `;
            
            fileTableBodyNilai.appendChild(row);
        });
    }
    
    function uploadFileNilai(index) {
        let file = null;
        if (window.uploadFilesNilai && Array.isArray(window.uploadFilesNilai) && window.uploadFilesNilai[index]) {
            file = window.uploadFilesNilai[index];
        } else {
            const fileInput = document.getElementById('fileInputNilai');
            if (fileInput && fileInput.files && fileInput.files[index]) {
                file = fileInput.files[index];
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'File tidak ditemukan!',
                    confirmButtonColor: '#2d5016'
                });
                return;
            }
        }
        
        if (!file || !file.name) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'File tidak valid!',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        const materiId = <?php echo $materi_id; ?>;
        if (!materiId) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Materi tidak ditemukan!',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        const formData = new FormData();
        formData.append('file_excel', file);
        formData.append('action', 'import_nilai');
        formData.append('materi_id', materiId);
        
        const progressBar = document.getElementById('progressNilai_' + index);
        const statusBadge = document.getElementById('statusNilai_' + index);
        const successCell = document.getElementById('successNilai_' + index);
        const failedCell = document.getElementById('failedNilai_' + index);
        
        if (!progressBar || !statusBadge || !successCell || !failedCell) {
            return;
        }
        
        // Reset progress bar
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressBar.setAttribute('aria-valuenow', '0');
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
        
        // Update status
        statusBadge.innerHTML = '<span class="badge bg-info">Mengupload...</span>';
        
        // Disable upload button
        const uploadBtn = document.querySelector(`#fileRowNilai_${index} button`);
        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        }
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const percentRounded = Math.round(percentComplete);
                
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentRounded + '%';
                progressBar.setAttribute('aria-valuenow', percentRounded);
                
                if (percentComplete < 50) {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                } else if (percentComplete < 100) {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                } else {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
                }
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const responseText = xhr.responseText.trim();
                    
                    if (!responseText) {
                        throw new Error('Response kosong dari server');
                    }
                    
                    if (!responseText.startsWith('{') && !responseText.startsWith('[')) {
                        throw new Error('Response bukan JSON valid');
                    }
                    
                    const response = JSON.parse(responseText);
                    
                    if (response.success) {
                        progressBar.className = 'progress-bar bg-success';
                        statusBadge.innerHTML = '<span class="badge bg-success">Selesai</span>';
                        successCell.textContent = response.success_count || 0;
                        failedCell.textContent = response.failed_count || 0;
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: response.message || 'Nilai berhasil diimpor',
                            confirmButtonColor: '#2d5016',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        progressBar.className = 'progress-bar bg-danger';
                        statusBadge.innerHTML = '<span class="badge bg-danger">Gagal</span>';
                        failedCell.textContent = response.failed_count || 0;
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message || 'Gagal mengimpor nilai',
                            confirmButtonColor: '#2d5016'
                        });
                    }
                } catch (error) {
                    progressBar.className = 'progress-bar bg-danger';
                    statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat memproses response: ' + error.message,
                        confirmButtonColor: '#2d5016'
                    });
                }
            } else {
                progressBar.className = 'progress-bar bg-danger';
                statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Server error: ' + xhr.status,
                    confirmButtonColor: '#2d5016'
                });
            }
            
            // Enable upload button
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
        });
        
        xhr.addEventListener('error', () => {
            progressBar.className = 'progress-bar bg-danger';
            statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
            
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengupload file',
                confirmButtonColor: '#2d5016'
            });
            
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
        });
        
        xhr.open('POST', 'import_nilai.php');
        xhr.send(formData);
    }
    
    // Reset tabel import saat modal import ditutup
    $('#modalImportNilai').on('hidden.bs.modal', function() {
        const fileTableBodyNilai = document.getElementById('fileTableBodyNilai');
        if (fileTableBodyNilai) {
            fileTableBodyNilai.innerHTML = '<tr id="noDataRowNilai"><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-info-circle"></i> Belum ada file yang dipilih</td></tr>';
        }
        const fileInputNilai = document.getElementById('fileInputNilai');
        if (fileInputNilai) {
            fileInputNilai.value = '';
        }
        window.uploadFilesNilai = null;
    });
    
    // Handle submit form tambah nilai
    document.getElementById('formTambahNilai').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var formData = new FormData(form);
        
        // Validasi: pastikan minimal ada satu nilai yang diisi
        var nilaiInputs = form.querySelectorAll('input[name="nilai[]"]');
        var adaNilai = false;
        nilaiInputs.forEach(function(input) {
            if (input.value && input.value.trim() !== '') {
                adaNilai = true;
            }
        });
        
        if (!adaNilai) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Minimal satu nilai harus diisi',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        // Tampilkan loading
        Swal.fire({
            title: 'Menyimpan Nilai...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('materi.php?id=<?php echo $materi_id; ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Tutup modal terlebih dahulu
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalTambahNilai'));
                modal.hide();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: data.message || 'Nilai berhasil disimpan',
                    confirmButtonColor: '#2d5016',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: true
                }).then(() => {
                    // Reload halaman untuk update data
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: data.message || 'Gagal menyimpan nilai',
                    confirmButtonColor: '#2d5016'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat menyimpan nilai',
                confirmButtonColor: '#2d5016'
            });
        });
    });
</script>
