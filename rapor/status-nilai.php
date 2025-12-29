<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();

// Filter kelas
$kelas_filter = $_GET['kelas'] ?? '';

// Cek apakah kolom kelas_id sudah ada di tabel materi_mulok
$has_kelas_id = false;
try {
    $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
    $has_kelas_id = ($check_kelas_id && $check_kelas_id->num_rows > 0);
} catch (Exception $e) {
    $has_kelas_id = false;
}

// Ambil data kelas (exclude kelas Alumni)
$kelas_list = null;
$semester = '1';
$tahun_ajaran = '';
try {
    $query_kelas = "SELECT k.*, p.nama as wali_kelas_nama 
                    FROM kelas k 
                    LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
                    WHERE k.nama_kelas NOT LIKE '%Alumni%' AND k.nama_kelas NOT LIKE '%Lulus%' 
                    ORDER BY k.nama_kelas";
    $kelas_list = $conn->query($query_kelas);
    if (!$kelas_list) {
        $kelas_list = null;
    }
} catch (Exception $e) {
    $kelas_list = null;
}

// Ambil profil untuk semester dan tahun ajaran
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

// Inisialisasi variabel progress bar keseluruhan
$total_semua_siswa = 0;
$total_semua_sudah_nilai = 0;
$persentase_keseluruhan = 0;

// Query status nilai per kelas yang dipilih
$status_data = [];

if ($kelas_filter) {
    // Ambil data kelas yang dipilih termasuk wali kelas
    $stmt_kelas = $conn->prepare("SELECT k.*, p.nama as wali_kelas_nama 
                                  FROM kelas k 
                                  LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
                                  WHERE k.id = ?");
    $stmt_kelas->bind_param("i", $kelas_filter);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas = $result_kelas ? $result_kelas->fetch_assoc() : null;
    
    if ($kelas) {
        $kelas_id = $kelas['id'];
        
        // Ambil siswa di kelas
        $stmt_siswa = $conn->prepare("SELECT id FROM siswa WHERE kelas_id = ?");
        $stmt_siswa->bind_param("i", $kelas_id);
        $stmt_siswa->execute();
        $siswa_list = $stmt_siswa->get_result();
        $siswa_ids = [];
        if ($siswa_list) {
            while ($s = $siswa_list->fetch_assoc()) {
                $siswa_ids[] = $s['id'];
            }
        }
        
        $total_siswa = count($siswa_ids);
        
        // Ambil semua materi yang diampu di kelas ini
        if ($has_kelas_id) {
            $query_materi = "SELECT DISTINCT mm.materi_mulok_id
                            FROM mengampu_materi mm
                            WHERE mm.kelas_id = ?";
            $stmt_materi = $conn->prepare($query_materi);
            $stmt_materi->bind_param("i", $kelas_id);
        } else {
            // Fallback jika kelas_id belum ada
            $query_materi = "SELECT DISTINCT mm.materi_mulok_id
                            FROM mengampu_materi mm";
            $stmt_materi = $conn->prepare($query_materi);
        }
        $stmt_materi->execute();
        $materi_result = $stmt_materi->get_result();
        $materi_ids = [];
        if ($materi_result) {
            while ($m = $materi_result->fetch_assoc()) {
                $materi_ids[] = $m['materi_mulok_id'];
            }
        }
        
        // Hitung total target = jumlah siswa x jumlah materi
        $total_target = $total_siswa * count($materi_ids);
        $sudah_nilai = 0;
        
        // Hitung total nilai yang sudah disetorkan untuk kelas ini
        if ($total_target > 0 && count($siswa_ids) > 0 && count($materi_ids) > 0) {
            $placeholders_siswa = str_repeat('?,', count($siswa_ids) - 1) . '?';
            $placeholders_materi = str_repeat('?,', count($materi_ids) - 1) . '?';
            
            $query_nilai = "SELECT COUNT(*) as total 
                           FROM nilai_siswa 
                           WHERE siswa_id IN ($placeholders_siswa) 
                           AND materi_mulok_id IN ($placeholders_materi)
                           AND semester = ? 
                           AND tahun_ajaran = ?";
            $stmt_nilai = $conn->prepare($query_nilai);
            $params = array_merge($siswa_ids, $materi_ids, [$semester, $tahun_ajaran]);
            $types = str_repeat('i', count($siswa_ids) + count($materi_ids)) . 'ss';
            $stmt_nilai->bind_param($types, ...$params);
            $stmt_nilai->execute();
            $result_nilai = $stmt_nilai->get_result();
            if ($result_nilai) {
                $row = $result_nilai->fetch_assoc();
                $sudah_nilai = $row['total'] ?? 0;
            }
        }
        
        // Hitung persentase
        $persentase = $total_target > 0 ? round(($sudah_nilai / $total_target) * 100, 2) : 0;
        
        // Akumulasi untuk progress bar keseluruhan
        $total_semua_siswa += $total_target;
        $total_semua_sudah_nilai += $sudah_nilai;
        
        $status_data[] = [
            'kelas_id' => $kelas_id,
            'kelas_nama' => $kelas['nama_kelas'],
            'wali_kelas_nama' => $kelas['wali_kelas_nama'] ?? 'Belum ditentukan',
            'persentase' => $persentase
        ];
        
        // Hitung persentase keseluruhan
        $persentase_keseluruhan = $total_semua_siswa > 0 ? round(($total_semua_sudah_nilai / $total_semua_siswa) * 100, 2) : 0;
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> Status Nilai</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">Filter Kelas</label>
            <select class="form-select" id="filterKelas" onchange="filterKelas()" style="max-width: 300px;">
                <option value="">-- Pilih Kelas --</option>
                <?php if ($kelas_list): 
                    $kelas_list->data_seek(0);
                    while ($kelas_option = $kelas_list->fetch_assoc()): 
                        // Skip kelas Alumni (double check untuk keamanan)
                        if (stripos($kelas_option['nama_kelas'], 'Alumni') !== false || stripos($kelas_option['nama_kelas'], 'Lulus') !== false) {
                            continue;
                        }
                ?>
                    <option value="<?php echo $kelas_option['id']; ?>" <?php echo $kelas_filter == $kelas_option['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kelas_option['nama_kelas']); ?>
                    </option>
                <?php 
                    endwhile;
                endif; ?>
            </select>
        </div>
        
        <?php if ($kelas_filter && isset($status_data) && count($status_data) > 0): ?>
            <!-- Progress Bar Keseluruhan -->
            <div class="card mb-3 border-primary">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-chart-line"></i> Progres Nilai
                    </h6>
                    <hr class="my-3">
                    <div class="mb-1">
                        <small class="text-dark fw-bold">Materi Mulok</small>
                    </div>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar <?php echo $persentase_keseluruhan == 100 ? 'bg-success' : ($persentase_keseluruhan >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                             role="progressbar" 
                             style="width: <?php echo $persentase_keseluruhan; ?>%; font-size: 14px; font-weight: bold; display: flex; align-items: center; justify-content: center;" 
                             aria-valuenow="<?php echo $persentase_keseluruhan; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo $persentase_keseluruhan; ?>%
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Wali Kelas</th>
                            <th>Nilai Terkirim</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status_data as $status): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($status['kelas_nama']); ?></td>
                                <td><?php echo htmlspecialchars($status['wali_kelas_nama']); ?></td>
                                <td>
                                    <?php if ($status['persentase'] == 100): ?>
                                        <span class="badge bg-success">100%</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Belum Terkirim</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$kelas_filter): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Pilih kelas terlebih dahulu untuk melihat progres nilai.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Belum ada data untuk kelas yang dipilih.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    function filterKelas() {
        var kelasId = $('#filterKelas').val();
        if (kelasId) {
            window.location.href = 'status-nilai.php?kelas=' + kelasId;
        } else {
            window.location.href = 'status-nilai.php';
        }
    }
</script>

