@echo off
REM Script Git Commit untuk Windows CMD
REM Penggunaan: git-commit.bat "Pesan commit"

setlocal enabledelayedexpansion

set "COMMIT_MSG=%~1"
if "%COMMIT_MSG%"=="" set "COMMIT_MSG=Update project"

echo === Git Commit Script ===
echo.

REM Cek apakah Git sudah terinstall
git --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Git tidak ditemukan! Silakan install Git terlebih dahulu.
    exit /b 1
)

REM Cek apakah sudah di dalam repository Git
if not exist ".git" (
    echo Inisialisasi Git repository...
    git init
    echo Git repository berhasil diinisialisasi!
)

REM Tampilkan status
echo.
echo Status repository:
git status --short

REM Tanyakan konfirmasi
echo.
set /p CONFIRM="Lanjutkan commit dengan pesan '%COMMIT_MSG%'? (Y/N): "
if /i not "%CONFIRM%"=="Y" (
    echo Dibatalkan.
    exit /b 0
)

REM Tambahkan semua file
echo.
echo Menambahkan file ke staging...
git add .

REM Commit
echo Membuat commit...
git commit -m "%COMMIT_MSG%"

if errorlevel 1 (
    echo.
    echo ERROR: Gagal membuat commit!
    exit /b 1
)

echo.
echo Commit berhasil dibuat!
echo.

REM Tanyakan apakah ingin push
set /p PUSH_CONFIRM="Apakah ingin push ke remote repository? (Y/N): "
if /i "%PUSH_CONFIRM%"=="Y" (
    echo.
    echo Mengecek remote repository...
    
    git remote get-url origin >nul 2>&1
    if errorlevel 1 (
        echo Remote repository belum dikonfigurasi.
        echo Tambahkan remote dengan: git remote add origin ^<URL^>
    ) else (
        git remote get-url origin
        echo Push ke remote...
        git push -u origin main
        if errorlevel 1 (
            echo Mencoba push ke branch master...
            git push -u origin master
        )
    )
)

echo.
echo === Selesai ===
pause

