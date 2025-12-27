# Panduan Keamanan Git - Mencegah File Hilang

## ⚠️ Masalah yang Terjadi
File aplikasi hilang dari working directory meskipun sudah di-commit di git.

## ✅ Solusi yang Sudah Dilakukan
File sudah dipulihkan menggunakan `git restore .`

## 🛡️ Cara Mencegah File Hilang di Masa Depan

### 1. **Selalu Cek Status Git Sebelum Operasi**
```bash
git status
```
Pastikan tidak ada file yang terhapus sebelum melakukan operasi git.

### 2. **Jangan Gunakan `git clean` Tanpa Hati-Hati**
```bash
# ❌ JANGAN gunakan tanpa cek dulu
git clean -f

# ✅ Gunakan dengan dry-run dulu
git clean -n  # Lihat apa yang akan dihapus
git clean -f  # Baru hapus jika sudah yakin
```

### 3. **Jangan Gunakan `git reset --hard` Tanpa Backup**
```bash
# ❌ BERBAHAYA - akan menghapus semua perubahan
git reset --hard HEAD

# ✅ Lebih aman - hanya reset ke commit tertentu dengan backup
git stash  # Simpan perubahan dulu
git reset --hard HEAD~1
```

### 4. **Gunakan `.gitignore` dengan Benar**
Pastikan file penting tidak di-ignore:
- File aplikasi (`.php`, `.js`, `.html`) ✅ harus di-track
- File konfigurasi penting ✅ harus di-track
- File yang di-ignore: `vendor/`, `node_modules/`, `*.log`, dll

### 5. **Commit Secara Berkala**
```bash
# Commit perubahan secara berkala
git add .
git commit -m "Deskripsi perubahan yang jelas"
```

### 6. **Buat Backup Berkala**
```bash
# Buat backup ke branch terpisah
git branch backup-$(date +%Y%m%d)
```

### 7. **Cek File yang Akan Dihapus**
```bash
# Lihat file yang akan dihapus
git status | grep deleted

# Pulihkan file yang terhapus
git restore <nama-file>
# atau
git restore .  # Pulihkan semua
```

## 🔍 Cara Mengecek Jika File Hilang

### 1. Cek Status Git
```bash
git status
```

### 2. Lihat File di Commit Terakhir
```bash
git ls-tree -r HEAD --name-only
```

### 3. Bandingkan dengan Working Directory
```bash
git diff --name-status HEAD
```

## 🚨 Jika File Hilang Lagi

### Langkah 1: Cek Status
```bash
git status
```

### Langkah 2: Pulihkan dari Commit
```bash
# Pulihkan semua file yang terhapus
git restore .

# Atau pulihkan file tertentu
git restore <path/to/file>
```

### Langkah 3: Cek Reflog (jika perlu)
```bash
# Lihat history operasi git
git reflog

# Kembalikan ke commit tertentu jika perlu
git reset --hard <commit-hash>
```

## 📝 Best Practices

1. **Commit Message yang Jelas**
   ```bash
   git commit -m "Fix: Perbaiki masalah waktu aktivitas"
   # Bukan: "Update project"
   ```

2. **Review Perubahan Sebelum Commit**
   ```bash
   git diff  # Lihat perubahan
   git status  # Cek status
   ```

3. **Jangan Langsung Force Push**
   ```bash
   # ❌ JANGAN
   git push --force
   
   # ✅ Lebih aman
   git push
   ```

4. **Gunakan Branch untuk Fitur Baru**
   ```bash
   git checkout -b fitur-baru
   # Bekerja di branch terpisah
   git checkout master
   git merge fitur-baru
   ```

## 🔧 Script Bantuan

### Script untuk Cek File Hilang
```bash
# Simpan sebagai check-missing-files.sh
#!/bin/bash
echo "Checking for missing files..."
git status | grep deleted
if [ $? -eq 0 ]; then
    echo "⚠️ Found deleted files!"
    echo "Run: git restore ."
else
    echo "✅ No missing files"
fi
```

## 📚 Referensi
- Git Documentation: https://git-scm.com/doc
- Git Best Practices: https://git-scm.com/book

---
**Catatan**: File ini dibuat setelah masalah file hilang terjadi. 
Selalu backup pekerjaan penting dan commit secara berkala!

