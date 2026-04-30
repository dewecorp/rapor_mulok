            </div>
        </div>
    </div>
    
    <!-- Cache busting version - gunakan APP_VERSION saja untuk production -->
    <?php 
    // Untuk development, gunakan timestamp. Untuk production, gunakan APP_VERSION saja
    $cache_version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
    // $cache_version = defined('APP_VERSION') ? APP_VERSION . '.' . time() : time(); // Uncomment untuk development
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11?v=<?php echo $cache_version; ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js?v=<?php echo $cache_version; ?>"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js?v=<?php echo $cache_version; ?>"></script>
    
    <!-- Script untuk toggle sidebar di mobile dan desktop -->
    <script>
    // Fungsi untuk toggle sidebar collapsed
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
            // Simpan state ke localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    }
    
    // Load state sidebar dari localStorage saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar) {
            var isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        }
        
        // Toggle sidebar untuk mobile
        var sidebarToggle = document.querySelector('.sidebar-toggle');
        var mainContent = document.querySelector('.main-content');
        var sidebarOverlay = null;
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.toggle('show');
                
                // Tambahkan overlay untuk menutup sidebar saat klik di luar
                if (sidebar.classList.contains('show')) {
                    if (!sidebarOverlay) {
                        sidebarOverlay = document.createElement('div');
                        sidebarOverlay.className = 'sidebar-overlay';
                        sidebarOverlay.style.cssText = 'position: fixed; top: 56px; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none;';
                        document.body.appendChild(sidebarOverlay);
                    }
                    sidebarOverlay.style.display = 'block';
                    
                    sidebarOverlay.addEventListener('click', function() {
                        sidebar.classList.remove('show');
                        sidebarOverlay.style.display = 'none';
                    });
                } else {
                    if (sidebarOverlay) {
                        sidebarOverlay.style.display = 'none';
                    }
                }
            });
        }
        
        // Tutup sidebar saat resize ke desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991 && sidebar) {
                sidebar.classList.remove('show');
                if (sidebarOverlay) {
                    sidebarOverlay.style.display = 'none';
                }
            }
        });
        
        // Script untuk toggle dropdown user
        var userAvatar = document.getElementById('userAvatarDropdown');
        var userDropdown = document.getElementById('userDropdownMenu');
        
        if (userAvatar && userDropdown) {
            // Toggle dropdown saat avatar diklik
            userAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            
            // Tutup dropdown saat klik di luar
            document.addEventListener('click', function(e) {
                if (!userAvatar.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        }
    });
    
    function logout() {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: 'Anda akan logout dari sistem',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2d5016',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?php echo $basePath; ?>logout.php';
            }
        });
    }
    </script>
    
    
    <!-- Script untuk Welcome Alert - sudah dipindahkan ke login.php -->

    
    <script>
        // Fungsi untuk update datetime realtime dengan format Indonesia
        // Hitung selisih waktu server dan client agar jam sesuai dengan server (Asia/Jakarta)
        var serverTime = <?php echo time() * 1000; ?>;
        var clientTime = new Date().getTime();
        var timeOffset = serverTime - clientTime;

        function updateDateTime() {
            // Gunakan waktu sekarang ditambah offset
            var now = new Date(new Date().getTime() + timeOffset);
            
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
            jQuery(document).ready(function() {
                /*
                 * Sidebar: class active / show pada submenu diatur dari server (includes/header.php)
                 * via SCRIPT_NAME, karena href menu memakai path relatif (../) yang tidak cocok dengan
                 * window.location.pathname. Skrip ini hanya menyelaraskan instance Collapse Bootstrap.
                 */
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    document.querySelectorAll('#sidebar .collapse.show').forEach(function(el) {
                        try {
                            bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
                        } catch (ignore) { /* */ }
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
