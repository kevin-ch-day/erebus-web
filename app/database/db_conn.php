<?php
// app/database/db_conn.php
// PDO connection factory (DB/session is always UTC)

declare(strict_types=1);

// v1 note: app_config.php is the canonical app config filename (not config.php)
require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/db_config.php';

/**
 * Return the singleton PDO connection.
 *
 * Design goals:
 * - One connection per request (cached via static).
 * - UTF8MB4 everywhere.
 * - DB/session time is forced to UTC (authoritative time).
 * - Strict-ish SQL mode to surface bugs early.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Guardrails: fail early with useful errors if config is wrong.
    $required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
    foreach ($required as $k) {
        if (!defined($k) || trim((string)constant($k)) === '') {
            throw new RuntimeException("DB config missing required constant: {$k}");
        }
    }

    $host = (string)DB_HOST;
    $port = (int)(defined('DB_PORT') ? DB_PORT : 3306);
    $name = (string)DB_NAME;
    $user = (string)DB_USER;
    $pass = (string)(defined('DB_PASS') ? DB_PASS : '');
    $socket = (string)(defined('DB_SOCKET') ? DB_SOCKET : '');

    if (db_permission_intel_split_enabled() && !db_permission_intel_connection_matches_primary()) {
        throw new RuntimeException(
            'Split Permission Intel credentials differ from primary DB credentials. ' .
            'This web app currently supports split catalogs only when both catalogs are reachable ' .
            'through the same MariaDB host/user/socket connection.'
        );
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    $dsnCandidates = [];
    if ($socket !== '') {
        $dsnCandidates[] = [
            'dsn' => sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $name),
            'label' => 'socket:' . $socket,
        ];
    }
    $dsnCandidates[] = [
        'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
        'label' => 'tcp:' . $host . ':' . $port,
    ];
    if ($socket === '' && in_array($host, ['127.0.0.1', 'localhost'], true)) {
        foreach (['/run/mysqld/mysqld.sock', '/var/run/mysqld/mysqld.sock', '/run/mariadb/mariadb.sock', '/var/lib/mysql/mysql.sock'] as $candidate) {
            if (file_exists($candidate)) {
                $dsnCandidates[] = [
                    'dsn' => sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $candidate, $name),
                    'label' => 'socket:' . $candidate,
                ];
            }
        }
    }

    $errors = [];
    foreach ($dsnCandidates as $candidate) {
        try {
            $pdo = new PDO($candidate['dsn'], $user, $pass, $options);

            // Enforce UTC session (critical: scheduling/backoff uses DB UTC).
            $pdo->exec("SET time_zone = '+00:00'");

            // Strict enough for dev; adjust centrally here if needed.
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

            // Optional: set a connection name for easier debugging in SHOW PROCESSLIST.
            try {
                $pdo->exec("SET SESSION application_name = 'erebus-web-v1'");
            } catch (Throwable $ignored) {
                // Not supported on all servers; safe to ignore.
            }

            return $pdo;
        } catch (Throwable $e) {
            $errors[] = $candidate['label'] . ' => ' . $e->getMessage();
        }
    }

    throw new RuntimeException(
        "Database connection failed. Check DB_HOST={$host}, DB_PORT={$port}, DB_NAME={$name}, DB_USER={$user}, DB_SOCKET={$socket} and ensure MariaDB/MySQL is running. Attempts: " . implode(' | ', $errors)
    );
}

/**
 * Quick DB UTC time check (useful for health endpoints).
 */
function db_utc_now(): ?string
{
    $row = db()->query("SELECT UTC_TIMESTAMP() AS utc_now")->fetch();
    return $row['utc_now'] ?? null;
}

/**
 * Quick DB ping (returns true/false).
 * Useful for a health endpoint without throwing fatal errors.
 */
function db_ping(): bool
{
    try {
        $row = db()->query("SELECT 1 AS ok")->fetch();
        return isset($row['ok']) && (int)$row['ok'] === 1;
    } catch (Throwable $e) {
        return false;
    }
}
