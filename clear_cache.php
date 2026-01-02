<?php
/**
 * Script untuk Clear Cache Browser
 * Akses file ini melalui browser untuk memaksa clear cache
 */

// Set headers untuk prevent cache
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Clear Cache - Rapor Mulok Digital</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2a 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            color: #333;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #2d5016;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #bee5eb;
            text-align: left;
        }
        .info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .btn {
            background: #2d5016;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #1a3009;
        }
        .timestamp {
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
    <script>
        // Force reload tanpa cache
        if ('caches' in window) {
            caches.keys().then(function(names) {
                for (let name of names) {
                    caches.delete(name);
                }
            });
        }
        
        // Clear localStorage dan sessionStorage
        try {
            localStorage.clear();
            sessionStorage.clear();
        } catch(e) {
            console.log('Error clearing storage:', e);
        }
        
        // Reload setelah 2 detik
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>
</head>
<body>
    <div class="container">
        <h1>🔄 Clear Cache Browser</h1>
        <div class="success">
            <strong>✓ Cache berhasil di-clear!</strong>
        </div>
        <div class="info">
            <strong>Yang sudah dilakukan:</strong>
            <ul>
                <li>HTTP headers untuk prevent cache sudah di-set</li>
                <li>Browser cache akan di-clear otomatis</li>
                <li>localStorage dan sessionStorage sudah di-clear</li>
                <li>Anda akan di-redirect ke halaman utama dalam 2 detik</li>
            </ul>
        </div>
        <p><strong>Jika masih melihat tampilan lama:</strong></p>
        <ol style="text-align: left; display: inline-block;">
            <li>Tekan <kbd>Ctrl + Shift + Delete</kbd> untuk buka dialog clear cache</li>
            <li>Pilih "Cached images and files"</li>
            <li>Pilih "All time"</li>
            <li>Klik "Clear data"</li>
            <li>Atau tekan <kbd>Ctrl + F5</kbd> untuk hard refresh</li>
        </ol>
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">Lanjut ke Dashboard</a>
            <button onclick="location.reload(true)" class="btn">Refresh Sekarang</button>
        </div>
        <div class="timestamp">
            Waktu: <?php echo date('d/m/Y H:i:s'); ?>
        </div>
    </div>
</body>
</html>




