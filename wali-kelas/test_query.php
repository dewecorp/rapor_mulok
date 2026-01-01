<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil kelas wali kelas
$kelas_id = 0;
$semester = '1';
$tahun_ajaran = '';

try {
    $stmt_kelas = $conn->prepare("SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ? LIMIT 1");
    $stmt_kelas->bind_param("i", $user_id);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    $kelas_data = $result_kelas ? $result_kelas->fetch_assoc() : null;
    $kelas_id = $kelas_data['id'] ?? 0;
    
    $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $semester = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

echo "<h2>Test Query Status Nilai - Kelas ID: $kelas_id, Semester: $semester</h2>";

// Cek struktur tabel
$has_kelas_id = false;
$has_semester = false;
try {
    $columns = $conn->query("SHOW COLUMNS FROM materi_mulok");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            if ($col['Field'] == 'kelas_id') $has_kelas_id = true;
            if ($col['Field'] == 'semester') $has_semester = true;
        }
    }
} catch (Exception $e) {
    // Ignore
}

echo "<p>Has kelas_id: " . ($has_kelas_id ? 'Yes' : 'No') . "</p>";
echo "<p>Has semester: " . ($has_semester ? 'Yes' : 'No') . "</p>";

// Query yang sama dengan status-nilai.php
$materi_list = [];

if ($kelas_id && !empty($semester)) {
    if ($has_kelas_id && $has_semester) {
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         WHERE m.kelas_id = ? AND m.semester = ?
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("iis", $kelas_id, $kelas_id, $semester);
    } else {
        $query_materi = "SELECT m.id as materi_id, m.nama_mulok, mm.guru_id, p.nama as nama_guru
                         FROM materi_mulok m
                         LEFT JOIN mengampu_materi mm ON m.id = mm.materi_mulok_id AND mm.kelas_id = ?
                         LEFT JOIN pengguna p ON mm.guru_id = p.id
                         ORDER BY m.nama_mulok";
        $stmt_materi = $conn->prepare($query_materi);
        $stmt_materi->bind_param("i", $kelas_id);
    }
    
    $stmt_materi->execute();
    $materi_result = $stmt_materi->get_result();
    
    echo "<h3>Hasil Query (num_rows: " . $materi_result->num_rows . "):</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Materi ID</th><th>Nama Mulok</th><th>Guru ID</th><th>Nama Guru</th></tr>";
    
    $count = 0;
    if ($materi_result) {
        while ($materi = $materi_result->fetch_assoc()) {
            $count++;
            echo "<tr>";
            echo "<td>" . $materi['materi_id'] . "</td>";
            echo "<td>" . htmlspecialchars($materi['nama_mulok']) . "</td>";
            echo "<td>" . ($materi['guru_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($materi['nama_guru'] ?? '-') . "</td>";
            echo "</tr>";
            
            $materi_list[] = [
                'materi_id' => $materi['materi_id'],
                'nama_mulok' => $materi['nama_mulok'],
                'guru_id' => $materi['guru_id'],
                'nama_guru' => $materi['nama_guru'] ?? '-'
            ];
        }
        $stmt_materi->close();
    }
    echo "</table>";
    
    echo "<p><strong>Total materi dalam array \$materi_list: " . count($materi_list) . "</strong></p>";
    echo "<p><strong>Loop count: $count</strong></p>";
    
    echo "<h3>Array \$materi_list:</h3>";
    echo "<pre>";
    print_r($materi_list);
    echo "</pre>";
}

$conn->close();
?>

