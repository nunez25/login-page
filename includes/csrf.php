<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Per-session CSRF token.
 */
function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input to embed in every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate the posted token against the session token.
 */
function csrf_verify(): bool
{
    start_secure_session();
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    return is_string($posted) && $stored !== '' && hash_equals($stored, $posted);
}
