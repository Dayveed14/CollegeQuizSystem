<?php
/**
 * Bootstraps every page: config, errors, session, database, helpers.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads/materials');

$GLOBALS['config'] = require APP_ROOT . '/config.php';

$host    = strtolower($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
$host    = preg_replace('/:\d+$/', '', $host);
$isPrivateIp = filter_var($host, FILTER_VALIDATE_IP)
    && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
// localhost, 127.0.0.1, LAN addresses like 192.168.x.x, and *.test / *.local all count as XAMPP
$isLocal = PHP_SAPI === 'cli' || $isPrivateIp
    || in_array($host, ['localhost', '::1'], true)
    || preg_match('/\.(test|local|localhost)$/', $host);
$GLOBALS['env'] = $GLOBALS['config'][$isLocal ? 'local' : 'live'];

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'UTC');

if (!empty($GLOBALS['env']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ---- Session -------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('cqs_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Database ------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $env = $GLOBALS['env'];
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $env['db_host'], $env['db_name']);
    try {
        $pdo = new PDO($dsn, $env['db_user'], $env['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL's clock in line with PHP's timezone
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    } catch (PDOException $ex) {
        http_response_code(500);
        $detail = !empty($env['debug']) ? '<p><code>' . e($ex->getMessage()) . '</code></p>' : '';
        exit('<!doctype html><meta charset="utf-8"><title>Database unavailable</title>'
            . '<div style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 16px">'
            . '<h1 style="font-size:22px">Can\'t reach the database</h1>'
            . '<p>Check the database settings in <b>config.php</b> and make sure <b>database.sql</b> has been imported.</p>'
            . $detail . '</div>');
    }
    return $pdo;
}

/** Run a prepared query and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// ---- Output helpers ------------------------------------------------
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_name(): string
{
    return $GLOBALS['config']['app_name'] ?? 'College Quiz System';
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function fmt_time(?string $t): string
{
    return $t ? date('g:i A', strtotime($t)) : '';
}

function fmt_date(?string $d, bool $withTime = true): string
{
    if (!$d) return '—';
    return date($withTime ? 'j M Y, g:i A' : 'j M Y', strtotime($d));
}

function fmt_bytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

function percent(int $score, int $total): int
{
    return $total > 0 ? (int) round($score * 100 / $total) : 0;
}

/** Letter grade on a common 5-point college scale. */
function grade_for(int $pct): string
{
    if ($pct >= 70) return 'A';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C';
    if ($pct >= 45) return 'D';
    if ($pct >= 40) return 'E';
    return 'F';
}

// ---- CSRF ----------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        flash('error', 'Your session expired. Please try that again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---- Auth ----------------------------------------------------------
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) return $user = null;
    $row = q('SELECT id, reg_number, first_name, last_name, email, phone, role FROM users WHERE id = ?', [$id])->fetch();
    if (!$row) {
        unset($_SESSION['user_id']);
        return $user = null;
    }
    return $user = $row;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('error', 'Please sign in to continue.');
        redirect('index.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        flash('error', 'That page is for administrators only.');
        redirect('dashboard.php');
    }
    return $u;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function int_param(array $src, string $key): int
{
    return isset($src[$key]) && ctype_digit((string) $src[$key]) ? (int) $src[$key] : 0;
}

require __DIR__ . '/layout.php';
