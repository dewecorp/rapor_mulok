<?php
/**
 * VERSI PERBAIKAN: Materi Mulok dengan dukungan struktur database lama dan baru
 * File ini akan menggantikan materi.php setelah testing
 */

require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Cek struktur database terlebih dahulu
$has_kategori_mulok = false;
$has_kode_mulok = false;
$has_kelas_id = false;
$has_semester = false;
$has_jumlah_jam = false;

try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kategori_mulok') $has_kategori_mulok = true;
            if ($col['Field'] == 'kode_mulok') $has_kode_mulok = true;
            if ($col['Field'] == 'kelas_id') $has_kelas_id = true;
            if ($col['Field'] == 'semester') $has_semester = true;
            if ($col['Field'] == 'jumlah_jam') $has_jumlah_jam = true;
        }
    }
} catch (Exception $e) {
    $error = 'Error mengecek struktur database: ' . $e->getMessage();
}

// Tentukan kolom yang digunakan
$kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
$use_kelas_semester = ($has_kelas_id && $has_semester);

// Ambil pesan dari session
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
            $kategori = trim($_POST['kategori_mulok'] ?? $_POST['kode_mulok'] ?? '');
            $nama_mulok = trim($_POST['nama_mulok'] ?? '');
            
            // Validasi input
            if (empty($kategori)) {
                $error = ($has_kategori_mulok ? 'Kategori' : 'Kode') . ' Mulok tidak boleh kosong!';
            } elseif (empty($nama_mulok)) {
                $error = 'Nama Mulok tidak boleh kosong!';
            } else {
                try {
                    if ($use_kelas_semester) {
                        $kelas_id = intval($_POST['kelas_id'] ?? 0);
                        $semester = trim($_POST['semester'] ?? '1');
                        
                        if ($kelas_id <= 0) {
                            $error = 'Kelas harus dipilih!';
                        } else {
                            $stmt = $conn->prepare("INSERT INTO materi_mulok ($kolom_kategori, nama_mulok, kelas_id, semester) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("ssis", $kategori, $nama_mulok, $kelas_id, $semester);
                            
                            if ($stmt->execute()) {
                                $success = 'Materi mulok berhasil ditambahkan!';
                            } else {
                                $error = 'Gagal menambahkan materi mulok! Error: ' . $stmt->error;
                            }
                        }
                    } elseif ($has_jumlah_jam) {
                        $jumlah_jam = intval($_POST['jumlah_jam'] ?? 0);
                        $stmt = $conn->prepare("INSERT INTO materi_mulok ($kolom_kategori, nama_mulok, jumlah_jam) VALUES (?, ?, ?)");
                        $stmt->bind_param("ssi", $kategori, $nama_mulok, $jumlah_jam);
                        
                        if ($stmt->execute()) {
                            $success = 'Materi mulok berhasil ditambahkan!';
                        } else {
                            $error = 'Gagal menambahkan materi mulok! Error: ' . $stmt->error;
                        }
                    } else {
                        $stmt = $conn->prepare("INSERT INTO materi_mulok ($kolom_kategori, nama_mulok) VALUES (?, ?)");
                        $stmt->bind_param("ss", $kategori, $nama_mulok);
                        
                        if ($stmt->execute()) {
                            $success = 'Materi mulok berhasil ditambahkan!';
                        } else {
                            $error = 'Gagal menambahkan materi mulok! Error: ' . $stmt->error;
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Gagal menambahkan materi mulok! Error: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $kategori = trim($_POST['kategori_mulok'] ?? $_POST['kode_mulok'] ?? '');
            $nama_mulok = trim($_POST['nama_mulok'] ?? '');
            
            if (empty($kategori)) {
                $error = ($has_kategori_mulok ? 'Kategori' : 'Kode') . ' Mulok tidak boleh kosong!';
            } elseif (empty($nama_mulok)) {
                $error = 'Nama Mulok tidak boleh kosong!';
            } elseif ($id <= 0) {
                $error = 'ID tidak valid!';
            } else {
                try {
                    if ($use_kelas_semester) {
                        $kelas_id = intval($_POST['kelas_id'] ?? 0);
                        $semester = trim($_POST['semester'] ?? '1');
                        
                        if ($kelas_id <= 0) {
                            $error = 'Kelas harus dipilih!';
                        } else {
                            $stmt = $conn->prepare("UPDATE materi_mulok SET $kolom_kategori=?, nama_mulok=?, kelas_id=?, semester=? WHERE id=?");
                            $stmt->bind_param("ssisi", $kategori, $nama_mulok, $kelas_id, $semester, $id);
                            
                            if ($stmt->execute()) {
                                $success = 'Materi mulok berhasil diperbarui!';
                            } else {
                                $error = 'Gagal memperbarui materi mulok! Error: ' . $stmt->error;
                            }
                        }
                    } elseif ($has_jumlah_jam) {
                        $jumlah_jam = intval($_POST['jumlah_jam'] ?? 0);
                        $stmt = $conn->prepare("UPDATE materi_mulok SET $kolom_kategori=?, nama_mulok=?, jumlah_jam=? WHERE id=?");
                        $stmt->bind_param("ssii", $kategori, $nama_mulok, $jumlah_jam, $id);
                        
                        if ($stmt->execute()) {
                            $success = 'Materi mulok berhasil diperbarui!';
                        } else {
                            $error = 'Gagal memperbarui materi mulok! Error: ' . $stmt->error;
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE materi_mulok SET $kolom_kategori=?, nama_mulok=? WHERE id=?");
                        $stmt->bind_param("ssi", $kategori, $nama_mulok, $id);
                        
                        if ($stmt->execute()) {
                            $success = 'Materi mulok berhasil diperbarui!';
                        } else {
                            $error = 'Gagal memperbarui materi mulok! Error: ' . $stmt->error;
                        }
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
                    $_SESSION['success_message'] = 'Materi mulok berhasil dihapus!';
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header('Location: materi.php');
                    exit();
                } else {
                    $_SESSION['error_message'] = 'Gagal menghapus materi mulok! Error: ' . $conn->error;
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header('Location: materi.php');
                    exit();
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Gagal menghapus materi mulok! Error: ' . $e->getMessage();
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: materi.php');
                exit();
            }
        }
    }
}

// Ambil data untuk edit
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

// Ambil parameter filter
$filter_kategori = isset($_GET['filter_kategori']) ? trim($_GET['filter_kategori']) : '';
$filter_nama = isset($_GET['filter_nama']) ? trim($_GET['filter_nama']) : '';
$filter_kelas = isset($_GET['filter_kelas']) ? intval($_GET['filter_kelas']) : 0;
$filter_semester = isset($_GET['filter_semester']) ? trim($_GET['filter_semester']) : '';

// Ambil semua data dengan ORDER BY yang benar dan filter
$materi_data = [];
try {
    // Tentukan kolom untuk ORDER BY
    $order_by = $has_kategori_mulok ? 'kategori_mulok' : ($has_kode_mulok ? 'kode_mulok' : 'id');
    
    // Build query dengan filter
    $params = [];
    $types = '';
    
    if ($use_kelas_semester) {
        $query = "SELECT m.*, k.nama_kelas 
                  FROM materi_mulok m 
                  LEFT JOIN kelas k ON m.kelas_id = k.id 
                  WHERE 1=1";
        
        // Filter kategori/kode
        if (!empty($filter_kategori)) {
            $query .= " AND m.$kolom_kategori LIKE ?";
            $params[] = '%' . $filter_kategori . '%';
            $types .= 's';
        }
        
        // Filter nama mulok
        if (!empty($filter_nama)) {
            $query .= " AND m.nama_mulok LIKE ?";
            $params[] = '%' . $filter_nama . '%';
            $types .= 's';
        }
        
        // Filter kelas
        if ($filter_kelas > 0) {
            $query .= " AND m.kelas_id = ?";
            $params[] = $filter_kelas;
            $types .= 'i';
        }
        
        // Filter semester
        if (!empty($filter_semester)) {
            $query .= " AND m.semester = ?";
            $params[] = $filter_semester;
            $types .= 's';
        }
        
        $query .= " ORDER BY m.$order_by ASC";
    } else {
        $query = "SELECT * FROM materi_mulok WHERE 1=1";
        
        // Filter kategori/kode
        if (!empty($filter_kategori)) {
            $query .= " AND $kolom_kategori LIKE ?";
            $params[] = '%' . $filter_kategori . '%';
            $types .= 's';
        }
        
        // Filter nama mulok
        if (!empty($filter_nama)) {
            $query .= " AND nama_mulok LIKE ?";
            $params[] = '%' . $filter_nama . '%';
            $types .= 's';
        }
        
        $query .= " ORDER BY $order_by ASC";
    }
    
    // Execute query
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($query);
    }
    
    if (!$result) {
        $error = 'Error query: ' . $conn->error;
    } else {
        while ($row = $result->fetch_assoc()) {
            $materi_data[] = $row;
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

// Ambil daftar kategori unik untuk dropdown filter
$kategori_list = [];
try {
    $query_kategori = "SELECT DISTINCT $kolom_kategori FROM materi_mulok WHERE $kolom_kategori IS NOT NULL AND $kolom_kategori != '' ORDER BY $kolom_kategori";
    $result_kategori = $conn->query($query_kategori);
    if ($result_kategori) {
        while ($row = $result_kategori->fetch_assoc()) {
            $kategori_list[] = $row[$kolom_kategori];
        }
    }
} catch (Exception $e) {
    // Ignore
}

// Ambil data kelas untuk dropdown (jika menggunakan kelas_id)
$kelas_data = [];
if ($use_kelas_semester) {
    try {
        $result_kelas = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas");
        if ($result_kelas) {
            while ($row = $result_kelas->fetch_assoc()) {
                $kelas_data[] = $row;
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// Set page title (variabel lokal)
$page_title = 'Materi Mulok';

include '../includes/header.php';
?>

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
        
        <!-- Filter Form -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="materi.php" id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><?php echo $has_kategori_mulok ? 'Kategori' : 'Kode'; ?> Mulok</label>
                            <select class="form-select form-select-sm" name="filter_kategori" id="filterKategori">
                                <option value="">-- Semua <?php echo $has_kategori_mulok ? 'Kategori' : 'Kode'; ?> --</option>
                                <?php foreach ($kategori_list as $kat): ?>
                                    <option value="<?php echo htmlspecialchars($kat); ?>" <?php echo $filter_kategori == $kat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nama Mulok</label>
                            <input type="text" class="form-control form-control-sm" name="filter_nama" id="filterNama" 
                                   value="<?php echo htmlspecialchars($filter_nama); ?>" 
                                   placeholder="Cari nama mulok...">
                        </div>
                        <?php if ($use_kelas_semester): ?>
                        <div class="col-md-2">
                            <label class="form-label">Kelas</label>
                            <select class="form-select form-select-sm" name="filter_kelas" id="filterKelas">
                                <option value="">-- Semua Kelas --</option>
                                <?php foreach ($kelas_data as $kelas): 
                                    // Skip kelas Alumni dari filter
                                    if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                        continue;
                                    }
                                ?>
                                    <option value="<?php echo $kelas['id']; ?>" <?php echo $filter_kelas == $kelas['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Semester</label>
                            <select class="form-select form-select-sm" name="filter_semester" id="filterSemester">
                                <option value="">-- Semua Semester --</option>
                                <option value="1" <?php echo $filter_semester == '1' ? 'selected' : ''; ?>>Semester 1</option>
                                <option value="2" <?php echo $filter_semester == '2' ? 'selected' : ''; ?>>Semester 2</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="materi.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableMateri">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th><?php echo $has_kategori_mulok ? 'Kategori Mulok' : 'Kode Mulok'; ?></th>
                        <th>Nama Mulok</th>
                        <?php if ($use_kelas_semester): ?>
                            <th>Kelas</th>
                            <th>Semester</th>
                        <?php elseif ($has_jumlah_jam): ?>
                            <th>Jumlah Jam</th>
                        <?php endif; ?>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($materi_data) > 0):
                        $no = 1;
                        foreach ($materi_data as $row): 
                            $kategori_value = $row[$kolom_kategori] ?? '';
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <?php 
                                $kategori_lower = strtolower(trim($kategori_value));
                                $badge_class = 'bg-secondary'; // default abu-abu
                                
                                if (strpos($kategori_lower, 'hafalan') !== false) {
                                    $badge_class = 'bg-info'; // biru
                                } elseif (strpos($kategori_lower, 'membaca') !== false) {
                                    $badge_class = 'bg-success'; // hijau
                                } elseif (strpos($kategori_lower, 'praktik ibadah') !== false || strpos($kategori_lower, 'praktik') !== false) {
                                    $badge_class = 'bg-primary'; // primary
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?> text-white"><?php echo htmlspecialchars($kategori_value); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_mulok']); ?></td>
                            <?php if ($use_kelas_semester): ?>
                                <td>
                                    <?php 
                                    // Nama kelas sudah diambil dari JOIN, atau ambil dari row
                                    if (!empty($row['nama_kelas'])) {
                                        echo htmlspecialchars($row['nama_kelas']);
                                    } elseif (!empty($row['kelas_id'])) {
                                        // Fallback jika JOIN tidak berhasil
                                        try {
                                            $stmt_kelas = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
                                            $stmt_kelas->bind_param("i", $row['kelas_id']);
                                            $stmt_kelas->execute();
                                            $result_kelas = $stmt_kelas->get_result();
                                            if ($result_kelas && $result_kelas->num_rows > 0) {
                                                $kelas_row = $result_kelas->fetch_assoc();
                                                echo htmlspecialchars($kelas_row['nama_kelas']);
                                            } else {
                                                echo '-';
                                            }
                                        } catch (Exception $e) {
                                            echo '-';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['semester'] ?? '-'); ?></td>
                            <?php elseif ($has_jumlah_jam): ?>
                                <td><?php echo htmlspecialchars($row['jumlah_jam'] ?? '0'); ?> Jam</td>
                            <?php endif; ?>
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
                            <td colspan="<?php echo $use_kelas_semester ? '6' : ($has_jumlah_jam ? '5' : '4'); ?>" class="text-center text-muted">Belum ada data materi mulok</td>
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
                        <label class="form-label"><?php echo $has_kategori_mulok ? 'Kategori' : 'Kode'; ?> Mulok <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="<?php echo $kolom_kategori; ?>" id="kategoriMulok" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Mulok <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mulok" id="namaMulok" required>
                    </div>
                    
                    <?php if ($use_kelas_semester): ?>
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_id" id="kelasId" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($kelas_data as $kelas): 
                                    // Skip kelas Alumni dari dropdown
                                    if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                        continue;
                                    }
                                ?>
                                    <option value="<?php echo $kelas['id']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" name="semester" id="semester" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                        </div>
                    <?php elseif ($has_jumlah_jam): ?>
                        <div class="mb-3">
                            <label class="form-label">Jumlah Jam <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="jumlah_jam" id="jumlahJam" min="0" required>
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
            order: [[1, 'asc']],
            pageLength: 25
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
        // Ambil parameter filter dari URL
        var urlParams = new URLSearchParams(window.location.search);
        var filterKategori = urlParams.get('filter_kategori') || '';
        var filterNama = urlParams.get('filter_nama') || '';
        var filterKelas = urlParams.get('filter_kelas') || '';
        var filterSemester = urlParams.get('filter_semester') || '';
        
        var exportUrl = 'export_materi.php?format=excel';
        if (filterKategori) exportUrl += '&filter_kategori=' + encodeURIComponent(filterKategori);
        if (filterNama) exportUrl += '&filter_nama=' + encodeURIComponent(filterNama);
        if (filterKelas) exportUrl += '&filter_kelas=' + encodeURIComponent(filterKelas);
        if (filterSemester) exportUrl += '&filter_semester=' + encodeURIComponent(filterSemester);
        
        window.open(exportUrl, '_blank');
    }
    
    function exportPDF() {
        // Ambil parameter filter dari URL
        var urlParams = new URLSearchParams(window.location.search);
        var filterKategori = urlParams.get('filter_kategori') || '';
        var filterNama = urlParams.get('filter_nama') || '';
        var filterKelas = urlParams.get('filter_kelas') || '';
        var filterSemester = urlParams.get('filter_semester') || '';
        
        var exportUrl = 'export_materi.php?format=pdf';
        if (filterKategori) exportUrl += '&filter_kategori=' + encodeURIComponent(filterKategori);
        if (filterNama) exportUrl += '&filter_nama=' + encodeURIComponent(filterNama);
        if (filterKelas) exportUrl += '&filter_kelas=' + encodeURIComponent(filterKelas);
        if (filterSemester) exportUrl += '&filter_semester=' + encodeURIComponent(filterSemester);
        
        window.open(exportUrl, '_blank');
    }
    
    // Reset form saat modal ditutup
    $('#modalMateri').on('hidden.bs.modal', function() {
        $('#formMateri')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Materi Mulok');
    });
    
    // Load data untuk edit
    <?php if ($edit_data && empty($success) && empty($error)): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        $('#kategoriMulok').val('<?php echo addslashes($edit_data[$kolom_kategori] ?? ''); ?>');
        $('#namaMulok').val('<?php echo addslashes($edit_data['nama_mulok']); ?>');
        <?php if ($use_kelas_semester): ?>
            $('#kelasId').val('<?php echo $edit_data['kelas_id'] ?? ''; ?>');
            $('#semester').val('<?php echo $edit_data['semester'] ?? '1'; ?>');
        <?php elseif ($has_jumlah_jam): ?>
            $('#jumlahJam').val(<?php echo $edit_data['jumlah_jam'] ?? 0; ?>);
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
        window.location.href = window.location.pathname;
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

