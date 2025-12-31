# Script PowerShell untuk auto-commit perubahan Git
# Gunakan: .\auto-commit.ps1 "pesan commit"

param(
    [string]$Message = ""
)

# Cek apakah ada perubahan
$hasChanges = git status --porcelain
if (-not $hasChanges) {
    Write-Host "Tidak ada perubahan untuk di-commit" -ForegroundColor Yellow
    exit 0
}

# Jika pesan kosong, gunakan default
if ([string]::IsNullOrWhiteSpace($Message)) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $Message = "Auto commit: $timestamp"
}

# Tambahkan semua perubahan
Write-Host "Menambahkan perubahan..." -ForegroundColor Cyan
git add -A

# Commit dengan pesan
Write-Host "Melakukan commit..." -ForegroundColor Cyan
git commit -m $Message

if ($LASTEXITCODE -eq 0) {
    Write-Host "Berhasil commit: $Message" -ForegroundColor Green
} else {
    Write-Host "Gagal commit" -ForegroundColor Red
    exit 1
}

