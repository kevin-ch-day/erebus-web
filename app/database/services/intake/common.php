<?php
declare(strict_types=1);

function db_ingest_table_exists(string $table): bool
{
    $row = db_one(
        "
        SELECT 1 AS ok
        FROM information_schema.tables
        WHERE table_schema = :schema_name
          AND table_name = :table_name
        LIMIT 1
        ",
        [
            'schema_name' => db_table_catalog_name($table),
            'table_name' => $table,
        ]
    );

    return $row !== null;
}

function db_ingest_column_is_auto_increment(string $table, string $column): ?bool
{
    $row = db_one(
        "
        SELECT extra
        FROM information_schema.columns
        WHERE table_schema = :schema_name
          AND table_name = :table_name
          AND column_name = :column_name
        LIMIT 1
        ",
        [
            'schema_name' => db_table_catalog_name($table),
            'table_name' => $table,
            'column_name' => $column,
        ]
    );
    if ($row === null) {
        return null;
    }

    return str_contains(strtolower((string)($row['extra'] ?? '')), 'auto_increment');
}

function db_ingest_has_index(string $table, string $column): ?bool
{
    $row = db_one(
        "
        SELECT 1 AS ok
        FROM information_schema.statistics
        WHERE table_schema = :schema_name
          AND table_name = :table_name
          AND column_name = :column_name
        LIMIT 1
        ",
        [
            'schema_name' => db_table_catalog_name($table),
            'table_name' => $table,
            'column_name' => $column,
        ]
    );

    return $row !== null;
}

function db_ingest_count_sql(string $sql, array $params = []): ?int
{
    try {
        $row = db_one($sql, $params);
    } catch (Throwable $e) {
        return null;
    }

    if ($row === null) {
        return null;
    }

    return (int)($row['cnt'] ?? 0);
}

function db_ingest_friendly_cohort_kind(?string $lane): string
{
    $lane = strtolower(trim((string)$lane));
    if ($lane === 'raw_hash_reservoir') {
        return 'generic discovery-hash intake';
    }
    if ($lane === '') {
        return 'generic intake';
    }
    return str_replace('_', ' ', $lane);
}

function db_ingest_friendly_source_label(?string $value): string
{
    $raw = trim((string)$value);
    $normalized = strtolower($raw);
    $prefix = 'raw_hash_reservoir_';
    if (str_starts_with($normalized, $prefix)) {
        $suffix = substr($raw, strlen($prefix));
        if (preg_match('/^\d{8}$/', $suffix) === 1) {
            return 'discovery hash list ' . substr($suffix, 0, 4) . '-' . substr($suffix, 4, 2) . '-' . substr($suffix, 6, 2);
        }
        return 'discovery hash list';
    }
    return $raw !== '' ? $raw : '-';
}

function db_ingest_is_android_ingest_source(?string $value): bool
{
    $source = strtolower(trim((string)$value));
    if ($source === '') {
        return false;
    }
    if (str_starts_with($source, 'external_ioc_zimperium_')) {
        return true;
    }

    return str_contains($source, 'android') || str_contains($source, 'apk');
}

function db_ingest_is_generic_reservoir_source(?string $value): bool
{
    $source = strtolower(trim((string)$value));
    if ($source === '') {
        return false;
    }

    return str_starts_with($source, 'raw_hash_reservoir_');
}

function db_ingest_source_class_label(?string $value): string
{
    if (db_ingest_is_android_ingest_source($value)) {
        return 'Android feed';
    }
    if (db_ingest_is_generic_reservoir_source($value)) {
        return 'Generic discovery reservoir';
    }

    return 'Other feed';
}

function db_ingest_queue_status_label(int $pending, int $processing, int $failed, int $done, int $total): string
{
    if ($total === 0) {
        return 'empty';
    }
    if ($pending > 0 && $processing > 0) {
        return 'Active Pending=' . number_format($pending) . ' | Processing residue=' . number_format($processing);
    }
    if ($pending > 0) {
        return 'Active Pending=' . number_format($pending);
    }
    if ($processing > 0) {
        return 'Needs Recovery | Processing residue=' . number_format($processing);
    }
    if ($failed > 0) {
        return 'Failed Only | failed=' . number_format($failed);
    }
    return 'Idle | done=' . number_format($done) . ' / total=' . number_format($total);
}

function db_ingest_import_readiness_label(?bool $queueAutoIncrement): string
{
    if ($queueAutoIncrement === false) {
        return 'BLOCKED — queue ingest_id is not AUTO_INCREMENT';
    }
    if ($queueAutoIncrement === true) {
        return 'OK';
    }
    return 'unavailable';
}

function db_ingest_scale_warning_label(?bool $registryMd5Indexed, ?bool $registrySha1Indexed): string
{
    if ($registryMd5Indexed === false || $registrySha1Indexed === false) {
        return 'registry md5/sha1 indexes missing';
    }
    if ($registryMd5Indexed === true && $registrySha1Indexed === true) {
        return 'none';
    }
    return 'unavailable';
}

function db_ingest_filter_sql(?string $sourceFilter, ?string $laneFilter, string $sourceExpr, string $laneExpr, array &$params): string
{
    $parts = [];
    if ($sourceFilter !== null && $sourceFilter !== '') {
        $parts[] = $sourceExpr . ' = :source_filter';
        $params['source_filter'] = $sourceFilter;
    }
    if ($laneFilter !== null && $laneFilter !== '') {
        $parts[] = $laneExpr . ' = :lane_filter';
        $params['lane_filter'] = $laneFilter;
    }
    return $parts === [] ? '' : (' AND ' . implode(' AND ', $parts));
}
