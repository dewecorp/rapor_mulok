<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$materi_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle tambah nilai
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah_nilai') {
    $materi_id_post = intval($_POST['materi_id'] ?? 0);
    $kelas_id_post = intval($_POST['kelas_id'] ?? 0);
    $semester_post = trim($_POST['semester'] ?? '1');
    $tahun_ajaran_post = trim($_POST['tahun_ajaran'] ?? '');
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    $nilai_array = $_POST['nilai'] ?? [];
    
    if ($materi_id_post > 0 && $kelas_id_post > 0 && !empty($tahun_ajaran_post) && !empty($siswa_ids) && !empty($nilai_array)) {
        $conn->begin_transaction();
        try {
            $saved_count = 0;
            foreach ($siswa_ids as $index => $siswa_id) {
                $siswa_id = intval($siswa_id);
                $nilai_value = trim($nilai_array[$index] ?? '');
                
                if ($siswa_id > 0 && $nilai_value !== '') {
                    $nilai_float = floatval($nilai_value);
                    
                    // Cek apakah nilai sudah ada
                    $stmt_check = $conn->prepare("SELECT id FROM nilai_siswa 
                                                 WHERE siswa_id = ? 
                                                 AND materi_mulok_id = ? 
                                                 AND kelas_id = ? 
                                                 AND semester = ? 
                                                 AND tahun_ajaran = ?");
                    $stmt_check->bind_param("iiiss", $siswa_id, $materi_id_post, $kelas_id_post, $semester_post, $tahun_ajaran_post);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $existing = $result_check->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($existing) {
                        // Update nilai yang sudah ada
                        $stmt_update = $conn->prepare("UPDATE nilai_siswa 
                                                      SET nilai_pengetahuan = ?, guru_id = ? 
                                                      WHERE id = ?");
                        $stmt_update->bind_param("dii", $nilai_float, $user_id, $existing['id']);
                        $stmt_update->execute();
                        $stmt_update->close();
                    } else {
                        // Insert nilai baru
                        $stmt_insert = $conn->prepare("INSERT INTO nilai_siswa 
                                                       (siswa_id, materi_mulok_id, kelas_id, guru_id, semester, tahun_ajaran, nilai_pengetahuan) 
                                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt_insert->bind_param("iiiissd", $siswa_id, $materi_id_post, $kelas_id_post, $user_id, $semester_post, $tahun_ajaran_post, $nilai_float);
                        $stmt_insert->execute();
                        $stmt_insert->close();
                    }
                    $saved_count++;
                }
            }
            
            $conn->commit();
            $success_message = "Berhasil menyimpan $saved_count nilai!";
            
            // Return JSON untuk AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $success_message
            ]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = 'Gagal menyimpan nilai: ' . $e->getMessage();
            
            // Return JSON untuk AJAX request
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error_message
            ]);
            exit;
        }
    } else {
        $error_message = 'Data tidak lengkap!';
        
        // Return JSON untuk AJAX request
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
        exit;
    }
}

// Ambil semester aktif dan tahun ajaran aktif
$semester_aktif = '1';
$tahun_ajaran = '';
try {
    $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $semester_aktif = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    $semester_aktif = '1';
    $tahun_ajaran = '';
}

// Ambil kelas yang diampu oleh wali kelas ini
$kelas_id = 0;
try {
    $stmt_kelas = $conn->prepare("SELECT id FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    $stmt_kelas->close();
} catch (Exception $e) {
    $kelas_id = 0;
}

// Cek apakah kolom kategori_mulok ada
$has_kategori_mulok = false;
try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kategori_mulok') {
                $has_kategori_mulok = true;
                break;
            }
        }
    }
} catch (Exception $e) {
    $has_kategori_mulok = false;
}

// Jika materi_id ada, tampilkan tabel nilai siswa
if ($materi_id > 0 && $kelas_id > 0) {
    // Ambil data materi
    $materi_data = null;
    try {
        $kolom_kategori = $has_kategori_mulok ? 'kategori_mulok' : 'kode_mulok';
        $stmt_materi = $conn->prepare("SELECT m.id, m.nama_mulok, m.$kolom_kategori as kategori
                      FROM materi_mulok m
                      INNER JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id
                      WHERE m.id = ? AND mm.guru_id = ? AND mm.kelas_id = ? AND m.semester = ?");
        $stmt_materi->bind_param("iiis", $materi_id, $user_id, $kelas_id, $semester_aktif);
        $stmt_materi->execute();
        $result_materi = $stmt_materi->get_result();
        $materi_data = $result_materi ? $result_materi->fetch_assoc() : null;
        $stmt_materi->close();
    } catch (Exception $e) {
        $materi_data = null;
    }
    
    // Ambil data siswa di kelas
    $siswa_data = [];
    $nilai_data = [];
    if ($materi_data && $kelas_id > 0) {
        try {
            // Ambil siswa di kelas
            $stmt_siswa = $conn->prepare("SELECT s.* FROM siswa s WHERE s.kelas_id = ? ORDER BY s.nama");
            $stmt_siswa->bind_param("i", $kelas_id);
            $stmt_siswa->execute();
            $result_siswa = $stmt_siswa->get_result();
            
            if ($result_siswa) {
                while ($siswa = $result_siswa->fetch_assoc()) {
                    $siswa_data[] = $siswa;
                }
            }
            $stmt_siswa->close();
            
            // Ambil nilai siswa untuk materi ini
            if (!empty($siswa_data) && !empty($tahun_ajaran)) {
                $siswa_ids = array_column($siswa_data, 'id');
                if (!empty($siswa_ids)) {
                    $placeholders = str_repeat('?,', count($siswa_ids) - 1) . '?';
                    $query_nilai = "SELECT * FROM nilai_siswa 
                                   WHERE siswa_id IN ($placeholders) 
                                   AND materi_mulok_id = ? 
                                   AND semester = ? 
                                   AND tahun_ajaran = ?";
                    $stmt_nilai = $conn->prepare($query_nilai);
                    $params = array_merge($siswa_ids, [$materi_id, $semester_aktif, $tahun_ajaran]);
                    $types = str_repeat('i', count($siswa_ids)) . 'iss';
                    $stmt_nilai->bind_param($types, ...$params);
                    $stmt_nilai->execute();
                    $result_nilai = $stmt_nilai->get_result();
                    
                    if ($result_nilai) {
                        while ($nilai = $result_nilai->fetch_assoc()) {
                            $nilai_data[$nilai['siswa_id']] = $nilai;
                        }
                    }
                    $stmt_nilai->close();
                }
            }
        } catch (Exception $e) {
            $siswa_data = [];
            $nilai_data = [];
        }
    }
} else {
    // Jika tidak ada materi_id, tampilkan daftar materi
    $materi_data = null;
    $siswa_data = [];
    $nilai_data = [];
    
    // Ambil materi yang diampu oleh wali kelas ini di kelas tersebut dengan filter semester aktif
    $materi_list = [];
    if ($kelas_id > 0) {
        try {
            $stmt = $conn->prepare("SELECT DISTINCT m.id, m.nama_mulok, m.kode_mulok, m.jumlah_jam
                      FROM mengampu_materi mm
                      INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                      WHERE mm.guru_id = ? AND mm.kelas_id = ? AND m.semester = ?
                      ORDER BY m.nama_mulok");
            $stmt->bind_param("iis", $user_id, $kelas_id, $semester_aktif);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $materi_id_check = $row['id'];
                    
                    // Cek apakah nilai sudah dikirim untuk materi ini
                    $status_nilai = 'belum';
                    if (!empty($tahun_ajaran)) {
                        $stmt_cek = $conn->prepare("SELECT COUNT(*) as total 
                                                   FROM nilai_siswa 
                                                   WHERE materi_mulok_id = ? 
                                                   AND kelas_id = ? 
                                                   AND semester = ? 
                                                   AND tahun_ajaran = ?");
                        $stmt_cek->bind_param("iiss", $materi_id_check, $kelas_id, $semester_aktif, $tahun_ajaran);
                        $stmt_cek->execute();
                        $result_cek = $stmt_cek->get_result();
                        $cek_data = $result_cek->fetch_assoc();
                        $ada_nilai = ($cek_data['total'] ?? 0) > 0;
                        
                        if ($ada_nilai) {
                            $status_nilai = 'terkirim';
                        }
                        $stmt_cek->close();
                    }
                    
                    $materi_list[] = [
                        'id' => $row['id'],
                        'kode_mulok' => $row['kode_mulok'],
                        'nama_mulok' => $row['nama_mulok'],
                        'jumlah_jam' => $row['jumlah_jam'],
                        'status_nilai' => $status_nilai
                    ];
                }
            }
            $stmt->close();
        } catch (Exception $e) {
            $materi_list = [];
        }
    }
}
?>
<?php include '../includes/header.php'; ?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($materi_id > 0 && $materi_data): ?>
    <!-- Tampilkan tabel nilai siswa untuk materi tertentu -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-book"></i> 
                <?php echo htmlspecialchars($materi_data['kategori'] ?? '-'); ?> - <?php echo htmlspecialchars($materi_data['nama_mulok']); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="mb-3 d-flex gap-2">
                <button type="button" class="btn btn-primary" onclick="tambahNilai()">
                    <i class="fas fa-plus"></i> Tambah Nilai
                </button>
                <button type="button" class="btn btn-info" onclick="imporNilai()">
                    <i class="fas fa-file-import"></i> Impor Nilai
                </button>
                <button type="button" class="btn btn-success" onclick="kirimNilai()">
                    <i class="fas fa-paper-plane"></i> Kirim Nilai
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableNilai">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th width="60">L/P</th>
                            <th width="100">Nilai</th>
                            <th width="100">Predikat</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_data)): ?>
                            <?php 
                            $no = 1;
                            foreach ($siswa_data as $siswa): 
                                $nilai = $nilai_data[$siswa['id']] ?? null;
                                $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
                                $predikat = $nilai ? ($nilai['predikat'] ?? '') : '';
                                $deskripsi = $nilai ? ($nilai['deskripsi'] ?? '') : '';
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                    <td><?php echo (($siswa['jenis_kelamin'] ?? '') == 'L') ? 'L' : 'P'; ?></td>
                                    <td><?php echo htmlspecialchars($nilai_value); ?></td>
                                    <td><?php echo htmlspecialchars($predikat); ?></td>
                                    <td><?php echo htmlspecialchars($deskripsi); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Tidak ada siswa di kelas ini</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Tampilkan daftar materi jika tidak ada materi_id -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-book"></i> Materi Mulok yang Diampu</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($materi_list)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableMateri">
                        <thead>
                            <tr>
                                <th width="50">No</th>
                                <th>Kode Mulok</th>
                                <th>Materi Mulok</th>
                                <th>Jumlah Jam</th>
                                <th>Status Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($materi_list as $materi): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($materi['kode_mulok']); ?></td>
                                    <td>
                                        <a href="materi.php?id=<?php echo $materi['id']; ?>">
                                            <?php echo htmlspecialchars($materi['nama_mulok']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($materi['jumlah_jam']); ?> Jam</td>
                                    <td>
                                        <?php if ($materi['status_nilai'] == 'terkirim'): ?>
                                            <span class="badge bg-success">Terkirim</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Belum</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Belum ada materi yang diampu.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Tambah Nilai -->
<?php if ($materi_id > 0 && !empty($siswa_data)): ?>
<div class="modal fade" id="modalTambahNilai" tabindex="-1" aria-labelledby="modalTambahNilaiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahNilaiLabel">
                    <i class="fas fa-plus"></i> Tambah Nilai - <?php echo htmlspecialchars($materi_data['kategori'] ?? '-'); ?> - <?php echo htmlspecialchars($materi_data['nama_mulok']); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTambahNilai">
                <input type="hidden" name="action" value="tambah_nilai">
                <input type="hidden" name="materi_id" value="<?php echo $materi_id; ?>">
                <input type="hidden" name="kelas_id" value="<?php echo $kelas_id; ?>">
                <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester_aktif); ?>">
                <input type="hidden" name="tahun_ajaran" value="<?php echo htmlspecialchars($tahun_ajaran); ?>">
                <div class="modal-body">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="50">No</th>
                                    <th>NISN</th>
                                    <th>Nama Siswa</th>
                                    <th width="150">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($siswa_data as $siswa): 
                                    $nilai = $nilai_data[$siswa['id']] ?? null;
                                    $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nama'] ?? '-'); ?></td>
                                        <td>
                                            <input type="hidden" name="siswa_ids[]" value="<?php echo $siswa['id']; ?>">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   name="nilai[]" 
                                                   value="<?php echo htmlspecialchars($nilai_value); ?>"
                                                   min="0" 
                                                   max="100" 
                                                   step="0.01"
                                                   placeholder="0-100">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        <?php if ($materi_id > 0): ?>
            $('#tableNilai').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                order: [[2, 'asc']],
                pageLength: 25
            });
        <?php else: ?>
            $('#tableMateri').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                }
            });
        <?php endif; ?>
    });
    
    function tambahNilai() {
        var modal = new bootstrap.Modal(document.getElementById('modalTambahNilai'));
        modal.show();
    }
    
    function imporNilai() {
        // TODO: Implementasi impor nilai
        Swal.fire({
            icon: 'info',
            title: 'Impor Nilai',
            text: 'Fitur ini akan segera tersedia',
            confirmButtonColor: '#2d5016'
        });
    }
    
    function kirimNilai() {
        // TODO: Implementasi kirim nilai
        Swal.fire({
            icon: 'info',
            title: 'Kirim Nilai',
            text: 'Fitur ini akan segera tersedia',
            confirmButtonColor: '#2d5016'
        });
    }
    
    // Handle submit form tambah nilai
    document.getElementById('formTambahNilai').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var formData = new FormData(form);
        
        // Validasi: pastikan minimal ada satu nilai yang diisi
        var nilaiInputs = form.querySelectorAll('input[name="nilai[]"]');
        var adaNilai = false;
        nilaiInputs.forEach(function(input) {
            if (input.value && input.value.trim() !== '') {
                adaNilai = true;
            }
        });
        
        if (!adaNilai) {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Minimal satu nilai harus diisi',
                confirmButtonColor: '#2d5016'
            });
            return;
        }
        
        // Tampilkan loading
        Swal.fire({
            title: 'Menyimpan Nilai...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('materi.php?id=<?php echo $materi_id; ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: data.message || 'Nilai berhasil disimpan',
                    confirmButtonColor: '#2d5016'
                }).then(() => {
                    // Tutup modal
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalTambahNilai'));
                    modal.hide();
                    
                    // Reload halaman untuk update data
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: data.message || 'Gagal menyimpan nilai',
                    confirmButtonColor: '#2d5016'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat menyimpan nilai',
                confirmButtonColor: '#2d5016'
            });
        });
    });
</script>
