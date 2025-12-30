<?php
ob_start();
require_once '../config/config.php';
require_once '../config/database.php';
requireRole(['proktor', 'wali_kelas']);

$conn = getConnection();

// Ambil parameter
$siswa_id = $_GET['siswa'] ?? 0;
$kelas_id = $_GET['kelas'] ?? 0;

// Pastikan kolom email_madrasah dan website_madrasah ada di database
try {
    $check_email = $conn->query("SHOW COLUMNS FROM profil_madrasah LIKE 'email_madrasah'");
    if ($check_email->num_rows == 0) {
        $conn->query("ALTER TABLE profil_madrasah ADD COLUMN email_madrasah VARCHAR(255) DEFAULT NULL");
    }
    $check_website = $conn->query("SHOW COLUMNS FROM profil_madrasah LIKE 'website_madrasah'");
    if ($check_website->num_rows == 0) {
        $conn->query("ALTER TABLE profil_madrasah ADD COLUMN website_madrasah VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {
    // Kolom mungkin sudah ada, lanjutkan
}

// Ambil data profil madrasah
$profil = null;
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
}

// Ambil data siswa
$siswa_data = [];
$siswa_data_by_id = []; // Menggunakan array dengan key ID untuk menghindari duplikasi

if ($siswa_id > 0) {
    $stmt = $conn->prepare("SELECT s.id, s.nisn, s.nama, s.jenis_kelamin, s.tempat_lahir, s.tanggal_lahir, s.orangtua_wali, s.kelas_id, k.nama_kelas, k.wali_kelas_id FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ? LIMIT 1");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $siswa_id_key = (int)$row['id'];
        if (!isset($siswa_data_by_id[$siswa_id_key])) {
            $siswa_data_by_id[$siswa_id_key] = $row;
        }
    }
} elseif ($kelas_id > 0) {
    // Query sederhana tanpa JOIN dulu untuk menghindari duplikasi dari JOIN
    $stmt = $conn->prepare("SELECT id, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, orangtua_wali, kelas_id FROM siswa WHERE kelas_id = ? AND id IS NOT NULL ORDER BY id ASC");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Ambil data kelas sekali saja
    $kelas_stmt = $conn->prepare("SELECT nama_kelas, wali_kelas_id FROM kelas WHERE id = ?");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $siswa_id_key = (int)$row['id'];
            
            // Hanya proses jika ID belum ada di array (menghindari duplikasi)
            if (!isset($siswa_data_by_id[$siswa_id_key])) {
                // Ambil data kelas
                $kelas_stmt->bind_param("i", $row['kelas_id']);
                $kelas_stmt->execute();
                $kelas_result = $kelas_stmt->get_result();
                if ($kelas_result && $kelas_result->num_rows > 0) {
                    $kelas_data = $kelas_result->fetch_assoc();
                    $row['nama_kelas'] = $kelas_data['nama_kelas'] ?? '';
                    $row['wali_kelas_id'] = $kelas_data['wali_kelas_id'] ?? 0;
                } else {
                    $row['nama_kelas'] = '';
                    $row['wali_kelas_id'] = 0;
                }
                
                // Simpan dengan key ID untuk memastikan tidak ada duplikasi
                $siswa_data_by_id[$siswa_id_key] = $row;
            }
        }
    }
    $kelas_stmt->close();
}

// Konversi array dengan key ID menjadi array numerik untuk looping
$siswa_data = array_values($siswa_data_by_id);

// Ambil data wali kelas dan kepala madrasah untuk setiap siswa
foreach ($siswa_data as $index => $siswa) {
    $wali_kelas_id = $siswa['wali_kelas_id'] ?? 0;
    $wali_kelas_nama = '';
    if ($wali_kelas_id > 0) {
        $stmt_wali = $conn->prepare("SELECT nama FROM pengguna WHERE id = ?");
        $stmt_wali->bind_param("i", $wali_kelas_id);
        $stmt_wali->execute();
        $result_wali = $stmt_wali->get_result();
        if ($result_wali && $result_wali->num_rows > 0) {
            $wali_data = $result_wali->fetch_assoc();
            $wali_kelas_nama = $wali_data['nama'] ?? '';
        }
        $stmt_wali->close();
    }
    $siswa_data[$index]['wali_kelas_nama'] = $wali_kelas_nama;
    
    if ($profil) {
        $siswa_data[$index]['kepala_madrasah'] = $profil['nama_kepala'] ?? '';
    } else {
        $siswa_data[$index]['kepala_madrasah'] = '';
    }
}

// Ambil semester dan tahun ajaran aktif
$semester_aktif = '1';
$tahun_ajaran_aktif = '';
if ($profil) {
    $semester_aktif = $profil['semester_aktif'] ?? '1';
    $tahun_ajaran_aktif = $profil['tahun_ajaran_aktif'] ?? '';
}

// Format semester
$semester_text = ($semester_aktif == '1') ? 'Gasal' : 'Genap';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Sampul Rapor</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif !important;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            background: #fff;
        }
        body, body *, body *::before, body *::after,
        html, html *, html *::before, html *::after,
        table, table *, td, th, tr, div, span, p, h1, h2, h3, h4, h5, h6 {
            font-family: Arial, sans-serif !important;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            padding-bottom: 50mm;
            margin: 0 auto;
            background: #fff;
            page-break-after: always;
            position: relative;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo {
            width: 160px;
            height: 160px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            -moz-box-shadow: none !important;
        }
        .logo div {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            -moz-box-shadow: none !important;
            background: transparent !important;
        }
        .logo-container {
            border: none !important;
            outline: none !important;
        }
        .title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: Arial, sans-serif !important;
        }
        .student-info {
            margin-top: 100px;
            margin-bottom: 30px;
        }
        .info-box {
            border: 2px solid #000;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 11pt;
        }
        .info-box table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Arial, sans-serif !important;
        }
        .info-box table td:first-child {
            width: auto;
            font-weight: bold;
            text-align: left;
            padding-right: 5px;
            white-space: nowrap;
            font-family: Arial, sans-serif !important;
            vertical-align: top;
        }
        .info-box table td:nth-child(2) {
            width: 10px;
            font-weight: bold;
            text-align: right;
            padding-right: 5px;
            padding-left: 0;
            white-space: nowrap;
            font-family: Arial, sans-serif !important;
            vertical-align: top;
        }
        .info-box table td:last-child {
            padding-left: 0;
            word-wrap: break-word;
            font-family: Arial, sans-serif !important;
            vertical-align: top;
        }
        .madrasah-info {
            margin-top: 40px;
            font-size: 11pt;
        }
        .madrasah-info table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Arial, sans-serif !important;
        }
        .madrasah-info table td {
            padding: 5px 0;
            vertical-align: top;
            font-family: Arial, sans-serif !important;
        }
        .madrasah-info table td:first-child {
            width: auto;
            font-weight: bold;
            text-align: left;
            padding-right: 5px;
            white-space: nowrap;
            font-family: Arial, sans-serif !important;
            vertical-align: top;
        }
        .madrasah-info table td:nth-child(2) {
            width: 10px;
            font-weight: bold;
            text-align: right;
            padding-right: 5px;
            padding-left: 0;
            white-space: nowrap;
            font-family: Arial, sans-serif !important;
            vertical-align: top;
        }
        .madrasah-info table td:last-child {
            padding-left: 0;
            word-wrap: break-word;
            font-family: Arial, sans-serif !important;
            vertical-align: top;
        }
        .footer {
            position: absolute;
            bottom: 20mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
            font-family: Arial, sans-serif !important;
            line-height: 1.6;
        }
        .footer-line {
            display: block;
            margin-bottom: 3px;
            text-align: center;
        }
        .footer-line:nth-child(2) {
            margin-left: auto;
            margin-right: auto;
        }
        @media print {
            body, body *, body *::before, body *::after {
                font-family: Arial, sans-serif !important;
            }
            .logo, .logo *, .logo img, .logo div {
                border: none !important;
                outline: none !important;
                box-shadow: none !important;
            }
            body {
                background: #fff;
            }
            .page {
                margin: 0;
                padding: 20mm;
                padding-bottom: 50mm;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php foreach ($siswa_data as $siswa): ?>
    <div class="page">
        <div class="logo-container">
            <div class="logo" style="border: none !important; outline: none !important; box-shadow: none !important;">
                <?php 
                $logo_path = '';
                if (!empty($profil['logo']) && file_exists('../uploads/' . $profil['logo'])) {
                    $logo_path = '../uploads/' . htmlspecialchars($profil['logo']);
                }
                ?>
                <?php if (!empty($logo_path)): ?>
                    <img src="<?php echo $logo_path; ?>" alt="Logo" style="border: none !important; outline: none !important; box-shadow: none !important;">
                <?php else: ?>
                    <div style="font-size: 10pt; text-align: center; padding: 10px; border: none !important; outline: none !important; box-shadow: none !important; font-family: Arial, sans-serif !important;">
                        <div style="font-weight: bold; font-family: Arial, sans-serif !important;">MADRASAH<br>IBTIDAIYAH</div>
                        <div style="font-size: 8pt; margin-top: 5px; font-family: Arial, sans-serif !important;">SULTAN FATTAH<br>SUKOSONO</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="title">
            LAPORAN PENILAIAN MULOK KHUSUS<br>
            <?php echo htmlspecialchars(strtoupper($profil['nama_madrasah'] ?? 'MADRASAH IBTIDAIYAH SULTAN FATTAH SUKOSONO')); ?>
        </div>
        
        <div class="student-info">
            <div class="info-box">
                <table>
                    <tr>
                        <td>Nama Siswa</td>
                        <td>:</td>
                        <td><?php echo strtoupper(htmlspecialchars($siswa['nama'] ?? '-')); ?></td>
                    </tr>
                </table>
            </div>
            <div class="info-box">
                <table>
                    <tr>
                        <td>NISN</td>
                        <td>:</td>
                        <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="madrasah-info">
            <table>
                <tr>
                    <td>Nama Madrasah</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['nama_madrasah'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>NSM</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['nsm'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>Alamat Madrasah</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['alamat'] ?? '-'); ?></td>
                </tr>
                <?php if (!empty($profil['desa_kelurahan'])): ?>
                <tr>
                    <td>Desa/Kelurahan</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['desa_kelurahan']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['kecamatan'])): ?>
                <tr>
                    <td>Kecamatan</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['kecamatan']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['kabupaten'])): ?>
                <tr>
                    <td>Kabupaten / Kota</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['kabupaten']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['provinsi'])): ?>
                <tr>
                    <td>Provinsi</td>
                    <td>:</td>
                    <td><?php echo htmlspecialchars($profil['provinsi']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Email Madrasah</td>
                    <td>:</td>
                    <td><?php echo !empty($profil['email_madrasah']) ? htmlspecialchars($profil['email_madrasah']) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Website Madrasah</td>
                    <td>:</td>
                    <td><?php echo !empty($profil['website_madrasah']) ? htmlspecialchars($profil['website_madrasah']) : '-'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <div class="footer-line">YAYASAN SULTAN FATTAH JEPARA</div>
            <div class="footer-line">MADRASAH IBTIDAIYAH SULTAN FATTAH</div>
            <div class="footer-line">SUKOSONO <?php echo htmlspecialchars(strtoupper($profil['kecamatan'] ?? 'KEDUNG')); ?> <?php echo htmlspecialchars(strtoupper($profil['kabupaten'] ?? 'JEPARA')); ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>

