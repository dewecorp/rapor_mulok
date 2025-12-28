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

REM Cek apakah ada perubahan
git diff --quiet --exit-code
if errorlevel 1 (
    set HAS_CHANGES=1
) else (
    git diff --cached --quiet --exit-code
    if errorlevel 1 (
        set HAS_CHANGES=1
    ) else (
        set HAS_CHANGES=0
    )
)

if "%HAS_CHANGES%"=="0" (
    echo.
    echo Tidak ada perubahan untuk di-commit.
    exit /b 0
)

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
        REM Cek branch yang aktif
        for /f "tokens=2" %%b in ('git branch --show-current') do set CURRENT_BRANCH=%%b
        if "%CURRENT_BRANCH%"=="" set CURRENT_BRANCH=master
        
        echo Push ke branch %CURRENT_BRANCH%...
        git push -u origin %CURRENT_BRANCH%
        if errorlevel 1 (
            echo ERROR: Gagal push ke remote!
        )
    )
)

echo.
echo === Selesai ===
pause


