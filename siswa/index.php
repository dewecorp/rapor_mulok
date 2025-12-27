<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();
$success = '';
$error = '';

// Filter kelas
$kelas_filter = $_GET['kelas'] ?? '';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nisn = $_POST['nisn'] ?? '';
            $nama = $_POST['nama'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = $_POST['tempat_lahir'] ?? '';
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $kelas_id = $_POST['kelas_id'] ?? null;
            
            $stmt = $conn->prepare("INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, kelas_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $nisn, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $kelas_id);
            
            if ($stmt->execute()) {
                // Update jumlah siswa di kelas
                if ($kelas_id) {
                    $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
                }
                $success = 'Data siswa berhasil ditambahkan!';
            } else {
                $error = 'Gagal menambahkan data siswa!';
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'] ?? 0;
            $nisn = $_POST['nisn'] ?? '';
            $nama = $_POST['nama'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = $_POST['tempat_lahir'] ?? '';
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $kelas_id = $_POST['kelas_id'] ?? null;
            
            // Ambil kelas lama
            $query_old = "SELECT kelas_id FROM siswa WHERE id = $id";
            $old_kelas = $conn->query($query_old)->fetch_assoc()['kelas_id'];
            
            $stmt = $conn->prepare("UPDATE siswa SET nisn=?, nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, kelas_id=? WHERE id=?");
            $stmt->bind_param("sssssii", $nisn, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $kelas_id, $id);
            
            if ($stmt->execute()) {
                // Update jumlah siswa di kelas lama dan baru
                if ($old_kelas) {
                    $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $old_kelas) WHERE id = $old_kelas");
                }
                if ($kelas_id) {
                    $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
                }
                $success = 'Data siswa berhasil diperbarui!';
            } else {
                $error = 'Gagal memperbarui data siswa!';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Ambil kelas_id sebelum hapus
            try {
                $stmt_kelas = $conn->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
                $stmt_kelas->bind_param("i", $id);
                $stmt_kelas->execute();
                $result_kelas = $stmt_kelas->get_result();
                $kelas_data = $result_kelas->fetch_assoc();
                $kelas_id = $kelas_data ? $kelas_data['kelas_id'] : null;
            } catch (Exception $e) {
                $kelas_id = null;
            }
            
            $stmt = $conn->prepare("DELETE FROM siswa WHERE id=?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Update jumlah siswa di kelas
                if ($kelas_id) {
                    $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
                }
                $success = 'Data siswa berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus data siswa!';
            }
        }
    }
}

// Ambil data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil data kelas
$query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
$kelas_list = $conn->query($query_kelas);

// Query data siswa
$result = null;
$siswa_data = [];
try {
    if ($kelas_filter) {
        $kelas_id = intval($kelas_filter);
        $stmt = $conn->prepare("SELECT s.*, k.nama_kelas
                  FROM siswa s
                  LEFT JOIN kelas k ON s.kelas_id = k.id
                  WHERE s.kelas_id = ?
                  ORDER BY s.nama");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $siswa_data[] = $row;
            }
        }
    } else {
        $query = "SELECT s.*, k.nama_kelas
              FROM siswa s
              LEFT JOIN kelas k ON s.kelas_id = k.id
              ORDER BY s.nama";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $siswa_data[] = $row;
            }
        }
    }
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
        $result = null;
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
    $siswa_data = [];
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Data Siswa</h5>
        <div>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalSiswa">
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
        
        <div class="mb-3">
            <label class="form-label">Filter Kelas</label>
            <select class="form-select" id="filterKelas" onchange="filterKelas()" style="max-width: 300px;">
                <option value="">-- Semua Kelas --</option>
                <?php 
                $kelas_list->data_seek(0);
                while ($kelas = $kelas_list->fetch_assoc()): 
                ?>
                    <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_filter == $kelas['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <?php if (count($siswa_data) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableSiswa">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>Jenis Kelamin</th>
                        <th>Tempat, Tgl Lahir</th>
                        <th>Kelas</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($siswa_data as $row): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nisn']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                            <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-'); ?>, <?php echo $row['tanggal_lahir'] ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editSiswa(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteSiswa(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif (!$result): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan saat mengambil data siswa.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada data siswa<?php echo $kelas_filter ? ' di kelas yang dipilih' : ''; ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalSiswa" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Data Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formSiswa">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="formId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NISN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nisn" id="nisn" required>
                        </div>
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
                            <label class="form-label">Kelas</label>
                            <select class="form-select" name="kelas_id" id="kelasId">
                                <option value="">-- Pilih Kelas --</option>
                                <?php 
                                $kelas_list->data_seek(0);
                                while ($kelas = $kelas_list->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $kelas['id']; ?>">
                                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
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
    function filterKelas() {
        var kelasId = $('#filterKelas').val();
        if (kelasId) {
            window.location.href = 'index.php?kelas=' + kelasId;
        } else {
            window.location.href = 'index.php';
        }
    }
    
    $(document).ready(function() {
        <?php if (count($siswa_data) > 0): ?>
        $('#tableSiswa').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            order: [[2, 'asc']], // Sort by Nama ascending
            pageLength: 25
        });
        <?php endif; ?>
    });
    
    function editSiswa(id) {
        window.location.href = 'index.php?edit=' + id;
    }
    
    function deleteSiswa(id) {
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
    
    function exportExcel() {
        var kelas = $('#filterKelas').val();
        window.location.href = 'export_siswa.php?format=excel&kelas=' + kelas;
    }
    
    function exportPDF() {
        var kelas = $('#filterKelas').val();
        window.location.href = 'export_siswa.php?format=pdf&kelas=' + kelas;
    }
    
    // Reset form saat modal ditutup
    $('#modalSiswa').on('hidden.bs.modal', function() {
        $('#formSiswa')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Data Siswa');
    });
    
    // Load data untuk edit
    <?php if ($edit_data): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#nisn').val('<?php echo addslashes($edit_data['nisn']); ?>');
        $('#nama').val('<?php echo addslashes($edit_data['nama']); ?>');
        $('#jenisKelamin').val('<?php echo $edit_data['jenis_kelamin']; ?>');
        $('#tempatLahir').val('<?php echo addslashes($edit_data['tempat_lahir'] ?? ''); ?>');
        $('#tanggalLahir').val('<?php echo $edit_data['tanggal_lahir'] ?? ''; ?>');
        $('#kelasId').val(<?php echo $edit_data['kelas_id'] ?? 'null'; ?>);
        $('#modalTitle').text('Edit Data Siswa');
        $('#modalSiswa').modal('show');
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
        window.location.href = 'index.php<?php echo $kelas_filter ? "?kelas=" . $kelas_filter : ""; ?>';
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

