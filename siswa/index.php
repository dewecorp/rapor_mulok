<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');


$conn = getConnection();
$success = '';
$error = '';

// Pastikan kolom orangtua_wali ada di tabel siswa
try {
    $check_column = $conn->query("SHOW COLUMNS FROM siswa LIKE 'orangtua_wali'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE siswa ADD COLUMN orangtua_wali VARCHAR(255) NULL AFTER tanggal_lahir");
    }
} catch (Exception $e) {
    // Kolom mungkin sudah ada atau ada error lain, lanjutkan saja
}

// Filter kelas
$kelas_filter = isset($_GET['kelas']) && $_GET['kelas'] !== '' ? $_GET['kelas'] : '';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $nisn = $_POST['nisn'] ?? '';
            // Bersihkan NISN: hapus semua karakter non-angka
            $nisn = preg_replace('/[^0-9]/', '', $nisn);
            $nama = $_POST['nama'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = $_POST['tempat_lahir'] ?? '';
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $orangtua_wali = $_POST['orangtua_wali'] ?? '';
            // Gunakan kelas_id dari filter jika tidak ada di POST
            $kelas_id = $_POST['kelas_id'] ?? (!empty($kelas_filter) ? $kelas_filter : null);
            
            $stmt = $conn->prepare("INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, orangtua_wali, kelas_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $nisn, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $orangtua_wali, $kelas_id);
            
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
            // Bersihkan NISN: hapus semua karakter non-angka
            $nisn = preg_replace('/[^0-9]/', '', $nisn);
            $nama = $_POST['nama'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
            $tempat_lahir = $_POST['tempat_lahir'] ?? '';
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $orangtua_wali = $_POST['orangtua_wali'] ?? '';
            // Gunakan kelas_id dari filter jika tidak ada di POST
            $kelas_id = $_POST['kelas_id'] ?? (!empty($kelas_filter) ? $kelas_filter : null);
            
            // Ambil kelas lama
            $query_old = "SELECT kelas_id FROM siswa WHERE id = $id";
            $old_kelas = $conn->query($query_old)->fetch_assoc()['kelas_id'];
            
            $stmt = $conn->prepare("UPDATE siswa SET nisn=?, nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, orangtua_wali=?, kelas_id=? WHERE id=?");
            $stmt->bind_param("ssssssii", $nisn, $nama, $jenis_kelamin, $tempat_lahir, $tanggal_lahir, $orangtua_wali, $kelas_id, $id);
            
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

// Ambil data untuk edit (tanpa redirect, tetap di halaman yang sama)
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    try {
        $stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
        // Pastikan kelas_id dari data edit sesuai dengan filter
        if ($edit_data && !empty($kelas_filter)) {
            $edit_data['kelas_id'] = $kelas_filter;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Ambil data kelas
$query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
$kelas_list = $conn->query($query_kelas);

// Query data siswa
// Logika: Jika kelas belum dipilih, tabel kosong. Jika kelas dipilih, tampilkan data siswa kelas tersebut.
$result = null;
$siswa_data = [];
try {
    if (!empty($kelas_filter) && $kelas_filter !== '') {
        // Filter berdasarkan kelas tertentu - tampilkan data siswa kelas terpilih
        $kelas_id = intval($kelas_filter);
        
        $query = "SELECT s.*, k.nama_kelas
                  FROM siswa s
                  LEFT JOIN kelas k ON s.kelas_id = k.id
                  WHERE s.kelas_id = ?
                  ORDER BY s.nama";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $kelas_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $siswa_data[] = $row;
            }
        }
        
        $stmt->close();
    } else {
        // Jika kelas belum dipilih, tidak tampilkan data (tabel kosong)
        $result = null;
        $siswa_data = [];
    }
    
    // Cek jika ada error pada prepared statement
    if ($result === false && empty($error)) {
        $error = 'Error query: ' . $conn->error;
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    $result = null;
    $siswa_data = [];
}

// Set page title (variabel lokal)
$page_title = 'Data Siswa';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Data Siswa</h5>
        <div>
            <?php if (!empty($kelas_filter)): ?>
            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalImportSiswa">
                <i class="fas fa-file-upload"></i> Impor Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalSiswa">
                <i class="fas fa-plus"></i> Tambah
            </button>
            <?php endif; ?>
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
                    // Skip kelas Alumni dari filter
                    if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                        continue;
                    }
                ?>
                    <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_filter == $kelas['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <?php if (!$result && !empty($error)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan saat mengambil data siswa: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif (empty($kelas_filter)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Silakan pilih kelas terlebih dahulu untuk menampilkan data siswa.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped<?php echo count($siswa_data) > 0 ? '' : ' table-empty'; ?>" id="tableSiswa">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>NISN</th>
                        <th>Nama</th>
                        <th>Jenis Kelamin</th>
                        <th>Tempat, Tgl Lahir</th>
                        <th>Orangtua/Wali</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (count($siswa_data) > 0): 
                    ?>
                        <?php 
                        $no = 1;
                        foreach ($siswa_data as $row): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($row['nisn'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(($row['jenis_kelamin'] ?? 'L') == 'L' ? 'Laki-laki' : 'Perempuan', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['tempat_lahir'] ?? '-', ENT_QUOTES, 'UTF-8'); ?><?php echo (!empty($row['tempat_lahir']) && !empty($row['tanggal_lahir'])) ? ', ' : ''; ?><?php echo !empty($row['tanggal_lahir']) ? htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_lahir'])), ENT_QUOTES, 'UTF-8') : ''; ?></td>
                                <td><?php echo htmlspecialchars($row['orangtua_wali'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editSiswa(<?php echo $row['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSiswa(<?php echo $row['id']; ?>)" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (count($siswa_data) == 0): ?>
                <div class="alert alert-info mt-2">
                    <i class="fas fa-info-circle"></i> Belum ada data siswa di kelas yang dipilih.
                </div>
            <?php endif; ?>
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
                            <input type="text" class="form-control" name="nisn" id="nisn" required 
                                   pattern="[0-9]+" 
                                   title="NISN harus berupa angka (tidak boleh format tanggal seperti YYYY-MM-DD)">
                            <small class="text-muted">Format: angka saja (contoh: 1234567890)</small>
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
                            <label class="form-label">Orangtua/Wali</label>
                            <input type="text" class="form-control" name="orangtua_wali" id="orangtuaWali" placeholder="Nama orangtua/wali">
                        </div>
                        <?php if (!empty($kelas_filter)): ?>
                            <input type="hidden" name="kelas_id" id="kelasId" value="<?php echo $kelas_filter; ?>">
                        <?php endif; ?>
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

<!-- Modal Import Siswa -->
<div class="modal fade" id="modalImportSiswa" tabindex="-1" aria-labelledby="modalImportSiswaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #2d5016; color: white;">
                <h5 class="modal-title" id="modalImportSiswaLabel"><i class="fas fa-file-upload"></i> Upload Siswa</h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" onclick="downloadTemplateSiswa()">
                        <i class="fas fa-download"></i> Template Excel
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <!-- Upload Area -->
                <div class="upload-area" id="uploadAreaSiswa" style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer; background-color: #f8f9fa; transition: all 0.3s;">
                    <input type="file" id="fileInputSiswa" accept=".xls,.xlsx" style="display: none;" multiple>
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                    <p class="mb-0" style="color: #6c757d; font-size: 16px;">
                        Letakkan File atau Klik Disini untuk upload
                    </p>
                </div>
                
                <!-- File List Table -->
                <div class="mt-4">
                    <table class="table table-bordered table-sm" id="fileTableSiswa" style="display: table;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Progress</th>
                                <th>Sukses</th>
                                <th>Gagal</th>
                                <th>Ganda</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fileTableBodySiswa">
                            <tr id="noDataRowSiswa">
                                <td colspan="8" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle"></i> Belum ada file yang dipilih
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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
        // Inisialisasi DataTables hanya jika kelas sudah dipilih DAN ada data
        <?php if (!empty($kelas_filter) && count($siswa_data) > 0): ?>
        // Pastikan tabel ada sebelum inisialisasi DataTables
        if ($('#tableSiswa').length > 0) {
            $('#tableSiswa').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']], // Sort by Nama ascending
                pageLength: 25,
                searching: true,
                paging: true,
                info: true,
                // Pastikan jumlah kolom sesuai
                columnDefs: [
                    { orderable: false, targets: [0, 6] } // No dan Aksi tidak bisa di-sort
                ]
            });
        }
        <?php endif; ?>
    });
    
    function editSiswa(id) {
        // Ambil kelas dari filter untuk tetap di halaman yang sama
        var kelasFilter = $('#filterKelas').val();
        var url = 'index.php?edit=' + id;
        if (kelasFilter) {
            url += '&kelas=' + kelasFilter;
        }
        window.location.href = url;
    }
    
    function deleteSiswa(id) {
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
        window.open('export_siswa.php?format=excel&kelas=' + kelas, '_blank');
    }
    
    function exportPDF() {
        var kelas = $('#filterKelas').val();
        window.open('export_siswa.php?format=pdf&kelas=' + kelas, '_blank');
    }
    
    // Reset form saat modal ditutup
    $('#modalSiswa').on('hidden.bs.modal', function() {
        $('#formSiswa')[0].reset();
        $('#formAction').val('add');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Data Siswa');
        // Set kelas_id dari filter saat modal ditutup
        <?php if (!empty($kelas_filter)): ?>
        $('#kelasId').val('<?php echo $kelas_filter; ?>');
        <?php endif; ?>
        // Hapus parameter edit dari URL saat modal ditutup
        var url = window.location.pathname;
        <?php if ($kelas_filter): ?>
        url += '?kelas=<?php echo $kelas_filter; ?>';
        <?php endif; ?>
        window.history.replaceState({}, '', url);
    });
    
    // Set kelas_id dari filter saat modal dibuka untuk tambah
    $('#modalSiswa').on('show.bs.modal', function() {
        <?php if (!empty($kelas_filter)): ?>
        if ($('#formAction').val() == 'add') {
            $('#kelasId').val('<?php echo $kelas_filter; ?>');
        }
        <?php endif; ?>
    });
    
    // Reset tabel import saat modal import ditutup
    $('#modalImportSiswa').on('hidden.bs.modal', function() {
        const fileTableBodySiswa = document.getElementById('fileTableBodySiswa');
        if (fileTableBodySiswa) {
            fileTableBodySiswa.innerHTML = '<tr id="noDataRowSiswa"><td colspan="8" class="text-center text-muted py-3"><i class="fas fa-info-circle"></i> Belum ada file yang dipilih</td></tr>';
        }
        const fileInputSiswa = document.getElementById('fileInputSiswa');
        if (fileInputSiswa) {
            fileInputSiswa.value = '';
        }
        window.uploadFilesSiswa = null;
    });
    
    // Validasi NISN tidak boleh format tanggal
    $('#nisn').on('input', function() {
        var nisn = $(this).val();
        // Cek jika format seperti tanggal (YYYY-MM-DD atau YYYY/MM/DD)
        if (/^\d{4}[-/]\d{2}[-/]\d{2}$/.test(nisn)) {
            $(this).addClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
            $(this).after('<div class="invalid-feedback">NISN tidak boleh menggunakan format tanggal. Gunakan format angka saja.</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Validasi sebelum submit
    $('#formSiswa').on('submit', function(e) {
        var nisn = $('#nisn').val();
        if (/^\d{4}[-/]\d{2}[-/]\d{2}$/.test(nisn)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Format NISN Salah',
                text: 'NISN tidak boleh menggunakan format tanggal (YYYY-MM-DD). Gunakan format angka saja.',
            });
            return false;
        }
    });
    
    // Load data untuk edit (hanya jika tidak ada success message)
    <?php if ($edit_data && !$success): ?>
    $(document).ready(function() {
        $('#formAction').val('edit');
        $('#formId').val(<?php echo $edit_data['id']; ?>);
        var nisnValue = '<?php echo addslashes($edit_data['nisn']); ?>';
        // Jika NISN format tanggal, ubah ke format angka saja
        if (/^\d{4}[-/]\d{2}[-/]\d{2}$/.test(nisnValue)) {
            nisnValue = nisnValue.replace(/[^0-9]/g, '');
        }
        $('#nisn').val(nisnValue);
        $('#nama').val('<?php echo addslashes($edit_data['nama']); ?>');
        $('#jenisKelamin').val('<?php echo $edit_data['jenis_kelamin']; ?>');
        $('#tempatLahir').val('<?php echo addslashes($edit_data['tempat_lahir'] ?? ''); ?>');
        $('#tanggalLahir').val('<?php echo $edit_data['tanggal_lahir'] ?? ''; ?>');
        $('#orangtuaWali').val('<?php echo addslashes($edit_data['orangtua_wali'] ?? ''); ?>');
        <?php if (!empty($kelas_filter)): ?>
        $('#kelasId').val(<?php echo $kelas_filter; ?>);
        <?php else: ?>
        $('#kelasId').val(<?php echo $edit_data['kelas_id'] ?? 'null'; ?>);
        <?php endif; ?>
        $('#modalTitle').text('Edit Data Siswa');
        $('#modalSiswa').modal('show');
    });
    <?php endif; ?>
    
    <?php if ($success): ?>
    $(document).ready(function() {
        // Tutup modal edit jika sedang terbuka
        $('#modalSiswa').modal('hide');
        
        // Tunggu sebentar untuk memastikan modal tertutup
        setTimeout(function() {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '<?php echo addslashes($success); ?>',
                confirmButtonColor: '#2d5016',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false
            }).then(() => {
                // Hapus parameter edit dari URL tapi tetap pertahankan filter kelas
                var url = 'index.php';
                <?php if ($kelas_filter): ?>
                url += '?kelas=<?php echo $kelas_filter; ?>';
                <?php endif; ?>
                window.location.href = url;
            });
        }, 300);
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
    
    // Import Siswa Modal
    const uploadAreaSiswa = document.getElementById('uploadAreaSiswa');
    const fileInputSiswa = document.getElementById('fileInputSiswa');
    const fileTableSiswa = document.getElementById('fileTableSiswa');
    const fileTableBodySiswa = document.getElementById('fileTableBodySiswa');
    
    // Click to upload
    uploadAreaSiswa.addEventListener('click', () => {
        fileInputSiswa.click();
    });
    
    // Drag and drop
    uploadAreaSiswa.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadAreaSiswa.style.borderColor = '#2d5016';
        uploadAreaSiswa.style.backgroundColor = '#e8f5e9';
    });
    
    uploadAreaSiswa.addEventListener('dragleave', () => {
        uploadAreaSiswa.style.borderColor = '#ccc';
        uploadAreaSiswa.style.backgroundColor = '#f8f9fa';
    });
    
    uploadAreaSiswa.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadAreaSiswa.style.borderColor = '#ccc';
        uploadAreaSiswa.style.backgroundColor = '#f8f9fa';
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Simpan sebagai Array
            const filesArray = Array.from(files);
            handleFilesSiswa(filesArray);
        }
    });
    
    fileInputSiswa.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            // Simpan files SEBELUM reset input
            const files = Array.from(e.target.files);
            handleFilesSiswa(files);
            // Reset setelah handleFilesSiswa selesai
            setTimeout(() => {
                e.target.value = '';
            }, 100);
        }
    });
    
    function handleFilesSiswa(files) {
        if (!fileTableSiswa || !fileTableBodySiswa) {
            return;
        }
        
        // Hapus baris "belum ada data" jika ada
        const noDataRow = document.getElementById('noDataRowSiswa');
        if (noDataRow) {
            noDataRow.remove();
        }
        
        // Tampilkan tabel
        fileTableSiswa.style.display = 'table';
        fileTableBodySiswa.innerHTML = '';
        
        // Simpan files sebagai Array ke window untuk akses di uploadFileSiswa
        window.uploadFilesSiswa = Array.from(files);
        
        Array.from(files).forEach((file, index) => {
            const row = document.createElement('tr');
            row.id = 'fileRowSiswa_' + index;
            
            // Format ukuran file
            let fileSize = '';
            if (file.size < 1024) {
                fileSize = file.size + ' B';
            } else if (file.size < 1024 * 1024) {
                fileSize = (file.size / 1024).toFixed(2) + ' KB';
            } else {
                fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
            
            row.innerHTML = `
                <td>${file.name}</td>
                <td>${fileSize}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="progressSiswa_${index}" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </td>
                <td id="successSiswa_${index}" style="text-align: center; font-weight: bold; color: #28a745;">0</td>
                <td id="failedSiswa_${index}" style="text-align: center; font-weight: bold; color: #dc3545;">0</td>
                <td id="duplicateSiswa_${index}" style="text-align: center; font-weight: bold; color: #ffc107;">0</td>
                <td id="statusSiswa_${index}">
                    <span class="badge bg-secondary">Menunggu</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="uploadFileSiswa(${index})">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </td>
            `;
            
            fileTableBodySiswa.appendChild(row);
        });
    }
    
    function uploadFileSiswa(index) {
        // Gunakan files yang disimpan di window
        let file = null;
        if (window.uploadFilesSiswa && Array.isArray(window.uploadFilesSiswa) && window.uploadFilesSiswa[index]) {
            file = window.uploadFilesSiswa[index];
        } else {
            // Fallback ke fileInput (jika masih ada)
            const fileInput = document.getElementById('fileInputSiswa');
            if (fileInput && fileInput.files && fileInput.files[index]) {
                file = fileInput.files[index];
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: `File tidak ditemukan!<br>Index: ${index}<br>Total files: ${window.uploadFilesSiswa ? window.uploadFilesSiswa.length : 0}`,
                    confirmButtonColor: '#2d5016'
                });
                return;
            }
        }
        
        if (!file || !file.name) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'File tidak valid!',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        // Ambil kelas_id dari filter
        const kelasFilter = new URLSearchParams(window.location.search).get('kelas');
        if (!kelasFilter) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Silakan pilih kelas terlebih dahulu sebelum mengimpor data!',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        const formData = new FormData();
        formData.append('file_excel', file);
        formData.append('action', 'import');
        
        const progressBar = document.getElementById('progressSiswa_' + index);
        const statusBadge = document.getElementById('statusSiswa_' + index);
        const successCell = document.getElementById('successSiswa_' + index);
        const failedCell = document.getElementById('failedSiswa_' + index);
        const duplicateCell = document.getElementById('duplicateSiswa_' + index);
        
        if (!progressBar || !statusBadge || !successCell || !failedCell || !duplicateCell) {
            return;
        }
        
        // Reset progress bar ke 0%
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressBar.setAttribute('aria-valuenow', '0');
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
        
        // Update status
        statusBadge.innerHTML = '<span class="badge bg-info">Mengupload...</span>';
        
        // Disable upload button
        const uploadBtn = document.querySelector(`#fileRowSiswa_${index} button`);
        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        }
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const percentRounded = Math.round(percentComplete);
                
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentRounded + '%';
                progressBar.setAttribute('aria-valuenow', percentRounded);
                
                // Update warna progress bar berdasarkan progress
                if (percentComplete < 50) {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                } else if (percentComplete < 100) {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                } else {
                    progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
                }
                }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    // Trim response untuk menghilangkan whitespace
                    const responseText = xhr.responseText.trim();
                    
                    // Log untuk debugging (hapus di production)
                    console.log('Response received:', responseText.substring(0, 200));
                    
                    // Cek jika response kosong
                    if (!responseText) {
                        throw new Error('Response kosong dari server');
                    }
                    
                    // Cek jika response dimulai dengan karakter yang tidak valid
                    if (!responseText.startsWith('{') && !responseText.startsWith('[')) {
                        console.error('Invalid JSON start:', responseText.substring(0, 100));
                        throw new Error('Response bukan JSON valid. Response: ' + responseText.substring(0, 100));
                    }
                    
                    const response = JSON.parse(responseText);
                    
                    if (response.success) {
                        statusBadge.innerHTML = '<span class="badge bg-success">Selesai</span>';
                        successCell.textContent = response.success_count || 0;
                        failedCell.textContent = response.error_count || 0;
                        duplicateCell.textContent = response.duplicate_count || 0;
                        
                        if (response.success_count > 0) {
                            // Ambil kelas_id dari data yang baru diimport
                            var importedKelasIds = response.imported_kelas_ids || [];
                            var kelasFilter = $('#filterKelas').val();
                            var currentUrl = window.location.href;
                            var urlParams = new URLSearchParams(window.location.search);
                            var kelasFromUrl = urlParams.get('kelas');
                            
                            // Prioritaskan: kelas dari URL > kelas dari data import > kelas dari dropdown
                            var kelasToUse = kelasFromUrl || (importedKelasIds.length > 0 ? importedKelasIds[0] : null) || kelasFilter;
                            
                            var messageHtml = `Berhasil mengimpor ${response.success_count} data siswa`;
                            if (importedKelasIds.length > 0) {
                                messageHtml += `<br><small>Data akan ditampilkan untuk kelas yang sesuai</small>`;
                            } else {
                                messageHtml += `<br><small>Peringatan: Data diimport tanpa kelas. Silakan edit data untuk menambahkan kelas.</small>`;
                            }
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                html: messageHtml,
                                confirmButtonColor: '#2d5016',
                                timer: 2500,
                                timerProgressBar: true,
                                showConfirmButton: false
                            }).then(() => {
                                // Tutup modal import terlebih dahulu
                                $('#modalImportSiswa').modal('hide');
                                
                                // Delay untuk memastikan data tersimpan di database
                                setTimeout(function() {
                                    // Selalu redirect dengan kelas dari import jika ada, atau kelas dari URL/dropdown
                                    if (importedKelasIds.length > 0) {
                                        // Gunakan kelas pertama dari data yang diimport
                                        window.location.href = 'index.php?kelas=' + importedKelasIds[0];
                                    } else if (kelasToUse) {
                                        // Gunakan kelas dari URL atau dropdown
                                        window.location.href = 'index.php?kelas=' + kelasToUse;
                                    } else {
                                        // Jika tidak ada kelas sama sekali, reload biasa
                                        location.reload();
                                    }
                                }, 800);
                            });
                        }
                    } else {
                        statusBadge.innerHTML = '<span class="badge bg-danger">Gagal</span>';
                        failedCell.textContent = response.error_count || 0;
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message || 'Gagal mengimpor data',
                            confirmButtonColor: '#2d5016',
                            timer: 4000,
                            timerProgressBar: true,
                            showConfirmButton: true
                        });
                    }
                } catch (e) {
                    statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
                    console.error('JSON Parse Error:', e);
                    console.error('Response Text:', xhr.responseText.substring(0, 500));
                    
                    let errorMessage = 'Terjadi kesalahan saat memproses response';
                    if (e.message) {
                        errorMessage += ': ' + e.message;
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        html: errorMessage + '<br><small>Silakan cek console browser untuk detail error</small>',
                        confirmButtonColor: '#2d5016'
                    });
                }
            } else {
                statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
                progressBar.className = 'progress-bar bg-danger';
                
                // Coba parse response sebagai JSON untuk mendapatkan pesan error yang lebih detail
                let errorMessage = 'Gagal mengupload file';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch (e) {
                    // Jika bukan JSON, gunakan status text atau default message
                    errorMessage = xhr.statusText || 'Gagal mengupload file (Status: ' + xhr.status + ')';
                }
                
                if (uploadBtn) {
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: errorMessage + '<br><small>Status: ' + xhr.status + '</small>',
                    confirmButtonColor: '#2d5016',
                    timer: 5000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
            }
        });
        
        xhr.addEventListener('error', () => {
            statusBadge.innerHTML = '<span class="badge bg-danger">Error</span>';
            progressBar.className = 'progress-bar bg-danger';
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat mengupload',
                confirmButtonColor: '#2d5016'
            });
        });
        
        xhr.addEventListener('abort', () => {
            statusBadge.innerHTML = '<span class="badge bg-warning">Dibatalkan</span>';
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
        });
        
        xhr.open('POST', 'import_ajax.php?kelas=' + kelasFilter, true);
        xhr.send(formData);
    }
    
    function downloadTemplateSiswa() {
        // Ambil kelas_id dari filter untuk ditampilkan di template
        const kelasFilter = new URLSearchParams(window.location.search).get('kelas');
        if (kelasFilter) {
            window.location.href = 'template_siswa.php?kelas=' + kelasFilter;
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan!',
                text: 'Silakan pilih kelas terlebih dahulu sebelum download template!',
                confirmButtonColor: '#2d5016'
            });
        }
    }
</script>

