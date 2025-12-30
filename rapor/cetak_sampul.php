<?php
ob_start();
require_once '../config/config.php';
require_once '../config/database.php';
requireRole(['proktor', 'wali_kelas']);

$conn = getConnection();

// Ambil parameter
$siswa_id = $_GET['siswa'] ?? 0;
$kelas_id = $_GET['kelas'] ?? 0;

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
if ($siswa_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, k.nama_kelas, k.wali_kelas_id FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $siswa_data[] = $result->fetch_assoc();
    }
} elseif ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, k.nama_kelas, k.wali_kelas_id FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.kelas_id = ? ORDER BY s.nama ASC");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $siswa_data[] = $row;
        }
    }
}

// Ambil data wali kelas dan kepala madrasah untuk setiap siswa
foreach ($siswa_data as &$siswa) {
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
    }
    $siswa['wali_kelas_nama'] = $wali_kelas_nama;
    
    if ($profil) {
        $siswa['kepala_madrasah'] = $profil['nama_kepala'] ?? '';
    } else {
        $siswa['kepala_madrasah'] = '';
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
        body, body *, body *::before, body *::after {
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
            margin-bottom: 20px;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: none;
        }
        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border: none !important;
            outline: none !important;
        }
        .logo div {
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
            margin: 30px 0;
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
            width: 140px;
            font-weight: bold;
            text-align: right;
            padding-right: 10px;
            white-space: nowrap;
            font-family: Arial, sans-serif !important;
        }
        .info-box table td:last-child {
            padding-left: 0;
            word-wrap: break-word;
            font-family: Arial, sans-serif !important;
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
            width: 220px;
            font-weight: bold;
            text-align: right;
            padding-right: 10px;
            white-space: nowrap;
            font-family: Arial, sans-serif !important;
        }
        .madrasah-info table td:last-child {
            padding-left: 0;
            word-wrap: break-word;
            font-family: Arial, sans-serif !important;
        }
        .signature-section {
            margin-top: 60px;
            font-size: 11pt;
        }
        .signature-date {
            text-align: right;
            margin-bottom: 40px;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .signature-left, .signature-right {
            width: 48%;
        }
        .signature-center {
            text-align: center;
            width: 100%;
        }
        .signature-name {
            margin-top: 50px;
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
        }
        @media print {
            body {
                background: #fff;
            }
            .page {
                margin: 0;
                padding: 20mm;
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
                        <td>Nama Siswa:</td>
                        <td><?php echo strtoupper(htmlspecialchars($siswa['nama'] ?? '-')); ?></td>
                    </tr>
                </table>
            </div>
            <div class="info-box">
                <table>
                    <tr>
                        <td>NISN:</td>
                        <td><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="madrasah-info">
            <table>
                <tr>
                    <td>Nama Madrasah:</td>
                    <td><?php echo htmlspecialchars($profil['nama_madrasah'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>NSM:</td>
                    <td><?php echo htmlspecialchars($profil['nsm'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td>Alamat Madrasah:</td>
                    <td><?php echo htmlspecialchars($profil['alamat'] ?? '-'); ?></td>
                </tr>
                <?php if (!empty($profil['desa_kelurahan'])): ?>
                <tr>
                    <td>Desa/Kelurahan:</td>
                    <td><?php echo htmlspecialchars($profil['desa_kelurahan']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['kecamatan'])): ?>
                <tr>
                    <td>Kecamatan:</td>
                    <td><?php echo htmlspecialchars($profil['kecamatan']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['kabupaten'])): ?>
                <tr>
                    <td>Kabupaten / Kota:</td>
                    <td><?php echo htmlspecialchars($profil['kabupaten']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['provinsi'])): ?>
                <tr>
                    <td>Provinsi:</td>
                    <td><?php echo htmlspecialchars($profil['provinsi']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['website_madrasah'])): ?>
                <tr>
                    <td>Website:</td>
                    <td><?php echo htmlspecialchars($profil['website_madrasah']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($profil['email_madrasah'])): ?>
                <tr>
                    <td>Email:</td>
                    <td><?php echo htmlspecialchars($profil['email_madrasah']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="signature-section">
            <div class="signature-date">
                Jepara, <?php echo date('d F Y'); ?>
            </div>
            
            <div class="signature-row">
                <div class="signature-left">
                    <div class="signature-name">
                        <?php 
                        $orangtua_wali = $siswa['orangtua_wali'] ?? '';
                        if (!empty($orangtua_wali)) {
                            echo htmlspecialchars($orangtua_wali);
                        } else {
                            echo 'Wali Murid';
                        }
                        ?>
                    </div>
                </div>
                <div class="signature-right">
                    <div class="signature-name">
                        <?php echo htmlspecialchars($siswa['wali_kelas_nama'] ?? 'Wali Kelas'); ?>
                    </div>
                </div>
            </div>
            
            <div class="signature-center">
                <div class="signature-name">
                    Mengetahui Kepala MI<br>
                    <?php echo htmlspecialchars($siswa['kepala_madrasah'] ?? ''); ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            YAYASAN SULTAN FATTAH JEPARA <?php echo htmlspecialchars(strtoupper($profil['nama_madrasah'] ?? 'MADRASAH IBTIDAIYAH SULTAN FATTAH SUKOSONO')); ?> <?php echo htmlspecialchars(strtoupper($profil['kecamatan'] ?? '')); ?> <?php echo htmlspecialchars(strtoupper($profil['kabupaten'] ?? 'JEPARA')); ?>
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

