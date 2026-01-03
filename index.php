<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Validasi dan sinkronisasi role dari database
// Pastikan role di session sesuai dengan role di database
try {
    $stmt_role = $conn->prepare("SELECT role FROM pengguna WHERE id = ? LIMIT 1");
    $stmt_role->bind_param("i", $user_id);
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();
    if ($result_role && $result_role->num_rows > 0) {
        $user_db = $result_role->fetch_assoc();
        $role_db = $user_db['role'] ?? null;
        
        // Jika role di session berbeda dengan database, update session
        if ($role_db && (!isset($_SESSION['role']) || $_SESSION['role'] != $role_db)) {
            $_SESSION['role'] = $role_db;
        }
    }
    $stmt_role->close();
} catch (Exception $e) {
    // Ignore error, gunakan role dari session
}

$role = $_SESSION['role'] ?? 'guru'; // Default ke guru jika tidak ada

// Inisialisasi variabel
$total_guru = 0;
$total_siswa = 0;
$total_kelas = 0;
$total_materi = 0;
$materi_diampu = null;
$materi_data = [];
$is_wali_kelas = false;
$kelas_id = 0;

// Ambil data untuk dashboard berdasarkan role
if ($role == 'proktor') {
    // Dashboard Proktor
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM pengguna WHERE role IN ('guru', 'wali_kelas')");
        $total_guru = $result ? $result->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_guru = 0;
    }
    
    try {
        // Hitung siswa yang tidak berada di kelas Alumni
        $result = $conn->query("SELECT COUNT(*) as total FROM siswa s 
                                INNER JOIN kelas k ON s.kelas_id = k.id 
                                WHERE LOWER(TRIM(k.nama_kelas)) != 'alumni' 
                                AND LOWER(TRIM(k.nama_kelas)) NOT LIKE '%lulus%'");
        $total_siswa = $result ? $result->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_siswa = 0;
    }
    
    try {
        // Hitung kelas yang bukan Alumni
        $result = $conn->query("SELECT COUNT(*) as total FROM kelas 
                                WHERE LOWER(TRIM(nama_kelas)) != 'alumni' 
                                AND LOWER(TRIM(nama_kelas)) NOT LIKE '%lulus%'");
        $total_kelas = $result ? $result->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_kelas = 0;
    }
    
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM materi_mulok");
        $total_materi = $result ? $result->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_materi = 0;
    }
} elseif ($role == 'wali_kelas') {
    // Dashboard Wali Kelas
    try {
        $query_kelas = "SELECT id FROM kelas WHERE wali_kelas_id = $user_id LIMIT 1";
        $result = $conn->query($query_kelas);
        $kelas_data = $result ? $result->fetch_assoc() : null;
        $kelas_id = $kelas_data['id'] ?? 0;
    } catch (Exception $e) {
        $kelas_id = 0;
    }
    
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM siswa WHERE kelas_id = $kelas_id");
        $total_siswa = $result ? $result->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_siswa = 0;
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
    
    // Ambil materi yang diampu di kelas dalam semester aktif
    $materi_data = [];
    if ($kelas_id > 0) {
        try {
            $stmt = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok
                      FROM mengampu_materi mm
                      INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                      WHERE mm.guru_id = ? AND mm.kelas_id = ? AND m.semester = ?
                      ORDER BY m.nama_mulok");
            $stmt->bind_param("iis", $user_id, $kelas_id, $semester_aktif);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $materi_id = $row['id'];
                    
                    // Cek apakah nilai sudah dikirim untuk materi ini
                    $status_nilai = 'belum';
                    if (!empty($tahun_ajaran)) {
                        $stmt_cek = $conn->prepare("SELECT status FROM nilai_kirim_status 
                                                   WHERE materi_mulok_id = ? 
                                                   AND kelas_id = ? 
                                                   AND semester = ? 
                                                   AND tahun_ajaran = ? 
                                                   AND status = 'terkirim'");
                        $stmt_cek->bind_param("iiss", $materi_id, $kelas_id, $semester_aktif, $tahun_ajaran);
                        $stmt_cek->execute();
                        $result_cek = $stmt_cek->get_result();
                        if ($result_cek && $result_cek->num_rows > 0) {
                            $status_nilai = 'terkirim';
                        }
                        $stmt_cek->close();
                    }
                    
                    $materi_data[] = [
                        'id' => $row['id'],
                        'nama_mulok' => $row['nama_mulok'],
                        'status_nilai' => $status_nilai
                    ];
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $materi_data = [];
        }
    }
    
    // Untuk kompatibilitas dengan kode lama
    $materi_diampu = null;
    if (!empty($materi_data)) {
        // Buat object result dummy untuk kompatibilitas
        $materi_diampu = (object)['num_rows' => count($materi_data)];
    }
    
    // Cek apakah user adalah wali kelas
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM kelas WHERE wali_kelas_id = $user_id");
        $is_wali_kelas = $result ? ($result->fetch_assoc()['total'] > 0) : false;
    } catch (Exception $e) {
        $is_wali_kelas = false;
    }
    
    // Ambil data lengkap user untuk identitas
    $user_data = null;
    try {
        $stmt_user = $conn->prepare("SELECT nama, tempat_lahir, tanggal_lahir, pendidikan FROM pengguna WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $user_data = $result_user ? $result_user->fetch_assoc() : null;
        $stmt_user->close();
    } catch (Exception $e) {
        $user_data = null;
    }
} elseif ($role == 'guru') {
    // Dashboard Guru - sama dengan wali kelas tapi tanpa menu wali kelas
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
    
    // Ambil materi yang diampu di semester aktif
    $materi_data = [];
    try {
        $stmt = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok, k.nama_kelas, k.id as kelas_id
                      FROM mengampu_materi mm
                      INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                      INNER JOIN kelas k ON mm.kelas_id = k.id
                      WHERE mm.guru_id = ? AND m.semester = ?
                      ORDER BY m.nama_mulok");
        $stmt->bind_param("is", $user_id, $semester_aktif);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $materi_id = $row['id'];
                $kelas_id_materi = $row['kelas_id'];
                
                // Cek apakah nilai sudah dikirim untuk materi ini
                $status_nilai = 'belum';
                if (!empty($tahun_ajaran)) {
                    $stmt_cek = $conn->prepare("SELECT status FROM nilai_kirim_status 
                                               WHERE materi_mulok_id = ? 
                                               AND kelas_id = ? 
                                               AND semester = ? 
                                               AND tahun_ajaran = ? 
                                               AND status = 'terkirim'");
                    $stmt_cek->bind_param("iiss", $materi_id, $kelas_id_materi, $semester_aktif, $tahun_ajaran);
                    $stmt_cek->execute();
                    $result_cek = $stmt_cek->get_result();
                    if ($result_cek && $result_cek->num_rows > 0) {
                        $status_nilai = 'terkirim';
                    }
                    $stmt_cek->close();
                }
                
                $materi_data[] = [
                    'id' => $row['id'],
                    'nama_mulok' => $row['nama_mulok'],
                    'nama_kelas' => $row['nama_kelas'],
                    'status_nilai' => $status_nilai
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        $materi_data = [];
    }
    
    // Untuk kompatibilitas dengan kode lama
    $materi_diampu = null;
    if (!empty($materi_data)) {
        // Buat object result dummy untuk kompatibilitas
        $materi_diampu = (object)['num_rows' => count($materi_data)];
    }
    
    // Ambil data lengkap user untuk identitas
    $user_data = null;
    try {
        $stmt_user = $conn->prepare("SELECT nama, tempat_lahir, tanggal_lahir, pendidikan FROM pengguna WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $user_data = $result_user ? $result_user->fetch_assoc() : null;
        $stmt_user->close();
    } catch (Exception $e) {
        $user_data = null;
    }
}

// Set page title (variabel lokal)
$page_title = 'Dashboard';
?>
<?php include 'includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-home"></i> Dashboard</h5>
    </div>
    <div class="card-body">
        <?php if ($role == 'proktor'): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Guru</h6>
                                    <h2 class="mb-0"><?php echo $total_guru; ?></h2>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Siswa</h6>
                                    <h2 class="mb-0"><?php echo $total_siswa; ?></h2>
                                </div>
                                <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Kelas</h6>
                                    <h2 class="mb-0"><?php echo $total_kelas; ?></h2>
                                </div>
                                <i class="fas fa-school fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Total Materi</h6>
                                    <h2 class="mb-0"><?php echo $total_materi; ?></h2>
                                </div>
                                <i class="fas fa-book fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            // Buat tabel dan ambil info aplikasi
            $info_aplikasi = '';
            try {
                // Buat tabel jika belum ada
                $conn->query("CREATE TABLE IF NOT EXISTS `pengaturan_aplikasi` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `info_aplikasi` text DEFAULT NULL,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `updated_by` int(11) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Cek apakah ada data, jika tidak insert default
                $check = $conn->query("SELECT COUNT(*) as total FROM pengaturan_aplikasi");
                $count = $check ? $check->fetch_assoc()['total'] : 0;
                if ($count == 0) {
                    $default_info = 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                    $stmt = $conn->prepare("INSERT INTO pengaturan_aplikasi (info_aplikasi) VALUES (?)");
                    $stmt->bind_param("s", $default_info);
                    $stmt->execute();
                }
                
                // Ambil info aplikasi
                $result_info = $conn->query("SELECT info_aplikasi FROM pengaturan_aplikasi LIMIT 1");
                if ($result_info && $result_info->num_rows > 0) {
                    $info_data = $result_info->fetch_assoc();
                    $info_aplikasi = $info_data['info_aplikasi'] ?? '';
                }
            } catch (Exception $e) {
                // Error, tetap tampilkan default
                $info_aplikasi = 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
            }
            
            // Ambil aktivitas login (24 jam terakhir)
            $aktivitas_data = [];
            $total_aktivitas = 0;
            try {
                // Buat tabel jika belum ada
                $conn->query("CREATE TABLE IF NOT EXISTS `aktivitas_login` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `nama` varchar(255) NOT NULL,
                    `role` varchar(50) NOT NULL,
                    `ip_address` varchar(50) DEFAULT NULL,
                    `user_agent` text DEFAULT NULL,
                    `waktu_login` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_waktu_login` (`waktu_login`),
                    KEY `idx_role` (`role`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Hapus aktivitas yang lebih dari 24 jam
                $conn->query("DELETE FROM aktivitas_login WHERE waktu_login < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                
                // Ambil aktivitas 24 jam terakhir
                $query_aktivitas = "SELECT * FROM aktivitas_login ORDER BY waktu_login DESC LIMIT 50";
                $result_aktivitas = $conn->query($query_aktivitas);
                if ($result_aktivitas) {
                    while ($row = $result_aktivitas->fetch_assoc()) {
                        $aktivitas_data[] = $row;
                    }
                    $total_aktivitas = $result_aktivitas->num_rows;
                }
            } catch (Exception $e) {
                // Error
            }
            ?>
            
            <div class="card mt-3">
                <div class="card-header" style="background-color: #2d5016; color: white;">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Info RMD</h6>
                </div>
                <div class="card-body">
                    <div class="info-aplikasi">
                        <?php 
                        if (!empty($info_aplikasi)) {
                            echo $info_aplikasi; 
                        } else {
                            echo 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header" style="background-color: #2d5016; color: white;">
                    <h6 class="mb-0">
                        <i class="fas fa-history"></i> Aktivitas Login (24 Jam Terakhir)
                        <span class="badge bg-light text-dark ms-2"><?php echo $total_aktivitas; ?></span>
                    </h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                    <?php if (count($aktivitas_data) > 0): ?>
                        <div class="timeline-container">
                            <div class="timeline">
                                <?php foreach ($aktivitas_data as $index => $aktivitas): 
                                    $waktu = strtotime($aktivitas['waktu_login']);
                                    $selisih = time() - $waktu;
                                    $menit = floor($selisih / 60);
                                    $jam = floor($selisih / 3600);
                                    $hari = floor($selisih / 86400);
                                    
                                    // Format waktu relatif
                                    if ($menit < 1) {
                                        $waktu_text = 'Baru saja';
                                    } elseif ($menit < 60) {
                                        $waktu_text = $menit . ' menit yang lalu';
                                    } elseif ($jam < 24) {
                                        $waktu_text = $jam . ' jam yang lalu';
                                    } elseif ($hari == 1) {
                                        $waktu_text = 'Kemarin';
                                    } else {
                                        $waktu_text = $hari . ' hari yang lalu';
                                    }
                                    
                                    // Format tanggal dan waktu lengkap
                                    $tanggal_waktu = date('d/m/Y H:i:s', $waktu);
                                    $hari_nama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                                    $bulan_nama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                    $hari_indonesia = $hari_nama[date('w', $waktu)];
                                    $tanggal_lengkap = $hari_indonesia . ', ' . date('d', $waktu) . ' ' . $bulan_nama[date('n', $waktu) - 1] . ' ' . date('Y', $waktu) . ' pukul ' . date('H:i:s', $waktu);
                                    
                                    $role_badge = [
                                        'proktor' => 'danger',
                                        'wali_kelas' => 'info',
                                        'guru' => 'success'
                                    ];
                                    $badge_color = $role_badge[$aktivitas['role']] ?? 'secondary';
                                ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($aktivitas['nama']); ?>
                                                        <span class="badge bg-<?php echo $badge_color; ?> ms-2">
                                                            <?php echo ucfirst(str_replace('_', ' ', $aktivitas['role'])); ?>
                                                        </span>
                                                    </h6>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-clock"></i> <strong><?php echo $waktu_text; ?></strong>
                                                        <span class="ms-2 text-muted" style="font-size: 0.9em;">
                                                            (<?php echo $tanggal_lengkap; ?>)
                                                        </span>
                                                    </p>
                                                    <p class="text-muted mb-0" style="font-size: 0.85em;">
                                                        <i class="fas fa-globe"></i> IP: <?php echo htmlspecialchars($aktivitas['ip_address'] ?? 'unknown'); ?>
                                                        <span class="ms-3">
                                                            <i class="fas fa-calendar-alt"></i> <?php echo $tanggal_waktu; ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-inbox"></i> Belum ada aktivitas login dalam 24 jam terakhir.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($role == 'wali_kelas'): ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="position-relative d-inline-block">
                                <?php 
                                $foto_wali = $_SESSION['foto'] ?? '';
                                $nama_wali = $_SESSION['nama'] ?? 'User';
                                $inisial_wali = strtoupper(substr($nama_wali, 0, 1));
                                
                                // Cek apakah foto ada dan file exist
                                if (!empty($foto_wali) && file_exists('uploads/' . $foto_wali) && $foto_wali != 'default.png') {
                                    $foto_src = 'uploads/' . htmlspecialchars($foto_wali);
                                    $use_avatar = false;
                                } else {
                                    // Gunakan avatar dengan inisial
                                    $foto_src = 'data:image/svg+xml;base64,' . base64_encode('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><circle cx="75" cy="75" r="75" fill="#2d5016"/><text x="75" y="75" font-family="Arial, sans-serif" font-size="60" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inisial_wali) . '</text></svg>');
                                    $use_avatar = true;
                                }
                                ?>
                                <img src="<?php echo $foto_src; ?>" 
                                     alt="Foto" class="rounded-circle mb-3" width="150" height="150" 
                                     id="fotoProfilWaliKelas"
                                     style="object-fit: cover;" 
                                     onerror="<?php if (!$use_avatar): ?>this.onerror=null; this.src='data:image/svg+xml;base64,<?php echo base64_encode('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><circle cx="75" cy="75" r="75" fill="#2d5016"/><text x="75" y="75" font-family="Arial, sans-serif" font-size="60" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inisial_wali) . '</text></svg>'); ?>';<?php endif; ?>">
                                <?php if (!$use_avatar): ?>
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                        style="width: 40px; height: 40px; padding: 0; border: 2px solid white;"
                                        onclick="openEditFotoModal('wali_kelas')" title="Edit Foto">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                        style="width: 40px; height: 40px; padding: 0; border: 2px solid white;"
                                        onclick="openEditFotoModal('wali_kelas')" title="Upload Foto">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <h5><?php echo htmlspecialchars($nama_wali); ?></h5>
                            <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></p>
                            <?php if ($is_wali_kelas): ?>
                                <span class="badge bg-success">Wali Kelas Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Bukan Wali Kelas</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header" style="background-color: #2d5016; color: white;">
                            <h6 class="mb-0"><i class="fas fa-school"></i> Identitas Kelas</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong><i class="fas fa-user-graduate"></i> Jumlah Siswa:</strong><br>
                                <h4 class="mb-0 mt-1"><?php echo $total_siswa; ?></h4>
                            </div>
                            <div class="mb-0">
                                <strong><i class="fas fa-book"></i> Materi yang Diampu:</strong><br>
                                <h4 class="mb-0 mt-1"><?php echo !empty($materi_data) ? count($materi_data) : 0; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header" style="background-color: #2d5016; color: white;">
                            <h6 class="mb-0"><i class="fas fa-id-card"></i> Identitas Wali</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong><i class="fas fa-user"></i> Nama:</strong><br>
                                <span><?php echo htmlspecialchars($user_data['nama'] ?? $nama_wali); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-map-marker-alt"></i> Tempat Lahir:</strong><br>
                                <span><?php echo htmlspecialchars($user_data['tempat_lahir'] ?? '-'); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-calendar"></i> Tanggal Lahir:</strong><br>
                                <span><?php echo !empty($user_data['tanggal_lahir']) ? date('d/m/Y', strtotime($user_data['tanggal_lahir'])) : '-'; ?></span>
                            </div>
                            <div class="mb-0">
                                <strong><i class="fas fa-graduation-cap"></i> Pendidikan:</strong><br>
                                <span><?php echo htmlspecialchars($user_data['pendidikan'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Materi yang Diampu</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($materi_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Materi Mulok</th>
                                        <th>Status Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    foreach ($materi_data as $materi): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_mulok']); ?></td>
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
                        <p class="text-muted">Belum ada materi yang diampu.</p>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($role == 'guru'): ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="position-relative d-inline-block">
                                <?php 
                                $foto_guru = $_SESSION['foto'] ?? '';
                                $nama_guru = $_SESSION['nama'] ?? 'User';
                                $inisial_guru = strtoupper(substr($nama_guru, 0, 1));
                                
                                // Cek apakah foto ada dan file exist
                                if (!empty($foto_guru) && file_exists('uploads/' . $foto_guru) && $foto_guru != 'default.png') {
                                    $foto_src = 'uploads/' . htmlspecialchars($foto_guru);
                                    $use_avatar = false;
                                } else {
                                    // Gunakan avatar dengan inisial
                                    $foto_src = 'data:image/svg+xml;base64,' . base64_encode('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><circle cx="75" cy="75" r="75" fill="#2d5016"/><text x="75" y="75" font-family="Arial, sans-serif" font-size="60" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inisial_guru) . '</text></svg>');
                                    $use_avatar = true;
                                }
                                ?>
                                <img src="<?php echo $foto_src; ?>" 
                                     alt="Foto" class="rounded-circle mb-3" width="150" height="150" 
                                     id="fotoProfilGuru"
                                     style="object-fit: cover;" 
                                     onerror="<?php if (!$use_avatar): ?>this.onerror=null; this.src='data:image/svg+xml;base64,<?php echo base64_encode('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><circle cx="75" cy="75" r="75" fill="#2d5016"/><text x="75" y="75" font-family="Arial, sans-serif" font-size="60" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">' . htmlspecialchars($inisial_guru) . '</text></svg>'); ?>';<?php endif; ?>">
                                <?php if (!$use_avatar): ?>
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                        style="width: 40px; height: 40px; padding: 0; border: 2px solid white;"
                                        onclick="openEditFotoModal('guru')" title="Edit Foto">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                        style="width: 40px; height: 40px; padding: 0; border: 2px solid white;"
                                        onclick="openEditFotoModal('guru')" title="Upload Foto">
                                    <i class="fas fa-camera"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <h5><?php echo htmlspecialchars($nama_guru); ?></h5>
                            <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header" style="background-color: #2d5016; color: white;">
                            <h6 class="mb-0"><i class="fas fa-book"></i> Materi yang Diampu</h6>
                        </div>
                        <div class="card-body">
                            <h2 class="mb-0"><?php echo !empty($materi_data) ? count($materi_data) : 0; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header" style="background-color: #2d5016; color: white;">
                            <h6 class="mb-0"><i class="fas fa-id-card"></i> Identitas Guru</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong><i class="fas fa-user"></i> Nama:</strong><br>
                                <span><?php echo htmlspecialchars($user_data['nama'] ?? $nama_guru); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-map-marker-alt"></i> Tempat Lahir:</strong><br>
                                <span><?php echo htmlspecialchars($user_data['tempat_lahir'] ?? '-'); ?></span>
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-calendar"></i> Tanggal Lahir:</strong><br>
                                <span><?php echo !empty($user_data['tanggal_lahir']) ? date('d/m/Y', strtotime($user_data['tanggal_lahir'])) : '-'; ?></span>
                            </div>
                            <div class="mb-0">
                                <strong><i class="fas fa-graduation-cap"></i> Pendidikan:</strong><br>
                                <span><?php echo htmlspecialchars($user_data['pendidikan'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Materi yang Diampu</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($materi_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Materi Mulok</th>
                                        <th>Kelas</th>
                                        <th>Status Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    foreach ($materi_data as $materi): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_mulok']); ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_kelas']); ?></td>
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
                        <p class="text-muted">Belum ada materi yang diampu.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Style untuk card statistik dashboard */
.card.bg-primary .card-body h2,
.card.bg-success .card-body h2,
.card.bg-info .card-body h2,
.card.bg-warning .card-body h2 {
    font-size: 1.75rem;
}

.card.bg-primary .card-body h6,
.card.bg-success .card-body h6,
.card.bg-info .card-body h6,
.card.bg-warning .card-body h6 {
    font-size: 0.9rem;
}

.card.bg-primary .card-body i,
.card.bg-success .card-body i,
.card.bg-info .card-body i,
.card.bg-warning .card-body i {
    font-size: 2rem !important;
}

.timeline-container {
    position: relative;
    padding: 20px 0;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #2d5016, #4a7c2a);
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
    padding-left: 40px;
    /* Disable animation untuk performa yang lebih baik */
    /* animation: fadeInUp 0.5s ease-out; */
}

.timeline-item:last-child {
    margin-bottom: 0;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.timeline-marker {
    position: absolute;
    left: -15px;
    top: 0;
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #2d5016, #4a7c2a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(45, 80, 22, 0.3);
    z-index: 1;
}

.timeline-content {
    background: #f8f9fa;
    border-left: 3px solid #2d5016;
    padding: 12px;
    border-radius: 8px;
    transition: background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    font-size: 13px;
}

.timeline-content h6 {
    font-size: 0.95rem;
}

.timeline-content p {
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
}

.timeline-content:hover {
    background: #e9ecef;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-aplikasi {
    line-height: 1.6;
    color: #333;
    font-size: 13px;
}

.info-aplikasi p {
    font-size: 13px;
    margin-bottom: 0.75rem;
}

.info-aplikasi h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #2d5016;
}

.info-aplikasi h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.875rem;
    color: #2d5016;
}

.info-aplikasi h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #2d5016;
}

.info-aplikasi h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.625rem;
    color: #2d5016;
}

.info-aplikasi h5, .info-aplikasi h6 {
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 0.625rem;
}

.info-aplikasi ul, .info-aplikasi ol {
    font-size: 13px;
    margin-bottom: 0.75rem;
    padding-left: 1.5rem;
}

.info-aplikasi li {
    margin-bottom: 0.5rem;
}

.info-aplikasi strong, .info-aplikasi b {
    font-weight: 600;
    color: #2d5016;
}

.info-aplikasi a {
    color: #2d5016;
    text-decoration: underline;
}

.info-aplikasi a:hover {
    color: #4a7c2a;
}

/* Style untuk foto profil dengan tombol edit */
.position-relative.d-inline-block {
    position: relative;
    display: inline-block;
}

.position-relative.d-inline-block .btn {
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.position-relative.d-inline-block .btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

#previewFoto {
    border: 3px solid #2d5016;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<!-- Modal Edit Foto -->
<div class="modal fade" id="modalEditFoto" tabindex="-1" aria-labelledby="modalEditFotoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditFotoLabel"><i class="fas fa-camera"></i> Edit Foto Profil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditFoto" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img id="previewFoto" src="" alt="Preview" class="rounded-circle" width="200" height="200" style="object-fit: cover; border: 3px solid #2d5016;">
                    </div>
                    <div class="mb-3">
                        <label for="fotoInput" class="form-label">Pilih Foto</label>
                        <input type="file" class="form-control" id="fotoInput" name="foto" accept="image/*" required>
                        <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 2MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Foto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditFotoModal(role) {
    // Ambil foto saat ini
    var fotoElement = role === 'wali_kelas' ? document.getElementById('fotoProfilWaliKelas') : document.getElementById('fotoProfilGuru');
    var fotoSrc = fotoElement ? fotoElement.src : 'uploads/default.png';
    
    // Set preview foto
    document.getElementById('previewFoto').src = fotoSrc;
    
    // Reset form
    document.getElementById('formEditFoto').reset();
    document.getElementById('fotoInput').value = '';
    
    // Buka modal
    var modal = new bootstrap.Modal(document.getElementById('modalEditFoto'));
    modal.show();
    
    // Simpan role untuk digunakan saat submit
    document.getElementById('formEditFoto').setAttribute('data-role', role);
}

// Preview foto saat file dipilih (event delegation)
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'fotoInput') {
        var file = e.target.files[0];
        if (file) {
            // Validasi ukuran file (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Ukuran File Terlalu Besar',
                    text: 'Ukuran file maksimal 2MB',
                    confirmButtonColor: '#2d5016'
                });
                e.target.value = '';
                return;
            }
            
            // Validasi tipe file
            if (!file.type.match('image.*')) {
                Swal.fire({
                    icon: 'error',
                    title: 'Format File Tidak Valid',
                    text: 'Hanya file gambar yang diperbolehkan',
                    confirmButtonColor: '#2d5016'
                });
                e.target.value = '';
                return;
            }
            
            // Preview gambar
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewFoto').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
});

// Handle submit form
document.getElementById('formEditFoto').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var form = this;
    var formData = new FormData(form);
    formData.append('action', 'upload_foto');
    
    // Tampilkan loading
    Swal.fire({
        title: 'Mengupload Foto...',
        text: 'Mohon tunggu',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('upload_foto.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: 'Foto profil berhasil diupdate',
                confirmButtonColor: '#2d5016'
            }).then(() => {
                // Update foto di halaman
                var role = form.getAttribute('data-role');
                var fotoElement = role === 'wali_kelas' ? document.getElementById('fotoProfilWaliKelas') : document.getElementById('fotoProfilGuru');
                if (fotoElement) {
                    fotoElement.src = 'uploads/' + data.filename + '?t=' + new Date().getTime();
                }
                
                // Update session foto di navbar jika ada (hanya untuk non-admin)
                var navbarFoto = document.getElementById('userAvatarDropdown');
                if (navbarFoto && role !== 'proktor') {
                    navbarFoto.src = 'uploads/' + data.filename + '?t=' + new Date().getTime();
                    navbarFoto.onerror = null; // Reset error handler
                }
                
                // Tutup modal
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalEditFoto'));
                modal.hide();
                
                // Reload halaman setelah 1 detik untuk memastikan semua foto terupdate
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: data.message || 'Gagal mengupload foto',
                confirmButtonColor: '#2d5016'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Terjadi kesalahan saat mengupload foto',
            confirmButtonColor: '#2d5016'
        });
    });
});
</script>

<script>
// Auto close alert sukses di dashboard wali dan guru
$(document).ready(function() {
    // Cek apakah role adalah wali_kelas atau guru
    var role = '<?php echo $role; ?>';
    
    if (role === 'wali_kelas' || role === 'guru') {
        // Fungsi untuk auto close alert sukses
        function autoCloseSuccessAlert($alert) {
            setTimeout(function() {
                if ($alert.hasClass('alert-dismissible')) {
                    // Gunakan Bootstrap alert close method
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        var bsAlert = new bootstrap.Alert($alert[0]);
                        bsAlert.close();
                    } else {
                        // Fallback: fade out dan remove
                        $alert.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                } else {
                    // Fade out dan remove
                    $alert.fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            }, 3000);
        }
        
        // Cari semua alert sukses yang sudah ada
        $('.alert-success').each(function() {
            autoCloseSuccessAlert($(this));
        });
        
        // Handle alert sukses yang muncul setelah halaman dimuat (menggunakan MutationObserver)
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.hasClass('alert-success')) {
                                autoCloseSuccessAlert($node);
                            }
                            // Juga cek child elements
                            $node.find('.alert-success').each(function() {
                                autoCloseSuccessAlert($(this));
                            });
                        }
                    });
                });
            });
            
            // Observe perubahan di body
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>

