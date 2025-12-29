# Cara Menghapus Error "Duplicate entry" di Materi Mulok

## Masalah
Error: **"Duplicate entry 'Hafalan' for key 'materi_mulok.kode_mulok'"**

Error ini terjadi karena database masih memiliki **unique constraint** di kolom `kode_mulok` yang mencegah duplikasi nilai.

## Solusi

### ✅ Opsi 1: Script PHP (PALING MUDAH - DISARANKAN)

1. Buka browser dan akses:
   ```
   http://localhost/rapor-mulok/lembaga/fix_constraint.php
   ```

2. Script akan otomatis:
   - Mencari semua unique constraint
   - Menghapus semua unique constraint
   - Mengubah kolom menjadi nullable

3. Setelah selesai:
   - ✅ Refresh halaman materi mulok
   - ✅ Error akan hilang
   - ⚠️ **HAPUS file `fix_constraint.php` untuk keamanan**

---

### ✅ Opsi 2: SQL Manual (phpMyAdmin)

1. Buka **phpMyAdmin**
2. Pilih database Anda
3. Klik tab **"SQL"**
4. Copy-paste isi file **`database/fix_duplicate_error.sql`**
5. Klik **"Go"** atau **"Jalankan"**

**Atau copy-paste query ini:**

```sql
-- Hapus unique constraint dari kode_mulok
DROP INDEX IF EXISTS `kode_mulok` ON `materi_mulok`;
DROP INDEX IF EXISTS `uk_kode_mulok` ON `materi_mulok`;
DROP INDEX IF EXISTS `unique_kode_mulok` ON `materi_mulok`;
DROP INDEX IF EXISTS `idx_kode_mulok` ON `materi_mulok`;

-- Ubah kolom menjadi nullable
ALTER TABLE `materi_mulok` 
MODIFY COLUMN `kode_mulok` VARCHAR(255) NULL;

-- Hapus unique constraint dari kategori_mulok (jika sudah di-migrate)
DROP INDEX IF EXISTS `kategori_mulok` ON `materi_mulok`;
DROP INDEX IF EXISTS `uk_kategori_mulok` ON `materi_mulok`;
DROP INDEX IF EXISTS `unique_kategori_mulok` ON `materi_mulok`;
DROP INDEX IF EXISTS `idx_kategori_mulok` ON `materi_mulok`;

ALTER TABLE `materi_mulok` 
MODIFY COLUMN `kategori_mulok` VARCHAR(255) NULL;
```

---

### ✅ Opsi 3: Cek dan Hapus Manual

Jika masih error, cek index yang ada:

```sql
SHOW INDEX FROM materi_mulok WHERE Column_name IN ('kode_mulok', 'kategori_mulok') AND Non_unique = 0;
```

Kemudian hapus satu per satu:

```sql
DROP INDEX `nama_index_yang_ditemukan` ON `materi_mulok`;
```

---

## Setelah Menghapus Constraint

✅ Error "Duplicate entry" akan hilang  
✅ Bisa menambahkan kategori yang sama  
✅ Bisa edit tanpa error  
✅ Tidak ada validasi unique di database  

---

## File yang Tersedia

1. **`lembaga/fix_constraint.php`** - Script PHP (paling mudah)
2. **`database/fix_duplicate_error.sql`** - Script SQL lengkap
3. **`database/remove_unique_constraint.sql`** - Script SQL alternatif

---

## ⚠️ PENTING

- **Selalu backup database** sebelum menjalankan script
- **Hapus file `fix_constraint.php`** setelah selesai untuk keamanan
- Jika masih error, cek apakah ada constraint lain dengan nama berbeda

