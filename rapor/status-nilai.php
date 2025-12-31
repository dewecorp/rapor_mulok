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

// Query status nilai per kelas dan materi
$status_data = [];
$materi_status_list = [];
if ($kelas_filter) {
    $kelas_id = intval($kelas_filter);
    
    // Ambil materi yang diampu untuk kelas ini dan semester aktif
    if ($has_kelas_id && $has_semester) {
        // Struktur baru: ambil materi berdasarkan kelas_id dan semester
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE m.kelas_id = ? AND m.semester = ?
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("iis", $kelas_id, $kelas_id, $semester);
    } else {
        // Struktur lama: ambil semua materi yang diampu untuk kelas ini
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $kelas_id);
    }
    $stmt_materi->execute();
    $materi_result = $stmt_materi->get_result();
    
    // Hitung total materi dan materi yang sudah dikirim
    $total_materi = 0;
    $materi_terkirim = 0;
    
    if ($materi_result) {
        while ($materi = $materi_result->fetch_assoc()) {
            $total_materi++;
            $materi_id = $materi['materi_id'];
            
            // Cek apakah ada nilai yang sudah dikirim untuk materi ini
            $query_cek_nilai = "SELECT COUNT(*) as total 
                               FROM nilai_siswa 
                               WHERE materi_mulok_id = ? 
                               AND kelas_id = ? 
                               AND semester = ? 
                               AND tahun_ajaran = ?";
            $stmt_cek = $conn->prepare($query_cek_nilai);
            $stmt_cek->bind_param("iiss", $materi_id, $kelas_id, $semester, $tahun_ajaran);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            $cek_data = $result_cek->fetch_assoc();
            $ada_nilai = ($cek_data['total'] ?? 0) > 0;
            
            if ($ada_nilai) {
                $materi_terkirim++;
            }
            
            $materi_status_list[] = [
                'materi_id' => $materi_id,
                'nama_mulok' => $materi['nama_mulok'],
                'guru_id' => $materi['guru_id'],
                'nama_guru' => $materi['nama_guru'] ?? '-',
                'status' => $ada_nilai ? 'terkirim' : 'belum'
            ];
        }
    }
    
    // Hitung persentase progress
    $persentase_progress = $total_materi > 0 ? round(($materi_terkirim / $total_materi) * 100, 2) : 0;
    
    $status_data = [
        'total_materi' => $total_materi,
        'materi_terkirim' => $materi_terkirim,
        'materi_belum' => $total_materi - $materi_terkirim,
        'persentase' => $persentase_progress,
        'materi_list' => $materi_status_list
    ];
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
        
        <?php if ($kelas_filter && isset($status_data) && !empty($status_data['materi_list'])): ?>
            <!-- Progress Bar Materi Mulok -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Materi Mulok</h6>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar" 
                             role="progressbar" 
                             style="width: <?php echo $status_data['persentase']; ?>%; background-color: #2d5016;" 
                             aria-valuenow="<?php echo $status_data['persentase']; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo $status_data['persentase']; ?>% (<?php echo $status_data['materi_terkirim']; ?> dari <?php echo $status_data['total_materi']; ?>)
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
                            <th>Materi Mulok</th>
                            <th>Nama Guru</th>
                            <th>Status Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($status_data['materi_list'] as $materi): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($materi['nama_mulok']); ?></td>
                                <td><?php echo htmlspecialchars($materi['nama_guru']); ?></td>
                                <td>
                                    <?php if ($materi['status'] == 'terkirim'): ?>
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

