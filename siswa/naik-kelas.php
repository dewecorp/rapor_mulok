<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();
$success = '';
$error = '';

// Ambil tahun ajaran aktif
$tahun_ajaran = '';
try {
    $query_profil = "SELECT tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    $tahun_ajaran = '';
}

// Handle naik kelas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'naik') {
    $kelas_lama_id = $_POST['kelas_lama_id'] ?? 0;
    $kelas_baru_id = $_POST['kelas_baru_id'] ?? 0;
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    
    if (!$kelas_lama_id || !$kelas_baru_id) {
        $error = 'Pilih kelas asal dan kelas tujuan!';
    } elseif (empty($siswa_ids) || !is_array($siswa_ids)) {
        $error = 'Pilih siswa yang akan naik kelas!';
    } else {
        $naik_count = 0;
        $conn->begin_transaction();
        
        try {
            foreach ($siswa_ids as $siswa_id) {
                $siswa_id = intval($siswa_id);
                if ($siswa_id > 0) {
                    $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $kelas_baru_id, $siswa_id);
                    if ($stmt->execute()) {
                        $naik_count++;
                    }
                }
            }
            
            // Update jumlah siswa di kelas lama
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_lama_id) WHERE id = $kelas_lama_id");
            
            // Update jumlah siswa di kelas baru
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_baru_id) WHERE id = $kelas_baru_id");
            
            $conn->commit();
            $success = "Berhasil menaikkan $naik_count siswa!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Gagal menaikkan siswa: ' . $e->getMessage();
        }
    }
}

// Handle batal naik (dari panel kanan)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'batal_naik') {
    $siswa_ids_batal = $_POST['siswa_ids_batal'] ?? [];
    $kelas_tujuan_id = $_POST['kelas_tujuan_id'] ?? 0;
    $kelas_asal_id = $_POST['kelas_asal_id'] ?? 0;
    
    if (empty($siswa_ids_batal) || !is_array($siswa_ids_batal)) {
        $error = 'Pilih siswa yang akan dibatalkan naik!';
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
            $success = "Berhasil membatalkan naik $batal_count siswa!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Gagal membatalkan naik siswa: ' . $e->getMessage();
        }
    }
}

// Filter kelas asal dan tujuan
$kelas_asal_filter = $_GET['kelas_asal'] ?? '';
$kelas_tujuan_filter = $_GET['kelas_tujuan'] ?? '';

// Ambil data kelas
$query_kelas = "SELECT * FROM kelas ORDER BY nama_kelas";
$kelas_list = $conn->query($query_kelas);

// Fungsi untuk normalisasi tingkat (konversi angka ke romawi)
function normalizeTingkat($tingkat) {
    $tingkat = strtoupper(trim($tingkat));
    $normalize_map = [
        'I' => 'I', '1' => 'I',
        'II' => 'II', '2' => 'II',
        'III' => 'III', '3' => 'III',
        'IV' => 'IV', '4' => 'IV',
        'V' => 'V', '5' => 'V',
        'VI' => 'VI', '6' => 'VI'
    ];
    return $normalize_map[$tingkat] ?? $tingkat;
}

// Fungsi untuk mendapatkan tingkat dari nama kelas (I, II, III, IV, V, VI)
function getTingkatFromKelas($nama_kelas) {
    $nama_kelas = trim($nama_kelas);
    // Cek apakah nama kelas mengandung angka romawi atau angka biasa
    // Urutan penting: yang lebih panjang harus dicocokkan terlebih dahulu (VI sebelum V, III sebelum II, dll)
    if (preg_match('/^(VI|IV|III|II|V|I|6|4|3|2|5|1)/i', $nama_kelas, $matches)) {
        return normalizeTingkat(strtoupper($matches[1]));
    }
    return '';
}

// Fungsi untuk mendapatkan tingkat berikutnya
function getTingkatBerikutnya($tingkat) {
    $tingkat = normalizeTingkat($tingkat);
    $tingkat_map = [
        'I' => 'II',
        'II' => 'III',
        'III' => 'IV',
        'IV' => 'V',
        'V' => 'VI',
        'VI' => 'LULUS'
    ];
    return $tingkat_map[$tingkat] ?? '';
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

// Ambil tingkat tujuan berdasarkan kelas asal
$tingkat_tujuan = '';
if (!empty($tingkat_kelas_asal)) {
    $tingkat_tujuan = getTingkatBerikutnya($tingkat_kelas_asal);
}

// Ambil daftar tingkat unik dari kelas
$tingkat_list = [];
$kelas_list->data_seek(0);
while ($kelas = $kelas_list->fetch_assoc()) {
    $tingkat = getTingkatFromKelas($kelas['nama_kelas']);
    if (!empty($tingkat) && !in_array($tingkat, $tingkat_list)) {
        $tingkat_list[] = $tingkat;
    }
}
sort($tingkat_list);

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

// Query data siswa kelas tujuan (untuk preview dan batal naik)
$siswa_tujuan_data = [];
$kelas_tujuan_auto = null; // Kelas tujuan yang otomatis ditentukan
$kelas_tujuan_ids = []; // Array ID kelas tujuan untuk query

// Jika kelas tujuan sudah dipilih manual, gunakan itu
if (!empty($kelas_tujuan_filter)) {
    $kelas_id = intval($kelas_tujuan_filter);
    $kelas_tujuan_auto = $kelas_id;
    $kelas_tujuan_ids[] = $kelas_id;
} 
// Jika kelas asal sudah dipilih tapi kelas tujuan belum dipilih, ambil semua kelas dengan tingkat tujuan
elseif (!empty($kelas_asal_filter) && !empty($tingkat_tujuan)) {
    // Jika kelas 6, cari kelas Alumni
    if ($tingkat_tujuan == 'LULUS') {
        $query_alumni = "SELECT id FROM kelas WHERE nama_kelas LIKE '%Alumni%' OR nama_kelas LIKE '%Lulus%' ORDER BY nama_kelas";
        $result_alumni = $conn->query($query_alumni);
        if ($result_alumni) {
            while ($row_alumni = $result_alumni->fetch_assoc()) {
                $kelas_tujuan_ids[] = $row_alumni['id'];
            }
            if (!empty($kelas_tujuan_ids)) {
                $kelas_tujuan_auto = $kelas_tujuan_ids[0]; // Untuk display
            }
        }
    } 
    // Jika bukan kelas 6, ambil semua kelas dengan tingkat tujuan
    else {
        $tingkat_tujuan_normalized = normalizeTingkat($tingkat_tujuan);
        // Reset pointer kelas_list dan ambil ulang data kelas untuk memastikan data fresh
        $query_kelas_fresh = "SELECT * FROM kelas ORDER BY nama_kelas";
        $kelas_list_fresh = $conn->query($query_kelas_fresh);
        
        if ($kelas_list_fresh) {
            while ($kelas = $kelas_list_fresh->fetch_assoc()) {
                // Skip kelas Alumni untuk kelas selain kelas 6
                if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                    continue;
                }
                
                $tingkat_kelas = getTingkatFromKelas($kelas['nama_kelas']);
                $tingkat_kelas_normalized = normalizeTingkat($tingkat_kelas);
                // Debug: log untuk melihat proses matching
                // error_log("Kelas: {$kelas['nama_kelas']}, Tingkat: $tingkat_kelas, Normalized: $tingkat_kelas_normalized, Target: $tingkat_tujuan_normalized");
                // Match tingkat yang sudah dinormalisasi
                if (!empty($tingkat_kelas_normalized) && $tingkat_kelas_normalized == $tingkat_tujuan_normalized) {
                    $kelas_tujuan_ids[] = $kelas['id'];
                }
            }
            if (!empty($kelas_tujuan_ids)) {
                $kelas_tujuan_auto = $kelas_tujuan_ids[0]; // Untuk display
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
            error_log("Error executing siswa query: " . $stmt->error);
            error_log("Query: " . $query);
            error_log("Kelas IDs: " . implode(', ', $kelas_tujuan_ids));
        }
        $stmt->close();
    } else {
        // Debug: log error jika prepare gagal
        error_log("Error preparing siswa query: " . $conn->error);
        error_log("Query: " . $query);
    }
} else {
    // Debug: log jika kelas_tujuan_ids kosong
    error_log("kelas_tujuan_ids is empty. kelas_asal_filter: " . ($kelas_asal_filter ?? 'NULL') . ", tingkat_tujuan: " . ($tingkat_tujuan ?? 'NULL'));
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-arrow-up"></i> Kenaikan Kelas</h5>
        <p class="mb-0 text-muted" style="font-size: 14px;">Menu ini digunakan untuk menaikkan siswa dari tingkatan sebelumnya.</p>
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
        
        <div class="row">
            <!-- Panel Tahun Ajaran Asal -->
            <div class="col-md-6">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <?php if (!empty($tahun_ajaran)): ?>
                                <?php echo htmlspecialchars($tahun_ajaran); ?> Tahun Ajaran Asal
                            <?php else: ?>
                                Tahun Ajaran Asal
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formNaikKelas">
                            <input type="hidden" name="action" value="naik">
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
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Pilih kelas untuk melihat data siswa.
                                </div>
                            <?php endif; ?>
                            
                            <!-- Warning Box Asal -->
                            <div class="alert alert-danger mt-3" id="warningBoxAsal" style="display: none;">
                                <strong>Perhatikan:</strong>
                                <ul class="mb-0" id="warningListAsal">
                                </ul>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Panel Tahun Ajaran Tujuan -->
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Tahun Ajaran Tujuan</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formBatalNaik">
                            <input type="hidden" name="action" value="batal_naik">
                            <input type="hidden" name="kelas_asal_id" id="kelasAsalIdBatal" value="<?php echo $kelas_asal_filter; ?>">
                            <input type="hidden" name="kelas_tujuan_id" id="kelasTujuanIdBatal" value="<?php echo $kelas_tujuan_filter; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tingkat</label>
                                    <select class="form-select" id="tingkatTujuan" disabled>
                                        <option value="">--Pilih Kelas Asal Terlebih Dahulu--</option>
                                        <?php if (!empty($tingkat_tujuan)): ?>
                                            <?php if ($tingkat_tujuan == 'LULUS'): ?>
                                                <option value="LULUS" selected>Lulus</option>
                                            <?php else: ?>
                                                <option value="<?php echo htmlspecialchars($tingkat_tujuan); ?>" selected>
                                                    <?php echo htmlspecialchars($tingkat_tujuan); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted">Tingkat otomatis berdasarkan kelas asal yang dipilih</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kelas</label>
                                    <select class="form-select" id="kelasTujuan" onchange="updateKelasTujuan()">
                                    <option value="">--Pilih--</option>
                                    <?php 
                                    // Hanya tampilkan kelas tujuan jika kelas asal sudah dipilih
                                    if (!empty($kelas_asal_filter) && !empty($tingkat_kelas_asal)): 
                                        // Jika kelas asal adalah kelas 6, tampilkan Alumni
                                        if ($tingkat_kelas_asal == 'VI' || $tingkat_kelas_asal == '6'): 
                                            // Cari kelas Alumni di database
                                            $query_alumni = "SELECT * FROM kelas WHERE nama_kelas LIKE '%Alumni%' OR nama_kelas LIKE '%Lulus%' ORDER BY nama_kelas LIMIT 1";
                                            $result_alumni = $conn->query($query_alumni);
                                            $kelas_alumni_id = null;
                                            
                                            if ($result_alumni && $row_alumni = $result_alumni->fetch_assoc()):
                                                $kelas_alumni_id = $row_alumni['id'];
                                            else:
                                                // Jika tidak ada kelas Alumni, buat otomatis
                                                try {
                                                    $stmt_alumni = $conn->prepare("INSERT INTO kelas (nama_kelas, jumlah_siswa) VALUES ('Alumni', 0)");
                                                    if ($stmt_alumni->execute()) {
                                                        $kelas_alumni_id = $conn->insert_id;
                                                    }
                                                    $stmt_alumni->close();
                                                } catch (Exception $e) {
                                                    // Jika gagal membuat, gunakan opsi khusus
                                                }
                                            endif;
                                            
                                            if ($kelas_alumni_id):
                                        ?>
                                            <option value="<?php echo $kelas_alumni_id; ?>" 
                                                    data-tingkat="LULUS"
                                                    <?php echo $kelas_tujuan_filter == $kelas_alumni_id ? 'selected' : ''; ?>>
                                                Alumni
                                            </option>
                                        <?php 
                                            endif;
                                        // Jika bukan kelas 6, tampilkan kelas sesuai tingkat berikutnya (tidak termasuk Alumni)
                                        else:
                                            // Pastikan tingkat_tujuan sudah terisi
                                            if (!empty($tingkat_tujuan) && $tingkat_tujuan != 'LULUS'):
                                                // Normalisasi tingkat_tujuan untuk perbandingan
                                                $tingkat_tujuan_normalized = normalizeTingkat($tingkat_tujuan);
                                                $kelas_list->data_seek(0);
                                                while ($kelas = $kelas_list->fetch_assoc()): 
                                                    // Skip kelas Alumni untuk kelas selain kelas 6
                                                    if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                                                        continue;
                                                    }
                                                    
                                                    $tingkat_kelas = getTingkatFromKelas($kelas['nama_kelas']);
                                                    // Normalisasi tingkat kelas untuk perbandingan
                                                    $tingkat_kelas_normalized = normalizeTingkat($tingkat_kelas);
                                                    // Hanya tampilkan kelas dengan tingkat tujuan
                                                    if (!empty($tingkat_kelas_normalized) && $tingkat_kelas_normalized == $tingkat_tujuan_normalized):
                                        ?>
                                            <option value="<?php echo $kelas['id']; ?>" 
                                                    data-tingkat="<?php echo htmlspecialchars($tingkat_kelas_normalized); ?>"
                                                    <?php echo $kelas_tujuan_filter == $kelas['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                            </option>
                                        <?php 
                                                    endif;
                                                endwhile;
                                            endif;
                                        endif;
                                    endif; 
                                    ?>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if (!empty($kelas_asal_filter) && !empty($tingkat_tujuan)): ?>
                            <?php 
                            // Cek apakah kelas tujuan sudah kosong
                            $jumlah_siswa_tujuan = count($siswa_tujuan_data);
                            $is_kelas_6 = ($tingkat_kelas_asal == 'VI' || $tingkat_kelas_asal == '6');
                            $kelas_tujuan_display = $kelas_tujuan_filter ?: $kelas_tujuan_auto;
                            
                            // Debug: tampilkan info untuk troubleshooting
                            $debug_info = "<!-- Debug Info:\n";
                            $debug_info .= "Kelas Asal Filter: " . ($kelas_asal_filter ?? 'NULL') . "\n";
                            $debug_info .= "Tingkat Kelas Asal: " . ($tingkat_kelas_asal ?? 'NULL') . "\n";
                            $debug_info .= "Tingkat Tujuan: " . ($tingkat_tujuan ?? 'NULL') . "\n";
                            $debug_info .= "Tingkat Tujuan Normalized: " . (isset($tingkat_tujuan) ? normalizeTingkat($tingkat_tujuan) : 'NULL') . "\n";
                            $debug_info .= "Kelas Tujuan IDs: " . (empty($kelas_tujuan_ids) ? 'EMPTY' : implode(', ', $kelas_tujuan_ids)) . "\n";
                            $debug_info .= "Jumlah Siswa Tujuan: " . $jumlah_siswa_tujuan . "\n";
                            $debug_info .= "Kelas Tujuan Auto: " . ($kelas_tujuan_auto ?? 'NULL') . "\n";
                            $debug_info .= "-->";
                            echo $debug_info;
                            
                            // Tampilkan alert jika kelas_tujuan_ids kosong
                            if (empty($kelas_tujuan_ids)) {
                                echo '<div class="alert alert-warning mb-3">';
                                echo '<i class="fas fa-exclamation-triangle"></i> ';
                                echo '<strong>Peringatan:</strong> Tidak ditemukan kelas dengan tingkat tujuan "' . htmlspecialchars($tingkat_tujuan) . '". ';
                                echo 'Pastikan nama kelas di database menggunakan format yang benar (misalnya: "2A", "II A", "Kelas 2", dll).';
                                echo '</div>';
                            }
                            ?>
                            <?php if ($jumlah_siswa_tujuan > 0): ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Peringatan:</strong> Kelas tujuan masih memiliki <?php echo $jumlah_siswa_tujuan; ?> siswa. 
                                    Pastikan kelas tujuan sudah kosong sebelum melakukan naik kelas, atau lakukan naik kelas dari kelas 6 terlebih dahulu agar kelas di bawahnya sudah kosong.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mb-3">
                                    <i class="fas fa-check-circle"></i> 
                                    <strong>Kelas tujuan sudah kosong.</strong> 
                                    <?php if (!$is_kelas_6): ?>
                                        Pastikan kelas di atasnya sudah melakukan naik kelas terlebih dahulu.
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
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Pilih kelas asal terlebih dahulu untuk melihat kelas tujuan.
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($kelas_asal_filter) && !empty($tingkat_tujuan) && empty($kelas_tujuan_filter)): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Info:</strong> Tabel di atas menampilkan siswa dari kelas tujuan yang sesuai dengan tingkat berikutnya. 
                                    Pastikan kelas tujuan sudah kosong sebelum melakukan naik kelas, atau lakukan naik kelas dari kelas 6 terlebih dahulu agar kelas di bawahnya sudah kosong.
                                </div>
                            <?php endif; ?>
                            
                            <!-- Warning Box Tujuan -->
                            <div class="alert alert-danger mt-3" id="warningBoxTujuan" style="display: none;">
                                <strong>Perhatikan:</strong>
                                <ul class="mb-0" id="warningListTujuan">
                                </ul>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tombol Aksi -->
        <div class="row mt-3">
            <div class="col-md-6 text-center">
                <button type="button" class="btn btn-primary btn-lg" onclick="naikKelas()">
                    <i class="fas fa-arrow-up"></i> Naik Kelas
                </button>
            </div>
            <div class="col-md-6 text-center">
                <button type="button" class="btn btn-warning btn-lg" onclick="batalNaik()">
                    <i class="fas fa-undo"></i> Batal Naik
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Fungsi untuk mendapatkan tingkat berikutnya
    function getTingkatBerikutnya(tingkat) {
        tingkat = tingkat.toUpperCase();
        var tingkatMap = {
            'I': 'II',
            'II': 'III',
            'III': 'IV',
            'IV': 'V',
            'V': 'VI',
            'VI': 'LULUS',
            '1': '2',
            '2': '3',
            '3': '4',
            '4': '5',
            '5': '6',
            '6': 'LULUS'
        };
        return tingkatMap[tingkat] || '';
    }
    
    // Set tingkat tujuan berdasarkan kelas asal saat halaman dimuat
    <?php if (!empty($kelas_asal_filter) && !empty($tingkat_kelas_asal)): ?>
    $(document).ready(function() {
        var tingkatTujuan = '<?php echo htmlspecialchars($tingkat_tujuan, ENT_QUOTES); ?>';
        if (tingkatTujuan) {
            $('#tingkatTujuan').val(tingkatTujuan);
            filterKelasTujuan();
        }
    });
    <?php endif; ?>
    
    function filterKelasTujuan() {
        var tingkat = $('#tingkatTujuan').val();
        var options = $('#kelasTujuan option');
        
        if (tingkat) {
            options.each(function() {
                var optionTingkat = $(this).data('tingkat');
                if (optionTingkat && optionTingkat.toUpperCase() === tingkat.toUpperCase()) {
                    $(this).show();
                } else if ($(this).val() === '') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            options.show();
        }
        
        // Reset kelas jika tingkat berubah
        $('#kelasTujuan').val('');
        updateKelasTujuan();
    }
    
    function updateKelasAsal() {
        var kelasId = $('#kelasAsal').val();
        $('#kelasLamaId').val(kelasId);
        $('#kelasAsalIdBatal').val(kelasId);
        
        // Update URL dan reload untuk update data
        var url = new URL(window.location.href);
        if (kelasId) {
            url.searchParams.set('kelas_asal', kelasId);
        } else {
            url.searchParams.delete('kelas_asal');
        }
        // Reset kelas tujuan saat kelas asal berubah
        url.searchParams.delete('kelas_tujuan');
        window.history.replaceState({}, '', url);
        
        // Reload untuk update data
        window.location.href = url.toString();
    }
    
    function updateKelasTujuan() {
        var kelasId = $('#kelasTujuan').val();
        $('#kelasBaruId').val(kelasId);
        $('#kelasTujuanIdBatal').val(kelasId);
        
        // Update URL tanpa reload - data siswa tujuan sudah diambil otomatis berdasarkan tingkat tujuan
        var url = new URL(window.location.href);
        if (kelasId) {
            url.searchParams.set('kelas_tujuan', kelasId);
        } else {
            url.searchParams.delete('kelas_tujuan');
        }
        window.history.replaceState({}, '', url);
        
        // Tidak perlu reload karena data siswa tujuan sudah ditampilkan otomatis
        // Tabel siswa tujuan akan menampilkan semua siswa dari semua kelas dengan tingkat tujuan yang sesuai
    }
    
    function toggleSelectAllAsal() {
        var selectAll = document.getElementById('selectAllAsal');
        var checkboxes = document.querySelectorAll('.siswa-checkbox-asal');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAll.checked;
        });
    }
    
    function toggleSelectAllTujuan() {
        var selectAll = document.getElementById('selectAllTujuan');
        var checkboxes = document.querySelectorAll('.siswa-checkbox-tujuan');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAll.checked;
        });
    }
    
    function naikKelas() {
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
            warnings.push('Silahkan klik pada siswa yang akan di naikkan');
        }
        
        // Cek apakah kelas tujuan sudah kosong
        if (kelasTujuanId) {
            var siswaTujuanRows = $('#tableTujuan tbody tr').not(':has(.text-muted)');
            var jumlahSiswaTujuan = siswaTujuanRows.length;
            if (jumlahSiswaTujuan > 0) {
                warnings.push('Kelas tujuan masih memiliki ' + jumlahSiswaTujuan + ' siswa. Pastikan kelas tujuan sudah kosong sebelum melakukan naik kelas, atau lakukan naik kelas dari kelas 6 terlebih dahulu agar kelas di bawahnya sudah kosong.');
            }
        }
        
        if (warnings.length > 0) {
            $('#warningListAsal').empty();
            warnings.forEach(function(warning) {
                $('#warningListAsal').append('<li>' + warning + '</li>');
            });
            $('#warningBoxAsal').show();
            return;
        }
        
        $('#warningBoxAsal').hide();
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Anda akan menaikkan ' + checkedBoxes.length + ' siswa ke kelas yang lebih tinggi.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Naikkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formNaikKelas').submit();
            }
        });
    }
    
    function batalNaik() {
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
            warnings.push('Silahkan klik pada siswa yang akan dibatalkan naik');
        }
        
        if (warnings.length > 0) {
            $('#warningListTujuan').empty();
            warnings.forEach(function(warning) {
                $('#warningListTujuan').append('<li>' + warning + '</li>');
            });
            $('#warningBoxTujuan').show();
            return;
        }
        
        $('#warningBoxTujuan').hide();
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Anda akan membatalkan naik ' + checkedBoxes.length + ' siswa dan mengembalikannya ke kelas asal.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formBatalNaik').submit();
            }
        });
    }
    
    $(document).ready(function() {
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
                order: [[2, 'asc']],
                pageLength: 10,
                columnDefs: [
                    { orderable: false, targets: [0] }
                ]
            });
        }
        <?php endif; ?>
    });
</script>
