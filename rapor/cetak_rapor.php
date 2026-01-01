<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

// Ambil parameter
$siswa_id = isset($_GET['siswa']) ? intval($_GET['siswa']) : 0;
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;
$semua = isset($_GET['semua']) ? true : false;

// Fungsi untuk menghitung predikat
function hitungPredikat($nilai) {
    $nilai_float = floatval($nilai);
    if ($nilai_float <= 60) return 'D';
    elseif ($nilai_float <= 69) return 'C';
    elseif ($nilai_float <= 89) return 'B';
    elseif ($nilai_float <= 100) return 'A';
    return '-';
}

// Fungsi untuk menghitung deskripsi
function hitungDeskripsi($predikat, $nama_materi, $kategori = '') {
    if (empty($predikat) || $predikat == '-') return '-';
    
    $materi_full = $nama_materi;
    if (!empty($kategori)) {
        $materi_full = $kategori . ' ' . $nama_materi;
    }
    
    switch ($predikat) {
        case 'A':
            return 'Sangat baik dalam ' . $materi_full;
        case 'B':
            return 'Baik dalam ' . $materi_full;
        case 'C':
            return 'Cukup dalam ' . $materi_full;
        case 'D':
            return 'Kurang dalam ' . $materi_full;
        default:
            return '-';
    }
}

// Ambil data profil madrasah
$profil_madrasah = null;
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    if ($result_profil && $result_profil->num_rows > 0) {
        $profil_madrasah = $result_profil->fetch_assoc();
    }
} catch (Exception $e) {
    // Handle error
}

$semester_aktif = $profil_madrasah['semester_aktif'] ?? '1';
$tahun_ajaran = $profil_madrasah['tahun_ajaran_aktif'] ?? '';

// Ambil data siswa
$siswa_list = [];
if ($siswa_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, k.nama_kelas, k.wali_kelas_id FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $siswa_list[] = $row;
    }
    $stmt->close();
} elseif ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, k.nama_kelas, k.wali_kelas_id FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.kelas_id = ? ORDER BY s.nama");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $nama_siswa = trim($row['nama'] ?? '');
        if (!empty($nama_siswa)) {
            $nama_lower = strtolower($nama_siswa);
            if ($nama_lower !== 'administrator' && $nama_lower !== 'admin' && $nama_lower !== 'proktor') {
                $siswa_list[] = $row;
            }
        }
    }
    $stmt->close();
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
    // Ignore
}

// Proses setiap siswa
$rapor_data = [];
foreach ($siswa_list as $siswa) {
    $siswa_kelas_id = $siswa['kelas_id'] ?? 0;
    $siswa_semester = $semester_aktif;
    
    // Ambil semua materi untuk kelas dan semester ini
    $materi_query = "SELECT m.* FROM materi_mulok m WHERE m.kelas_id = ? AND m.semester = ? ORDER BY ";
    if ($has_kategori_mulok) {
        $materi_query .= "m.kategori_mulok, ";
    }
    $materi_query .= "m.nama_mulok";
    
    $stmt_materi = $conn->prepare($materi_query);
    $stmt_materi->bind_param("is", $siswa_kelas_id, $siswa_semester);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();
    
    $nilai_items = [];
    $total_nilai = 0;
    $count_nilai = 0;
    $no = 1;
    $current_kategori = '';
    
    while ($materi = $result_materi->fetch_assoc()) {
        // Ambil nilai siswa untuk materi ini
        $stmt_nilai = $conn->prepare("SELECT * FROM nilai_siswa WHERE siswa_id = ? AND materi_mulok_id = ? AND semester = ? AND tahun_ajaran = ?");
        $stmt_nilai->bind_param("iiss", $siswa['id'], $materi['id'], $siswa_semester, $tahun_ajaran);
        $stmt_nilai->execute();
        $result_nilai = $stmt_nilai->get_result();
        $nilai_data = $result_nilai->fetch_assoc();
        $stmt_nilai->close();
        
        $kategori = '';
        if ($has_kategori_mulok && !empty($materi['kategori_mulok'])) {
            $kategori = $materi['kategori_mulok'];
        }
        
        $nilai_value = $nilai_data ? ($nilai_data['nilai_pengetahuan'] ?? $nilai_data['harian'] ?? '') : '';
        $predikat = $nilai_data ? ($nilai_data['predikat'] ?? '') : '';
        $deskripsi = $nilai_data ? ($nilai_data['deskripsi'] ?? '') : '';
        
        if (empty($predikat) && !empty($nilai_value)) {
            $predikat = hitungPredikat($nilai_value);
        }
        
        if (!empty($predikat) && $predikat != '-' && !empty($materi['nama_mulok'])) {
            if (empty($deskripsi)) {
                $deskripsi = hitungDeskripsi($predikat, $materi['nama_mulok'], $kategori);
            }
        }
        
        // Tampilkan kategori sebagai header jika berbeda
        $show_kategori = false;
        if (!empty($kategori) && $kategori != $current_kategori) {
            $show_kategori = true;
            $current_kategori = $kategori;
        }
        
        $nilai_items[] = [
            'no' => $no++,
            'show_kategori' => $show_kategori,
            'kategori' => $kategori,
            'materi' => $materi['nama_mulok'],
            'nilai' => $nilai_value,
            'predikat' => $predikat,
            'deskripsi' => $deskripsi
        ];
        
        if (!empty($nilai_value)) {
            $total_nilai += floatval($nilai_value);
            $count_nilai++;
        }
    }
    $stmt_materi->close();
    
    // Ambil data wali kelas
    $wali_kelas = null;
    $wali_kelas_id = $siswa['wali_kelas_id'] ?? 0;
    if ($wali_kelas_id > 0) {
        $stmt_wali = $conn->prepare("SELECT nama FROM pengguna WHERE id = ?");
        $stmt_wali->bind_param("i", $wali_kelas_id);
        $stmt_wali->execute();
        $result_wali = $stmt_wali->get_result();
        $wali_kelas = $result_wali->fetch_assoc();
        $stmt_wali->close();
    }
    
    // Ambil catatan kompetensi (jika ada)
    $catatan_kompetensi = '';
    // TODO: Ambil dari database jika ada tabel catatan
    
    $rapor_data[] = [
        'siswa' => $siswa,
        'nilai_items' => $nilai_items,
        'rata_rata' => $count_nilai > 0 ? round($total_nilai / $count_nilai, 0) : 0,
        'wali_kelas' => $wali_kelas,
        'catatan_kompetensi' => $catatan_kompetensi
    ];
}

$conn->close();

// Konversi semester
function getSemesterText($semester) {
    return ($semester == '1') ? 'Gasal' : 'Genap';
}

// Konversi nama kelas
function getKelasText($nama_kelas) {
    $romawi = [
        'I' => 'I (SATU)',
        'II' => 'II (DUA)',
        'III' => 'III (TIGA)',
        'IV' => 'IV (EMPAT)',
        'V' => 'V (LIMA)',
        'VI' => 'VI (ENAM)'
    ];
    return $romawi[$nama_kelas] ?? $nama_kelas;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rapor</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 2cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        
        .container {
            max-width: 21cm;
            margin: 0 auto;
            background-color: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .identitas-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .identitas-table td {
            padding: 5px 10px;
            font-size: 12pt;
        }
        
        .identitas-table td:first-child {
            text-align: right;
            width: 25%;
            padding-right: 15px;
        }
        
        .identitas-table td:last-child {
            text-align: left;
            width: 75%;
        }
        
        .nilai-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12pt;
        }
        
        .nilai-table th,
        .nilai-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        
        .nilai-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 12pt;
        }
        
        .nilai-table td {
            font-size: 12pt;
        }
        
        .nilai-table .no-col {
            width: 5%;
            text-align: center;
        }
        
        .nilai-table .materi-col {
            width: 30%;
        }
        
        .nilai-table .nilai-col {
            width: 10%;
            text-align: center;
        }
        
        .nilai-table .predikat-col {
            width: 10%;
            text-align: center;
        }
        
        .nilai-table .deskripsi-col {
            width: 45%;
        }
        
        .kategori-header {
            font-weight: bold;
            background-color: #e0e0e0;
        }
        
        .catatan-box {
            border: 1px solid #000;
            padding: 10px;
            margin: 20px 0;
            min-height: 60px;
            font-size: 12pt;
        }
        
        .ttd-container {
            margin-top: 40px;
        }
        
        .ttd-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 60px;
        }
        
        .ttd-item {
            width: 30%;
            text-align: center;
        }
        
        .ttd-item label {
            display: block;
            margin-bottom: 50px;
            font-size: 12pt;
        }
        
        .ttd-item .nama {
            font-weight: bold;
            margin-top: 5px;
            font-size: 12pt;
        }
        
        .ttd-center {
            text-align: center;
            margin-top: 20px;
        }
        
        .ttd-center label {
            display: block;
            margin-bottom: 50px;
            font-size: 12pt;
        }
        
        .ttd-center .nama {
            font-weight: bold;
            margin-top: 5px;
            font-size: 12pt;
        }
        
        .tanggal {
            text-align: right;
            margin-bottom: 20px;
            font-size: 12pt;
        }
        
        .student-page {
            page-break-after: always;
            margin-bottom: 50px;
        }
        
        .student-page:last-child {
            page-break-after: auto;
        }
    </style>
    <script>
        // Auto print saat halaman dimuat (mode windows print)
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>
    <div class="container">
        <?php foreach ($rapor_data as $index => $rapor): 
            $siswa = $rapor['siswa'];
            $nilai_items = $rapor['nilai_items'];
            $rata_rata = $rapor['rata_rata'];
            $wali_kelas = $rapor['wali_kelas'];
        ?>
        <div class="student-page">
            <!-- Header -->
            <div class="header">
                <h1>LAPORAN PENILAIAN MULOK KHUSUS<br><?php echo htmlspecialchars($profil_madrasah['nama_madrasah'] ?? 'MI SULTAN FATTAH SUKOSONO'); ?></h1>
            </div>
            
            <!-- Identitas Siswa -->
            <table class="identitas-table">
                <tr>
                    <td>Nama Siswa</td>
                    <td>: <?php echo htmlspecialchars(strtoupper($siswa['nama'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <td>NISN</td>
                    <td>: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>Semester</td>
                    <td>: <?php echo getSemesterText($semester_aktif); ?></td>
                </tr>
                <tr>
                    <td>Kelas</td>
                    <td>: <?php echo getKelasText($siswa['nama_kelas'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>Tahun Ajaran</td>
                    <td>: <?php echo htmlspecialchars($tahun_ajaran); ?></td>
                </tr>
            </table>
            
            <!-- Tabel Nilai -->
            <table class="nilai-table">
                <thead>
                    <tr>
                        <th class="no-col">No</th>
                        <th class="materi-col">Materi</th>
                        <th class="nilai-col">Nilai</th>
                        <th class="predikat-col">Predikat</th>
                        <th class="deskripsi-col">Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($nilai_items as $item): 
                        if ($item['show_kategori'] && !empty($item['kategori'])):
                    ?>
                        <tr class="kategori-header">
                            <td colspan="5"><?php echo htmlspecialchars($item['kategori']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="no-col"><?php echo $no++; ?></td>
                        <td class="materi-col">
                            <?php if (!empty($item['kategori']) && !$item['show_kategori']): ?>
                                &nbsp;&nbsp;&nbsp;&nbsp;a. 
                            <?php elseif (!empty($item['kategori'])): ?>
                                a. 
                            <?php endif; ?>
                            <?php echo htmlspecialchars($item['materi']); ?>
                        </td>
                        <td class="nilai-col"><?php echo htmlspecialchars($item['nilai'] ?: '-'); ?></td>
                        <td class="predikat-col"><?php echo htmlspecialchars($item['predikat'] ?: '-'); ?></td>
                        <td class="deskripsi-col"><?php echo htmlspecialchars($item['deskripsi'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2" style="font-weight: bold; text-align: right;">RERATA</td>
                        <td class="nilai-col" style="font-weight: bold;"><?php echo $rata_rata; ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Catatan Kompetensi -->
            <div class="catatan-box">
                <strong>CATATAN KOMPETENSI</strong><br>
                <?php 
                $catatan = $rapor['catatan_kompetensi'];
                if (empty($catatan)) {
                    // Generate catatan otomatis berdasarkan rata-rata
                    $nama_siswa = strtoupper($siswa['nama']);
                    if ($rata_rata < 60) {
                        $catatan = "ANANDA $nama_siswa KURANG DALAM KOMPETENSI PENDIDIKAN MUATAN LOKAL KHUSUS MADRASAH. MOHON ORANG TUA/WALI MENINGKATKAN BIMBINGAN";
                    } else {
                        $catatan = "ANANDA $nama_siswa SUDAH MENGIKUTI PEMBELAJARAN PENDIDIKAN MUATAN LOKAL KHUSUS MADRASAH DENGAN BAIK";
                    }
                }
                echo htmlspecialchars($catatan);
                ?>
            </div>
            
            <!-- Tanda Tangan -->
            <div class="tanggal">
                Jepara, <?php echo date('d F Y'); ?>
            </div>
            
            <div class="ttd-row">
                <div class="ttd-item">
                    <label>Wali Murid,</label>
                    <div class="nama">(___________________)</div>
                </div>
                <div class="ttd-item">
                    <label>Wali Kelas,</label>
                    <div class="nama">(<?php echo htmlspecialchars($wali_kelas['nama'] ?? '-'); ?>)</div>
                </div>
            </div>
            
            <div class="ttd-center">
                <label>Mengetahui</label>
                <div style="margin-bottom: 10px; margin-top: 10px;">Kepala MI,</div>
                <div class="nama">(<?php echo htmlspecialchars($profil_madrasah['nama_kepala'] ?? '-'); ?>)</div>
            </div>
        </div>
        
        <?php if ($index < count($rapor_data) - 1): ?>
            <div style="page-break-after: always;"></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</body>
</html>

