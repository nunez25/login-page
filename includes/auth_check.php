<?php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';

start_secure_session();
header('Cache-Control: no-store');

/**
 * Return the active account from the database, not a role trusted from session data.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!is_string($id) && !is_int($id)) {
        return null;
    }
    $id = (string) $id;
    if (preg_match('/\A[1-9][0-9]*\z/', $id) !== 1) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, username, email, role, is_active FROM users WHERE id = ? LIMIT 1'
    );
    $statement->execute([$id]);
    $record = $statement->fetch();
    if (!$record || (int) $record['is_active'] !== 1) {
        return null;
    }

    $user = $record;
    $_SESSION['user_id'] = (string) $record['id'];
    $_SESSION['username'] = $record['username'];
    $_SESSION['role'] = $record['role'];
    return $user;
}

function requireAuthenticated(): array
{
    $user = current_user();
    if ($user === null) {
        $_SESSION = [];
        redirect('/login.php');
    }
    return $user;
}

function dashboardPathForRole(string $role): string
{
    return match ($role) {
        'Super Admin' => '/superadmin/index.php',
        'Admin' => '/admin/index.php',
        'User' => '/user/index.php',
        default => '/login.php',
    };
}

function requireRole(string $role): array
{
    $user = requireAuthenticated();
    if (($user['role'] ?? null) !== $role) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

function isSuperAdmin(): bool
{
    return (current_user()['role'] ?? null) === 'Super Admin';
}

function isAdmin(): bool
{
    return (current_user()['role'] ?? null) === 'Admin';
}

function canManageUsers(): bool
{
    return isSuperAdmin() || isAdmin();
}

function canAssignRole(string $role): bool
{
    if (isSuperAdmin()) {
        return in_array($role, ['Admin', 'User'], true);
    }
    return isAdmin() && $role === 'User';
}

/**
 * A Super Admin may open their own profile to update contact details/password,
 * but management operations never allow targeting another Super Admin.
 */
function canManageUser(array $target): bool
{
    $targetRole = $target['role'] ?? null;
    if (isSuperAdmin()) {
        if ($targetRole === 'Super Admin') {
            return (string) ($target['id'] ?? '') === (string) (current_user()['id'] ?? '');
        }
        return in_array($targetRole, ['Admin', 'User'], true);
    }
    if (!isAdmin()) {
        return false;
    }
    if ($targetRole === 'Admin') {
        return (string) ($target['id'] ?? '') === (string) (current_user()['id'] ?? '');
    }
    return $targetRole === 'User';
}

function canDeleteUser(array $target): bool
{
    if (!canManageUsers()) {
        return false;
    }
    if ((string) ($target['id'] ?? '') === (string) (current_user()['id'] ?? '')) {
        return false;
    }
    return in_array($target['role'] ?? null, ['Admin', 'User'], true)
        && (isSuperAdmin() || ($target['role'] ?? null) === 'User');
}

function requireUserManager(): void
{
    requireAuthenticated();
    if (!canManageUsers()) {
        http_response_code(403);
        exit('You do not have permission to manage users.');
    }
}

function denyUserNotFound(): never
{
    http_response_code(404);
    exit('The requested user was not found.');
}
