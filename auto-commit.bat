@echo off
REM Script untuk auto-commit perubahan Git
REM Gunakan: auto-commit.bat "pesan commit"

setlocal

REM Cek apakah ada perubahan
git status --porcelain >nul 2>&1
if %errorlevel% neq 0 (
    echo Git tidak tersedia atau bukan repository Git
    exit /b 1
)

REM Cek apakah ada perubahan yang belum di-commit
git diff --quiet && git diff --cached --quiet
if %errorlevel% equ 0 (
    echo Tidak ada perubahan untuk di-commit
    exit /b 0
)

REM Ambil pesan commit dari parameter atau gunakan default
set "commit_msg=%~1"
if "%commit_msg%"=="" set "commit_msg=Auto commit: %date% %time%"

REM Tambahkan semua perubahan
git add -A

REM Commit dengan pesan
git commit -m "%commit_msg%"

if %errorlevel% equ 0 (
    echo Berhasil commit: %commit_msg%
) else (
    echo Gagal commit
    exit /b 1
)

endlocal

