<?php
/**
 * Script untuk mengecek dan memperbaiki role user
 * Akses: http://localhost/rapor-mulok/check_user_roles.php
 */

require_once 'config/config.php';
require_once 'config/database.php';
requireLogin();

// Hanya proktor yang bisa akses
if (!hasRole('proktor')) {
    die('Akses ditolak. Hanya proktor yang bisa mengakses script ini.');
}

$conn = getConnection();
$results = [];

// Ambil semua user
try {
    $query = "SELECT p.id, p.username, p.nama, p.role, p.nuptk, 
                     k.id as kelas_id, k.nama_kelas, k.wali_kelas_id,
                     COUNT(DISTINCT mm.id) as jumlah_mengampu
              FROM pengguna p
              LEFT JOIN kelas k ON k.wali_kelas_id = p.id
              LEFT JOIN mengampu_materi mm ON mm.guru_id = p.id
              WHERE p.role IN ('guru', 'wali_kelas')
              GROUP BY p.id, p.username, p.nama, p.role, p.nuptk, k.id, k.nama_kelas, k.wali_kelas_id
              ORDER BY p.role, p.nama";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
            $role_db = $row['role'];
            $is_wali_kelas = !empty($row['kelas_id']);
            $jumlah_mengampu = $row['jumlah_mengampu'] ?? 0;
            
            $status = 'OK';
            $saran = '';
            
            // Validasi role
            if ($role_db == 'wali_kelas' && !$is_wali_kelas) {
                $status = 'WARNING';
                $saran = 'User memiliki role wali_kelas tapi tidak tercatat sebagai wali kelas di tabel kelas';
            } elseif ($role_db == 'guru' && $is_wali_kelas) {
                $status = 'WARNING';
                $saran = 'User memiliki role guru tapi tercatat sebagai wali kelas. Perlu diubah role menjadi wali_kelas atau hapus dari wali_kelas_id';
            } elseif ($role_db == 'wali_kelas' && $jumlah_mengampu == 0) {
                $status = 'INFO';
                $saran = 'Wali kelas tidak mengampu materi apapun';
            }
            
            $results[] = [
                'id' => $user_id,
                'username' => $row['username'],
                'nama' => $row['nama'],
                'role_db' => $role_db,
                'nuptk' => $row['nuptk'] ?? '-',
                'kelas' => $row['nama_kelas'] ?? '-',
                'is_wali_kelas' => $is_wali_kelas,
                'jumlah_mengampu' => $jumlah_mengampu,
                'status' => $status,
                'saran' => $saran
            ];
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check User Roles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-users-cog"></i> Pengecekan Role User</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Nama</th>
                                    <th>NUPTK</th>
                                    <th>Role (DB)</th>
                                    <th>Wali Kelas?</th>
                                    <th>Kelas</th>
                                    <th>Jumlah Mengampu</th>
                                    <th>Status</th>
                                    <th>Saran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><?php echo $r['id']; ?></td>
                                        <td><?php echo htmlspecialchars($r['username']); ?></td>
                                        <td><?php echo htmlspecialchars($r['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($r['nuptk']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $r['role_db'] == 'wali_kelas' ? 'success' : 'info'; ?>">
                                                <?php echo htmlspecialchars($r['role_db']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $r['is_wali_kelas'] ? '<span class="badge bg-success">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($r['kelas']); ?></td>
                                        <td><?php echo $r['jumlah_mengampu']; ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower($r['status']); ?>">
                                                <i class="fas fa-<?php echo $r['status'] == 'OK' ? 'check-circle' : ($r['status'] == 'WARNING' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                                                <?php echo $r['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['saran']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Keterangan:</h6>
                        <ul>
                            <li><strong>OK:</strong> Role sesuai dengan data di database</li>
                            <li><strong>WARNING:</strong> Ada ketidaksesuaian antara role dan data terkait</li>
                            <li><strong>INFO:</strong> Informasi tambahan</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

