<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();

// Filter kelas
$kelas_filter = $_GET['kelas'] ?? '';

// Ambil data kelas
$kelas_list = null;
$result = null;
try {
    $query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
    $kelas_list = $conn->query($query_kelas);
    if (!$kelas_list) {
        $kelas_list = null;
    }
} catch (Exception $e) {
    $kelas_list = null;
}

// Query data siswa
try {
    if ($kelas_filter) {
        $kelas_id = intval($kelas_filter);
        $stmt = $conn->prepare("SELECT s.*, k.nama_kelas
              FROM siswa s
              LEFT JOIN kelas k ON s.kelas_id = k.id
              WHERE s.kelas_id = ?
              ORDER BY s.nama");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT s.*, k.nama_kelas
              FROM siswa s
              LEFT JOIN kelas k ON s.kelas_id = k.id
              ORDER BY s.nama";
        $result = $conn->query($query);
    }
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
        $result = null;
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-pdf"></i> Cetak Rapor</h5>
        <div>
            <?php if ($kelas_filter): ?>
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
        <div class="mb-3">
            <label class="form-label">Filter Kelas</label>
            <select class="form-select" id="filterKelas" onchange="filterKelas()" style="max-width: 300px;">
                <option value="">-- Pilih Kelas --</option>
                <?php 
                $kelas_list->data_seek(0);
                while ($kelas = $kelas_list->fetch_assoc()): 
                ?>
                    <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_filter == $kelas['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <?php if ($kelas_filter && $result && $result->num_rows > 0): ?>
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
                    if ($result && $result->num_rows > 0):
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
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada data siswa</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php elseif (!$kelas_filter): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Pilih kelas terlebih dahulu untuk melihat data siswa.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Belum ada siswa di kelas yang dipilih.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    function filterKelas() {
        var kelasId = $('#filterKelas').val();
        if (kelasId) {
            window.location.href = 'cetak.php?kelas=' + kelasId;
        } else {
            window.location.href = 'cetak.php';
        }
    }
    
    $(document).ready(function() {
        $('#tableCetak').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
    
    function cetakRaporSiswa(siswaId) {
        window.open('cetak_rapor.php?siswa=' + siswaId, '_blank');
    }
    
    function cetakSemuaRapor() {
        var kelasId = $('#filterKelas').val();
        window.open('cetak_rapor.php?kelas=' + kelasId + '&semua=1', '_blank');
    }
    
    function cetakSemuaNilai() {
        var kelasId = $('#filterKelas').val();
        window.open('cetak_nilai.php?kelas=' + kelasId, '_blank');
    }
    
    function cetakSampul() {
        var kelasId = $('#filterKelas').val();
        window.open('cetak_sampul.php?kelas=' + kelasId, '_blank');
    }
    
    function cetakIdentitas() {
        var kelasId = $('#filterKelas').val();
        window.open('cetak_identitas.php?kelas=' + kelasId, '_blank');
    }
    
    function exportLeggerExcel() {
        var kelasId = $('#filterKelas').val();
        window.location.href = 'export_legger.php?format=excel&kelas=' + kelasId;
    }
    
    function exportLeggerPDF() {
        var kelasId = $('#filterKelas').val();
        window.location.href = 'export_legger.php?format=pdf&kelas=' + kelasId;
    }
</script>

