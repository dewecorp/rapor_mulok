#!/bin/bash
# Script Git Commit untuk Linux/Mac
# Penggunaan: ./git-commit.sh "Pesan commit"

COMMIT_MSG="${1:-Update project}"

echo "=== Git Commit Script ==="
echo ""

# Cek apakah Git sudah terinstall
if ! command -v git &> /dev/null; then
    echo "ERROR: Git tidak ditemukan! Silakan install Git terlebih dahulu."
    exit 1
fi

echo "Git ditemukan: $(git --version)"
echo ""

# Cek apakah sudah di dalam repository Git
if [ ! -d ".git" ]; then
    echo "Inisialisasi Git repository..."
    git init
    echo "Git repository berhasil diinisialisasi!"
    echo ""
fi

# Tampilkan status
echo "Status repository:"
git status --short
echo ""

# Cek apakah ada perubahan
has_changes=false
if ! git diff --quiet --exit-code 2>/dev/null; then
    has_changes=true
elif ! git diff --cached --quiet --exit-code 2>/dev/null; then
    has_changes=true
fi

if [ "$has_changes" = false ]; then
    echo "Tidak ada perubahan untuk di-commit."
    exit 0
fi

# Tanyakan konfirmasi
read -p "Lanjutkan commit dengan pesan '$COMMIT_MSG'? (Y/N): " confirm
if [[ ! $confirm =~ ^[Yy]$ ]]; then
    echo "Dibatalkan."
    exit 0
fi

# Tambahkan semua file
echo ""
echo "Menambahkan file ke staging..."
git add .

# Commit
echo "Membuat commit..."
git commit -m "$COMMIT_MSG"

if [ $? -eq 0 ]; then
    echo ""
    echo "Commit berhasil dibuat!"
    echo ""
    
    # Tanyakan apakah ingin push
    read -p "Apakah ingin push ke remote repository? (Y/N): " push_confirm
    if [[ $push_confirm =~ ^[Yy]$ ]]; then
        echo ""
        echo "Mengecek remote repository..."
        
        remote=$(git remote get-url origin 2>/dev/null)
        if [ -n "$remote" ]; then
            echo "Remote ditemukan: $remote"
            # Cek branch yang aktif
            current_branch=$(git branch --show-current)
            if [ -z "$current_branch" ]; then
                current_branch="master"
            fi
            echo "Push ke branch $current_branch..."
            git push -u origin "$current_branch"
            
            if [ $? -ne 0 ]; then
                echo "ERROR: Gagal push ke remote!"
            fi
        else
            echo "Remote repository belum dikonfigurasi."
            echo "Tambahkan remote dengan: git remote add origin <URL>"
        fi
    fi
else
    echo ""
    echo "ERROR: Gagal membuat commit!"
    exit 1
fi

echo ""
echo "=== Selesai ==="


