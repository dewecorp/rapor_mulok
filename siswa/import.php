<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Ambil data kelas untuk mapping
$kelas_list = [];
try {
    $query_kelas = "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas";
    $result_kelas = $conn->query($query_kelas);
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[$row['nama_kelas']] = $row['id'];
    }
} catch (Exception $e) {
    // Ignore
}

// Handle import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];
    
    if ($file['error'] == 0) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, ['xls', 'xlsx'])) {
            $error = 'Format file tidak didukung! Hanya file Excel (.xls, .xlsx) yang diperbolehkan';
        } else {
            $upload_dir = '../uploads/temp/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_path = $upload_dir . 'import_siswa_' . time() . '.' . $file_ext;
            move_uploaded_file($file['tmp_name'], $file_path);
            
            // Baca file Excel
            $data = [];
            $success_count = 0;
            $error_count = 0;
            $errors = [];
            
            try {
                // Baca file Excel menggunakan PhpSpreadsheet atau metode alternatif
                if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    // Menggunakan PhpSpreadsheet
                    require_once '../vendor/autoload.php';
                    
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_path);
                    $spreadsheet = $reader->load($file_path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    // Skip header (baris pertama)
                    array_shift($rows);
                    
                    foreach ($rows as $row) {
                        if (count($row) >= 5 && !empty($row[0]) && !empty($row[1])) {
                            $data[] = [
                                'nisn' => trim($row[0] ?? ''),
                                'nama' => trim($row[1] ?? ''),
                                'jenis_kelamin' => trim($row[2] ?? 'L'),
                                'tempat_lahir' => trim($row[3] ?? ''),
                                'tanggal_lahir' => trim($row[4] ?? ''),
                                'kelas' => trim($row[5] ?? '') // Nama kelas
                            ];
                        }
                    }
                } else {
                    // Fallback: Baca Excel sebagai HTML table (untuk .xls)
                    // Atau gunakan SimpleXLSX untuk .xlsx
                    if ($file_ext == 'xlsx' && class_exists('SimpleXLSX')) {
                        if ($xlsx = SimpleXLSX::parse($file_path)) {
                            $rows = $xlsx->rows();
                            // Skip header
                            array_shift($rows);
                            
                            foreach ($rows as $row) {
                                if (count($row) >= 5 && !empty($row[0]) && !empty($row[1])) {
                                    $data[] = [
                                        'nisn' => trim($row[0] ?? ''),
                                        'nama' => trim($row[1] ?? ''),
                                        'jenis_kelamin' => trim($row[2] ?? 'L'),
                                        'tempat_lahir' => trim($row[3] ?? ''),
                                        'tanggal_lahir' => trim($row[4] ?? ''),
                                        'kelas' => trim($row[5] ?? '') // Nama kelas
                                    ];
                                }
                            }
                        } else {
                            throw new Exception('Gagal membaca file Excel. Pastikan file tidak corrupt.');
                        }
                    } else {
                        $error = 'Library PhpSpreadsheet tidak ditemukan. Silakan install dengan: composer require phpoffice/phpspreadsheet';
                        unlink($file_path);
                        goto end;
                    }
                }
                
                // Import data
                foreach ($data as $index => $row_data) {
                    $line = $index + 2; // +2 karena header dan index mulai dari 0
                    
                    if (empty($row_data['nama']) || empty($row_data['nisn'])) {
                        $error_count++;
                        $errors[] = "Baris $line: Nama dan NISN wajib diisi";
                        continue;
                    }
                    
                    // Validasi NISN unik
                    $check_nisn = $conn->prepare("SELECT id FROM siswa WHERE nisn = ?");
                    $check_nisn->bind_param("s", $row_data['nisn']);
                    $check_nisn->execute();
                    $result_check = $check_nisn->get_result();
                    
                    if ($result_check->num_rows > 0) {
                        $error_count++;
                        $errors[] = "Baris $line: NISN '{$row_data['nisn']}' sudah digunakan";
                        continue;
                    }
                    
                    // Mapping kelas
                    $kelas_id = null;
                    if (!empty($row_data['kelas'])) {
                        if (isset($kelas_list[$row_data['kelas']])) {
                            $kelas_id = $kelas_list[$row_data['kelas']];
                        } else {
                            $error_count++;
                            $errors[] = "Baris $line: Kelas '{$row_data['kelas']}' tidak ditemukan";
                            continue;
                        }
                    }
                    
                    // Insert data
                    $tempat_lahir_null = empty($row_data['tempat_lahir']) ? null : $row_data['tempat_lahir'];
                    $tanggal_lahir_null = empty($row_data['tanggal_lahir']) ? null : $row_data['tanggal_lahir'];
                    
                    $stmt = $conn->prepare("INSERT INTO siswa (nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, kelas_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssi", 
                        $row_data['nisn'],
                        $row_data['nama'],
                        $row_data['jenis_kelamin'],
                        $tempat_lahir_null,
                        $tanggal_lahir_null,
                        $kelas_id
                    );
                    
                    if ($stmt->execute()) {
                        $success_count++;
                        // Update jumlah siswa di kelas
                        if ($kelas_id) {
                            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_id) WHERE id = $kelas_id");
                        }
                    } else {
                        $error_count++;
                        $errors[] = "Baris $line: " . $stmt->error;
                    }
                }
                
                // Hapus file temporary
                unlink($file_path);
                
                if ($success_count > 0) {
                    $success = "Berhasil mengimpor $success_count data siswa";
                    if ($error_count > 0) {
                        $success .= ". $error_count data gagal diimpor.";
                    }
                    if (count($errors) > 0 && count($errors) <= 10) {
                        $error = implode('<br>', $errors);
                    } elseif (count($errors) > 10) {
                        $error = implode('<br>', array_slice($errors, 0, 10)) . '<br>... dan ' . (count($errors) - 10) . ' error lainnya';
                    }
                } else {
                    $error = "Tidak ada data yang berhasil diimpor. " . implode('<br>', array_slice($errors, 0, 10));
                }
                
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    } else {
        $error = 'Error upload file!';
    }
}

// Set page title (variabel lokal)
$page_title = 'Impor Data Siswa';

end:
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header" style="background-color: #2d5016; color: white;">
        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Impor Data Siswa</h5>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <script>
                setTimeout(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        html: '<?php echo addslashes($success); ?>',
                        confirmButtonColor: '#2d5016',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                }, 100);
            </script>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <script>
                setTimeout(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        html: '<?php echo addslashes($error); ?>',
                        confirmButtonColor: '#2d5016',
                        timer: 5000,
                        timerProgressBar: true,
                        showConfirmButton: true
                    });
                }, 100);
            </script>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-info-circle"></i> Format File Excel</h6>
                        <p>File harus berformat Excel (.xls atau .xlsx) dengan kolom berikut (urutan harus sesuai):</p>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Kolom</th>
                                    <th>Keterangan</th>
                                    <th>Wajib</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1. NISN</td>
                                    <td>Nomor Induk Siswa Nasional</td>
                                    <td><span class="badge bg-danger">Ya</span></td>
                                </tr>
                                <tr>
                                    <td>2. Nama</td>
                                    <td>Nama lengkap siswa</td>
                                    <td><span class="badge bg-danger">Ya</span></td>
                                </tr>
                                <tr>
                                    <td>3. Jenis Kelamin</td>
                                    <td>L atau P</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>4. Tempat Lahir</td>
                                    <td>Tempat lahir</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>5. Tanggal Lahir</td>
                                    <td>Format: YYYY-MM-DD (contoh: 2010-01-15)</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>6. Kelas</td>
                                    <td>Nama kelas (harus sesuai dengan data kelas yang sudah ada)</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-lightbulb"></i> <strong>Tips:</strong>
                            <ul class="mb-0">
                                <li>Pastikan file Excel memiliki header di baris pertama</li>
                                <li>Baris pertama adalah header (akan diabaikan)</li>
                                <li>NISN harus unik, tidak boleh duplikat</li>
                                <li>Nama kelas harus sesuai dengan data kelas yang sudah ada di sistem</li>
                                <li>Jika ada error, periksa format data di file Excel</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title"><i class="fas fa-upload"></i> Upload File</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Pilih File Excel</label>
                                <input type="file" class="form-control" name="file_excel" accept=".xls,.xlsx" required>
                                <small class="text-muted">Format: .xls atau .xlsx (Excel)</small>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Impor Data
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

