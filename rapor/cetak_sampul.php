<?php
require_once '../config/config.php';
require_once '../config/database.php';
requireRole('proktor');

$conn = getConnection();

// Ambil kelas dari parameter
$kelas_id = isset($_GET['kelas']) ? intval($_GET['kelas']) : 0;

// Ambil data kelas
$kelas_data = null;
$siswa_data = [];
$profil_madrasah = null;

try {
    // Ambil data kelas
    if ($kelas_id > 0) {
        $stmt_kelas = $conn->prepare("SELECT * FROM kelas WHERE id = ?");
        $stmt_kelas->bind_param("i", $kelas_id);
        $stmt_kelas->execute();
        $result_kelas = $stmt_kelas->get_result();
        $kelas_data = $result_kelas->fetch_assoc();
        $stmt_kelas->close();
        
        // Ambil data siswa
        if ($kelas_data) {
            $stmt_siswa = $conn->prepare("SELECT * FROM siswa WHERE kelas_id = ? ORDER BY nama");
            $stmt_siswa->bind_param("i", $kelas_id);
            $stmt_siswa->execute();
            $result_siswa = $stmt_siswa->get_result();
            
            while ($row = $result_siswa->fetch_assoc()) {
                $siswa_data[] = $row;
            }
            $stmt_siswa->close();
        }
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sampul Rapor - <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? ''); ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 1cm;
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
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 16pt;
            font-weight: bold;
        }
        
        .header h3 {
            margin: 5px 0;
            font-size: 14pt;
            font-weight: bold;
        }
        
        .header p {
            margin: 3px 0;
            font-size: 11pt;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th,
        table td {
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
            text-align: left;
        }
        
        /* Mengurangi lebar kolom sesuai permintaan - kolom lebih rapi */
        .col-no {
            width: 5%;
            text-align: center;
        }
        
        .col-nisn {
            width: 8%;
            text-align: center;
        }
        
        .col-nama {
            width: 18%;
        }
        
        .col-madrasah {
            width: 69%;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
        }
        
        .no-print {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .no-print button {
            padding: 10px 20px;
            font-size: 14px;
            background-color: #2d5016;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .no-print button:hover {
            background-color: #4a7c2a;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>
    
    <div class="header">
        <h2><?php echo htmlspecialchars($profil_madrasah['nama_madrasah'] ?? 'MI Sultan Fattah Sukosono'); ?></h2>
        <p><?php echo htmlspecialchars($profil_madrasah['alamat'] ?? ''); ?></p>
        <p>Kecamatan <?php echo htmlspecialchars($profil_madrasah['kecamatan'] ?? ''); ?>, 
           <?php echo htmlspecialchars($profil_madrasah['kabupaten'] ?? ''); ?>, 
           <?php echo htmlspecialchars($profil_madrasah['provinsi'] ?? ''); ?></p>
        <h3>DAFTAR SISWA</h3>
        <h3>Kelas: <?php echo htmlspecialchars($kelas_data['nama_kelas'] ?? ''); ?></h3>
        <p>Tahun Pelajaran: <?php echo htmlspecialchars($profil_madrasah['tahun_ajaran_aktif'] ?? ''); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-nisn">NISN</th>
                <th class="col-nama">Nama Siswa</th>
                <th class="col-madrasah">Nama Madrasah</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($siswa_data) > 0): ?>
                <?php 
                $no = 1;
                foreach ($siswa_data as $siswa): 
                    // Skip jika nama kosong atau invalid
                    $nama_siswa = trim($siswa['nama'] ?? '');
                    if (empty($nama_siswa)) {
                        continue;
                    }
                    
                    // Skip jika nama sama dengan Administrator, Admin, atau Proktor
                    $nama_lower = strtolower($nama_siswa);
                    if ($nama_lower === 'administrator' || $nama_lower === 'admin' || $nama_lower === 'proktor') {
                        continue;
                    }
                ?>
                    <tr>
                        <td class="col-no"><?php echo $no++; ?></td>
                        <td class="col-nisn"><?php echo htmlspecialchars($siswa['nisn'] ?? '-'); ?></td>
                        <td class="col-nama"><?php echo htmlspecialchars($nama_siswa); ?></td>
                        <td class="col-madrasah"><?php echo htmlspecialchars($profil_madrasah['nama_madrasah'] ?? 'MI Sultan Fattah Sukosono'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Tidak ada data siswa</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Dicetak pada: <?php echo date('d F Y H:i:s'); ?></p>
    </div>
</body>
</html>

