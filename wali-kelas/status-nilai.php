<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil kelas yang diampu oleh wali kelas ini
$kelas_data = null;
$kelas_id = 0;
$semester = '1';
$tahun_ajaran = '';
$status_data = [];

try {
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    // Ambil profil untuk semester dan tahun ajaran
    $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $semester = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
    $semester = '1';
    $tahun_ajaran = '';
}

// Query status nilai per materi
if ($kelas_id) {
    try {
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
        
        // Ambil materi yang diampu di kelas ini
        $stmt_materi = $conn->prepare("SELECT DISTINCT mm.materi_mulok_id, m.nama_mulok 
                         FROM mengampu_materi mm
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                         WHERE mm.kelas_id = ?");
        $stmt_materi->bind_param("i", $kelas_id);
        $stmt_materi->execute();
        $materi_list = $stmt_materi->get_result();
        
        // Hitung progres
        $status_data = [];
        if ($materi_list) {
            while ($materi = $materi_list->fetch_assoc()) {
                $materi_id = $materi['materi_mulok_id'];
                $total_siswa = count($siswa_ids);
                $sudah_nilai = 0;
                
                if ($total_siswa > 0) {
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
                        $sudah_nilai = $result_nilai->fetch_assoc()['total'];
                    }
                }
                
                $persentase = $total_siswa > 0 ? round(($sudah_nilai / $total_siswa) * 100, 2) : 0;
                
                $status_data[] = [
                    'materi' => $materi['nama_mulok'],
                    'total_siswa' => $total_siswa,
                    'sudah_nilai' => $sudah_nilai,
                    'belum_nilai' => $total_siswa - $sudah_nilai,
                    'persentase' => $persentase
                ];
            }
        }
    } catch (Exception $e) {
        $status_data = [];
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> Status Nilai - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($kelas_id && isset($status_data)): ?>
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
        <?php elseif (!$kelas_id): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Anda belum ditugaskan sebagai wali kelas.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada data untuk kelas ini.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

