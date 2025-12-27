<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Ambil data profil
$query = "SELECT * FROM profil_madrasah LIMIT 1";
$result = $conn->query($query);
$profil = $result->fetch_assoc();

if (!$profil) {
    // Insert default jika belum ada
    $conn->query("INSERT INTO profil_madrasah (nama_madrasah) VALUES ('MI Sultan Fattah Sukosono')");
    $result = $conn->query($query);
    $profil = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_madrasah = $_POST['nama_madrasah'] ?? '';
    $nsm = $_POST['nsm'] ?? '';
    $npsn = $_POST['npsn'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $kecamatan = $_POST['kecamatan'] ?? '';
    $kabupaten = $_POST['kabupaten'] ?? '';
    $provinsi = $_POST['provinsi'] ?? '';
    $nama_kepala = $_POST['nama_kepala'] ?? '';
    $nip_kepala = $_POST['nip_kepala'] ?? '';
    $tahun_ajaran = $_POST['tahun_ajaran_aktif'] ?? '';
    $semester = $_POST['semester_aktif'] ?? '1';
    
    // Upload logo
    $logo = $profil['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logo = 'logo_' . time() . '.' . $file_ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo);
    }
    
    // Upload foto login
    $foto_login = $profil['foto_login'];
    if (isset($_FILES['foto_login']) && $_FILES['foto_login']['error'] == 0) {
        $upload_dir = '../uploads/';
        $file_ext = pathinfo($_FILES['foto_login']['name'], PATHINFO_EXTENSION);
        $foto_login = 'login_' . time() . '.' . $file_ext;
        move_uploaded_file($_FILES['foto_login']['tmp_name'], $upload_dir . $foto_login);
    }
    
    $stmt = $conn->prepare("UPDATE profil_madrasah SET logo=?, nama_madrasah=?, nsm=?, npsn=?, alamat=?, kecamatan=?, kabupaten=?, provinsi=?, nama_kepala=?, nip_kepala=?, tahun_ajaran_aktif=?, semester_aktif=?, foto_login=? WHERE id=?");
    $stmt->bind_param("sssssssssssssi", $logo, $nama_madrasah, $nsm, $npsn, $alamat, $kecamatan, $kabupaten, $provinsi, $nama_kepala, $nip_kepala, $tahun_ajaran, $semester, $foto_login, $profil['id']);
    
    if ($stmt->execute()) {
        $success = 'Profil madrasah berhasil diperbarui!';
        $result = $conn->query($query);
        $profil = $result->fetch_assoc();
    } else {
        $error = 'Gagal memperbarui profil madrasah!';
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-school"></i> Profil Madrasah</h5>
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
        
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-12 mb-4">
                    <h6 class="border-bottom pb-2">A. Pengaturan Logo</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Logo Madrasah</label>
                                <input type="file" class="form-control" name="logo" accept="image/*">
                                <small class="text-muted">Format: JPG, PNG. Maksimal 2MB</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Preview Logo</label><br>
                            <img src="../uploads/<?php echo htmlspecialchars($profil['logo'] ?? 'logo.png'); ?>" 
                                 alt="Logo" style="max-height: 100px; background: #f0f0f0; padding: 10px;" 
                                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <div style="display: none; max-height: 100px; background: #f0f0f0; border: 2px dashed #ccc; padding: 10px; text-align: center; color: #999;">
                                <i class="fas fa-image"></i><br>
                                <small>Logo belum diupload</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12 mb-4">
                    <h6 class="border-bottom pb-2">B. Info Madrasah</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Madrasah <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_madrasah" 
                                   value="<?php echo htmlspecialchars($profil['nama_madrasah'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">NSM</label>
                            <input type="text" class="form-control" name="nsm" 
                                   value="<?php echo htmlspecialchars($profil['nsm'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">NPSN</label>
                            <input type="text" class="form-control" name="npsn" 
                                   value="<?php echo htmlspecialchars($profil['npsn'] ?? ''); ?>">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="alamat" rows="2"><?php echo htmlspecialchars($profil['alamat'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kecamatan</label>
                            <input type="text" class="form-control" name="kecamatan" 
                                   value="<?php echo htmlspecialchars($profil['kecamatan'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kabupaten</label>
                            <input type="text" class="form-control" name="kabupaten" 
                                   value="<?php echo htmlspecialchars($profil['kabupaten'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Provinsi</label>
                            <input type="text" class="form-control" name="provinsi" 
                                   value="<?php echo htmlspecialchars($profil['provinsi'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12 mb-4">
                    <h6 class="border-bottom pb-2">C. Pimpinan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Kepala Madrasah</label>
                            <input type="text" class="form-control" name="nama_kepala" 
                                   value="<?php echo htmlspecialchars($profil['nama_kepala'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIP Kepala Madrasah</label>
                            <input type="text" class="form-control" name="nip_kepala" 
                                   value="<?php echo htmlspecialchars($profil['nip_kepala'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12 mb-4">
                    <h6 class="border-bottom pb-2">D. Pengaturan Akademik</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun Ajaran Aktif</label>
                            <input type="text" class="form-control" name="tahun_ajaran_aktif" 
                                   value="<?php echo htmlspecialchars($profil['tahun_ajaran_aktif'] ?? ''); ?>" 
                                   placeholder="Contoh: 2024/2025">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Semester Aktif</label>
                            <select class="form-select" name="semester_aktif">
                                <option value="1" <?php echo ($profil['semester_aktif'] ?? '1') == '1' ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="2" <?php echo ($profil['semester_aktif'] ?? '1') == '2' ? 'selected' : ''; ?>>Semester 2</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12 mb-4">
                    <h6 class="border-bottom pb-2">E. Foto Login</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Foto/Gambar Login</label>
                                <input type="file" class="form-control" name="foto_login" accept="image/*">
                                <small class="text-muted">Foto yang ditampilkan di halaman login sebelah kiri</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Preview Foto Login</label><br>
                            <img src="../uploads/<?php echo htmlspecialchars($profil['foto_login'] ?? 'login-bg.jpg'); ?>" 
                                 alt="Foto Login" style="max-height: 200px; width: 100%; object-fit: cover; background: #f0f0f0; display: block;" 
                                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="display: none; max-height: 200px; width: 100%; background: #f0f0f0; border: 2px dashed #ccc; padding: 20px; text-align: center; color: #999;">
                                <i class="fas fa-image fa-3x mb-2"></i><br>
                                <small>Foto belum diupload</small>
                            </div>
                        </div>
                    </div>
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

