            </div>
        </div>
    </div>
    
    <!-- Cache busting version - menggunakan timestamp untuk force refresh -->
    <?php 
    // Gunakan timestamp yang selalu berubah untuk memaksa browser reload
    $cache_version = defined('APP_VERSION') ? APP_VERSION . '.' . time() : time();
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js?v=<?php echo $cache_version; ?>"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js?v=<?php echo $cache_version; ?>"></script>
    
    <!-- Script untuk Welcome Alert - dieksekusi setelah semua library dimuat -->
    <?php if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome']): ?>
        <?php
        $welcome_name = $_SESSION['welcome_name'] ?? 'Pengguna';
        $welcome_role = $_SESSION['welcome_role'] ?? '';
        $role_text = '';
        switch($welcome_role) {
            case 'proktor':
                $role_text = 'Administrator';
                break;
            case 'wali_kelas':
                $role_text = 'Wali Kelas';
                break;
            case 'guru':
                $role_text = 'Guru';
                break;
            default:
                $role_text = ucfirst($welcome_role);
        }
        // Hapus session variable setelah digunakan
        unset($_SESSION['show_welcome']);
        unset($_SESSION['welcome_name']);
        unset($_SESSION['welcome_role']);
        ?>
        <script>
            // Simpan data ke variabel JavaScript sebelum session dihapus
            var welcomeName = '<?php echo htmlspecialchars($welcome_name, ENT_QUOTES); ?>';
            var welcomeRole = '<?php echo htmlspecialchars($role_text, ENT_QUOTES); ?>';
            
            console.log('Welcome alert script loaded:', welcomeName, welcomeRole);
            
            // Tampilkan welcome alert setelah semua library dimuat
            function showWelcomeAlert() {
                console.log('showWelcomeAlert called, Swal available:', typeof Swal !== 'undefined');
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    console.log('Showing welcome alert');
                    Swal.fire({
                        icon: 'success',
                        title: 'Selamat Datang!',
                        html: '<strong>' + welcomeName + '</strong><br><small>' + welcomeRole + '</small>',
                        confirmButtonColor: '#2d5016',
                        confirmButtonText: 'Mulai',
                        timer: 4000,
                        timerProgressBar: true,
                        showConfirmButton: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function() {
                            if (document.body) {
                                document.body.style.overflow = 'hidden';
                            }
                        },
                        willClose: function() {
                            if (document.body) {
                                document.body.style.overflow = '';
                            }
                        }
                    });
                } else {
                    // Jika SweetAlert belum tersedia, coba lagi setelah 50ms
                    setTimeout(showWelcomeAlert, 50);
                }
            }
            
            // Tunggu jQuery dan SweetAlert2 dimuat
            if (typeof jQuery !== 'undefined') {
                $(document).ready(function() {
                    setTimeout(showWelcomeAlert, 50);
                });
            } else {
                // Jika jQuery belum tersedia, tunggu window load
                window.addEventListener('load', function() {
                    setTimeout(showWelcomeAlert, 50);
                });
            }
        </script>
    <?php endif; ?>
    
    <script>
        // Fungsi untuk update datetime realtime dengan format Indonesia
        function updateDateTime() {
            var now = new Date();
            var hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            var bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            var hariNama = hari[now.getDay()];
            var tanggal = now.getDate();
            var bulanNama = bulan[now.getMonth()];
            var tahun = now.getFullYear();
            
            // Format waktu dengan leading zero
            var jam = String(now.getHours()).padStart(2, '0');
            var menit = String(now.getMinutes()).padStart(2, '0');
            var detik = String(now.getSeconds()).padStart(2, '0');
            
            var datetimeString = hariNama + ', ' + tanggal + ' ' + bulanNama + ' ' + tahun + ' | ' + jam + ':' + menit + ':' + detik;
            
            var datetimeElement = document.getElementById('datetime');
            if (datetimeElement) {
                datetimeElement.textContent = datetimeString;
            }
        }
        
        // Update datetime saat halaman dimuat
        updateDateTime();
        
        // Update datetime setiap detik (realtime)
        setInterval(updateDateTime, 1000);
        
        // Dropdown untuk proktor logo
        document.addEventListener('DOMContentLoaded', function() {
            var proktorLogoBtn = document.getElementById('proktorLogoBtn');
            var proktorDropdownMenu = document.getElementById('proktorDropdownMenu');
            
            if (proktorLogoBtn && proktorDropdownMenu) {
                // Toggle dropdown saat logo diklik
                proktorLogoBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    proktorDropdownMenu.classList.toggle('show');
                });
                
                // Tutup dropdown saat klik di luar
                document.addEventListener('click', function(e) {
                    if (!proktorLogoBtn.contains(e.target) && !proktorDropdownMenu.contains(e.target)) {
                        proktorDropdownMenu.classList.remove('show');
                    }
                });
            }
        });
        
        if (typeof toastr !== 'undefined') {
            toastr.options = {"closeButton":true,"debug":false,"newestOnTop":true,"progressBar":true,"positionClass":"toast-top-right","preventDuplicates":false,"onclick":null,"showDuration":"300","hideDuration":"1000","timeOut":"5005","extendedTimeOut":"1000","showEasing":"swing","hideEasing":"linear","showMethod":"fadeIn","hideMethod":"fadeOut"};
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                var path = window.location.pathname;
                var url = window.location.href;
                
                // Reset semua active dan collapse - PASTIKAN SEMUA TERTUTUP
                $('.nav-link').removeClass('active');
                $('.collapse').removeClass('show');
                
                // Cek apakah di dashboard (root/index.php)
                var pathParts = path.split('/').filter(function(p) { return p.length > 0 && p !== 'rapor-mulok'; });
                // Dashboard adalah ketika path berakhir dengan index.php di root atau tidak ada path sama sekali
                var lastPart = pathParts[pathParts.length - 1];
                var isDashboard = pathParts.length === 0 || 
                                 (pathParts.length === 1 && lastPart === 'index.php') ||
                                 (pathParts.length === 1 && lastPart === 'rapor-mulok');
                
                if (isDashboard) {
                    // Aktifkan menu Dashboard saja, pastikan semua collapse tertutup
                    $('.sidebar .nav-link').each(function() {
                        var $link = $(this);
                        var href = $link.attr('href');
                        if (href) {
                            // Normalisasi href untuk perbandingan
                            var normalizedHref = href.split('?')[0].replace(/\/$/, '');
                            var hrefParts = normalizedHref.split('/').filter(function(p) { return p.length > 0 && p !== 'rapor-mulok'; });
                            var hrefFile = hrefParts[hrefParts.length - 1];
                            
                            // Jika href adalah index.php di root (tidak ada subdirectory sebelum index.php)
                            if (hrefFile === 'index.php' && hrefParts.length === 1) {
                                $link.addClass('active');
                                // Force apply style dengan inline style sebagai backup
                                $link.css({
                                    'background': 'linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%)',
                                    'border-left': '4px solid #ffffff',
                                    'color': '#ffffff',
                                    'font-weight': '600',
                                    'box-shadow': '0 4px 12px rgba(45, 80, 22, 0.4)',
                                    'transform': 'translateX(5px)'
                                });
                                $link.find('i').css('color', '#ffffff');
                                console.log('Dashboard menu aktif:', href);
                            }
                        }
                    });
                } else {
                    // Normalisasi path untuk perbandingan
                    var normalizedPath = path.replace(/\/$/, ''); // Hapus trailing slash
                    var normalizedUrl = url.split('?')[0].replace(/\/$/, ''); // Hapus query string dan trailing slash
                    
                    // Set active berdasarkan halaman saat ini
                    $('.sidebar .nav-link').each(function() {
                        var $link = $(this);
                        var href = $link.attr('href');
                        
                        if (href && href !== '#' && href !== 'javascript:void(0);') {
                            // Normalisasi href
                            var normalizedHref = href.split('?')[0].replace(/\/$/, '');
                            
                            // Cek apakah path saat ini cocok dengan href
                            var isMatch = false;
                            
                            // Metode 1: Exact match setelah normalisasi
                            if (normalizedPath === normalizedHref || normalizedUrl === normalizedHref) {
                                isMatch = true;
                            }
                            
                            // Metode 2: Path contains href (untuk subdirectory)
                            if (!isMatch && (normalizedPath.indexOf(normalizedHref) !== -1 || normalizedUrl.indexOf(normalizedHref) !== -1)) {
                                // Pastikan bukan partial match yang salah
                                var hrefParts = normalizedHref.split('/');
                                var pathParts = normalizedPath.split('/');
                                var urlParts = normalizedUrl.split('/');
                                
                                // Cek apakah semua bagian href ada di path
                                var allPartsMatch = true;
                                for (var i = 0; i < hrefParts.length; i++) {
                                    if (hrefParts[i] && (pathParts.indexOf(hrefParts[i]) === -1 && urlParts.indexOf(hrefParts[i]) === -1)) {
                                        allPartsMatch = false;
                                        break;
                                    }
                                }
                                
                                if (allPartsMatch) {
                                    isMatch = true;
                                }
                            }
                            
                            // Metode 3: Cek filename saja (untuk file di direktori berbeda)
                            if (!isMatch) {
                                var hrefFile = normalizedHref.split('/').pop();
                                var pathFile = normalizedPath.split('/').pop();
                                var urlFile = normalizedUrl.split('/').pop();
                                
                                if (hrefFile && (hrefFile === pathFile || hrefFile === urlFile)) {
                                    isMatch = true;
                                }
                            }
                            
                            if (isMatch) {
                                $link.addClass('active');
                                console.log('Menu aktif:', href);
                                
                                // Buka parent collapse jika ada
                                var parentCollapse = $link.closest('.collapse');
                                if (parentCollapse.length) {
                                    parentCollapse.addClass('show');
                                    
                                    // Cari parent menu berdasarkan data-bs-target
                                    var collapseId = parentCollapse.attr('id');
                                    if (collapseId) {
                                        var targetSelector = '[data-bs-target="#' + collapseId + '"]';
                                        var parentMenu = $(targetSelector);
                                        if (parentMenu.length) {
                                            parentMenu.addClass('has-active-child');
                                            console.log('Parent menu dengan child aktif:', collapseId);
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    // Tambahkan style khusus untuk parent menu yang collapse terbuka dan memiliki child aktif
                    $('.sidebar .collapse.show').each(function() {
                        var collapseId = $(this).attr('id');
                        if (collapseId) {
                            var parentMenu = $('[data-bs-target="#' + collapseId + '"]');
                            if (parentMenu.length && $(this).find('.nav-link.active').length > 0) {
                                parentMenu.addClass('has-active-child');
                                console.log('Parent menu dengan child aktif (dari collapse.show):', collapseId);
                            }
                        }
                    });
                }
            });
        }
        
    </script>
    
    <!-- Footer -->
    <footer class="footer mt-auto py-3" style="background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%); color: white; margin-top: auto;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0" style="font-size: 12px; margin: 0;">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - 
                        <?php 
                        // Ambil nama madrasah dari profil jika ada
                        $nama_madrasah = 'MI Sultan Fattah Sukosono';
                        try {
                            $conn = getConnection();
                            $query_profil = "SELECT nama_madrasah FROM profil_madrasah LIMIT 1";
                            $result_profil = $conn->query($query_profil);
                            if ($result_profil && $result_profil->num_rows > 0) {
                                $profil = $result_profil->fetch_assoc();
                                $nama_madrasah = $profil['nama_madrasah'] ?? 'MI Sultan Fattah Sukosono';
                            }
                        } catch (Exception $e) {
                            // Gunakan default
                        }
                        ?>
                        <a href="https://misultanfattah.sch.id/" target="_blank" rel="noopener noreferrer" style="color: white; text-decoration: none;"><?php echo htmlspecialchars($nama_madrasah); ?></a>
                    </p>
                    <p class="mb-0 mt-1" style="font-size: 11px; opacity: 0.9; margin: 0;">
                        Dikembangkan oleh Tim IT MI Sultan Fattah Sukosono
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <style>
        html, body {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .content-wrapper {
            flex: 1;
        }
        
        .footer {
            width: 100%;
            margin-top: auto;
        }
    </style>
</body>
</html>
