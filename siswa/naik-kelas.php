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

// Fungsi untuk menghitung tahun ajaran tujuan (tahun ajaran aktif + 1 tahun)
function hitungTahunAjaranTujuan($tahun_ajaran_aktif) {
    if (empty($tahun_ajaran_aktif)) {
        return '';
    }
    
    // Format tahun ajaran: YYYY/YYYY atau YYYY-YYYY
    // Contoh: 2025/2026 atau 2025-2026
    $tahun_ajaran_aktif = trim($tahun_ajaran_aktif);
    
    // Cek apakah menggunakan slash atau dash
    if (strpos($tahun_ajaran_aktif, '/') !== false) {
        $parts = explode('/', $tahun_ajaran_aktif);
    } elseif (strpos($tahun_ajaran_aktif, '-') !== false) {
        $parts = explode('-', $tahun_ajaran_aktif);
    } else {
        // Jika format tidak sesuai, coba parse tahun pertama
        if (preg_match('/^(\d{4})/', $tahun_ajaran_aktif, $matches)) {
            $tahun_awal = intval($matches[1]);
            $tahun_akhir = $tahun_awal + 1;
            return $tahun_akhir . '/' . ($tahun_akhir + 1);
        }
        return '';
    }
    
    if (count($parts) >= 2) {
        $tahun_awal = intval(trim($parts[0]));
        $tahun_akhir = intval(trim($parts[1]));
        
        // Validasi tahun
        if ($tahun_awal > 0 && $tahun_akhir > 0 && $tahun_akhir == $tahun_awal + 1) {
            // Tahun ajaran tujuan = tahun awal + 1 / tahun akhir + 1
            $tahun_awal_tujuan = $tahun_awal + 1;
            $tahun_akhir_tujuan = $tahun_akhir + 1;
            
            // Gunakan separator yang sama dengan tahun ajaran aktif
            $separator = strpos($tahun_ajaran_aktif, '/') !== false ? '/' : '-';
            return $tahun_awal_tujuan . $separator . $tahun_akhir_tujuan;
        }
    }
    
    return '';
}

// Hitung tahun ajaran tujuan
$tahun_ajaran_tujuan = hitungTahunAjaranTujuan($tahun_ajaran);

// Pastikan kolom tahun_ajaran_lulus ada di tabel siswa
try {
    $check_column = $conn->query("SHOW COLUMNS FROM siswa LIKE 'tahun_ajaran_lulus'");
    if ($check_column->num_rows == 0) {
        $conn->query("ALTER TABLE siswa ADD COLUMN tahun_ajaran_lulus VARCHAR(20) NULL AFTER kelas_id");
    }
} catch (Exception $e) {
    // Kolom mungkin sudah ada atau ada error lain, lanjutkan saja
}

// Cek apakah kelas tujuan adalah kelas Alumni
$is_kelas_alumni = false;
if (isset($_POST['kelas_baru_id'])) {
    $kelas_baru_id_check = intval($_POST['kelas_baru_id']);
    if ($kelas_baru_id_check > 0) {
        $stmt_check = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $stmt_check->bind_param("i", $kelas_baru_id_check);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($row_check = $result_check->fetch_assoc()) {
            $nama_kelas_baru = strtolower($row_check['nama_kelas']);
            if (stripos($nama_kelas_baru, 'alumni') !== false || stripos($nama_kelas_baru, 'lulus') !== false) {
                $is_kelas_alumni = true;
            }
        }
        $stmt_check->close();
    }
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
        // Cek apakah kelas tujuan adalah kelas Alumni
        $is_kelas_alumni = false;
        $stmt_check_alumni = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
        $stmt_check_alumni->bind_param("i", $kelas_baru_id);
        $stmt_check_alumni->execute();
        $result_check_alumni = $stmt_check_alumni->get_result();
        if ($row_check_alumni = $result_check_alumni->fetch_assoc()) {
            $nama_kelas_baru = strtolower($row_check_alumni['nama_kelas']);
            if (stripos($nama_kelas_baru, 'alumni') !== false || stripos($nama_kelas_baru, 'lulus') !== false) {
                $is_kelas_alumni = true;
            }
        }
        $stmt_check_alumni->close();
        
        $naik_count = 0;
        $conn->begin_transaction();
        
        try {
            foreach ($siswa_ids as $siswa_id) {
                $siswa_id = intval($siswa_id);
                if ($siswa_id > 0) {
                    // Validasi: Pastikan siswa benar-benar dari kelas lama
                    $stmt_validate = $conn->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
                    $stmt_validate->bind_param("i", $siswa_id);
                    $stmt_validate->execute();
                    $result_validate = $stmt_validate->get_result();
                    $siswa_data = $result_validate->fetch_assoc();
                    $stmt_validate->close();
                    
                    // Skip jika siswa tidak ditemukan atau bukan dari kelas lama
                    if (!$siswa_data || $siswa_data['kelas_id'] != $kelas_lama_id) {
                        continue;
                    }
                    
                    // Jika kelas tujuan adalah Alumni, simpan tahun ajaran lulus
                    if ($is_kelas_alumni) {
                        // Jika tahun ajaran aktif kosong, coba ambil dari nilai siswa terakhir
                        $tahun_ajaran_lulus = $tahun_ajaran;
                        if (empty($tahun_ajaran_lulus)) {
                            // Ambil tahun ajaran dari nilai siswa terakhir
                            $stmt_tahun = $conn->prepare("SELECT tahun_ajaran FROM nilai_siswa WHERE siswa_id = ? ORDER BY tahun_ajaran DESC LIMIT 1");
                            $stmt_tahun->bind_param("i", $siswa_id);
                            $stmt_tahun->execute();
                            $result_tahun = $stmt_tahun->get_result();
                            if ($row_tahun = $result_tahun->fetch_assoc()) {
                                $tahun_ajaran_lulus = $row_tahun['tahun_ajaran'];
                            }
                            $stmt_tahun->close();
                        }
                        
                        // Jika masih kosong, gunakan tahun ajaran saat ini (dari sistem)
                        if (empty($tahun_ajaran_lulus)) {
                            $tahun_sekarang = date('Y');
                            $tahun_ajaran_lulus = ($tahun_sekarang - 1) . '/' . $tahun_sekarang;
                        }
                        
                        // Update siswa dengan kelas Alumni dan tahun ajaran lulus
                        $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ?, tahun_ajaran_lulus = ? WHERE id = ?");
                        $stmt->bind_param("isi", $kelas_baru_id, $tahun_ajaran_lulus, $siswa_id);
                    } else {
                        // Jika bukan Alumni, update kelas_id saja (jaga tahun_ajaran_lulus jika sudah ada)
                        // Reset tahun_ajaran_lulus jika bukan Alumni (untuk memastikan hanya alumni yang punya tahun lulus)
                        $stmt = $conn->prepare("UPDATE siswa SET kelas_id = ?, tahun_ajaran_lulus = NULL WHERE id = ?");
                        $stmt->bind_param("ii", $kelas_baru_id, $siswa_id);
                    }
                    if ($stmt->execute()) {
                        $naik_count++;
                    }
                    $stmt->close();
                }
            }
            
            // Update jumlah siswa di kelas lama (akan otomatis kosong jika semua siswa naik)
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_lama_id) WHERE id = $kelas_lama_id");
            
            // Update jumlah siswa di kelas baru (akan terisi dengan siswa yang naik)
            $conn->query("UPDATE kelas SET jumlah_siswa = (SELECT COUNT(*) FROM siswa WHERE kelas_id = $kelas_baru_id) WHERE id = $kelas_baru_id");
            
            $conn->commit();
            
            // Buat pesan sukses yang lebih informatif
            $success_message = "Berhasil menaikkan $naik_count siswa dari kelas lama ke kelas baru!";
            if ($naik_count > 0) {
                // Cek apakah kelas lama sekarang kosong
                $stmt_check_kosong = $conn->prepare("SELECT COUNT(*) as jumlah FROM siswa WHERE kelas_id = ?");
                $stmt_check_kosong->bind_param("i", $kelas_lama_id);
                $stmt_check_kosong->execute();
                $result_check_kosong = $stmt_check_kosong->get_result();
                $data_check_kosong = $result_check_kosong->fetch_assoc();
                $stmt_check_kosong->close();
                
                if ($data_check_kosong['jumlah'] == 0) {
                    $success_message .= " Kelas lama sekarang kosong dan siap diisi siswa baru.";
                }
            }
            $success = $success_message;
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

// Filter kelas asal
$kelas_asal_filter = $_GET['kelas_asal'] ?? '';

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

// Kelas & siswa tujuan ditentukan otomatis dari kelas asal (tanpa dropdown)
$siswa_tujuan_data = [];
$kelas_tujuan_resolved_id = null;
$nama_kelas_tujuan_resolved = '';
$kelas_tujuan_candidates = [];

if (!empty($kelas_asal_filter) && !empty($tingkat_kelas_asal) && !empty($tingkat_tujuan)) {
    if ($tingkat_tujuan === 'LULUS') {
        $query_alumni = "SELECT id, nama_kelas FROM kelas WHERE nama_kelas LIKE '%Alumni%' OR nama_kelas LIKE '%Lulus%' ORDER BY nama_kelas";
        $result_alumni = $conn->query($query_alumni);
        if ($result_alumni) {
            while ($row_alumni = $result_alumni->fetch_assoc()) {
                $kelas_tujuan_candidates[] = [
                    'id' => (int) $row_alumni['id'],
                    'nama' => trim($row_alumni['nama_kelas'] ?? '') ?: 'Alumni',
                ];
            }
        }
        if (empty($kelas_tujuan_candidates)) {
            try {
                $stmt_alumni_ins = $conn->prepare("INSERT INTO kelas (nama_kelas, jumlah_siswa) VALUES ('Alumni', 0)");
                if ($stmt_alumni_ins && $stmt_alumni_ins->execute()) {
                    $nid = (int) $conn->insert_id;
                    $kelas_tujuan_candidates[] = ['id' => $nid, 'nama' => 'Alumni'];
                }
                if ($stmt_alumni_ins) {
                    $stmt_alumni_ins->close();
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
    } else {
        $tingkat_tujuan_normalized = normalizeTingkat($tingkat_tujuan);
        $kelas_list->data_seek(0);
        while ($kelas = $kelas_list->fetch_assoc()) {
            if (stripos($kelas['nama_kelas'], 'Alumni') !== false || stripos($kelas['nama_kelas'], 'Lulus') !== false) {
                continue;
            }
            $tingkat_kelas_normalized = normalizeTingkat(getTingkatFromKelas($kelas['nama_kelas']));
            if (!empty($tingkat_kelas_normalized) && $tingkat_kelas_normalized === $tingkat_tujuan_normalized) {
                $kelas_tujuan_candidates[] = [
                    'id' => (int) $kelas['id'],
                    'nama' => $kelas['nama_kelas'],
                ];
            }
        }
    }

    usort($kelas_tujuan_candidates, function ($a, $b) {
        return strcasecmp($a['nama'], $b['nama']);
    });

    if (!empty($kelas_tujuan_candidates)) {
        $kelas_tujuan_resolved_id = $kelas_tujuan_candidates[0]['id'];
        $nama_kelas_tujuan_resolved = $kelas_tujuan_candidates[0]['nama'];
    }
}

$kelas_tujuan_ambiguous = count($kelas_tujuan_candidates) > 1;

if ($kelas_tujuan_resolved_id !== null && $kelas_tujuan_resolved_id > 0) {
    $kt_bind = $kelas_tujuan_resolved_id;
    $query = "SELECT s.*, k.nama_kelas, k.id AS kelas_id
              FROM siswa s
              LEFT JOIN kelas k ON s.kelas_id = k.id
              WHERE s.kelas_id = ?
              ORDER BY s.nama ASC, s.nisn ASC";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $kt_bind);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $siswa_tujuan_data[] = $row;
                }
            }
        }
        $stmt->close();
    }
}

// Set page title (variabel lokal)
$page_title = 'Naik Kelas';
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-arrow-up"></i> Kenaikan Kelas</h5>
        <p class="mb-0 text-white" style="font-size: 14px;">Menu ini digunakan untuk menaikkan siswa dari tingkatan sebelumnya.</p>
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
                            <input type="hidden" name="kelas_baru_id" id="kelasBaruId" value="<?php echo (int) ($kelas_tujuan_resolved_id ?? 0); ?>">
                            
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
                                                    <input type="checkbox" name="siswa_ids[]" value="<?php echo $row['id']; ?>" class="siswa-checkbox-asal" onchange="updateButtonVisibility()">
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
                            <!-- Tombol Naik Kelas dan Reset -->
                            <div class="mt-3 d-flex gap-2">
                                <div id="btnNaikContainer" style="display: none;">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="naikKelas()">
                                        <i class="fas fa-arrow-up"></i> Naik Kelas
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="resetKelasAsal()">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                </div>
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
                        <h6 class="mb-0">
                            <?php if (!empty($tahun_ajaran_tujuan)): ?>
                                <?php echo htmlspecialchars($tahun_ajaran_tujuan); ?> Tahun Ajaran Tujuan
                            <?php else: ?>
                                Tahun Ajaran Tujuan
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formBatalNaik">
                            <input type="hidden" name="action" value="batal_naik">
                            <input type="hidden" name="kelas_asal_id" id="kelasAsalIdBatal" value="<?php echo $kelas_asal_filter; ?>">
                            <input type="hidden" name="kelas_tujuan_id" id="kelasTujuanIdBatal" value="<?php echo (int) ($kelas_tujuan_resolved_id ?? 0); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Kelas tujuan</label>
                                <input type="text"
                                       id="kelasTujuanTampilan"
                                       class="form-control"
                                       disabled
                                       readonly
                                       value="<?php echo !empty($nama_kelas_tujuan_resolved) ? htmlspecialchars($nama_kelas_tujuan_resolved) : (empty($kelas_asal_filter) ? '— Pilih kelas asal di panel kiri —' : (!empty($tingkat_tujuan) ? '— Tidak ada kelas yang cocok —' : '—')); ?>">
                                <small class="text-muted">Ditetapkan otomatis sesuai tingkat naik dari kelas asal.</small>
                                <?php if ($kelas_tujuan_ambiguous): ?>
                                    <div class="alert alert-warning py-2 small mb-0 mt-2">
                                        <i class="fas fa-exclamation-triangle"></i> Terdapat <strong><?php echo count($kelas_tujuan_candidates); ?></strong> kelas paralel untuk tingkat ini.
                                        Yang dipakai untuk naik kelas: <strong><?php echo htmlspecialchars($nama_kelas_tujuan_resolved); ?></strong> (nama kelas pertama menurut abjad).
                                        Pastikan sasaran ini sesuai, atau gabung siswa paralel lain di menu Kelas.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($kelas_asal_filter) && !empty($tingkat_tujuan)): ?>
                            <?php 
                            $jumlah_siswa_tujuan = count($siswa_tujuan_data);
                            
                            echo "<!-- kelas_tujuan_resolved: " . (int) ($kelas_tujuan_resolved_id ?? 0) . ', nama: ' . htmlspecialchars($nama_kelas_tujuan_resolved ?: '-') . ", paralel:" . count($kelas_tujuan_candidates) . " -->\n";
                            
                            if (!$kelas_tujuan_resolved_id) {
                                echo '<div class="alert alert-warning mb-3">';
                                echo '<i class="fas fa-exclamation-triangle"></i> ';
                                echo '<strong>Peringatan:</strong> Tidak ditemukan kelas dengan tingkat tujuan "' . htmlspecialchars((string) $tingkat_tujuan) . '". ';
                                echo 'Pastikan nama kelas di database menggunakan format yang benar (misalnya: "2A", "II A", "II", dll).';
                                echo '</div>';
                            }
                            ?>
                            <?php if ($kelas_tujuan_resolved_id): ?>
                                <?php if ($jumlah_siswa_tujuan > 0): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Info:</strong> Kelas <?php echo htmlspecialchars($nama_kelas_tujuan_resolved); ?> berisi <?php echo $jumlah_siswa_tujuan; ?> siswa 
                                        (diurutkan berdasarkan nama, kemudian NISN). Siswa yang naik bergabung ke kelas ini.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Info:</strong> Kelas <?php echo htmlspecialchars($nama_kelas_tujuan_resolved); ?> masih kosong siswa yang tercatat.
                                    </div>
                                <?php endif; ?>
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
                                                    <input type="checkbox" name="siswa_ids_batal[]" value="<?php echo $row['id']; ?>" class="siswa-checkbox-tujuan" onchange="updateButtonVisibility()">
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
                            <div class="mt-3 d-flex gap-2">
                                <div id="btnBatalContainer" style="display: none;">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="batalNaik()">
                                        <i class="fas fa-undo"></i> Batal Naik
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Pilih kelas asal terlebih dahulu untuk melihat kelas tujuan.
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
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
        window.history.replaceState({}, '', url);
        
        // Reload untuk update data
        window.location.href = url.toString();
    }
    
    function toggleSelectAllAsal() {
        var selectAll = document.getElementById('selectAllAsal');
        var checkboxes = document.querySelectorAll('.siswa-checkbox-asal');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAll.checked;
        });
        updateButtonVisibility();
    }
    
    function toggleSelectAllTujuan() {
        var selectAll = document.getElementById('selectAllTujuan');
        var checkboxes = document.querySelectorAll('.siswa-checkbox-tujuan');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = selectAll.checked;
        });
        updateButtonVisibility();
    }
    
    // Fungsi untuk menampilkan/menyembunyikan tombol berdasarkan checkbox yang dipilih
    function updateButtonVisibility() {
        // Cek checkbox siswa asal
        var checkedAsal = document.querySelectorAll('.siswa-checkbox-asal:checked');
        var btnNaikContainer = document.getElementById('btnNaikContainer');
        if (btnNaikContainer) {
            if (checkedAsal.length > 0) {
                btnNaikContainer.style.display = 'block';
            } else {
                btnNaikContainer.style.display = 'none';
            }
        }
        
        // Cek checkbox siswa tujuan
        var checkedTujuan = document.querySelectorAll('.siswa-checkbox-tujuan:checked');
        var btnBatalContainer = document.getElementById('btnBatalContainer');
        if (btnBatalContainer) {
            if (checkedTujuan.length > 0) {
                btnBatalContainer.style.display = 'block';
            } else {
                btnBatalContainer.style.display = 'none';
            }
        }
    }
    
    function resetKelasAsal() {
        $('#kelasAsal').val('');
        updateKelasAsal();
    }
    
    function naikKelas() {
        var kelasAsalId = $('#kelasAsal').val();
        var kelasTujuanId = $('#kelasBaruId').val();
        var checkedBoxes = document.querySelectorAll('.siswa-checkbox-asal:checked');
        
        var warnings = [];
        
        if (!kelasAsalId) {
            warnings.push('Silahkan pilih kelas asal');
        }
        if (!kelasTujuanId || parseInt(kelasTujuanId, 10) <= 0) {
            warnings.push('Kelas tujuan otomatis belum tersedia. Periksa data kelas / penamaan tingkat di Master Kelas.');
        }
        if (checkedBoxes.length === 0) {
            warnings.push('Silahkan klik pada siswa yang akan di naikkan');
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
        var kelasTujuanId = $('#kelasTujuanIdBatal').val();
        var checkedBoxes = document.querySelectorAll('.siswa-checkbox-tujuan:checked');
        
        var warnings = [];
        
        if (!kelasAsalId) {
            warnings.push('Silahkan pilih kelas asal');
        }
        if (!kelasTujuanId || parseInt(kelasTujuanId, 10) <= 0) {
            warnings.push('Kelas tujuan belum tersedia.');
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
                ],
                drawCallback: function() {
                    // Update visibility tombol setelah DataTables redraw
                    updateButtonVisibility();
                }
            });
        }
        <?php endif; ?>
        
        <?php if (!empty($kelas_asal_filter) && count($siswa_tujuan_data) > 0): ?>
        if ($('#tableTujuan').length > 0) {
            $('#tableTujuan').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[3, 'asc'], [2, 'asc']],
                pageLength: 10,
                columnDefs: [
                    { orderable: false, targets: [0] }
                ],
                drawCallback: function() {
                    // Update visibility tombol setelah DataTables redraw
                    updateButtonVisibility();
                }
            });
        }
        <?php endif; ?>
        
        // Inisialisasi visibility tombol saat halaman dimuat
        updateButtonVisibility();
    });
</script>
