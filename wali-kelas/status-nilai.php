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
    // COPY PASTE PERSIS dari test_query.php yang berhasil
    if ($has_kelas_id && $has_semester) {
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE m.kelas_id = ? AND m.semester = ?
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("iis", $kelas_id, $kelas_id, $semester);
    } else {
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $kelas_id);
    }
    
    $stmt_materi->execute();
    $materi_result = $stmt_materi->get_result();
    
    // COPY PASTE PERSIS dari test_query.php - build array tanpa status dulu
    $temp_materi_list = [];
    if ($materi_result) {
        while ($materi = $materi_result->fetch_assoc()) {
            $temp_materi_list[] = [
                'materi_id' => $materi['materi_id'],
                'nama_mulok' => $materi['nama_mulok'],
                'guru_id' => $materi['guru_id'],
                'nama_guru' => $materi['nama_guru'] ?? '-'
            ];
        }
        $stmt_materi->close();
    }
    
    // Sekarang cek status untuk semua materi (setelah loop selesai)
    $total_materi = count($temp_materi_list);
    $materi_terkirim = 0;
    $status_nilai_materi_list = [];
    
    if (!empty($temp_materi_list) && !empty($tahun_ajaran)) {
        $materi_ids = array_column($temp_materi_list, 'materi_id');
        $ids_str = implode(',', array_map('intval', $materi_ids));
        $kelas_id_safe = intval($kelas_id);
        $semester_safe = $conn->real_escape_string($semester);
        $tahun_ajaran_safe = $conn->real_escape_string($tahun_ajaran);
        
        $query_status = "SELECT materi_mulok_id FROM nilai_kirim_status 
                        WHERE materi_mulok_id IN ($ids_str) 
                        AND kelas_id = $kelas_id_safe 
                        AND semester = '$semester_safe' 
                        AND tahun_ajaran = '$tahun_ajaran_safe' 
                        AND status = 'terkirim'";
        $result_status = $conn->query($query_status);
        
        $terkirim_ids = [];
        if ($result_status) {
            while ($row = $result_status->fetch_assoc()) {
                $terkirim_ids[] = intval($row['materi_mulok_id']);
            }
            $result_status->free();
        }
        
        // Build final array dengan status
        foreach ($temp_materi_list as $item) {
            $ada_nilai = in_array($item['materi_id'], $terkirim_ids);
            if ($ada_nilai) {
                $materi_terkirim++;
            }
            
            $status_nilai_materi_list[] = [
                'materi_id' => $item['materi_id'],
                'nama_mulok' => $item['nama_mulok'],
                'guru_id' => $item['guru_id'],
                'nama_guru' => $item['nama_guru'],
                'status' => $ada_nilai ? 'terkirim' : 'belum'
            ];
        }
    } else {
        // Jika tidak ada tahun_ajaran, semua status 'belum'
        foreach ($temp_materi_list as $item) {
            $status_nilai_materi_list[] = [
                'materi_id' => $item['materi_id'],
                'nama_mulok' => $item['nama_mulok'],
                'guru_id' => $item['guru_id'],
                'nama_guru' => $item['nama_guru'],
                'status' => 'belum'
            ];
        }
    }
    
    // Assign ke $materi_list SETELAH semua proses selesai (sebelum include header)
    $materi_list = $status_nilai_materi_list;
    
    // Hitung persentase progress
    $persentase_progress = $total_materi > 0 ? round(($materi_terkirim / $total_materi) * 100, 2) : 0;
}

// DEBUG: Log jumlah materi yang ditemukan
error_log("Status Nilai Wali Kelas - Kelas ID: $kelas_id, Semester: $semester, Total Materi: " . count($materi_list));

// Set page title (variabel lokal)
$page_title = 'Status Nilai';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tasks"></i> Status Nilai - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <!-- DEBUG INFO -->
        <div class="alert alert-info mb-3">
            <strong>Debug Info:</strong><br>
            Kelas ID: <?php echo $kelas_id; ?><br>
            Semester: <?php echo $semester; ?><br>
            Total Materi dalam Array: <?php echo count($materi_list); ?><br>
            Total Materi Counter: <?php echo $total_materi; ?><br>
            Materi Terkirim: <?php echo $materi_terkirim; ?><br>
        </div>
        
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

