<?php
declare(strict_types=1);

require_once __DIR__ . '/constants.php';

/**
 * Shared PDO connection (lazy, one per request). All times are UTC.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        env('DB_HOST', 'db'),
        (int) env('DB_PORT', '3306'),
        env('DB_NAME')
    );

    try {
        $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $ex) {
        error_log('[' . APP_NAME . '] DB connection failed: ' . $ex->getMessage());
        throw new RuntimeException('Database unavailable.');
    }

    return $pdo;
}
