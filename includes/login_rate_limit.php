<?php
declare(strict_types=1);

function login_lockout_seconds(int $attempts): int
{
    return match (true) {
        $attempts >= 15 => 1800,
        $attempts === 10 => 900,
        $attempts === 3 => 300,
        default => 0,
    };
}

function login_retry_after(PDO $pdo, string $username, string $ip): int
{
    $select = $pdo->prepare('SELECT GREATEST(0, COALESCE(MAX(
        TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), lockout_until)), 0))
        FROM login_attempts WHERE (scope = ? AND subject = ?) OR (scope = ? AND subject = ?)');
    $select->execute(['account', $username, 'ip', $ip]);
    return (int) $select->fetchColumn();
}

function authenticate_login(PDO $pdo, string $username, string $password, string $ip, bool $captchaValid = true): array
{
    $validUsername = $username !== '' && mb_check_encoding($username, 'UTF-8') && mb_strlen($username, 'UTF-8') <= 50;
    $subjects = $validUsername ? ['account' => $username, 'ip' => $ip] : ['ip' => $ip];
    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare('INSERT INTO login_attempts (scope, subject) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE subject = login_attempts.subject');
        $select = $pdo->prepare('SELECT failed_attempts,
            GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), lockout_until), 0)) AS retry_after
            FROM login_attempts WHERE scope = ? AND subject = ? FOR UPDATE');
        $attempts = [];
        $retryAfter = 0;

        foreach ($subjects as $scope => $subject) {
            $insert->execute([$scope, $subject]);
            $select->execute([$scope, $subject]);
            $state = $select->fetch();
            $attempts[$scope] = (int) $state['failed_attempts'];
            $retryAfter = max($retryAfter, (int) $state['retry_after']);
        }

        if ($retryAfter > 0) {
            $pdo->commit();
            return ['user' => null, 'retry_after' => $retryAfter];
        }

        $user = null;
        $validPassword = false;
        if ($captchaValid && $validUsername && $password !== '') {
            $selectUser = $pdo->prepare('SELECT id, username, password_hash, role, is_active
                FROM users WHERE username = ? LIMIT 1');
            $selectUser->execute([$username]);
            $user = $selectUser->fetch();
            $hash = $user ? $user['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
            $validPassword = password_verify($password, $hash);
        }

        if ($user && $validPassword && (int) $user['is_active'] === 1) {
            $reset = $pdo->prepare('UPDATE login_attempts SET failed_attempts = 0,
                lockout_until = NULL WHERE scope = ? AND subject = ?');
            foreach ($subjects as $scope => $subject) {
                $reset->execute([$scope, $subject]);
            }
            $pdo->commit();
            return ['user' => $user, 'retry_after' => 0];
        }

        $update = $pdo->prepare('UPDATE login_attempts SET failed_attempts = ?,
            lockout_until = CASE WHEN ? > 0 THEN TIMESTAMPADD(SECOND, ?, UTC_TIMESTAMP()) ELSE NULL END
            WHERE scope = ? AND subject = ?');
        foreach ($subjects as $scope => $subject) {
            $count = min($attempts[$scope] + 1, 4294967295);
            $seconds = login_lockout_seconds($count);
            $update->execute([$count, $seconds, $seconds, $scope, $subject]);
            $retryAfter = max($retryAfter, $seconds);
        }

        $pdo->commit();
        return ['user' => null, 'retry_after' => $retryAfter];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}