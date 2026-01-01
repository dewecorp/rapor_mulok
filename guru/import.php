<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

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
            
            $file_path = $upload_dir . 'import_guru_' . time() . '.' . $file_ext;
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
                        if (count($row) >= 6 && !empty($row[0]) && !empty($row[5])) {
                            $data[] = [
                                'nama' => trim($row[0] ?? ''),
                                'jenis_kelamin' => trim($row[1] ?? 'L'),
                                'tempat_lahir' => trim($row[2] ?? ''),
                                'tanggal_lahir' => trim($row[3] ?? ''),
                                'pendidikan' => trim($row[4] ?? ''),
                                'nuptk' => trim($row[5] ?? ''),
                                'password' => trim($row[6] ?? '123456'),
                                'role' => trim($row[7] ?? 'guru')
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
                                if (count($row) >= 6 && !empty($row[0]) && !empty($row[5])) {
                                    $data[] = [
                                        'nama' => trim($row[0] ?? ''),
                                        'jenis_kelamin' => trim($row[1] ?? 'L'),
                                        'tempat_lahir' => trim($row[2] ?? ''),
                                        'tanggal_lahir' => trim($row[3] ?? ''),
                                        'pendidikan' => trim($row[4] ?? ''),
                                        'nuptk' => trim($row[5] ?? ''),
                                        'password' => trim($row[6] ?? '123456'),
                                        'role' => trim($row[7] ?? 'guru')
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
                
                // Cek apakah kolom NUPTK dan pendidikan ada
                $check_columns = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'nuptk'");
                if ($check_columns->num_rows == 0) {
                    $conn->query("ALTER TABLE pengguna ADD COLUMN nuptk VARCHAR(50) UNIQUE AFTER username");
                }
                $check_pendidikan = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'pendidikan'");
                if ($check_pendidikan->num_rows == 0) {
                    $conn->query("ALTER TABLE pengguna ADD COLUMN pendidikan VARCHAR(100) AFTER tanggal_lahir");
                }
                
                // Import data
                foreach ($data as $index => $row_data) {
                    $line = $index + 2; // +2 karena header dan index mulai dari 0
                    
                    if (empty($row_data['nama']) || empty($row_data['nuptk'])) {
                        $error_count++;
                        $errors[] = "Baris $line: Nama dan NUPTK wajib diisi";
                        continue;
                    }
                    
                    // Validasi NUPTK unik
                    $check_nuptk = $conn->prepare("SELECT id FROM pengguna WHERE nuptk = ?");
                    $check_nuptk->bind_param("s", $row_data['nuptk']);
                    $check_nuptk->execute();
                    $result_check = $check_nuptk->get_result();
                    
                    if ($result_check->num_rows > 0) {
                        $error_count++;
                        $errors[] = "Baris $line: NUPTK '{$row_data['nuptk']}' sudah digunakan";
                        continue;
                    }
                    
                    // Validasi username unik (username = NUPTK)
                    $username = $row_data['nuptk'];
                    $check_username = $conn->prepare("SELECT id FROM pengguna WHERE username = ?");
                    $check_username->bind_param("s", $username);
                    $check_username->execute();
                    $result_check_username = $check_username->get_result();
                    
                    if ($result_check_username->num_rows > 0) {
                        $error_count++;
                        $errors[] = "Baris $line: Username/NUPTK '{$row_data['nuptk']}' sudah digunakan";
                        continue;
                    }
                    
                    // Hash password
                    $password = password_hash($row_data['password'], PASSWORD_DEFAULT);
                    
                    // Insert data
                    $tempat_lahir_null = empty($row_data['tempat_lahir']) ? null : $row_data['tempat_lahir'];
                    $tanggal_lahir_null = empty($row_data['tanggal_lahir']) ? null : $row_data['tanggal_lahir'];
                    $pendidikan_null = empty($row_data['pendidikan']) ? null : $row_data['pendidikan'];
                    $role = in_array($row_data['role'], ['guru', 'wali_kelas', 'proktor']) ? $row_data['role'] : 'guru';
                    
                    $stmt = $conn->prepare("INSERT INTO pengguna (nama, jenis_kelamin, tempat_lahir, tanggal_lahir, pendidikan, username, nuptk, password, foto, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'default.png', ?)");
                    $stmt->bind_param("sssssssss", 
                        $row_data['nama'],
                        $row_data['jenis_kelamin'],
                        $tempat_lahir_null,
                        $tanggal_lahir_null,
                        $pendidikan_null,
                        $username,
                        $row_data['nuptk'],
                        $password,
                        $role
                    );
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = "Baris $line: " . $stmt->error;
                    }
                }
                
                // Hapus file temporary
                unlink($file_path);
                
                if ($success_count > 0) {
                    $success = "Berhasil mengimpor $success_count data guru";
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
$page_title = 'Impor Data Guru';

end:
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header" style="background-color: #2d5016; color: white;">
        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Impor Data Guru</h5>
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
                        window.location.href = 'data.php';
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
                                    <td>1. Nama</td>
                                    <td>Nama lengkap guru</td>
                                    <td><span class="badge bg-danger">Ya</span></td>
                                </tr>
                                <tr>
                                    <td>2. Jenis Kelamin</td>
                                    <td>L atau P</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>3. Tempat Lahir</td>
                                    <td>Tempat lahir</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>4. Tanggal Lahir</td>
                                    <td>Format: YYYY-MM-DD (contoh: 1990-01-15)</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>5. Pendidikan</td>
                                    <td>Contoh: S1, S2, dll</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>6. NUPTK</td>
                                    <td>NUPTK guru (unik)</td>
                                    <td><span class="badge bg-danger">Ya</span></td>
                                </tr>
                                <tr>
                                    <td>7. Password</td>
                                    <td>Password default (jika kosong akan menggunakan "123456")</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                                <tr>
                                    <td>8. Role</td>
                                    <td>guru, wali_kelas, atau proktor (default: guru)</td>
                                    <td><span class="badge bg-warning">Opsional</span></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-lightbulb"></i> <strong>Tips:</strong>
                            <ul class="mb-0">
                                <li>Pastikan file Excel memiliki header di baris pertama</li>
                                <li>Baris pertama adalah header (akan diabaikan)</li>
                                <li>NUPTK harus unik, tidak boleh duplikat</li>
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
                                <a href="data.php" class="btn btn-secondary">
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

