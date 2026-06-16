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
            $nama_kategori = trim($_POST['nama_kategori'] ?? '');
            if (empty($nama_kategori)) {
                $error = 'Nama kategori tidak boleh kosong!';
            } else {
                try {
                    $stmt = $conn->prepare("INSERT INTO kategori_mulok (nama_kategori) VALUES (?)");
                    $stmt->bind_param("s", $nama_kategori);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Kategori berhasil ditambahkan!';
                        header('Location: kategori_materi.php');
                        exit();
                    } else {
                        $error = 'Gagal menambahkan kategori! Error: ' . $stmt->error;
                    }
                } catch (Exception $e) {
                    $error = 'Gagal menambahkan kategori! Error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $nama_kategori = trim($_POST['nama_kategori'] ?? '');
            if (empty($nama_kategori)) {
                $error = 'Nama kategori tidak boleh kosong!';
            } elseif ($id <= 0) {
                $error = 'ID tidak valid!';
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE kategori_mulok SET nama_kategori = ? WHERE id = ?");
                    $stmt->bind_param("si", $nama_kategori, $id);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Kategori berhasil diperbarui!';
                        header('Location: kategori_materi.php');
                        exit();
                    } else {
                        $error = 'Gagal memperbarui kategori! Error: ' . $stmt->error;
                    }
                } catch (Exception $e) {
                    $error = 'Gagal memperbarui kategori! Error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $conn->prepare("DELETE FROM kategori_mulok WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Kategori berhasil dihapus!';
                    header('Location: kategori_materi.php');
                    exit();
                } else {
                    $_SESSION['error_message'] = 'Gagal menghapus kategori! Error: ' . $conn->error;
                    header('Location: kategori_materi.php');
                    exit();
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Gagal menghapus kategori! Error: ' . $e->getMessage();
                header('Location: kategori_materi.php');
                exit();
            }
        }
    }
}

// Ambil pesan dari session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit']) && empty($success) && empty($error)) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM kategori_mulok WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil semua data kategori
$kategori_list = [];
try {
    $result = $conn->query("SELECT * FROM kategori_mulok ORDER BY nama_kategori ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $kategori_list[] = $row;
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

// Set page title
$page_title = 'Kategori Materi Mulok';
include '../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-tags"></i> Kategori Materi Mulok</h5>
        <div>
            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalKategori">
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
            <table class="table table-bordered table-striped" id="tableKategori">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Nama Kategori</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($kategori_list) > 0):
                        $no = 1;
                        foreach ($kategori_list as $row): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editKategori(<?php echo $row['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteKategori(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">Belum ada data kategori</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalKategori" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formKategori">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kategori" id="namaKategori" required>
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
        <?php if (count($kategori_list) > 0): ?>
        $('#tableKategori').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            order: [[1, 'asc']],
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: [2] }
            ]
        });
        <?php endif; ?>
    });
    
    function editKategori(id) {
        window.location.href = 'kategori_materi.php?edit=' + id;
    }
    
    function deleteKategori(id) {
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
    
    // Reset form saat modal ditutup
    $('#modalKategori').on('hidden.bs.modal', function() {
        $('#formKategori')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Kategori');
    });
    
    // Load data untuk edit
    <?php if ($edit_data && empty($success) && empty($error)): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#namaKategori').val('<?php echo addslashes($edit_data['nama_kategori']); ?>');
        $('#modalTitle').text('Edit Kategori');
        $('#modalKategori').modal('show');
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
    });
    <?php endif; ?>
    
    <?php if ($error): ?>
    Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: '<?php echo addslashes($error); ?>',
        confirmButtonColor: '#2d5016'
    });
    <?php endif; ?>
</script>
