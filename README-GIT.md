# Panduan Git untuk Proyek Rapor Mulok

## Instalasi Git

Jika belum memiliki Git, download dan install dari:
- **Windows**: https://git-scm.com/download/win
- **Mac**: https://git-scm.com/download/mac
- **Linux**: `sudo apt-get install git` (Ubuntu/Debian) atau `sudo yum install git` (CentOS/RHEL)

## Setup Awal

### 1. Inisialisasi Repository (jika belum ada)

```bash
git init
```

### 2. Konfigurasi Git (jika belum pernah setup)

```bash
git config --global user.name "Nama Anda"
git config --global user.email "email@example.com"
```

### 3. Tambahkan Remote Repository (opsional)

Jika sudah membuat repository di GitHub/GitLab/Bitbucket:

```bash
git remote add origin https://github.com/username/rapor-mulok.git
```

## Menggunakan Script Commit

### Windows PowerShell

```powershell
# Commit dengan pesan default
.\git-commit.ps1

# Commit dengan pesan custom
.\git-commit.ps1 "Menambahkan fitur baru"
```

### Windows CMD

```cmd
REM Commit dengan pesan default
git-commit.bat

REM Commit dengan pesan custom
git-commit.bat "Menambahkan fitur baru"
```

### Linux/Mac

```bash
# Berikan permission execute
chmod +x git-commit.sh

# Commit dengan pesan default
./git-commit.sh

# Commit dengan pesan custom
./git-commit.sh "Menambahkan fitur baru"
```

## Manual Git Commands

### Menambahkan file ke staging

```bash
# Tambahkan semua file
git add .

# Tambahkan file tertentu
git add nama-file.php
```

### Membuat commit

```bash
git commit -m "Pesan commit"
```

### Push ke remote

```bash
# Push ke branch main
git push -u origin main

# Atau push ke branch master
git push -u origin master
```

### Melihat status

```bash
git status
```

### Melihat history commit

```bash
git log
```

## Workflow yang Disarankan

1. **Buat perubahan** di file-file proyek
2. **Jalankan script commit** atau gunakan manual commands
3. **Push ke remote** untuk backup online

## File yang Diabaikan (.gitignore)

File-file berikut tidak akan di-commit ke Git:
- File log (*.log)
- File cache dan temporary
- File upload (uploads/*)
- File database (*.sql, *.db)
- File backup
- Konfigurasi IDE (.vscode/, .idea/)
- File OS (.DS_Store, Thumbs.db)

## Tips

- **Commit sering-sering**: Buat commit setiap kali fitur selesai atau bug diperbaiki
- **Pesan commit yang jelas**: Gunakan pesan yang menjelaskan apa yang diubah
- **Jangan commit file sensitif**: Pastikan file konfigurasi dengan password tidak di-commit
- **Backup ke remote**: Selalu push ke remote repository untuk backup online

## Troubleshooting

### Error: "Git tidak ditemukan"
- Pastikan Git sudah terinstall
- Restart terminal/command prompt setelah install Git

### Error: "Remote repository belum dikonfigurasi"
- Tambahkan remote dengan: `git remote add origin <URL>`
- Atau skip push jika hanya ingin commit lokal

### Error: "Permission denied" (Linux/Mac)
- Berikan permission execute: `chmod +x git-commit.sh`

### Error: "Cannot execute script" (Windows PowerShell)
- Buka PowerShell sebagai Administrator
- Atau jalankan: `Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser`


