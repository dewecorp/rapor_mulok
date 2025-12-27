# Script Git Commit Pintar dengan Pesan Otomatis
# Penggunaan: .\smart-commit.ps1 [pesan manual]
# Atau: .\smart-commit.ps1 (akan generate pesan otomatis)

param(
    [Parameter(Mandatory=$false)]
    [string]$CustomMessage = ""
)

Write-Host "=== Smart Git Commit Script ===" -ForegroundColor Cyan
Write-Host ""

# Cek Git
try {
    $gitVersion = git --version
    Write-Host "Git: $gitVersion" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Git tidak ditemukan!" -ForegroundColor Red
    exit 1
}

# Cek repository
if (-not (Test-Path .git)) {
    Write-Host "Inisialisasi Git repository..." -ForegroundColor Yellow
    git init
}

# Cek status
Write-Host ""
Write-Host "Menganalisis perubahan..." -ForegroundColor Yellow
$status = git status --short

if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "Tidak ada perubahan untuk di-commit." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "Perubahan yang terdeteksi:" -ForegroundColor Cyan
git status --short

# Analisis perubahan untuk generate pesan
Write-Host ""
Write-Host "Menganalisis tipe perubahan..." -ForegroundColor Yellow

$modifiedFiles = git diff --cached --name-status 2>$null
if ([string]::IsNullOrWhiteSpace($modifiedFiles)) {
    $modifiedFiles = git diff --name-status
}

$newFiles = ($modifiedFiles | Select-String -Pattern "^A" | Measure-Object).Count
$deletedFiles = ($modifiedFiles | Select-String -Pattern "^D" | Measure-Object).Count
$modifiedFilesCount = ($modifiedFiles | Select-String -Pattern "^M" | Measure-Object).Count
$renamedFiles = ($modifiedFiles | Select-String -Pattern "^R" | Measure-Object).Count

# Cek file yang diubah untuk menentukan tipe commit
$changedFiles = git diff --name-only 2>$null
if ([string]::IsNullOrWhiteSpace($changedFiles)) {
    $changedFiles = git diff --cached --name-only 2>$null
}

$hasConfig = $false
$hasFix = $false
$hasFeature = $false
$hasStyle = $false
$hasDocs = $false
$hasRefactor = $false
$hasTest = $false

foreach ($file in $changedFiles) {
    $fileName = $file.ToLower()
    
    if ($fileName -match "config|database|\.env|settings") {
        $hasConfig = $true
    }
    if ($fileName -match "fix|bug|error|issue|perbaiki") {
        $hasFix = $true
    }
    if ($fileName -match "feature|fitur|add|tambah|new") {
        $hasFeature = $true
    }
    if ($fileName -match "style|css|design|ui|theme") {
        $hasStyle = $true
    }
    if ($fileName -match "readme|doc|\.md|changelog") {
        $hasDocs = $true
    }
    if ($fileName -match "refactor|restructure|optimize") {
        $hasRefactor = $true
    }
    if ($fileName -match "test|spec") {
        $hasTest = $true
    }
}

# Generate pesan commit otomatis
$commitType = ""
$commitScope = ""
$commitMessage = ""

if ([string]::IsNullOrWhiteSpace($CustomMessage)) {
    # Tentukan tipe commit
    if ($hasFix) {
        $commitType = "Fix"
        $commitMessage = "Perbaiki bug"
    } elseif ($hasConfig) {
        $commitType = "Config"
        $commitMessage = "Update konfigurasi"
    } elseif ($hasFeature) {
        $commitType = "Feat"
        $commitMessage = "Tambah fitur baru"
    } elseif ($hasStyle) {
        $commitType = "Style"
        $commitMessage = "Perbaiki styling"
    } elseif ($hasDocs) {
        $commitType = "Docs"
        $commitMessage = "Update dokumentasi"
    } elseif ($hasRefactor) {
        $commitType = "Refactor"
        $commitMessage = "Refactor kode"
    } elseif ($hasTest) {
        $commitType = "Test"
        $commitMessage = "Tambah/update test"
    } else {
        $commitType = "Update"
        $commitMessage = "Update kode"
    }
    
    # Tentukan scope berdasarkan file yang diubah
    $mainFiles = @()
    foreach ($file in $changedFiles) {
        $dir = Split-Path -Parent $file
        if ($dir -and $dir -ne ".") {
            $mainFiles += $dir.Split([IO.Path]::DirectorySeparatorChar)[0]
        }
    }
    
    if ($mainFiles.Count -gt 0) {
        $mostCommon = ($mainFiles | Group-Object | Sort-Object Count -Descending | Select-Object -First 1).Name
        if ($mostCommon) {
            $commitScope = $mostCommon
        }
    }
    
    # Tambahkan detail berdasarkan jumlah file
    $detail = ""
    if ($newFiles -gt 0) {
        $detail += "tambah $newFiles file baru"
    }
    if ($deletedFiles -gt 0) {
        if ($detail) { $detail += ", " }
        $detail += "hapus $deletedFiles file"
    }
    if ($modifiedFilesCount -gt 0) {
        if ($detail) { $detail += ", " }
        $detail += "ubah $modifiedFilesCount file"
    }
    
    # Buat pesan lengkap
    if ($commitScope) {
        $fullMessage = "$commitType($commitScope): $commitMessage"
    } else {
        $fullMessage = "$commitType: $commitMessage"
    }
    
    if ($detail) {
        $fullMessage += " ($detail)"
    }
    
    # Tambahkan timestamp untuk membuat unik
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm"
    $fullMessage += " - $timestamp"
    
    $commitMessage = $fullMessage
} else {
    $commitMessage = $CustomMessage
}

# Tampilkan preview
Write-Host ""
Write-Host "Pesan commit yang akan digunakan:" -ForegroundColor Cyan
Write-Host "  $commitMessage" -ForegroundColor Yellow
Write-Host ""

# Menu pilihan
Write-Host "Pilihan:" -ForegroundColor Cyan
Write-Host "  1. Gunakan pesan ini (otomatis)" -ForegroundColor White
Write-Host "  2. Edit pesan" -ForegroundColor White
Write-Host "  3. Pilih tipe commit manual" -ForegroundColor White
Write-Host "  4. Batal" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Pilih (1-4)"

switch ($choice) {
    "1" {
        # Gunakan pesan otomatis
        $finalMessage = $commitMessage
    }
    "2" {
        # Edit pesan
        $finalMessage = Read-Host "Masukkan pesan commit"
        if ([string]::IsNullOrWhiteSpace($finalMessage)) {
            Write-Host "Pesan tidak boleh kosong!" -ForegroundColor Red
            exit 1
        }
    }
    "3" {
        # Pilih tipe manual
        Write-Host ""
        Write-Host "Tipe commit:" -ForegroundColor Cyan
        Write-Host "  1. Feat - Fitur baru" -ForegroundColor White
        Write-Host "  2. Fix - Perbaikan bug" -ForegroundColor White
        Write-Host "  3. Update - Update kode" -ForegroundColor White
        Write-Host "  4. Refactor - Refactor kode" -ForegroundColor White
        Write-Host "  5. Style - Perbaikan styling" -ForegroundColor White
        Write-Host "  6. Docs - Dokumentasi" -ForegroundColor White
        Write-Host "  7. Config - Konfigurasi" -ForegroundColor White
        Write-Host "  8. Test - Test" -ForegroundColor White
        Write-Host ""
        
        $typeChoice = Read-Host "Pilih tipe (1-8)"
        $typeMap = @{
            "1" = "Feat"
            "2" = "Fix"
            "3" = "Update"
            "4" = "Refactor"
            "5" = "Style"
            "6" = "Docs"
            "7" = "Config"
            "8" = "Test"
        }
        
        $selectedType = $typeMap[$typeChoice]
        if (-not $selectedType) {
            Write-Host "Pilihan tidak valid!" -ForegroundColor Red
            exit 1
        }
        
        $scope = Read-Host "Scope (opsional, tekan Enter untuk skip)"
        $desc = Read-Host "Deskripsi"
        
        if ($scope) {
            $finalMessage = "$selectedType($scope): $desc"
        } else {
            $finalMessage = "$selectedType: $desc"
        }
    }
    "4" {
        Write-Host "Dibatalkan." -ForegroundColor Yellow
        exit 0
    }
    default {
        Write-Host "Pilihan tidak valid!" -ForegroundColor Red
        exit 1
    }
}

# Konfirmasi
Write-Host ""
Write-Host "Pesan commit final: $finalMessage" -ForegroundColor Green
$confirm = Read-Host "Lanjutkan commit? (Y/N)"

if ($confirm -ne "Y" -and $confirm -ne "y") {
    Write-Host "Dibatalkan." -ForegroundColor Yellow
    exit 0
}

# Stage files
Write-Host ""
Write-Host "Menambahkan file ke staging..." -ForegroundColor Yellow
git add .

# Commit
Write-Host "Membuat commit..." -ForegroundColor Yellow
git commit -m "$finalMessage"

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✓ Commit berhasil dibuat!" -ForegroundColor Green
    Write-Host ""
    
    # Tampilkan log commit terakhir
    Write-Host "Commit terakhir:" -ForegroundColor Cyan
    git log -1 --oneline
    
    Write-Host ""
    $pushConfirm = Read-Host "Push ke remote repository? (Y/N)"
    if ($pushConfirm -eq "Y" -or $pushConfirm -eq "y") {
        Write-Host ""
        Write-Host "Push ke remote..." -ForegroundColor Yellow
        
        $remote = git remote get-url origin 2>$null
        if ($remote) {
            Write-Host "Remote: $remote" -ForegroundColor Green
            
            # Cek branch saat ini
            $currentBranch = git branch --show-current
            if (-not $currentBranch) {
                $currentBranch = "master"
            }
            
            git push -u origin $currentBranch
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "✓ Push berhasil!" -ForegroundColor Green
            } else {
                Write-Host "✗ Push gagal!" -ForegroundColor Red
            }
        } else {
            Write-Host "Remote belum dikonfigurasi." -ForegroundColor Yellow
            Write-Host "Tambahkan dengan: git remote add origin <URL>" -ForegroundColor Cyan
        }
    }
} else {
    Write-Host ""
    Write-Host "✗ ERROR: Gagal membuat commit!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=== Selesai ===" -ForegroundColor Green

