# Migration Script: kode_mulok ke kategori_mulok

## Masalah
Error "Duplicate entry for key 'materi_mulok.kode_mulok'" terjadi karena:
1. Database masih menggunakan kolom `kode_mulok` dengan unique constraint
2. Unique constraint mencegah duplikasi nilai

## Solusi
Jalankan script migration untuk:
1. Menghapus unique constraint dari `kode_mulok`
2. Mengubah nama kolom dari `kode_mulok` menjadi `kategori_mulok`
3. Menghapus unique constraint (tidak dibuat kembali) sesuai permintaan "tidak ada validasi"

## Cara Menjalankan

### Opsi 1: Menggunakan phpMyAdmin
1. Buka phpMyAdmin
2. Pilih database Anda
3. Klik tab "SQL"
4. Copy-paste isi file `migrate_kode_to_kategori_mulok_simple.sql`
5. Klik "Go" atau "Jalankan"

### Opsi 2: Menggunakan MySQL Command Line
```bash
mysql -u username -p database_name < database/migrate_kode_to_kategori_mulok_simple.sql
```

### Opsi 3: Menggunakan MySQL Workbench
1. Buka MySQL Workbench
2. Connect ke database
3. File > Open SQL Script
4. Pilih file `migrate_kode_to_kategori_mulok_simple.sql`
5. Klik Execute

## File Migration

### migrate_kode_to_kategori_mulok_simple.sql (RECOMMENDED)
- Versi sederhana dan mudah digunakan
- Menghapus unique constraint
- Mengubah nama kolom
- Tidak membuat unique constraint baru

### migrate_kode_to_kategori_mulok.sql
- Versi lengkap dengan pengecekan
- Lebih aman dengan validasi sebelum drop index

## Setelah Migration
Setelah menjalankan script:
1. Refresh halaman aplikasi
2. Aplikasi akan otomatis menggunakan kolom `kategori_mulok`
3. Tidak akan ada lagi error duplicate entry
4. Bisa menambahkan kategori yang sama (tidak ada validasi)

## Backup
**PENTING:** Selalu backup database sebelum menjalankan migration!

```bash
mysqldump -u username -p database_name > backup_before_migration.sql
```






