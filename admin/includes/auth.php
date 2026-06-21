<?php
require_once __DIR__ . '/../../config.php';

// ── Biztonságos session beállítások ───────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // Tömbös forma PHP 7.3+, régebbi PHP-n ini_set-tel csináljuk
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => $is_https ? 'Strict' : 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $is_https, true);
    }
    session_start();
}

// ── Biztonsági HTTP fejlécek ───────────────────────────────
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self';");
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// ── Session fixation védelem ───────────────────────────────
function session_regenerate_safe(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function require_login(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
    // Session lejárat: 2 óra inaktivitás után
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
        session_unset();
        session_destroy();
        header('Location: ' . SITE_URL . '/admin/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function current_user(): array {
    static $user = null;
    if ($user === null && !empty($_SESSION['admin_id'])) {
        $stmt = db()->prepare('SELECT id, username, full_name, role, last_login FROM admin_users WHERE id=?');
        $stmt->execute([$_SESSION['admin_id']]);
        $user = $stmt->fetch() ?: [];
    }
    return $user ?? [];
}

function is_superadmin(): bool {
    return (current_user()['role'] ?? '') === 'superadmin';
}

function is_campaign_manager(): bool {
    return (current_user()['role'] ?? '') === 'kampanyfonok';
}

// ── CSRF védelem ───────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $token)) {
        http_response_code(403);
        error_log('CSRF mismatch from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        die('Érvénytelen kérés.');
    }
}

// ── Brute-force védelem (login kísérletszámlálás) ─────────
function login_rate_limit(string $identifier): bool {
    $key      = 'login_attempts_' . md5($identifier);
    $maxTries = 10;
    $window   = 900; // 15 perc

    $attempts = $_SESSION[$key]['count'] ?? 0;
    $first    = $_SESSION[$key]['first']  ?? time();

    if ((time() - $first) > $window) {
        // Ablak lejárt, reset
        $_SESSION[$key] = ['count' => 1, 'first' => time()];
        return true;
    }

    if ($attempts >= $maxTries) {
        return false; // Blokkolt
    }

    $_SESSION[$key]['count'] = $attempts + 1;
    return true;
}

function login_reset_limit(string $identifier): void {
    unset($_SESSION['login_attempts_' . md5($identifier)]);
}

// ── Naplózás ───────────────────────────────────────────────
function log_action(string $action, string $details = ''): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, details, ip) VALUES (?,?,?,?)'
        );
        $stmt->execute([
            $_SESSION['admin_id'] ?? null,
            $action,
            mb_substr($details, 0, 1000), // Max hossz
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Exception $e) {
        // Naplózási hiba nem akaszthatja meg a folyamatot
    }
}

// ── Flash üzenetek ─────────────────────────────────────────
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = [
        'type' => in_array($type, ['success','error','warning','info']) ? $type : 'info',
        'msg'  => htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'),
    ];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Fájl feltöltés validálás (központi) ───────────────────
function validate_image_upload(array $file): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Feltöltési hiba (kód: ' . $file['error'] . ').';
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return 'A fájl mérete meghaladja a ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB-os határt.';
    }

    // Valódi MIME ellenőrzés finfo-val (nem a kliens által küldött értékkel)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimes, true)) {
        return 'Csak kép fájl (JPG, PNG, WEBP, GIF) tölthető fel!';
    }

    // Dupla kiterjesztés elleni védelem + whitelist
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    return null; // OK
}

function safe_image_ext(string $tmpName): string {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpName);
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ][$mime] ?? 'bin';
}

function safe_upload_filename(string $prefix, string $tmpName): string {
    $ext = safe_image_ext($tmpName);
    // random_bytes helyett cryptographically secure filename
    return $prefix . bin2hex(random_bytes(16)) . '.' . $ext;
}
