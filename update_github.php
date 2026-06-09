<?php
/**
 * Script untuk update aplikasi langsung dari GitHub
 * Hanya dapat diakses oleh role Proktor (Admin)
 */

require_once 'config/config.php';
require_once 'config/database.php';

// Proteksi: Hanya proktor yang bisa akses
if (!isLoggedIn() || !hasRole('proktor')) {
    header('Location: index.php');
    exit;
}

$page_title = "Update dari GitHub";
include 'includes/header.php';

// Pastikan base path benar
$base_path = realpath(__DIR__);
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Update dari GitHub</li>
                    </ol>
                </nav>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fab fa-github me-2"></i>Update Aplikasi dari GitHub</h5>
                        <a href="index.php" class="btn btn-sm btn-light text-dark"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
                    </div>
                    <div class="card-body">
                        <?php
                        if (isset($_POST['update'])) {
                            echo "<h6>Log Proses Update:</h6>";
                            echo "<div class='bg-dark text-white p-3 rounded mb-3' style='max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px;'>";
                            
                            // 1. Cek Git
                            echo "<span class='text-info'>[1/3] Memeriksa koneksi Git...</span>\n";
                            $git_version = [];
                            exec('git --version 2>&1', $git_version, $git_status);
                            
                            if ($git_status !== 0) {
                                echo "<span class='text-danger'>Error: Git tidak terdeteksi di server.</span>\n";
                                echo "Pesan: " . implode("\n", $git_version) . "\n";
                                echo "Hubungi admin server untuk mengaktifkan fitur ini.";
                            } else {
                                echo "Git terdeteksi: " . $git_version[0] . "\n\n";
                                
                                // 2. Jalankan Git Pull
                                echo "<span class='text-info'>[2/3] Mengambil pembaruan dari repository...</span>\n";
                                $output = [];
                                $return_var = 0;
                                
                                // Ganti ke directory root aplikasi
                                chdir($base_path);
                                
                                // Jalankan pull
                                exec('git pull origin master 2>&1', $output, $return_var);
                                
                                foreach ($output as $line) {
                                    echo htmlspecialchars($line) . "\n";
                                }
                                
                                if ($return_var === 0) {
                                    echo "\n<span class='text-success'>[OK] Berhasil menarik data dari GitHub.</span>\n\n";
                                    
                                    // 3. Update Database (jika ada migration, tapi di aplikasi ini sepertinya tidak ada framework migration)
                                    // Kita bisa tambahkan logic untuk composer install jika perlu
                                    echo "<span class='text-info'>[3/3] Membersihkan cache aplikasi...</span>\n";
                                    if (file_exists('clear_cache.php')) {
                                        include 'clear_cache.php';
                                        echo "Cache berhasil dibersihkan.\n";
                                    } else {
                                        echo "Skip: Script pembersih cache tidak ditemukan.\n";
                                    }
                                    
                                    echo "\n<h5 class='text-success mt-3'><i class='fas fa-check-circle me-2'></i>UPDATE BERHASIL!</h5>";
                                    echo "Aplikasi Anda sekarang sudah menggunakan versi terbaru.";
                                } else {
                                    echo "\n<span class='text-danger'>[FAIL] Terjadi kesalahan saat menarik data.</span>\n";
                                    echo "Pastikan tidak ada file yang konflik atau disk penuh.";
                                }
                            }
                            
                            echo "</div>";
                            echo "<div class='mt-3'>";
                            echo "<a href='index.php' class='btn btn-primary'><i class='fas fa-home me-2'></i>Kembali ke Dashboard</a> ";
                            echo "<a href='update_github.php' class='btn btn-outline-secondary'><i class='fas fa-sync me-2'></i>Ulangi Proses</a>";
                            echo "</div>";
                        } else {
                        ?>
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="alert alert-info border-0 shadow-sm">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <i class="fas fa-info-circle fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="alert-heading fw-bold">Tentang Fitur Update</h6>
                                                <p class="mb-0">Fitur ini memungkinkan Anda memperbarui kode aplikasi langsung dari repository GitHub tanpa perlu upload file secara manual via FTP atau File Manager.</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <ul class="list-group list-group-flush mb-4">
                                        <li class="list-group-item bg-transparent px-0 py-3">
                                            <i class="fas fa-check text-success me-3"></i>
                                            <strong>Repository:</strong> <a href="https://github.com/dewecorp/rapor_mulok" target="_blank">dewecorp/rapor_mulok</a>
                                        </li>
                                        <li class="list-group-item bg-transparent px-0 py-3">
                                            <i class="fas fa-check text-success me-3"></i>
                                            <strong>Branch:</strong> <code>master</code>
                                        </li>
                                        <li class="list-group-item bg-transparent px-0 py-3">
                                            <i class="fas fa-check text-success me-3"></i>
                                            <strong>Database Aman:</strong> Pengaturan database Anda tidak akan berubah karena menggunakan sistem proteksi file konfigurasi.
                                        </li>
                                    </ul>

                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin melakukan update sekarang? Proses ini akan menimpa file lokal dengan versi terbaru dari GitHub.')">
                                        <button type="submit" name="update" class="btn btn-primary btn-lg px-5 shadow">
                                            <i class="fas fa-cloud-download-alt me-2"></i>Mulai Update Sekarang
                                        </button>
                                    </form>
                                </div>
                                <div class="col-md-4 text-center d-none d-md-block">
                                    <i class="fab fa-github text-muted" style="font-size: 150px; opacity: 0.2;"></i>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="card mt-4 border-warning">
                    <div class="card-body">
                        <h6 class="text-warning fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Peringatan Penting:</h6>
                        <small class="text-muted d-block mt-2">
                            1. Jangan menutup halaman ini saat proses update sedang berjalan.<br>
                            2. Jika terjadi error "Permission Denied", pastikan folder aplikasi memiliki izin tulis (write permission) untuk user web server.<br>
                            3. Sangat disarankan untuk melakukan backup database sebelum melakukan update besar.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tambahkan script tambahan jika diperlukan
</script>

<?php include 'includes/footer.php'; ?>
