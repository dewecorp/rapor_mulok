<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil kelas yang diampu oleh wali kelas ini
$kelas_data = null;
$kelas_id = 0;
$result = null;
try {
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    if ($kelas_id) {
        // Query data siswa
        $stmt = $conn->prepare("SELECT s.*
                  FROM siswa s
                  WHERE s.kelas_id = ?
                  ORDER BY s.nama");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            $result = null;
        }
    }
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
    $result = null;
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
        <?php if ($kelas_id && $result && $result->num_rows > 0): ?>
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
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['nisn']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                <td><?php echo $row['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?></td>
                                <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?>, <?php echo $row['tanggal_lahir'] ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-'; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="cetakRaporSiswa(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-print"></i> Cetak
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$kelas_id): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Anda belum ditugaskan sebagai wali kelas.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tableCetak').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
    
    function cetakRaporSiswa(siswaId) {
        window.open('../rapor/cetak_rapor.php?siswa=' + siswaId, '_blank');
    }
    
    function cetakSemuaRapor() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.open('../rapor/cetak_rapor.php?kelas=' + kelasId + '&semua=1', '_blank');
    }
</script>

