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
        if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $id = $_POST['id'] ?? 0;
            $kelas_id = $_POST['kelas_id'] ?? 0;
            $kkm = $_POST['kkm'] ?? 70;
            $predikat_a_min = $_POST['predikat_a_min'] ?? 90;
            $predikat_a_max = $_POST['predikat_a_max'] ?? 100;
            $predikat_b_min = $_POST['predikat_b_min'] ?? 80;
            $predikat_b_max = $_POST['predikat_b_max'] ?? 89;
            $predikat_c_min = $_POST['predikat_c_min'] ?? 70;
            $predikat_c_max = $_POST['predikat_c_max'] ?? 79;
            $predikat_d_min = $_POST['predikat_d_min'] ?? 0;
            $predikat_d_max = $_POST['predikat_d_max'] ?? 69;
            
            if ($_POST['action'] == 'add') {
                $stmt = $conn->prepare("INSERT INTO nilai_kkm (kelas_id, kkm, predikat_a_min, predikat_a_max, predikat_b_min, predikat_b_max, predikat_c_min, predikat_c_max, predikat_d_min, predikat_d_max) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiiiiiiii", $kelas_id, $kkm, $predikat_a_min, $predikat_a_max, $predikat_b_min, $predikat_b_max, $predikat_c_min, $predikat_c_max, $predikat_d_min, $predikat_d_max);
            } else {
                $stmt = $conn->prepare("UPDATE nilai_kkm SET kelas_id=?, kkm=?, predikat_a_min=?, predikat_a_max=?, predikat_b_min=?, predikat_b_max=?, predikat_c_min=?, predikat_c_max=?, predikat_d_min=?, predikat_d_max=? WHERE id=?");
                $stmt->bind_param("iiiiiiiiiii", $kelas_id, $kkm, $predikat_a_min, $predikat_a_max, $predikat_b_min, $predikat_b_max, $predikat_c_min, $predikat_c_max, $predikat_d_min, $predikat_d_max, $id);
            }
            
            if ($stmt->execute()) {
                $success = 'Nilai KKM berhasil disimpan!';
            } else {
                $error = 'Gagal menyimpan nilai KKM!';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $stmt = $conn->prepare("DELETE FROM nilai_kkm WHERE id=?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = 'Nilai KKM berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus nilai KKM!';
            }
        }
    }
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM nilai_kkm WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil semua data KKM dengan nama kelas
$result = null;
$kelas_list = null;
try {
    $query = "SELECT nk.*, k.nama_kelas 
              FROM nilai_kkm nk
              INNER JOIN kelas k ON nk.kelas_id = k.id
              ORDER BY k.nama_kelas";
    $result = $conn->query($query);
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
        $result = null;
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
}

// Ambil data kelas
try {
    $query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
    $kelas_list = $conn->query($query_kelas);
    if (!$kelas_list) {
        $kelas_list = null;
    }
} catch (Exception $e) {
    $kelas_list = null;
}

// Set page title (variabel lokal)
$page_title = 'Nilai KKM';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Nilai KKM</h5>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalKKM">
            <i class="fas fa-plus"></i> Tambah
        </button>
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
            <table class="table table-bordered table-striped" id="tableKKM">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Kelas</th>
                        <th>KKM</th>
                        <th>Predikat A</th>
                        <th>Predikat B</th>
                        <th>Predikat C</th>
                        <th>Predikat D</th>
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
                            <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['kkm']); ?></strong></td>
                            <td><?php echo $row['predikat_a_min']; ?> - <?php echo $row['predikat_a_max']; ?></td>
                            <td><?php echo $row['predikat_b_min']; ?> - <?php echo $row['predikat_b_max']; ?></td>
                            <td><?php echo $row['predikat_c_min']; ?> - <?php echo $row['predikat_c_max']; ?></td>
                            <td><?php echo $row['predikat_d_min']; ?> - <?php echo $row['predikat_d_max']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editKKM(<?php echo $row['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteKKM(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Belum ada data nilai KKM</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalKKM" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Nilai KKM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formKKM">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_id" id="kelasId" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php if ($kelas_list): 
                                    $kelas_list->data_seek(0);
                                    while ($kelas = $kelas_list->fetch_assoc()): 
                                        // Skip kelas Alumni dari dropdown
                                        if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                            continue;
                                        }
                                ?>
                                    <option value="<?php echo $kelas['id']; ?>">
                                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">KKM <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="kkm" id="kkm" min="0" max="100" value="70" required>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Pengaturan Predikat</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat A - Min</label>
                            <input type="number" class="form-control" name="predikat_a_min" id="predikatAMin" min="0" max="100" value="90" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat A - Max</label>
                            <input type="number" class="form-control" name="predikat_a_max" id="predikatAMax" min="0" max="100" value="100" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat B - Min</label>
                            <input type="number" class="form-control" name="predikat_b_min" id="predikatBMin" min="0" max="100" value="80" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat B - Max</label>
                            <input type="number" class="form-control" name="predikat_b_max" id="predikatBMax" min="0" max="100" value="89" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat C - Min</label>
                            <input type="number" class="form-control" name="predikat_c_min" id="predikatCMin" min="0" max="100" value="70" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat C - Max</label>
                            <input type="number" class="form-control" name="predikat_c_max" id="predikatCMax" min="0" max="100" value="79" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat D - Min</label>
                            <input type="number" class="form-control" name="predikat_d_min" id="predikatDMin" min="0" max="100" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Predikat D - Max</label>
                            <input type="number" class="form-control" name="predikat_d_max" id="predikatDMax" min="0" max="100" value="69" required>
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
        $('#tableKKM').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
    
    function editKKM(id) {
        window.location.href = 'kkm.php?edit=' + id;
    }
    
    function deleteKKM(id) {
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
    
    // Load data untuk edit
    <?php if ($edit_data): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#kelasId').val(<?php echo $edit_data['kelas_id']; ?>);
        $('#kkm').val(<?php echo $edit_data['kkm']; ?>);
        $('#predikatAMin').val(<?php echo $edit_data['predikat_a_min']; ?>);
        $('#predikatAMax').val(<?php echo $edit_data['predikat_a_max']; ?>);
        $('#predikatBMin').val(<?php echo $edit_data['predikat_b_min']; ?>);
        $('#predikatBMax').val(<?php echo $edit_data['predikat_b_max']; ?>);
        $('#predikatCMin').val(<?php echo $edit_data['predikat_c_min']; ?>);
        $('#predikatCMax').val(<?php echo $edit_data['predikat_c_max']; ?>);
        $('#predikatDMin').val(<?php echo $edit_data['predikat_d_min']; ?>);
        $('#predikatDMax').val(<?php echo $edit_data['predikat_d_max']; ?>);
        $('#modalTitle').text('Edit Nilai KKM');
        $('#modalKKM').modal('show');
    });
    <?php endif; ?>
    
    // Reset form saat modal ditutup
    $('#modalKKM').on('hidden.bs.modal', function() {
        $('#formKKM')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Nilai KKM');
    });
    
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
        window.location.href = 'kkm.php';
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

