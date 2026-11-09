<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function fail(string $message, int $status = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($status);
}

function set_terminal_echo(bool $enabled): void
{
    $process = proc_open(
        ['stty', $enabled ? 'echo' : '-echo'],
        [0 => STDIN, 1 => STDOUT, 2 => STDERR],
        $pipes
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to configure terminal input.');
    }

    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Unable to configure terminal input.');
    }
}

function read_password(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    set_terminal_echo(false);

    try {
        $password = fgets(STDIN);
    } finally {
        set_terminal_echo(true);
        fwrite(STDOUT, PHP_EOL);
    }

    if ($password === false) {
        throw new RuntimeException('Unable to read password from terminal.');
    }

    return rtrim($password, "\r\n");
}

if ($argc !== 2) {
    fail('Usage: php cli/create_admin.php <username>', 2);
}

if (!stream_isatty(STDIN)) {
    fail('Run this command in an interactive terminal so passwords are not exposed.');
}

$username = $argv[1];
if (preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) !== 1) {
    fail('Username must be 3-50 characters: letters, numbers, dot, underscore, or hyphen.', 2);
}

try {
    $password = read_password('Password (12-72 characters): ');
    $confirmation = read_password('Confirm password: ');

    if (strlen($password) < 12 || strlen($password) > 72) {
        fail('Password must be 12-72 bytes long.', 2);
    }
    if (!hash_equals($password, $confirmation)) {
        fail('Passwords do not match.', 2);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Unable to hash password.');
    }

    $statement = db()->prepare(
        "INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, 'Super Admin')"
    );
    $statement->execute([
        'username' => $username,
        'password_hash' => $passwordHash,
    ]);

    fwrite(STDOUT, "Super Admin account created for {$username}." . PHP_EOL);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fail('That username already exists.', 2);
    }
    error_log('[Silverwood Academy SIS] Super Admin creation failed: ' . $exception->getMessage());
    fail('Could not create administrator account. Check the application logs.');
} catch (Throwable $exception) {
    error_log('[Silverwood Academy SIS] Super Admin creation failed: ' . $exception->getMessage());
    fail('Could not create administrator account. Check the application logs.');
}
