# Git Auto-Commit Scripts

Script-script untuk melakukan auto-commit perubahan Git secara otomatis.

## File Scripts

1. **auto-commit.bat** - Script batch untuk Windows (Command Prompt)
2. **auto-commit.ps1** - Script PowerShell untuk Windows
3. **watch-and-commit.ps1** - Script untuk watch perubahan dan auto-commit

## Cara Menggunakan

### 1. Auto-Commit Manual (Batch)

```cmd
auto-commit.bat "Pesan commit Anda"
```

Atau tanpa pesan (akan menggunakan timestamp):
```cmd
auto-commit.bat
```

### 2. Auto-Commit Manual (PowerShell)

```powershell
.\auto-commit.ps1 "Pesan commit Anda"
```

Atau tanpa pesan:
```powershell
.\auto-commit.ps1
```

### 3. Watch dan Auto-Commit (PowerShell)

Jalankan script untuk watch perubahan file dan otomatis commit setiap ada perubahan:

```powershell
.\watch-and-commit.ps1
```

Script ini akan:
- Monitor perubahan file setiap 5 detik
- Auto-commit jika ada perubahan (dengan delay 30 detik untuk menghindari commit terlalu sering)
- Menampilkan informasi file yang berubah
- Tekan Ctrl+C untuk berhenti

## Catatan

- Pastikan Anda sudah berada di direktori repository Git
- Script akan menambahkan semua perubahan (git add -A)
- Untuk watch script, pastikan PowerShell execution policy mengizinkan script:
  ```powershell
  Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
  ```

## Tips

- Gunakan `watch-and-commit.ps1` untuk development aktif
- Gunakan `auto-commit.ps1` atau `auto-commit.bat` untuk commit manual dengan pesan custom
- Sesuaikan `$commitDelay` di `watch-and-commit.ps1` jika ingin mengubah delay commit

