<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Filter tahun ajaran
$tahun_ajaran_filter = isset($_GET['tahun_ajaran']) && $_GET['tahun_ajaran'] !== '' ? $_GET['tahun_ajaran'] : '';

// Pastikan kolom tahun_ajaran_lulus ada di tabel siswa
try {
    $check_column = $conn->query("SHOW COLUMNS FROM siswa LIKE 'tahun_ajaran_lulus'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE siswa ADD COLUMN tahun_ajaran_lulus VARCHAR(20) NULL AFTER kelas_id");
    }
} catch (Exception $e) {
    // Kolom mungkin sudah ada atau ada error lain, lanjutkan saja
}

// Ambil ID kelas Alumni
$kelas_alumni_id = null;
try {
    $query_alumni = "SELECT id FROM kelas WHERE nama_kelas LIKE '%Alumni%' OR nama_kelas LIKE '%Lulus%' ORDER BY nama_kelas LIMIT 1";
    $result_alumni = $conn->query($query_alumni);
    if ($result_alumni && $result_alumni->num_rows > 0) {
        $kelas_alumni = $result_alumni->fetch_assoc();
        $kelas_alumni_id = $kelas_alumni['id'];
    }
} catch (Exception $e) {
    // Handle error
}

// Ambil semua tahun ajaran unik dari kolom tahun_ajaran_lulus untuk siswa alumni
$tahun_ajaran_list = [];
if ($kelas_alumni_id) {
    try {
        // Ambil tahun ajaran unik dari kolom tahun_ajaran_lulus
        $query_tahun = "SELECT DISTINCT tahun_ajaran_lulus 
                        FROM siswa 
                        WHERE kelas_id = ? 
                        AND tahun_ajaran_lulus IS NOT NULL 
                        AND tahun_ajaran_lulus != '' 
                        ORDER BY tahun_ajaran_lulus DESC";
        $stmt_tahun = $conn->prepare($query_tahun);
        $stmt_tahun->bind_param("i", $kelas_alumni_id);
        $stmt_tahun->execute();
        $result_tahun = $stmt_tahun->get_result();
        while ($row = $result_tahun->fetch_assoc()) {
            if (!empty($row['tahun_ajaran_lulus'])) {
                $tahun_ajaran_list[] = $row['tahun_ajaran_lulus'];
            }
        }
        $stmt_tahun->close();
        
        // Jika tidak ada tahun ajaran dari tahun_ajaran_lulus, ambil dari nilai_siswa sebagai fallback
        if (empty($tahun_ajaran_list)) {
            // Ambil semua siswa alumni
            $query_siswa_alumni = "SELECT id FROM siswa WHERE kelas_id = ?";
            $stmt_siswa = $conn->prepare($query_siswa_alumni);
            $stmt_siswa->bind_param("i", $kelas_alumni_id);
            $stmt_siswa->execute();
            $result_siswa = $stmt_siswa->get_result();
            $siswa_alumni_ids = [];
            while ($row = $result_siswa->fetch_assoc()) {
                $siswa_alumni_ids[] = $row['id'];
            }
            $stmt_siswa->close();
            
            // Ambil tahun ajaran unik dari nilai_siswa untuk siswa alumni
            if (!empty($siswa_alumni_ids)) {
                $placeholders = str_repeat('?,', count($siswa_alumni_ids) - 1) . '?';
                $query_tahun_nilai = "SELECT DISTINCT tahun_ajaran FROM nilai_siswa WHERE siswa_id IN ($placeholders) AND tahun_ajaran IS NOT NULL AND tahun_ajaran != '' ORDER BY tahun_ajaran DESC";
                $stmt_tahun_nilai = $conn->prepare($query_tahun_nilai);
                $types = str_repeat('i', count($siswa_alumni_ids));
                $stmt_tahun_nilai->bind_param($types, ...$siswa_alumni_ids);
                $stmt_tahun_nilai->execute();
                $result_tahun_nilai = $stmt_tahun_nilai->get_result();
                $tahun_ajaran_set = [];
                while ($row = $result_tahun_nilai->fetch_assoc()) {
                    if (!empty($row['tahun_ajaran'])) {
                        $tahun_ajaran_set[$row['tahun_ajaran']] = true;
                    }
                }
                $stmt_tahun_nilai->close();
                $tahun_ajaran_list = array_keys($tahun_ajaran_set);
                // Sort descending
                rsort($tahun_ajaran_list);
            }
        }
        
        // Jika masih tidak ada tahun ajaran, ambil dari profil_madrasah dan generate beberapa tahun ke belakang
        if (empty($tahun_ajaran_list)) {
            try {
                $query_profil = "SELECT tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
                $result_profil = $conn->query($query_profil);
                $profil = $result_profil ? $result_profil->fetch_assoc() : null;
                $tahun_ajaran_aktif = $profil['tahun_ajaran_aktif'] ?? '';
                
                if (!empty($tahun_ajaran_aktif)) {
                    // Parse tahun ajaran aktif
                    if (strpos($tahun_ajaran_aktif, '/') !== false) {
                        $parts = explode('/', $tahun_ajaran_aktif);
                        $separator = '/';
                    } elseif (strpos($tahun_ajaran_aktif, '-') !== false) {
                        $parts = explode('-', $tahun_ajaran_aktif);
                        $separator = '-';
                    } else {
                        $parts = [];
                        $separator = '/';
                    }
                    
                    if (count($parts) >= 2) {
                        $tahun_awal = intval(trim($parts[0]));
                        
                        // Generate 10 tahun ke belakang
                        for ($i = 0; $i < 10; $i++) {
                            $tahun_start = $tahun_awal - $i;
                            $tahun_end = $tahun_start + 1;
                            $tahun_ajaran_list[] = $tahun_start . $separator . $tahun_end;
                        }
                    }
                }
            } catch (Exception $e) {
                // Handle error
            }
        }
    } catch (Exception $e) {
        // Handle error
    }
}

// Query data alumni
$alumni_data = [];
try {
    if ($kelas_alumni_id) {
        // Filter berdasarkan tahun ajaran jika dipilih
        if (!empty($tahun_ajaran_filter)) {
            // Ambil siswa alumni yang tahun ajaran lulusnya sesuai dengan filter
            // Gunakan kolom tahun_ajaran_lulus dari tabel siswa
            $query = "SELECT s.*, k.nama_kelas
                      FROM siswa s
                      LEFT JOIN kelas k ON s.kelas_id = k.id
                      WHERE s.kelas_id = ?
                      AND s.tahun_ajaran_lulus = ?
                      ORDER BY s.nama";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("is", $kelas_alumni_id, $tahun_ajaran_filter);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $alumni_data[] = $row;
                    }
                }
                $stmt->close();
            }
        } else {
            // Tampilkan semua alumni
            $query = "SELECT s.*, k.nama_kelas
                      FROM siswa s
                      LEFT JOIN kelas k ON s.kelas_id = k.id
                      WHERE s.kelas_id = ?
                      ORDER BY s.tahun_ajaran_lulus DESC, s.nama";
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $kelas_alumni_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $alumni_data[] = $row;
                    }
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

// Set page title (variabel lokal)
$page_title = 'Data Alumni';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Data Alumni</h5>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label">Filter Tahun Ajaran</label>
            <select class="form-select" id="filterTahunAjaran" onchange="filterTahunAjaran()" style="max-width: 300px;">
                <option value="">-- Semua Tahun Ajaran --</option>
                <?php 
                foreach ($tahun_ajaran_list as $tahun): 
                ?>
                    <option value="<?php echo htmlspecialchars($tahun); ?>" <?php echo $tahun_ajaran_filter == $tahun ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tahun); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Pilih tahun ajaran untuk melihat alumni berdasarkan tahun ajaran tertentu</small>
        </div>
        
        <?php if (!$kelas_alumni_id): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Kelas Alumni belum ditemukan. Pastikan sudah ada kelas dengan nama "Alumni" atau "Lulus" di database.
            </div>
        <?php elseif (empty($alumni_data)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <?php if (!empty($tahun_ajaran_filter)): ?>
                    Tidak ada data alumni untuk tahun ajaran <?php echo htmlspecialchars($tahun_ajaran_filter); ?>.
                <?php else: ?>
                    Belum ada data alumni.
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableAlumni">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>Jenis Kelamin</th>
                        <th>Tempat, Tgl Lahir</th>
                        <th>Orangtua/Wali</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($alumni_data) > 0): 
                        $no = 1;
                        foreach ($alumni_data as $row): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nisn'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-', ENT_QUOTES, 'UTF-8'); ?><?php echo (!empty($row['tempat_lahir']) && !empty($row['tanggal_lahir'])) ? ', ' : ''; ?><?php echo !empty($row['tanggal_lahir']) ? htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_lahir'])), ENT_QUOTES, 'UTF-8') : ''; ?></td>
                            <td><?php echo htmlspecialchars($row['orangtua_wali'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editAlumni(<?php echo $row['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteAlumni(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada data alumni</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    function filterTahunAjaran() {
        var tahunAjaran = $('#filterTahunAjaran').val();
        var url = new URL(window.location.href);
        if (tahunAjaran) {
            url.searchParams.set('tahun_ajaran', tahunAjaran);
        } else {
            url.searchParams.delete('tahun_ajaran');
        }
        window.location.href = url.toString();
    }
    
    function editAlumni(id) {
        // Redirect ke halaman edit siswa dengan parameter alumni
        window.location.href = '../siswa/index.php?edit=' + id + '&alumni=1';
    }
    
    function deleteAlumni(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Data alumni akan dihapus secara permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect ke halaman siswa untuk delete
                window.location.href = '../siswa/index.php?delete=' + id;
            }
        });
    }
    
    $(document).ready(function() {
        // Inisialisasi DataTables
        <?php if (count($alumni_data) > 0): ?>
        if ($('#tableAlumni').length > 0) {
            $('#tableAlumni').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']],
                pageLength: 25
            });
        }
        <?php endif; ?>
    });
</script>

