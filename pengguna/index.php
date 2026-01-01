<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();
$success = '';
$error = '';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nama = $_POST['nama'] ?? '';
            $email = $_POST['email'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = $_POST['tempat_lahir'] ?? '';
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $username = $_POST['username'] ?? '';
            $password = password_hash($_POST['password'] ?? '123456', PASSWORD_DEFAULT);
            $role = $_POST['role'] ?? 'guru';
            
            $foto = 'default.png';
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $foto = 'user_' . time() . '.' . $file_ext;
                move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
            }
            
            $stmt = $conn->prepare("INSERT INTO pengguna (nama, email, jenis_kelamin, tempat_lahir, tanggal_lahir, username, password, foto, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $nama, $email, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $username, $password, $foto, $role);
            
            if ($stmt->execute()) {
                $success = 'Pengguna berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan pengguna!';
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'] ?? 0;
            $nama = $_POST['nama'] ?? '';
            $email = $_POST['email'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = $_POST['tempat_lahir'] ?? '';
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $username = $_POST['username'] ?? '';
            $role = $_POST['role'] ?? 'guru';
            
            // Ambil foto lama
            try {
                $stmt_foto = $conn->prepare("SELECT foto FROM pengguna WHERE id = ?");
                $stmt_foto->bind_param("i", $id);
                $stmt_foto->execute();
                $result_foto = $stmt_foto->get_result();
                $foto_data = $result_foto->fetch_assoc();
                $foto = $foto_data ? $foto_data['foto'] : 'default.png';
            } catch (Exception $e) {
                $foto = 'default.png';
            }
            
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $upload_dir = '../uploads/';
                $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $foto = 'user_' . time() . '.' . $file_ext;
                move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
            }
            
            $password_update = '';
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $password_update = ", password='$password'";
            }
            
            $query = "UPDATE pengguna SET nama=?, email=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, username=?, foto=?, role=? $password_update WHERE id=?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssssi", $nama, $email, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $username, $foto, $role, $id);
            
            if ($stmt->execute()) {
                $success = 'Pengguna berhasil diperbarui!';
            } else {
                $error = 'Gagal memperbarui pengguna!';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Cek apakah proktor utama
            try {
                $stmt_check = $conn->prepare("SELECT is_proktor_utama FROM pengguna WHERE id = ?");
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $check = $result_check->fetch_assoc();
            } catch (Exception $e) {
                $check = ['is_proktor_utama' => 0];
            }
            
            if ($check['is_proktor_utama']) {
                $error = 'Akun proktor utama tidak dapat dihapus!';
            } else {
                $stmt = $conn->prepare("DELETE FROM pengguna WHERE id=?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $success = 'Pengguna berhasil dihapus!';
                } else {
                    $error = 'Gagal menghapus pengguna!';
                }
            }
        }
    }
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM pengguna WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil semua data pengguna
$result = null;
try {
    $query = "SELECT * FROM pengguna ORDER BY nama";
    $result = $conn->query($query);
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
        $result = null;
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
}

// Set page title (variabel lokal)
$page_title = 'Pengguna';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-users"></i> Pengguna</h5>
        <div>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalPengguna">
                <i class="fas fa-plus"></i> Tambah
            </button>
        </div>
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
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tablePengguna">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>L/P</th>
                        <th>TTL</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th width="150">Aksi</th>
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
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                            <td><?php echo $row['jenis_kelamin'] == 'L' ? 'L' : 'P'; ?></td>
                            <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?>, <?php echo $row['tanggal_lahir'] ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="resetPassword(<?php echo $row['id']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editPengguna(<?php echo $row['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (!$row['is_proktor_utama']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deletePengguna(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Belum ada data pengguna</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalPengguna" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPengguna" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" id="nama" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select class="form-select" name="jenis_kelamin" id="jenisKelamin" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" name="tempat_lahir" id="tempatLahir">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" name="tanggal_lahir" id="tanggalLahir">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="passwordRequired">*</span></label>
                            <input type="password" class="form-control" name="password" id="password">
                            <small class="text-muted" id="passwordHint">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="proktor">Proktor</option>
                                <option value="wali_kelas">Wali Kelas</option>
                                <option value="guru">Guru</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Foto</label>
                            <input type="file" class="form-control" name="foto" id="foto" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tablePengguna').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
    
    function editPengguna(id) {
        window.location.href = 'index.php?edit=' + id;
    }
    
    function deletePengguna(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                                '<input type="hidden" name="id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    function resetPassword(id) {
        Swal.fire({
            title: 'Reset Password',
            text: 'Password akan direset menjadi "123456"',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Reset',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'reset_password.php',
                    type: 'POST',
                    data: {id: id},
                    success: function(response) {
                        var result = JSON.parse(response);
                        if (result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: 'Password berhasil direset menjadi "123456"',
                                confirmButtonColor: '#2d5016',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: 'Gagal mereset password!',
                                confirmButtonColor: '#2d5016',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: true
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat mereset password!',
                            confirmButtonColor: '#2d5016',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: true
                        });
                    }
                });
            }
        });
    }
    
    function exportExcel() {
        window.location.href = 'export_pengguna.php?format=excel';
    }
    
    function exportPDF() {
        window.location.href = 'export_pengguna.php?format=pdf';
    }
    
    // Reset form saat modal ditutup
    $('#modalPengguna').on('hidden.bs.modal', function() {
        $('#formPengguna')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Pengguna');
        $('#passwordRequired').show();
        $('#passwordHint').hide();
    });
    
    // Load data untuk edit
    <?php if ($edit_data): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#nama').val('<?php echo addslashes($edit_data['nama']); ?>');
        $('#email').val('<?php echo addslashes($edit_data['email'] ?? ''); ?>');
        $('#jenisKelamin').val('<?php echo $edit_data['jenis_kelamin']; ?>');
        $('#tempatLahir').val('<?php echo addslashes($edit_data['tempat_lahir'] ?? ''); ?>');
        $('#tanggalLahir').val('<?php echo $edit_data['tanggal_lahir'] ?? ''; ?>');
        $('#username').val('<?php echo addslashes($edit_data['username']); ?>');
        $('#role').val('<?php echo $edit_data['role']; ?>');
        $('#modalTitle').text('Edit Pengguna');
        $('#passwordRequired').hide();
        $('#passwordHint').show();
        $('#password').removeAttr('required');
        $('#modalPengguna').modal('show');
    });
    <?php endif; ?>
    
    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: '<?php echo addslashes($success); ?>',
        confirmButtonColor: '#2d5016',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
    }).then(() => {
        window.location.href = 'index.php';
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

