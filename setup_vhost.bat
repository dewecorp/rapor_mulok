@echo off
echo ========================================
echo Setup Virtual Host untuk Rapor Mulok
echo ========================================
echo.

REM Cek apakah running sebagai administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Script harus dijalankan sebagai Administrator!
    echo Klik kanan file ini dan pilih "Run as administrator"
    pause
    exit /b 1
)

echo [1/3] Menambahkan entry ke hosts file...
echo 127.0.0.1    rapor-mulok.test >> C:\Windows\System32\drivers\etc\hosts
if %errorLevel% equ 0 (
    echo ✓ Entry berhasil ditambahkan ke hosts file
) else (
    echo ✗ Gagal menambahkan entry ke hosts file
    pause
    exit /b 1
)

echo.
echo [2/3] Membuat konfigurasi virtual host...
set VHOST_FILE=H:\laragon\bin\apache\apache-2.4.65\conf\extra\httpd-vhosts.conf

REM Cek apakah file ada
if not exist "%VHOST_FILE%" (
    echo File httpd-vhosts.conf tidak ditemukan di lokasi default
    echo Silakan edit manual sesuai lokasi Apache di Laragon Anda
    echo Lokasi biasanya: H:\laragon\bin\apache\apache-[version]\conf\extra\httpd-vhosts.conf
    pause
    exit /b 1
)

REM Cek apakah sudah ada konfigurasi
findstr /C:"rapor-mulok.test" "%VHOST_FILE%" >nul 2>&1
if %errorLevel% equ 0 (
    echo ✓ Konfigurasi virtual host sudah ada
) else (
    echo. >> "%VHOST_FILE%"
    echo # Virtual Host untuk Rapor Mulok Khusus >> "%VHOST_FILE%"
    echo ^<VirtualHost *:80^> >> "%VHOST_FILE%"
    echo     ServerName rapor-mulok.test >> "%VHOST_FILE%"
    echo     DocumentRoot "H:/laragon/www/rapor-mulok" >> "%VHOST_FILE%"
    echo     ^<Directory "H:/laragon/www/rapor-mulok"^> >> "%VHOST_FILE%"
    echo         Options Indexes FollowSymLinks >> "%VHOST_FILE%"
    echo         AllowOverride All >> "%VHOST_FILE%"
    echo         Require all granted >> "%VHOST_FILE%"
    echo     ^</Directory^> >> "%VHOST_FILE%"
    echo ^</VirtualHost^> >> "%VHOST_FILE%"
    echo ✓ Konfigurasi virtual host berhasil ditambahkan
)

echo.
echo [3/3] Selesai!
echo.
echo Langkah selanjutnya:
echo 1. Restart Apache di Laragon (Stop lalu Start)
echo 2. Buka browser dan akses: http://rapor-mulok.test/
echo.
echo ATAU gunakan localhost (lebih mudah):
echo http://localhost/rapor-mulok/
echo.
pause

