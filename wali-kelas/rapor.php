<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil kelas yang diampu oleh wali kelas ini
$kelas_data = null;
$kelas_id = 0;
$error = '';
$siswa_data = [];

try {
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    if ($kelas_id) {
        // Query data siswa - sama dengan dashboard proktor
        $query = "SELECT s.*, k.nama_kelas
                  FROM siswa s
                  LEFT JOIN kelas k ON s.kelas_id = k.id
                  WHERE s.kelas_id = ?
                  ORDER BY s.nama";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $siswa_data[] = $row;
            }
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $kelas_data = null;
    $kelas_id = 0;
    $siswa_data = [];
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-pdf"></i> Cetak Rapor - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
        <?php if ($kelas_id): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="cetakSemuaRapor()">
                <i class="fas fa-print"></i> Semua Rapor
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($kelas_id): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableCetak">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>L/P</th>
                        <th>TTL</th>
                        <th width="100">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($siswa_data) > 0): 
                        $no = 1;
                        foreach ($siswa_data as $row): 
                            // Pastikan id ada sebelum digunakan
                            $siswa_id = isset($row['id']) ? intval($row['id']) : 0;
                            $nama_siswa = trim($row['nama'] ?? '');
                            
                            // Skip hanya jika ID tidak valid atau nama kosong
                            if ($siswa_id <= 0 || empty($nama_siswa)) {
                                continue;
                            }
                            
                            // Skip jika nama persis sama dengan Administrator, Admin, atau Proktor
                            $nama_lower = strtolower($nama_siswa);
                            if ($nama_lower === 'administrator' || $nama_lower === 'admin' || $nama_lower === 'proktor') {
                                continue;
                            }
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nisn'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($nama_siswa); ?></td>
                            <td><?php echo (($row['jenis_kelamin'] ?? '') == 'L') ? 'L' : 'P'; ?></td>
                            <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?>, <?php echo !empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="cetakRaporSiswa(<?php echo $siswa_id; ?>)">
                                    <i class="fas fa-print"></i> Cetak
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Anda belum ditugaskan sebagai wali kelas.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Inisialisasi DataTables hanya jika ada data
        <?php if ($kelas_id && count($siswa_data) > 0): ?>
        if ($('#tableCetak').length > 0) {
            $('#tableCetak').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                }
            });
        }
        <?php endif; ?>
    });
    
    function cetakRaporSiswa(siswaId) {
        window.open('../rapor/cetak_rapor.php?siswa=' + siswaId, '_blank');
    }
    
    function cetakSemuaRapor() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.open('../rapor/cetak_rapor.php?kelas=' + kelasId + '&semua=1', '_blank');
    }
</script>

