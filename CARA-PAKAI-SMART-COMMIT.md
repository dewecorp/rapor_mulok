# Cara Pakai Smart Commit Script

## 📋 Deskripsi
Script commit pintar yang **otomatis membuat pesan commit berbeda** berdasarkan perubahan yang ada.

## 🚀 Cara Menggunakan

### Windows PowerShell (Recommended)
```powershell
.\smart-commit.ps1
```

Dengan pesan custom:
```powershell
.\smart-commit.ps1 "Fix: Perbaiki bug login"
```

### Windows CMD
```cmd
smart-commit.bat
```

Dengan pesan custom:
```cmd
smart-commit.bat "Fix: Perbaiki bug login"
```

### Linux/Mac
```bash
chmod +x smart-commit.sh
./smart-commit.sh
```

Dengan pesan custom:
```bash
./smart-commit.sh "Fix: Perbaiki bug login"
```

## ✨ Fitur

### 1. **Analisis Otomatis Perubahan**
Script akan menganalisis file yang diubah dan menentukan tipe commit:
- `Fix` - Jika ada file dengan kata "fix", "bug", "error"
- `Config` - Jika ada file konfigurasi (config, database, .env)
- `Feat` - Jika ada file fitur baru
- `Style` - Jika ada perubahan CSS/styling
- `Docs` - Jika ada perubahan dokumentasi
- `Update` - Default untuk perubahan umum

### 2. **Pesan Commit Otomatis**
Contoh pesan yang dihasilkan:
```
Fix(config): Perbaiki bug - 2025-12-27 22:30:15
Feat(siswa): Tambah fitur baru (5 file) - 2025-12-27 22:30:15
Config: Update konfigurasi (2 file) - 2025-12-27 22:30:15
```

### 3. **Menu Interaktif**
Setelah analisis, Anda akan mendapat pilihan:
1. **Gunakan pesan otomatis** - Langsung commit dengan pesan yang dihasilkan
2. **Edit pesan** - Edit pesan sebelum commit
3. **Pilih tipe manual** - Pilih tipe commit secara manual (Feat, Fix, Update, dll)
4. **Batal** - Batalkan commit

### 4. **Preview Sebelum Commit**
Script akan menampilkan:
- File yang akan di-commit
- Pesan commit yang akan digunakan
- Konfirmasi sebelum commit

## 📝 Contoh Penggunaan

### Contoh 1: Commit Otomatis
```powershell
PS> .\smart-commit.ps1

=== Smart Git Commit Script ===
Menganalisis perubahan...
Perubahan yang terdeteksi:
 M config/config.php
 M index.php
?? includes/time_helper.php

Menganalisis tipe perubahan...
Pesan commit yang akan digunakan:
  Fix(config): Perbaiki bug (3 file) - 2025-12-27 22:30:15

Pilihan:
  1. Gunakan pesan ini (otomatis)
  2. Edit pesan
  3. Pilih tipe commit manual
  4. Batal

Pilih (1-4): 1
```

### Contoh 2: Edit Pesan
```powershell
PS> .\smart-commit.ps1

Pesan commit yang akan digunakan:
  Update: Update kode (2 file) - 2025-12-27 22:30:15

Pilih (1-4): 2
Masukkan pesan commit: Fix: Perbaiki masalah waktu aktivitas
```

### Contoh 3: Pilih Tipe Manual
```powershell
PS> .\smart-commit.ps1

Pilih (1-4): 3

Tipe commit:
  1. Feat - Fitur baru
  2. Fix - Perbaikan bug
  3. Update - Update kode
  4. Refactor - Refactor kode
  5. Style - Perbaikan styling
  6. Docs - Dokumentasi
  7. Config - Konfigurasi
  8. Test - Test

Pilih tipe (1-8): 2
Scope (opsional): aktivitas
Deskripsi: Perbaiki perhitungan waktu
```

## 🔄 Perbandingan dengan Script Lama

### Script Lama (`git-commit.bat`)
```cmd
git-commit.bat
# Selalu menghasilkan: "Update project"
```

### Script Baru (`smart-commit.bat`)
```cmd
smart-commit.bat
# Menganalisis perubahan dan menghasilkan pesan yang berbeda:
# - "Fix(config): Perbaiki bug (3 file) - 2025-12-27 22:30:15"
# - "Feat(siswa): Tambah fitur baru (5 file) - 2025-12-27 22:30:15"
# - "Config: Update konfigurasi (2 file) - 2025-12-27 22:30:15"
```

## 💡 Tips

1. **Gunakan PowerShell version** untuk fitur lengkap (menu lebih lengkap)
2. **Review pesan sebelum commit** - Script akan menampilkan preview
3. **Gunakan pesan custom** jika perlu pesan spesifik
4. **Commit secara berkala** untuk history yang lebih baik

## 🎯 Format Pesan Commit

Script menggunakan format:
```
[Tipe]([Scope]): [Deskripsi] ([Detail]) - [Timestamp]
```

Contoh:
- `Fix(config): Perbaiki bug timezone (2 file) - 2025-12-27 22:30:15`
- `Feat(siswa): Tambah fitur import (5 file) - 2025-12-27 22:30:15`
- `Update: Update kode (3 file) - 2025-12-27 22:30:15`

## ⚠️ Catatan

- Script akan otomatis menambahkan semua file ke staging (`git add .`)
- Pastikan file yang tidak ingin di-commit sudah ada di `.gitignore`
- Script akan menanyakan konfirmasi sebelum commit dan push

## 🐛 Troubleshooting

### Script tidak bisa dijalankan (Windows)
```powershell
# Set execution policy untuk PowerShell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Permission denied (Linux/Mac)
```bash
chmod +x smart-commit.sh
```

### Git tidak ditemukan
Pastikan Git sudah terinstall dan ada di PATH system.

---
**Selamat menggunakan!** 🎉

