<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Escape a value for HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Build a page <title> using the configured school identity.
 */
function page_title(string $title): string
{
    return $title . ' | ' . SCHOOL_SHORT_NAME . ' SIS';
}

/**
 * Public URL of the school logo, or null if the file has not been added yet.
 */
function logo_url(): ?string
{
    $file = PUBLIC_PATH . '/assets/img/logo.png';
    return is_file($file) ? '/assets/img/logo.png?v=' . filemtime($file) : null;
}

/**
 * True when the client connection is HTTPS (directly or via the Cloudflare tunnel).
 */
function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on';
}

/**
 * Client IP as set by nginx (real IP from CF-Connecting-IP).
 */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

/**
 * Start the session with hardened cookie settings (idempotent).
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Redirect to a local path and stop.
 */
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

/**
 * Log unexpected errors and show a generic message instead of details.
 */
set_exception_handler(static function (Throwable $ex): void {
    error_log('[' . APP_NAME . '] ' . $ex::class . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>' . e(page_title('Error')) . '</title>'
        . '<p style="font-family:sans-serif;padding:2rem">Something went wrong. Please try again later.</p>';
});
