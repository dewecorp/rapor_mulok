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
$materi_list = [];
$total_materi = 0;
$materi_terkirim = 0;
$persentase_progress = 0;

if ($kelas_id && !empty($semester) && !empty($tahun_ajaran)) {
    try {
        // Cek struktur tabel materi_mulok apakah ada kolom kelas_id dan semester
        $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
        $has_kelas_id = $check_kelas_id && $check_kelas_id->num_rows > 0;
        
        $check_semester = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'semester'");
        $has_semester = $check_semester && $check_semester->num_rows > 0;
        
        // Ambil semua materi dari mengampu_materi untuk kelas ini
        // Tanpa filter semester dulu untuk memastikan semua materi terambil
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru, m.semester
                         FROM mengampu_materi mm
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE mm.kelas_id = ?
                         ORDER BY m.nama_mulok";
        
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $kelas_id);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        
        if ($result_materi) {
            while ($materi = $result_materi->fetch_assoc()) {
                // Filter semester di PHP jika ada kolom semester
                if ($has_semester) {
                    $materi_semester = isset($materi['semester']) ? $materi['semester'] : '';
                    if ($materi_semester != $semester) {
                        continue; // Skip jika semester tidak sesuai
                    }
                }
                
                $total_materi++;
                $materi_id = $materi['materi_id'];
                
                // Cek apakah ada nilai yang sudah dikirim untuk materi ini (sama seperti di dashboard proktor)
                $query_cek_nilai = "SELECT status FROM nilai_kirim_status 
                                   WHERE materi_mulok_id = ? 
                                   AND kelas_id = ? 
                                   AND semester = ? 
                                   AND tahun_ajaran = ? 
                                   AND status = 'terkirim'";
                $stmt_cek = $conn->prepare($query_cek_nilai);
                $stmt_cek->bind_param("iiss", $materi_id, $kelas_id, $semester, $tahun_ajaran);
                $stmt_cek->execute();
                $result_cek = $stmt_cek->get_result();
                $ada_nilai = ($result_cek && $result_cek->num_rows > 0);
                $stmt_cek->close();
                
                if ($ada_nilai) {
                    $materi_terkirim++;
                }
                
                // Pastikan nama_guru tidak null
                $nama_guru = isset($materi['nama_guru']) && !empty(trim($materi['nama_guru'])) ? trim($materi['nama_guru']) : '-';
                
                // Jika nama_guru masih kosong tapi ada guru_id, ambil dari database
                if ($nama_guru == '-' && isset($materi['guru_id']) && !empty($materi['guru_id'])) {
                    $guru_id_check = intval($materi['guru_id']);
                    if ($guru_id_check > 0) {
                        $stmt_guru = $conn->prepare("SELECT nama FROM pengguna WHERE id = ?");
                        $stmt_guru->bind_param("i", $guru_id_check);
                        $stmt_guru->execute();
                        $result_guru = $stmt_guru->get_result();
                        if ($result_guru && $result_guru->num_rows > 0) {
                            $guru_data = $result_guru->fetch_assoc();
                            $nama_guru = isset($guru_data['nama']) && !empty(trim($guru_data['nama'])) ? trim($guru_data['nama']) : '-';
                        }
                        $stmt_guru->close();
                    }
                }
                
                $materi_list[] = [
                    'materi_id' => $materi_id,
                    'nama_mulok' => isset($materi['nama_mulok']) ? $materi['nama_mulok'] : '-',
                    'guru_id' => isset($materi['guru_id']) ? intval($materi['guru_id']) : 0,
                    'nama_guru' => $nama_guru,
                    'status' => $ada_nilai ? 'terkirim' : 'belum'
                ];
            }
        }
        $stmt_materi->close();
        
        // Hitung persentase progress
        $persentase_progress = $total_materi > 0 ? round(($materi_terkirim / $total_materi) * 100, 2) : 0;
        
    } catch (Exception $e) {
        $materi_list = [];
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> Status Nilai - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($kelas_id && !empty($materi_list)): ?>
            <!-- Progress Bar Materi Mulok -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Materi Mulok</h6>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar" 
                             role="progressbar" 
                             style="width: <?php echo $persentase_progress; ?>%; background-color: #2d5016;" 
                             aria-valuenow="<?php echo $persentase_progress; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo $persentase_progress; ?>% (<?php echo $materi_terkirim; ?> dari <?php echo $total_materi; ?>)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabel Status Nilai -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableStatusNilai">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Materi</th>
                            <th>Guru Pengampu</th>
                            <th>Status Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($materi_list as $materi): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($materi['nama_mulok'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($materi['nama_guru'] ?? '-'); ?></td>
                                <td>
                                    <?php if (isset($materi['status']) && $materi['status'] == 'terkirim'): ?>
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

<script>
    $(document).ready(function() {
        <?php if ($kelas_id && !empty($materi_list)): ?>
        if ($('#tableStatusNilai').length > 0) {
            $('#tableStatusNilai').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[1, 'asc']], // Sort by Nama Materi ascending
                pageLength: 10,
                searching: true,
                paging: true,
                info: true
            });
        }
        <?php endif; ?>
    });
</script>

