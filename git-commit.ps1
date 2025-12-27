# Script Git Commit untuk Windows PowerShell
# Penggunaan: .\git-commit.ps1 "Pesan commit"

param(
    [Parameter(Mandatory=$false)]
    [string]$Message = "Update project"
)

Write-Host "=== Git Commit Script ===" -ForegroundColor Green
Write-Host ""

# Cek apakah Git sudah terinstall
try {
    $gitVersion = git --version
    Write-Host "Git ditemukan: $gitVersion" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Git tidak ditemukan! Silakan install Git terlebih dahulu." -ForegroundColor Red
    exit 1
}

# Cek apakah sudah di dalam repository Git
if (-not (Test-Path .git)) {
    Write-Host "Inisialisasi Git repository..." -ForegroundColor Yellow
    git init
    Write-Host "Git repository berhasil diinisialisasi!" -ForegroundColor Green
}

# Tampilkan status
Write-Host ""
Write-Host "Status repository:" -ForegroundColor Cyan
git status --short

# Tanyakan konfirmasi
Write-Host ""
$confirm = Read-Host "Lanjutkan commit dengan pesan '$Message'? (Y/N)"
if ($confirm -ne "Y" -and $confirm -ne "y") {
    Write-Host "Dibatalkan." -ForegroundColor Yellow
    exit 0
}

# Tambahkan semua file
Write-Host ""
Write-Host "Menambahkan file ke staging..." -ForegroundColor Yellow
git add .

# Commit
Write-Host "Membuat commit..." -ForegroundColor Yellow
git commit -m "$Message"

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "Commit berhasil dibuat!" -ForegroundColor Green
    Write-Host ""
    
    # Tanyakan apakah ingin push
    $pushConfirm = Read-Host "Apakah ingin push ke remote repository? (Y/N)"
    if ($pushConfirm -eq "Y" -or $pushConfirm -eq "y") {
        Write-Host ""
        Write-Host "Mengecek remote repository..." -ForegroundColor Yellow
        
        $remote = git remote get-url origin 2>$null
        if ($remote) {
            Write-Host "Remote ditemukan: $remote" -ForegroundColor Green
            Write-Host "Push ke remote..." -ForegroundColor Yellow
            git push -u origin main
            
            if ($LASTEXITCODE -ne 0) {
                # Coba branch master jika main gagal
                Write-Host "Mencoba push ke branch master..." -ForegroundColor Yellow
                git push -u origin master
            }
        } else {
            Write-Host "Remote repository belum dikonfigurasi." -ForegroundColor Yellow
            Write-Host "Tambahkan remote dengan: git remote add origin <URL>" -ForegroundColor Cyan
        }
    }
} else {
    Write-Host ""
    Write-Host "ERROR: Gagal membuat commit!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=== Selesai ===" -ForegroundColor Green


