<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Buat tabel jika belum ada
try {
    // Tabel pengaturan_aplikasi
    $conn->query("CREATE TABLE IF NOT EXISTS `pengaturan_aplikasi` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `info_aplikasi` text DEFAULT NULL,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert default jika belum ada
    $check = $conn->query("SELECT COUNT(*) as total FROM pengaturan_aplikasi");
    if ($check && $check->fetch_assoc()['total'] == 0) {
        $conn->query("INSERT INTO pengaturan_aplikasi (info_aplikasi) VALUES ('Selamat datang di aplikasi Rapor Mulok Digital. Aplikasi ini digunakan untuk mengelola rapor mata pelajaran muatan lokal.')");
    }
} catch (Exception $e) {
    $error = 'Error membuat tabel: ' . $e->getMessage();
}

// Ambil data pengaturan
$pengaturan = null;
try {
    $result = $conn->query("SELECT * FROM pengaturan_aplikasi LIMIT 1");
    $pengaturan = $result ? $result->fetch_assoc() : null;
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

// Ambil data profil untuk foto login
$profil = null;
try {
    $result_profil = $conn->query("SELECT * FROM profil_madrasah LIMIT 1");
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    // Tabel belum ada atau error
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if ($action == 'update_info') {
        // Handle update info aplikasi
        $info_aplikasi = trim($_POST['info_aplikasi'] ?? '');
        
        // Validasi: hapus tag HTML kosong dan whitespace
        $info_aplikasi_clean = strip_tags($info_aplikasi);
        $info_aplikasi_clean = trim($info_aplikasi_clean);
        
        if (empty($info_aplikasi_clean)) {
            $error = 'Info aplikasi tidak boleh kosong!';
        } else {
            try {
                if ($pengaturan && isset($pengaturan['id'])) {
                    $stmt = $conn->prepare("UPDATE pengaturan_aplikasi SET info_aplikasi=?, updated_by=? WHERE id=?");
                    $stmt->bind_param("sii", $info_aplikasi, $user_id, $pengaturan['id']);
                } else {
                    $stmt = $conn->prepare("INSERT INTO pengaturan_aplikasi (info_aplikasi, updated_by) VALUES (?, ?)");
                    $stmt->bind_param("si", $info_aplikasi, $user_id);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Info aplikasi berhasil diperbarui!';
                    // Pastikan tidak ada output sebelum redirect
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    // Gunakan path absolut ke root untuk menghindari masalah redirect di subdirektori
                    $redirect_url = '/pengaturan/index.php';
                    if (isset($_SERVER['HTTP_HOST'])) {
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                        header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $redirect_url);
                    } else {
                        header('Location: ' . $redirect_url);
                    }
                    exit();
                } else {
                    $error = 'Gagal memperbarui info aplikasi!';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'update_ttd_kepala') {
        // Handle update tanda tangan kepala
        $jenis_ttd = $_POST['jenis_ttd'] ?? 'none';
        $allowed_jenis = ['none', 'image', 'qrcode'];
        
        if (!in_array($jenis_ttd, $allowed_jenis)) {
            $jenis_ttd = 'none';
        }
        
        // Update jenis ttd dulu
        try {
            $result_id = $conn->query("SELECT id FROM profil_madrasah LIMIT 1");
            if ($result_id && $result_id->num_rows > 0) {
                $profil_id = $result_id->fetch_assoc()['id'];
                $stmt_jenis = $conn->prepare("UPDATE profil_madrasah SET jenis_ttd=? WHERE id=?");
                $stmt_jenis->bind_param("si", $jenis_ttd, $profil_id);
                $stmt_jenis->execute();
            }
        } catch (Exception $e) {
            $error = 'Error update jenis TTD: ' . $e->getMessage();
        }

        // Handle file upload jika ada
        if (isset($_FILES['ttd_kepala']) && $_FILES['ttd_kepala']['error'] == 0) {
            // Validasi file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $file_type = $_FILES['ttd_kepala']['type'];
            $file_size = $_FILES['ttd_kepala']['size'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Format file tidak didukung! Gunakan format JPG atau PNG (background transparan disarankan).';
            } elseif ($file_size > $max_size) {
                $error = 'Ukuran file terlalu besar! Maksimal 2MB.';
            } else {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Ambil ttd lama
                $ttd_lama = $profil['ttd_kepala'] ?? null;
                
                // Generate nama file baru
                $file_ext = pathinfo($_FILES['ttd_kepala']['name'], PATHINFO_EXTENSION);
                $ttd_kepala = 'ttd_kepala_' . time() . '.' . $file_ext;
                
                // Upload file baru
                if (move_uploaded_file($_FILES['ttd_kepala']['tmp_name'], $upload_dir . $ttd_kepala)) {
                    try {
                        if (isset($profil_id)) {
                            $stmt_foto = $conn->prepare("UPDATE profil_madrasah SET ttd_kepala=? WHERE id=?");
                            $stmt_foto->bind_param("si", $ttd_kepala, $profil_id);
                            
                            if ($stmt_foto->execute()) {
                                // Hapus file lama jika ada
                                if ($ttd_lama && file_exists($upload_dir . $ttd_lama)) {
                                    @unlink($upload_dir . $ttd_lama);
                                }
                                $_SESSION['success_message'] = 'Tanda tangan berhasil diperbarui!';
                            } else {
                                @unlink($upload_dir . $ttd_kepala);
                                $error = 'Gagal update database!';
                            }
                        }
                    } catch (Exception $e) {
                        @unlink($upload_dir . $ttd_kepala);
                        $error = 'Error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Gagal mengupload file!';
                }
            }
        } else {
            if (empty($error)) {
                $_SESSION['success_message'] = 'Pengaturan tanda tangan disimpan!';
            }
        }
        
        if (empty($error)) {
            // Redirect
             if (ob_get_level() > 0) ob_clean();
            $redirect_url = '/pengaturan/index.php';
            if (isset($_SERVER['HTTP_HOST'])) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $redirect_url);
            } else {
                header('Location: ' . $redirect_url);
            }
            exit();
        }
    } elseif ($action == 'update_foto_login') {
        // Handle update foto login
        // Jika ada file baru yang diupload, update foto login
        if (isset($_FILES['foto_login']) && $_FILES['foto_login']['error'] == 0) {
            // Validasi file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['foto_login']['type'];
            $file_size = $_FILES['foto_login']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_type, $allowed_types)) {
                $error = 'Format file tidak didukung! Gunakan format JPG, PNG, atau GIF.';
            } elseif ($file_size > $max_size) {
                $error = 'Ukuran file terlalu besar! Maksimal 5MB.';
            } else {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Ambil foto lama untuk dihapus jika ada
                $foto_lama = $profil['foto_login'] ?? null;
                
                // Generate nama file baru
                $file_ext = pathinfo($_FILES['foto_login']['name'], PATHINFO_EXTENSION);
                $foto_login = 'login_' . time() . '.' . $file_ext;
                
                // Upload file baru
                if (move_uploaded_file($_FILES['foto_login']['tmp_name'], $upload_dir . $foto_login)) {
                    // Update foto login di tabel profil_madrasah
                    try {
                        // Ambil ID profil terlebih dahulu
                        $result_id = $conn->query("SELECT id FROM profil_madrasah LIMIT 1");
                        if ($result_id && $result_id->num_rows > 0) {
                            $profil_id = $result_id->fetch_assoc()['id'];
                            $stmt_foto = $conn->prepare("UPDATE profil_madrasah SET foto_login=? WHERE id=?");
                            $stmt_foto->bind_param("si", $foto_login, $profil_id);
                            
                            if ($stmt_foto->execute()) {
                                // Hapus foto lama jika ada dan bukan default
                                if ($foto_lama && $foto_lama != 'login-bg.jpg' && file_exists($upload_dir . $foto_lama)) {
                                    @unlink($upload_dir . $foto_lama);
                                }
                                
                                $_SESSION['success_message'] = 'Foto login berhasil diperbarui!';
                                // Pastikan tidak ada output sebelum redirect
                                if (ob_get_level() > 0) {
                                    ob_clean();
                                }
                                // Gunakan path absolut ke root untuk menghindari masalah redirect di subdirektori
                                $redirect_url = '/pengaturan/index.php';
                                if (isset($_SERVER['HTTP_HOST'])) {
                                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                                    header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $redirect_url);
                                } else {
                                    header('Location: ' . $redirect_url);
                                }
                                exit();
                            } else {
                                // Hapus file yang sudah diupload jika update gagal
                                @unlink($upload_dir . $foto_login);
                                $error = 'Gagal memperbarui foto login di database!';
                            }
                        } else {
                            // Hapus file yang sudah diupload jika tidak ada profil
                            @unlink($upload_dir . $foto_login);
                            $error = 'Profil madrasah tidak ditemukan!';
                        }
                    } catch (Exception $e) {
                        // Hapus file yang sudah diupload jika error
                        @unlink($upload_dir . $foto_login);
                        $error = 'Error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Gagal mengupload foto login!';
                }
            }
        } else {
            // Tidak ada file yang diupload, tetap gunakan foto lama (tidak error)
            $_SESSION['success_message'] = 'Tidak ada perubahan pada foto login.';
            // Pastikan tidak ada output sebelum redirect
            if (ob_get_level() > 0) {
                ob_clean();
            }
            // Gunakan path absolut ke root untuk menghindari masalah redirect di subdirektori
            $redirect_url = '/pengaturan/index.php';
            if (isset($_SERVER['HTTP_HOST'])) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $_SERVER['HTTP_HOST'] . $redirect_url);
            } else {
                header('Location: ' . $redirect_url);
            }
            exit();
        }
    }
}

// Ambil data terbaru
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    $result = $conn->query("SELECT * FROM pengaturan_aplikasi LIMIT 1");
    $pengaturan = $result ? $result->fetch_assoc() : null;
} catch (Exception $e) {
    $pengaturan = null;
}

// Ambil data profil terbaru untuk foto login
try {
    $result_profil = $conn->query("SELECT * FROM profil_madrasah LIMIT 1");
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
}

// Set page title (variabel lokal)
$page_title = 'Pengaturan';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header" style="background-color: #2d5016; color: white;">
        <h5 class="mb-0"><i class="fas fa-cog"></i> Pengaturan Aplikasi</h5>
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
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: '<?php echo addslashes($error); ?>',
                        confirmButtonColor: '#2d5016',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: true
                    });
                });
            </script>
        <?php endif; ?>
        
        <div class="row">
            <!-- Box Info Aplikasi -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #2d5016; color: white;">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Info Aplikasi</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formInfoAplikasi" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_info">
                            <div class="mb-3">
                                <label class="form-label">Info Aplikasi <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="info_aplikasi" id="info_aplikasi" rows="8" required><?php echo htmlspecialchars($pengaturan['info_aplikasi'] ?? ''); ?></textarea>
                                <small class="text-muted">Info ini akan ditampilkan di dashboard. Gunakan editor untuk memformat teks.</small>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if ($pengaturan && isset($pengaturan['updated_at'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> Terakhir diperbarui: 
                                            <?php echo date('d/m/Y H:i:s', strtotime($pengaturan['updated_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Simpan Info Aplikasi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Box Foto Login -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #2d5016; color: white;">
                        <h6 class="mb-0"><i class="fas fa-image"></i> Foto Login</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formFotoLogin" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_foto_login">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Foto/Gambar Login</label>
                                        <input type="file" class="form-control" name="foto_login" accept="image/*" id="foto_login">
                                        <small class="text-muted">Foto yang ditampilkan di halaman login sebelah kiri. Format: JPG, PNG. Maksimal 5MB</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preview Foto Login</label><br>
                                    <img src="../uploads/<?php echo htmlspecialchars($profil['foto_login'] ?? 'login-bg.jpg'); ?>" 
                                         alt="Foto Login" id="previewFotoLogin" 
                                         style="max-height: 250px; width: 100%; object-fit: cover; background: #f0f0f0; border-radius: 8px; display: block;" 
                                         onerror="this.onerror=null; this.style.display='none'; document.getElementById('placeholderFotoLogin').style.display='block';">
                                    <div id="placeholderFotoLogin" style="display: none; max-height: 250px; width: 100%; background: #f0f0f0; border: 2px dashed #ccc; padding: 40px; text-align: center; color: #999; border-radius: 8px;">
                                        <i class="fas fa-image fa-4x mb-2"></i><br>
                                        <small>Foto belum diupload</small>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Simpan Foto Login
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Box Tanda Tangan -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header" style="background-color: #2d5016; color: white;">
                        <h6 class="mb-0"><i class="fas fa-signature"></i> Tanda Tangan Kepala Madrasah</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formTtd" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_ttd_kepala">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tampilkan Sebagai</label>
                                        <select class="form-select" name="jenis_ttd" id="jenis_ttd">
                                            <option value="none" <?php echo ($profil['jenis_ttd'] ?? '') == 'none' ? 'selected' : ''; ?>>Tidak Ditampilkan</option>
                                            <option value="image" <?php echo ($profil['jenis_ttd'] ?? '') == 'image' ? 'selected' : ''; ?>>Gambar Tanda Tangan</option>
                                            <option value="qrcode" <?php echo ($profil['jenis_ttd'] ?? '') == 'qrcode' ? 'selected' : ''; ?>>QR Code</option>
                                        </select>
                                        <small class="text-muted">Pilih jenis tanda tangan yang akan ditampilkan di rapor.</small>
                                    </div>
                                    <div class="mb-3" id="upload_ttd_container" style="<?php echo ($profil['jenis_ttd'] ?? '') == 'image' ? '' : 'display:none;'; ?>">
                                        <label class="form-label">Upload Gambar Tanda Tangan</label>
                                        <input type="file" class="form-control" name="ttd_kepala" accept="image/png, image/jpeg" id="ttd_kepala">
                                        <small class="text-muted">Format: PNG (transparan) atau JPG. Maksimal 2MB. Disarankan rasio 3:1.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preview</label><br>
                                    
                                    <!-- Preview Image -->
                                    <div id="previewTtdImage" style="<?php echo ($profil['jenis_ttd'] ?? '') == 'image' ? '' : 'display:none;'; ?>">
                                        <img src="../uploads/<?php echo htmlspecialchars($profil['ttd_kepala'] ?? ''); ?>" 
                                             alt="Tanda Tangan" id="imgTtd" 
                                             style="max-height: 100px; max-width: 100%; border: 1px solid #ddd; padding: 5px; display: block;" 
                                             onerror="this.style.display='none'; document.getElementById('placeholderTtd').style.display='block';">
                                        <div id="placeholderTtd" style="display: <?php echo !empty($profil['ttd_kepala'] ?? '') ? 'none' : 'block'; ?>; padding: 20px; border: 1px dashed #ccc; text-align: center; color: #999;">
                                            Belum ada tanda tangan
                                        </div>
                                    </div>

                                    <!-- Preview QR -->
                                    <div id="previewTtdQr" style="<?php echo ($profil['jenis_ttd'] ?? '') == 'qrcode' ? '' : 'display:none;'; ?>">
                                        <div style="padding: 20px; border: 1px dashed #ccc; text-align: center; color: #666;">
                                            <i class="fas fa-qrcode fa-3x mb-2"></i><br>
                                            QR Code akan digenerate otomatis saat cetak rapor<br>
                                            berisi data: "Ditandatangani secara elektronik oleh: [Nama Kepala]"
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Simpan Pengaturan Tanda Tangan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>

<script>
    // Inisialisasi CKEditor
    ClassicEditor
        .create(document.querySelector('#info_aplikasi'), {
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'underline', 'strikethrough', '|',
                    'fontSize', 'fontColor', 'fontBackgroundColor', '|',
                    'bulletedList', 'numberedList', '|',
                    'alignment', '|',
                    'link', 'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ],
                shouldNotGroupWhenFull: true
            },
            fontSize: {
                options: [9, 11, 13, 'default', 17, 19, 21, 24, 28, 32, 36]
            },
            heading: {
                options: [
                    { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                    { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                    { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                    { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
                ]
            },
            language: 'id',
            placeholder: 'Masukkan info aplikasi di sini...'
        })
        .then(editor => {
            window.editor = editor;
            
            // Update form saat submit dengan validasi
            document.getElementById('formInfoAplikasi').addEventListener('submit', function(e) {
                const data = editor.getData();
                // Validasi: hapus tag HTML dan cek apakah kosong
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                const textContent = tempDiv.textContent || tempDiv.innerText || '';
                const cleanText = textContent.trim();
                
                if (cleanText === '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Info aplikasi tidak boleh kosong!',
                        confirmButtonColor: '#2d5016'
                    });
                    return false;
                }
                
                document.getElementById('info_aplikasi').value = data;
            });
        })
        .catch(error => {
            console.error('Error initializing CKEditor:', error);
        });
    
    // Preview foto login saat file dipilih
    document.getElementById('foto_login').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validasi ukuran file (5MB)
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Ukuran file terlalu besar! Maksimal 5MB.',
                    confirmButtonColor: '#2d5016'
                });
                this.value = '';
                return;
            }
            
            // Validasi tipe file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Format file tidak didukung! Gunakan format JPG, PNG, atau GIF.',
                    confirmButtonColor: '#2d5016'
                });
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('previewFotoLogin');
                const placeholder = document.getElementById('placeholderFotoLogin');
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    });

    // Toggle jenis ttd
    const jenisTtdSelect = document.getElementById('jenis_ttd');
    if (jenisTtdSelect) {
        jenisTtdSelect.addEventListener('change', function() {
            const val = this.value;
            const uploadContainer = document.getElementById('upload_ttd_container');
            const previewImage = document.getElementById('previewTtdImage');
            const previewQr = document.getElementById('previewTtdQr');
            
            if (val === 'image') {
                uploadContainer.style.display = 'block';
                previewImage.style.display = 'block';
                previewQr.style.display = 'none';
            } else if (val === 'qrcode') {
                uploadContainer.style.display = 'none';
                previewImage.style.display = 'none';
                previewQr.style.display = 'block';
            } else {
                uploadContainer.style.display = 'none';
                previewImage.style.display = 'none';
                previewQr.style.display = 'none';
            }
        });
    }

    // Preview image ttd
    const ttdInput = document.getElementById('ttd_kepala');
    if (ttdInput) {
        ttdInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validasi ukuran
                if (file.size > 2 * 1024 * 1024) {
                     Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Ukuran file terlalu besar! Maksimal 2MB.',
                        confirmButtonColor: '#2d5016'
                    });
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('imgTtd');
                    const placeholder = document.getElementById('placeholderTtd');
                    img.src = e.target.result;
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
    }

    
</script>

<style>
    .ck-editor__editable {
        min-height: 300px;
        font-size: 16px;
    }
    
    .ck-content {
        font-size: 16px;
        line-height: 1.6;
    }
    
    .ck-content p {
        font-size: 16px;
    }
    
    .ck-content h1 {
        font-size: 2.5em;
    }
    
    .ck-content h2 {
        font-size: 2em;
    }
    
    .ck-content h3 {
        font-size: 1.75em;
    }
    
    .ck-content h4 {
        font-size: 1.5em;
    }
</style>

<?php include '../includes/footer.php'; ?>
