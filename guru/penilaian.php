<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

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
} catch (Exception $e) {
    // Ignore jika kolom sudah ada atau error lainnya
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
    
    // Gunakan nama materi sesuai dengan data (tidak lowercase)
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

// Cek apakah kolom harian, pas_pat, rapor ada di tabel nilai_siswa
$has_harian = false;
$has_pas_pat = false;
$has_rapor = false;
try {
    $check_cols = $conn->query("SHOW COLUMNS FROM nilai_siswa");
    if ($check_cols) {
        while ($col = $check_cols->fetch_assoc()) {
            if ($col['Field'] == 'harian') $has_harian = true;
            if ($col['Field'] == 'pas_pat') $has_pas_pat = true;
            if ($col['Field'] == 'rapor') $has_rapor = true;
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Handle simpan nilai (inline editing)
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'simpan_nilai') {
    $materi_id_post = $_POST['materi_id'] ?? 0;
    $kelas_id_post = $_POST['kelas_id'] ?? 0;
    $semester_post = $_POST['semester'] ?? '1';
    $tahun_ajaran_post = $_POST['tahun_ajaran'] ?? '';
    $harian_array = $_POST['harian'] ?? [];
    $pas_pat_array = $_POST['pas_pat'] ?? [];
    
    if ($materi_id_post > 0 && $kelas_id_post > 0) {
        $conn->begin_transaction();
        try {
            foreach ($harian_array as $siswa_id => $harian_value) {
                $siswa_id = intval($siswa_id);
                $harian_value = trim($harian_value);
                $pas_pat_value = trim($pas_pat_array[$siswa_id] ?? '');
                
                if ($siswa_id > 0) {
                    $harian_float = $harian_value !== '' ? floatval($harian_value) : null;
                    $pas_pat_float = $pas_pat_value !== '' ? floatval($pas_pat_value) : null;
                    
                    // Hitung rapor: (harian * 0.6) + (pas_pat * 0.4)
                    $rapor_float = null;
                    if ($harian_float !== null && $pas_pat_float !== null) {
                        $rapor_float = round(($harian_float * 0.6) + ($pas_pat_float * 0.4), 2);
                    } elseif ($harian_float !== null) {
                        $rapor_float = $harian_float;
                    } elseif ($pas_pat_float !== null) {
                        $rapor_float = $pas_pat_float;
                    }
                    
                    // Cek apakah nilai sudah ada
                    $stmt_check = $conn->prepare("SELECT id FROM nilai_siswa 
                                                 WHERE siswa_id = ? 
                                                 AND materi_mulok_id = ? 
                                                 AND semester = ? 
                                                 AND tahun_ajaran = ?");
                    $stmt_check->bind_param("iiss", $siswa_id, $materi_id_post, $semester_post, $tahun_ajaran_post);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing = $result_check->fetch_assoc();
                    
                    if ($existing) {
                        // Update nilai yang sudah ada
                        if ($has_harian && $has_pas_pat && $has_rapor) {
                            $stmt_update = $conn->prepare("UPDATE nilai_siswa 
                                                          SET harian = ?, pas_pat = ?, rapor = ? 
                                                          WHERE id = ?");
                            $stmt_update->bind_param("dddi", $harian_float, $pas_pat_float, $rapor_float, $existing['id']);
                        } else {
                            // Fallback ke nilai_pengetahuan dan nilai_keterampilan
                            $stmt_update = $conn->prepare("UPDATE nilai_siswa 
                                                          SET nilai_pengetahuan = ?, nilai_keterampilan = ? 
                                                          WHERE id = ?");
                            $stmt_update->bind_param("iii", $harian_float, $pas_pat_float, $existing['id']);
                        }
                        $stmt_update->execute();
                    } else {
                        // Insert nilai baru
                        if ($has_harian && $has_pas_pat && $has_rapor) {
                            $stmt_insert = $conn->prepare("INSERT INTO nilai_siswa 
                                                           (siswa_id, materi_mulok_id, kelas_id, guru_id, semester, tahun_ajaran, harian, pas_pat, rapor) 
                                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_insert->bind_param("iiiissddd", $siswa_id, $materi_id_post, $kelas_id_post, $user_id, $semester_post, $tahun_ajaran_post, $harian_float, $pas_pat_float, $rapor_float);
                        } else {
                            // Fallback ke nilai_pengetahuan dan nilai_keterampilan
                            $stmt_insert = $conn->prepare("INSERT INTO nilai_siswa 
                                                           (siswa_id, materi_mulok_id, kelas_id, guru_id, semester, tahun_ajaran, nilai_pengetahuan, nilai_keterampilan) 
                                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_insert->bind_param("iiiissii", $siswa_id, $materi_id_post, $kelas_id_post, $user_id, $semester_post, $tahun_ajaran_post, $harian_float, $pas_pat_float);
                        }
                        $stmt_insert->execute();
                    }
                }
            }
            $conn->commit();
            $_SESSION['success_message'] = 'Nilai berhasil disimpan!';
            // Redirect untuk refresh data dengan parameter kelas_nama jika ada
            $redirect_url = 'penilaian.php?materi_id=' . $materi_id_post;
            if (!empty($_GET['kelas_nama'])) {
                $redirect_url .= '&kelas_nama=' . urlencode($_GET['kelas_nama']);
            }
            header('Location: ' . $redirect_url);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = 'Gagal menyimpan nilai: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Data tidak lengkap!';
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah_nilai') {
    $materi_id_post = $_POST['materi_id'] ?? 0;
    $kelas_id_post = $_POST['kelas_id'] ?? 0;
    $semester_post = $_POST['semester'] ?? '1';
    $tahun_ajaran_post = $_POST['tahun_ajaran'] ?? '';
    $nilai_array = $_POST['nilai'] ?? [];
    
    if ($materi_id_post > 0 && $kelas_id_post > 0 && !empty($nilai_array)) {
        $conn->begin_transaction();
        try {
            foreach ($nilai_array as $siswa_id => $nilai_value) {
                $siswa_id = intval($siswa_id);
                $nilai_value = trim($nilai_value);
                
                if ($siswa_id > 0 && $nilai_value !== '') {
                    $nilai_float = floatval($nilai_value);
                    
                    // Hitung predikat dari nilai
                    $predikat = hitungPredikat($nilai_float);
                    
                    // Ambil nama materi untuk deskripsi - gunakan nama_mulok langsung dari database tanpa modifikasi
                    // Gunakan BINARY untuk memastikan case sensitivity
                    $nama_materi = '';
                    $stmt_materi_nama = $conn->prepare("SELECT BINARY nama_mulok as nama_mulok FROM materi_mulok WHERE id = ?");
                    $stmt_materi_nama->bind_param("i", $materi_id_post);
                    $stmt_materi_nama->execute();
                    $result_materi_nama = $stmt_materi_nama->get_result();
                    if ($result_materi_nama && $result_materi_nama->num_rows > 0) {
                        $materi_row = $result_materi_nama->fetch_assoc();
                        // Ambil nama_mulok langsung tanpa modifikasi apapun (termasuk case)
                        $nama_materi = isset($materi_row['nama_mulok']) ? (string)$materi_row['nama_mulok'] : '';
                    }
                    
                    // Hitung deskripsi dengan nama materi yang sama persis dengan database
                    $deskripsi = hitungDeskripsi($predikat, $nama_materi);
                    
                    // Cek apakah nilai sudah ada
                    $stmt_check = $conn->prepare("SELECT id FROM nilai_siswa 
                                                 WHERE siswa_id = ? 
                                                 AND materi_mulok_id = ? 
                                                 AND semester = ? 
                                                 AND tahun_ajaran = ?");
                    $stmt_check->bind_param("iiss", $siswa_id, $materi_id_post, $semester_post, $tahun_ajaran_post);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing = $result_check->fetch_assoc();
                    
                    if ($existing) {
                        // Update nilai yang sudah ada
                        $stmt_update = $conn->prepare("UPDATE nilai_siswa 
                                                      SET nilai_pengetahuan = ?, predikat = ?, deskripsi = ? 
                                                      WHERE id = ?");
                        $stmt_update->bind_param("dssi", $nilai_float, $predikat, $deskripsi, $existing['id']);
                        $stmt_update->execute();
                    } else {
                        // Insert nilai baru
                        $stmt_insert = $conn->prepare("INSERT INTO nilai_siswa 
                                                       (siswa_id, materi_mulok_id, kelas_id, guru_id, semester, tahun_ajaran, nilai_pengetahuan, predikat, deskripsi) 
                                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_insert->bind_param("iiiissdss", $siswa_id, $materi_id_post, $kelas_id_post, $user_id, $semester_post, $tahun_ajaran_post, $nilai_float, $predikat, $deskripsi);
                        $stmt_insert->execute();
                    }
                }
            }
            $conn->commit();
            $_SESSION['success_message'] = 'Nilai berhasil disimpan!';
            // Redirect untuk refresh data dengan parameter kelas_nama jika ada
            $redirect_url = 'penilaian.php?materi_id=' . $materi_id_post;
            if (!empty($_GET['kelas_nama'])) {
                $redirect_url .= '&kelas_nama=' . urlencode($_GET['kelas_nama']);
            }
            header('Location: ' . $redirect_url);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = 'Gagal menyimpan nilai: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Data tidak lengkap!';
    }
}

// Ambil materi_id dari GET (wajib ada)
$materi_id = $_GET['materi_id'] ?? 0;
$kelas_nama_filter = $_GET['kelas_nama'] ?? '';

// Jika tidak ada materi_id, redirect ke halaman materi atau tampilkan error
if (!$materi_id) {
    // Coba ambil materi pertama yang diampu
    try {
        $stmt_materi_first = $conn->prepare("SELECT m.id 
                                             FROM mengampu_materi mm
                                             INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                             WHERE mm.guru_id = ?
                                             LIMIT 1");
        $stmt_materi_first->bind_param("i", $user_id);
        $stmt_materi_first->execute();
        $result_materi_first = $stmt_materi_first->get_result();
        if ($result_materi_first && $result_materi_first->num_rows > 0) {
            $first_materi = $result_materi_first->fetch_assoc();
            header('Location: penilaian.php?materi_id=' . $first_materi['id']);
            exit;
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// Ambil profil untuk semester dan tahun ajaran
$semester = '1';
$tahun_ajaran = '';
try {
    $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $semester = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    $semester = '1';
    $tahun_ajaran = '';
}

// Ambil data kelas yang diampu oleh wali kelas
$kelas_data = null;
$kelas_id = 0;
try {
    $stmt_kelas = $conn->prepare("SELECT * FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
}

// Ambil materi yang diampu jika materi_id belum dipilih
$materi_list = [];
if (!$materi_id) {
    try {
        // Ambil semua materi yang diampu oleh guru ini (tanpa filter kelas_id)
        $stmt_materi = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok, k.nama_kelas
                                       FROM mengampu_materi mm
                                       INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                                       LEFT JOIN kelas k ON mm.kelas_id = k.id
                                       WHERE mm.guru_id = ?
                                       ORDER BY k.nama_kelas, m.nama_mulok");
        $stmt_materi->bind_param("i", $user_id);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        if ($result_materi) {
            while ($row = $result_materi->fetch_assoc()) {
                $materi_list[] = $row;
            }
        }
        // Jika hanya ada 1 materi, otomatis pilih
        if (count($materi_list) == 1) {
            $materi_id = $materi_list[0]['id'];
        }
    } catch (Exception $e) {
        $materi_list = [];
    }
}

// Ambil data materi yang dipilih
$materi_data = null;
$siswa_list = null;
$nilai_data = [];
$kelas_id_for_materi = 0;

if ($materi_id > 0) {
    try {
        // Ambil data materi beserta kelas yang diampu
        // Jika ada kelas_nama filter, gunakan untuk memastikan kelas yang tepat
        if (!empty($kelas_nama_filter)) {
            $stmt_materi = $conn->prepare("SELECT m.*, mm.kelas_id, k.nama_kelas
                                           FROM materi_mulok m
                                           INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                                           LEFT JOIN kelas k ON mm.kelas_id = k.id
                                           WHERE m.id = ? AND mm.guru_id = ? AND k.nama_kelas = ?
                                           LIMIT 1");
            $stmt_materi->bind_param("iis", $materi_id, $user_id, $kelas_nama_filter);
            $stmt_materi->execute();
            $result_materi = $stmt_materi->get_result();
            $materi_data = $result_materi ? $result_materi->fetch_assoc() : null;
        }
        
        // Jika tidak ditemukan dengan filter kelas_nama, coba ambil dengan filter guru_id saja
        if (!$materi_data) {
            $stmt_materi = $conn->prepare("SELECT m.*, mm.kelas_id, k.nama_kelas
                                           FROM materi_mulok m
                                           INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                                           LEFT JOIN kelas k ON mm.kelas_id = k.id
                                           WHERE m.id = ? AND mm.guru_id = ?
                                           ORDER BY k.nama_kelas
                                           LIMIT 1");
            $stmt_materi->bind_param("ii", $materi_id, $user_id);
            $stmt_materi->execute();
            $result_materi = $stmt_materi->get_result();
            $materi_data = $result_materi ? $result_materi->fetch_assoc() : null;
        }
        
        // Jika tidak ditemukan dengan filter guru_id, coba ambil tanpa filter guru_id
        if (!$materi_data) {
            $stmt_materi2 = $conn->prepare("SELECT m.*, mm.kelas_id, k.nama_kelas
                                           FROM materi_mulok m
                                           INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                                           LEFT JOIN kelas k ON mm.kelas_id = k.id
                                           WHERE m.id = ?
                                           ORDER BY k.nama_kelas
                                           LIMIT 1");
            $stmt_materi2->bind_param("i", $materi_id);
            $stmt_materi2->execute();
            $result_materi2 = $stmt_materi2->get_result();
            $materi_data = $result_materi2 ? $result_materi2->fetch_assoc() : null;
        }
        
        // Jika masih belum ditemukan, ambil langsung dari tabel materi_mulok
        if (!$materi_data) {
            $stmt_materi3 = $conn->prepare("SELECT m.*, NULL as kelas_id, NULL as nama_kelas
                                           FROM materi_mulok m
                                           WHERE m.id = ?");
            $stmt_materi3->bind_param("i", $materi_id);
            $stmt_materi3->execute();
            $result_materi3 = $stmt_materi3->get_result();
            $materi_data = $result_materi3 ? $result_materi3->fetch_assoc() : null;
        }
        
        // Pastikan nama_mulok menggunakan case yang benar dengan mengambil ulang menggunakan BINARY jika perlu
        if ($materi_data && isset($materi_data['id'])) {
            $stmt_nama_binary = $conn->prepare("SELECT BINARY nama_mulok as nama_mulok FROM materi_mulok WHERE id = ?");
            $stmt_nama_binary->bind_param("i", $materi_data['id']);
            $stmt_nama_binary->execute();
            $result_nama_binary = $stmt_nama_binary->get_result();
            if ($result_nama_binary && $result_nama_binary->num_rows > 0) {
                $row_nama = $result_nama_binary->fetch_assoc();
                $materi_data['nama_mulok'] = (string)$row_nama['nama_mulok'];
            }
        }
        
        if ($materi_data) {
            $kelas_id_for_materi = $materi_data['kelas_id'] ?? 0;
            
            // Jika kelas_id dari mengampu_materi tidak ada, gunakan kelas_id dari wali_kelas
            if (!$kelas_id_for_materi && $kelas_id) {
                $kelas_id_for_materi = $kelas_id;
            }
            
            // Ambil siswa di kelas (pastikan selalu mengambil siswa jika kelas_id ada)
            if ($kelas_id_for_materi > 0) {
                $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE kelas_id = ? ORDER BY nama");
                $stmt_siswa->bind_param("i", $kelas_id_for_materi);
                $stmt_siswa->execute();
                $siswa_list = $stmt_siswa->get_result();
            } else {
                // Jika tidak ada kelas_id, coba ambil dari kelas wali_kelas
                if ($kelas_id > 0) {
                    $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE kelas_id = ? ORDER BY nama");
                    $stmt_siswa->bind_param("i", $kelas_id);
                    $stmt_siswa->execute();
                    $siswa_list = $stmt_siswa->get_result();
                    $kelas_id_for_materi = $kelas_id;
                }
            }
        }
        
        // Ambil nilai siswa untuk materi ini
        if ($siswa_list && $siswa_list->num_rows > 0) {
            $siswa_ids = [];
            $siswa_list->data_seek(0);
            while ($s = $siswa_list->fetch_assoc()) {
                $siswa_ids[] = $s['id'];
            }
            
            if (count($siswa_ids) > 0) {
                $placeholders = str_repeat('?,', count($siswa_ids) - 1) . '?';
                $query_nilai = "SELECT * FROM nilai_siswa 
                               WHERE siswa_id IN ($placeholders) 
                               AND materi_mulok_id = ? 
                               AND semester = ? 
                               AND tahun_ajaran = ?";
                $stmt_nilai = $conn->prepare($query_nilai);
                $params = array_merge($siswa_ids, [$materi_id, $semester, $tahun_ajaran]);
                $types = str_repeat('i', count($siswa_ids)) . 'iss';
                $stmt_nilai->bind_param($types, ...$params);
                $stmt_nilai->execute();
                $result_nilai = $stmt_nilai->get_result();
                
                if ($result_nilai) {
                    while ($nilai = $result_nilai->fetch_assoc()) {
                        $nilai_data[$nilai['siswa_id']] = $nilai;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $materi_data = null;
        $siswa_list = null;
    }
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

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-clipboard-check"></i> 
            <?php if ($materi_data): ?>
                Nilai <?php echo htmlspecialchars($materi_data['nama_mulok']); ?> <?php echo htmlspecialchars($materi_data['nama_kelas'] ?? $kelas_data['nama_kelas'] ?? ''); ?>
            <?php else: ?>
                Penilaian
            <?php endif; ?>
        </h5>
        <?php if ($materi_id > 0 && $materi_data): ?>
            <div>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahNilai">
                    <i class="fas fa-plus"></i> Tambah Nilai
                </button>
                <button type="button" class="btn btn-warning btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#modalImportNilai">
                    <i class="fas fa-file-import"></i> Impor Nilai
                </button>
                <button type="button" class="btn btn-info btn-sm ms-2">
                    <i class="fas fa-paper-plane"></i> Kirim Nilai
                </button>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$materi_id): ?>
            <?php if (count($materi_list) > 0): ?>
                <!-- Pilih Materi -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Pilih materi untuk melihat penilaian.
                </div>
                <div class="list-group">
                    <?php foreach ($materi_list as $materi): ?>
                        <a href="?materi_id=<?php echo $materi['id']; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($materi['nama_mulok']); ?>
                            <?php if (!empty($materi['nama_kelas'])): ?>
                                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($materi['nama_kelas']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Belum ada materi yang diampu atau data tidak ditemukan.
                </div>
            <?php endif; ?>
        <?php elseif ($materi_id > 0 && $materi_data): ?>
            <!-- Tabel Nilai -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
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
                            <?php if ($siswa_list && $siswa_list->num_rows > 0): ?>
                                <?php 
                                $no = 1;
                                $siswa_list->data_seek(0);
                                while ($siswa = $siswa_list->fetch_assoc()): 
                                    $nilai = $nilai_data[$siswa['id']] ?? null;
                                    $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? '') : '';
                                    $deskripsi_value = $nilai ? ($nilai['deskripsi'] ?? '') : '';
                                    $predikat_value = $nilai ? ($nilai['predikat'] ?? '') : '';
                                    
                                    // Hitung predikat dari nilai jika belum ada
                                    if (empty($predikat_value) && !empty($nilai_value)) {
                                        $predikat_value = hitungPredikat($nilai_value);
                                    }
                                    
                                    // Hitung deskripsi jika belum ada - gunakan nama_mulok langsung dari database tanpa modifikasi
                                    if (empty($deskripsi_value) && !empty($predikat_value) && $predikat_value != '-') {
                                        // Ambil nama_mulok langsung dari materi_data tanpa modifikasi apapun (termasuk case)
                                        // Pastikan menggunakan string cast untuk mempertahankan case
                                        $nama_materi_display = isset($materi_data['nama_mulok']) ? (string)$materi_data['nama_mulok'] : '';
                                        $deskripsi_value = hitungDeskripsi($predikat_value, $nama_materi_display);
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                        <td><?php echo ($siswa['jenis_kelamin'] ?? '') == 'L' ? 'L' : 'P'; ?></td>
                                        <td><?php echo htmlspecialchars($nilai_value ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($predikat_value ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($deskripsi_value ?: '-'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Belum ada materi yang diampu atau data tidak ditemukan.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>


<!-- Modal Tambah Nilai -->
<?php if ($materi_id > 0 && $materi_data): ?>
<div class="modal fade" id="modalTambahNilai" tabindex="-1" aria-labelledby="modalTambahNilaiLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formTambahNilai">
                <input type="hidden" name="action" value="tambah_nilai">
                <input type="hidden" name="materi_id" value="<?php echo $materi_id; ?>">
                <input type="hidden" name="kelas_id" value="<?php echo $kelas_id_for_materi; ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
                <input type="hidden" name="tahun_ajaran" value="<?php echo htmlspecialchars($tahun_ajaran); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahNilaiLabel">Tambah Nilai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th>NISN</th>
                                    <th>Nama Siswa</th>
                                    <th width="60">L/P</th>
                                    <th width="150">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($siswa_list && $siswa_list->num_rows > 0): ?>
                                    <?php 
                                    $no = 1;
                                    $siswa_list->data_seek(0);
                                    while ($siswa = $siswa_list->fetch_assoc()): 
                                        $nilai = $nilai_data[$siswa['id']] ?? null;
                                        $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? '') : '';
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                            <td><?php echo ($siswa['jenis_kelamin'] ?? '') == 'L' ? 'L' : 'P'; ?></td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control form-control-sm" 
                                                       name="nilai[<?php echo $siswa['id']; ?>]" 
                                                       min="0" 
                                                       max="100" 
                                                       step="0.01"
                                                       value="<?php echo htmlspecialchars($nilai_value); ?>">
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
                                        </td>
                                    </tr>
                                <?php endif; ?>
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
<?php endif; ?>

<!-- Modal Import Nilai -->
<?php if ($materi_id > 0 && $materi_data): ?>
<div class="modal fade" id="modalImportNilai" tabindex="-1" aria-labelledby="modalImportNilaiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #ffc107; color: #000;">
                <h5 class="modal-title" id="modalImportNilaiLabel"><i class="fas fa-file-upload"></i> Impor Nilai Excel</h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadTemplateNilai()">
                        <i class="fas fa-download"></i> Template Excel
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <!-- Upload Area -->
                <div class="upload-area" id="uploadAreaNilai" style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer; background-color: #f8f9fa; transition: all 0.3s;">
                    <input type="file" id="fileInputNilai" accept=".xls,.xlsx" style="display: none;">
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
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Progress</th>
                                <th>Berhasil</th>
                                <th>Gagal</th>
                                <th>Status</th>
                                <th>Aksi</th>
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

<script>
    // Simpan file yang dipilih
    window.uploadFileNilai = null;
    
    const materiIdNilai = <?php echo $materi_id; ?>;
    const kelasNamaNilai = '<?php echo htmlspecialchars($materi_data['nama_kelas'] ?? $kelas_nama_filter ?? '', ENT_QUOTES); ?>';
    
    function downloadTemplateNilai() {
        const url = 'template_nilai.php?materi_id=' + materiIdNilai + '&kelas_nama=' + encodeURIComponent(kelasNamaNilai);
        window.location.href = url;
    }
    
    $(document).ready(function() {
        // Handle click pada upload area
        $('#uploadAreaNilai').on('click', function() {
            $('#fileInputNilai').click();
        });
        
        // Handle drag and drop
        $('#uploadAreaNilai').on('dragover', function(e) {
            e.preventDefault();
            $(this).css('border-color', '#28a745');
            $(this).css('background-color', '#e8f5e9');
        });
        
        $('#uploadAreaNilai').on('dragleave', function(e) {
            e.preventDefault();
            $(this).css('border-color', '#ccc');
            $(this).css('background-color', '#f8f9fa');
        });
        
        $('#uploadAreaNilai').on('drop', function(e) {
            e.preventDefault();
            $(this).css('border-color', '#ccc');
            $(this).css('background-color', '#f8f9fa');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $('#fileInputNilai')[0].files = files;
                handleFileSelectNilai(files[0]);
            }
        });
        
        // Handle file input change
        $('#fileInputNilai').on('change', function() {
            if (this.files && this.files.length > 0) {
                handleFileSelectNilai(this.files[0]);
            }
        });
    });
    
    function handleFileSelectNilai(file) {
        window.uploadFileNilai = file;
        
        // Hapus row "Belum ada file"
        $('#noDataRowNilai').remove();
        
        // Format ukuran file
        const fileSize = (file.size / 1024).toFixed(2) + ' KB';
        
        // Buat row untuk file
        const rowId = 'fileRowNilai_0';
        const row = `
            <tr id="${rowId}">
                <td>${file.name}</td>
                <td>${fileSize}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div id="progressNilai_0" class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                    </div>
                </td>
                <td id="successNilai_0">-</td>
                <td id="errorNilai_0">-</td>
                <td id="statusNilai_0"><span class="badge bg-secondary">Menunggu</span></td>
                <td>
                    <button type="button" class="btn btn-primary btn-sm" onclick="uploadFileNilai(0)">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </td>
            </tr>
        `;
        
        $('#fileTableBodyNilai').html(row);
    }
    
    function uploadFileNilai(index) {
        const file = window.uploadFileNilai;
        if (!file) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'File tidak ditemukan!',
                confirmButtonColor: '#ffc107'
            });
            return;
        }
        
        const formData = new FormData();
        formData.append('file_excel', file);
        formData.append('materi_id', materiIdNilai);
        formData.append('kelas_nama', kelasNamaNilai);
        
        const progressBar = $('#progressNilai_' + index);
        const statusBadge = $('#statusNilai_' + index);
        const successCell = $('#successNilai_0');
        const errorCell = $('#errorNilai_0');
        
        // Update status
        statusBadge.html('<span class="badge bg-info">Mengupload...</span>');
        
        $.ajax({
            url: 'import_nilai_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.css('width', percentComplete + '%').text(Math.round(percentComplete) + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.success) {
                        progressBar.css('width', '100%').text('100%').removeClass('bg-info').addClass('bg-success');
                        statusBadge.html('<span class="badge bg-success">Selesai</span>');
                        successCell.text(data.success_count || 0);
                        errorCell.text(data.error_count || 0);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: data.message + (data.errors && data.errors.length > 0 ? '<br><br>Error detail:<br>' + data.errors.join('<br>') : ''),
                            confirmButtonColor: '#ffc107'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        progressBar.removeClass('bg-info').addClass('bg-danger');
                        statusBadge.html('<span class="badge bg-danger">Gagal</span>');
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message || 'Terjadi kesalahan saat mengimpor file',
                            confirmButtonColor: '#ffc107'
                        });
                    }
                } catch (e) {
                    progressBar.removeClass('bg-info').addClass('bg-danger');
                    statusBadge.html('<span class="badge bg-danger">Error</span>');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat memproses response',
                        confirmButtonColor: '#ffc107'
                    });
                }
            },
            error: function(xhr, status, error) {
                progressBar.removeClass('bg-info').addClass('bg-danger');
                statusBadge.html('<span class="badge bg-danger">Error</span>');
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat mengupload file: ' + error,
                    confirmButtonColor: '#ffc107'
                });
            }
        });
    }
</script>
<?php endif; ?>

