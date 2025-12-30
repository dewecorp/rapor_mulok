<?php
ob_start();
require_once '../config/config.php';
require_once '../config/database.php';
requireRole(['proktor', 'wali_kelas']);

$conn = getConnection();

// Cek kolom yang tersedia (kategori_mulok atau kode_mulok)
$use_kategori = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM materi_mulok LIKE 'kategori_mulok'");
    $use_kategori = ($check_column && $check_column->num_rows > 0);
} catch (Exception $e) {
    $use_kategori = false;
}
$kolom_kategori = $use_kategori ? 'kategori_mulok' : 'kode_mulok';

// Fungsi untuk menghitung predikat berdasarkan nilai
function hitungPredikat($nilai) {
    $nilai_float = floatval($nilai);
    if ($nilai_float <= 60) return 'D';
    elseif ($nilai_float <= 69) return 'C';
    elseif ($nilai_float <= 89) return 'B';
    elseif ($nilai_float <= 100) return 'A';
    return '-';
}

// Ambil parameter
$siswa_id = $_GET['siswa'] ?? 0;
$kelas_id = $_GET['kelas'] ?? 0;
$semua = $_GET['semua'] ?? 0;

// Ambil data profil madrasah
$profil = null;
try {
    $query_profil = "SELECT * FROM profil_madrasah LIMIT 1";
    $result_profil = $conn->query($query_profil);
    $profil = $result_profil ? $result_profil->fetch_assoc() : null;
} catch (Exception $e) {
    $profil = null;
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

// Ambil data siswa
$siswa_data = [];
if ($siswa_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $siswa_data[] = $result->fetch_assoc();
    }
} elseif ($kelas_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.kelas_id = ? ORDER BY s.nama ASC");
    $stmt->bind_param("i", $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $siswa_data[] = $row;
        }
    }
}

// Ambil data wali kelas dan kepala madrasah
$wali_kelas_nama = '';
$kepala_madrasah = '';
if ($profil) {
    $kepala_madrasah = $profil['nama_kepala'] ?? '';
}

// Untuk setiap siswa, ambil nilai-nilainya
foreach ($siswa_data as &$siswa) {
    $siswa_id_current = $siswa['id'];
    $kelas_id_current = $siswa['kelas_id'] ?? 0;
    
    // Ambil wali kelas dari kelas siswa ini
    if ($kelas_id_current > 0) {
        $stmt_wali = $conn->prepare("SELECT p.nama FROM kelas k LEFT JOIN pengguna p ON k.wali_kelas_id = p.id WHERE k.id = ?");
        $stmt_wali->bind_param("i", $kelas_id_current);
        $stmt_wali->execute();
        $result_wali = $stmt_wali->get_result();
        if ($result_wali && $result_wali->num_rows > 0) {
            $wali_data = $result_wali->fetch_assoc();
            $wali_kelas_nama = $wali_data['nama'] ?? '';
        }
    }
    
    // Ambil semua materi yang diampu di kelas ini untuk semester aktif
    $materi_list = [];
    $query_materi = "SELECT DISTINCT m.id, m.nama_mulok, m.$kolom_kategori as kategori
                     FROM mengampu_materi mm
                     INNER JOIN materi_mulok m ON mm.materi_mulok_id = m.id
                     WHERE mm.kelas_id = ? AND m.semester = ?
                     ORDER BY m.nama_mulok ASC";
    $stmt_materi = $conn->prepare($query_materi);
    $stmt_materi->bind_param("is", $kelas_id_current, $semester_aktif);
    $stmt_materi->execute();
    $result_materi = $stmt_materi->get_result();
    if ($result_materi) {
        while ($materi = $result_materi->fetch_assoc()) {
            // Ambil nilai siswa untuk materi ini
            $query_nilai = "SELECT nilai_pengetahuan, predikat, deskripsi FROM nilai_siswa 
                           WHERE siswa_id = ? AND materi_mulok_id = ? AND semester = ? AND tahun_ajaran = ?";
            $stmt_nilai = $conn->prepare($query_nilai);
            $stmt_nilai->bind_param("iiss", $siswa_id_current, $materi['id'], $semester_aktif, $tahun_ajaran_aktif);
            $stmt_nilai->execute();
            $result_nilai = $stmt_nilai->get_result();
            $nilai_data = $result_nilai->fetch_assoc();
            
            $nilai_value = $nilai_data['nilai_pengetahuan'] ?? '';
            $predikat_value = $nilai_data['predikat'] ?? '';
            $deskripsi_value = $nilai_data['deskripsi'] ?? '';
            
            // Hitung predikat jika belum ada
            if (empty($predikat_value) && !empty($nilai_value)) {
                $predikat_value = hitungPredikat($nilai_value);
            }
            
            // Ambil kategori
            $kategori = $materi['kategori'] ?? '';
            
            $materi_list[] = [
                'nama' => $materi['nama_mulok'],
                'kategori' => $kategori,
                'nilai' => $nilai_value,
                'predikat' => $predikat_value,
                'deskripsi' => $deskripsi_value
            ];
        }
    }
    
    $siswa['materi_list'] = $materi_list;
    
    // Hitung rerata
    $total_nilai = 0;
    $count_nilai = 0;
    foreach ($materi_list as $materi) {
        if (!empty($materi['nilai']) && is_numeric($materi['nilai'])) {
            $total_nilai += floatval($materi['nilai']);
            $count_nilai++;
        }
    }
    $siswa['rerata'] = $count_nilai > 0 ? round($total_nilai / $count_nilai, 0) : 0;
    
    // Hitung catatan kompetensi berdasarkan rerata
    $siswa['catatan_kompetensi'] = '';
    if ($siswa['rerata'] > 0) {
        $predikat_rerata = hitungPredikat($siswa['rerata']);
        $kata_predikat = '';
        switch ($predikat_rerata) {
            case 'A':
                $kata_predikat = 'SANGAT BAIK';
                break;
            case 'B':
                $kata_predikat = 'BAIK';
                break;
            case 'C':
                $kata_predikat = 'CUKUP';
                break;
            case 'D':
                $kata_predikat = 'KURANG';
                break;
        }
        if (!empty($kata_predikat)) {
            $siswa['catatan_kompetensi'] = 'ANANDA ' . strtoupper($siswa['nama']) . ' ' . $kata_predikat . ' DALAM KOMPETENSI PENDIDIKAN MUATAN LOKAL KHUSUS MADRASAH.';
            if ($predikat_rerata == 'D') {
                $siswa['catatan_kompetensi'] .= ' MOHON ORANG TUA/WALI MENINGKATKAN BIMBINGAN';
            }
        }
    } else {
        $siswa['catatan_kompetensi'] = 'ANANDA ' . strtoupper($siswa['nama']) . ' KURANG DALAM KOMPETENSI PENDIDIKAN MUATAN LOKAL KHUSUS MADRASAH. MOHON ORANG TUA/WALI MENINGKATKAN BIMBINGAN';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rapor Lengkap</title>
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
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            background: #fff;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: #fff;
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        /* Sampul Styles */
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            border: 2px solid #2d5016;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }
        .logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
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
        .madrasah-info {
            margin-top: 40px;
            font-size: 11pt;
        }
        .madrasah-info p {
            margin: 5px 0;
            line-height: 1.8;
        }
        .madrasah-info strong {
            display: inline-block;
            width: 180px;
        }
        /* Nilai Styles */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-logo {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .header-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .header-text {
            flex: 1;
            text-align: center;
        }
        .header-text h2 {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            margin: 0;
        }
        .student-info-nilai {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 10pt;
        }
        .student-info-left, .student-info-right {
            width: 48%;
        }
        .student-info-nilai p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 10pt;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        table td {
            vertical-align: top;
        }
        table td:first-child {
            text-align: center;
            width: 30px;
        }
        table td:nth-child(3) {
            text-align: center;
            width: 60px;
        }
        table td:nth-child(4) {
            text-align: center;
            width: 60px;
        }
        .rerata {
            margin: 15px 0;
            font-size: 11pt;
            font-weight: bold;
        }
        .catatan {
            border: 1px solid #000;
            padding: 10px;
            margin: 15px 0;
            min-height: 80px;
            font-size: 10pt;
        }
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            font-size: 10pt;
        }
        .signature-item {
            text-align: center;
            width: 30%;
        }
        .signature-item p {
            margin: 5px 0;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
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
    <!-- Halaman Sampul -->
    <div class="page">
        <div class="logo-container">
            <div class="logo">
                <?php if (!empty($profil['logo'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($profil['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <div style="font-size: 10pt; text-align: center; padding: 10px;">
                        <div style="font-weight: bold;">MADRASAH<br>IBTIDAIYAH</div>
                        <div style="font-size: 8pt; margin-top: 5px;">SULTAN FATTAH<br>SUKOSONO</div>
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
                <strong>Nama Siswa :</strong> <?php echo strtoupper(htmlspecialchars($siswa['nama'] ?? '-')); ?>
            </div>
            <div class="info-box">
                <strong>NISN :</strong> <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?>
            </div>
        </div>
        
        <div class="madrasah-info">
            <p><strong>Nama Madrasah:</strong> <?php echo htmlspecialchars($profil['nama_madrasah'] ?? '-'); ?></p>
            <p><strong>NSM:</strong> <?php echo htmlspecialchars($profil['nsm'] ?? '-'); ?></p>
            <p><strong>Alamat Madrasah:</strong> <?php echo htmlspecialchars($profil['alamat'] ?? '-'); ?></p>
            <?php if (!empty($profil['desa_kelurahan'])): ?>
            <p><strong>Desa/Kelurahan:</strong> <?php echo htmlspecialchars($profil['desa_kelurahan']); ?></p>
            <?php endif; ?>
            <?php if (!empty($profil['kecamatan'])): ?>
            <p><strong>Kecamatan:</strong> <?php echo htmlspecialchars($profil['kecamatan']); ?></p>
            <?php endif; ?>
            <?php if (!empty($profil['kabupaten'])): ?>
            <p><strong>Kabupaten / Kota:</strong> <?php echo htmlspecialchars($profil['kabupaten']); ?></p>
            <?php endif; ?>
            <?php if (!empty($profil['provinsi'])): ?>
            <p><strong>Provinsi:</strong> <?php echo htmlspecialchars($profil['provinsi']); ?></p>
            <?php endif; ?>
            <?php if (!empty($profil['website_madrasah'])): ?>
            <p><strong>Website:</strong> <?php echo htmlspecialchars($profil['website_madrasah']); ?></p>
            <?php endif; ?>
            <?php if (!empty($profil['email_madrasah'])): ?>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($profil['email_madrasah']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Halaman Nilai -->
    <div class="page">
        <div class="header">
            <div class="header-logo">
                <?php 
                $logo_path = '';
                if (!empty($profil['logo']) && file_exists('../uploads/' . $profil['logo'])) {
                    $logo_path = '../uploads/' . htmlspecialchars($profil['logo']);
                }
                ?>
                <?php if (!empty($logo_path)): ?>
                    <img src="<?php echo $logo_path; ?>" alt="Logo" style="width: 60px; height: 60px; object-fit: contain;">
                <?php else: ?>
                    <div style="width: 60px; height: 60px; border: 2px solid #2d5016; border-radius: 5px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 8pt; text-align: center; padding: 5px; background: #fff;">
                        <div style="font-weight: bold; line-height: 1.2;">MADRASAH<br>IBTIDAIYAH</div>
                        <div style="font-size: 7pt; margin-top: 2px; line-height: 1.2;">SULTAN FATTAH<br>SUKOSONO</div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="header-text">
                <h2>LAPORAN PENILAIAN MULOK KHUSUS<br><?php echo htmlspecialchars(strtoupper($profil['nama_madrasah'] ?? 'MADRASAH IBTIDAIYAH SULTAN FATTAH SUKOSONO')); ?></h2>
            </div>
        </div>
        
        <div class="student-info-nilai">
            <div class="student-info-left">
                <p><strong>Nama Siswa:</strong> <?php echo htmlspecialchars($siswa['nama']); ?></p>
                <p><strong>NISN:</strong> <?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></p>
                <p><strong>Semester:</strong> <?php echo $semester_text; ?></p>
            </div>
            <div class="student-info-right">
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></p>
                <p><strong>Tahun Ajaran:</strong> <?php echo htmlspecialchars($tahun_ajaran_aktif); ?></p>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Materi</th>
                    <th>Nilai</th>
                    <th>Predikat</th>
                    <th>Deskripsi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($siswa['materi_list'] as $materi): 
                    $materi_display = !empty($materi['kategori']) ? $materi['kategori'] . ' ' . $materi['nama'] : $materi['nama'];
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($materi_display); ?></td>
                    <td><?php echo !empty($materi['nilai']) ? htmlspecialchars($materi['nilai']) : ''; ?></td>
                    <td><?php echo !empty($materi['predikat']) ? htmlspecialchars($materi['predikat']) : ''; ?></td>
                    <td><?php echo !empty($materi['deskripsi']) ? htmlspecialchars($materi['deskripsi']) : ''; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="rerata">
            <strong>RERATA: <?php echo $siswa['rerata']; ?></strong>
        </div>
        
        <div class="catatan">
            <strong>CATATAN KOMPETENSI:</strong><br>
            <?php echo htmlspecialchars($siswa['catatan_kompetensi']); ?>
        </div>
        
        <div class="signature">
            <div class="signature-item">
                <div class="signature-line">
                    <p>Wali Murid</p>
                </div>
            </div>
            <div class="signature-item">
                <div class="signature-line">
                    <p>Wali Kelas</p>
                    <p><?php echo htmlspecialchars($wali_kelas_nama); ?></p>
                </div>
            </div>
            <div class="signature-item">
                <div class="signature-line">
                    <p>Mengetahui Kepala MI</p>
                    <p><?php echo htmlspecialchars($kepala_madrasah); ?></p>
                </div>
            </div>
        </div>
        
        <div style="text-align: right; margin-top: 20px; font-size: 10pt;">
            Jepara, <?php echo date('d F Y'); ?>
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

