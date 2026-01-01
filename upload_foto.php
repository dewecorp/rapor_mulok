<?php
require_once 'config/config.php';
require_once 'config/database.php';
requireLogin();

header('Content-Type: application/json');

$conn = getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_foto') {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'File tidak ditemukan atau terjadi error saat upload'
        ]);
        exit;
    }
    
    $file = $_FILES['foto'];
    
    // Validasi ukuran file (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'Ukuran file maksimal 2MB'
        ]);
        exit;
    }
    
    // Validasi tipe file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'Format file tidak valid. Hanya JPG, PNG, dan GIF yang diperbolehkan'
        ]);
        exit;
    }
    
    // Generate nama file baru
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'foto_' . $user_id . '_' . time() . '.' . $extension;
    $upload_path = 'uploads/' . $new_filename;
    
    // Hapus foto lama jika ada
    try {
        $stmt = $conn->prepare("SELECT foto FROM pengguna WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $old_foto = $row['foto'];
            if ($old_foto && $old_foto != 'default.png' && file_exists('uploads/' . $old_foto)) {
                unlink('uploads/' . $old_foto);
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Ignore error saat menghapus foto lama
    }
    
    // Upload file baru
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Update database
        try {
            $stmt = $conn->prepare("UPDATE pengguna SET foto = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            if ($stmt->execute()) {
                // Update session
                $_SESSION['foto'] = $new_filename;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Foto berhasil diupdate',
                    'filename' => $new_filename
                ]);
            } else {
                // Hapus file yang sudah diupload jika update database gagal
                if (file_exists($upload_path)) {
                    unlink($upload_path);
                }
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal mengupdate database'
                ]);
            }
            $stmt->close();
        } catch (Exception $e) {
            // Hapus file yang sudah diupload jika terjadi error
            if (file_exists($upload_path)) {
                unlink($upload_path);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengupload file'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Request tidak valid'
    ]);
}
?>



