<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('guru');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';
$label_kategori = $use_kategori ? 'Kategori Mulok' : 'Kode Mulok';

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

// Ambil materi yang diampu oleh guru ini
$result = null;
try {
    if ($has_kelas_id) {
        $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas, k_materi.nama_kelas as nama_kelas_materi
                  FROM mengampu_materi mm
                  INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                  INNER JOIN kelas k ON mm.kelas_id = k.id
                  LEFT JOIN kelas k_materi ON m.kelas_id = k_materi.id
                  WHERE mm.guru_id = ?
                  ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC");
    } else {
        $stmt = $conn->prepare("SELECT DISTINCT m.*, k.nama_kelas, NULL as nama_kelas_materi
                  FROM mengampu_materi mm
                  INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                  INNER JOIN kelas k ON mm.kelas_id = k.id
                  WHERE mm.guru_id = ?
                  ORDER BY k.nama_kelas, LOWER(m.$kolom_kategori) ASC, LOWER(m.nama_mulok) ASC");
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $result = null;
    }
} catch (Exception $e) {
    $result = null;
}
?>
<?php include '../includes/header.php'; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-book"></i> Materi yang Diampu</h5>
    </div>
    <div class="card-body">
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tableMateri">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th><?php echo $label_kategori; ?></th>
                            <th>Nama Mulok</th>
                            <th>Kelas Materi</th>
                            <th>Kelas Mengampu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <?php 
                                    $kategori_value = $row[$kolom_kategori] ?? '';
                                    if (!empty($kategori_value)): 
                                        $badge_color = getBadgeColor($kategori_value);
                                    ?>
                                        <span class="badge <?php echo $badge_color; ?>"><?php echo htmlspecialchars($kategori_value); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['nama_mulok']); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_kelas_materi'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['nama_kelas']); ?></td>
                            </tr>
                        <?php endwhile; ?>
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

<?php include '../includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#tableMateri').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            }
        });
    });
</script>

