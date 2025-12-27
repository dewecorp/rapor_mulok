@echo off
REM Script Git Commit Pintar dengan Pesan Otomatis untuk Windows CMD
REM Penggunaan: smart-commit.bat [pesan manual]

setlocal enabledelayedexpansion

echo === Smart Git Commit Script ===
echo.

REM Cek Git
git --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Git tidak ditemukan!
    pause
    exit /b 1
)

REM Cek repository
if not exist ".git" (
    echo Inisialisasi Git repository...
    git init
)

REM Cek status
echo.
echo Menganalisis perubahan...
git status --short >nul 2>&1
if errorlevel 1 (
    echo Tidak ada perubahan untuk di-commit.
    pause
    exit /b 0
)

echo.
echo Perubahan yang terdeteksi:
git status --short
echo.

REM Cek apakah ada parameter pesan custom
set "CUSTOM_MSG=%~1"
set "USE_CUSTOM=0"
if not "!CUSTOM_MSG!"=="" set USE_CUSTOM=1

REM Analisis file yang diubah
echo Menganalisis tipe perubahan...
echo.

REM Cek file yang diubah untuk menentukan tipe
set "HAS_CONFIG=0"
set "HAS_FIX=0"
set "HAS_FEATURE=0"
set "HAS_STYLE=0"
set "HAS_DOCS=0"

for /f "delims=" %%f in ('git diff --name-only 2^>nul') do (
    set "file=%%f"
    set "file=!file:config=!"
    set "file=!file:database=!"
    if not "!file!"=="%%f" set HAS_CONFIG=1
    
    set "file=%%f"
    set "file=!file:fix=!"
    set "file=!file:bug=!"
    set "file=!file:error=!"
    if not "!file!"=="%%f" set HAS_FIX=1
    
    set "file=%%f"
    set "file=!file:feature=!"
    set "file=!file:fitur=!"
    set "file=!file:add=!"
    set "file=!file:tambah=!"
    if not "!file!"=="%%f" set HAS_FIX=1
)

REM Generate pesan commit
set "COMMIT_TYPE=Update"
set "COMMIT_MSG=Update kode"

if !HAS_FIX!==1 (
    set "COMMIT_TYPE=Fix"
    set "COMMIT_MSG=Perbaiki bug"
) else if !HAS_CONFIG!==1 (
    set "COMMIT_TYPE=Config"
    set "COMMIT_MSG=Update konfigurasi"
) else if !HAS_FEATURE!==1 (
    set "COMMIT_TYPE=Feat"
    set "COMMIT_MSG=Tambah fitur baru"
)

REM Hitung jumlah file
for /f %%a in ('git diff --name-only 2^>nul ^| find /c /v ""') do set FILE_COUNT=%%a
if "!FILE_COUNT!"=="" set FILE_COUNT=0

REM Buat pesan lengkap
set "FULL_MSG=!COMMIT_TYPE!: !COMMIT_MSG!"
if !FILE_COUNT! GTR 0 (
    set "FULL_MSG=!FULL_MSG! (!FILE_COUNT! file)"
)

REM Tambahkan timestamp
for /f "tokens=1-3 delims=/ " %%a in ('date /t') do set DATE=%%c-%%b-%%a
for /f "tokens=1-2 delims=: " %%a in ('time /t') do set TIME=%%a:%%b
set "FULL_MSG=!FULL_MSG! - !DATE! !TIME!"

REM Gunakan custom message jika ada
if !USE_CUSTOM!==1 set "FULL_MSG=!CUSTOM_MSG!"

REM Tampilkan preview
echo Pesan commit yang akan digunakan:
echo   !FULL_MSG!
echo.

REM Menu pilihan
echo Pilihan:
echo   1. Gunakan pesan ini
echo   2. Edit pesan
echo   3. Batal
echo.

set /p CHOICE="Pilih (1-3): "

if "!CHOICE!"=="1" (
    set "FINAL_MSG=!FULL_MSG!"
) else if "!CHOICE!"=="2" (
    set /p FINAL_MSG="Masukkan pesan commit: "
    if "!FINAL_MSG!"=="" (
        echo Pesan tidak boleh kosong!
        pause
        exit /b 1
    )
) else (
    echo Dibatalkan.
    pause
    exit /b 0
)

REM Konfirmasi
echo.
echo Pesan commit final: !FINAL_MSG!
set /p CONFIRM="Lanjutkan commit? (Y/N): "
if /i not "!CONFIRM!"=="Y" (
    echo Dibatalkan.
    pause
    exit /b 0
)

REM Stage dan commit
echo.
echo Menambahkan file ke staging...
git add .

echo Membuat commit...
git commit -m "!FINAL_MSG!"

if errorlevel 1 (
    echo.
    echo ERROR: Gagal membuat commit!
    pause
    exit /b 1
)

echo.
echo Commit berhasil dibuat!
echo.

REM Tampilkan log
echo Commit terakhir:
git log -1 --oneline
echo.

REM Tanya push
set /p PUSH_CONFIRM="Push ke remote repository? (Y/N): "
if /i "!PUSH_CONFIRM!"=="Y" (
    echo.
    echo Push ke remote...
    
    git remote get-url origin >nul 2>&1
    if not errorlevel 1 (
        for /f "delims=" %%b in ('git branch --show-current 2^>nul') do set CURRENT_BRANCH=%%b
        if "!CURRENT_BRANCH!"=="" set CURRENT_BRANCH=master
        
        git push -u origin !CURRENT_BRANCH!
        
        if errorlevel 1 (
            echo Push gagal!
        ) else (
            echo Push berhasil!
        )
    ) else (
        echo Remote belum dikonfigurasi.
        echo Tambahkan dengan: git remote add origin ^<URL^>
    )
)

echo.
echo === Selesai ===
pause

