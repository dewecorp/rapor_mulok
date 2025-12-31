# Script PowerShell untuk watch perubahan file dan auto-commit
# Gunakan: .\watch-and-commit.ps1

$ErrorActionPreference = "Stop"

Write-Host "=== Git Auto-Commit Watcher ===" -ForegroundColor Cyan
Write-Host "Menunggu perubahan file..." -ForegroundColor Yellow
Write-Host "Tekan Ctrl+C untuk berhenti" -ForegroundColor Yellow
Write-Host ""

$lastCommit = Get-Date
$commitDelay = 30 # Detik delay sebelum commit (untuk menghindari commit terlalu sering)

while ($true) {
    Start-Sleep -Seconds 5
    
    # Cek apakah ada perubahan
    $hasChanges = git status --porcelain
    if ($hasChanges) {
        $timeSinceLastCommit = (Get-Date) - $lastCommit
        
        if ($timeSinceLastCommit.TotalSeconds -ge $commitDelay) {
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            $changedFiles = git diff --name-only | Measure-Object -Line
            $fileCount = $changedFiles.Lines
            
            Write-Host "[$timestamp] Ditemukan $fileCount file berubah" -ForegroundColor Cyan
            
            # Tambahkan semua perubahan
            git add -A
            
            # Buat pesan commit berdasarkan file yang berubah
            $files = git diff --cached --name-only | Select-Object -First 5
            $fileList = $files -join ", "
            if ($files.Count -gt 5) {
                $fileList += " dan lainnya"
            }
            
            $commitMsg = "Auto commit: $fileList"
            
            # Commit
            git commit -m $commitMsg
            
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] Berhasil commit: $commitMsg" -ForegroundColor Green
                $lastCommit = Get-Date
            } else {
                Write-Host "[$timestamp] Gagal commit" -ForegroundColor Red
            }
        }
    }
}

