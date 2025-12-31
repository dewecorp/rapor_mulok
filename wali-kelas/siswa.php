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
$siswa_data = [];
try {
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    if ($kelas_id) {
        // Ambil data siswa di kelas
        $stmt = $conn->prepare("SELECT * FROM siswa WHERE kelas_id = ? ORDER BY nama");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $siswa_data[] = $row;
            }
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
    $result = null;
    $siswa_data = [];
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Data Siswa Kelas <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($kelas_id): ?>
            <?php if (count($siswa_data) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableSiswa">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>NISN</th>
                                <th>Nama</th>
                                <th>Jenis Kelamin</th>
                                <th>Tempat, Tgl Lahir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($siswa_data as $row): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($row['nisn'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama'] ?? ''); ?></td>
                                    <td><?php echo ($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                    <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?><?php echo (!empty($row['tempat_lahir']) && !empty($row['tanggal_lahir'])) ? ', ' : ''; ?><?php echo !empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
                </div>
            <?php endif; ?>
        <?php elseif (!$kelas_id): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Anda belum ditugaskan sebagai wali kelas.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if ($kelas_id && count($siswa_data) > 0): ?>
        if ($('#tableSiswa').length > 0) {
            $('#tableSiswa').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']], // Sort by Nama ascending
                pageLength: 10,
                searching: true,
                paging: true,
                info: true
            });
        }
        <?php endif; ?>
    });
</script>

