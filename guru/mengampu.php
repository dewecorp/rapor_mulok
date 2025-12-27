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
            $materi_mulok_id = $_POST['materi_mulok_id'] ?? 0;
            $guru_id = $_POST['guru_id'] ?? 0;
            $kelas_id = $_POST['kelas_id'] ?? 0;
            
            if ($materi_mulok_id && $guru_id && $kelas_id) {
                $stmt = $conn->prepare("INSERT INTO mengampu_materi (materi_mulok_id, guru_id, kelas_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $materi_mulok_id, $guru_id, $kelas_id);
                
                if ($stmt->execute()) {
                    $success = 'Data mengampu berhasil ditambahkan!';
                } else {
                    $error = 'Gagal menambahkan data mengampu!';
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $stmt = $conn->prepare("DELETE FROM mengampu_materi WHERE id=?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = 'Data mengampu berhasil dihapus!';
            } else {
                $error = 'Gagal menghapus data mengampu!';
            }
        }
    }
}

// Ambil data materi mulok
$query_materi = "SELECT * FROM materi_mulok ORDER BY nama_mulok";
$materi_list = $conn->query($query_materi);

// Ambil data guru
$query_guru = "SELECT * FROM pengguna WHERE role IN ('guru', 'wali_kelas') ORDER BY nama";
$guru_list = $conn->query($query_guru);

// Ambil data kelas
$query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
$kelas_list = $conn->query($query_kelas);

// Query data mengampu
$result = null;
try {
    if ($kelas_filter) {
        $kelas_id = intval($kelas_filter);
        $stmt = $conn->prepare("SELECT mm.*, m.nama_mulok, m.jumlah_jam, p.nama as nama_guru, k.nama_kelas
              FROM mengampu_materi mm
              INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
              INNER JOIN pengguna p ON mm.guru_id = p.id
              INNER JOIN kelas k ON mm.kelas_id = k.id
              WHERE mm.kelas_id = ?
              ORDER BY k.nama_kelas, m.nama_mulok");
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $query = "SELECT mm.*, m.nama_mulok, m.jumlah_jam, p.nama as nama_guru, k.nama_kelas
              FROM mengampu_materi mm
              INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
              INNER JOIN pengguna p ON mm.guru_id = p.id
              INNER JOIN kelas k ON mm.kelas_id = k.id
              ORDER BY k.nama_kelas, m.nama_mulok";
        $result = $conn->query($query);
    }
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
        <h5 class="mb-0"><i class="fas fa-book-reader"></i> Mengampu Materi</h5>
        <div>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMengampu">
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
        
        <?php if ($result && ($kelas_filter || $result->num_rows > 0)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableMengampu">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Materi Mulok</th>
                        <th>Jumlah Jam</th>
                        <th>Guru</th>
                        <th>Kelas</th>
                        <th width="100">Aksi</th>
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
                            <td><?php echo htmlspecialchars($row['nama_mulok']); ?></td>
                            <td><?php echo htmlspecialchars($row['jumlah_jam']); ?> Jam</td>
                            <td><?php echo htmlspecialchars($row['nama_guru']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteMengampu(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Belum ada data mengampu materi</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php elseif (!$kelas_filter): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Pilih kelas terlebih dahulu untuk melihat data mengampu.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Belum ada data mengampu untuk kelas yang dipilih.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalMengampu" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Mengampu Materi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formMengampu">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Materi Mulok <span class="text-danger">*</span></label>
                        <select class="form-select" name="materi_mulok_id" required>
                            <option value="">-- Pilih Materi Mulok --</option>
                            <?php 
                            $materi_list->data_seek(0);
                            while ($materi = $materi_list->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $materi['id']; ?>">
                                    <?php echo htmlspecialchars($materi['nama_mulok']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Guru <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" id="guruId" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php 
                            $guru_list->data_seek(0);
                            while ($guru = $guru_list->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $guru['id']; ?>">
                                    <?php echo htmlspecialchars($guru['nama']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" name="kelas_id" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php if ($kelas_list): 
                                $kelas_list->data_seek(0);
                                while ($kelas = $kelas_list->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $kelas['id']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                </option>
                            <?php 
                                endwhile;
                            endif; ?>
                        </select>
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
            window.location.href = 'mengampu.php?kelas=' + kelasId;
        } else {
            window.location.href = 'mengampu.php';
        }
    }
    
    $(document).ready(function() {
        $('#tableMengampu').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
    
    function deleteMengampu(id) {
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
        window.location.href = 'export_mengampu.php?format=excel&kelas=' + kelas;
    }
    
    function exportPDF() {
        var kelas = $('#filterKelas').val();
        window.location.href = 'export_mengampu.php?format=pdf&kelas=' + kelas;
    }
    
    // Tampilkan tombol hapus hanya jika guru dipilih
    $('#guruId').on('change', function() {
        var guruId = $(this).val();
        // Logic untuk menampilkan/menyembunyikan tombol hapus bisa ditambahkan di sini
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
        window.location.reload();
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

