<?php
ob_start();

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/simad.php';

ob_clean();

if (!hasRole('proktor')) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses!']);
    exit;
}

/**
 * @param mysqli $conn
 */
function guru_sync_ensure_columns(mysqli $conn): void
{
    $cols = [
        'nuptk' => 'ALTER TABLE pengguna ADD COLUMN nuptk VARCHAR(50) UNIQUE AFTER username',
        'pendidikan' => 'ALTER TABLE pengguna ADD COLUMN pendidikan VARCHAR(100) AFTER tanggal_lahir',
        'password_plain' => 'ALTER TABLE pengguna ADD COLUMN password_plain VARCHAR(255) NULL AFTER password',
        'is_proktor_utama' => 'ALTER TABLE pengguna ADD COLUMN is_proktor_utama TINYINT(1) NOT NULL DEFAULT 0',
        'simad_id_guru' => 'ALTER TABLE pengguna ADD COLUMN simad_id_guru INT UNSIGNED NULL DEFAULT NULL COMMENT \'ID guru di SIMAD\' AFTER nuptk',
    ];
    foreach ($cols as $name => $ddl) {
        $r = $conn->query("SHOW COLUMNS FROM pengguna LIKE '" . $conn->real_escape_string($name) . "'");
        if ($r && $r->num_rows === 0) {
            @$conn->query($ddl);
        }
    }
    $idx = $conn->query("SHOW INDEX FROM pengguna WHERE Key_name = 'idx_pengguna_simad_id_guru'");
    if ($idx && $idx->num_rows === 0) {
        @$conn->query('ALTER TABLE pengguna ADD INDEX idx_pengguna_simad_id_guru (simad_id_guru)');
    }
}

function guru_sync_table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");

    return $r && $r->num_rows > 0;
}

function guru_sync_normalize_jk($raw): string
{
    $s = strtolower(trim((string) $raw));
    if ($s === 'p' || str_contains($s, 'perempuan')) {
        return 'P';
    }

    return 'L';
}

function guru_sync_norm_nama(string $nama): string
{
    $s = preg_replace('/\s+/u', ' ', trim($nama));
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

function guru_sync_parse_kelas_wali(?string $kelas_wali): array
{
    if ($kelas_wali === null || $kelas_wali === '') {
        return [];
    }
    $kelas_wali = guru_sync_sanitize_field(trim($kelas_wali));
    if ($kelas_wali === '' || $kelas_wali === '-' || $kelas_wali === '--') {
        return [];
    }
    $parts = preg_split('/\s*,\s*/', $kelas_wali) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t !== '' && $t !== '-') {
            $out[] = $t;
        }
    }

    return $out;
}

/**
 * Tahun lahir wajar untuk validasi.
 */
function guru_sync_tahun_lahir_valid(int $y): bool
{
    return $y >= 1940 && $y <= (int) date('Y') + 1;
}

/**
 * Parse tanggal lahir dari SIMAD/Rapor: utamakan format Indonesia d-m-Y / d/m/Y
 * agar sama di Windows maupun Linux (strtotime saja sering salah untuk "04-01-1979").
 */
function guru_sync_parse_tanggal_lahir($raw): ?string
{
    if ($raw === null || $raw === '' || $raw === '0000-00-00') {
        return null;
    }
    $raw = guru_sync_sanitize_field(trim((string) $raw));
    if ($raw === '' || $raw === '-' || $raw === '--') {
        return null;
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $raw) {
            $y = (int) $dt->format('Y');

            return guru_sync_tahun_lahir_valid($y) ? $dt->format('Y-m-d') : null;
        }
    }

    $formatsTanggal = ['d-m-Y', 'j-n-Y', 'd-m-y', 'j-n-y', 'd/m/Y', 'j/n/Y', 'd/m/y', 'j/n/y', 'Y-m-d H:i:s', 'Y-m-d'];
    foreach ($formatsTanggal as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $raw);
        if (!$dt instanceof DateTimeImmutable) {
            continue;
        }
        $errs = DateTimeImmutable::getLastErrors();
        if (is_array($errs) && (($errs['error_count'] ?? 0) > 0 || ($errs['warning_count'] ?? 0) > 0)) {
            continue;
        }
        $y = (int) $dt->format('Y');
        if (!guru_sync_tahun_lahir_valid($y)) {
            continue;
        }

        return $dt->format('Y-m-d');
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }
    $y = (int) date('Y', $ts);
    if (!guru_sync_tahun_lahir_valid($y)) {
        return null;
    }

    return date('Y-m-d', $ts);
}

/**
 * Tempat & tanggal lahir dari payload SIMAD (kolom terpisah atau gabungan "Jepara, 04-01-1979").
 *
 * @return array{0: string, 1: ?string} [tempat, tanggal Y-m-d|null]
 */
function guru_sync_baris_lahir_simad(array $row): array
{
    $tp = guru_sync_sanitize_field(trim((string) ($row['tempat_lahir'] ?? '')));
    $tlRaw = trim((string) ($row['tanggal_lahir'] ?? ''));
    $tgl = guru_sync_parse_tanggal_lahir($tlRaw !== '' ? $tlRaw : null);

    $comboKeys = ['tempat_tanggal_lahir', 'ttl', 'tempat_tgl_lahir', 'lahir', 'ttl_guru'];
    foreach ($comboKeys as $k) {
        if (!isset($row[$k])) {
            continue;
        }
        $combo = guru_sync_sanitize_field(trim((string) $row[$k]));
        if ($combo === '' || $combo === '-') {
            continue;
        }
        if (strpos($combo, ',') !== false) {
            $parts = preg_split('/\s*,\s*/', $combo, 2);
            $cTp = guru_sync_sanitize_field(trim((string) ($parts[0] ?? '')));
            $cTl = guru_sync_sanitize_field(trim((string) ($parts[1] ?? '')));
            if ($tp === '' && $cTp !== '' && $cTp !== '-') {
                $tp = $cTp;
            }
            if ($cTl !== '' && $cTl !== '-' && $cTl !== '--') {
                $p2 = guru_sync_parse_tanggal_lahir($cTl);
                if ($p2 !== null && $tgl === null) {
                    $tgl = $p2;
                }
            }
        }
    }

    if ($tp !== '' && strpos($tp, ',') !== false && $tgl === null) {
        $parts = preg_split('/\s*,\s*/', $tp, 2);
        $tp = guru_sync_sanitize_field(trim((string) ($parts[0] ?? '')));
        $rest = guru_sync_sanitize_field(trim((string) ($parts[1] ?? '')));
        if ($rest !== '' && $rest !== '-' && $rest !== '--') {
            $tgl = guru_sync_parse_tanggal_lahir($rest) ?? $tgl;
        }
    }

    return [$tp, $tgl];
}

function guru_sync_is_meaningful($value): bool
{
    $v = trim((string) $value);

    return $v !== '' && $v !== '-' && $v !== '--' && $v !== '0';
}

/**
 * Bersihkan string dari API JSON: BOM UTF-8, zero-width, spasi aneh.
 * Di hosting respons kadang menyertakan karakter tak terlihat sehingga tempat/nama tidak cocok atau salah map.
 */
function guru_sync_sanitize_field(string $s): string
{
    if ($s === '') {
        return '';
    }
    if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
        $s = substr($s, 3);
    }
    $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));

    return $s;
}

/**
 * Normalisasi tempat lahir untuk perbandingan (hindari "Kab." vs "Kabupaten" dianggap beda).
 */
function guru_sync_norm_tempat_lahir(string $t): string
{
    $t = guru_sync_sanitize_field($t);
    if ($t === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $t = mb_strtolower($t, 'UTF-8');
    } else {
        $t = strtolower($t);
    }
    $t = preg_replace('/^(kabupaten|kab\.?|kota)\s+/u', '', $t);
    $t = preg_replace('/\s+/u', ' ', trim($t));

    return $t;
}

function guru_sync_try_save_foto_from_url(string $url, int $simad_id): ?string
{
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'RaporMulok-SIMAD-Sync/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300 || strlen($body) > 2_500_000) {
        return null;
    }
    if (stripos($ctype, 'image/') === false && stripos($ctype, 'octet-stream') === false) {
        return null;
    }
    $ext = 'jpg';
    if (stripos($ctype, 'image/png') !== false) {
        $ext = 'png';
    } elseif (stripos($ctype, 'image/gif') !== false) {
        $ext = 'gif';
    } elseif (stripos($ctype, 'image/webp') !== false) {
        $ext = 'webp';
    }
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $fname = 'guru_simad_' . $simad_id . '_' . time() . '.' . $ext;
    if (@file_put_contents($dir . $fname, $body) === false) {
        return null;
    }

    return $fname;
}

function guru_sync_fetch_teachers_from_simad(): array
{
    $apiUrl = SIMAD_TEACHERS_API_URL . '?api_key=' . rawurlencode(SIMAD_API_KEY);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: ' . SIMAD_API_KEY]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);

        return ['success' => false, 'message' => 'CURL Error: ' . $err];
    }
    curl_close($ch);

    if ($response === false || $response === '') {
        return ['success' => false, 'message' => "Respon SIMAD kosong (HTTP $httpCode). Periksa URL API guru di config/simad.php dan API key."];
    }

    $result = json_decode($response, true);
    if (!is_array($result)) {
        $snippet = strip_tags(substr($response, 0, 200));

        return ['success' => false, 'message' => 'Respon bukan JSON valid. HTTP ' . $httpCode . ' — ' . $snippet];
    }

    if (isset($result['status']) && $result['status'] === 'success') {
        if (isset($result['data']) && is_array($result['data'])) {
            return ['success' => true, 'data' => $result['data']];
        }
        if (isset($result['teachers']) && is_array($result['teachers'])) {
            return ['success' => true, 'data' => $result['teachers']];
        }
    }

    if (isset($result['status']) && $result['status'] === 'error') {
        return ['success' => false, 'message' => $result['message'] ?? 'SIMAD mengembalikan error'];
    }

    error_log('SIMAD teachers API: ' . substr($response, 0, 500));
    $snippet = strip_tags(substr($response, 0, 150));

    return ['success' => false, 'message' => 'Format respon SIMAD tidak dikenali (bukan status=success). HTTP ' . $httpCode . ' — ' . $snippet];
}

function guru_sync_reconcile_wali_roles(mysqli $conn): void
{
    $conn->query("UPDATE pengguna p SET p.role = 'wali_kelas' WHERE p.role IN ('guru','wali_kelas') AND EXISTS (SELECT 1 FROM kelas k WHERE k.wali_kelas_id = p.id)");
    $conn->query("UPDATE pengguna p SET p.role = 'guru' WHERE p.role IN ('guru','wali_kelas') AND NOT EXISTS (SELECT 1 FROM kelas k WHERE k.wali_kelas_id = p.id)");
}

/**
 * Gabungkan user duplikat ke keeper: pindahkan FK lalu hapus baris duplikat.
 *
 * @return bool true jika baris duplikat benar-benar dihapus
 */
function guru_sync_merge_duplicate_users(mysqli $conn, int $keeperId, int $dupId): bool
{
    if ($keeperId <= 0 || $dupId <= 0 || $keeperId === $dupId) {
        return false;
    }

    if (guru_sync_table_exists($conn, 'kelas')) {
        $conn->query('UPDATE kelas SET wali_kelas_id = ' . (int) $keeperId . ' WHERE wali_kelas_id = ' . (int) $dupId);
    }

    if (guru_sync_table_exists($conn, 'mengampu_materi')) {
        $q = $conn->query('SELECT id, materi_mulok_id, kelas_id FROM mengampu_materi WHERE guru_id = ' . (int) $dupId);
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $idmm = (int) $r['id'];
                $mid = (int) $r['materi_mulok_id'];
                $kid = (int) $r['kelas_id'];
                $chk = $conn->query('SELECT id FROM mengampu_materi WHERE guru_id = ' . (int) $keeperId . ' AND materi_mulok_id = ' . $mid . ' AND kelas_id = ' . $kid . ' LIMIT 1');
                if ($chk && $chk->num_rows > 0) {
                    $conn->query('DELETE FROM mengampu_materi WHERE id = ' . $idmm);
                } else {
                    $conn->query('UPDATE mengampu_materi SET guru_id = ' . (int) $keeperId . ' WHERE id = ' . $idmm);
                }
            }
        }
    }

    if (guru_sync_table_exists($conn, 'nilai_siswa')) {
        $conn->query('UPDATE nilai_siswa SET guru_id = ' . (int) $keeperId . ' WHERE guru_id = ' . (int) $dupId);
    }

    if (guru_sync_table_exists($conn, 'nilai_kirim_status')) {
        $conn->query('UPDATE nilai_kirim_status SET user_id = ' . (int) $keeperId . ' WHERE user_id = ' . (int) $dupId);
    }

    if (guru_sync_table_exists($conn, 'aktivitas_pengguna')) {
        $conn->query('UPDATE aktivitas_pengguna SET user_id = ' . (int) $keeperId . ' WHERE user_id = ' . (int) $dupId);
    }

    $conn->query('DELETE FROM pengguna WHERE id = ' . (int) $dupId . ' AND IFNULL(is_proktor_utama,0) = 0 AND role IN (\'guru\',\'wali_kelas\')');

    return $conn->affected_rows > 0;
}

/**
 * Akun lain yang memakai NUPTK/username target — digabung ke keeper (guru/wali saja).
 */
function guru_sync_absorb_login_conflicts(mysqli $conn, int $keeperId, string $targetLogin): int
{
    if ($targetLogin === '' || strlen($targetLogin) > 64) {
        return 0;
    }
    $merged = 0;
    $stmt = $conn->prepare("SELECT id FROM pengguna WHERE id != ? AND role IN ('guru','wali_kelas') AND IFNULL(is_proktor_utama,0) = 0 AND (nuptk = ? OR username = ?)");
    $stmt->bind_param('iss', $keeperId, $targetLogin, $targetLogin);
    $stmt->execute();
    $res = $stmt->get_result();
    $others = [];
    while ($row = $res->fetch_assoc()) {
        $others[] = (int) $row['id'];
    }
    $stmt->close();
    foreach ($others as $oid) {
        if (guru_sync_merge_duplicate_users($conn, $keeperId, $oid)) {
            $merged++;
        }
    }

    return $merged;
}

/**
 * @return int[] urutan unik id pengguna lokal yang mengacu ke baris SIMAD ini
 */
function guru_sync_collect_local_ids(mysqli $conn, int $id_simad, string $nuptk_simad, string $kode_simad, string $nama_guru): array
{
    $ids = [];

    if ($id_simad > 0) {
        $st = $conn->prepare('SELECT id FROM pengguna WHERE simad_id_guru = ? AND role IN (\'guru\',\'wali_kelas\') AND IFNULL(is_proktor_utama,0) = 0');
        $st->bind_param('i', $id_simad);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $st->close();
    }

    $keys = [];
    if ($nuptk_simad !== '') {
        $keys[] = $nuptk_simad;
    }
    if ($kode_simad !== '' && $kode_simad !== $nuptk_simad) {
        $keys[] = $kode_simad;
    }
    foreach ($keys as $key) {
        $st = $conn->prepare('SELECT id FROM pengguna WHERE nuptk = ? AND role IN (\'guru\',\'wali_kelas\') AND IFNULL(is_proktor_utama,0) = 0');
        $st->bind_param('s', $key);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $st->close();
    }

    if ($ids === [] && $id_simad > 0 && guru_sync_is_meaningful($nama_guru)) {
        $st = $conn->prepare("SELECT id, simad_id_guru FROM pengguna WHERE role IN ('guru','wali_kelas') AND IFNULL(is_proktor_utama,0) = 0 AND LOWER(TRIM(nama)) = LOWER(TRIM(?))");
        $st->bind_param('s', $nama_guru);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $sid = isset($row['simad_id_guru']) ? (int) $row['simad_id_guru'] : 0;
            if ($sid !== 0 && $sid !== $id_simad) {
                continue;
            }
            $ids[] = (int) $row['id'];
        }
        $st->close();
    }

    $ids = array_values(array_unique(array_filter($ids)));

    return $ids;
}

/**
 * Pilih baris utama: prioritas sudah punya simad_id_guru sama, lalu id terkecil.
 *
 * @param int[] $ids
 */
function guru_sync_pick_keeper(mysqli $conn, array $ids, int $id_simad): int
{
    if ($ids === []) {
        return 0;
    }
    sort($ids, SORT_NUMERIC);
    if ($id_simad > 0) {
        $st = $conn->prepare('SELECT id FROM pengguna WHERE id IN (' . implode(',', array_map('intval', $ids)) . ') AND simad_id_guru = ? ORDER BY id ASC LIMIT 1');
        $st->bind_param('i', $id_simad);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if ($r && isset($r['id'])) {
            return (int) $r['id'];
        }
    }

    return (int) $ids[0];
}

/**
 * Nama untuk perbandingan: hilangkan gelar umum, rapikan spasi.
 */
function guru_sync_nama_inti(string $nama): string
{
    $n = trim($nama);
    $gelar_akhir = [
        '/\s*,?\s*S\.?\s*P\s*d\.?\s*$/iu',
        '/\s*,?\s*M\.?\s*P\s*d\.?\s*Gr\.?\s*$/iu',
        '/\s*,?\s*M\.?\s*P\s*d\.?\s*$/iu',
        '/\s*,?\s*B\.?\s*Ed\.?\s*$/iu',
        '/\s*,?\s*M\.?\s*Si\.?\s*$/iu',
    ];
    foreach ($gelar_akhir as $p) {
        $n = preg_replace($p, '', $n);
    }
    $n = preg_replace('/\s+/u', ' ', trim($n));

    return $n;
}

/**
 * Skor 0–100: kemiripan baris lokal terhadap data SIMAD (nama + tempat/tgl lahir).
 */
function guru_sync_skor_kemiripan_ke_simad(
    string $nama_simad,
    string $tmp_simad,
    ?string $tgl_simad,
    string $nama_local,
    string $tmp_local,
    ?string $tgl_local
): float {
    $a = guru_sync_nama_inti($nama_simad);
    $b = guru_sync_nama_inti($nama_local);
    if ($a === '' || $b === '') {
        return 0.0;
    }

    similar_text($a, $b, $pct);
    $score = (float) $pct;
    if ($score < 73.0) {
        return 0.0;
    }

    $ts = guru_sync_norm_date_value($tgl_simad);
    $tl = guru_sync_norm_date_value($tgl_local);
    $ms = guru_sync_is_meaningful($tmp_simad);
    $ml = guru_sync_is_meaningful($tmp_local);
    if ($ms && $ml) {
        if (guru_sync_norm_tempat_lahir($tmp_simad) === guru_sync_norm_tempat_lahir($tmp_local)) {
            $score += 14.0;
        } else {
            $score -= 32.0;
        }
    } elseif ($ms xor $ml) {
        $score += 0.5;
    }

    if ($ts !== null && $tl !== null) {
        if ($ts === $tl) {
            $score += 12.0;
        } else {
            $score -= 20.0;
        }
    } elseif ($ts !== null || $tl !== null) {
        $score += 3.0;
    }

    if (strlen($a) > 6 && strlen($b) > 6 && abs(strlen($a) - strlen($b)) <= 3) {
        if (preg_match('/^[\x20-\x7E]+$/u', $a) && preg_match('/^[\x20-\x7E]+$/u', $b)) {
            $lev = levenshtein($a, $b);
            if ($lev <= 2) {
                $score += 10.0;
            } elseif ($lev <= 4) {
                $score += 4.0;
            }
        }
    }

    return min(100.0, max(0.0, $score));
}

/**
 * Tambahkan id guru lokal yang sangat mirip SIMAD tetapi tidak tertangkap NUPTK/kode/id persis (mis. typo nama / NUPTK salah).
 *
 * @param int[] $existing_ids
 *
 * @return int[]
 */
function guru_sync_suplemen_fuzzy_ids(
    mysqli $conn,
    array $existing_ids,
    int $id_simad,
    string $nama_guru,
    string $tmp_simad,
    ?string $tgl_simad,
    string $nuptk_simad,
    string $kode_simad
): array {
    if (!guru_sync_is_meaningful($nama_guru)) {
        return [];
    }

    $existing_set = array_flip($existing_ids);
    $scores = [];

    $st = $conn->prepare("SELECT id, nama, tempat_lahir, tanggal_lahir, simad_id_guru, nuptk FROM pengguna WHERE role IN ('guru','wali_kelas') AND IFNULL(is_proktor_utama,0) = 0");
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        $sid = isset($row['simad_id_guru']) ? (int) $row['simad_id_guru'] : 0;
        if ($id_simad > 0 && $sid > 0 && $sid !== $id_simad) {
            continue;
        }
        if (isset($existing_set[$id])) {
            continue;
        }

        $ln = trim((string) ($row['nuptk'] ?? ''));
        if ($nuptk_simad !== '' && $ln === $nuptk_simad) {
            continue;
        }
        if ($kode_simad !== '' && $ln === $kode_simad) {
            continue;
        }

        $tglL = $row['tanggal_lahir'] ?? null;
        $s = guru_sync_skor_kemiripan_ke_simad(
            $nama_guru,
            $tmp_simad,
            $tgl_simad,
            guru_sync_sanitize_field(trim((string) ($row['nama'] ?? ''))),
            guru_sync_sanitize_field(trim((string) ($row['tempat_lahir'] ?? ''))),
            $tglL !== null && $tglL !== '' && $tglL !== '0000-00-00' ? (string) $tglL : null
        );
        if ($s >= 62.0) {
            $scores[$id] = $s;
        }
    }
    $st->close();

    if ($scores === []) {
        return [];
    }

    arsort($scores, SORT_NUMERIC);
    $best = (float) reset($scores);
    if ($best < 74.0) {
        return [];
    }

    $out = [];
    foreach ($scores as $id => $s) {
        if ($s >= $best - 8.0 && $s >= 67.0) {
            $out[] = (int) $id;
        }
    }

    $out = array_values(array_unique($out));
    if (count($out) > 5) {
        $out = array_slice($out, 0, 5);
    }

    return $out;
}

function guru_sync_norm_date_value($d): ?string
{
    if ($d === null || $d === '' || $d === '0000-00-00') {
        return null;
    }

    return substr(trim((string) $d), 0, 10);
}

/**
 * True jika minimal satu kolom pengguna berbeda dari nilai yang akan ditulis (selaras SIMAD).
 *
 * @param array<string,mixed> $existing
 */
function guru_sync_pengguna_perlu_update(
    array $existing,
    string $final_nama,
    string $jk,
    string $final_tmp,
    ?string $final_tgl,
    string $username_baru,
    string $nuptk_baru,
    string $foto_name,
    ?int $simad_col
): bool {
    if (trim((string) ($existing['nama'] ?? '')) !== trim($final_nama)) {
        return true;
    }
    if (($existing['jenis_kelamin'] ?? 'L') !== $jk) {
        return true;
    }
    if (trim((string) ($existing['tempat_lahir'] ?? '')) !== trim($final_tmp)) {
        return true;
    }
    if (guru_sync_norm_date_value($existing['tanggal_lahir'] ?? null) !== guru_sync_norm_date_value($final_tgl)) {
        return true;
    }
    if (trim((string) ($existing['username'] ?? '')) !== trim($username_baru)) {
        return true;
    }
    if (trim((string) ($existing['nuptk'] ?? '')) !== trim($nuptk_baru)) {
        return true;
    }
    if (trim((string) ($existing['foto'] ?? '')) !== trim($foto_name)) {
        return true;
    }
    $exSid = (int) ($existing['simad_id_guru'] ?? 0);
    if ($simad_col !== null && (int) $simad_col !== $exSid) {
        return true;
    }

    return false;
}

$conn = getConnection();
guru_sync_ensure_columns($conn);
$conn->set_charset('utf8mb4');
@$conn->query('SET NAMES utf8mb4');

try {
    $api = guru_sync_fetch_teachers_from_simad();
    if (!$api['success']) {
        throw new Exception($api['message'] ?? 'Gagal menghubungi SIMAD');
    }

    $teachers = $api['data'];
    if (!is_array($teachers) || count($teachers) === 0) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'message' => 'SIMAD tidak mengembalikan data guru (array kosong).',
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'merged_duplicates' => 0,
        ]);
        exit;
    }

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $merged_duplicates = 0;

    $conn->begin_transaction();

    foreach ($teachers as $row) {
        unset($uid);

        $nama_guru = guru_sync_sanitize_field(trim((string) ($row['nama_guru'] ?? '')));
        $nuptk_simad = guru_sync_sanitize_field(trim((string) ($row['nuptk'] ?? '')));
        $kode_simad = guru_sync_sanitize_field(trim((string) ($row['kode_guru'] ?? '')));
        $id_simad = (int) ($row['id_guru'] ?? 0);
        [$tmp_simad, $tgl_simad] = guru_sync_baris_lahir_simad($row);

        if ($id_simad <= 0 && $nuptk_simad === '' && $kode_simad === '' && !guru_sync_is_meaningful($nama_guru)) {
            $skipped++;
            continue;
        }

        $local_ids = guru_sync_collect_local_ids($conn, $id_simad, $nuptk_simad, $kode_simad, $nama_guru);
        $fuzzy_extra = guru_sync_suplemen_fuzzy_ids($conn, $local_ids, $id_simad, $nama_guru, $tmp_simad, $tgl_simad, $nuptk_simad, $kode_simad);
        $local_ids = array_values(array_unique(array_merge($local_ids, $fuzzy_extra)));

        if ($local_ids === []) {
            if (!guru_sync_is_meaningful($nama_guru)) {
                $skipped++;
                continue;
            }
            $target_new = $nuptk_simad !== '' ? $nuptk_simad : ($kode_simad !== '' ? $kode_simad : ($id_simad > 0 ? 'simad_' . $id_simad : ''));
            if ($target_new === '' || strlen($target_new) > 64) {
                $skipped++;
                continue;
            }
            $stc = $conn->prepare('SELECT id, role FROM pengguna WHERE nuptk = ? OR username = ? LIMIT 1');
            $stc->bind_param('ss', $target_new, $target_new);
            $stc->execute();
            $rowc = $stc->get_result()->fetch_assoc();
            $stc->close();
            if ($rowc) {
                $skipped++;
                continue;
            }

            $jk = guru_sync_normalize_jk($row['jenis_kelamin'] ?? 'L');
            $tmp = $tmp_simad;
            $tgl = $tgl_simad;
            $kelas_wali_list_new = guru_sync_parse_kelas_wali(isset($row['kelas_wali']) ? (string) $row['kelas_wali'] : null);
            $foto_raw_new = trim((string) ($row['foto'] ?? ''));
            $foto_ins = 'default.png';
            if ($foto_raw_new !== '' && $foto_raw_new !== '-' && preg_match('#^https?://#i', $foto_raw_new)) {
                $down = guru_sync_try_save_foto_from_url($foto_raw_new, $id_simad > 0 ? $id_simad : time());
                if ($down !== null) {
                    $foto_ins = $down;
                }
            }
            $plain = '123456';
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            if ($id_simad > 0) {
                $stmt_ins = $conn->prepare("INSERT INTO pengguna (nama, jenis_kelamin, tempat_lahir, tanggal_lahir, pendidikan, username, nuptk, password, password_plain, foto, role, simad_id_guru) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'guru', ?)");
                $stmt_ins->bind_param('sssssssssi', $nama_guru, $jk, $tmp, $tgl, $target_new, $target_new, $hash, $plain, $foto_ins, $id_simad);
            } else {
                $stmt_ins = $conn->prepare("INSERT INTO pengguna (nama, jenis_kelamin, tempat_lahir, tanggal_lahir, pendidikan, username, nuptk, password, password_plain, foto, role) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'guru')");
                $stmt_ins->bind_param('sssssssss', $nama_guru, $jk, $tmp, $tgl, $target_new, $target_new, $hash, $plain, $foto_ins);
            }
            if (!$stmt_ins->execute()) {
                $skipped++;
                $stmt_ins->close();
                continue;
            }
            $stmt_ins->close();
            $uid = (int) $conn->insert_id;
            $inserted++;

            $allowed = array_flip($kelas_wali_list_new);
            $res_kelas = $conn->prepare('SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ?');
            $res_kelas->bind_param('i', $uid);
            $res_kelas->execute();
            $rk = $res_kelas->get_result();
            while ($kr = $rk->fetch_assoc()) {
                $nm = trim((string) ($kr['nama_kelas'] ?? ''));
                if ($kelas_wali_list_new === [] || !isset($allowed[$nm])) {
                    $clr = $conn->prepare('UPDATE kelas SET wali_kelas_id = NULL WHERE id = ? AND wali_kelas_id = ?');
                    $kid = (int) $kr['id'];
                    $clr->bind_param('ii', $kid, $uid);
                    $clr->execute();
                    $clr->close();
                }
            }
            $res_kelas->close();
            foreach ($kelas_wali_list_new as $nama_kelas_simad) {
                $stmt_k = $conn->prepare('UPDATE kelas SET wali_kelas_id = ? WHERE TRIM(nama_kelas) = ? LIMIT 1');
                $stmt_k->bind_param('is', $uid, $nama_kelas_simad);
                $stmt_k->execute();
                $stmt_k->close();
            }
            unset($uid);
            continue;
        }

        $keeperId = guru_sync_pick_keeper($conn, $local_ids, $id_simad);
        foreach ($local_ids as $lid) {
            if ($lid !== $keeperId && guru_sync_merge_duplicate_users($conn, $keeperId, $lid)) {
                $merged_duplicates++;
            }
        }

        $stmt_one = $conn->prepare('SELECT id, role, is_proktor_utama, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, password, foto, nuptk, username, simad_id_guru FROM pengguna WHERE id = ? LIMIT 1');
        $stmt_one->bind_param('i', $keeperId);
        $stmt_one->execute();
        $existing = $stmt_one->get_result()->fetch_assoc();
        $stmt_one->close();

        if (!$existing || ($existing['role'] ?? '') === 'proktor' || (int) ($existing['is_proktor_utama'] ?? 0) === 1) {
            $skipped++;
            continue;
        }
        if (!in_array($existing['role'] ?? '', ['guru', 'wali_kelas'], true)) {
            $skipped++;
            continue;
        }

        $jk = guru_sync_normalize_jk($row['jenis_kelamin'] ?? 'L');
        $tmp = $tmp_simad;
        $tgl = $tgl_simad;
        $kelas_wali_list = guru_sync_parse_kelas_wali(isset($row['kelas_wali']) ? (string) $row['kelas_wali'] : null);
        $foto_raw = trim((string) ($row['foto'] ?? ''));

        $final_nama = guru_sync_is_meaningful($nama_guru) ? $nama_guru : trim((string) ($existing['nama'] ?? ''));
        $final_tmp = guru_sync_is_meaningful($tmp) ? $tmp : trim((string) ($existing['tempat_lahir'] ?? ''));
        $ex_tgl = $existing['tanggal_lahir'] ?? null;
        if ($ex_tgl === '0000-00-00') {
            $ex_tgl = null;
        }
        $final_tgl = $tgl !== null ? $tgl : $ex_tgl;
        if ($final_tgl === '0000-00-00') {
            $final_tgl = null;
        }

        $cur_nuptk = trim((string) ($existing['nuptk'] ?? ''));
        $cur_user = trim((string) ($existing['username'] ?? ''));

        $target_login = null;
        if ($nuptk_simad !== '') {
            $target_login = $nuptk_simad;
        } elseif ($kode_simad !== '') {
            $target_login = $kode_simad;
        }

        if ($target_login !== null && $target_login !== '') {
            $merged_duplicates += guru_sync_absorb_login_conflicts($conn, $keeperId, $target_login);
        }

        $nuptk_baru = $cur_nuptk;
        $username_baru = $cur_user !== '' ? $cur_user : $cur_nuptk;
        if ($target_login !== null && $target_login !== '') {
            $nuptk_baru = $target_login;
            $username_baru = $target_login;
        }

        $foto_name = trim((string) ($existing['foto'] ?? 'default.png'));
        if ($foto_name === '') {
            $foto_name = 'default.png';
        }
        $foto_boleh_ganti = ($foto_name === 'default.png' || strpos($foto_name, 'guru_simad_') === 0);
        if ($foto_raw !== '' && $foto_raw !== '-' && preg_match('#^https?://#i', $foto_raw) && $foto_boleh_ganti) {
            $down = guru_sync_try_save_foto_from_url($foto_raw, $id_simad > 0 ? $id_simad : $keeperId);
            if ($down !== null) {
                $foto_name = $down;
            }
        }

        $uid = $keeperId;
        $simad_col = $id_simad > 0 ? $id_simad : null;

        if (guru_sync_pengguna_perlu_update($existing, $final_nama, $jk, $final_tmp, $final_tgl, $username_baru, $nuptk_baru, $foto_name, $simad_col)) {
            if ($simad_col !== null) {
                $stmt_up = $conn->prepare('UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, username=?, nuptk=?, foto=?, simad_id_guru=? WHERE id=?');
                $stmt_up->bind_param(
                    'sssssssii',
                    $final_nama,
                    $jk,
                    $final_tmp,
                    $final_tgl,
                    $username_baru,
                    $nuptk_baru,
                    $foto_name,
                    $simad_col,
                    $uid
                );
            } else {
                $stmt_up = $conn->prepare('UPDATE pengguna SET nama=?, jenis_kelamin=?, tempat_lahir=?, tanggal_lahir=?, username=?, nuptk=?, foto=? WHERE id=?');
                $stmt_up->bind_param(
                    'sssssssi',
                    $final_nama,
                    $jk,
                    $final_tmp,
                    $final_tgl,
                    $username_baru,
                    $nuptk_baru,
                    $foto_name,
                    $uid
                );
            }
            if ($stmt_up->execute() && $stmt_up->affected_rows > 0) {
                $updated++;
            }
            $stmt_up->close();
        }

        $res_kelas = $conn->prepare('SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = ?');
        $res_kelas->bind_param('i', $uid);
        $res_kelas->execute();
        $rk = $res_kelas->get_result();
        $allowed = array_flip($kelas_wali_list);
        while ($kr = $rk->fetch_assoc()) {
            $nm = trim((string) ($kr['nama_kelas'] ?? ''));
            if ($kelas_wali_list === [] || !isset($allowed[$nm])) {
                $clr = $conn->prepare('UPDATE kelas SET wali_kelas_id = NULL WHERE id = ? AND wali_kelas_id = ?');
                $kid = (int) $kr['id'];
                $clr->bind_param('ii', $kid, $uid);
                $clr->execute();
                $clr->close();
            }
        }
        $res_kelas->close();

        foreach ($kelas_wali_list as $nama_kelas_simad) {
            $stmt_k = $conn->prepare('UPDATE kelas SET wali_kelas_id = ? WHERE TRIM(nama_kelas) = ? LIMIT 1');
            $stmt_k->bind_param('is', $uid, $nama_kelas_simad);
            $stmt_k->execute();
            $stmt_k->close();
        }

        unset($uid);
    }

    guru_sync_reconcile_wali_roles($conn);

    $conn->commit();

    $conn->query("CREATE TABLE IF NOT EXISTS `aktivitas_pengguna` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `nama` varchar(255) NOT NULL,
        `role` varchar(50) NOT NULL,
        `jenis_aktivitas` varchar(50) NOT NULL,
        `deskripsi` text DEFAULT NULL,
        `tabel_target` varchar(100) DEFAULT NULL,
        `record_id` int(11) DEFAULT NULL,
        `ip_address` varchar(50) DEFAULT NULL,
        `user_agent` text DEFAULT NULL,
        `waktu` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $deskripsi = "Sinkronisasi guru SIMAD: $inserted baru, $updated baris diperbarui (hanya jika beda dari SIMAD), $merged_duplicates duplikat digabung, $skipped dilewati.";
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    $user_nama = (string) ($_SESSION['nama'] ?? '');
    $user_role = (string) ($_SESSION['role'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt_log = $conn->prepare("INSERT INTO aktivitas_pengguna (user_id, nama, role, jenis_aktivitas, deskripsi, tabel_target, ip_address) VALUES (?, ?, ?, 'sync', ?, 'pengguna', ?)");
    $stmt_log->bind_param('issss', $user_id, $user_nama, $user_role, $deskripsi, $ip);
    $stmt_log->execute();
    $stmt_log->close();

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'message' => "Selesai. Guru baru: $inserted. Data diperbarui (yang belum sama dengan SIMAD): $updated. Duplikat digabung: $merged_duplicates. Dilewati: $skipped.",
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'merged_duplicates' => $merged_duplicates,
    ]);
    exit;
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
    exit;
}
