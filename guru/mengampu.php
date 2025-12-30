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

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';

// Cek apakah kolom kelas_id sudah ada
$has_kelas_id = false;
try {
    $check_kelas_id = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kelas_id'");
    $has_kelas_id = ($check_kelas_id && $check_kelas_id->num_rows > 0);
} catch (Exception $e) {
    $has_kelas_id = false;
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

// Ambil data materi mulok untuk dropdown (filter berdasarkan kelas yang dipilih)
// Jika kelas dipilih, hanya tampilkan materi yang kelas_id-nya sesuai atau NULL
// Ambil semester aktif dari profil
$semester_aktif = '1';
try {
    $stmt_profil = $conn->query("SELECT semester_aktif FROM profil_madrasah LIMIT 1");
    if ($stmt_profil && $stmt_profil->num_rows > 0) {
        $profil_data = $stmt_profil->fetch_assoc();
        $semester_aktif = $profil_data['semester_aktif'] ?? '1';
    }
} catch (Exception $e) {
    // Use default
}

// Cek apakah kolom semester sudah ada
$has_semester = false;
try {
    $check_semester = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'semester'");
    $has_semester = ($check_semester && $check_semester->num_rows > 0);
} catch (Exception $e) {
    $has_semester = false;
}

$materi_list = null;
if (!empty($kelas_filter) && $kelas_filter !== '' && $has_kelas_id) {
    $kelas_id_for_materi = intval($kelas_filter);
    if ($has_semester) {
        $query_materi = "SELECT * FROM materi_mulok 
                         WHERE (kelas_id = ? OR kelas_id IS NULL) 
                         AND (semester = ? OR semester IS NULL)
                         ORDER BY LOWER($kolom_kategori) ASC, LOWER(nama_mulok) ASC";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("is", $kelas_id_for_materi, $semester_aktif);
    } else {
        $query_materi = "SELECT * FROM materi_mulok 
                         WHERE kelas_id = ? OR kelas_id IS NULL 
                         ORDER BY LOWER($kolom_kategori) ASC, LOWER(nama_mulok) ASC";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $kelas_id_for_materi);
    }
    $stmt_materi->execute();
    $materi_list = $stmt_materi->get_result();
} else {
    // Jika kelas belum dipilih atau kelas_id belum ada, tampilkan semua materi
    if ($has_semester) {
        $query_materi = "SELECT * FROM materi_mulok 
                         WHERE semester = ? OR semester IS NULL
                         ORDER BY LOWER($kolom_kategori) ASC, LOWER(nama_mulok) ASC";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("s", $semester_aktif);
        $stmt_materi->execute();
        $materi_list = $stmt_materi->get_result();
    } else {
        $query_materi = "SELECT * FROM materi_mulok ORDER BY LOWER($kolom_kategori) ASC, LOWER(nama_mulok) ASC";
        $materi_list = $conn->query($query_materi);
    }
}

// Ambil data guru
$query_guru = "SELECT * FROM pengguna WHERE role IN ('guru', 'wali_kelas') ORDER BY nama";
$guru_list = $conn->query($query_guru);

// Ambil data kelas (exclude kelas Alumni)
$query_kelas = "SELECT * FROM kelas WHERE nama_kelas NOT LIKE '%Alumni%' AND nama_kelas NOT LIKE '%Lulus%' ORDER BY nama_kelas";
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
        
        // Tampilkan hanya materi mulok yang sesuai dengan kelas yang dipilih
        // Materi yang ditampilkan: materi dengan kelas_id = kelas yang dipilih ATAU kelas_id = NULL (untuk semua kelas)
        if ($has_kelas_id) {
            $query = "SELECT m.*, 
                      mm.id as mengampu_id,
                      mm.guru_id,
                      p.nama as nama_guru,
                      k.nama_kelas,
                      k_materi.nama_kelas as nama_kelas_materi
                      FROM materi_mulok m
                      LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                      LEFT JOIN pengguna p ON mm.guru_id = p.id
                      LEFT JOIN kelas k ON mm.kelas_id = k.id
                      LEFT JOIN kelas k_materi ON m.kelas_id = k_materi.id
                      WHERE m.kelas_id = ? OR m.kelas_id IS NULL
                      ORDER BY LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
        } else {
            // Jika kelas_id belum ada di tabel, tampilkan semua materi (fallback)
            $query = "SELECT m.*, 
                      mm.id as mengampu_id,
                      mm.guru_id,
                      p.nama as nama_guru,
                      k.nama_kelas,
                      NULL as nama_kelas_materi
                      FROM materi_mulok m
                      LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                      LEFT JOIN pengguna p ON mm.guru_id = p.id
                      LEFT JOIN kelas k ON mm.kelas_id = k.id
                      ORDER BY LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC";
        }
        
        if ($has_kelas_id) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $kelas_id, $kelas_id);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $kelas_id);
        }
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
            <?php if (!empty($kelas_filter)): ?>
            <button type="button" class="btn btn-light btn-sm" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="exportPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMengampu">
                <i class="fas fa-plus"></i> Tambah
            </button>
            <?php endif; ?>
        </div>
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
        
        <div class="mb-3">
            <label class="form-label">Filter Kelas</label>
            <select class="form-select" id="filterKelas" onchange="filterKelas()" style="max-width: 300px;">
                <option value="">-- Semua Kelas --</option>
                <?php 
                $kelas_list->data_seek(0);
                while ($kelas = $kelas_list->fetch_assoc()): 
                    // Skip kelas Alumni (double check untuk keamanan)
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
                        <th>Kelas</th>
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
                            $kategori_value = $row[$kolom_kategori] ?? '';
                            $materi_id = $row['id'] ?? 0; // id dari materi_mulok (karena query menggunakan m.*)
                            $nama_kelas = $row['nama_kelas'] ?? '';
                            $nama_kelas_materi = $row['nama_kelas_materi'] ?? '';
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <?php if (!empty($kategori_value)): 
                                    $badge_color = getBadgeColor($kategori_value);
                                ?>
                                    <span class="badge <?php echo $badge_color; ?> me-2"><?php echo htmlspecialchars($kategori_value); ?></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($nama_mulok); ?>
                            </td>
                            <td><?php echo htmlspecialchars($nama_kelas_materi ?: '-'); ?></td>
                            <td>
                                <?php if ($nama_guru): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($nama_guru); ?></span>
                                <?php else: ?>
                                    <select class="form-select form-select-sm select-guru-table" style="width: 100%; min-width: 200px;" data-materi-id="<?php echo $materi_id; ?>" data-kelas-id="<?php echo $kelas_filter; ?>">
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
                        <select class="form-select" name="materi_mulok_id" id="materiMulokId" style="width: 100%;" required>
                            <option value="">-- Pilih Materi Mulok --</option>
                            <?php 
                            if ($materi_list):
                                $materi_list->data_seek(0);
                                while ($materi = $materi_list->fetch_assoc()): 
                                    // Double check: hanya tampilkan materi yang sesuai dengan kelas yang dipilih
                                    if (!empty($kelas_filter) && $has_kelas_id) {
                                        $materi_kelas_id = $materi['kelas_id'] ?? null;
                                        $kelas_id_for_check = intval($kelas_filter);
                                        // Skip jika materi memiliki kelas_id yang berbeda dengan kelas yang dipilih
                                        if ($materi_kelas_id !== null && $materi_kelas_id != $kelas_id_for_check) {
                                            continue;
                                        }
                                    }
                                    
                                    $kategori_value = $materi[$kolom_kategori] ?? '';
                                    $display_text = $kategori_value ? htmlspecialchars($kategori_value) . ' - ' . htmlspecialchars($materi['nama_mulok']) : htmlspecialchars($materi['nama_mulok']);
                            ?>
                                <option value="<?php echo $materi['id']; ?>">
                                    <?php echo $display_text; ?>
                                </option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                        <?php if (empty($kelas_filter)): ?>
                            <small class="text-muted d-block mt-1">Silakan pilih kelas terlebih dahulu untuk melihat materi yang tersedia.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Guru <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" id="guruId" style="width: 100%;" required>
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
                        <select class="form-select" name="kelas_id" id="kelasIdModal" style="width: 100%;" required>
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

<style>
    /* Pastikan dropdown Select2 muncul di bawah dan z-index tinggi */
    .select2-container--bootstrap-5 .select2-dropdown {
        z-index: 9999 !important;
    }
    .select2-dropdown-below {
        margin-top: 0 !important;
    }
    /* Pastikan Select2 container tidak overflow */
    .select2-container {
        z-index: 9999 !important;
    }
</style>

<script>
    function filterKelas() {
        var kelasId = $('#filterKelas').val();
        if (kelasId) {
            window.location.href = 'mengampu.php?kelas=' + kelasId;
        } else {
            window.location.href = 'mengampu.php';
        }
    }
    
    // Inisialisasi Select2 untuk dropdown di tabel (dinamis)
    function initSelect2Table() {
        // Destroy dulu semua Select2 yang sudah ada untuk menghindari duplikasi
        $('.select-guru-table').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                try {
                    $(this).select2('destroy');
                } catch(e) {
                    // Ignore error jika destroy gagal
                }
            }
        });
        
        // Inisialisasi Select2 untuk semua dropdown guru di tabel
        $('.select-guru-table').each(function() {
            var $select = $(this);
            
            // Skip jika sudah diinisialisasi
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }
            
            $select.select2({
                theme: 'bootstrap-5',
                placeholder: '-- Pilih Guru --',
                allowClear: true,
                dropdownParent: $('body'), // Gunakan body sebagai parent untuk memastikan dropdown muncul dengan benar
                width: '100%',
                dropdownCssClass: 'select2-dropdown-below', // CSS class untuk memastikan dropdown ke bawah
                language: {
                    noResults: function() {
                        return "Tidak ada data ditemukan";
                    },
                    searching: function() {
                        return "Mencari...";
                    }
                }
            }).on('change', function() {
                var materiId = $(this).data('materi-id');
                var kelasId = $(this).data('kelas-id');
                var guruId = $(this).val();
                if (guruId) {
                    setGuru(materiId, guruId, kelasId);
                }
            });
        });
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
        // Re-inisialisasi Select2 setelah DataTables selesai render
        <?php if (!empty($kelas_filter) && count($mengampu_data) > 0): ?>
        // Pastikan tabel ada sebelum inisialisasi DataTables
        if ($('#tableMengampu').length > 0) {
            var table = $('#tableMengampu').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                drawCallback: function() {
                    // Re-inisialisasi Select2 setelah tabel di-render ulang
                    setTimeout(function() {
                        initSelect2Table();
                    }, 300);
                }
            });
            
            // Inisialisasi Select2 setelah DataTables selesai
            setTimeout(function() {
                initSelect2Table();
            }, 500);
        }
        <?php else: ?>
        // Jika tidak ada DataTables, langsung inisialisasi Select2
        setTimeout(function() {
            initSelect2Table();
        }, 100);
        <?php endif; ?>
        
        // Fungsi untuk inisialisasi Select2 di modal
        function initSelect2Modal() {
            // Materi Mulok
            if ($('#materiMulokId').length && !$('#materiMulokId').hasClass('select2-hidden-accessible')) {
                $('#materiMulokId').select2({
                    theme: 'bootstrap-5',
                    placeholder: '-- Pilih Materi Mulok --',
                    allowClear: true,
                    dropdownParent: $('#modalMengampu'),
                    language: {
                        noResults: function() {
                            return "Tidak ada data ditemukan";
                        },
                        searching: function() {
                            return "Mencari...";
                        }
                    }
                });
            }
            
            // Guru
            if ($('#guruId').length && !$('#guruId').hasClass('select2-hidden-accessible')) {
                $('#guruId').select2({
                    theme: 'bootstrap-5',
                    placeholder: '-- Pilih Guru --',
                    allowClear: true,
                    dropdownParent: $('#modalMengampu'),
                    language: {
                        noResults: function() {
                            return "Tidak ada data ditemukan";
                        },
                        searching: function() {
                            return "Mencari...";
                        }
                    }
                });
            }
            
            // Kelas
            if ($('#kelasIdModal').length && !$('#kelasIdModal').hasClass('select2-hidden-accessible')) {
                $('#kelasIdModal').select2({
                    theme: 'bootstrap-5',
                    placeholder: '-- Pilih Kelas --',
                    allowClear: true,
                    dropdownParent: $('#modalMengampu'),
                    language: {
                        noResults: function() {
                            return "Tidak ada data ditemukan";
                        },
                        searching: function() {
                            return "Mencari...";
                        }
                    }
                });
            }
        }
        
        // Inisialisasi Select2 saat modal dibuka
        $('#modalMengampu').on('shown.bs.modal', function() {
            initSelect2Modal();
        });
        
        // Destroy Select2 saat modal ditutup
        $('#modalMengampu').on('hidden.bs.modal', function() {
            if ($('#materiMulokId').hasClass('select2-hidden-accessible')) {
                $('#materiMulokId').select2('destroy');
            }
            if ($('#guruId').hasClass('select2-hidden-accessible')) {
                $('#guruId').select2('destroy');
            }
            if ($('#kelasIdModal').hasClass('select2-hidden-accessible')) {
                $('#kelasIdModal').select2('destroy');
            }
        });
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

