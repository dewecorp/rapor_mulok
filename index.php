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
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

