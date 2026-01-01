<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (!hasRole('proktor') && !hasRole('wali_kelas')) {
    header('Location: /index.php');
    exit();
}

$conn = getConnection();

// Ambil parameter
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;

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

// Ambil data pengaturan cetak
$pengaturan_cetak = null;
try {
    $query_pengaturan = "SELECT * FROM pengaturan_cetak LIMIT 1";
    $result_pengaturan = $conn->query($query_pengaturan);
    if ($result_pengaturan && $result_pengaturan->num_rows > 0) {
        $pengaturan_cetak = $result_pengaturan->fetch_assoc();
    }
} catch (Exception $e) {
    // Handle error
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if (empty($tanggal)) {
        return '';
    }
    
    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_num = (int)date('m', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

$semester_aktif = $profil_madrasah['semester_aktif'] ?? '1';
$tahun_ajaran = $profil_madrasah['tahun_ajaran_aktif'] ?? '';

// Ambil data kelas dengan wali kelas
$kelas_data = null;
if ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT k.*, p.nama as wali_kelas_nama FROM kelas k LEFT JOIN pengguna p ON k.wali_kelas_id = p.id WHERE k.id = ?");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $kelas_data = $result->fetch_assoc();
    $stmt->close();
}

// Ambil data siswa
$siswa_list = [];
if ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT s.* FROM siswa s WHERE s.kelas_id = ? ORDER BY s.nama");
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

// Ambil semua materi untuk kelas dan semester ini
$materi_list = [];
if ($kelas_id > 0) {
    $materi_query = "SELECT m.* FROM materi_mulok m WHERE m.kelas_id = ? AND m.semester = ? ORDER BY ";
    if ($has_kategori_mulok) {
        $materi_query .= "m.kategori_mulok, ";
    }
    $materi_query .= "m.nama_mulok";
    
    $stmt_materi = $conn->prepare($materi_query);
    $stmt_materi->bind_param("is", $kelas_id, $semester_aktif);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();
    
    while ($materi = $result_materi->fetch_assoc()) {
        $materi_list[] = $materi;
    }
    $stmt_materi->close();
}

// Ambil semua nilai untuk siswa di kelas ini
$nilai_data = [];
if (!empty($siswa_list) && !empty($materi_list)) {
    $siswa_ids = array_column($siswa_list, 'id');
    $materi_ids = array_column($materi_list, 'id');
    
    if (!empty($siswa_ids) && !empty($materi_ids)) {
        $placeholders_siswa = implode(',', array_fill(0, count($siswa_ids), '?'));
        $placeholders_materi = implode(',', array_fill(0, count($materi_ids), '?'));
        
        $query_nilai = "SELECT * FROM nilai_siswa WHERE siswa_id IN ($placeholders_siswa) AND materi_mulok_id IN ($placeholders_materi) AND semester = ? AND tahun_ajaran = ?";
        $stmt_nilai = $conn->prepare($query_nilai);
        
        $params = array_merge($siswa_ids, $materi_ids);
        $params[] = $semester_aktif;
        $params[] = $tahun_ajaran;
        
        $types = str_repeat('i', count($siswa_ids)) . str_repeat('i', count($materi_ids)) . 'ss';
        $stmt_nilai->bind_param($types, ...$params);
        $stmt_nilai->execute();
        $result_nilai = $stmt_nilai->get_result();
        
        while ($nilai = $result_nilai->fetch_assoc()) {
            $key = $nilai['siswa_id'] . '_' . $nilai['materi_mulok_id'];
            $nilai_data[$key] = $nilai;
        }
        $stmt_nilai->close();
    }
}

$conn->close();

// Konversi semester
function getSemesterText($semester) {
    return ($semester == '1') ? 'Gasal' : 'Genap';
}

// Cek apakah kolom desa/kelurahan ada
$has_desa = false;
try {
    $conn_check = getConnection();
    $check_cols = $conn_check->query("SHOW COLUMNS FROM profil_madrasah LIKE 'desa'");
    if ($check_cols && $check_cols->num_rows > 0) {
        $has_desa = true;
    }
    $conn_check->close();
} catch (Exception $e) {
    $has_desa = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Semua Rapor - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? ''); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1.5cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .cover-page {
                page-break-after: always;
            }
            .nilai-page {
                page-break-after: always;
                page-break-inside: auto;
            }
            .nilai-page:last-child {
                page-break-after: auto;
            }
            .nilai-table {
                page-break-inside: auto;
            }
            .nilai-table tr {
                page-break-inside: avoid;
            }
            .ttd-row, .ttd-center {
                page-break-inside: avoid;
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
        
        /* Cover Page Styles */
        .logo-container {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .logo {
            width: 150px;
            height: 150px;
            display: inline-block;
            padding: 10px;
            background-color: #fff;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .title {
            text-align: center;
            margin: 20px 0 15px 0;
        }
        
        .title h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
        }
        
        .info-container {
            border: 2px solid #000;
            padding: 15px;
            margin: 70px 0 15px 0;
        }
        
        .info-siswa-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .info-siswa-table td {
            padding: 5px 10px;
            vertical-align: top;
            font-size: 12pt;
        }
        
        .info-siswa-table td:first-child {
            text-align: left;
            width: 35%;
            padding-right: 15px;
        }
        
        .info-siswa-table td:last-child {
            text-align: left;
            width: 65%;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .info-table td {
            padding: 5px 10px;
            vertical-align: top;
            font-size: 12pt;
        }
        
        .info-table td:first-child {
            text-align: left;
            width: 35%;
            padding-right: 15px;
        }
        
        .info-table td:last-child {
            text-align: left;
            width: 65%;
        }
        
        .footer {
            margin-top: 120px;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
        }
        
        /* Nilai Page Styles */
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo-container-nilai {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .logo-nilai {
            width: 100px;
            height: 100px;
            display: inline-block;
            padding: 5px;
            background-color: #fff;
        }
        
        .logo-nilai img {
            width: 100%;
            height: 100%;
            object-fit: contain;
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
            font-size: 11pt;
        }
        
        .identitas-table td {
            padding: 5px 10px;
            text-align: left;
        }
        
        .identitas-table td.label {
            width: 18%;
        }
        
        .identitas-table td.colon {
            width: 2%;
            padding: 5px 5px;
        }
        
        .identitas-table td.value {
            width: 35%;
        }
        
        .identitas-table tr:last-child td.value {
            width: 50%;
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
        }
        
        .no-col {
            width: 5%;
            text-align: center;
        }
        
        .materi-col {
            width: 30%;
        }
        
        .nilai-col {
            width: 10%;
            text-align: center;
        }
        
        .predikat-col {
            width: 10%;
            text-align: center;
        }
        
        .deskripsi-col {
            width: 45%;
        }
        
        .kategori-header {
            font-weight: bold;
            background-color: #e0e0e0;
        }
        
        .tanggal {
            text-align: right;
            margin-bottom: 20px;
            margin-top: 20px;
            font-size: 12pt;
        }
        
        .ttd-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
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
            margin-top: 10px;
        }
        
        .ttd-center label {
            display: block;
            margin-bottom: 40px;
            font-size: 12pt;
        }
        
        .ttd-center .nama {
            font-weight: bold;
            margin-top: 5px;
            font-size: 12pt;
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
        <?php foreach ($siswa_list as $index => $siswa): ?>
            <!-- Cover Page -->
            <div class="cover-page">
                <!-- Logo -->
                <div class="logo-container">
                    <div class="logo">
                        <?php if (!empty($profil_madrasah['logo'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($profil_madrasah['logo']); ?>" alt="Logo Madrasah">
                        <?php else: ?>
                            <div style="text-align: center; padding-top: 30px; color: #2d5016; font-weight: bold;">
                                <div style="font-size: 10pt;">MADRASAH</div>
                                <div style="font-size: 10pt;">IBTIDAIYAH</div>
                                <div style="font-size: 12pt; margin-top: 5px;">SULTAN</div>
                                <div style="font-size: 12pt;">FATTAH</div>
                                <div style="font-size: 9pt; margin-top: 5px;">SUKOSONO</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Judul -->
                <div class="title">
                    <h1>LAPORAN PENILAIAN<br>MULOK KHUSUS</h1>
                </div>
                
                <!-- Container untuk semua info (siswa + madrasah) -->
                <div class="info-container">
                    <!-- Info Siswa -->
                    <div class="info-siswa-box">
                        <table class="info-siswa-table">
                            <tr>
                                <td>Nama Siswa</td>
                                <td>: <?php echo htmlspecialchars(strtoupper($siswa['nama'] ?? '-')); ?></td>
                            </tr>
                            <tr>
                                <td>NISN</td>
                                <td>: <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <hr style="border: 1px solid #000; margin: 20px 0;">
                    
                    <!-- Info Madrasah -->
                    <table class="info-table">
                        <tr>
                            <td>Nama Madrasah</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['nama_madrasah'] ?? 'MIS Sultan Fattah Sukosono'); ?></td>
                        </tr>
                        <tr>
                            <td>NSM</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['nsm'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>Alamat Madrasah</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['alamat'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>Desa / Kelurahan</td>
                            <td>: <?php 
                                if ($has_desa && !empty($profil_madrasah['desa'])) {
                                    echo htmlspecialchars($profil_madrasah['desa']);
                                } else {
                                    // Default dari alamat atau nama madrasah
                                    echo 'Sukosono';
                                }
                            ?></td>
                        </tr>
                        <tr>
                            <td>Kecamatan</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['kecamatan'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>Kabupaten / Kota</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['kabupaten'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>Provinsi</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['provinsi'] ?? '-'); ?></td>
                        </tr>
                        <?php if (!empty($profil_madrasah['website'])): ?>
                        <tr>
                            <td>Website</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['website']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($profil_madrasah['email'])): ?>
                        <tr>
                            <td>Email</td>
                            <td>: <?php echo htmlspecialchars($profil_madrasah['email']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <!-- Footer -->
                <div class="footer">
                    <div>YAYASAN SULTAN FATTAH JEPARA</div>
                    <div>MADRASAH IBTIDAIYAH SULTAN FATTAH</div>
                    <div>SUKOSONO KEDUNG JEPARA</div>
                </div>
            </div>
            
            <!-- Nilai Page -->
            <div class="nilai-page">
                <!-- Header -->
                <div class="header">
                    <!-- Logo -->
                    <div class="logo-container-nilai">
                        <div class="logo-nilai">
                            <?php if (!empty($profil_madrasah['logo'])): ?>
                                <img src="../uploads/<?php echo htmlspecialchars($profil_madrasah['logo']); ?>" alt="Logo Madrasah">
                            <?php else: ?>
                                <div style="text-align: center; padding-top: 25px; color: #2d5016; font-weight: bold;">
                                    <div style="font-size: 9pt;">MADRASAH</div>
                                    <div style="font-size: 9pt;">IBTIDAIYAH</div>
                                    <div style="font-size: 11pt; margin-top: 3px;">SULTAN</div>
                                    <div style="font-size: 11pt;">FATTAH</div>
                                    <div style="font-size: 8pt; margin-top: 3px;">SUKOSONO</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h1>LAPORAN PENILAIAN MULOK KHUSUS<br><?php echo htmlspecialchars(strtoupper($profil_madrasah['nama_madrasah'] ?? 'MI SULTAN FATTAH SUKOSONO')); ?></h1>
                </div>
                
                <!-- Identitas Siswa -->
                <table class="identitas-table">
                    <tr>
                        <td class="label">Nama Siswa</td>
                        <td class="colon">:</td>
                        <td class="value"><?php echo htmlspecialchars(strtoupper($siswa['nama'] ?? '-')); ?></td>
                        <td class="label">Kelas</td>
                        <td class="colon">:</td>
                        <td class="value"><?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <td class="label">NISN</td>
                        <td class="colon">:</td>
                        <td class="value"><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                        <td class="label">Tahun Ajaran</td>
                        <td class="colon">:</td>
                        <td class="value"><?php echo htmlspecialchars($tahun_ajaran); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Semester</td>
                        <td class="colon">:</td>
                        <td class="value"><?php echo getSemesterText($semester_aktif); ?></td>
                        <td colspan="3"></td>
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
                        $total_nilai = 0;
                        $count_nilai = 0;
                        $current_kategori = '';
                        
                        foreach ($materi_list as $materi): 
                            $key = $siswa['id'] . '_' . $materi['id'];
                            $nilai = $nilai_data[$key] ?? null;
                            
                            $kategori = '';
                            if ($has_kategori_mulok && !empty($materi['kategori_mulok'])) {
                                $kategori = $materi['kategori_mulok'];
                            }
                            
                            $nilai_value = $nilai ? ($nilai['nilai_pengetahuan'] ?? $nilai['harian'] ?? '') : '';
                            $predikat = $nilai ? ($nilai['predikat'] ?? '') : '';
                            $deskripsi = $nilai ? ($nilai['deskripsi'] ?? '') : '';
                            
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
                            
                            if ($show_kategori && !empty($kategori)):
                        ?>
                            <tr class="kategori-header">
                                <td colspan="5"><?php echo htmlspecialchars($kategori); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="no-col"><?php echo $no++; ?></td>
                            <td class="materi-col">
                                <?php echo htmlspecialchars($materi['nama_mulok']); ?>
                            </td>
                            <td class="nilai-col"><?php echo htmlspecialchars($nilai_value ?: '-'); ?></td>
                            <td class="predikat-col"><?php echo htmlspecialchars($predikat ?: '-'); ?></td>
                            <td class="deskripsi-col"><?php echo htmlspecialchars($deskripsi ?: '-'); ?></td>
                        </tr>
                        <?php 
                            if (!empty($nilai_value)) {
                                $total_nilai += floatval($nilai_value);
                                $count_nilai++;
                            }
                        endforeach; 
                        ?>
                        <tr>
                            <td colspan="2" style="font-weight: bold; text-align: right;">RERATA</td>
                            <td class="nilai-col" style="font-weight: bold;"><?php echo $count_nilai > 0 ? round($total_nilai / $count_nilai, 0) : 0; ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Tanda Tangan -->
                <div class="tanggal">
                    <?php 
                    $tempat_cetak = $pengaturan_cetak['tempat_cetak'] ?? 'Jepara';
                    $tanggal_cetak = $pengaturan_cetak['tanggal_cetak'] ?? date('Y-m-d');
                    echo htmlspecialchars($tempat_cetak) . ', ' . formatTanggalIndonesia($tanggal_cetak);
                    ?>
                </div>
                
                <div class="ttd-row">
                    <div class="ttd-item">
                        <label>Wali Murid,</label>
                        <div class="nama">(<?php echo htmlspecialchars($siswa['orangtua_wali'] ?? '___________________'); ?>)</div>
                    </div>
                    <div class="ttd-item">
                        <label>Wali Kelas,</label>
                        <div class="nama">(<?php echo htmlspecialchars($kelas_data['wali_kelas_nama'] ?? '-'); ?>)</div>
                    </div>
                </div>
                
                <div class="ttd-center">
                    <div style="margin-bottom: 5px;">Mengetahui</div>
                    <div style="margin-bottom: 50px;">Kepala MI,</div>
                    <div class="nama">(<?php echo htmlspecialchars($profil_madrasah['nama_kepala'] ?? '-'); ?>)</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>

