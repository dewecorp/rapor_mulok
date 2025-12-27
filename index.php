<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Inisialisasi variabel
$total_guru = 0;
$total_siswa = 0;
$total_kelas = 0;
$total_materi = 0;
$materi_diampu = null;
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
        $result = $conn->query("SELECT COUNT(*) as total FROM siswa");
        $total_siswa = $result ? $result->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_siswa = 0;
    }
    
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM kelas");
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
    
    try {
        $query_materi = "SELECT mm.* FROM mengampu_materi mm 
                         INNER JOIN pengguna p ON mm.guru_id = p.id 
                         WHERE p.id = $user_id";
        $materi_diampu = $conn->query($query_materi);
    } catch (Exception $e) {
        $materi_diampu = null;
    }
    
    // Cek apakah user adalah wali kelas
    try {
        $result = $conn->query("SELECT COUNT(*) as total FROM kelas WHERE wali_kelas_id = $user_id");
        $is_wali_kelas = $result ? ($result->fetch_assoc()['total'] > 0) : false;
    } catch (Exception $e) {
        $is_wali_kelas = false;
    }
} elseif ($role == 'guru') {
    // Dashboard Guru
    try {
        $query_materi = "SELECT mm.*, m.nama_mulok FROM mengampu_materi mm 
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id 
                         WHERE mm.guru_id = $user_id";
        $materi_diampu = $conn->query($query_materi);
    } catch (Exception $e) {
        $materi_diampu = null;
    }
}
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
            
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama']); ?>!</h6>
                </div>
                <div class="card-body">
                    <p>Anda login sebagai <strong>Proktor/Admin</strong>. Gunakan menu di sidebar untuk mengakses fitur-fitur aplikasi.</p>
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
                    $default_info = 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                $info_aplikasi = 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Info Aplikasi</h6>
                </div>
                <div class="card-body">
                    <div class="info-aplikasi">
                        <?php 
                        if (!empty($info_aplikasi)) {
                            echo $info_aplikasi; 
                        } else {
                            echo 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                <div class="card-body">
                    <?php if (count($aktivitas_data) > 0): ?>
                        <div class="timeline-container">
                            <div class="timeline">
                                <?php foreach ($aktivitas_data as $index => $aktivitas): 
                                    // Set timezone ke Asia/Jakarta di awal
                                    date_default_timezone_set('Asia/Jakarta');
                                    
                                    // Parse waktu dari database
                                    // MySQL datetime disimpan dalam format 'Y-m-d H:i:s'
                                    // Asumsikan waktu database sudah dalam timezone lokal (Asia/Jakarta)
                                    $waktu_db = $aktivitas['waktu_login'];
                                    
                                    // Buat DateTime object dari waktu database dengan timezone Asia/Jakarta
                                    $dt_waktu = DateTime::createFromFormat('Y-m-d H:i:s', $waktu_db, new DateTimeZone('Asia/Jakarta'));
                                    if (!$dt_waktu) {
                                        // Jika format tidak cocok, coba parse sebagai string biasa
                                        $dt_waktu = new DateTime($waktu_db, new DateTimeZone('Asia/Jakarta'));
                                    }
                                    
                                    // Waktu sekarang dengan timezone yang sama
                                    $dt_sekarang = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                                    
                                    // Hitung selisih dalam detik (timestamp sudah dalam UTC, jadi bisa langsung dikurang)
                                    $selisih_detik = $dt_sekarang->getTimestamp() - $dt_waktu->getTimestamp();
                                    
                                    // Pastikan selisih tidak negatif (jika waktu di masa depan, anggap baru saja)
                                    if ($selisih_detik < 0) {
                                        $selisih_detik = 0;
                                    }
                                    
                                    // Hitung menit, jam, hari
                                    $menit = floor($selisih_detik / 60);
                                    $jam = floor($selisih_detik / 3600);
                                    $hari = floor($selisih_detik / 86400);
                                    
                                    // Format waktu relatif dengan perhitungan yang benar
                                    if ($selisih_detik < 60) {
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
                                    
                                    // Untuk format tanggal lengkap, gunakan timestamp dari DateTime object
                                    $waktu = $dt_waktu->getTimestamp();
                                    
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
                            <img src="uploads/<?php echo htmlspecialchars($_SESSION['foto'] ?? 'default.png'); ?>" 
                                 alt="Foto" class="rounded-circle mb-3" width="150" height="150" 
                                 style="object-fit: cover;" onerror="this.onerror=null; this.style.display='none';">
                            <h5><?php echo htmlspecialchars($_SESSION['nama']); ?></h5>
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
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Jumlah Siswa</h6>
                                    <h2 class="mb-0"><?php echo $total_siswa; ?></h2>
                                </div>
                                <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Materi yang Diampu</h6>
                                    <h2 class="mb-0"><?php echo $materi_diampu ? $materi_diampu->num_rows : 0; ?></h2>
                                </div>
                                <i class="fas fa-book fa-3x opacity-50"></i>
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
                    <?php if ($materi_diampu && $materi_diampu->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Materi Mulok</th>
                                        <th>Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    while ($materi = $materi_diampu->fetch_assoc()): 
                                        try {
                                            $materi_result = $conn->query("SELECT nama_mulok FROM materi_mulok WHERE id = " . $materi['materi_mulok_id']);
                                            $materi_mulok = $materi_result ? $materi_result->fetch_assoc() : ['nama_mulok' => '-'];
                                        } catch (Exception $e) {
                                            $materi_mulok = ['nama_mulok' => '-'];
                                        }
                                        
                                        try {
                                            $kelas_result = $conn->query("SELECT nama_kelas FROM kelas WHERE id = " . $materi['kelas_id']);
                                            $kelas = $kelas_result ? $kelas_result->fetch_assoc() : ['nama_kelas' => '-'];
                                        } catch (Exception $e) {
                                            $kelas = ['nama_kelas' => '-'];
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($materi_mulok['nama_mulok'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($kelas['nama_kelas'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Belum ada materi yang diampu.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Buat tabel dan ambil info aplikasi untuk wali kelas
            $info_aplikasi_wk = '';
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
                    $default_info = 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                    $stmt = $conn->prepare("INSERT INTO pengaturan_aplikasi (info_aplikasi) VALUES (?)");
                    $stmt->bind_param("s", $default_info);
                    $stmt->execute();
                }
                
                // Ambil info aplikasi
                $result_info = $conn->query("SELECT info_aplikasi FROM pengaturan_aplikasi LIMIT 1");
                if ($result_info && $result_info->num_rows > 0) {
                    $info_data = $result_info->fetch_assoc();
                    $info_aplikasi_wk = $info_data['info_aplikasi'] ?? '';
                }
            } catch (Exception $e) {
                // Error, tetap tampilkan default
                $info_aplikasi_wk = 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
            }
            ?>
            
            <div class="card mt-3">
                <div class="card-header" style="background-color: #2d5016; color: white;">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Info Aplikasi</h6>
                </div>
                <div class="card-body">
                    <div class="info-aplikasi">
                        <?php 
                        if (!empty($info_aplikasi_wk)) {
                            echo $info_aplikasi_wk; 
                        } else {
                            echo 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($role == 'guru'): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <img src="uploads/<?php echo htmlspecialchars($_SESSION['foto'] ?? 'default.png'); ?>" 
                                 alt="Foto" class="rounded-circle mb-3" width="150" height="150" 
                                 style="object-fit: cover;" onerror="this.onerror=null; this.style.display='none';">
                            <h5><?php echo htmlspecialchars($_SESSION['nama']); ?></h5>
                            <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2">Materi yang Diampu</h6>
                                    <h2 class="mb-0"><?php echo $materi_diampu ? $materi_diampu->num_rows : 0; ?></h2>
                                </div>
                                <i class="fas fa-book fa-3x opacity-50"></i>
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
                    <?php if ($materi_diampu && $materi_diampu->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Materi Mulok</th>
                                        <th>Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    while ($materi = $materi_diampu->fetch_assoc()): 
                                        try {
                                            $kelas_result = $conn->query("SELECT nama_kelas FROM kelas WHERE id = " . $materi['kelas_id']);
                                            $kelas = $kelas_result ? $kelas_result->fetch_assoc() : ['nama_kelas' => '-'];
                                        } catch (Exception $e) {
                                            $kelas = ['nama_kelas' => '-'];
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_mulok'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($kelas['nama_kelas'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Belum ada materi yang diampu.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Buat tabel dan ambil info aplikasi untuk guru
            $info_aplikasi_guru = '';
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
                    $default_info = 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                    $stmt = $conn->prepare("INSERT INTO pengaturan_aplikasi (info_aplikasi) VALUES (?)");
                    $stmt->bind_param("s", $default_info);
                    $stmt->execute();
                }
                
                // Ambil info aplikasi
                $result_info = $conn->query("SELECT info_aplikasi FROM pengaturan_aplikasi LIMIT 1");
                if ($result_info && $result_info->num_rows > 0) {
                    $info_data = $result_info->fetch_assoc();
                    $info_aplikasi_guru = $info_data['info_aplikasi'] ?? '';
                }
            } catch (Exception $e) {
                // Error, tetap tampilkan default
                $info_aplikasi_guru = 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
            }
            ?>
            
            <div class="card mt-3">
                <div class="card-header" style="background-color: #2d5016; color: white;">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Info Aplikasi</h6>
                </div>
                <div class="card-body">
                    <div class="info-aplikasi">
                        <?php 
                        if (!empty($info_aplikasi_guru)) {
                            echo $info_aplikasi_guru; 
                        } else {
                            echo 'Selamat datang di aplikasi Rapor Mulok Khusus. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
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
    animation: fadeInUp 0.5s ease-out;
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
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(45, 80, 22, 0.3);
    z-index: 1;
}

.timeline-content {
    background: #f8f9fa;
    border-left: 3px solid #2d5016;
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.timeline-content:hover {
    background: #e9ecef;
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-aplikasi {
    line-height: 1.8;
    color: #333;
    font-size: 1.25rem;
}

.info-aplikasi p {
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.info-aplikasi h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: #2d5016;
}

.info-aplikasi h2 {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 1.25rem;
    color: #2d5016;
}

.info-aplikasi h3 {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #2d5016;
}

.info-aplikasi h4 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #2d5016;
}

.info-aplikasi h5, .info-aplikasi h6 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.info-aplikasi ul, .info-aplikasi ol {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    padding-left: 2rem;
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
</style>

<?php include 'includes/footer.php'; ?>

