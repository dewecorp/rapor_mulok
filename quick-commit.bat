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
    echo Push ke remote...
    git push -u origin main 2>nul || git push -u origin master 2>nul
)

echo Selesai!

