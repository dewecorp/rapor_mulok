<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Ambil data pengaturan cetak
$pengaturan = null;
try {
    $query = "SELECT * FROM pengaturan_cetak LIMIT 1";
    $result = $conn->query($query);
    $pengaturan = $result ? $result->fetch_assoc() : null;
    
    if (!$pengaturan) {
        // Insert default jika belum ada
        try {
            $conn->query("INSERT INTO pengaturan_cetak (tempat_cetak, tanggal_cetak) VALUES ('', NOW())");
            $result = $conn->query($query);
            $pengaturan = $result ? $result->fetch_assoc() : null;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $pengaturan = null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tempat_cetak = $_POST['tempat_cetak'] ?? '';
    $tanggal_cetak = $_POST['tanggal_cetak'] ?? date('Y-m-d');
    
    if ($pengaturan && isset($pengaturan['id'])) {
        $stmt = $conn->prepare("UPDATE pengaturan_cetak SET tempat_cetak=?, tanggal_cetak=? WHERE id=?");
        $stmt->bind_param("ssi", $tempat_cetak, $tanggal_cetak, $pengaturan['id']);
        
        if ($stmt->execute()) {
            $success = 'Pengaturan cetak berhasil diperbarui!';
            $result = $conn->query("SELECT * FROM pengaturan_cetak LIMIT 1");
            $pengaturan = $result ? $result->fetch_assoc() : null;
        } else {
            $error = 'Gagal memperbarui pengaturan cetak!';
        }
    } else {
        $error = 'Data pengaturan tidak ditemukan!';
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-print"></i> Pengaturan Cetak</h5>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: '<?php echo addslashes($success); ?>',
                        confirmButtonColor: '#2d5016',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                });
            </script>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tempat Cetak</label>
                    <input type="text" class="form-control" name="tempat_cetak" 
                           value="<?php echo htmlspecialchars($pengaturan['tempat_cetak'] ?? ''); ?>" 
                           placeholder="Contoh: Sukosono">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tanggal Cetak</label>
                    <input type="date" class="form-control" name="tanggal_cetak" 
                           value="<?php echo $pengaturan['tanggal_cetak'] ? date('Y-m-d', strtotime($pengaturan['tanggal_cetak'])) : date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: '<?php echo addslashes($success); ?>',
        confirmButtonColor: '#2d5016',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false
    });
    <?php endif; ?>
    
    <?php if ($error): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: '<?php echo addslashes($error); ?>',
        confirmButtonColor: '#2d5016',
        timer: 4000,
        timerProgressBar: true,
        showConfirmButton: true
    });
    <?php endif; ?>
</script>

