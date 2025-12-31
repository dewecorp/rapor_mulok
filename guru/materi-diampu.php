<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('guru');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil materi yang diampu oleh guru ini
$result = null;
try {
    $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas
              FROM mengampu_materi mm
              INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
              INNER JOIN kelas k ON mm.kelas_id = k.id
              WHERE mm.guru_id = ?
              ORDER BY k.nama_kelas, m.nama_mulok");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $result = null;
    }
} catch (Exception $e) {
    $result = null;
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-book"></i> Materi yang Diampu</h5>
    </div>
    <div class="card-body">
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableMateri">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Kode Mulok</th>
                            <th>Nama Mulok</th>
                            <th>Jumlah Jam</th>
                            <th>Kelas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['kode_mulok']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_mulok']); ?></td>
                                <td><?php echo htmlspecialchars($row['jumlah_jam']); ?> Jam</td>
                                <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada materi yang diampu.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tableMateri').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
</script>

