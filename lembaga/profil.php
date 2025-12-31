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
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_logo') {
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
        
        $stmt = $conn->prepare("UPDATE profil_madrasah SET logo=? WHERE id=?");
        $stmt->bind_param("si", $logo, $profil['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Logo berhasil diperbarui!';
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan path relatif yang benar untuk redirect
            $basePath = getRelativePath();
            header('Location: ' . $basePath . 'lembaga/profil.php');
            exit();
        } else {
            $error = 'Gagal memperbarui logo!';
        }
    } elseif ($action == 'update_info') {
        // Update info madrasah dan alamat
        $nama_madrasah = $_POST['nama_madrasah'] ?? '';
        $nsm = $_POST['nsm'] ?? '';
        $npsn = $_POST['npsn'] ?? '';
        $alamat = $_POST['alamat'] ?? '';
        $kecamatan = $_POST['kecamatan'] ?? '';
        $kabupaten = $_POST['kabupaten'] ?? '';
        $provinsi = $_POST['provinsi'] ?? '';
        
        $stmt = $conn->prepare("UPDATE profil_madrasah SET nama_madrasah=?, nsm=?, npsn=?, alamat=?, kecamatan=?, kabupaten=?, provinsi=? WHERE id=?");
        $stmt->bind_param("sssssssi", $nama_madrasah, $nsm, $npsn, $alamat, $kecamatan, $kabupaten, $provinsi, $profil['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Info madrasah berhasil diperbarui!';
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan path relatif yang benar untuk redirect
            $basePath = getRelativePath();
            header('Location: ' . $basePath . 'lembaga/profil.php');
            exit();
        } else {
            $error = 'Gagal memperbarui info madrasah!';
        }
    } elseif ($action == 'update_pimpinan') {
        // Update pimpinan
        $nama_kepala = $_POST['nama_kepala'] ?? '';
        $nip_kepala = $_POST['nip_kepala'] ?? '';
        
        $stmt = $conn->prepare("UPDATE profil_madrasah SET nama_kepala=?, nip_kepala=? WHERE id=?");
        $stmt->bind_param("ssi", $nama_kepala, $nip_kepala, $profil['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Data pimpinan berhasil diperbarui!';
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan path relatif yang benar untuk redirect
            $basePath = getRelativePath();
            header('Location: ' . $basePath . 'lembaga/profil.php');
            exit();
        } else {
            $error = 'Gagal memperbarui data pimpinan!';
        }
    } elseif ($action == 'update_akademik') {
        // Update akademik
        $tahun_ajaran = $_POST['tahun_ajaran_aktif'] ?? '';
        $semester = $_POST['semester_aktif'] ?? '1';
        
        $stmt = $conn->prepare("UPDATE profil_madrasah SET tahun_ajaran_aktif=?, semester_aktif=? WHERE id=?");
        $stmt->bind_param("ssi", $tahun_ajaran, $semester, $profil['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Pengaturan akademik berhasil diperbarui!';
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan path relatif yang benar untuk redirect
            $basePath = getRelativePath();
            header('Location: ' . $basePath . 'lembaga/profil.php');
            exit();
        } else {
            $error = 'Gagal memperbarui pengaturan akademik!';
        }
    }
}

// Ambil data terbaru
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$result = $conn->query($query);
$profil = $result->fetch_assoc();
?>
<?php include '../includes/header.php'; ?>

<?php if ($success): ?>
    <script>
        setTimeout(function() {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?php echo addslashes($success); ?>',
                confirmButtonColor: '#2d5016',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }, 100);
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <script>
        setTimeout(function() {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#2d5016',
                timer: 4000,
                timerProgressBar: true,
                showConfirmButton: true
            });
        }, 100);
    </script>
<?php endif; ?>

<div class="row">
    <!-- Box A: Pengaturan Logo -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header" style="background-color: #2d5016; color: white;">
                <h6 class="mb-0"><i class="fas fa-image"></i> A. Pengaturan Logo</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_logo">
                    <div class="mb-3">
                        <label class="form-label">Logo Madrasah</label>
                        <input type="file" class="form-control" name="logo" accept="image/*" id="logo">
                        <small class="text-muted">Format: JPG, PNG. Maksimal 2MB</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Preview Logo</label><br>
                        <div class="text-center">
                            <img src="../uploads/<?php echo htmlspecialchars($profil['logo'] ?? 'logo.png'); ?>" 
                                 alt="Logo" id="previewLogo" 
                                 style="max-height: 150px; max-width: 100%; background: #f0f0f0; padding: 10px; border-radius: 8px;" 
                                 onerror="this.onerror=null; this.style.display='none'; document.getElementById('placeholderLogo').style.display='block';">
                            <div id="placeholderLogo" style="display: none; max-height: 150px; background: #f0f0f0; border: 2px dashed #ccc; padding: 20px; text-align: center; color: #999; border-radius: 8px;">
                                <i class="fas fa-image fa-3x mb-2"></i><br>
                                <small>Logo belum diupload</small>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-save"></i> Simpan Logo
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Box B: Info Madrasah & Alamat (Read-only dengan tombol edit) -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #2d5016; color: white;">
                <h6 class="mb-0"><i class="fas fa-school"></i> B. Info Madrasah & Alamat</h6>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalInfo">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
            <div class="card-body">
                <div class="info-display">
                    <div class="mb-3">
                        <strong><i class="fas fa-school text-primary"></i> Nama Madrasah:</strong><br>
                        <span class="ms-4"><?php echo htmlspecialchars($profil['nama_madrasah'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-id-card text-info"></i> NSM:</strong><br>
                        <span class="ms-4"><?php echo htmlspecialchars($profil['nsm'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-id-badge text-success"></i> NPSN:</strong><br>
                        <span class="ms-4"><?php echo htmlspecialchars($profil['npsn'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-map-marker-alt text-danger"></i> Alamat:</strong><br>
                        <span class="ms-4"><?php echo nl2br(htmlspecialchars($profil['alamat'] ?? '-')); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-map text-warning"></i> Kecamatan:</strong><br>
                        <span class="ms-4"><?php echo htmlspecialchars($profil['kecamatan'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-city text-primary"></i> Kabupaten:</strong><br>
                        <span class="ms-4"><?php echo htmlspecialchars($profil['kabupaten'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-0">
                        <strong><i class="fas fa-globe text-info"></i> Provinsi:</strong><br>
                        <span class="ms-4"><?php echo htmlspecialchars($profil['provinsi'] ?? '-'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Box C: Pimpinan -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header" style="background-color: #2d5016; color: white;">
                <h6 class="mb-0"><i class="fas fa-user-tie"></i> C. Pimpinan</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_pimpinan">
                    <div class="mb-3">
                        <label class="form-label">Nama Kepala Madrasah</label>
                        <input type="text" class="form-control" name="nama_kepala" 
                               value="<?php echo htmlspecialchars($profil['nama_kepala'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIP Kepala Madrasah</label>
                        <input type="text" class="form-control" name="nip_kepala" 
                               value="<?php echo htmlspecialchars($profil['nip_kepala'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Box D: Pengaturan Akademik -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header" style="background-color: #2d5016; color: white;">
                <h6 class="mb-0"><i class="fas fa-calendar-alt"></i> D. Pengaturan Akademik</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_akademik">
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran Aktif</label>
                        <input type="text" class="form-control" name="tahun_ajaran_aktif" 
                               value="<?php echo htmlspecialchars($profil['tahun_ajaran_aktif'] ?? ''); ?>" 
                               placeholder="Contoh: 2024/2025">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester Aktif</label>
                        <select class="form-select" name="semester_aktif">
                            <option value="1" <?php echo ($profil['semester_aktif'] ?? '1') == '1' ? 'selected' : ''; ?>>Semester 1</option>
                            <option value="2" <?php echo ($profil['semester_aktif'] ?? '1') == '2' ? 'selected' : ''; ?>>Semester 2</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Info Madrasah & Alamat -->
<div class="modal fade" id="modalInfo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #2d5016; color: white;">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Info Madrasah & Alamat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formInfo">
                <input type="hidden" name="action" value="update_info">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Madrasah <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_madrasah" 
                               value="<?php echo htmlspecialchars($profil['nama_madrasah'] ?? ''); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NSM</label>
                            <input type="text" class="form-control" name="nsm" 
                                   value="<?php echo htmlspecialchars($profil['nsm'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NPSN</label>
                            <input type="text" class="form-control" name="npsn" 
                                   value="<?php echo htmlspecialchars($profil['npsn'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="alamat" rows="3"><?php echo htmlspecialchars($profil['alamat'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Preview logo saat file dipilih
    document.getElementById('logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('previewLogo');
                const placeholder = document.getElementById('placeholderLogo');
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<style>
.info-display {
    font-size: 0.95rem;
    line-height: 1.8;
}

.info-display strong {
    color: #2d5016;
    font-weight: 600;
}

.info-display .ms-4 {
    color: #555;
}
</style>

<?php include '../includes/footer.php'; ?>
