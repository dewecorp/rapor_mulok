<?php
/**
 * Utilitas tahun ajaran (format Indonesia YYYY/YYYY, Juli–Juni).
 */

function ta_normalisasi_ke_slash(?string $ta): string {
    if ($ta === null || trim($ta) === '') {
        return '';
    }
    $ta = preg_replace('/\s+/', '', trim($ta));
    return str_replace('-', '/', $ta);
}

function ta_ambil_tahun_mulai(?string $ta): int {
    $ta = ta_normalisasi_ke_slash($ta);
    if (preg_match('/^(\d{4})/', $ta, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/** Tahun ajaran berjalan berdasarkan tanggal server (Juli = awal tahun ajaran baru). */
function ta_dari_kalender(?int $ts = null): string {
    if ($ts === null) {
        $ts = time();
    }
    $y = (int) date('Y', $ts);
    $m = (int) date('n', $ts);
    if ($m >= 7) {
        return $y . '/' . ($y + 1);
    }
    return ($y - 1) . '/' . $y;
}

function ta_tambah_tahun(string $taNorm, int $n): string {
    $awal = ta_ambil_tahun_mulai($taNorm);
    if ($awal < 1) {
        return '';
    }
    $awalBaru = $awal + $n;
    return $awalBaru . '/' . ($awalBaru + 1);
}

/**
 * Rangkaian tahun ajaran: basis + $tambahan tahun berikutnya (total 1 + $tambahan opsi).
 */
function ta_rangkaian_ke_depan(?string $basisNorm, int $tambahan = 3): array {
    $basisNorm = ta_normalisasi_ke_slash($basisNorm ?? '');
    if ($basisNorm === '') {
        $basisNorm = ta_dari_kalender();
    }
    $out = [];
    for ($i = 0; $i <= $tambahan; $i++) {
        $s = ta_tambah_tahun($basisNorm, $i);
        if ($s !== '') {
            $out[] = $s;
        }
    }
    return $out;
}

/** Varian penyimpanan di DB (/ vs -) untuk klausa IN. */
function ta_varian_untuk_query(string $ta): array {
    $norm = ta_normalisasi_ke_slash($ta);
    if ($norm === '') {
        return [];
    }
    $dash = str_replace('/', '-', $norm);
    if ($dash === $norm) {
        return [$norm];
    }
    return array_values(array_unique([$norm, $dash]));
}

/**
 * Kumpulkan opsi dropdown profil: historis + data nilai + jendela (aktif + 3 ke depan).
 *
 * @return string[] terurut dari tahun mulai terbaru ke terlama
 */
function ta_kumpulkan_opsi_dropdown(mysqli $conn, string $tahunAjaranAktifNorm, string $jsonPernahAktif): array {
    $map = [];

    $decode = json_decode($jsonPernahAktif, true);
    if (is_array($decode)) {
        foreach ($decode as $t) {
            $n = ta_normalisasi_ke_slash((string) $t);
            if ($n !== '') {
                $map[$n] = ta_ambil_tahun_mulai($n);
            }
        }
    }

    $queries = [
        "SELECT DISTINCT tahun_ajaran AS t FROM nilai_siswa WHERE tahun_ajaran IS NOT NULL AND TRIM(tahun_ajaran) != ''",
        "SELECT DISTINCT tahun_ajaran AS t FROM nilai_kirim_status WHERE tahun_ajaran IS NOT NULL AND TRIM(tahun_ajaran) != ''",
    ];
    foreach ($queries as $q) {
        $r = @$conn->query($q);
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $n = ta_normalisasi_ke_slash($row['t'] ?? '');
                if ($n !== '') {
                    $map[$n] = ta_ambil_tahun_mulai($n);
                }
            }
        }
    }

    $basisAktif = $tahunAjaranAktifNorm !== '' ? $tahunAjaranAktifNorm : ta_dari_kalender();
    foreach (ta_rangkaian_ke_depan($basisAktif, 3) as $y) {
        $map[$y] = ta_ambil_tahun_mulai($y);
    }

    arsort($map);
    return array_keys($map);
}

function ta_perbarui_json_pernah_aktif(?string $jsonSekarang, string $baruNorm): string {
    $arr = json_decode($jsonSekarang ?? '[]', true);
    if (!is_array($arr)) {
        $arr = [];
    }
    $baruNorm = ta_normalisasi_ke_slash($baruNorm);
    if ($baruNorm !== '' && !in_array($baruNorm, $arr, true)) {
        $arr[] = $baruNorm;
    }
    return json_encode(array_values(array_unique($arr)), JSON_UNESCAPED_UNICODE);
}
