<?php
// ── Produkciós hibaelrejtés ────────────────────────────────
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
// ini_set('error_log', '/var/log/itsz_php_errors.log'); // Állítsd be a szerveren

// ── Adatbázis beállítások ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'itsz_gyor');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── Automatikus URL felismerés ────────────────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/admin');
$basePath = rtrim($basePath, '/');

define('SITE_URL', $protocol . '://' . $host . $basePath);

// ── Feltöltési könyvtár ────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');
define('MAX_FILE_SIZE', 8 * 1024 * 1024);

// ── Session neve ───────────────────────────────────────────
define('SESSION_NAME', 'itsz_admin');

// ── PDO kapcsolat ──────────────────────────────────────────
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            die('Az oldal átmenetileg nem érhető el. Kérjük próbáld újra később.');
        }
    }

    return $pdo;
}

// ── Tartalom lekérő segédfüggvények ───────────────────────
function get_content(string $section, string $key, string $default = ''): string {
    try {
        $stmt = db()->prepare('SELECT value FROM site_content WHERE section=? AND key_name=?');
        $stmt->execute([$section, $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function get_setting(string $key, string $default = ''): string {
    return get_content('theme', $key, $default);
}

function set_setting(string $key, string $value, string $label = '', int $sort_order = 0): bool {
    try {
        $stmt = db()->prepare('SELECT id FROM site_content WHERE section=? AND key_name=?');
        $stmt->execute(['theme', $key]);
        $row = $stmt->fetch();

        if ($row) {
            $stmt = db()->prepare('UPDATE site_content SET value=? WHERE section=? AND key_name=?');
            return $stmt->execute([$value, 'theme', $key]);
        }

        if ($label === '') $label = $key;

        $stmt = db()->prepare('INSERT INTO site_content (section, key_name, label, value, type, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        return $stmt->execute(['theme', $key, $label, $value, 'text', $sort_order]);
    } catch (Exception $e) {
        return false;
    }
}

function get_section(string $section): array {
    try {
        $stmt = db()->prepare('SELECT key_name, value FROM site_content WHERE section=? ORDER BY sort_order');
        $stmt->execute([$section]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['key_name']] = $row['value'];
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}
