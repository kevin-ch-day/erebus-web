<?php
// app/database/db_engine.php
// Main DB "engine" utilities: shared helpers + a thin query runner.
// Keep this file small and boring; put actual queries in db_queries.php.

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';

/**
 * Fetch a single row (or null).
 */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch all rows (possibly empty).
 */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a statement and return affected row count.
 */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Cache-busting generation token derived from the latest applied migration.
 * This keeps transient UI caches from surviving schema/data repair scripts.
 */
function db_cache_generation_token(): string
{
    static $token = null;
    if (is_string($token) && $token !== '') {
        return $token;
    }

    try {
        $row = db_one('SELECT MAX(version) AS max_version FROM schema_migrations');
        $version = trim((string)($row['max_version'] ?? ''));
        $token = $version !== '' ? $version : '0';
    } catch (Throwable $e) {
        $token = '0';
    }

    return $token;
}

/**
 * Run a transaction with automatic commit/rollback.
 */
function db_tx(callable $fn)
{
    $pdo = db();
    $startedTx = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTx = true;
    }
    try {
        $result = $fn($pdo);
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($startedTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Utility: clamp an integer value (used for pagination).
 */
function clamp_int($value, int $min, int $max, int $default): int
{
    if (!is_numeric($value)) return $default;
    $v = (int)$value;
    if ($v < $min) return $min;
    if ($v > $max) return $max;
    return $v;
}

/**
 * Guardrail for web-side writes: only approved columns can be changed.
 */
function db_assert_write_allowlist(string $table, array $columns): void
{
    static $allowlist = [
        'malware_sample_catalog' => [
            'sample_label',
            'family_label',
            'classification_primary',
            'classification_subtype',
            'record_updated_at_utc',
        ],
    ];

    $allowed = $allowlist[$table] ?? [];
    $bad = [];
    foreach ($columns as $column) {
        if (!in_array($column, $allowed, true)) {
            $bad[] = $column;
        }
    }
    if ($bad) {
        throw new RuntimeException(
            'Write guard violation for ' . $table . ': ' . implode(', ', $bad)
        );
    }
}
