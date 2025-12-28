<?php
// Pastikan tidak ada output sebelum ini
if (ob_get_level() > 0) {
    ob_clean();
}

require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['confirm'])) {
    // Pastikan session aktif sebelum destroy
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
    // Pastikan tidak ada output sebelum redirect
    if (ob_get_level() > 0) {
        ob_clean();
    }
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
Swal.fire({
    title: 'Apakah Anda yakin ingin logout?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#2d5016',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Logout',
    cancelButtonText: 'Batal'
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = 'logout.php?confirm=1';
    } else {
        window.location.href = '<?php echo getRelativePath(); ?>index.php';
    }
});
</script>
</body>
</html>
