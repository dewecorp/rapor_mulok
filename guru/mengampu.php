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
                    // Redirect untuk mencegah resubmit dan refresh data
                    // Gunakan kelas_id dari form untuk redirect
                    $_SESSION['success_message'] = 'Data mengampu berhasil ditambahkan!';
                    redirect(basename($_SERVER['PHP_SELF']) . '?kelas=' . $kelas_id, false);
                } else {
                    $_SESSION['error_message'] = 'Gagal menambahkan data mengampu!';
                    redirect(basename($_SERVER['PHP_SELF']) . '?kelas=' . $kelas_id, false);
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Ambil kelas_id dari data yang akan dihapus untuk redirect
            $kelas_id_for_redirect = '';
            try {
                $stmt_get = $conn->prepare("SELECT kelas_id FROM mengampu_materi WHERE id = ?");
                $stmt_get->bind_param("i", $id);
                $stmt_get->execute();
                $result_get = $stmt_get->get_result();
                if ($result_get && $result_get->num_rows > 0) {
                    $row_get = $result_get->fetch_assoc();
                    $kelas_id_for_redirect = $row_get['kelas_id'];
                }
            } catch (Exception $e) {
                // Ignore error
            }
            
            $stmt = $conn->prepare("DELETE FROM mengampu_materi WHERE id=?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Redirect untuk mencegah resubmit dan refresh data
                $_SESSION['success_message'] = 'Data mengampu berhasil dihapus!';
                $redirect_url = basename($_SERVER['PHP_SELF']);
                if ($kelas_id_for_redirect) {
                    $redirect_url .= '?kelas=' . $kelas_id_for_redirect;
                } elseif ($kelas_filter) {
                    $redirect_url .= '?kelas=' . $kelas_filter;
                }
                redirect($redirect_url, false);
            } else {
                $_SESSION['error_message'] = 'Gagal menghapus data mengampu!';
                $redirect_url = basename($_SERVER['PHP_SELF']);
                if ($kelas_id_for_redirect) {
                    $redirect_url .= '?kelas=' . $kelas_id_for_redirect;
                } elseif ($kelas_filter) {
                    $redirect_url .= '?kelas=' . $kelas_filter;
                }
                redirect($redirect_url, false);
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

// Query data materi mulok dengan status mengampu
// Logika: Jika kelas belum dipilih, tabel kosong. Jika kelas dipilih, tampilkan data materi mulok kelas tersebut.
$result = null;
$mengampu_data = [];
try {
    if (!empty($kelas_filter) && $kelas_filter !== '') {
        $kelas_id = intval($kelas_filter);
        // Ambil nama kelas
        $stmt_kelas = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $stmt_kelas->bind_param("i", $kelas_id);
        $stmt_kelas->execute();
        $result_kelas = $stmt_kelas->get_result();
        $kelas_info = $result_kelas->fetch_assoc();
        $nama_kelas = $kelas_info ? $kelas_info['nama_kelas'] : '';
        
        // Tampilkan semua materi mulok dengan LEFT JOIN ke mengampu_materi untuk melihat status
        $query = "SELECT m.*, 
                  mm.id as mengampu_id,
                  mm.guru_id,
                  p.nama as nama_guru,
                  k.nama_kelas
                  FROM materi_mulok m
                  LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                  LEFT JOIN pengguna p ON mm.guru_id = p.id
                  LEFT JOIN kelas k ON mm.kelas_id = k.id
                  ORDER BY m.nama_mulok";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            // Simpan data ke array
            while ($row = $result->fetch_assoc()) {
                // Tambahkan nama_kelas dari filter jika belum ada
                if (empty($row['nama_kelas']) && $nama_kelas) {
                    $row['nama_kelas'] = $nama_kelas;
                }
                $mengampu_data[] = $row;
            }
        }
    } else {
        // Jika kelas belum dipilih, tidak tampilkan data (tabel kosong)
        $result = null;
        $mengampu_data = [];
    }
    
    // Cek jika ada error pada prepared statement
    if ($result === false && empty($error)) {
        $error = 'Error query: ' . $conn->error;
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
    $mengampu_data = [];
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
        
        <?php if (empty($kelas_filter)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Silakan pilih kelas terlebih dahulu untuk melihat data mengampu materi.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableMengampu">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Materi Mulok</th>
                        <th>Jumlah Jam</th>
                        <th>Guru</th>
                        <th width="100">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($mengampu_data) > 0):
                        $no = 1;
                        foreach ($mengampu_data as $row): 
                    ?>
                        <?php 
                            $mengampu_id = $row['mengampu_id'] ?? null;
                            $guru_id = $row['guru_id'] ?? null;
                            $nama_guru = $row['nama_guru'] ?? null;
                            $nama_mulok = $row['nama_mulok'] ?? '';
                            $jumlah_jam = $row['jumlah_jam'] ?? 0;
                            $materi_id = $row['id'] ?? 0; // id dari materi_mulok (karena query menggunakan m.*)
                            $nama_kelas = $row['nama_kelas'] ?? '';
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($nama_mulok); ?></td>
                            <td><?php echo htmlspecialchars($jumlah_jam); ?> Jam</td>
                            <td>
                                <?php if ($nama_guru): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($nama_guru); ?></span>
                                <?php else: ?>
                                    <select class="form-select form-select-sm" style="width: auto; display: inline-block;" onchange="setGuru(<?php echo $materi_id; ?>, this.value, <?php echo $kelas_filter; ?>)">
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
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($mengampu_id): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMengampu(<?php echo $mengampu_id; ?>)" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                <i class="fas fa-info-circle"></i> Belum ada data materi mulok. Silakan tambah materi mulok terlebih dahulu.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
    
    function setGuru(materiId, guruId, kelasId) {
        if (!guruId || !kelasId) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Silakan pilih guru!',
                confirmButtonColor: '#2d5016',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            });
            return;
        }
        
        // Submit form untuk menambahkan data mengampu
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="add">' +
                        '<input type="hidden" name="materi_mulok_id" value="' + materiId + '">' +
                        '<input type="hidden" name="guru_id" value="' + guruId + '">' +
                        '<input type="hidden" name="kelas_id" value="' + kelasId + '">';
        document.body.appendChild(form);
        form.submit();
    }
    
    $(document).ready(function() {
        // Inisialisasi DataTables hanya jika kelas sudah dipilih DAN ada data
        <?php if (!empty($kelas_filter) && count($mengampu_data) > 0): ?>
        // Pastikan tabel ada sebelum inisialisasi DataTables
        if ($('#tableMengampu').length > 0) {
            $('#tableMengampu').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                }
            });
        }
        <?php endif; ?>
    });
    
    function deleteMengampu(id) {
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

