<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil kelas yang diampu oleh wali kelas ini
$kelas_data = null;
$kelas_id = 0;
$siswa_data = [];
try {
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    if ($kelas_id > 0) {
        // Ambil data siswa LANGSUNG dari tabel siswa saja
        // TIDAK ada JOIN, TIDAK ada subquery, TIDAK ada referensi ke tabel lain
        // Hanya mengambil dari tabel siswa berdasarkan kelas_id
        $stmt = $conn->prepare("SELECT id, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir FROM siswa WHERE kelas_id = ? ORDER BY nama ASC");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Simpan data ke array untuk menghindari masalah dengan result set
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Pastikan data siswa valid sebelum ditambahkan
                if (isset($row['id']) && $row['id'] > 0) {
                    $nama_siswa = trim($row['nama'] ?? '');
                    
                    // Skip jika nama sama dengan Administrator, Admin, atau Proktor
                    if (!empty($nama_siswa)) {
                        $nama_lower = strtolower($nama_siswa);
                        if ($nama_lower === 'administrator' || $nama_lower === 'admin' || $nama_lower === 'proktor') {
                            continue;
                        }
                    }
                    
                    $siswa_data[] = $row;
                }
            }
        }
        
        // Tutup statement setelah data diambil
        $stmt->close();
    }
    
    // Close kelas statement setelah data diambil
    if (isset($stmt_kelas)) {
        $stmt_kelas->close();
    }
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
    $siswa_data = [];
    error_log("Error in wali-kelas/rapor.php: " . $e->getMessage());
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-pdf"></i> Cetak Rapor</h5>
        <div>
            <?php if ($kelas_id > 0): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="cetakSemuaRapor()">
                    <i class="fas fa-print"></i> Semua Rapor
                </button>
                <button type="button" class="btn btn-info btn-sm" onclick="cetakSemuaNilai()">
                    <i class="fas fa-file-alt"></i> Semua Nilai
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="cetakSampul()">
                    <i class="fas fa-book"></i> Cetak Sampul
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="cetakIdentitas()">
                    <i class="fas fa-id-card"></i> Identitas
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="exportLeggerExcel()">
                    <i class="fas fa-file-excel"></i> Legger Excel
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="exportLeggerPDF()">
                    <i class="fas fa-file-pdf"></i> Legger PDF
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($kelas_id > 0): ?>
            <div class="mb-3">
                <label class="form-label">Kelas</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? 'Tidak Ada Kelas'); ?>" readonly style="max-width: 300px;">
            </div>
            
            <?php if (!empty($siswa_data)): ?>
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
                        foreach ($siswa_data as $row): 
                            // Pastikan id ada sebelum digunakan
                            $siswa_id = isset($row['id']) ? intval($row['id']) : 0;
                            $nama_siswa = trim($row['nama'] ?? '');
                            
                            // Skip hanya jika ID tidak valid
                            if ($siswa_id <= 0) {
                                continue;
                            }
                            
                            // Jika nama kosong, tampilkan placeholder
                            if (empty($nama_siswa)) {
                                $nama_siswa = '[Nama tidak tersedia]';
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.
                </div>
            <?php endif; ?>
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
        <?php if ($kelas_id > 0 && !empty($siswa_data)): ?>
        if ($('#tableCetak').length > 0) {
            $('#tableCetak').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']], // Sort by Nama ascending
                pageLength: 25
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
    
    function cetakSemuaNilai() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.open('../rapor/cetak_nilai.php?kelas=' + kelasId, '_blank');
    }
    
    function cetakSampul() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.open('../rapor/cetak_sampul.php?kelas=' + kelasId, '_blank');
    }
    
    function cetakIdentitas() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.open('../rapor/cetak_identitas.php?kelas=' + kelasId, '_blank');
    }
    
    function exportLeggerExcel() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.location.href = '../rapor/export_legger.php?format=excel&kelas=' + kelasId;
    }
    
    function exportLeggerPDF() {
        var kelasId = <?php echo $kelas_id; ?>;
        window.location.href = '../rapor/export_legger.php?format=pdf&kelas=' + kelasId;
    }
</script>
