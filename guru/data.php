<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();
$success = '';
$error = '';

// Ambil pesan dari session (setelah redirect)
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

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
                    // Cek dan tambahkan kolom password_plain untuk menyimpan password yang bisa dilihat admin
                    $check_password_plain = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'password_plain'");
                    if ($check_password_plain->num_rows == 0) {
                        $conn->query("ALTER TABLE pengguna ADD COLUMN password_plain VARCHAR(255) NULL AFTER password");
                    }
                    
                    // Username untuk guru/wali_kelas/proktor diisi dengan NUPTK
                    // Untuk guru: username = NUPTK (untuk login menggunakan NUPTK)
                    $username = $nuptk; // Username sama dengan NUPTK
                    $password_plain = $_POST['password'] ?? '123456'; // Simpan password plain text untuk admin
                    
                    $stmt = $conn->prepare("INSERT INTO pengguna (nama, jenis_kelamin, tempat_lahir, tanggal_lahir, pendidikan, username, nuptk, password, password_plain, foto, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $tempat_lahir_null = empty($tempat_lahir) ? null : $tempat_lahir;
                    $tanggal_lahir_null = empty($tanggal_lahir) ? null : $tanggal_lahir;
                    $pendidikan_null = empty($pendidikan) ? null : $pendidikan;
                    $stmt->bind_param("sssssssssss", $nama, $jenis_kelamin, $tempat_lahir_null, $tanggal_lahir_null, $pendidikan_null, $username, $nuptk, $password, $password_plain, $foto, $role);
                    
                    if ($stmt->execute()) {
                        // Redirect untuk mencegah resubmit dan refresh data
                        $_SESSION['success_message'] = 'Data guru berhasil ditambahkan!';
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        header('Location: data.php');
                        exit();
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
                    
                    // Cek dan tambahkan kolom password_plain jika belum ada
                    $check_password_plain = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'password_plain'");
                    if ($check_password_plain->num_rows == 0) {
                        $conn->query("ALTER TABLE pengguna ADD COLUMN password_plain VARCHAR(255) NULL AFTER password");
                    }
                    
                    // Update username dengan NUPTK (username selalu sama dengan NUPTK)
                    // Untuk guru: username = NUPTK (untuk login menggunakan NUPTK)
                    $username = $nuptk; // Username sama dengan NUPTK
                    
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $password_plain = $_POST['password']; // Simpan password plain text untuk admin
                        $stmt = $conn->prepare("UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?, username=?, nuptk=?, password=?, password_plain=?, foto=?, role=? WHERE id=?");
                        $stmt->bind_param("sssssssssssi", $nama, $jenis_kelamin, $tempat_lahir_null, $tanggal_lahir_null, $pendidikan_null, $username, $nuptk, $password, $password_plain, $foto, $role, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?, username=?, nuptk=?, foto=?, role=? WHERE id=?");
                        $stmt->bind_param("sssssssssi", $nama, $jenis_kelamin, $tempat_lahir_null, $tanggal_lahir_null, $pendidikan_null, $username, $nuptk, $foto, $role, $id);
                    }
                    
                    if ($stmt->execute()) {
                        // Redirect untuk mencegah resubmit dan refresh data
                        $_SESSION['success_message'] = 'Data guru berhasil diperbarui!';
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        header('Location: data.php');
                        exit();
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
                // Redirect untuk mencegah resubmit dan refresh data
                $_SESSION['success_message'] = 'Data guru berhasil dihapus!';
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: data.php');
                exit();
            } else {
                $_SESSION['error_message'] = 'Gagal menghapus data guru!';
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: data.php');
                exit();
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
$guru_data = [];
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
        $guru_data = [];
    } else {
        // Simpan data ke array untuk digunakan di view
        while ($row = $result->fetch_assoc()) {
            // Sanitize data dari database sebelum ditampilkan
            foreach ($row as $key => $value) {
                if (is_string($value)) {
                    // Hapus karakter kontrol dan karakter berbahaya
                    $row[$key] = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
                }
            }
            $guru_data[] = $row;
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
    $guru_data = [];
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Data Guru</h5>
        <div>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportGuru">
                <i class="fas fa-file-upload"></i> Impor Excel
            </button>
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
                        <th>Status Password</th>
                        <th width="200">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($guru_data) > 0):
                        $no = 1;
                        foreach ($guru_data as $row): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <img src="../uploads/<?php echo htmlspecialchars($row['foto'] ?? 'default.png'); ?>" 
                                     alt="Foto" class="rounded-circle" width="50" height="50" 
                                     style="object-fit: cover;" onerror="this.onerror=null; this.style.display='none';">
                            </td>
                            <td><?php echo htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?><?php echo ($row['tempat_lahir'] ?? '') && ($row['tanggal_lahir'] ?? '') ? ', ' : ''; ?><?php echo $row['tanggal_lahir'] ? htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_lahir']))) : ''; ?></td>
                            <td><?php echo htmlspecialchars($row['pendidikan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['nuptk'] ?? $row['username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['wali_kelas_nama'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php 
                                // Tampilkan password dari password_plain jika ada, atau cek apakah default
                                $password_plain = $row['password_plain'] ?? '';
                                $password_hash = $row['password'] ?? '';
                                
                                // Jika password_plain kosong, cek apakah password masih default
                                if (empty($password_plain)) {
                                    $is_default_password = password_verify('123456', $password_hash);
                                    if ($is_default_password) {
                                        $password_plain = '123456';
                                    }
                                }
                                
                                if (!empty($password_plain)) {
                                    // Tampilkan password dengan badge status
                                    $is_default = ($password_plain === '123456');
                                    if ($is_default) {
                                        echo '<span class="badge bg-warning text-dark me-2" title="Password default">';
                                        echo '<i class="fas fa-key"></i> Default';
                                        echo '</span>';
                                    } else {
                                        echo '<span class="badge bg-success me-2" title="Password sudah diubah">';
                                        echo '<i class="fas fa-lock"></i> Diubah';
                                        echo '</span>';
                                    }
                                    echo '<code style="font-size: 0.9em; color: #333; font-weight: 500;" id="password_' . $row['id'] . '">' . htmlspecialchars($password_plain) . '</code>';
                                    echo '<button class="btn btn-sm btn-link p-0 ms-1" onclick="copyPassword(' . $row['id'] . ', \'' . htmlspecialchars($password_plain, ENT_QUOTES) . '\')" title="Copy Password">';
                                    echo '<i class="fas fa-copy text-primary"></i>';
                                    echo '</button>';
                                } else {
                                    // Password tidak diketahui (untuk data lama)
                                    echo '<span class="badge bg-secondary me-2">';
                                    echo '<i class="fas fa-question"></i> Tidak Diketahui';
                                    echo '</span>';
                                    echo '<br><small class="text-muted">Gunakan Reset untuk set ke default</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="resetPassword(<?php echo $row['id']; ?>)" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editGuru(<?php echo $row['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (!$row['is_proktor_utama']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteGuru(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
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
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password" placeholder="Masukkan password baru" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" onclick="togglePasswordVisibility()">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <small class="text-muted" id="passwordHint">Kosongkan jika tidak ingin mengubah password (untuk edit)</small>
                            <small class="text-info d-block mt-1" id="passwordInfo">Default: 123456 (untuk tambah baru)</small>
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

<!-- Modal Import Guru -->
<div class="modal fade" id="modalImportGuru" tabindex="-1" aria-labelledby="modalImportGuruLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #2d5016; color: white;">
                <h5 class="modal-title" id="modalImportGuruLabel"><i class="fas fa-file-upload"></i> Upload Guru</h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadTemplateGuru()">
                        <i class="fas fa-download"></i> Template Excel
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <!-- Upload Area -->
                <div class="upload-area" id="uploadAreaGuru" style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer; background-color: #f8f9fa; transition: all 0.3s;">
                    <input type="file" id="fileInputGuru" accept=".xls,.xlsx" style="display: none;" multiple>
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                    <p class="mb-0" style="color: #6c757d; font-size: 16px;">
                        Letakkan File atau Klik Disini untuk upload
                    </p>
                </div>
                
                <!-- File List Table -->
                <div class="mt-4">
                    <table class="table table-bordered table-sm" id="fileTableGuru" style="display: table;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Progress</th>
                                <th>Sukses</th>
                                <th>Gagal</th>
                                <th>Ganda</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fileTableBodyGuru">
                            <tr id="noDataRowGuru">
                                <td colspan="8" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle"></i> Belum ada file yang dipilih
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if (count($guru_data) > 0): ?>
        $('#tableGuru').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
        <?php endif; ?>
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
        window.location.href = 'export_guru.php?format=excel';
    }
    
    function exportPDF() {
        window.location.href = 'export_guru.php?format=pdf';
    }
    
    // Fungsi untuk toggle password visibility
    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');
        
        if (passwordInput && toggleIcon) {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    }
    
    // Reset form saat modal ditutup
    $('#modalGuru').on('hidden.bs.modal', function() {
        $('#formGuru')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Data Guru');
        $('#passwordRequired').show();
        $('#passwordHint').hide();
        $('#passwordInfo').show();
        $('#password').attr('type', 'password');
        $('#password').attr('placeholder', 'Masukkan password baru');
        const toggleIcon = document.getElementById('togglePasswordIcon');
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    });
    
    // Reset tabel import saat modal import ditutup
    $('#modalImportGuru').on('hidden.bs.modal', function() {
        const fileTableBodyGuru = document.getElementById('fileTableBodyGuru');
        if (fileTableBodyGuru) {
            fileTableBodyGuru.innerHTML = '<tr id="noDataRowGuru"><td colspan="8" class="text-center text-muted py-3"><i class="fas fa-info-circle"></i> Belum ada file yang dipilih</td></tr>';
        }
        const fileInputGuru = document.getElementById('fileInputGuru');
        if (fileInputGuru) {
            fileInputGuru.value = '';
        }
        window.uploadFilesGuru = null;
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
        $('#passwordInfo').hide();
        $('#password').removeAttr('required');
        $('#password').attr('placeholder', 'Masukkan password baru (kosongkan jika tidak ingin mengubah)');
        $('#password').val(''); // Kosongkan password field saat edit
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
    
    // Import Guru Modal
    const uploadAreaGuru = document.getElementById('uploadAreaGuru');
    const fileInputGuru = document.getElementById('fileInputGuru');
    const fileTableGuru = document.getElementById('fileTableGuru');
    const fileTableBodyGuru = document.getElementById('fileTableBodyGuru');
    
    if (uploadAreaGuru && fileInputGuru) {
        // Click to upload
        uploadAreaGuru.addEventListener('click', () => {
            fileInputGuru.click();
        });
        
        // Drag and drop
        uploadAreaGuru.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadAreaGuru.style.borderColor = '#2d5016';
            uploadAreaGuru.style.backgroundColor = '#e8f5e9';
        });
        
        uploadAreaGuru.addEventListener('dragleave', () => {
            uploadAreaGuru.style.borderColor = '#ccc';
            uploadAreaGuru.style.backgroundColor = '#f8f9fa';
        });
        
        uploadAreaGuru.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadAreaGuru.style.borderColor = '#ccc';
            uploadAreaGuru.style.backgroundColor = '#f8f9fa';
            
            const files = e.dataTransfer.files;
            console.log('Files dropped:', files.length);
            if (files.length > 0) {
                // Simpan sebagai Array
                const filesArray = Array.from(files);
                handleFilesGuru(filesArray);
            }
        });
        
        fileInputGuru.addEventListener('change', (e) => {
            console.log('File input changed, files:', e.target.files.length);
            if (e.target.files.length > 0) {
                // Simpan files SEBELUM reset input
                const files = Array.from(e.target.files);
                handleFilesGuru(files);
                // Reset setelah handleFilesGuru selesai
                setTimeout(() => {
                    e.target.value = '';
                }, 100);
            }
        });
    }
    
    function handleFilesGuru(files) {
        console.log('handleFilesGuru called with files:', files);
        
        if (!fileTableGuru || !fileTableBodyGuru) {
            console.error('File table elements not found');
            return;
        }
        
        // Hapus baris "belum ada data" jika ada
        const noDataRow = document.getElementById('noDataRowGuru');
        if (noDataRow) {
            noDataRow.remove();
        }
        
        // Tampilkan tabel
        fileTableGuru.style.display = 'table';
        fileTableBodyGuru.innerHTML = '';
        
        // Simpan files sebagai Array ke window untuk akses di uploadFileGuru
        // Ini penting karena FileList bisa menjadi invalid setelah file input direset
        window.uploadFilesGuru = Array.from(files);
        console.log('Files saved to window.uploadFilesGuru:', window.uploadFilesGuru.length, 'files');
        
        // Reset file input SETELAH menyimpan files
        // Jangan reset sebelum menyimpan karena FileList akan menjadi invalid
        
        Array.from(files).forEach((file, index) => {
            const row = document.createElement('tr');
            row.id = 'fileRowGuru_' + index;
            
            // Format ukuran file
            let fileSize = '';
            if (file.size < 1024) {
                fileSize = file.size + ' B';
            } else if (file.size < 1024 * 1024) {
                fileSize = (file.size / 1024).toFixed(2) + ' KB';
            } else {
                fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
            
            row.innerHTML = `
                <td>${file.name}</td>
                <td>${fileSize}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="progressGuru_${index}" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </td>
                <td id="successGuru_${index}" style="text-align: center; font-weight: bold; color: #28a745;">0</td>
                <td id="failedGuru_${index}" style="text-align: center; font-weight: bold; color: #dc3545;">0</td>
                <td id="duplicateGuru_${index}" style="text-align: center; font-weight: bold; color: #ffc107;">0</td>
                <td id="statusGuru_${index}">
                    <span class="badge bg-secondary">Menunggu</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="uploadFileGuru(${index})">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </td>
            `;
            
            fileTableBodyGuru.appendChild(row);
        });
    }
    
    function uploadFileGuru(index) {
        console.log('=== uploadFileGuru START ===');
        console.log('Index:', index);
        console.log('window.uploadFilesGuru:', window.uploadFilesGuru);
        console.log('window.uploadFilesGuru length:', window.uploadFilesGuru ? window.uploadFilesGuru.length : 0);
        
        // Gunakan files yang disimpan di window
        let file = null;
        if (window.uploadFilesGuru && Array.isArray(window.uploadFilesGuru) && window.uploadFilesGuru[index]) {
            file = window.uploadFilesGuru[index];
            console.log('File found in window.uploadFilesGuru:', file.name, 'Size:', file.size);
        } else {
            console.error('File not found in window.uploadFilesGuru');
            console.error('Available files:', window.uploadFilesGuru);
            
            // Fallback ke fileInput (jika masih ada)
            const fileInput = document.getElementById('fileInputGuru');
            if (fileInput && fileInput.files && fileInput.files[index]) {
                file = fileInput.files[index];
                console.log('File found in fileInput (fallback):', file.name);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: `File tidak ditemukan!<br>Index: ${index}<br>Total files: ${window.uploadFilesGuru ? window.uploadFilesGuru.length : 0}`,
                    confirmButtonColor: '#2d5016'
                });
                return;
            }
        }
        
        if (!file || !file.name) {
            console.error('File is null or invalid:', file);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'File tidak valid!',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        console.log('File ready for upload:', file.name, file.size, 'bytes');
        
        const formData = new FormData();
        formData.append('file_excel', file);
        formData.append('action', 'import');
        
        const progressBar = document.getElementById('progressGuru_' + index);
        const statusBadge = document.getElementById('statusGuru_' + index);
        const successCell = document.getElementById('successGuru_' + index);
        const failedCell = document.getElementById('failedGuru_' + index);
        const duplicateCell = document.getElementById('duplicateGuru_' + index);
        
        if (!progressBar || !statusBadge || !successCell || !failedCell || !duplicateCell) {
            console.error('Required elements not found:', {
                progressBar: !!progressBar,
                statusBadge: !!statusBadge,
                successCell: !!successCell,
                failedCell: !!failedCell,
                duplicateCell: !!duplicateCell
            });
            return;
        }
        
        // Reset progress bar ke 0%
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressBar.setAttribute('aria-valuenow', '0');
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
        
        // Update status
        statusBadge.innerHTML = '<span class="badge bg-info">Mengupload...</span>';
        
        // Disable upload button
        const uploadBtn = document.querySelector(`#fileRowGuru_${index} button`);
        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        }
        
        console.log('Starting upload...');
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const percentRounded = Math.round(percentComplete);
                console.log('Upload progress:', percentRounded + '%');
                
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentRounded + '%';
                progressBar.setAttribute('aria-valuenow', percentRounded);
                
                // Update warna progress bar berdasarkan progress
                if (percentComplete < 50) {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                } else if (percentComplete < 100) {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                } else {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
                }
            } else {
                console.log('Progress not computable');
            }
        });
        
        xhr.addEventListener('load', () => {
            // Set progress bar ke 100% saat selesai
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.setAttribute('aria-valuenow', '100');
            progressBar.className = 'progress-bar bg-success';
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        statusBadge.innerHTML = '<span class="badge bg-success">Selesai</span>';
                        
                        // Update semua data
                        successCell.textContent = response.success_count || 0;
                        successCell.style.color = '#28a745';
                        successCell.style.fontWeight = 'bold';
                        
                        failedCell.textContent = response.error_count || 0;
                        failedCell.style.color = '#dc3545';
                        failedCell.style.fontWeight = 'bold';
                        
                        duplicateCell.textContent = response.duplicate_count || 0;
                        duplicateCell.style.color = '#ffc107';
                        duplicateCell.style.fontWeight = 'bold';
                        
                        // Disable upload button
                        const uploadBtn = document.querySelector(`#fileRowGuru_${index} button`);
                        if (uploadBtn) {
                            uploadBtn.disabled = true;
                            uploadBtn.innerHTML = '<i class="fas fa-check"></i> Selesai';
                            uploadBtn.className = 'btn btn-sm btn-success';
                        }
                        
                        if (response.success_count > 0) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                html: `Berhasil mengimpor ${response.success_count} data guru<br>
                                       Gagal: ${response.error_count || 0}<br>
                                       Duplikat: ${response.duplicate_count || 0}`,
                                confirmButtonColor: '#2d5016',
                                timer: 3000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Perhatian!',
                                html: `${response.message || 'Tidak ada data yang berhasil diimpor'}<br>
                                       <br>
                                       <strong>Detail:</strong><br>
                                       Sukses: ${response.success_count || 0}<br>
                                       Gagal: ${response.error_count || 0}<br>
                                       Duplikat: ${response.duplicate_count || 0}<br>
                                       <br>
                                       <small>Pastikan file Excel memiliki format yang benar:<br>
                                       Kolom 1: Nama<br>
                                       Kolom 2: Jenis Kelamin (L/P)<br>
                                       Kolom 3: Tempat Lahir<br>
                                       Kolom 4: Tanggal Lahir<br>
                                       Kolom 5: Pendidikan<br>
                                       Kolom 6: NUPTK<br>
                                       Kolom 7: Password (opsional)<br>
                                       Kolom 8: Role (opsional)</small>`,
                                confirmButtonColor: '#2d5016',
                                timer: 6000,
                                timerProgressBar: true,
                                showConfirmButton: true,
                                width: '600px'
                            });
                        }
                    } else {
                        statusBadge.innerHTML = '<span class="badge bg-danger">Gagal</span>';
                        progressBar.className = 'progress-bar bg-danger';
                        
                        failedCell.textContent = response.error_count || 0;
                        failedCell.style.color = '#dc3545';
                        failedCell.style.fontWeight = 'bold';
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message || 'Gagal mengimpor data',
                            confirmButtonColor: '#2d5016',
                            timer: 4000,
                            timerProgressBar: true,
                            showConfirmButton: true
                        });
                    }
                } catch (e) {
                    statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
                    progressBar.className = 'progress-bar bg-danger';
                    console.error('Error parsing response:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan saat memproses response',
                        confirmButtonColor: '#2d5016'
                    });
                }
            } else {
                statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
                progressBar.className = 'progress-bar bg-danger';
                
                // Coba parse response sebagai JSON untuk mendapatkan pesan error yang lebih detail
                let errorMessage = 'Gagal mengupload file';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch (e) {
                    // Jika bukan JSON, gunakan status text atau default message
                    errorMessage = xhr.statusText || 'Gagal mengupload file (Status: ' + xhr.status + ')';
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: errorMessage + '<br><small>Status: ' + xhr.status + '</small>',
                    confirmButtonColor: '#2d5016',
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            }
        });
        
        xhr.addEventListener('error', () => {
            console.error('XHR error occurred');
            statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
            progressBar.className = 'progress-bar bg-danger';
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengupload',
                confirmButtonColor: '#2d5016'
            });
        });
        
        xhr.addEventListener('abort', () => {
            console.log('XHR aborted');
            statusBadge.innerHTML = '<span class="badge bg-warning">Dibatalkan</span>';
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
        });
        
        console.log('Opening XHR connection...');
        xhr.open('POST', 'import_ajax.php', true);
        
        console.log('Sending formData...');
        xhr.send(formData);
    }
    
    function downloadTemplateGuru() {
        window.location.href = 'template_guru.php';
    }
</script>

