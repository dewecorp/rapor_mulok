<?php
/**
 * Helper function untuk menghitung selisih waktu dengan benar
 * Memperbaiki masalah waktu yang selalu terbaca "baru saja"
 */

/**
 * Menghitung selisih waktu dan mengembalikan string yang mudah dibaca
 * 
 * @param string|DateTime $timestamp Timestamp atau DateTime object
 * @param string $timezone Timezone (default: Asia/Jakarta)
 * @return string String waktu yang mudah dibaca (contoh: "2 jam yang lalu")
 */
function timeAgo($timestamp, $timezone = 'Asia/Jakarta') {
    // Set timezone
    date_default_timezone_set($timezone);
    
    // Konversi timestamp ke DateTime jika berupa string
    if (is_string($timestamp)) {
        // Cek apakah sudah dalam format yang benar
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
        if (!$dt) {
            // Coba format lain
            $dt = new DateTime($timestamp);
        }
    } else if ($timestamp instanceof DateTime) {
        $dt = $timestamp;
    } else {
        return 'Waktu tidak valid';
    }
    
    // Set timezone untuk DateTime object
    $dt->setTimezone(new DateTimeZone($timezone));
    
    // Waktu sekarang dengan timezone yang sama
    $now = new DateTime('now', new DateTimeZone($timezone));
    
    // Hitung selisih
    $diff = $now->diff($dt);
    
    // Jika waktu di masa depan, kembalikan pesan khusus
    if ($dt > $now) {
        return 'Baru saja';
    }
    
    // Hitung total detik
    $totalSeconds = $now->getTimestamp() - $dt->getTimestamp();
    
    // Jika kurang dari 60 detik
    if ($totalSeconds < 60) {
        return 'Baru saja';
    }
    
    // Jika kurang dari 3600 detik (1 jam)
    if ($totalSeconds < 3600) {
        $minutes = floor($totalSeconds / 60);
        return $minutes . ' menit yang lalu';
    }
    
    // Jika kurang dari 86400 detik (1 hari)
    if ($totalSeconds < 86400) {
        $hours = floor($totalSeconds / 3600);
        return $hours . ' jam yang lalu';
    }
    
    // Jika kurang dari 604800 detik (1 minggu)
    if ($totalSeconds < 604800) {
        $days = floor($totalSeconds / 86400);
        return $days . ' hari yang lalu';
    }
    
    // Jika kurang dari 2592000 detik (1 bulan)
    if ($totalSeconds < 2592000) {
        $weeks = floor($totalSeconds / 604800);
        return $weeks . ' minggu yang lalu';
    }
    
    // Jika kurang dari 31536000 detik (1 tahun)
    if ($totalSeconds < 31536000) {
        $months = floor($totalSeconds / 2592000);
        return $months . ' bulan yang lalu';
    }
    
    // Lebih dari 1 tahun
    $years = floor($totalSeconds / 31536000);
    return $years . ' tahun yang lalu';
}

/**
 * Format waktu untuk aktivitas dengan perbaikan timezone
 * 
 * @param string $created_at Timestamp dari database
 * @return string String waktu yang diformat
 */
function formatAktivitasTime($created_at) {
    // Pastikan timezone sudah diset
    $timezone = 'Asia/Jakarta';
    date_default_timezone_set($timezone);
    
    // Parse timestamp dari database
    // MySQL timestamp biasanya dalam format 'Y-m-d H:i:s'
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $created_at, new DateTimeZone('UTC'));
    
    if (!$dt) {
        // Coba format lain jika gagal
        $dt = new DateTime($created_at, new DateTimeZone('UTC'));
    }
    
    // Konversi ke timezone lokal
    $dt->setTimezone(new DateTimeZone($timezone));
    
    // Gunakan fungsi timeAgo
    return timeAgo($dt, $timezone);
}

