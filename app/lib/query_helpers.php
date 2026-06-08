<?php
// app/lib/query_helpers.php
// Shared query helpers for safe, whitelisted filters.

declare(strict_types=1);

/**
 * Add an equality filter if value is non-empty.
 */
function add_where_equals(array &$where, array &$params, string $expr, string $paramKey, ?string $value): void
{
    $value = trim((string)$value);
    if ($value === '') return;
    $where[] = "{$expr} = :{$paramKey}";
    $params[$paramKey] = $value;
}

/**
 * Add a LIKE filter (contains) if value is non-empty.
 */
function add_where_like(array &$where, array &$params, string $expr, string $paramKey, ?string $value): void
{
    $value = trim((string)$value);
    if ($value === '') return;
    $where[] = "{$expr} LIKE :{$paramKey}";
    $params[$paramKey] = '%' . $value . '%';
}

/**
 * Add a prefix filter (value%).
 */
function add_where_prefix(array &$where, array &$params, string $expr, string $paramKey, ?string $value): void
{
    $value = trim((string)$value);
    if ($value === '') return;
    $where[] = "{$expr} LIKE :{$paramKey}";
    $params[$paramKey] = $value . '%';
}

/**
 * Add an IN filter (values must be pre-validated).
 */
function add_where_in(array &$where, array &$params, string $expr, string $paramKey, array $values): void
{
    $values = array_values(array_filter($values, static fn($v) => $v !== null && $v !== ''));
    if (!$values) return;

    $placeholders = [];
    foreach ($values as $idx => $value) {
        $key = "{$paramKey}{$idx}";
        $placeholders[] = ":{$key}";
        $params[$key] = $value;
    }
    $where[] = "{$expr} IN (" . implode(',', $placeholders) . ")";
}

/**
 * Add a prefix OR filter for a list of prefixes (value%).
 */
function add_where_prefixes(array &$where, array &$params, string $expr, string $paramKey, array $prefixes): void
{
    $prefixes = array_values(array_filter(array_map('strval', $prefixes)));
    if (!$prefixes) return;

    $parts = [];
    foreach ($prefixes as $idx => $prefix) {
        $key = "{$paramKey}{$idx}";
        $parts[] = "{$expr} LIKE :{$key}";
        $params[$key] = $prefix . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

/**
 * Resolve a safe ORDER BY clause.
 */
function build_order_by(string $requested, array $allowed, string $default): string
{
    $key = $requested !== '' ? $requested : $default;
    $column = $allowed[$key] ?? $allowed[$default] ?? null;
    return $column ? " ORDER BY {$column}" : '';
}

/**
 * Bind named parameters with optional int keys.
 */
function bind_named_params(PDOStatement $stmt, array $params, array $intKeys = ['limit', 'offset']): void
{
    foreach ($params as $key => $value) {
        $paramKey = ':' . $key;
        if (in_array($key, $intKeys, true)) {
            $stmt->bindValue($paramKey, (int)$value, PDO::PARAM_INT);
            continue;
        }
        $stmt->bindValue($paramKey, $value);
    }
}

