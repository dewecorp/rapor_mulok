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

// Cek struktur database materi_mulok
$has_kelas_id = false;
$has_semester = false;
try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kelas_id') $has_kelas_id = true;
            if ($col['Field'] == 'semester') $has_semester = true;
        }
    }
} catch (Exception $e) {
    // Ignore error
}

// Query status nilai per materi (sama persis dengan dashboard proktor)
// Gunakan nama variabel yang berbeda untuk menghindari konflik dengan header.php
$status_nilai_materi_list = [];
$persentase_progress = 0;
$total_materi = 0;
$materi_terkirim = 0;

if ($kelas_id && !empty($semester)) {
    // Gunakan query langsung (bukan prepared statement) seperti test_query.php
    $kelas_id_safe = intval($kelas_id);
    $semester_safe = $conn->real_escape_string($semester);
    
    if ($has_kelas_id && $has_semester) {
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = $kelas_id_safe
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE m.kelas_id = $kelas_id_safe AND m.semester = '$semester_safe'
                         ORDER BY m.nama_mulok";
    } else {
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = $kelas_id_safe
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         ORDER BY m.nama_mulok";
    }
    
    $materi_result = $conn->query($query_materi);
    
    // Build array - sama persis dengan test_query.php
    $status_nilai_materi_list = [];
    $total_materi = 0;
    $materi_terkirim = 0;
    
    if ($materi_result) {
        while ($materi = $materi_result->fetch_assoc()) {
            $total_materi++;
            $materi_id = intval($materi['materi_id']);
            
            // Cek status dengan query langsung
            $ada_nilai = false;
            if (!empty($tahun_ajaran)) {
                $tahun_ajaran_safe = $conn->real_escape_string($tahun_ajaran);
                $query_cek = "SELECT COUNT(*) as cnt FROM nilai_kirim_status 
                             WHERE materi_mulok_id = $materi_id 
                             AND kelas_id = $kelas_id_safe 
                             AND semester = '$semester_safe' 
                             AND tahun_ajaran = '$tahun_ajaran_safe' 
                             AND status = 'terkirim'";
                $res_cek = $conn->query($query_cek);
                if ($res_cek) {
                    $row_cek = $res_cek->fetch_assoc();
                    $ada_nilai = (intval($row_cek['cnt']) > 0);
                    $res_cek->free();
                }
            }
            
            if ($ada_nilai) {
                $materi_terkirim++;
            }
            
            $status_nilai_materi_list[] = [
                'materi_id' => $materi_id,
                'nama_mulok' => $materi['nama_mulok'],
                'guru_id' => $materi['guru_id'],
                'nama_guru' => $materi['nama_guru'] ?? '-',
                'status' => $ada_nilai ? 'terkirim' : 'belum'
            ];
        }
        $materi_result->free();
    }
    
    // JANGAN assign ke $materi_list dulu (header.php akan meng-overwrite)
    // Simpan di variabel sementara
    $_SESSION['status_nilai_materi_list'] = $status_nilai_materi_list;
    $_SESSION['status_nilai_total_materi'] = $total_materi;
    $_SESSION['status_nilai_materi_terkirim'] = $materi_terkirim;
    $_SESSION['status_nilai_persentase'] = $total_materi > 0 ? round(($materi_terkirim / $total_materi) * 100, 2) : 0;
}

// Set page title (variabel lokal)
$page_title = 'Status Nilai';
?>
<?php include '../includes/header.php'; ?>

<?php
// Ambil data dari session setelah header di-include (untuk menghindari konflik dengan header.php)
if (isset($_SESSION['status_nilai_materi_list'])) {
    $materi_list = $_SESSION['status_nilai_materi_list'];
    $total_materi = $_SESSION['status_nilai_total_materi'] ?? 0;
    $materi_terkirim = $_SESSION['status_nilai_materi_terkirim'] ?? 0;
    $persentase_progress = $_SESSION['status_nilai_persentase'] ?? 0;
    
    // Hapus dari session
    unset($_SESSION['status_nilai_materi_list']);
    unset($_SESSION['status_nilai_total_materi']);
    unset($_SESSION['status_nilai_materi_terkirim']);
    unset($_SESSION['status_nilai_persentase']);
} else {
    $materi_list = [];
    $total_materi = 0;
    $materi_terkirim = 0;
    $persentase_progress = 0;
}
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> Status Nilai - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($kelas_id && !empty($materi_list)): ?>
            <!-- Progress Bar Materi Mulok -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Progres Nilai</h6>
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
                            <th>Materi Mulok</th>
                            <th>Nama Guru</th>
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
                                <td><?php echo htmlspecialchars($materi['nama_mulok'] ?? ''); ?></td>
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

