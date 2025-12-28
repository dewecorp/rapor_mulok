@echo off
REM Quick Git Commit - Tanpa konfirmasi
REM Penggunaan: quick-commit.bat "Pesan commit"

setlocal

set "COMMIT_MSG=%~1"
if "%COMMIT_MSG%"=="" set "COMMIT_MSG=Update project"

echo Quick Git Commit...
echo.

if not exist ".git" (
    git init
)

REM Cek apakah ada perubahan
git diff --quiet --exit-code 2>nul
if errorlevel 1 (
    set HAS_CHANGES=1
) else (
    git diff --cached --quiet --exit-code 2>nul
    if errorlevel 1 (
        set HAS_CHANGES=1
    ) else (
        set HAS_CHANGES=0
    )
)

if "%HAS_CHANGES%"=="0" (
    echo Tidak ada perubahan untuk di-commit.
    exit /b 0
)

git add .
git commit -m "%COMMIT_MSG%"

if errorlevel 1 (
    echo ERROR: Gagal commit!
    pause
    exit /b 1
)

echo.
echo Commit berhasil: %COMMIT_MSG%
echo.

REM Cek remote dan push otomatis jika ada
git remote get-url origin >nul 2>&1
if not errorlevel 1 (
    REM Cek branch yang aktif
    for /f "tokens=2" %%b in ('git branch --show-current') do set CURRENT_BRANCH=%%b
    if "%CURRENT_BRANCH%"=="" set CURRENT_BRANCH=master
    
    echo Push ke remote (%CURRENT_BRANCH%)...
    git push -u origin %CURRENT_BRANCH% 2>nul
    if errorlevel 1 (
        echo Warning: Gagal push ke remote (mungkin belum ada remote atau perlu konfigurasi)
    )
)

echo Selesai!


