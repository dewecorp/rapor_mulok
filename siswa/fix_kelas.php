<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'fix') {
    $siswa_id = intval($_POST['siswa_id']);
    $kelas_id = intval($_POST['kelas_id']);
    
    $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $kelas_id, $siswa_id);
    
    if ($stmt->execute()) {
        // Update jumlah siswa di kelas
        $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
        echo json_encode(['success' => true, 'message' => 'Kelas berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate kelas']);
    }
    exit;
}

// Ambil siswa tanpa kelas
$siswa_tanpa_kelas = [];
$query = "SELECT * FROM siswa WHERE kelas_id IS NULL ORDER BY nama";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $siswa_tanpa_kelas[] = $row;
    }
}

// Ambil daftar kelas
$kelas_list = [];
$query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
$result_kelas = $conn->query($query_kelas);
if ($result_kelas) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tools"></i> Perbaiki Kelas Siswa</h5>
    </div>
    <div class="card-body">
        <?php if (count($siswa_tanpa_kelas) > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Ditemukan <?php echo count($siswa_tanpa_kelas); ?> siswa tanpa kelas.
            </div>
            
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswa_tanpa_kelas as $siswa): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                            <td><?php echo htmlspecialchars($siswa['nama']); ?></td>
                            <td>
                                <select class="form-select form-select-sm kelas-select" data-siswa-id="<?php echo $siswa['id']; ?>">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($kelas_list as $kelas): ?>
                                        <option value="<?php echo $kelas['id']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-save" data-siswa-id="<?php echo $siswa['id']; ?>" disabled>
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Semua siswa sudah memiliki kelas.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.kelas-select').on('change', function() {
        var siswaId = $(this).data('siswa-id');
        var kelasId = $(this).val();
        var btnSave = $('.btn-save[data-siswa-id="' + siswaId + '"]');
        
        if (kelasId) {
            btnSave.prop('disabled', false);
        } else {
            btnSave.prop('disabled', true);
        }
    });
    
    $('.btn-save').on('click', function() {
        var siswaId = $(this).data('siswa-id');
        var kelasId = $('.kelas-select[data-siswa-id="' + siswaId + '"]').val();
        
        if (!kelasId) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Silakan pilih kelas terlebih dahulu',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        $.ajax({
            url: 'fix_kelas.php',
            method: 'POST',
            data: {
                action: 'fix',
                siswa_id: siswaId,
                kelas_id: kelasId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: response.message,
                        confirmButtonColor: '#2d5016',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message,
                        confirmButtonColor: '#2d5016'
                    });
                }
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>


