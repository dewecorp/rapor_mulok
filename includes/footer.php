            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    
    <script>
        if (typeof toastr !== 'undefined') {
            toastr.options = {"closeButton":true,"debug":false,"newestOnTop":true,"progressBar":true,"positionClass":"toast-top-right","preventDuplicates":false,"onclick":null,"showDuration":"300","hideDuration":"1000","timeOut":"5000","extendedTimeOut":"1000","showEasing":"swing","hideEasing":"linear","showMethod":"fadeIn","hideMethod":"fadeOut"};
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                var path = window.location.pathname;
                var url = window.location.href;
                
                // Reset semua active dan collapse - PASTIKAN SEMUA TERTUTUP
                $('.nav-link').removeClass('active');
                $('.collapse').removeClass('show');
                
                // Cek apakah di dashboard (root/index.php)
                var pathParts = path.split('/').filter(function(p) { return p.length > 0; });
                var isDashboard = pathParts.length === 0 || 
                                 (pathParts.length === 1 && pathParts[0] === 'rapor-mulok') ||
                                 (pathParts.length === 1 && pathParts[pathParts.length - 1] === 'index.php') ||
                                 (pathParts.length === 2 && pathParts[pathParts.length - 1] === 'index.php' && pathParts[pathParts.length - 2] === 'rapor-mulok');
                
                if (isDashboard) {
                    // Aktifkan menu Dashboard saja, pastikan semua collapse tertutup
                    $('.nav-link').each(function() {
                        var href = $(this).attr('href');
                        if (href) {
                            var hrefParts = href.split('/').filter(function(p) { return p.length > 0; });
                            var hrefFile = hrefParts[hrefParts.length - 1];
                            
                            // Jika href adalah index.php dan tidak ada subdirectory
                            if (hrefFile === 'index.php' && hrefParts.length <= 2) {
                                $(this).addClass('active');
                            }
                        }
                    });
                } else {
                    // Set active berdasarkan halaman saat ini
                    $('.nav-link').each(function() {
                        var href = $(this).attr('href');
                        if (href && href !== '#' && href !== 'javascript:void(0);') {
                            // Check if current path matches href
                            if (path.indexOf(href) !== -1 || url.indexOf(href) !== -1) {
                                $(this).addClass('active');
                                // Buka parent collapse jika ada
                                var parentCollapse = $(this).closest('.collapse');
                                if (parentCollapse.length) {
                                    parentCollapse.addClass('show');
                                }
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
