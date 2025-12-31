# Panduan Memulihkan Perubahan yang Hilang

## Masalah
Perubahan logika dan query yang dibuat kemarin tidak tersimpan/tidak terlihat.

## Langkah-langkah Diagnosa

### 1. Cek Status Git
```bash
git status
git log --oneline -10
```

### 2. Gunakan Script Check Missing Changes
Akses melalui browser:
```
http://localhost/rapor-mulok/check_missing_changes.php
```

Script ini akan mengecek:
- File-file penting apakah ada
- Fungsi-fungsi penting apakah ada
- Query database apakah masih bekerja
- Kolom-kolom penting di database

### 3. Cek Perubahan yang Tidak Ter-commit
```bash
# Cek apakah ada perubahan yang belum di-commit
git status

# Cek perubahan detail
git diff

# Cek perubahan di file tertentu
git diff nama_file.php
```

### 4. Cek History Perubahan File Tertentu
```bash
# Lihat history perubahan file
git log --follow -- nama_file.php

# Lihat perubahan di commit tertentu
git show commit_hash:nama_file.php

# Bandingkan dengan commit sebelumnya
git diff HEAD~1 HEAD -- nama_file.php
```

## Kemungkinan Penyebab

### 1. Perubahan Tidak Ter-commit
- Perubahan ada di working directory tapi belum di-commit
- Solusi: Commit perubahan yang ada

### 2. File Di-restore ke Versi Lama
- File mungkin di-restore secara tidak sengaja
- Solusi: Cek git reflog dan restore dari commit yang benar

### 3. Perubahan di Branch Lain
- Perubahan mungkin ada di branch lain
- Solusi: Cek semua branch dengan `git branch -a`

### 4. Perubahan Tidak Ter-save
- File mungkin tidak ter-save dengan benar
- Solusi: Cek file langsung di editor

## Cara Memulihkan

### Opsi 1: Restore dari Commit Tertentu
```bash
# Lihat semua commit
git log --oneline

# Restore file dari commit tertentu
git checkout commit_hash -- nama_file.php

# Atau restore semua perubahan dari commit tertentu
git checkout commit_hash -- .
```

### Opsi 2: Cek Stash
```bash
# Cek apakah ada perubahan yang di-stash
git stash list

# Jika ada, apply stash
git stash apply stash@{0}
```

### Opsi 3: Cek Reflog
```bash
# Lihat semua aktivitas git
git reflog

# Reset ke commit tertentu
git reset --hard commit_hash
```

## Informasi yang Diperlukan

Untuk membantu memulihkan perubahan, mohon berikan informasi:

1. **File apa yang berubah?**
   - Contoh: `guru/penilaian.php`, `wali-kelas/rapor.php`, dll

2. **Fungsi/query apa yang hilang?**
   - Contoh: fungsi `hitungPredikat()`, query `SELECT ...`, dll

3. **Kapan perubahan dibuat?**
   - Contoh: kemarin pagi, kemarin siang, dll

4. **Apakah perubahan sudah di-commit?**
   - Cek dengan `git log --oneline`

5. **Screenshot atau deskripsi perubahan yang hilang**
   - Bagaimana seharusnya tampilannya?
   - Apa yang seharusnya terjadi tapi tidak terjadi?

## Langkah Selanjutnya

1. **Jalankan script check:**
   ```
   http://localhost/rapor-mulok/check_missing_changes.php
   ```

2. **Berikan informasi detail:**
   - File apa yang berubah
   - Fungsi/query apa yang hilang
   - Kapan perubahan dibuat

3. **Cek git history:**
   ```bash
   git log --all --oneline --grep="nama_fungsi_atau_query"
   ```

4. **Jika perlu, restore dari backup:**
   - Cek folder `backups/` untuk backup database
   - Cek git untuk backup kode

## Kontak Support

Jika masih tidak bisa memulihkan, hubungi tim IT dengan informasi:
- Output dari `check_missing_changes.php`
- Output dari `git log --oneline -20`
- Deskripsi perubahan yang hilang


