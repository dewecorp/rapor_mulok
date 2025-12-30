<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('guru');

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$materi_id = $_GET['materi_id'] ?? 0;
$kelas_nama_filter = $_GET['kelas_nama'] ?? '';

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
$label_kategori = $use_kategori ? 'Kategori Mulok' : 'Kode Mulok';

// Cek apakah kolom kelas_id sudah ada
$has_kelas_id = false;
try {
    $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
    $has_kelas_id = ($check_kelas_id && $check_kelas_id->num_rows > 0);
} catch (Exception $e) {
    $has_kelas_id = false;
}

// Ambil data guru
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

// Ambil data materi yang dipilih
$materi_data = null;
$siswa_list = null;
if ($materi_id > 0) {
    try {
        // Jika ada kelas_nama filter, gunakan untuk memastikan kelas yang tepat
        if (!empty($kelas_nama_filter)) {
            $stmt_materi = $conn->prepare("SELECT m.*, mm.kelas_id, k.nama_kelas 
                                           FROM materi_mulok m
                                           INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                                           INNER JOIN kelas k ON mm.kelas_id = k.id
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
                                           INNER JOIN kelas k ON mm.kelas_id = k.id
                                           WHERE m.id = ? AND mm.guru_id = ?
                                           ORDER BY k.nama_kelas
                                           LIMIT 1");
            $stmt_materi->bind_param("ii", $materi_id, $user_id);
            $stmt_materi->execute();
            $result_materi = $stmt_materi->get_result();
            $materi_data = $result_materi ? $result_materi->fetch_assoc() : null;
        }
        
        // Ambil data siswa di kelas
        if ($materi_data && $materi_data['kelas_id']) {
            $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE kelas_id = ? ORDER BY nama");
            $stmt_siswa->bind_param("i", $materi_data['kelas_id']);
            $stmt_siswa->execute();
            $siswa_list = $stmt_siswa->get_result();
        }
    } catch (Exception $e) {
        $materi_data = null;
        $siswa_list = null;
    }
}

// Ambil profil untuk tahun ajaran dan semester
$profil = null;
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
}

// Fungsi untuk mendapatkan warna badge berdasarkan kategori (case-insensitive)
function getBadgeColor($kategori) {
    if (empty($kategori)) {
        return 'bg-secondary';
    }
    
    // Normalisasi: trim dan lowercase untuk case-insensitive
    $kategori_normalized = strtolower(trim($kategori));
    
    // Mapping warna badge untuk 3 kategori khusus (case-insensitive)
    if ($kategori_normalized === 'hafalan') {
        return 'bg-info';
    } elseif ($kategori_normalized === 'membaca') {
        return 'bg-primary';
    } elseif ($kategori_normalized === 'praktik ibadah' || $kategori_normalized === 'praktikibadah') {
        return 'bg-warning';
    }
    
    // Default untuk kategori lain (jika ada)
    return 'bg-secondary';
}

// Ambil materi yang diampu oleh guru ini
$result = null;
try {
    if ($has_kelas_id) {
        $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas, k_materi.nama_kelas as nama_kelas_materi
                  FROM mengampu_materi mm
                  INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                  INNER JOIN kelas k ON mm.kelas_id = k.id
                  LEFT JOIN kelas k_materi ON m.kelas_id = k_materi.id
                  WHERE mm.guru_id = ?
                  ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC");
    } else {
        $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas, NULL as nama_kelas_materi
                  FROM mengampu_materi mm
                  INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                  INNER JOIN kelas k ON mm.kelas_id = k.id
                  WHERE mm.guru_id = ?
                  ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $result = null;
    }
} catch (Exception $e) {
    $result = null;
}
?>
<?php include '../includes/header.php'; ?>

<?php if ($materi_id > 0 && $materi_data): ?>
    <!-- Box Guru -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-chalkboard-teacher fa-5x text-primary"></i>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($materi_data['nama_kelas'] ?? '-'); ?></h5>
                    <p class="text-muted mb-2">Guru</p>
                    <h6 class="mb-0"><?php echo htmlspecialchars($guru_data['nama'] ?? '-'); ?></h6>
                </div>
            </div>
        </div>
        
        <!-- Box Rincian Kelas -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Rincian Kelas</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td width="40%"><strong>Materi Mulok:</strong></td>
                            <td><?php echo htmlspecialchars($materi_data['nama_mulok'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Jumlah Siswa:</strong></td>
                            <td><?php echo $siswa_list ? $siswa_list->num_rows : 0; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tahun Ajaran:</strong></td>
                            <td><?php echo htmlspecialchars($profil['tahun_ajaran_aktif'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Semester:</strong></td>
                            <td><?php 
                                $semester = $profil['semester_aktif'] ?? '1';
                                echo $semester == '1' ? 'Ganjil' : 'Genap';
                            ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Box Kosong untuk spacing -->
        <div class="col-md-4"></div>
    </div>
    
    <!-- Box Data Siswa -->
    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-white"><i class="fas fa-users"></i> Siswa Kelas <?php echo htmlspecialchars($materi_data['nama_kelas'] ?? ''); ?></h6>
            <a href="penilaian.php?materi_id=<?php echo $materi_id; ?>&kelas_nama=<?php echo urlencode($materi_data['nama_kelas'] ?? ''); ?>" class="btn btn-dark btn-sm" style="background-color: #155724; border-color: #155724;">
                <i class="fas fa-clipboard-check"></i> Penilaian
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableSiswa">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>NISN</th>
                            <th>Nama</th>
                            <th>L/P</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($siswa_list && $siswa_list->num_rows > 0): ?>
                            <?php 
                            $no = 1;
                            $siswa_list->data_seek(0);
                            while ($siswa = $siswa_list->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                    <td><?php echo ($siswa['jenis_kelamin'] ?? '') == 'L' ? 'L' : 'P'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Tampilkan daftar semua materi jika tidak ada materi_id yang dipilih -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-book"></i> Materi Mulok yang Diampu</h5>
        </div>
        <div class="card-body">
            <?php 
            // Ambil semua materi yang diampu
            $result = null;
            try {
                if ($has_kelas_id) {
                    $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas, k_materi.nama_kelas as nama_kelas_materi
                              FROM mengampu_materi mm
                              INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                              INNER JOIN kelas k ON mm.kelas_id = k.id
                              LEFT JOIN kelas k_materi ON m.kelas_id = k_materi.id
                              WHERE mm.guru_id = ?
                              ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC");
                } else {
                    $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas, NULL as nama_kelas_materi
                              FROM mengampu_materi mm
                              INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                              INNER JOIN kelas k ON mm.kelas_id = k.id
                              WHERE mm.guru_id = ?
                              ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC");
                }
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
            } catch (Exception $e) {
                $result = null;
            }
            
            if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableMateri">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th><?php echo $label_kategori; ?></th>
                                <th>Nama Mulok</th>
                                <th>Kelas Materi</th>
                                <th>Kelas Mengampu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td>
                                        <?php 
                                        $kategori_value = $row[$kolom_kategori] ?? '';
                                        if (!empty($kategori_value)): 
                                            $badge_color = getBadgeColor($kategori_value);
                                        ?>
                                            <span class="badge <?php echo $badge_color; ?>"><?php echo htmlspecialchars($kategori_value); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?materi_id=<?php echo $row['id']; ?>&kelas_nama=<?php echo urlencode($row['nama_kelas']); ?>">
                                            <?php echo htmlspecialchars($row['nama_mulok']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['nama_kelas_materi'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                                </tr>
                            <?php endwhile; ?>
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

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if ($materi_id > 0 && $materi_data): ?>
            <?php if ($siswa_list && $siswa_list->num_rows > 0): ?>
                $('#tableSiswa').DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                    },
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'print', 'excel'
                    ],
                    paging: false,
                    searching: false,
                    info: false
                });
            <?php endif; ?>
        <?php else: ?>
            $('#tableMateri').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                }
            });
        <?php endif; ?>
    });
</script>

