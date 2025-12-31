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

// Query status nilai per materi
$materi_list = [];
$total_materi = 0;
$materi_terkirim = 0;
$persentase_progress = 0;

if ($kelas_id && !empty($semester) && !empty($tahun_ajaran)) {
    // Ambil SEMUA materi di kelas ini dan semester aktif, beserta guru pengampunya
    // Mulai dari materi_mulok untuk mengambil SEMUA materi, lalu LEFT JOIN dengan mengampu_materi
    if ($has_kelas_id && $has_semester) {
        // Struktur baru: ambil semua materi berdasarkan kelas_id dan semester
        $query_materi = "SELECT m.id as materi_id, 
                                m.nama_mulok, 
                                mm.guru_id, 
                                p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE m.kelas_id = ? AND m.semester = ?
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        if ($stmt_materi) {
            $stmt_materi->bind_param("iis", $kelas_id, $kelas_id, $semester);
            $stmt_materi->execute();
            $materi_result = $stmt_materi->get_result();
        } else {
            $materi_result = null;
        }
    } else {
        // Struktur lama: ambil semua materi yang diampu untuk kelas ini
        $query_materi = "SELECT m.id as materi_id, 
                                m.nama_mulok, 
                                mm.guru_id, 
                                p.nama as nama_guru
                         FROM mengampu_materi mm
                         INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE mm.kelas_id = ?
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        if ($stmt_materi) {
            $stmt_materi->bind_param("i", $kelas_id);
            $stmt_materi->execute();
            $materi_result = $stmt_materi->get_result();
        } else {
            $materi_result = null;
        }
    }
    
    // Hitung total materi dan materi yang sudah dikirim
    if ($materi_result) {
        while ($materi = $materi_result->fetch_assoc()) {
            $total_materi++;
            $materi_id = $materi['materi_id'];
            
            // Cek apakah ada nilai yang sudah dikirim untuk materi ini
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
            $nama_guru = isset($materi['nama_guru']) && !empty(trim($materi['nama_guru'])) ? trim($materi['nama_guru']) : '';
            $guru_id = isset($materi['guru_id']) && !empty($materi['guru_id']) ? intval($materi['guru_id']) : 0;
            
            // Jika nama_guru kosong tapi ada guru_id, ambil dari database
            if (empty($nama_guru) && $guru_id > 0) {
                $stmt_guru = $conn->prepare("SELECT nama FROM pengguna WHERE id = ?");
                if ($stmt_guru) {
                    $stmt_guru->bind_param("i", $guru_id);
                    $stmt_guru->execute();
                    $result_guru = $stmt_guru->get_result();
                    if ($result_guru && $result_guru->num_rows > 0) {
                        $guru_data = $result_guru->fetch_assoc();
                        $nama_guru = isset($guru_data['nama']) ? trim($guru_data['nama']) : '';
                    }
                    $stmt_guru->close();
                }
            }
            
            // Set default jika masih kosong
            if (empty($nama_guru)) {
                $nama_guru = '-';
            }
            
            $materi_list[] = [
                'materi_id' => $materi_id,
                'nama_mulok' => $materi['nama_mulok'],
                'guru_id' => $guru_id,
                'nama_guru' => $nama_guru,
                'status' => $ada_nilai ? 'terkirim' : 'belum'
            ];
        }
        if (isset($stmt_materi)) {
            $stmt_materi->close();
        }
    }
    
    // Hitung persentase progress
    $persentase_progress = $total_materi > 0 ? round(($materi_terkirim / $total_materi) * 100, 2) : 0;
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
