<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('wali_kelas');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Ambil kelas yang diampu oleh wali kelas ini
$kelas_data = null;
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
    
    // Ambil profil untuk semester dan tahun ajaran
    $query_profil = "SELECT semester_aktif, tahun_ajaran_aktif FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
    $semester = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran = $profil['tahun_ajaran_aktif'] ?? '';
} catch (Exception $e) {
    $kelas_data = null;
    $kelas_id = 0;
    $semester = '1';
    $tahun_ajaran = '';
}

echo "<h2>Debug Status Nilai - Kelas ID: $kelas_id, Semester: $semester</h2>";

// Query 1: Materi dari materi_mulok dengan kelas_id dan semester
echo "<h3>1. Materi dari materi_mulok (WHERE kelas_id = $kelas_id AND semester = '$semester'):</h3>";
$query1 = "SELECT id, nama_mulok, kelas_id, semester FROM materi_mulok WHERE kelas_id = ? AND semester = ? ORDER BY nama_mulok";
$stmt1 = $conn->prepare($query1);
$stmt1->bind_param("is", $kelas_id, $semester);
$stmt1->execute();
$result1 = $stmt1->get_result();
echo "Jumlah: " . $result1->num_rows . "<br>";
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Nama Mulok</th><th>Kelas ID</th><th>Semester</th></tr>";
while ($row = $result1->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['nama_mulok']}</td><td>{$row['kelas_id']}</td><td>{$row['semester']}</td></tr>";
}
echo "</table>";
$stmt1->close();

// Query 2: Materi dari mengampu_materi untuk kelas_id dan semester
echo "<h3>2. Materi dari mengampu_materi (WHERE kelas_id = $kelas_id) dengan semester = '$semester':</h3>";
$query2 = "SELECT DISTINCT m.id, m.nama_mulok, m.kelas_id, m.semester, mm.guru_id, p.nama as nama_guru
           FROM mengampu_materi mm
           INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
           LEFT JOIN pengguna p ON mm.guru_id = p.id
           WHERE mm.kelas_id = ? AND m.semester = ?
           ORDER BY m.nama_mulok";
$stmt2 = $conn->prepare($query2);
$stmt2->bind_param("is", $kelas_id, $semester);
$stmt2->execute();
$result2 = $stmt2->get_result();
echo "Jumlah: " . $result2->num_rows . "<br>";
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Nama Mulok</th><th>Kelas ID</th><th>Semester</th><th>Guru ID</th><th>Nama Guru</th></tr>";
while ($row = $result2->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>{$row['nama_mulok']}</td><td>" . ($row['kelas_id'] ?? 'NULL') . "</td><td>{$row['semester']}</td><td>" . ($row['guru_id'] ?? 'NULL') . "</td><td>" . ($row['nama_guru'] ?? '-') . "</td></tr>";
}
echo "</table>";
$stmt2->close();

// Query 3: Semua mengampu_materi untuk kelas_id (tanpa filter semester di mengampu_materi)
echo "<h3>3. Semua mengampu_materi untuk kelas_id = $kelas_id (semua semester):</h3>";
$query3 = "SELECT mm.id as mengampu_id, mm.materi_mulok_id, mm.kelas_id, mm.guru_id, m.nama_mulok, m.semester, p.nama as nama_guru
           FROM mengampu_materi mm
           INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
           LEFT JOIN pengguna p ON mm.guru_id = p.id
           WHERE mm.kelas_id = ?
           ORDER BY m.semester, m.nama_mulok";
$stmt3 = $conn->prepare($query3);
$stmt3->bind_param("i", $kelas_id);
$stmt3->execute();
$result3 = $stmt3->get_result();
echo "Jumlah: " . $result3->num_rows . "<br>";
echo "<table border='1' cellpadding='5'><tr><th>Mengampu ID</th><th>Materi ID</th><th>Kelas ID</th><th>Guru ID</th><th>Nama Mulok</th><th>Semester</th><th>Nama Guru</th></tr>";
while ($row = $result3->fetch_assoc()) {
    echo "<tr><td>{$row['mengampu_id']}</td><td>{$row['materi_mulok_id']}</td><td>{$row['kelas_id']}</td><td>" . ($row['guru_id'] ?? 'NULL') . "</td><td>{$row['nama_mulok']}</td><td>{$row['semester']}</td><td>" . ($row['nama_guru'] ?? '-') . "</td></tr>";
}
echo "</table>";
$stmt3->close();

$conn->close();
?>

