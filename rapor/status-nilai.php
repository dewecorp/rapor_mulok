<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();

// Filter kelas
$kelas_filter = $_GET['kelas'] ?? '';

// Ambil data kelas
$kelas_list = null;
$semester = '1';
$tahun_ajaran = '';
try {
    $query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
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

// Query status nilai per kelas dan materi
if ($kelas_filter) {
    // Ambil siswa di kelas
    $stmt_siswa = $conn->prepare("SELECT id FROM siswa WHERE kelas_id = ?");
    $stmt_siswa->bind_param("i", $kelas_filter);
    $stmt_siswa->execute();
    $siswa_list = $stmt_siswa->get_result();
    $siswa_ids = [];
    if ($siswa_list) {
        while ($s = $siswa_list->fetch_assoc()) {
            $siswa_ids[] = $s['id'];
        }
    }
    
    // Ambil semua materi mulok (tampilkan semua materi walaupun belum ada di mengampu_materi untuk kelas ini)
    $stmt_materi = $conn->prepare("SELECT m.id as materi_mulok_id, m.nama_mulok,
                     CASE WHEN mm.id IS NOT NULL THEN 1 ELSE 0 END as sudah_diampu
                     FROM materi_mulok m
                     LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                     ORDER BY m.nama_mulok");
    $stmt_materi->bind_param("i", $kelas_filter);
    $stmt_materi->execute();
    $materi_list = $stmt_materi->get_result();
    
    // Hitung progres
    $status_data = [];
    if ($materi_list) {
        while ($materi = $materi_list->fetch_assoc()) {
            $materi_id = $materi['materi_mulok_id'];
            $total_siswa = count($siswa_ids);
            $sudah_nilai = 0;
            
            // Hitung siswa yang sudah dinilai untuk materi ini
            if ($total_siswa > 0 && count($siswa_ids) > 0) {
                $placeholders = str_repeat('?,', count($siswa_ids) - 1) . '?';
                $query_nilai = "SELECT COUNT(DISTINCT siswa_id) as total 
                               FROM nilai_siswa 
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
                    $row = $result_nilai->fetch_assoc();
                    $sudah_nilai = $row['total'] ?? 0;
                }
            }
            
            // Hitung persentase (jika tidak ada siswa, persentase = 0)
            $persentase = $total_siswa > 0 ? round(($sudah_nilai / $total_siswa) * 100, 2) : 0;
            
            $status_data[] = [
                'materi' => $materi['nama_mulok'],
                'total_siswa' => $total_siswa,
                'sudah_nilai' => $sudah_nilai,
                'belum_nilai' => $total_siswa - $sudah_nilai,
                'persentase' => $persentase,
                'sudah_diampu' => $materi['sudah_diampu']
            ];
        }
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
                    while ($kelas = $kelas_list->fetch_assoc()): 
                        // Skip kelas Alumni dari filter
                        if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                            continue;
                        }
                ?>
                    <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_filter == $kelas['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    </option>
                <?php 
                    endwhile;
                endif; ?>
            </select>
        </div>
        
        <?php if ($kelas_filter && isset($status_data)): ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Materi Mulok</th>
                            <th>Total Siswa</th>
                            <th>Sudah Dinilai</th>
                            <th>Belum Dinilai</th>
                            <th>Progres</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($status_data as $status): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($status['materi']); ?></td>
                                <td><?php echo $status['total_siswa']; ?></td>
                                <td><span class="badge bg-success"><?php echo $status['sudah_nilai']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $status['belum_nilai']; ?></span></td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar <?php echo $status['persentase'] == 100 ? 'bg-success' : ($status['persentase'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $status['persentase']; ?>%;" 
                                             aria-valuenow="<?php echo $status['persentase']; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo $status['persentase']; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$kelas_filter): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Pilih kelas terlebih dahulu untuk melihat status nilai.
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

