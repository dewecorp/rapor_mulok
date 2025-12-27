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
            $nama = trim($_POST['nama'] ?? '');
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
            $pendidikan = trim($_POST['pendidikan'] ?? '');
            $nuptk = trim($_POST['nuptk'] ?? '');
            $password = password_hash($_POST['password'] ?? '123456', PASSWORD_DEFAULT);
            $role = $_POST['role'] ?? 'guru';
            
            // Validasi input
            if (empty($nama)) {
                $error = 'Nama tidak boleh kosong!';
            } elseif (empty($nuptk)) {
                $error = 'NUPTK tidak boleh kosong!';
            } else {
                $foto = 'default.png';
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                    $upload_dir = '../uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                    $foto = 'guru_' . time() . '.' . $file_ext;
                    move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
                }
                
                try {
                    // Cek apakah kolom NUPTK dan pendidikan ada, jika tidak tambahkan
                    $check_columns = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'nuptk'");
                    if ($check_columns->num_rows == 0) {
                        $conn->query("ALTER TABLE pengguna ADD COLUMN nuptk VARCHAR(50) UNIQUE AFTER username");
                    }
                    $check_pendidikan = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'pendidikan'");
                    if ($check_pendidikan->num_rows == 0) {
                        $conn->query("ALTER TABLE pengguna ADD COLUMN pendidikan VARCHAR(100) AFTER tanggal_lahir");
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO pengguna (nama, jenis_kelamin, tempat_lahir, tanggal_lahir, pendidikan, nuptk, password, foto, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $tempat_lahir_null = empty($tempat_lahir) ? null : $tempat_lahir;
                    $tanggal_lahir_null = empty($tanggal_lahir) ? null : $tanggal_lahir;
                    $pendidikan_null = empty($pendidikan) ? null : $pendidikan;
                    $stmt->bind_param("sssssssss", $nama, $jenis_kelamin, $tempat_lahir_null, $tanggal_lahir_null, $pendidikan_null, $nuptk, $password, $foto, $role);
                    
                    if ($stmt->execute()) {
                        $success = 'Data guru berhasil ditambahkan!';
                    } else {
                        $error_code = $stmt->errno;
                        $error_msg = $stmt->error;
                        
                        // Cek apakah error karena duplikasi
                        if ($error_code == 1062 || strpos($error_msg, 'Duplicate entry') !== false) {
                            if (strpos($error_msg, 'nuptk') !== false) {
                                $error = 'NUPTK sudah digunakan! Silakan gunakan NUPTK lain.';
                            } elseif (strpos($error_msg, 'username') !== false) {
                                $error = 'Username sudah digunakan! Silakan gunakan username lain.';
                            } else {
                                $error = 'Data sudah ada di sistem!';
                            }
                        } else {
                            $error = 'Gagal menambahkan data guru! Error: ' . $error_msg;
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    // Cek apakah error karena duplikasi
                    if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        if (strpos($e->getMessage(), 'nuptk') !== false) {
                            $error = 'NUPTK sudah digunakan! Silakan gunakan NUPTK lain.';
                        } elseif (strpos($e->getMessage(), 'username') !== false) {
                            $error = 'Username sudah digunakan! Silakan gunakan username lain.';
                        } else {
                            $error = 'Data sudah ada di sistem!';
                        }
                    } else {
                        $error = 'Gagal menambahkan data guru! Error: ' . $e->getMessage();
                    }
                } catch (Exception $e) {
                    $error = 'Gagal menambahkan data guru! Error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
            $pendidikan = trim($_POST['pendidikan'] ?? '');
            $nuptk = trim($_POST['nuptk'] ?? '');
            $role = $_POST['role'] ?? 'guru';
            
            // Validasi input
            if (empty($nama)) {
                $error = 'Nama tidak boleh kosong!';
            } elseif (empty($nuptk)) {
                $error = 'NUPTK tidak boleh kosong!';
            } elseif ($id <= 0) {
                $error = 'ID tidak valid!';
            } else {
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
                    $foto = 'guru_' . time() . '.' . $file_ext;
                    move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
                }
                
                try {
                    // Cek apakah kolom NUPTK dan pendidikan ada, jika tidak tambahkan
                    $check_columns = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'nuptk'");
                    if ($check_columns->num_rows == 0) {
                        $conn->query("ALTER TABLE pengguna ADD COLUMN nuptk VARCHAR(50) UNIQUE AFTER username");
                    }
                    $check_pendidikan = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'pendidikan'");
                    if ($check_pendidikan->num_rows == 0) {
                        $conn->query("ALTER TABLE pengguna ADD COLUMN pendidikan VARCHAR(100) AFTER tanggal_lahir");
                    }
                    
                    $tempat_lahir_null = empty($tempat_lahir) ? null : $tempat_lahir;
                    $tanggal_lahir_null = empty($tanggal_lahir) ? null : $tanggal_lahir;
                    $pendidikan_null = empty($pendidikan) ? null : $pendidikan;
                    
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?, nuptk=?, password=?, foto=?, role=? WHERE id=?");
                        $stmt->bind_param("sssssssssi", $nama, $jenis_kelamin, $tempat_lahir_null, $tanggal_lahir_null, $pendidikan_null, $nuptk, $password, $foto, $role, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?, nuptk=?, foto=?, role=? WHERE id=?");
                        $stmt->bind_param("ssssssssi", $nama, $jenis_kelamin, $tempat_lahir_null, $tanggal_lahir_null, $pendidikan_null, $nuptk, $foto, $role, $id);
                    }
                    
                    if ($stmt->execute()) {
                        $success = 'Data guru berhasil diperbarui!';
                    } else {
                        $error_code = $stmt->errno;
                        $error_msg = $stmt->error;
                        
                        // Cek apakah error karena duplikasi
                        if ($error_code == 1062 || strpos($error_msg, 'Duplicate entry') !== false) {
                            if (strpos($error_msg, 'nuptk') !== false) {
                                $error = 'NUPTK sudah digunakan! Silakan gunakan NUPTK lain.';
                            } elseif (strpos($error_msg, 'username') !== false) {
                                $error = 'Username sudah digunakan! Silakan gunakan username lain.';
                            } else {
                                $error = 'Data sudah ada di sistem!';
                            }
                        } else {
                            $error = 'Gagal memperbarui data guru! Error: ' . $error_msg;
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    // Cek apakah error karena duplikasi
                    if ($e->getCode() == 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        if (strpos($e->getMessage(), 'nuptk') !== false) {
                            $error = 'NUPTK sudah digunakan! Silakan gunakan NUPTK lain.';
                        } elseif (strpos($e->getMessage(), 'username') !== false) {
                            $error = 'Username sudah digunakan! Silakan gunakan username lain.';
                        } else {
                            $error = 'Data sudah ada di sistem!';
                        }
                    } else {
                        $error = 'Gagal memperbarui data guru! Error: ' . $e->getMessage();
                    }
                } catch (Exception $e) {
                    $error = 'Gagal memperbarui data guru! Error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $stmt = $conn->prepare("DELETE FROM pengguna WHERE id=? AND is_proktor_utama=0");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = 'Data guru berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus data guru!';
            }
        }
    }
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM pengguna WHERE id = ? AND role IN ('guru', 'wali_kelas')");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil semua data guru
$result = null;
try {
    $query = "SELECT p.*, 
              (SELECT nama_kelas FROM kelas WHERE wali_kelas_id = p.id LIMIT 1) as wali_kelas_nama
              FROM pengguna p 
              WHERE p.role IN ('guru', 'wali_kelas')
              ORDER BY p.nama";
    $result = $conn->query($query);
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
        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Data Guru</h5>
        <div>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalGuru">
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
            <table class="table table-bordered table-striped" id="tableGuru">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th width="80">Foto</th>
                        <th>Nama</th>
                        <th>Jenis Kelamin</th>
                        <th>Tempat, Tgl Lahir</th>
                        <th>Pendidikan</th>
                        <th>NUPTK</th>
                        <th>Wali Kelas</th>
                        <th>Password</th>
                        <th width="200">Aksi</th>
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
                            <td>
                                <img src="../uploads/<?php echo htmlspecialchars($row['foto'] ?? 'default.png'); ?>" 
                                     alt="Foto" class="rounded-circle" width="50" height="50" 
                                     style="object-fit: cover;" onerror="this.onerror=null; this.style.display='none';">
                            </td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                            <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?>, <?php echo $row['tanggal_lahir'] ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($row['pendidikan'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['nuptk'] ?? $row['username'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['wali_kelas_nama'] ?? '-'); ?></td>
                            <td>
                                <small class="text-muted" style="font-family: monospace; font-size: 0.75em; word-break: break-all;">
                                    <?php 
                                    $password = $row['password'] ?? '';
                                    if (!empty($password)) {
                                        // Tampilkan 30 karakter pertama dari hash untuk pengecekan
                                        echo htmlspecialchars(substr($password, 0, 30)) . '...';
                                    } else {
                                        echo '<span class="text-danger">-</span>';
                                    }
                                    ?>
                                </small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="resetPassword(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-key"></i> Reset
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editGuru(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (!$row['is_proktor_utama']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteGuru(<?php echo $row['id']; ?>)">
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
                            <td colspan="9" class="text-center text-muted">Belum ada data guru</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalGuru" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Data Guru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formGuru" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" id="nama" required>
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
                            <label class="form-label">Pendidikan</label>
                            <input type="text" class="form-control" name="pendidikan" id="pendidikan" placeholder="Contoh: S1, S2, dll">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NUPTK <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nuptk" id="nuptk" required>
                            <small class="text-muted">NUPTK digunakan untuk login</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger" id="passwordRequired">*</span></label>
                            <input type="text" class="form-control" name="password" id="password" placeholder="Default: 123456">
                            <small class="text-muted" id="passwordHint">Kosongkan jika tidak ingin mengubah password (untuk edit)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="role" required>
                                <option value="guru">Guru</option>
                                <option value="wali_kelas">Wali Kelas</option>
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
        $('#tableGuru').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
    
    function editGuru(id) {
        window.location.href = 'data.php?edit=' + id;
    }
    
    function deleteGuru(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
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
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Password berhasil direset menjadi "123456"',
                            confirmButtonColor: '#2d5016',
                            timer: 3000,
                            timerProgressBar: true,
                            showConfirmButton: false
                        });
                    }
                });
            }
        });
    }
    
    function exportExcel() {
        window.location.href = 'export_guru.php?format=excel';
    }
    
    function exportPDF() {
        window.location.href = 'export_guru.php?format=pdf';
    }
    
    // Reset form saat modal ditutup
    $('#modalGuru').on('hidden.bs.modal', function() {
        $('#formGuru')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Data Guru');
        $('#passwordRequired').show();
        $('#passwordHint').hide();
    });
    
    // Load data untuk edit
    <?php if ($edit_data): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#nama').val('<?php echo addslashes($edit_data['nama']); ?>');
        $('#jenisKelamin').val('<?php echo $edit_data['jenis_kelamin']; ?>');
        $('#tempatLahir').val('<?php echo addslashes($edit_data['tempat_lahir'] ?? ''); ?>');
        $('#tanggalLahir').val('<?php echo $edit_data['tanggal_lahir'] ?? ''; ?>');
        $('#pendidikan').val('<?php echo addslashes($edit_data['pendidikan'] ?? ''); ?>');
        $('#nuptk').val('<?php echo addslashes($edit_data['nuptk'] ?? $edit_data['username'] ?? ''); ?>');
        $('#role').val('<?php echo $edit_data['role']; ?>');
        $('#modalTitle').text('Edit Data Guru');
        $('#passwordRequired').hide();
        $('#passwordHint').show();
        $('#password').removeAttr('required');
        $('#modalGuru').modal('show');
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
        window.location.href = 'data.php';
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

