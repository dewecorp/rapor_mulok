#!/bin/bash
# Script Git Commit Pintar dengan Pesan Otomatis untuk Linux/Mac
# Penggunaan: ./smart-commit.sh [pesan manual]

CUSTOM_MSG="$1"

echo "=== Smart Git Commit Script ==="
echo ""

# Cek Git
if ! command -v git &> /dev/null; then
    echo "ERROR: Git tidak ditemukan!"
    exit 1
fi

echo "Git: $(git --version)"
echo ""

# Cek repository
if [ ! -d ".git" ]; then
    echo "Inisialisasi Git repository..."
    git init
fi

# Cek status
echo "Menganalisis perubahan..."
if [ -z "$(git status --short)" ]; then
    echo "Tidak ada perubahan untuk di-commit."
    exit 0
fi

echo ""
echo "Perubahan yang terdeteksi:"
git status --short
echo ""

# Analisis perubahan
echo "Menganalisis tipe perubahan..."
echo ""

# Cek file yang diubah
HAS_CONFIG=0
HAS_FIX=0
HAS_FEATURE=0
HAS_STYLE=0
HAS_DOCS=0

for file in $(git diff --name-only 2>/dev/null); do
    if [[ "$file" =~ (config|database|\.env|settings) ]]; then
        HAS_CONFIG=1
    fi
    if [[ "$file" =~ (fix|bug|error|issue|perbaiki) ]]; then
        HAS_FIX=1
    fi
    if [[ "$file" =~ (feature|fitur|add|tambah|new) ]]; then
        HAS_FEATURE=1
    fi
    if [[ "$file" =~ (style|css|design|ui|theme) ]]; then
        HAS_STYLE=1
    fi
    if [[ "$file" =~ (readme|doc|\.md|changelog) ]]; then
        HAS_DOCS=1
    fi
done

# Generate pesan commit
COMMIT_TYPE="Update"
COMMIT_MSG="Update kode"

if [ $HAS_FIX -eq 1 ]; then
    COMMIT_TYPE="Fix"
    COMMIT_MSG="Perbaiki bug"
elif [ $HAS_CONFIG -eq 1 ]; then
    COMMIT_TYPE="Config"
    COMMIT_MSG="Update konfigurasi"
elif [ $HAS_FEATURE -eq 1 ]; then
    COMMIT_TYPE="Feat"
    COMMIT_MSG="Tambah fitur baru"
elif [ $HAS_STYLE -eq 1 ]; then
    COMMIT_TYPE="Style"
    COMMIT_MSG="Perbaiki styling"
elif [ $HAS_DOCS -eq 1 ]; then
    COMMIT_TYPE="Docs"
    COMMIT_MSG="Update dokumentasi"
fi

# Hitung jumlah file
FILE_COUNT=$(git diff --name-only 2>/dev/null | wc -l | tr -d ' ')

# Buat pesan lengkap
FULL_MSG="$COMMIT_TYPE: $COMMIT_MSG"
if [ $FILE_COUNT -gt 0 ]; then
    FULL_MSG="$FULL_MSG ($FILE_COUNT file)"
fi

# Tambahkan timestamp
TIMESTAMP=$(date '+%Y-%m-%d %H:%M')
FULL_MSG="$FULL_MSG - $TIMESTAMP"

# Gunakan custom message jika ada
if [ -n "$CUSTOM_MSG" ]; then
    FULL_MSG="$CUSTOM_MSG"
fi

# Tampilkan preview
echo "Pesan commit yang akan digunakan:"
echo "  $FULL_MSG"
echo ""

# Menu pilihan
echo "Pilihan:"
echo "  1. Gunakan pesan ini"
echo "  2. Edit pesan"
echo "  3. Batal"
echo ""

read -p "Pilih (1-3): " CHOICE

case $CHOICE in
    1)
        FINAL_MSG="$FULL_MSG"
        ;;
    2)
        read -p "Masukkan pesan commit: " FINAL_MSG
        if [ -z "$FINAL_MSG" ]; then
            echo "Pesan tidak boleh kosong!"
            exit 1
        fi
        ;;
    3)
        echo "Dibatalkan."
        exit 0
        ;;
    *)
        echo "Pilihan tidak valid!"
        exit 1
        ;;
esac

# Konfirmasi
echo ""
echo "Pesan commit final: $FINAL_MSG"
read -p "Lanjutkan commit? (Y/N): " CONFIRM

if [[ ! $CONFIRM =~ ^[Yy]$ ]]; then
    echo "Dibatalkan."
    exit 0
fi

# Stage dan commit
echo ""
echo "Menambahkan file ke staging..."
git add .

echo "Membuat commit..."
git commit -m "$FINAL_MSG"

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Commit berhasil dibuat!"
    echo ""
    
    # Tampilkan log
    echo "Commit terakhir:"
    git log -1 --oneline
    echo ""
    
    read -p "Push ke remote repository? (Y/N): " PUSH_CONFIRM
    if [[ $PUSH_CONFIRM =~ ^[Yy]$ ]]; then
        echo ""
        echo "Push ke remote..."
        
        REMOTE=$(git remote get-url origin 2>/dev/null)
        if [ -n "$REMOTE" ]; then
            echo "Remote: $REMOTE"
            
            CURRENT_BRANCH=$(git branch --show-current 2>/dev/null || echo "master")
            git push -u origin "$CURRENT_BRANCH"
            
            if [ $? -eq 0 ]; then
                echo "✓ Push berhasil!"
            else
                echo "✗ Push gagal!"
            fi
        else
            echo "Remote belum dikonfigurasi."
            echo "Tambahkan dengan: git remote add origin <URL>"
        fi
    fi
else
    echo ""
    echo "✗ ERROR: Gagal membuat commit!"
    exit 1
fi

echo ""
echo "=== Selesai ==="

