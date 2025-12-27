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
            $nama_kelas = trim($_POST['nama_kelas'] ?? '');
            $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? intval($_POST['wali_kelas_id']) : null;
            
            // Validasi input
            if (empty($nama_kelas)) {
                $error = 'Nama Kelas tidak boleh kosong!';
            } else {
                // Validasi wali_kelas_id jika diisi
                if ($wali_kelas_id !== null && $wali_kelas_id > 0) {
                    $check_stmt = $conn->prepare("SELECT id FROM pengguna WHERE id = ? AND role IN ('wali_kelas', 'guru')");
                    $check_stmt->bind_param("i", $wali_kelas_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows == 0) {
                        $error = 'Wali Kelas yang dipilih tidak valid atau tidak ditemukan!';
                    }
                }
                
                if (empty($error)) {
                    try {
                        if ($wali_kelas_id !== null && $wali_kelas_id > 0) {
                            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES (?, ?)");
                            $stmt->bind_param("si", $nama_kelas, $wali_kelas_id);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, wali_kelas_id) VALUES (?, NULL)");
                            $stmt->bind_param("s", $nama_kelas);
                        }
                        
                        if ($stmt->execute()) {
                            // Redirect untuk mencegah resubmit dan refresh data
                            $_SESSION['success_message'] = 'Kelas berhasil ditambahkan!';
                            header('Location: kelas.php');
                            exit();
                        } else {
                            $error_code = $stmt->errno;
                            $error_msg = $stmt->error;
                            
                            // Cek apakah error karena foreign key constraint
                            if ($error_code == 1452 || strpos($error_msg, 'foreign key constraint') !== false) {
                                $error = 'Wali Kelas yang dipilih tidak valid! Pastikan Wali Kelas ada di sistem.';
                            } else {
                                $error = 'Gagal menambahkan kelas! Error: ' . $error_msg;
                            }
                        }
                    } catch (mysqli_sql_exception $e) {
                        // Cek apakah error karena foreign key constraint
                        if ($e->getCode() == 1452 || strpos($e->getMessage(), 'foreign key constraint') !== false) {
                            $error = 'Wali Kelas yang dipilih tidak valid! Pastikan Wali Kelas ada di sistem.';
                        } else {
                            $error = 'Gagal menambahkan kelas! Error: ' . $e->getMessage();
                        }
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                            $error = 'Wali Kelas yang dipilih tidak valid! Pastikan Wali Kelas ada di sistem.';
                        } else {
                            $error = 'Gagal menambahkan kelas! Error: ' . $e->getMessage();
                        }
                    }
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $nama_kelas = trim($_POST['nama_kelas'] ?? '');
            $wali_kelas_id = !empty($_POST['wali_kelas_id']) ? intval($_POST['wali_kelas_id']) : null;
            
            // Validasi input
            if (empty($nama_kelas)) {
                $error = 'Nama Kelas tidak boleh kosong!';
            } elseif ($id <= 0) {
                $error = 'ID tidak valid!';
            } else {
                // Validasi wali_kelas_id jika diisi
                if ($wali_kelas_id !== null && $wali_kelas_id > 0) {
                    $check_stmt = $conn->prepare("SELECT id FROM pengguna WHERE id = ? AND role IN ('wali_kelas', 'guru')");
                    $check_stmt->bind_param("i", $wali_kelas_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows == 0) {
                        $error = 'Wali Kelas yang dipilih tidak valid atau tidak ditemukan!';
                    }
                }
                
                if (empty($error)) {
                    try {
                        if ($wali_kelas_id !== null && $wali_kelas_id > 0) {
                            $stmt = $conn->prepare("UPDATE kelas SET nama_kelas=?, wali_kelas_id=? WHERE id=?");
                            $stmt->bind_param("sii", $nama_kelas, $wali_kelas_id, $id);
                        } else {
                            $stmt = $conn->prepare("UPDATE kelas SET nama_kelas=?, wali_kelas_id=NULL WHERE id=?");
                            $stmt->bind_param("si", $nama_kelas, $id);
                        }
                        
                        if ($stmt->execute()) {
                            $success = 'Kelas berhasil diperbarui!';
                        } else {
                            $error_code = $stmt->errno;
                            $error_msg = $stmt->error;
                            
                            // Cek apakah error karena foreign key constraint
                            if ($error_code == 1452 || strpos($error_msg, 'foreign key constraint') !== false) {
                                $error = 'Wali Kelas yang dipilih tidak valid! Pastikan Wali Kelas ada di sistem.';
                            } else {
                                $error = 'Gagal memperbarui kelas! Error: ' . $error_msg;
                            }
                        }
                    } catch (mysqli_sql_exception $e) {
                        // Cek apakah error karena foreign key constraint
                        if ($e->getCode() == 1452 || strpos($e->getMessage(), 'foreign key constraint') !== false) {
                            $error = 'Wali Kelas yang dipilih tidak valid! Pastikan Wali Kelas ada di sistem.';
                        } else {
                            $error = 'Gagal memperbarui kelas! Error: ' . $e->getMessage();
                        }
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                            $error = 'Wali Kelas yang dipilih tidak valid! Pastikan Wali Kelas ada di sistem.';
                        } else {
                            $error = 'Gagal memperbarui kelas! Error: ' . $e->getMessage();
                        }
                    }
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $stmt = $conn->prepare("DELETE FROM kelas WHERE id=?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = 'Kelas berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus kelas!';
            }
        }
    }
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil semua data kelas dengan nama wali kelas dan jumlah siswa
$result = null;
$kelas_data = [];
$guru_list = null;
try {
    $query = "SELECT k.*, p.nama as nama_wali_kelas,
              (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id) as jumlah_siswa
              FROM kelas k 
              LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
              ORDER BY k.nama_kelas";
    $result = $conn->query($query);
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
        $result = null;
    } else {
        // Simpan data ke array untuk digunakan di view
        while ($row = $result->fetch_assoc()) {
            $kelas_data[] = $row;
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
    $kelas_data = [];
}

// Ambil data guru untuk dropdown
try {
    $query_guru = "SELECT id, nama FROM pengguna WHERE role IN ('wali_kelas', 'guru') ORDER BY nama";
    $guru_list = $conn->query($query_guru);
    if (!$guru_list) {
        $guru_list = null;
    }
} catch (Exception $e) {
    $guru_list = null;
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-school"></i> Kelas</h5>
        <div>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalKelas">
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
            <table class="table table-bordered table-striped" id="tableKelas">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Nama Kelas</th>
                        <th>Jumlah Siswa</th>
                        <th>Wali Kelas</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($kelas_data) > 0):
                        $no = 1;
                        foreach ($kelas_data as $row): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                            <td><?php echo intval($row['jumlah_siswa'] ?? 0); ?> Siswa</td>
                            <td><?php echo htmlspecialchars($row['nama_wali_kelas'] ?? '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editKelas(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteKelas(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada data kelas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalKelas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formKelas">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kelas" id="namaKelas" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Wali Kelas</label>
                        <select class="form-select" name="wali_kelas_id" id="waliKelasId">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php if ($guru_list): 
                                while ($guru = $guru_list->fetch_assoc()): ?>
                                <option value="<?php echo $guru['id']; ?>"><?php echo htmlspecialchars($guru['nama']); ?></option>
                            <?php 
                                endwhile;
                            endif; ?>
                        </select>
                        <small class="text-muted">Jumlah siswa akan dihitung otomatis dari data siswa yang terdaftar di kelas ini.</small>
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
        <?php if (count($kelas_data) > 0): ?>
        $('#tableKelas').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            order: [[1, 'asc']], // Sort by Nama Kelas ascending
            pageLength: 25
        });
        <?php endif; ?>
    });
    
    function editKelas(id) {
        $.ajax({
            url: 'kelas.php?edit=' + id,
            type: 'GET',
            success: function(data) {
                // Parse response untuk mendapatkan data
                window.location.href = 'kelas.php?edit=' + id;
            }
        });
    }
    
    function deleteKelas(id) {
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
    
    function exportExcel() {
        window.location.href = 'export_kelas.php?format=excel';
    }
    
    function exportPDF() {
        window.location.href = 'export_kelas.php?format=pdf';
    }
    
    // Reset form saat modal ditutup
    $('#modalKelas').on('hidden.bs.modal', function() {
        $('#formKelas')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Kelas');
    });
    
    // Load data untuk edit
    <?php if ($edit_data): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#namaKelas').val('<?php echo addslashes($edit_data['nama_kelas']); ?>');
        $('#waliKelasId').val(<?php echo $edit_data['wali_kelas_id'] ?? 'null'; ?>);
        $('#modalTitle').text('Edit Kelas');
        $('#modalKelas').modal('show');
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
        window.location.href = 'kelas.php';
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

