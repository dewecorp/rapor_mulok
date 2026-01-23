@echo off
setlocal

:: Konfigurasi
set "REPO_URL=https://github.com/dewecorp/rapor_mulok"
set "BRANCH=master"

:: 1. Cek dan Setup Remote
echo 1. Memeriksa konfigurasi Remote Git...
git remote -v | findstr "origin" >nul
if %errorlevel% equ 0 (
    echo Remote 'origin' sudah ada.
) else (
    echo Remote 'origin' belum ada. Menambahkan...
    git remote add origin %REPO_URL%
    echo Remote berhasil ditambahkan.
)

:: 2. Input Pesan Commit
echo.
echo 2. Persiapan Commit
:INPUT_MSG
set "COMMIT_MSG="
set /p "COMMIT_MSG=Masukkan pesan commit (Wajib diisi): "
if "%COMMIT_MSG%"=="" (
    echo Error: Pesan commit tidak boleh kosong!
    goto INPUT_MSG
)

:: 3. Git Add dan Commit
echo.
echo 3. Menjalankan Git Add dan Commit...
git add .
git commit -m "%COMMIT_MSG%"

:: 4. Git Push
echo.
echo 4. Menjalankan Git Push ke %BRANCH%...
git push -u origin %BRANCH%

:: 5. Buat Backup ZIP
echo.
echo 5. Membuat/Update file ZIP backup...
set "ZIP_FILE=..\rapor_mulok.zip"

:: Menggunakan git archive untuk membuat zip (exclude .git dan file untracked)
git archive --format=zip --output="%ZIP_FILE%" %BRANCH%

if exist "%ZIP_FILE%" (
    echo Backup berhasil dibuat di luar folder proyek: %ZIP_FILE%
) else (
    echo Gagal membuat file zip.
)

echo.
echo Script Selesai.
pause
endlocal
