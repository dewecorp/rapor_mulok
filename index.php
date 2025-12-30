<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireLogin();

// Set timezone untuk perhitungan waktu yang akurat
date_default_timezone_set('Asia/Jakarta');

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle update foto untuk wali kelas dan guru
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_foto' && ($role == 'wali_kelas' || $role == 'guru')) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['foto']['type'];
        $file_size = $_FILES['foto']['size'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_type, $allowed_types)) {
            $error_message = 'Format file tidak didukung! Gunakan format JPG, PNG, atau GIF.';
        } elseif ($file_size > $max_size) {
            $error_message = 'Ukuran file terlalu besar! Maksimal 5MB.';
        } else {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Ambil foto lama
            try {
                $stmt_foto = $conn->prepare("SELECT foto FROM pengguna WHERE id = ?");
                $stmt_foto->bind_param("i", $user_id);
                $stmt_foto->execute();
                $result_foto = $stmt_foto->get_result();
                $foto_data = $result_foto->fetch_assoc();
                $foto_lama = $foto_data ? $foto_data['foto'] : null;
            } catch (Exception $e) {
                $foto_lama = null;
            }
            
            // Generate nama file baru
            $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $foto = 'wali_' . $user_id . '_' . time() . '.' . $file_ext;
            
            // Upload file baru
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto)) {
                try {
                    $stmt_update = $conn->prepare("UPDATE pengguna SET foto = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $foto, $user_id);
                    
                    if ($stmt_update->execute()) {
                        // Update session
                        $_SESSION['foto'] = $foto;
                        
                        // Hapus foto lama jika ada dan bukan default
                        if ($foto_lama && $foto_lama != 'default.png' && file_exists($upload_dir . $foto_lama)) {
                            @unlink($upload_dir . $foto_lama);
                        }
                        
                        $success_message = 'Foto berhasil diperbarui!';
                        // Refresh halaman setelah 1 detik
                        echo '<script>setTimeout(function(){ window.location.reload(); }, 1000);</script>';
                    } else {
                        $error_message = 'Gagal memperbarui foto.';
                    }
                } catch (Exception $e) {
                    $error_message = 'Error: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Gagal mengupload foto.';
            }
        }
    } else {
        $error_message = 'Tidak ada file yang diupload.';
    }
}

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
    // Ambil data lengkap guru/wali kelas
    $guru_data = null;
    try {
        $stmt_guru = $conn->prepare("SELECT * FROM pengguna WHERE id = ?");
        $stmt_guru->bind_param("i", $user_id);
        $stmt_guru->execute();
        $result_guru = $stmt_guru->get_result();
        $guru_data = $result_guru ? $result_guru->fetch_assoc() : null;
    } catch (Exception $e) {
        $guru_data = null;
    }
    
    // Ambil data kelas yang diampu
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
        $kelas_id = 0;
    }
    
    // Hitung jumlah siswa
    try {
        $stmt_siswa = $conn->prepare("SELECT COUNT(*) as total FROM siswa WHERE kelas_id = ?");
        $stmt_siswa->bind_param("i", $kelas_id);
        $stmt_siswa->execute();
        $result_siswa = $stmt_siswa->get_result();
        $total_siswa = $result_siswa ? $result_siswa->fetch_assoc()['total'] : 0;
    } catch (Exception $e) {
        $total_siswa = 0;
    }
    
    // Ambil materi yang diampu dengan JOIN yang benar
    try {
        $query_materi = "SELECT mm.*, m.nama_mulok, k.nama_kelas 
                         FROM mengampu_materi mm 
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id 
                         INNER JOIN kelas k ON mm.kelas_id = k.id 
                         WHERE mm.guru_id = ?
                         ORDER BY k.nama_kelas, m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $user_id);
        $stmt_materi->execute();
        $materi_diampu = $stmt_materi->get_result();
    } catch (Exception $e) {
        $materi_diampu = null;
    }
    
    // Cek apakah user adalah wali kelas
    $is_wali_kelas = ($kelas_data !== null);
} elseif ($role == 'guru') {
    // Dashboard Guru
    // Ambil data lengkap guru
    $guru_data = null;
    try {
        $stmt_guru = $conn->prepare("SELECT * FROM pengguna WHERE id = ?");
        $stmt_guru->bind_param("i", $user_id);
        $stmt_guru->execute();
        $result_guru = $stmt_guru->get_result();
        $guru_data = $result_guru ? $result_guru->fetch_assoc() : null;
    } catch (Exception $e) {
        $guru_data = null;
    }
    
    // Ambil materi yang diampu dengan JOIN yang benar
    try {
        $query_materi = "SELECT mm.*, m.nama_mulok, k.nama_kelas 
                         FROM mengampu_materi mm 
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id 
                         INNER JOIN kelas k ON mm.kelas_id = k.id 
                         WHERE mm.guru_id = ?
                         ORDER BY k.nama_kelas, m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $user_id);
        $stmt_materi->execute();
        $materi_diampu = $stmt_materi->get_result();
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
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Info Aplikasi</h6>
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
                                    // Gunakan DateTime untuk perhitungan yang lebih akurat
                                    try {
                                        $waktu_login_str = $aktivitas['waktu_login'];
                                        
                                        // Parse waktu login dari database dengan timezone
                                        $datetime_login = new DateTime($waktu_login_str, new DateTimeZone('Asia/Jakarta'));
                                        $datetime_sekarang = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                                        
                                        // Hitung selisih (pastikan urutan benar: sekarang - login)
                                        $interval = $datetime_sekarang->diff($datetime_login);
                                        
                                        // Format waktu relatif dengan lebih akurat
                                        // Periksa apakah waktu login di masa lalu (invert = 1 berarti login di masa lalu)
                                        if ($interval->invert == 0) {
                                            // Waktu login di masa depan (tidak mungkin, tapi handle)
                                            $waktu_text = 'Baru saja';
                                        } elseif ($interval->days > 0) {
                                            if ($interval->days == 1 && $interval->h == 0) {
                                                $waktu_text = 'Kemarin';
                                            } else {
                                                $waktu_text = $interval->days . ' hari yang lalu';
                                            }
                                        } elseif ($interval->h > 0) {
                                            // Hitung total menit untuk akurasi lebih baik
                                            // Jika lebih dari setengah jam berikutnya, bulatkan ke atas
                                            if ($interval->i >= 30) {
                                                $waktu_text = ($interval->h + 1) . ' jam yang lalu';
                                            } else {
                                                $waktu_text = $interval->h . ' jam yang lalu';
                                            }
                                        } elseif ($interval->i > 0) {
                                            $waktu_text = $interval->i . ' menit yang lalu';
                                        } elseif ($interval->s > 30) {
                                            $waktu_text = '1 menit yang lalu';
                                        } else {
                                            $waktu_text = 'Baru saja';
                                        }
                                        
                                        // Untuk format tanggal lengkap, gunakan timestamp
                                        $waktu = $datetime_login->getTimestamp();
                                    } catch (Exception $e) {
                                        // Fallback ke metode lama jika DateTime gagal
                                        $waktu = strtotime($aktivitas['waktu_login']);
                                        $selisih = time() - $waktu;
                                        $menit = floor($selisih / 60);
                                        $jam = floor($selisih / 3600);
                                        $hari = floor($selisih / 86400);
                                        
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
            
            <div class="row mb-4">
                <!-- Box Foto Wali/Guru -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="position-relative d-inline-block mb-3">
                                <img src="uploads/<?php echo htmlspecialchars($guru_data['foto'] ?? 'default.png'); ?>" 
                                     alt="Foto" class="rounded-circle" width="200" height="200" 
                                     style="object-fit: cover; border: 4px solid #2d5016;" 
                                     id="previewFoto"
                                     onerror="this.onerror=null; this.src='uploads/default.png';">
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                        style="width: 40px; height: 40px; border: 2px solid white;" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditFoto">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($guru_data['nama'] ?? $_SESSION['nama']); ?></h4>
                            <p class="text-muted mb-2"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></p>
                            <?php if ($is_wali_kelas): ?>
                                <span class="badge bg-success fs-6">Wali Kelas Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary fs-6">Bukan Wali Kelas</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Box Identitas Guru -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-user"></i> Identitas Guru</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td width="40%"><strong>Nama:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['nama'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>NIP:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['nip'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>NUPTK:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['nuptk'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>TTL:</strong></td>
                                    <td><?php 
                                        $ttl = '';
                                        if (!empty($guru_data['tempat_lahir'])) {
                                            $ttl .= htmlspecialchars($guru_data['tempat_lahir']);
                                        }
                                        if (!empty($guru_data['tanggal_lahir'])) {
                                            $ttl .= ($ttl ? ', ' : '') . date('d F Y', strtotime($guru_data['tanggal_lahir']));
                                        }
                                        echo $ttl ?: '-';
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Pendidikan:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['pendidikan_terakhir'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['email'] ?? '-'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Box Tentang Kelas -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-school"></i> Tentang Kelas</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($kelas_data): ?>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td width="40%"><strong>Kelas:</strong></td>
                                        <td><?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Jumlah Siswa:</strong></td>
                                        <td><span class="badge bg-primary fs-6"><?php echo $total_siswa; ?> siswa</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Wali Kelas:</strong></td>
                                        <td><?php echo htmlspecialchars($guru_data['nama'] ?? '-'); ?></td>
                                    </tr>
                                </table>
                            <?php else: ?>
                                <p class="text-muted mb-0">Belum ditugaskan sebagai wali kelas.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Box Tabel Materi yang Diampu -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-book"></i> Materi yang Diampu</h6>
                </div>
                <div class="card-body">
                    <?php if ($materi_diampu && $materi_diampu->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Materi Mulok</th>
                                        <th>Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    $materi_diampu->data_seek(0);
                                    while ($materi = $materi_diampu->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_mulok'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_kelas'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-inbox"></i> Belum ada materi yang diampu.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Modal Edit Foto -->
            <div class="modal fade" id="modalEditFoto" tabindex="-1" aria-labelledby="modalEditFotoLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" enctype="multipart/form-data" id="formEditFoto">
                            <input type="hidden" name="action" value="update_foto">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalEditFotoLabel">Edit Foto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Foto Baru</label>
                                    <input type="file" class="form-control" name="foto" accept="image/*" id="inputFoto" required>
                                    <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 5MB</small>
                                </div>
                                <div class="text-center">
                                    <img src="uploads/<?php echo htmlspecialchars($guru_data['foto'] ?? 'default.png'); ?>" 
                                         alt="Preview" id="previewFotoModal" 
                                         class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;"
                                         onerror="this.src='uploads/default.png';">
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
                // Preview foto sebelum upload
                document.getElementById('inputFoto').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('previewFotoModal').src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            </script>
            
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
                    $default_info = 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                $info_aplikasi_wk = 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                            echo 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
        <?php elseif ($role == 'guru'): ?>
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
            
            <div class="row mb-4">
                <!-- Box Foto Guru -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="position-relative d-inline-block mb-3">
                                <img src="uploads/<?php echo htmlspecialchars($guru_data['foto'] ?? 'default.png'); ?>" 
                                     alt="Foto" class="rounded-circle" width="200" height="200" 
                                     style="object-fit: cover; border: 4px solid #2d5016;" 
                                     id="previewFotoGuru"
                                     onerror="this.onerror=null; this.src='uploads/default.png';">
                                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                        style="width: 40px; height: 40px; border: 2px solid white;" 
                                        data-bs-toggle="modal" data-bs-target="#modalEditFotoGuru">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($guru_data['nama'] ?? $_SESSION['nama']); ?></h4>
                            <p class="text-muted mb-2"><?php echo ucfirst(str_replace('_', ' ', $role)); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Box Identitas Guru -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-user"></i> Identitas Guru</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td width="40%"><strong>Nama:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['nama'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>NIP:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['nip'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>NUPTK:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['nuptk'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>TTL:</strong></td>
                                    <td><?php 
                                        $ttl = '';
                                        if (!empty($guru_data['tempat_lahir'])) {
                                            $ttl .= htmlspecialchars($guru_data['tempat_lahir']);
                                        }
                                        if (!empty($guru_data['tanggal_lahir'])) {
                                            $ttl .= ($ttl ? ', ' : '') . date('d F Y', strtotime($guru_data['tanggal_lahir']));
                                        }
                                        echo $ttl ?: '-';
                                    ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Pendidikan:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['pendidikan_terakhir'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($guru_data['email'] ?? '-'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Box Tabel Materi yang Diampu -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-book"></i> Materi yang Diampu</h6>
                </div>
                <div class="card-body">
                    <?php if ($materi_diampu && $materi_diampu->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">No</th>
                                        <th>Materi Mulok</th>
                                        <th>Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    $materi_diampu->data_seek(0);
                                    while ($materi = $materi_diampu->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_mulok'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($materi['nama_kelas'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-inbox"></i> Belum ada materi yang diampu.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Modal Edit Foto -->
            <div class="modal fade" id="modalEditFotoGuru" tabindex="-1" aria-labelledby="modalEditFotoGuruLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" enctype="multipart/form-data" id="formEditFotoGuru">
                            <input type="hidden" name="action" value="update_foto">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalEditFotoGuruLabel">Edit Foto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Foto Baru</label>
                                    <input type="file" class="form-control" name="foto" accept="image/*" id="inputFotoGuru" required>
                                    <small class="text-muted">Format: JPG, PNG, GIF. Maksimal 5MB</small>
                                </div>
                                <div class="text-center">
                                    <img src="uploads/<?php echo htmlspecialchars($guru_data['foto'] ?? 'default.png'); ?>" 
                                         alt="Preview" id="previewFotoModalGuru" 
                                         class="img-thumbnail" style="max-width: 200px; max-height: 200px; object-fit: cover;"
                                         onerror="this.src='uploads/default.png';">
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
                // Preview foto sebelum upload untuk guru
                document.getElementById('inputFotoGuru').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('previewFotoModalGuru').src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            </script>
            
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
                    $default_info = 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                $info_aplikasi_guru = 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
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
                            echo 'Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.';
                        }
                        ?>
                    </div>
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
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(45, 80, 22, 0.3);
    z-index: 1;
}

.timeline-content {
    background: #f8f9fa;
    border-left: 3px solid #2d5016;
    padding: 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
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
</style>

<?php include 'includes/footer.php'; ?>

