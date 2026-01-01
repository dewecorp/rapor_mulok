<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Ambil pesan dari URL parameter (setelah redirect)
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'backup_created':
            $success = 'Backup berhasil dibuat!';
            break;
        case 'restore_done':
            $success = 'Restore berhasil dilakukan!';
            break;
        case 'backup_deleted':
            $success = 'Backup berhasil dihapus!';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'backup_failed':
            $error = 'Gagal membuat backup!';
            break;
        case 'restore_failed':
            $error = 'Gagal melakukan restore!';
            break;
        case 'delete_failed':
            $error = 'Gagal menghapus backup!';
            break;
        case 'file_not_found':
            $error = 'File backup tidak ditemukan!';
            break;
        case 'backup_not_found':
            $error = 'Backup tidak ditemukan!';
            break;
        case 'invalid_file_format':
            $error = 'Format file tidak valid! Hanya file .sql yang diperbolehkan.';
            break;
        case 'no_file_uploaded':
            $error = 'Tidak ada file yang diupload!';
            break;
    }
}

// Handle Backup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'backup') {
    $backup_dir = '../backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_file = $backup_dir . $backup_name;
    
    // Ambil semua tabel
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $output = "-- Backup Database Rapor Mulok Khusus\n";
    $output .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n\n";
    $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $output .= "START TRANSACTION;\n";
    $output .= "SET time_zone = \"+00:00\";\n\n";
    
    foreach ($tables as $table) {
        // Drop table
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Create table
        $create_table = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $create_table->fetch_array();
        $output .= $row[1] . ";\n\n";
        
        // Insert data
        $result = $conn->query("SELECT * FROM `$table`");
        if ($result->num_rows > 0) {
            $output .= "INSERT INTO `$table` VALUES\n";
            $values = [];
            while ($row = $result->fetch_assoc()) {
                $vals = [];
                foreach ($row as $val) {
                    $vals[] = $val === null ? 'NULL' : "'" . $conn->real_escape_string($val) . "'";
                }
                $values[] = "(" . implode(",", $vals) . ")";
            }
            $output .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    $output .= "COMMIT;\n";
    
    if (file_put_contents($backup_file, $output)) {
        $file_size = filesize($backup_file);
        $stmt = $conn->prepare("INSERT INTO backup (nama_backup, file_path, ukuran_file) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $backup_name, $backup_file, $file_size);
        $stmt->execute();
        
        // Redirect untuk mencegah resubmit
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: index.php?success=backup_created');
        exit();
    } else {
        // Redirect dengan error
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: index.php?error=backup_failed');
        exit();
    }
}

// Handle Restore
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'restore') {
    $backup_id = intval($_POST['backup_id'] ?? 0);
    
    try {
        $stmt = $conn->prepare("SELECT file_path FROM backup WHERE id = ?");
        $stmt->bind_param("i", $backup_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $backup = $result->fetch_assoc();
            $file_path = $backup['file_path'];
            
            if (file_exists($file_path)) {
                $sql = file_get_contents($file_path);
                
                // Execute SQL
                $conn->multi_query($sql);
                
                // Clear multi_query results
                while ($conn->next_result()) {
                    if ($result = $conn->store_result()) {
                        $result->free();
                    }
                }
                
                // Redirect untuk mencegah resubmit
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: index.php?success=restore_done');
                exit();
            } else {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: index.php?error=file_not_found');
                exit();
            }
        } else {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            header('Location: index.php?error=backup_not_found');
            exit();
        }
    } catch (Exception $e) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: index.php?error=restore_failed');
        exit();
    }
}

// Handle Restore from Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'restore_upload') {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
        $file = $_FILES['backup_file'];
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (strtolower($file_ext) !== 'sql') {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            header('Location: index.php?error=invalid_file_format');
            exit();
        }
        
        $sql = file_get_contents($file['tmp_name']);
        
        try {
            // Execute SQL
            $conn->multi_query($sql);
            
            // Clear multi_query results
            while ($conn->next_result()) {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            }
            
            // Redirect untuk mencegah resubmit
            if (ob_get_level() > 0) {
                ob_clean();
            }
            header('Location: index.php?success=restore_done');
            exit();
        } catch (Exception $e) {
            if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: index.php?error=restore_failed');
        exit();
        }
    } else {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: index.php?error=no_file_uploaded');
        exit();
    }
}

// Handle Delete Backup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $backup_id = intval($_POST['backup_id'] ?? 0);
    
    try {
        $stmt = $conn->prepare("SELECT file_path FROM backup WHERE id = ?");
        $stmt->bind_param("i", $backup_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $backup = $result->fetch_assoc();
            $file_path = $backup['file_path'];
            
            // Hapus file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Hapus dari database
            $stmt_delete = $conn->prepare("DELETE FROM backup WHERE id=?");
            $stmt_delete->bind_param("i", $backup_id);
            
            if ($stmt_delete->execute()) {
                // Redirect untuk mencegah resubmit
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: index.php?success=backup_deleted');
                exit();
            } else {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                header('Location: index.php?error=delete_failed');
                exit();
            }
        } else {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            header('Location: index.php?error=backup_not_found');
            exit();
        }
    } catch (Exception $e) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Location: index.php?error=delete_failed');
        exit();
    }
}

// Format ukuran file
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Ambil semua backup
$backup_list = null;
$total_backup = 0;
$total_size = 0;
try {
    // Cek apakah tabel backup ada
    $check_table = $conn->query("SHOW TABLES LIKE 'backup'");
    if ($check_table && $check_table->num_rows > 0) {
        $query = "SELECT * FROM backup ORDER BY created_at DESC";
        $backup_list = $conn->query($query);
        if (!$backup_list) {
            $error = 'Error query: ' . $conn->error;
            $backup_list = null;
        } else {
            $total_backup = $backup_list->num_rows;
            // Hitung total ukuran
            $backup_list->data_seek(0);
            while ($row = $backup_list->fetch_assoc()) {
                $total_size += $row['ukuran_file'] ?? 0;
            }
            $backup_list->data_seek(0); // Reset pointer
        }
    } else {
        // Tabel belum ada, buat tabel
        $create_table = "CREATE TABLE IF NOT EXISTS `backup` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_backup` varchar(255) NOT NULL,
            `file_path` varchar(500) NOT NULL,
            `ukuran_file` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($create_table);
        $backup_list = null;
    }
} catch (Exception $e) {
    // Tabel backup mungkin belum ada, tidak masalah
    $backup_list = null;
}

// Set page title (variabel lokal)
$page_title = 'Backup & Restore';
?>
<?php include '../includes/header.php'; ?>

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

<!-- Box Backup dan Restore Berjajar -->
<div class="row mb-4">
    <!-- Box Backup Database -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header" style="background-color: #2d5016; color: white;">
                <h5 class="mb-0"><i class="fas fa-database"></i> Backup Database</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Buat backup database untuk keamanan data</p>
                
                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <div>
                        <strong>Info:</strong> Backup akan menyimpan semua tabel dan data ke file SQL
                    </div>
                </div>
                
                <form method="POST" id="formBackup">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-download"></i> Buat Backup Sekarang
                    </button>
                </form>
                
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-clock"></i> Proses backup mungkin memakan waktu beberapa menit tergantung ukuran database
                </small>
            </div>
        </div>
    </div>
    
    <!-- Box Restore Database -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header" style="background-color: #2d5016; color: white;">
                <h5 class="mb-0"><i class="fas fa-upload"></i> Restore Database</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Restore database dari file backup</p>
                
                <div class="alert alert-warning d-flex align-items-center mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>
                        <strong>Peringatan:</strong> Restore akan mengganti semua data yang ada dengan data dari backup!
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="formRestoreUpload">
                    <input type="hidden" name="action" value="restore_upload">
                    <div class="mb-3">
                        <label class="form-label">Pilih File Backup (.sql)</label>
                        <input type="file" class="form-control" name="backup_file" accept=".sql" required>
                        <small class="text-muted">Hanya file dengan format .sql yang diperbolehkan</small>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-upload"></i> Restore Database
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Box Daftar Backup -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #2d5016; color: white;">
        <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Backup</h5>
        <div>
            <span class="badge bg-light text-dark me-2"><?php echo $total_backup; ?> File</span>
            <span class="badge bg-light text-dark">Total: <?php echo formatBytes($total_size); ?></span>
        </div>
    </div>
    <div class="card-body">
        <?php if ($backup_list && $backup_list->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tableBackup">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Nama Backup</th>
                        <th>Tanggal Backup</th>
                        <th>Ukuran File</th>
                        <th width="250">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    while ($row = $backup_list->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_backup'] ?? '-'); ?></td>
                            <td><?php echo isset($row['created_at']) ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-'; ?></td>
                            <td><?php echo isset($row['ukuran_file']) ? formatBytes($row['ukuran_file']) : '-'; ?></td>
                            <td>
                                <?php if (isset($row['file_path'])): 
                                    // Convert relative path to absolute URL
                                    $download_path = str_replace('../', '', $row['file_path']);
                                ?>
                                <a href="<?php echo htmlspecialchars($download_path); ?>" class="btn btn-sm btn-primary" download>
                                    <i class="fas fa-download"></i> Unduh
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-warning" onclick="restoreBackup(<?php echo $row['id']; ?>)" title="Restore">
                                    <i class="fas fa-upload"></i> Restore
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteBackup(<?php echo $row['id']; ?>)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Belum ada data backup. Klik tombol "Buat Backup Sekarang" untuk membuat backup database.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if ($backup_list && $backup_list->num_rows > 0): ?>
        $('#tableBackup').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            order: [[2, 'desc']] // Sort by tanggal backup descending
        });
        <?php endif; ?>
    });
    
    // Handle form backup dengan loading
    $('#formBackup').on('submit', function(e) {
        Swal.fire({
            title: 'Memproses Backup...',
            text: 'Mohon tunggu, proses backup sedang berjalan',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
    
    // Handle form restore upload dengan loading
    $('#formRestoreUpload').on('submit', function(e) {
        Swal.fire({
            title: 'Memproses Restore...',
            text: 'Mohon tunggu, proses restore sedang berjalan. Jangan tutup halaman ini!',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
    
    function restoreBackup(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Semua data akan diganti dengan data dari backup ini!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Restore!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Restore...',
                    text: 'Mohon tunggu, proses restore sedang berjalan. Jangan tutup halaman ini!',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="restore">' +
                                '<input type="hidden" name="backup_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    function deleteBackup(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Backup yang dihapus tidak dapat dikembalikan!",
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
                                '<input type="hidden" name="backup_id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: '<?php echo addslashes($success); ?>',
        confirmButtonColor: '#2d5016',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false
    }).then(() => {
        // Hapus parameter dari URL untuk mencegah resubmit
        window.location.href = window.location.pathname;
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

