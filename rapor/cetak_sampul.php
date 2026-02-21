<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireLogin();
if (!hasRole('proktor') && !hasRole('wali_kelas')) {
    header('Location: /index.php');
    exit();
}

$conn = getConnection();

// Ambil kelas dan siswa dari parameter
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;
$siswa_id = isset($_GET['siswa']) ? intval($_GET['siswa']) : 0;

// Ambil data
$kelas_data = null;
$siswa_data = [];
$profil_madrasah = null;

try {
    // Ambil data kelas (jika kelas_id sudah diketahui)
    if ($kelas_id > 0) {
        $stmt_kelas = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
        $stmt_kelas->bind_param("i", $kelas_id);
        $stmt_kelas->execute();
        $result_kelas = $stmt_kelas->get_result();
        $kelas_data = $result_kelas->fetch_assoc();
        $stmt_kelas->close();
    }
    
    // Ambil data siswa
    if ($siswa_id > 0) {
        // Jika ada siswa_id spesifik, ambil hanya siswa tersebut
        $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
        $stmt_siswa->bind_param("i", $siswa_id);
        $stmt_siswa->execute();
        $result_siswa = $stmt_siswa->get_result();
        $siswa_row = $result_siswa->fetch_assoc();
        if ($siswa_row) {
            $siswa_data[] = $siswa_row;
            if ($kelas_id <= 0 && !empty($siswa_row['kelas_id'])) {
                $kelas_id = (int)$siswa_row['kelas_id'];
                $stmt_kelas = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
                $stmt_kelas->bind_param("i", $kelas_id);
                $stmt_kelas->execute();
                $result_kelas = $stmt_kelas->get_result();
                $kelas_data = $result_kelas->fetch_assoc();
                $stmt_kelas->close();
            }
        }
        $stmt_siswa->close();
    } elseif ($kelas_id > 0) {
        // Jika tidak ada siswa_id, ambil semua siswa di kelas
        $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE kelas_id = ? ORDER BY nama");
        $stmt_siswa->bind_param("i", $kelas_id);
        $stmt_siswa->execute();
        $result_siswa = $stmt_siswa->get_result();
        
        while ($row = $result_siswa->fetch_assoc()) {
            // Skip jika nama kosong atau invalid
            $nama_siswa = trim($row['nama'] ?? '');
            if (empty($nama_siswa)) {
                continue;
            }
            
            // Skip jika nama sama dengan Administrator, Admin, atau Proktor
            $nama_lower = strtolower($nama_siswa);
            if ($nama_lower === 'administrator' || $nama_lower === 'admin' || $nama_lower === 'proktor') {
                continue;
            }
            
            $siswa_data[] = $row;
        }
        $stmt_siswa->close();
    }
    
    // Ambil data profil madrasah
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    if ($result_profil && $result_profil->num_rows > 0) {
        $profil_madrasah = $result_profil->fetch_assoc();
    }
} catch (Exception $e) {
    // Handle error
}

$conn->close();

$page_title_sampul = 'Sampul Rapor';
if (!empty($kelas_data['nama_kelas'] ?? '')) {
    $page_title_sampul .= ' - ' . trim($kelas_data['nama_kelas']);
}
if (!empty($siswa_data) && count($siswa_data) === 1) {
    $s = $siswa_data[0];
    $nama_siswa_title = strtoupper(trim($s['nama'] ?? 'Siswa'));
    $kelas_title = trim($kelas_data['nama_kelas'] ?? '');
    $nama_clean = preg_replace('/[^A-Za-z0-9\- ]/', '', $nama_siswa_title);
    $nama_clean = str_replace(' ', '_', $nama_clean);
    $kelas_clean = preg_replace('/[^A-Za-z0-9\- ]/', '', $kelas_title);
    $kelas_clean = str_replace(' ', '_', $kelas_clean);
    $page_title_sampul = 'Sampul_' . $nama_clean;
    if ($kelas_clean !== '') {
        $page_title_sampul .= '_' . $kelas_clean;
    }
}

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
    <title><?php echo htmlspecialchars($page_title_sampul); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0.3cm 2cm 0.5cm 2cm;
                margin-top: 0.3cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            /* Sembunyikan semua elemen yang tidak perlu */
            a[href]:after {
                content: "";
            }
            /* Sembunyikan URL dan elemen browser */
            body::before,
            body::after {
                display: none !important;
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
            font-size: 18pt;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 1px;
        }
        
        .info-box {
            border: 2px solid #000;
            padding: 10px 15px;
            margin: 15px 0;
            font-size: 12pt;
        }
        
        .info-container {
            border: 2px solid #000;
            padding: 15px;
            margin: 25px 0 15px 0;
        }
        
        .info-siswa-box {
            border: none;
            padding: 0;
            margin: 0 0 15px 0;
        }
        
        .info-siswa-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
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
        
        .info-table td:last-child,
        .info-siswa-table td:last-child {
            text-align: left;
        }
        
        .footer {
            margin-top: 120px;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
        }
        
        /* Page break untuk setiap siswa */
        @media print {
            @page {
                size: A4;
                margin: 1cm 2cm 0.5cm 2cm;
                margin-top: 1cm;
            }
            .page-break {
                page-break-after: always;
            }
        }
        
        .student-page {
            margin-bottom: 50px;
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
        <?php if (!empty($siswa_data)): ?>
            <?php foreach ($siswa_data as $index => $siswa): ?>
                <div class="student-page">
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
                        
                        <!-- Garis pemisah -->
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #000;">
                        
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
                
                <?php if ($index < count($siswa_data) - 1): ?>
                    <!-- Page break untuk siswa berikutnya -->
                    <div class="page-break"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 20px;">
                <p>Tidak ada data siswa untuk ditampilkan.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
