<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function fail(string $message, int $status = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($status);
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT role FROM users WHERE username = ? LIMIT 1 FOR UPDATE');
    $insert = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
    );
    $created = [];

    foreach ([
        ['sadmin', 'Super Admin'],
        ['admin', 'Admin'],
    ] as [$username, $role]) {
        $find->execute([$username]);
        $existingRole = $find->fetchColumn();
        if ($existingRole !== false) {
            if ($existingRole !== $role) {
                throw new RuntimeException(
                    "The existing {$username} account is not a {$role}; no accounts were changed."
                );
            }
            fwrite(STDOUT, "{$username} already exists; left unchanged." . PHP_EOL);
            continue;
        }

        $hash = password_hash('123', PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash an initial account password.');
        }
        $insert->execute([$username, $hash, $role]);
        $created[] = $username;
        fwrite(STDOUT, "Created {$role} account: {$username}." . PHP_EOL);
    }

    $pdo->commit();
    if ($created !== []) {
        fwrite(
            STDOUT,
            'Newly created accounts (' . implode(', ', $created)
                . ') use password "123"; change those passwords immediately.' . PHP_EOL
        );
    } else {
        fwrite(STDOUT, 'No accounts needed seeding; existing passwords were left unchanged.' . PHP_EOL);
    }
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Silverwood Academy SIS] Initial account seeding failed: ' . $exception->getMessage());
    fail($exception->getMessage());
}
