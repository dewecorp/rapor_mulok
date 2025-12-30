<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Handle pindah kelas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'pindah') {
    $kelas_lama_id = $_POST['kelas_lama_id'] ?? 0;
    $kelas_baru_id = $_POST['kelas_baru_id'] ?? 0;
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    
    if (!$kelas_lama_id || !$kelas_baru_id) {
        $error = 'Pilih kelas asal dan kelas tujuan!';
    } elseif (empty($siswa_ids) || !is_array($siswa_ids)) {
        $error = 'Pilih siswa yang akan dipindah!';
    } else {
        $pindah_count = 0;
        $conn->begin_transaction();
        
        try {
            foreach ($siswa_ids as $siswa_id) {
                $siswa_id = intval($siswa_id);
                if ($siswa_id > 0) {
                    $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $kelas_baru_id, $siswa_id);
                    if ($stmt->execute()) {
                        $pindah_count++;
                    }
                }
            }
            
            // Update jumlah siswa di kelas lama
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_lama_id) WHERE id = $kelas_lama_id");
            
            // Update jumlah siswa di kelas baru
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_baru_id) WHERE id = $kelas_baru_id");
            
            $conn->commit();
            $success = "Berhasil memindahkan $pindah_count siswa!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Gagal memindahkan siswa: ' . $e->getMessage();
        }
    }
}

// Ambil success message dari URL jika ada
if (isset($_GET['success']) && !isset($success)) {
    $success = urldecode($_GET['success']);
}

// Handle batal pindah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batal_pindah') {
    $siswa_ids_batal = $_POST['siswa_ids_batal'] ?? [];
    $kelas_tujuan_id = $_POST['kelas_tujuan_id'] ?? 0;
    $kelas_asal_id = $_POST['kelas_asal_id'] ?? 0;
    
    if (empty($siswa_ids_batal) || !is_array($siswa_ids_batal)) {
        $error = 'Pilih siswa yang akan dibatalkan pindah!';
    } elseif (!$kelas_asal_id) {
        $error = 'Pilih kelas asal!';
    } else {
        $batal_count = 0;
        $conn->begin_transaction();
        
        try {
            foreach ($siswa_ids_batal as $siswa_id) {
                $siswa_id = intval($siswa_id);
                if ($siswa_id > 0) {
                    $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $kelas_asal_id, $siswa_id);
                    if ($stmt->execute()) {
                        $batal_count++;
                    }
                }
            }
            
            // Update jumlah siswa di kelas asal
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_asal_id) WHERE id = $kelas_asal_id");
            
            // Update jumlah siswa di kelas tujuan
            if ($kelas_tujuan_id) {
                $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_tujuan_id) WHERE id = $kelas_tujuan_id");
            }
            
            $conn->commit();
            $success = "Berhasil membatalkan pindah $batal_count siswa!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Gagal membatalkan pindah siswa: ' . $e->getMessage();
        }
    }
}

// Filter kelas asal dan tujuan (ambil dari POST jika ada, jika tidak dari GET)
$kelas_asal_filter = $_POST['kelas_asal'] ?? $_GET['kelas_asal'] ?? '';
$kelas_tujuan_filter = $_POST['kelas_tujuan'] ?? $_GET['kelas_tujuan'] ?? '';
$status_tingkat_filter = $_POST['status_tingkat'] ?? $_GET['status_tingkat'] ?? 'sama'; // Default: tingkat sama

// Ambil data kelas
$query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
$kelas_list = $conn->query($query_kelas);

// Fungsi untuk mendapatkan tingkat dari nama kelas (I, II, III, IV, V, VI)
function getTingkatFromKelas($nama_kelas) {
    $nama_kelas = trim($nama_kelas);
    // Cek apakah nama kelas mengandung angka romawi atau angka biasa
    // Urutan penting: yang lebih panjang harus dicocokkan terlebih dahulu (VI sebelum V, III sebelum II, dll)
    if (preg_match('/^(VI|IV|III|II|V|I|6|4|3|2|5|1)/i', $nama_kelas, $matches)) {
        return strtoupper($matches[1]);
    }
    return '';
}

// Ambil tingkat kelas asal jika sudah dipilih
$tingkat_kelas_asal = '';
if (!empty($kelas_asal_filter)) {
    $kelas_id = intval($kelas_asal_filter);
    $query_tingkat = "SELECT nama_kelas FROM kelas WHERE id = ?";
    $stmt_tingkat = $conn->prepare($query_tingkat);
    $stmt_tingkat->bind_param("i", $kelas_id);
    $stmt_tingkat->execute();
    $result_tingkat = $stmt_tingkat->get_result();
    if ($row_tingkat = $result_tingkat->fetch_assoc()) {
        $tingkat_kelas_asal = getTingkatFromKelas($row_tingkat['nama_kelas']);
    }
    $stmt_tingkat->close();
}

// Query data siswa kelas asal
$siswa_asal_data = [];
if (!empty($kelas_asal_filter)) {
    $kelas_id = intval($kelas_asal_filter);
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
            $siswa_asal_data[] = $row;
        }
    }
    $stmt->close();
}

// Query data siswa kelas tujuan (untuk preview)
$siswa_tujuan_data = [];
$kelas_tujuan_ids = []; // Array ID kelas tujuan untuk query
$kelas_tujuan_auto = null; // Kelas tujuan yang otomatis ditentukan

// Jika kelas tujuan sudah dipilih manual, gunakan itu
if (!empty($kelas_tujuan_filter)) {
    $kelas_id = intval($kelas_tujuan_filter);
    $kelas_tujuan_ids[] = $kelas_id;
    $kelas_tujuan_auto = $kelas_id;
} 
// Jika kelas asal sudah dipilih tapi kelas tujuan belum dipilih, ambil semua kelas yang sesuai dengan filter
elseif (!empty($kelas_asal_filter) && !empty($tingkat_kelas_asal)) {
    // Ambil semua kelas sesuai dengan filter status tingkat
    $query_kelas_fresh = "SELECT * FROM kelas ORDER BY nama_kelas";
    $kelas_list_fresh = $conn->query($query_kelas_fresh);
    
    if ($kelas_list_fresh) {
        while ($kelas = $kelas_list_fresh->fetch_assoc()) {
            // Skip kelas asal
            if ($kelas['id'] == $kelas_asal_filter) {
                continue;
            }
            
            $tingkat_kelas = getTingkatFromKelas($kelas['nama_kelas']);
            
            // Filter berdasarkan status tingkat
            if ($status_tingkat_filter == 'sama') {
                // Tingkat sama: hanya ambil kelas paralel (tingkat sama dengan kelas asal)
                if (!empty($tingkat_kelas) && $tingkat_kelas == $tingkat_kelas_asal) {
                    $kelas_tujuan_ids[] = $kelas['id'];
                    // Set kelas tujuan otomatis ke kelas pertama yang sesuai
                    if ($kelas_tujuan_auto === null) {
                        $kelas_tujuan_auto = $kelas['id'];
                    }
                }
            } else {
                // Tingkat beda: ambil semua kelas (kecuali kelas asal yang sudah di-skip)
                $kelas_tujuan_ids[] = $kelas['id'];
                // Set kelas tujuan otomatis ke kelas pertama yang sesuai
                if ($kelas_tujuan_auto === null) {
                    $kelas_tujuan_auto = $kelas['id'];
                }
            }
        }
    }
}

// Query siswa kelas tujuan jika sudah ditentukan (manual atau otomatis)
if (!empty($kelas_tujuan_ids)) {
    // Buat placeholder untuk IN clause
    $placeholders = str_repeat('?,', count($kelas_tujuan_ids) - 1) . '?';
    $query = "SELECT s.*, k.nama_kelas, k.id as kelas_id
              FROM siswa s
              LEFT JOIN kelas k ON s.kelas_id = k.id
              WHERE s.kelas_id IN ($placeholders)
              ORDER BY k.nama_kelas, s.nama";
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Bind parameters
        $types = str_repeat('i', count($kelas_tujuan_ids));
        $stmt->bind_param($types, ...$kelas_tujuan_ids);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $siswa_tujuan_data[] = $row;
                }
            }
        } else {
            // Debug: log error jika query gagal
            error_log("Error executing siswa tujuan query: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Debug: log error jika prepare gagal
        error_log("Error preparing siswa tujuan query: " . $conn->error);
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Pindah Kelas</h5>
        <p class="mb-0 text-muted" style="font-size: 14px;">Menu ini digunakan untuk memindahkan siswa ke kelas lain.</p>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: '<?php echo addslashes($success); ?>',
                        confirmButtonColor: '#2d5016',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                });
            </script>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Panel Kelas Asal -->
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-school"></i> Kelas Asal</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formPindahKelas">
                            <input type="hidden" name="action" value="pindah">
                            <input type="hidden" name="kelas_lama_id" id="kelasLamaId" value="<?php echo $kelas_asal_filter; ?>">
                            <input type="hidden" name="kelas_baru_id" id="kelasBaruId" value="<?php echo $kelas_tujuan_filter; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Kelas</label>
                                <select class="form-select" id="kelasAsal" onchange="updateKelasAsal()">
                                    <option value="">--Pilih--</option>
                                    <?php 
                                    $kelas_list->data_seek(0);
                                    while ($kelas = $kelas_list->fetch_assoc()): 
                                        // Skip kelas Alumni dari kelas asal
                                        if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                            continue;
                                        }
                                    ?>
                                        <option value="<?php echo $kelas['id']; ?>" 
                                                data-tingkat="<?php echo htmlspecialchars(getTingkatFromKelas($kelas['nama_kelas'])); ?>"
                                                <?php echo $kelas_asal_filter == $kelas['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <?php if (!empty($kelas_asal_filter)): ?>
                            <div class="table-responsive" style="max-height: 400px;">
                                <table class="table table-bordered table-striped table-sm" id="tableAsal">
                                    <thead>
                                        <tr>
                                            <th width="30">
                                                <input type="checkbox" id="selectAllAsal" onchange="toggleSelectAllAsal()">
                                            </th>
                                            <th width="30">No</th>
                                            <th>NISN</th>
                                            <th>Nama</th>
                                            <th>L/P</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($siswa_asal_data) > 0): ?>
                                            <?php $no = 1; foreach ($siswa_asal_data as $row): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="siswa_ids[]" value="<?php echo $row['id']; ?>" class="siswa-checkbox-asal">
                                                </td>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['nisn'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['nama'] ?? '-'); ?></td>
                                                <td><?php echo (($row['jenis_kelamin'] ?? '') == 'L') ? 'L' : 'P'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">Tidak ada siswa</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-2 text-center">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="resetKelasAsal()">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                                <button type="button" class="btn btn-primary btn-sm ms-2" id="btnPindahKelas" onclick="pindahKelas()" style="display: none;">
                                    <i class="fas fa-exchange-alt"></i> Pindah Kelas
                                </button>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Pilih kelas untuk melihat data siswa.
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
                
                <!-- Panel Kelas Tujuan -->
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-arrow-right"></i> Kelas Tujuan</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formBatalPindah">
                                <input type="hidden" name="action" value="batal_pindah">
                                <input type="hidden" name="kelas_asal_id" id="kelasAsalIdBatal" value="<?php echo $kelas_asal_filter; ?>">
                                <input type="hidden" name="kelas_tujuan_id" id="kelasTujuanIdBatal" value="<?php echo $kelas_tujuan_filter; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Status Tingkat</label>
                                        <select class="form-select" id="statusTingkat" onchange="filterKelasTujuan()">
                                            <option value="sama" <?php echo $status_tingkat_filter == 'sama' ? 'selected' : ''; ?>>Tingkat Sama</option>
                                            <option value="beda" <?php echo $status_tingkat_filter == 'beda' ? 'selected' : ''; ?>>Tingkat Beda</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Kelas</label>
                                        <select class="form-select" id="kelasTujuan" onchange="updateKelasTujuan()">
                                            <option value="">--Pilih--</option>
                                            <?php 
                                            // Hanya tampilkan kelas jika kelas asal sudah dipilih DAN tingkat kelas asal sudah diketahui
                                            if (!empty($kelas_asal_filter) && !empty($tingkat_kelas_asal)) {
                                                $kelas_list->data_seek(0);
                                                while ($kelas = $kelas_list->fetch_assoc()): 
                                                    // Skip kelas asal
                                                    if ($kelas['id'] == $kelas_asal_filter) {
                                                        continue;
                                                    }
                                                    
                                                    // Skip kelas Alumni dari kelas tujuan
                                                    if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                                        continue;
                                                    }
                                                    
                                                    $tingkat_kelas = getTingkatFromKelas($kelas['nama_kelas']);
                                                    
                                                    // Filter berdasarkan status tingkat
                                                    if ($status_tingkat_filter == 'sama') {
                                                        // Tingkat sama: hanya tampilkan kelas paralel (tingkat sama dengan kelas asal)
                                                        // Skip jika tingkat kosong atau berbeda
                                                        if (empty($tingkat_kelas) || $tingkat_kelas_asal != $tingkat_kelas) {
                                                            continue;
                                                        }
                                                    }
                                                    // Jika status_tingkat_filter == 'beda', tampilkan semua kelas (kelas asal sudah di-skip di atas)
                                                    
                                                    // Render option hanya jika sampai di sini
                                                ?>
                                                    <option value="<?php echo $kelas['id']; ?>" 
                                                            data-tingkat="<?php echo htmlspecialchars($tingkat_kelas); ?>"
                                                            <?php echo $kelas_tujuan_filter == $kelas['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                                    </option>
                                                <?php 
                                                endwhile; 
                                            }
                                            // Jika kelas asal belum dipilih atau tingkat belum diketahui, tidak ada option kelas yang di-render
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <?php if (!empty($kelas_asal_filter) && !empty($tingkat_kelas_asal)): ?>
                                <?php 
                                // Cek apakah kelas tujuan sudah kosong
                                $jumlah_siswa_tujuan = count($siswa_tujuan_data);
                                ?>
                                <?php if ($jumlah_siswa_tujuan > 0): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Info:</strong> Tabel di bawah menampilkan siswa dari kelas tujuan yang sesuai dengan filter status tingkat. 
                                        <?php if ($status_tingkat_filter == 'sama'): ?>
                                            Menampilkan siswa dari kelas paralel (tingkat sama dengan kelas asal).
                                        <?php else: ?>
                                            Menampilkan siswa dari semua kelas (kecuali kelas asal).
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-3">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <strong>Peringatan:</strong> 
                                        <?php if ($status_tingkat_filter == 'sama'): ?>
                                            Tidak ada kelas paralel dengan tingkat yang sama dengan kelas asal, atau kelas paralel masih kosong.
                                        <?php else: ?>
                                            Tidak ada siswa di kelas tujuan yang sesuai dengan filter.
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="table-responsive" style="max-height: 400px;">
                                    <table class="table table-bordered table-striped table-sm" id="tableTujuan">
                                        <thead>
                                            <tr>
                                                <th width="30">
                                                    <input type="checkbox" id="selectAllTujuan" onchange="toggleSelectAllTujuan()">
                                                </th>
                                                <th width="30">No</th>
                                                <th>NISN</th>
                                                <th>Nama</th>
                                                <th>Kelas</th>
                                                <th>L/P</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($siswa_tujuan_data) > 0): ?>
                                                <?php $no = 1; foreach ($siswa_tujuan_data as $row): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="siswa_ids_batal[]" value="<?php echo $row['id']; ?>" class="siswa-checkbox-tujuan">
                                                    </td>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo htmlspecialchars($row['nisn'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['nama'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['nama_kelas'] ?? '-'); ?></td>
                                                    <td><?php echo (($row['jenis_kelamin'] ?? '') == 'L') ? 'L' : 'P'; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">Tidak ada siswa</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 text-center">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="resetKelasTujuan()">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm ms-2" id="btnBatalPindah" onclick="batalPindah()" style="display: none;">
                                        <i class="fas fa-undo"></i> Batal Pindah
                                    </button>
                                </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> Pilih kelas asal terlebih dahulu untuk melihat siswa kelas tujuan.
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Warning Box -->
            <div class="alert alert-warning mt-3" id="warningBox" style="display: none;">
                <strong>Perhatikan:</strong>
                <ul class="mb-0" id="warningList">
                </ul>
            </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Set tingkat kelas asal untuk JavaScript
    var tingkatKelasAsal = '<?php echo htmlspecialchars($tingkat_kelas_asal, ENT_QUOTES); ?>';
    var kelasAsalId = '<?php echo htmlspecialchars($kelas_asal_filter, ENT_QUOTES); ?>';
    var statusTingkatFilter = '<?php echo htmlspecialchars($status_tingkat_filter, ENT_QUOTES); ?>';
    
    // Hapus option yang tidak sesuai filter SEBELUM document ready
    (function() {
        var kelasTujuanSelect = document.getElementById('kelasTujuan');
        if (kelasTujuanSelect) {
            var options = kelasTujuanSelect.options;
            var kelasAsalIdCurrent = document.getElementById('kelasAsal') ? document.getElementById('kelasAsal').value : '';
            var statusTingkat = document.getElementById('statusTingkat') ? document.getElementById('statusTingkat').value : statusTingkatFilter || 'sama';
            
            // Jika kelas asal belum dipilih atau tingkat belum diketahui, hapus semua option kelas
            if (!kelasAsalId && !kelasAsalIdCurrent) {
                for (var i = options.length - 1; i >= 0; i--) {
                    if (options[i].value !== '') {
                        options[i].remove();
                    }
                }
            } else {
                var kelasAsalActive = kelasAsalIdCurrent || kelasAsalId;
                var tingkatAsalActive = tingkatKelasAsal;
                
                // Dapatkan tingkat dari option kelas asal yang dipilih
                var kelasAsalSelect = document.getElementById('kelasAsal');
                if (kelasAsalSelect && kelasAsalSelect.value) {
                    var selectedOption = kelasAsalSelect.options[kelasAsalSelect.selectedIndex];
                    if (selectedOption && selectedOption.dataset.tingkat) {
                        tingkatAsalActive = selectedOption.dataset.tingkat;
                    }
                }
                
                // Hapus option yang tidak sesuai filter
                for (var i = options.length - 1; i >= 0; i--) {
                    var optionValue = options[i].value;
                    var optionTingkat = options[i].dataset.tingkat;
                    
                    // Pertahankan option kosong
                    if (optionValue === '') {
                        continue;
                    }
                    
                    // Hapus kelas asal
                    if (optionValue == kelasAsalActive) {
                        options[i].remove();
                        continue;
                    }
                    
                    // Jika kelas asal belum dipilih atau tingkat belum diketahui, hapus semua
                    if (!kelasAsalActive || !tingkatAsalActive) {
                        options[i].remove();
                        continue;
                    }
                    
                    // Filter berdasarkan status tingkat
                    if (statusTingkat === 'sama') {
                        // Tingkat sama: hanya pertahankan kelas paralel
                        if (!optionTingkat || tingkatAsalActive.toUpperCase() !== optionTingkat.toUpperCase()) {
                            options[i].remove();
                        }
                    }
                    // Jika statusTingkat === 'beda', pertahankan semua kelas (kelas asal sudah dihapus di atas)
                }
            }
        }
    })();
    
    function updateKelasAsal() {
        var kelasId = $('#kelasAsal').val();
        $('#kelasLamaId').val(kelasId);
        $('#kelasBaruId').val($('#kelasTujuan').val());
        $('#kelasAsalIdBatal').val(kelasId);
        
        // Update URL tanpa reload
        var url = new URL(window.location.href);
        if (kelasId) {
            url.searchParams.set('kelas_asal', kelasId);
        } else {
            url.searchParams.delete('kelas_asal');
        }
        // Reset kelas tujuan dan status tingkat jika kelas asal berubah
        url.searchParams.delete('kelas_tujuan');
        url.searchParams.set('status_tingkat', 'sama'); // Reset ke default
        window.history.replaceState({}, '', url);
        
        // Reload untuk update data
        window.location.href = url.toString();
    }
    
    function filterKelasTujuan() {
        var statusTingkat = $('#statusTingkat').val();
        var options = $('#kelasTujuan option');
        var kelasAsalIdCurrent = $('#kelasAsal').val();
        var selectedOptionAsal = $('#kelasAsal option:selected');
        var tingkatAsalCurrent = selectedOptionAsal.length > 0 ? selectedOptionAsal.data('tingkat') : tingkatKelasAsal;
        
        // Update URL dengan status tingkat
        var url = new URL(window.location.href);
        url.searchParams.set('status_tingkat', statusTingkat);
        
        // Jika kelas asal belum dipilih, reset kelas tujuan dan reload
        if (!kelasAsalIdCurrent && !kelasAsalId) {
            url.searchParams.delete('kelas_tujuan');
            window.location.href = url.toString();
            return;
        }
        
        // Gunakan kelas asal yang aktif (dari select atau dari PHP)
        var kelasAsalActive = kelasAsalIdCurrent || kelasAsalId;
        var tingkatAsalActive = tingkatAsalCurrent || tingkatKelasAsal;
        
        options.each(function() {
            var optionValue = $(this).val();
            var optionTingkat = $(this).data('tingkat');
            var optionText = $(this).text().toLowerCase();
            
            // Selalu tampilkan option kosong
            if (optionValue === '') {
                $(this).show();
                return;
            }
            
            // Jangan tampilkan kelas asal
            if (optionValue === kelasAsalActive) {
                $(this).hide();
                return;
            }
            
            // Jangan tampilkan kelas Alumni
            if (optionText.indexOf('alumni') !== -1 || optionText.indexOf('lulus') !== -1) {
                $(this).hide();
                return;
            }
            
            // Filter berdasarkan status tingkat
            if (statusTingkat === 'sama') {
                // Tingkat sama: hanya tampilkan kelas paralel (tingkat sama dengan kelas asal)
                if (tingkatAsalActive && optionTingkat && tingkatAsalActive.toUpperCase() === optionTingkat.toUpperCase()) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            } else {
                // Tingkat beda: tampilkan semua kelas kecuali kelas asal
                $(this).show();
            }
        });
        
        // Reset kelas tujuan jika filter berubah
        $('#kelasTujuan').val('');
        
        // Update URL dan reload
        url.searchParams.delete('kelas_tujuan');
        window.location.href = url.toString();
    }
    
    function updateKelasTujuan() {
        var kelasId = $('#kelasTujuan').val();
        var statusTingkat = $('#statusTingkat').val();
        $('#kelasBaruId').val(kelasId);
        $('#kelasLamaId').val($('#kelasAsal').val());
        $('#kelasTujuanIdBatal').val(kelasId);
        
        // Update URL tanpa reload
        var url = new URL(window.location.href);
        if (kelasId) {
            url.searchParams.set('kelas_tujuan', kelasId);
        } else {
            url.searchParams.delete('kelas_tujuan');
        }
        if (statusTingkat) {
            url.searchParams.set('status_tingkat', statusTingkat);
        }
        window.history.replaceState({}, '', url);
        
        // Reload untuk update data
        if (kelasId) {
            window.location.href = url.toString();
        }
    }
    
    function resetKelasAsal() {
        $('#kelasAsal').val('');
        updateKelasAsal();
    }
    
    function resetKelasTujuan() {
        $('#kelasTujuan').val('');
        updateKelasTujuan();
    }
    
    function toggleSelectAllAsal() {
        var selectAll = document.getElementById('selectAllAsal');
        var checkboxes = document.querySelectorAll('.siswa-checkbox-asal');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAll.checked;
        });
        updateTombolPindah();
    }
    
    function toggleSelectAllTujuan() {
        var selectAll = document.getElementById('selectAllTujuan');
        var checkboxes = document.querySelectorAll('.siswa-checkbox-tujuan');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAll.checked;
        });
        updateTombolBatalPindah();
    }
    
    // Fungsi untuk update visibility tombol Pindah Kelas
    function updateTombolPindah() {
        var checkedBoxes = document.querySelectorAll('.siswa-checkbox-asal:checked');
        var btnPindah = document.getElementById('btnPindahKelas');
        if (btnPindah) {
            if (checkedBoxes.length > 0) {
                btnPindah.style.display = 'inline-block';
            } else {
                btnPindah.style.display = 'none';
            }
        }
    }
    
    // Fungsi untuk update visibility tombol Batal Pindah
    function updateTombolBatalPindah() {
        var checkedBoxes = document.querySelectorAll('.siswa-checkbox-tujuan:checked');
        var btnBatal = document.getElementById('btnBatalPindah');
        if (btnBatal) {
            if (checkedBoxes.length > 0) {
                btnBatal.style.display = 'inline-block';
            } else {
                btnBatal.style.display = 'none';
            }
        }
    }
    
    function batalPindah() {
        var kelasAsalId = $('#kelasAsal').val();
        var kelasTujuanId = $('#kelasTujuan').val();
        var checkedBoxes = document.querySelectorAll('.siswa-checkbox-tujuan:checked');
        
        var warnings = [];
        
        if (!kelasAsalId) {
            warnings.push('Silahkan pilih kelas asal');
        }
        if (!kelasTujuanId) {
            warnings.push('Silahkan pilih kelas tujuan');
        }
        if (checkedBoxes.length === 0) {
            warnings.push('Pilih minimal satu siswa yang akan dibatalkan pindah');
        }
        
        if (warnings.length > 0) {
            $('#warningList').empty();
            warnings.forEach(function(warning) {
                $('#warningList').append('<li>' + warning + '</li>');
            });
            $('#warningBox').show();
            return;
        }
        
        $('#warningBox').hide();
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Anda akan membatalkan pindah ' + checkedBoxes.length + ' siswa dan mengembalikannya ke kelas asal.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formBatalPindah').submit();
            }
        });
    }
    
    function pindahKelas() {
        var kelasAsalId = $('#kelasAsal').val();
        var kelasTujuanId = $('#kelasTujuan').val();
        var checkedBoxes = document.querySelectorAll('.siswa-checkbox-asal:checked');
        
        var warnings = [];
        
        if (!kelasAsalId) {
            warnings.push('Silahkan pilih kelas asal');
        }
        if (!kelasTujuanId) {
            warnings.push('Silahkan pilih kelas tujuan');
        }
        if (checkedBoxes.length === 0) {
            warnings.push('Pilih minimal satu siswa yang akan dipindah');
        }
        
        if (warnings.length > 0) {
            $('#warningList').empty();
            warnings.forEach(function(warning) {
                $('#warningList').append('<li>' + warning + '</li>');
            });
            $('#warningBox').show();
            return;
        }
        
        $('#warningBox').hide();
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Anda akan memindahkan ' + checkedBoxes.length + ' siswa ke kelas tujuan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Pindahkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formPindahKelas').submit();
            }
        });
    }
    
    // Inisialisasi filter kelas tujuan saat halaman dimuat
    $(document).ready(function() {
        // Terapkan filter kelas tujuan berdasarkan status tingkat yang dipilih
        var statusTingkat = $('#statusTingkat').val() || 'sama';
        var kelasAsalIdCurrent = $('#kelasAsal').val();
        var selectedOptionAsal = $('#kelasAsal option:selected');
        var tingkatAsalActive = (kelasAsalIdCurrent && selectedOptionAsal.length > 0) ? selectedOptionAsal.data('tingkat') : tingkatKelasAsal;
        var kelasAsalActive = kelasAsalIdCurrent || kelasAsalId;
        
        // Hapus semua option kelas yang tidak sesuai filter
        $('#kelasTujuan option').each(function() {
            var optionValue = $(this).val();
            var optionTingkat = $(this).data('tingkat');
            var optionText = $(this).text().toLowerCase();
            
            // Selalu pertahankan option kosong
            if (optionValue === '') {
                return;
            }
            
            // Jika kelas asal belum dipilih atau tingkat belum diketahui, hapus semua option
            if (!kelasAsalActive || !tingkatAsalActive) {
                $(this).remove();
                return;
            }
            
            // Hapus kelas asal
            if (optionValue == kelasAsalActive) {
                $(this).remove();
                return;
            }
            
            // Hapus kelas Alumni
            if (optionText.indexOf('alumni') !== -1 || optionText.indexOf('lulus') !== -1) {
                $(this).remove();
                return;
            }
            
            // Filter berdasarkan status tingkat
            if (statusTingkat === 'sama') {
                // Tingkat sama: hanya pertahankan kelas paralel (tingkat sama dengan kelas asal)
                if (!optionTingkat || tingkatAsalActive.toUpperCase() !== optionTingkat.toUpperCase()) {
                    $(this).remove();
                }
            }
            // Jika statusTingkat === 'beda', pertahankan semua kelas (kelas asal sudah dihapus di atas)
        });
        
        // Event listener untuk checkbox siswa kelas asal
        $(document).on('change', '.siswa-checkbox-asal', function() {
            updateTombolPindah();
        });
        
        // Event listener untuk checkbox siswa kelas tujuan
        $(document).on('change', '.siswa-checkbox-tujuan', function() {
            updateTombolBatalPindah();
        });
        
        // Inisialisasi visibility tombol saat halaman dimuat
        updateTombolPindah();
        updateTombolBatalPindah();
        
        // Inisialisasi DataTables
        <?php if (!empty($kelas_asal_filter) && count($siswa_asal_data) > 0): ?>
        if ($('#tableAsal').length > 0) {
            $('#tableAsal').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[3, 'asc']],
                pageLength: 10,
                columnDefs: [
                    { orderable: false, targets: [0] }
                ]
            });
        }
        <?php endif; ?>
        
        <?php if (!empty($kelas_tujuan_filter) && count($siswa_tujuan_data) > 0): ?>
        if ($('#tableTujuan').length > 0) {
            $('#tableTujuan').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[3, 'asc']],
                pageLength: 10,
                columnDefs: [
                    { orderable: false, targets: [0] }
                ]
            });
        }
        <?php endif; ?>
    });
</script>
