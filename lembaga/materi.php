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
            $kode_mulok = trim($_POST['kode_mulok'] ?? '');
            $nama_mulok = trim($_POST['nama_mulok'] ?? '');
            $jumlah_jam = intval($_POST['jumlah_jam'] ?? 0);
            
            // Validasi input
            if (empty($kode_mulok)) {
                $error = 'Kode Mulok tidak boleh kosong!';
            } elseif (empty($nama_mulok)) {
                $error = 'Nama Mulok tidak boleh kosong!';
            } else {
                // Langsung insert, biarkan database yang menangkap error duplikasi
                try {
                    $stmt = $conn->prepare("INSERT INTO materi_mulok (kode_mulok, nama_mulok, jumlah_jam) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $kode_mulok, $nama_mulok, $jumlah_jam);
                    
                    if ($stmt->execute()) {
                        $success = 'Materi mulok berhasil ditambahkan!';
                    } else {
                        // Cek error dari statement
                        $error_code = $stmt->errno;
                        $error_msg = $stmt->error;
                        
                        // Hanya tampilkan error duplikasi jika benar-benar error code 1062
                        if ($error_code == 1062) {
                            // Cari data yang sudah ada untuk informasi debug
                            $debug_stmt = $conn->prepare("SELECT id, kode_mulok, nama_mulok FROM materi_mulok WHERE kode_mulok = ?");
                            $debug_stmt->bind_param("s", $kode_mulok);
                            $debug_stmt->execute();
                            $debug_result = $debug_stmt->get_result();
                            
                            if ($debug_result->num_rows > 0) {
                                $existing = $debug_result->fetch_assoc();
                                $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah ada! (ID: ' . $existing['id'] . ', Kode: "' . htmlspecialchars($existing['kode_mulok']) . '", Nama: ' . htmlspecialchars($existing['nama_mulok']) . ')';
                            } else {
                                $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah ada! Silakan gunakan kode yang berbeda.';
                            }
                        } else {
                            $error = 'Gagal menambahkan materi mulok! Error: ' . $error_msg;
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    // Hanya tampilkan error duplikasi jika benar-benar error code 1062
                    $error_code = $e->getCode();
                    $error_msg = $e->getMessage();
                    
                    if ($error_code == 1062) {
                        // Cari data yang sudah ada untuk informasi debug
                        $debug_stmt = $conn->prepare("SELECT id, kode_mulok, nama_mulok FROM materi_mulok WHERE kode_mulok = ?");
                        $debug_stmt->bind_param("s", $kode_mulok);
                        $debug_stmt->execute();
                        $debug_result = $debug_stmt->get_result();
                        
                        if ($debug_result->num_rows > 0) {
                            $existing = $debug_result->fetch_assoc();
                            $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah ada! (ID: ' . $existing['id'] . ', Kode: "' . htmlspecialchars($existing['kode_mulok']) . '", Nama: ' . htmlspecialchars($existing['nama_mulok']) . ')';
                        } else {
                            $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah ada! Silakan gunakan kode yang berbeda.';
                        }
                    } else {
                        $error = 'Gagal menambahkan materi mulok! Error: ' . $error_msg;
                    }
                } catch (Exception $e) {
                    // Hanya tampilkan error duplikasi jika benar-benar ada kata "Duplicate entry" dan "kode_mulok"
                    $error_msg = $e->getMessage();
                    
                    if (strpos($error_msg, 'Duplicate entry') !== false && strpos($error_msg, 'kode_mulok') !== false) {
                        // Cari data yang sudah ada untuk informasi debug
                        $debug_stmt = $conn->prepare("SELECT id, kode_mulok, nama_mulok FROM materi_mulok WHERE kode_mulok = ?");
                        $debug_stmt->bind_param("s", $kode_mulok);
                        $debug_stmt->execute();
                        $debug_result = $debug_stmt->get_result();
                        
                        if ($debug_result->num_rows > 0) {
                            $existing = $debug_result->fetch_assoc();
                            $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah ada! (ID: ' . $existing['id'] . ', Kode: "' . htmlspecialchars($existing['kode_mulok']) . '", Nama: ' . htmlspecialchars($existing['nama_mulok']) . ')';
                        } else {
                            $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah ada! Silakan gunakan kode yang berbeda.';
                        }
                    } else {
                        $error = 'Gagal menambahkan materi mulok! Error: ' . $error_msg;
                    }
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $kode_mulok = trim($_POST['kode_mulok'] ?? '');
            $nama_mulok = trim($_POST['nama_mulok'] ?? '');
            $jumlah_jam = intval($_POST['jumlah_jam'] ?? 0);
            
            // Validasi input
            if (empty($kode_mulok)) {
                $error = 'Kode Mulok tidak boleh kosong!';
            } elseif (empty($nama_mulok)) {
                $error = 'Nama Mulok tidak boleh kosong!';
            } elseif ($id <= 0) {
                $error = 'ID tidak valid!';
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE materi_mulok SET kode_mulok=?, nama_mulok=?, jumlah_jam=? WHERE id=?");
                    $stmt->bind_param("ssii", $kode_mulok, $nama_mulok, $jumlah_jam, $id);
                    
                    if ($stmt->execute()) {
                        $success = 'Materi mulok berhasil diperbarui!';
                    } else {
                        // Cek error dari statement
                        $error_code = $stmt->errno;
                        $error_msg = $stmt->error;
                        
                        // Hanya tampilkan error duplikasi jika benar-benar error code 1062
                        if ($error_code == 1062) {
                            $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah digunakan oleh materi lain! Silakan gunakan kode yang berbeda.';
                        } else {
                            $error = 'Gagal memperbarui materi mulok! Error: ' . $error_msg;
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    // Hanya tampilkan error duplikasi jika benar-benar error code 1062
                    if ($e->getCode() == 1062) {
                        $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah digunakan oleh materi lain! Silakan gunakan kode yang berbeda.';
                    } else {
                        $error = 'Gagal memperbarui materi mulok! Error: ' . $e->getMessage();
                    }
                } catch (Exception $e) {
                    // Hanya tampilkan error duplikasi jika benar-benar ada kata "Duplicate entry"
                    $error_msg = $e->getMessage();
                    if (strpos($error_msg, 'Duplicate entry') !== false && strpos($error_msg, 'kode_mulok') !== false) {
                        $error = 'Kode Mulok "' . htmlspecialchars($kode_mulok) . '" sudah digunakan oleh materi lain! Silakan gunakan kode yang berbeda.';
                    } else {
                        $error = 'Gagal memperbarui materi mulok! Error: ' . $error_msg;
                    }
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            try {
                $stmt = $conn->prepare("DELETE FROM materi_mulok WHERE id=?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    // Redirect untuk mencegah resubmit dan form edit muncul
                    $_SESSION['success_message'] = 'Materi mulok berhasil dihapus!';
                    header('Location: materi.php');
                    exit();
                } else {
                    $_SESSION['error_message'] = 'Gagal menghapus materi mulok! Error: ' . $conn->error;
                    header('Location: materi.php');
                    exit();
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Gagal menghapus materi mulok! Error: ' . $e->getMessage();
                header('Location: materi.php');
                exit();
            }
        }
    }
}

// Ambil data untuk edit (hanya jika tidak ada pesan sukses/error dari redirect)
$edit_data = null;
if (isset($_GET['edit']) && empty($success) && empty($error)) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM materi_mulok WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil semua data
$result = null;
$materi_data = [];
try {
    // Ambil semua data tanpa filter
    $query = "SELECT * FROM materi_mulok ORDER BY kode_mulok ASC";
    $result = $conn->query($query);
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
        $result = null;
    } else {
        // Simpan data ke array untuk digunakan di view
        while ($row = $result->fetch_assoc()) {
            $materi_data[] = $row;
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-book"></i> Materi Mulok</h5>
        <div>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMateri">
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
        
        <?php 
        // Info: Tampilkan jumlah data yang ada
        if (count($materi_data) > 0) {
            echo '<div class="alert alert-info mb-3">';
            echo '<i class="fas fa-info-circle"></i> Total data: <strong>' . count($materi_data) . '</strong> materi mulok';
            echo '</div>';
        }
        ?>
        
        <?php 
        // Debug: Tampilkan semua data yang ada di database
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            echo '<div class="alert alert-info">';
            echo '<h6>Debug Info - Semua Data di Database:</h6>';
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>ID</th><th>Kode Mulok</th><th>Nama Mulok</th><th>Jumlah Jam</th></tr></thead>';
            echo '<tbody>';
            $debug_query = "SELECT * FROM materi_mulok ORDER BY id";
            $debug_result = $conn->query($debug_query);
            if ($debug_result && $debug_result->num_rows > 0) {
                while ($debug_row = $debug_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . $debug_row['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($debug_row['kode_mulok']) . '</td>';
                    echo '<td>' . htmlspecialchars($debug_row['nama_mulok']) . '</td>';
                    echo '<td>' . $debug_row['jumlah_jam'] . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="4">Tidak ada data</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
        ?>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableMateri">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Kode Mulok</th>
                        <th>Nama Mulok</th>
                        <th>Jumlah Jam</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($materi_data) > 0):
                        $no = 1;
                        foreach ($materi_data as $row): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['kode_mulok']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_mulok']); ?></td>
                            <td><?php echo htmlspecialchars($row['jumlah_jam']); ?> Jam</td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editMateri(<?php echo $row['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteMateri(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada data materi mulok</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalMateri" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Materi Mulok</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formMateri">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="mb-3">
                        <label class="form-label">Kode Mulok <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_mulok" id="kodeMulok" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Mulok <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mulok" id="namaMulok" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jumlah Jam <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="jumlah_jam" id="jumlahJam" min="0" required>
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
        <?php if (count($materi_data) > 0): ?>
        $('#tableMateri').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            order: [[1, 'asc']], // Sort by Kode Mulok ascending
            pageLength: 25,
            searching: true,
            paging: true,
            info: true
        });
        <?php endif; ?>
    });
    
    function editMateri(id) {
        window.location.href = 'materi.php?edit=' + id;
    }
    
    function deleteMateri(id) {
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
        window.location.href = 'export_materi.php?format=excel';
    }
    
    function exportPDF() {
        window.location.href = 'export_materi.php?format=pdf';
    }
    
    // Reset form saat modal ditutup
    $('#modalMateri').on('hidden.bs.modal', function() {
        $('#formMateri')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Materi Mulok');
    });
    
    // Load data untuk edit (hanya jika tidak ada pesan sukses/error)
    <?php if ($edit_data && empty($success) && empty($error)): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#kodeMulok').val('<?php echo addslashes($edit_data['kode_mulok']); ?>');
        $('#namaMulok').val('<?php echo addslashes($edit_data['nama_mulok']); ?>');
        $('#jumlahJam').val(<?php echo $edit_data['jumlah_jam']; ?>);
        $('#modalTitle').text('Edit Materi Mulok');
        $('#modalMateri').modal('show');
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
        // Hapus parameter dari URL untuk mencegah resubmit dan form edit muncul
        if (window.location.search.includes('edit=')) {
            window.location.href = window.location.pathname;
        } else {
            window.location.href = window.location.pathname;
        }
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

