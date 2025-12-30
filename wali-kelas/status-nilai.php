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

// Query status nilai per materi dan guru
$total_target = 0;
$total_sudah_nilai = 0;
$persentase_keseluruhan = 0;

if ($kelas_id > 0) {
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
        $total_siswa = count($siswa_ids);
        
        // Ambil materi yang diampu di kelas ini beserta guru yang mengampu
        // PASTIKAN hanya mengambil materi yang diampu di kelas wali kelas ini saja (kelas_id = kelas wali kelas)
        $query_materi = "SELECT mm.materi_mulok_id, mm.guru_id, m.nama_mulok, p.nama as nama_guru
                         FROM mengampu_materi mm
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE mm.kelas_id = ?
                         ORDER BY m.nama_mulok ASC";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $kelas_id);
        $stmt_materi->execute();
        $materi_result = $stmt_materi->get_result();
        
        $status_data = [];
        if ($materi_result && $materi_result->num_rows > 0) {
            while ($materi = $materi_result->fetch_assoc()) {
                $materi_id = $materi['materi_mulok_id'];
                $guru_id = $materi['guru_id'];
                
                // Hitung apakah guru sudah mengirim nilai untuk semua siswa
                $sudah_nilai = 0;
                $sudah_terkirim = false;
                
                if ($total_siswa > 0 && count($siswa_ids) > 0 && $materi_id > 0 && $guru_id > 0) {
                    $placeholders = str_repeat('?,', count($siswa_ids) - 1) . '?';
                    $query_nilai = "SELECT COUNT(DISTINCT siswa_id) as total 
                                   FROM nilai_siswa 
                                   WHERE siswa_id IN ($placeholders) 
                                   AND materi_mulok_id = ? 
                                   AND guru_id = ?
                                   AND semester = ? 
                                   AND tahun_ajaran = ?";
                    $stmt_nilai = $conn->prepare($query_nilai);
                    // Gabungkan parameter: siswa_ids (integer) + materi_id (integer) + guru_id (integer) + semester (string) + tahun_ajaran (string)
                    // Pastikan semua parameter terdefinisi
                    $params = array_merge($siswa_ids, [(int)$materi_id, (int)$guru_id, $semester, $tahun_ajaran]);
                    // Types: i untuk setiap siswa_id, i untuk materi_id, i untuk guru_id, s untuk semester, s untuk tahun_ajaran
                    // Total: count($siswa_ids) + 4 parameter lainnya
                    $types = str_repeat('i', count($siswa_ids)) . 'iiss';
                    $stmt_nilai->bind_param($types, ...$params);
                    $stmt_nilai->execute();
                    $result_nilai = $stmt_nilai->get_result();
                    if ($result_nilai) {
                        $row_nilai = $result_nilai->fetch_assoc();
                        $sudah_nilai = $row_nilai['total'] ?? 0;
                    }
                    
                    // Cek status kirim nilai dari tabel status_kirim_nilai
                    $sudah_terkirim = false;
                    try {
                        $stmt_status = $conn->prepare("SELECT status FROM status_kirim_nilai 
                                                      WHERE materi_mulok_id = ? 
                                                      AND kelas_id = ? 
                                                      AND guru_id = ? 
                                                      AND semester = ? 
                                                      AND tahun_ajaran = ?");
                        $stmt_status->bind_param("iiiss", $materi_id, $kelas_id, $guru_id, $semester, $tahun_ajaran);
                        $stmt_status->execute();
                        $result_status = $stmt_status->get_result();
                        if ($result_status && $result_status->num_rows > 0) {
                            $status_row = $result_status->fetch_assoc();
                            $sudah_terkirim = intval($status_row['status']) == 1;
                        }
                    } catch (Exception $e) {
                        // Fallback: sudah terkirim jika semua siswa sudah dinilai
                        $sudah_terkirim = ($sudah_nilai == $total_siswa && $total_siswa > 0);
                    }
                }
                
                // Akumulasi untuk progress bar keseluruhan
                $total_target += $total_siswa;
                if ($sudah_terkirim) {
                    $total_sudah_nilai += $total_siswa;
                }
                
                $status_data[] = [
                    'materi' => $materi['nama_mulok'],
                    'guru' => $materi['nama_guru'] ?? 'Belum ditentukan',
                    'guru_id' => $guru_id,
                    'materi_id' => $materi_id,
                    'total_siswa' => $total_siswa,
                    'sudah_nilai' => $sudah_nilai,
                    'sudah_terkirim' => $sudah_terkirim
                ];
            }
        }
        
        // Hitung persentase keseluruhan
        $persentase_keseluruhan = $total_target > 0 ? round(($total_sudah_nilai / $total_target) * 100, 2) : 0;
        
    } catch (Exception $e) {
        $status_data = [];
        error_log("Error in wali-kelas/status-nilai.php: " . $e->getMessage());
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> Status Nilai - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($kelas_id > 0 && !empty($status_data)): ?>
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
            
            <!-- Tabel Status Nilai -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Materi</th>
                            <th>Guru</th>
                            <th>Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($status_data as $status): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($status['materi']); ?></td>
                                <td><?php echo htmlspecialchars($status['guru']); ?></td>
                                <td>
                                    <?php if ($status['sudah_terkirim']): ?>
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
        <?php elseif ($kelas_id <= 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Anda belum ditugaskan sebagai wali kelas.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada materi yang diampu di kelas ini.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
