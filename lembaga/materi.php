<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();
$success = '';
$error = '';

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
$label_kategori = $use_kategori ? 'Kategori Mulok' : 'Kode Mulok';

// Cek apakah kolom kelas_id sudah ada
$has_kelas_id = false;
try {
    $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
    $has_kelas_id = ($check_kelas_id && $check_kelas_id->num_rows > 0);
} catch (Exception $e) {
    $has_kelas_id = false;
}

// Cek apakah kolom jumlah_jam masih ada (untuk fallback)
$has_jumlah_jam = false;
try {
    $check_jumlah_jam = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'jumlah_jam'");
    $has_jumlah_jam = ($check_jumlah_jam && $check_jumlah_jam->num_rows > 0);
} catch (Exception $e) {
    $has_jumlah_jam = false;
}

// Jika kelas_id belum ada, tampilkan pesan error dan redirect ke migration
if (!$has_kelas_id && !$has_jumlah_jam) {
    $error = 'Kolom kelas_id belum ada di database. Silakan jalankan migrasi terlebih dahulu: <a href="migrate_jumlah_jam_to_kelas_id.php">Jalankan Migrasi</a>';
} else if (!$has_kelas_id) {
    // Fallback: masih menggunakan jumlah_jam jika kelas_id belum ada
    $error = 'Kolom kelas_id belum ada. Silakan jalankan migrasi: <a href="migrate_jumlah_jam_to_kelas_id.php">Jalankan Migrasi</a>';
}

// Ambil data kelas untuk dropdown (exclude kelas Alumni)
$kelas_list = null;
try {
    $kelas_list = $conn->query("SELECT * FROM kelas WHERE nama_kelas NOT LIKE '%Alumni%' AND nama_kelas NOT LIKE '%Lulus%' ORDER BY nama_kelas");
} catch (Exception $e) {
    if (empty($error)) {
        $error = 'Error mengambil data kelas: ' . $e->getMessage();
    }
}

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
            // Trim dan terima semua format (case-insensitive)
            $kategori_mulok = trim($_POST[$kolom_kategori] ?? '');
            $nama_mulok = trim($_POST['nama_mulok'] ?? '');
            
            if (!$has_kelas_id) {
                $error = 'Kolom kelas_id belum ada di database. Silakan jalankan migrasi terlebih dahulu!';
            } else {
                $kelas_id = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : null;
                
                try {
                    if ($kelas_id !== null && $kelas_id > 0) {
                        $stmt = $conn->prepare("INSERT INTO materi_mulok ($kolom_kategori, nama_mulok, kelas_id) VALUES (?, ?, ?)");
                        $stmt->bind_param("ssi", $kategori_mulok, $nama_mulok, $kelas_id);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO materi_mulok ($kolom_kategori, nama_mulok, kelas_id) VALUES (?, ?, NULL)");
                        $stmt->bind_param("ss", $kategori_mulok, $nama_mulok);
                    }
                    
                    if ($stmt->execute()) {
                        $success = 'Materi mulok berhasil ditambahkan!';
                    } else {
                        $error = 'Gagal menambahkan materi mulok! Error: ' . $stmt->error;
                    }
                } catch (Exception $e) {
                    $error = 'Gagal menambahkan materi mulok! Error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            // Trim dan terima semua format (case-insensitive)
            $kategori_mulok = trim($_POST[$kolom_kategori] ?? '');
            $nama_mulok = trim($_POST['nama_mulok'] ?? '');
            
            if (!$has_kelas_id) {
                $error = 'Kolom kelas_id belum ada di database. Silakan jalankan migrasi terlebih dahulu!';
            } else {
                $kelas_id = !empty($_POST['kelas_id']) ? intval($_POST['kelas_id']) : null;
                
                try {
                    if ($kelas_id !== null && $kelas_id > 0) {
                        $stmt = $conn->prepare("UPDATE materi_mulok SET $kolom_kategori=?, nama_mulok=?, kelas_id=? WHERE id=?");
                        $stmt->bind_param("ssii", $kategori_mulok, $nama_mulok, $kelas_id, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE materi_mulok SET $kolom_kategori=?, nama_mulok=?, kelas_id=NULL WHERE id=?");
                        $stmt->bind_param("ssi", $kategori_mulok, $nama_mulok, $id);
                    }
                    
                    if ($stmt->execute()) {
                        $success = 'Materi mulok berhasil diperbarui!';
                    } else {
                        $error = 'Gagal memperbarui materi mulok! Error: ' . $stmt->error;
                    }
                } catch (Exception $e) {
                    $error = 'Gagal memperbarui materi mulok! Error: ' . $e->getMessage();
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
                    redirect(basename($_SERVER['PHP_SELF']), false);
                } else {
                    $_SESSION['error_message'] = 'Gagal menghapus materi mulok! Error: ' . $conn->error;
                    redirect(basename($_SERVER['PHP_SELF']), false);
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Gagal menghapus materi mulok! Error: ' . $e->getMessage();
                // Pastikan tidak ada output sebelum redirect
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: ' . basename($_SERVER['PHP_SELF']));
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
        if ($has_kelas_id) {
            $stmt = $conn->prepare("SELECT m.*, k.nama_kelas FROM materi_mulok m LEFT JOIN kelas k ON m.kelas_id = k.id WHERE m.id = ?");
        } else {
            $stmt = $conn->prepare("SELECT * FROM materi_mulok WHERE id = ?");
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fungsi untuk mendapatkan warna badge berdasarkan kategori (case-insensitive)
function getBadgeColor($kategori) {
    if (empty($kategori)) {
        return 'bg-secondary';
    }
    
    // Normalisasi: trim dan lowercase untuk case-insensitive
    $kategori_normalized = strtolower(trim($kategori));
    
    // Mapping warna badge untuk 3 kategori khusus (case-insensitive)
    if ($kategori_normalized === 'hafalan') {
        return 'bg-info';
    } elseif ($kategori_normalized === 'membaca') {
        return 'bg-primary';
    } elseif ($kategori_normalized === 'praktik ibadah' || $kategori_normalized === 'praktikibadah') {
        return 'bg-warning';
    }
    
    // Default untuk kategori lain (jika ada)
    return 'bg-secondary';
}

// Ambil semua data
$result = null;
$materi_data = [];
try {
    if ($has_kelas_id) {
        // Ambil semua data dengan JOIN ke tabel kelas, urut berdasarkan kategori kemudian nama (case-insensitive)
        $query = "SELECT m.*, k.nama_kelas 
                  FROM materi_mulok m 
                  LEFT JOIN kelas k ON m.kelas_id = k.id 
                  ORDER BY LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
    } else {
        // Fallback: ambil data tanpa JOIN jika kelas_id belum ada
        $query = "SELECT * FROM materi_mulok ORDER BY LOWER($kolom_kategori) ASC, LOWER(nama_mulok) ASC";
    }
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
            echo '<thead><tr><th>ID</th><th>' . $label_kategori . '</th><th>Nama Mulok</th><th>Kelas</th></tr></thead>';
            echo '<tbody>';
            if ($has_kelas_id) {
                $debug_query = "SELECT m.*, k.nama_kelas FROM materi_mulok m LEFT JOIN kelas k ON m.kelas_id = k.id ORDER BY m.id";
            } else {
                $debug_query = "SELECT * FROM materi_mulok ORDER BY id";
            }
            $debug_result = $conn->query($debug_query);
            if ($debug_result && $debug_result->num_rows > 0) {
                while ($debug_row = $debug_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . $debug_row['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($debug_row[$kolom_kategori] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($debug_row['nama_mulok']) . '</td>';
                    echo '<td>' . htmlspecialchars($has_kelas_id ? ($debug_row['nama_kelas'] ?? '-') : '-') . '</td>';
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
                        <th><?php echo $label_kategori; ?></th>
                        <th>Nama Mulok</th>
                        <th>Kelas</th>
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
                            <td>
                                <?php 
                                $kategori_value = $row[$kolom_kategori] ?? '';
                                if (!empty($kategori_value)): 
                                    $badge_color = getBadgeColor($kategori_value);
                                ?>
                                    <span class="badge <?php echo $badge_color; ?>"><?php echo htmlspecialchars($kategori_value); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_mulok']); ?></td>
                            <td><?php echo htmlspecialchars($has_kelas_id ? ($row['nama_kelas'] ?? '-') : '-'); ?></td>
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
                        <label class="form-label"><?php echo $label_kategori; ?></label>
                        <input type="text" class="form-control" name="<?php echo $kolom_kategori; ?>" id="kategoriMulok">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Mulok</label>
                        <input type="text" class="form-control" name="nama_mulok" id="namaMulok">
                    </div>
                    
                    <?php if ($has_kelas_id): ?>
                    <div class="mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" name="kelas_id" id="kelasId" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php if ($kelas_list): 
                                $kelas_list->data_seek(0);
                                while ($kelas = $kelas_list->fetch_assoc()): 
                                    // Skip kelas Alumni (double check untuk keamanan)
                                    if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                        continue;
                                    }
                            ?>
                                <option value="<?php echo $kelas['id']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                            <?php 
                                endwhile;
                            endif; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <div class="alert alert-warning">
                            <strong>Perhatian:</strong> Kolom kelas_id belum ada di database. 
                            <a href="migrate_jumlah_jam_to_kelas_id.php" class="alert-link">Jalankan migrasi terlebih dahulu</a>.
                        </div>
                    </div>
                    <?php endif; ?>
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
            order: [[1, 'asc'], [2, 'asc']], // Sort by Kategori Mulok then Nama Mulok ascending
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
        $('#kategoriMulok').val('<?php echo addslashes($edit_data[$kolom_kategori] ?? ''); ?>');
        $('#namaMulok').val('<?php echo addslashes($edit_data['nama_mulok']); ?>');
        <?php if ($has_kelas_id): ?>
        $('#kelasId').val(<?php echo $edit_data['kelas_id'] ?? 'null'; ?>);
        <?php endif; ?>
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

