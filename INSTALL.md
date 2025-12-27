# Panduan Instalasi Rapor Mulok Khusus (RMK)

## Persyaratan Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi / MariaDB 10.2 atau lebih tinggi
- Web Server (Apache/Nginx)
- Extension PHP: mysqli, mbstring, gd

## Langkah Instalasi

### 1. Upload File
Upload semua file aplikasi ke folder web server Anda (contoh: `htdocs/rapor-mulok` atau `www/rapor-mulok`)

### 2. Buat Database
Buat database baru dengan nama `rapor_mulok` di phpMyAdmin atau melalui command line:
```sql
CREATE DATABASE rapor_mulok CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Import Database
Import file `database/schema.sql` ke database yang telah dibuat:
- Buka phpMyAdmin
- Pilih database `rapor_mulok`
- Klik tab "Import"
- Pilih file `database/schema.sql`
- Klik "Go"

### 4. Konfigurasi Database
Edit file `config/database.php` dan sesuaikan dengan konfigurasi database Anda:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'rapor_mulok');
```

### 5. Set Permission Folder
Pastikan folder berikut dapat ditulis (writeable):
- `uploads/` - untuk menyimpan foto dan logo
- `backups/` - untuk menyimpan backup database

Di Linux/Mac:
```bash
chmod 777 uploads/
chmod 777 backups/
```

Di Windows, pastikan folder memiliki permission write.

### 6. Akses Aplikasi
Buka browser dan akses aplikasi:
```
http://localhost/rapor-mulok/
```

### 7. Login Pertama Kali
Gunakan kredensial default:
- **Username:** `admin`
- **Password:** `admin123`

**PENTING:** Segera ubah password setelah login pertama kali!

## Konfigurasi Tambahan

### Upload Logo dan Foto Login
1. Login sebagai Proktor
2. Masuk ke menu **Lembaga > Profil Madrasah**
3. Upload logo madrasah dan foto untuk halaman login
4. Isi data profil madrasah lengkap

### Membuat Akun Guru/Wali Kelas
1. Login sebagai Proktor
2. Masuk ke menu **Guru > Data Guru**
3. Klik tombol **Tambah**
4. Isi data guru dan pilih role (Guru atau Wali Kelas)
5. Password default: `123456` (bisa diubah setelah login)

## Troubleshooting

### Error: "Koneksi gagal"
- Pastikan konfigurasi database di `config/database.php` sudah benar
- Pastikan MySQL/MariaDB sedang berjalan
- Pastikan database `rapor_mulok` sudah dibuat

### Error: "Permission denied" saat upload
- Pastikan folder `uploads/` dan `backups/` memiliki permission write
- Di Linux: `chmod 777 uploads/ backups/`

### Halaman kosong atau error
- Pastikan PHP error reporting aktif untuk debugging
- Cek log error di web server
- Pastikan semua file sudah terupload dengan lengkap

## Dukungan

Untuk bantuan lebih lanjut, hubungi administrator sistem.


