# Cara Mengatasi Masalah Tampilan Tidak Tersimpan (Browser Cache)

## Masalah
Perubahan tampilan tidak tersimpan meskipun sudah auto-save dan commit git. Data tersimpan di database, tetapi tampilan kembali ke versi lama.

## Penyebab
Masalah ini biasanya disebabkan oleh **browser cache** yang menyimpan versi lama dari CSS, JavaScript, dan HTML.

## Solusi yang Sudah Diterapkan

### 1. Cache Busting
Sudah ditambahkan parameter version pada semua CSS dan JavaScript:
- Menggunakan `APP_VERSION` + `filemtime()` untuk generate unique version
- Setiap kali file diubah, version akan berubah otomatis
- Browser akan memuat ulang file yang baru

### 2. Meta Tags untuk Prevent Cache
Sudah ditambahkan meta tags di `includes/header.php`:
```html
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
```

## Cara Clear Browser Cache (Manual)

### Google Chrome / Microsoft Edge
1. Tekan `Ctrl + Shift + Delete` (atau `Cmd + Shift + Delete` di Mac)
2. Pilih "Cached images and files"
3. Pilih waktu "All time" atau "Last hour"
4. Klik "Clear data"

**Atau:**
1. Tekan `F12` untuk buka Developer Tools
2. Klik kanan pada tombol refresh (di address bar)
3. Pilih "Empty Cache and Hard Reload"

**Atau:**
1. Tekan `Ctrl + F5` untuk hard refresh (force reload tanpa cache)

### Firefox
1. Tekan `Ctrl + Shift + Delete`
2. Pilih "Cache"
3. Pilih waktu "Everything"
4. Klik "Clear Now"

**Atau:**
1. Tekan `Ctrl + Shift + R` untuk hard refresh

### Safari
1. Tekan `Cmd + Option + E` untuk clear cache
2. Atau: `Cmd + Shift + R` untuk hard refresh

## Cara Cek Apakah Masalah Sudah Teratasi

1. **Clear browser cache** menggunakan salah satu metode di atas
2. **Refresh halaman** dengan `Ctrl + F5` atau `Ctrl + Shift + R`
3. **Cek Developer Tools** (F12):
   - Buka tab "Network"
   - Refresh halaman
   - Pastikan file CSS/JS dimuat dengan parameter `?v=...` yang baru
   - Pastikan status "200" atau "304" (bukan dari cache)

## Jika Masih Bermasalah

### 1. Clear Session Storage dan Local Storage
1. Buka Developer Tools (F12)
2. Buka tab "Application" (Chrome) atau "Storage" (Firefox)
3. Klik kanan pada "Local Storage" dan "Session Storage"
4. Pilih "Clear"

### 2. Restart Browser
Tutup semua tab browser dan buka kembali.

### 3. Gunakan Incognito/Private Mode
1. Buka browser dalam mode incognito/private
2. Login ke aplikasi
3. Cek apakah tampilan sudah benar

### 4. Clear PHP OpCache (jika menggunakan)
Jika menggunakan PHP dengan OpCache:
```bash
# Restart web server (Apache/Nginx)
# Atau jalankan:
php -r "opcache_reset();"
```

### 5. Cek File Permissions
Pastikan file PHP bisa diubah:
```bash
# Windows: Pastikan file tidak read-only
# Linux/Mac:
chmod 644 includes/header.php includes/footer.php
```

## Verifikasi Perubahan Sudah Tersimpan

1. **Cek Git Status:**
   ```bash
   git status
   git log --oneline -5
   ```

2. **Cek File Langsung:**
   - Buka `includes/header.php`
   - Pastikan ada meta tags cache control
   - Pastikan ada parameter `?v=` pada CSS/JS

3. **Cek di Browser:**
   - View page source (Ctrl + U)
   - Cari meta tags cache control
   - Cek link CSS/JS apakah ada parameter version

## Catatan Penting

- **Cache busting** akan otomatis bekerja setiap kali file `header.php` atau `footer.php` diubah
- **Meta tags** akan memaksa browser untuk tidak cache halaman (development mode)
- Untuk **production**, pertimbangkan untuk menghapus meta tags prevent cache dan hanya menggunakan cache busting
- **Data di database** tidak terpengaruh oleh cache browser, hanya tampilan saja

## Kontak Support

Jika masalah masih terjadi setelah mengikuti langkah-langkah di atas, hubungi tim IT.



