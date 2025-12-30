<?php
// Prevent caching untuk memastikan data selalu fresh setelah redirect
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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
    // Ambil kelas yang diampu oleh wali kelas ini
    // Query langsung tanpa cache
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    // Pastikan kelas_id valid sebelum query siswa
    $siswa_data = [];
    if ($kelas_id > 0) {
        // Ambil data siswa LANGSUNG dari tabel siswa saja
        // TIDAK ada JOIN, TIDAK ada subquery, TIDAK ada referensi ke tabel lain
        // Hanya mengambil dari tabel siswa berdasarkan kelas_id
        // Query langsung tanpa cache untuk memastikan data selalu fresh
        $stmt = $conn->prepare("SELECT id, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir FROM siswa WHERE kelas_id = ? ORDER BY nama ASC");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Simpan data ke array untuk menghindari masalah dengan result set
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Pastikan data siswa valid
                if (isset($row['id']) && $row['id'] > 0) {
                    $siswa_data[] = $row;
                }
            }
        }
        
        // Tutup statement setelah data diambil
        $stmt->close();
        
        // Set result ke null karena kita sudah menggunakan array
        $result = null;
    }
    
    // Close kelas statement setelah data diambil
    if (isset($stmt_kelas)) {
        $stmt_kelas->close();
    }
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
    $result = null;
    error_log("Error in wali-kelas/siswa.php: " . $e->getMessage());
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Data Siswa - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($kelas_id > 0 && !empty($siswa_data)): ?>
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
                                <td><?php echo htmlspecialchars($row['nisn'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['nama'] ?? '-'); ?></td>
                                <td><?php echo ($row['jenis_kelamin'] ?? '') == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?>, <?php echo !empty($row['tanggal_lahir']) ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($kelas_id <= 0): ?>
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
        <?php if ($kelas_id > 0 && !empty($siswa_data)): ?>
        if ($('#tableSiswa').length > 0) {
            $('#tableSiswa').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']], // Sort by Nama ascending
                pageLength: 25
            });
        }
        <?php endif; ?>
    });
</script>

