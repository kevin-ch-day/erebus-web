<?php
// app/database/services/health_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/../queries/health_queries.php';
require_once __DIR__ . '/schema_service.php';
require_once __DIR__ . '/android_service.php';
require_once __DIR__ . '/family_service.php';
require_once __DIR__ . '/vt_service.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/transient_cache.php';

function db_platform_workflow_debt_empty_summary(): array
{
    return [
        'configured_triage_statuses' => [],
        'operator_triage_statuses' => [],
        'deprecated_configured_triage_statuses' => [],
        'live_triage_statuses' => [],
        'deprecated_live_triage_statuses' => [],
        'unexpected_live_triage_statuses' => [],
        'legacy_queue_actions_total' => [],
        'legacy_queue_actions_active' => [],
    ];
}

function db_health_family_taxonomy_scorecard_cached(): array
{
    static $requestCache = null;
    if (is_array($requestCache)) {
        return $requestCache;
    }

    $cacheKey = md5(json_encode([
        'primary' => db_primary_catalog_name(),
        'version' => APP_VERSION,
        'payload_contract' => 'health_family_taxonomy_scorecard_v1',
    ], JSON_UNESCAPED_SLASHES) ?: '');

    $cached = app_transient_cache_read('health_family_taxonomy_scorecard', $cacheKey, 120);
    if (is_array($cached)) {
        $requestCache = $cached;
        return $requestCache;
    }

    $staleCached = app_transient_cache_read_stale('health_family_taxonomy_scorecard', $cacheKey);
    if (is_array($staleCached)) {
        $requestCache = $staleCached;
        return $requestCache;
    }

    $requestCache = db_family_taxonomy_scorecard();
    app_transient_cache_write('health_family_taxonomy_scorecard', $cacheKey, $requestCache);
    return $requestCache;
}

/**
 * Health payload:
 * - system control singleton
 * - DB UTC now
 */
function db_health(bool $includeDiagnostics = false): array
{
    static $requestCache = [];
    $cacheKey = $includeDiagnostics ? 'diag' : 'light';
    if (isset($requestCache[$cacheKey])) {
        return $requestCache[$cacheKey];
    }

    if ($includeDiagnostics) {
        $transientKey = md5(json_encode([
            'primary' => db_primary_catalog_name(),
            'permission_intel' => db_permission_intel_catalog_name(),
            'split_enabled' => db_permission_intel_split_enabled(),
            'version' => APP_VERSION,
            'payload_contract' => 'health_v4_workflow_debt_compat',
        ], JSON_UNESCAPED_SLASHES) ?: '');
        $cached = app_transient_cache_read('health_diag', $transientKey, 120);
        if (is_array($cached)) {
            $requestCache[$cacheKey] = $cached;
            return $requestCache[$cacheKey];
        }
        $staleCached = app_transient_cache_read_stale('health_diag', $transientKey);
        if (is_array($staleCached)) {
            $requestCache[$cacheKey] = $staleCached;
            return $requestCache[$cacheKey];
        }
    }

    $control = db_one(sql_system_control());
    $utc = db_one(sql_utc_now());
    $eligible = db_one(sql_count_eligible_now());
    $processing = db_one(sql_count_processing_now());
    $errors = db_one(sql_count_error());
    $retryWait = db_one(sql_count_retry_wait());
    $reasons = db_all(sql_reason_breakdown(20));
    $stale = db_one(sql_count_stale_claims(), ['mins' => STALE_CLAIM_MINUTES]);
    $schemaGuard = db_schema_guard();
    $schemaInventory = db_schema_inventory();
    $vtSurfaceSummary = db_vt_surface_inventory_summary($schemaInventory);
    $collationGuard = db_permission_collation_guard();
    $rollupGuard = null;
    if ($includeDiagnostics) {
        try {
            $rollupGuard = db_android_permission_rollup_guard();
        } catch (Throwable $e) {
            $rollupGuard = null;
        }
    }
    $schemaHeads = db_schema_heads();
    $workflowDebt = $includeDiagnostics ? db_platform_workflow_debt_summary() : db_platform_workflow_debt_empty_summary();
    $familyTaxonomy = db_health_family_taxonomy_scorecard_cached();
    $vtKeyStatus = db_vt_key_status_snapshot();

    $payload = [
        'utc_now' => $utc['utc_now'] ?? null,
        'catalogs' => [
            'primary' => db_primary_catalog_name(),
            'permission_intel' => db_permission_intel_catalog_name(),
            'split_enabled' => db_permission_intel_split_enabled(),
        ],
        'db_config' => db_config_diagnostics(),
        'system_control' => $control,
        'schema_guard' => $schemaGuard,
        'schema_inventory' => [
            'summary' => $schemaInventory['summary'] ?? [],
        ],
        'vt_surface_summary' => $vtSurfaceSummary,
        'family_taxonomy_summary' => $familyTaxonomy,
        'schema_heads' => $schemaHeads,
        'vt_key_posture' => $vtKeyStatus['key_posture'] ?? [],
        'vt_key_status' => $vtKeyStatus,
        'collation_guard' => $collationGuard,
        'rollup_guard' => $rollupGuard,
        'workflow_debt' => $workflowDebt,
        'diagnostics_included' => $includeDiagnostics,
        'metrics' => [
            'stale_claim_minutes' => STALE_CLAIM_MINUTES,
            'eligible_now' => (int)($eligible['eligible_now'] ?? 0),
            'processing_now' => (int)($processing['processing_now'] ?? 0),
            'stale_claims' => (int)($stale['stale_claims'] ?? 0),
            'error_count' => (int)($errors['error_count'] ?? 0),
            'retry_wait_count' => (int)($retryWait['retry_wait_count'] ?? 0),
            'reason_breakdown' => $reasons,
        ],
        'pipeline' => db_pipeline_status(true),
    ];

    $requestCache[$cacheKey] = $payload;
    if ($includeDiagnostics) {
        app_transient_cache_write('health_diag', $transientKey, $payload);
    }

    return $requestCache[$cacheKey];
}

function db_schema_heads(): array
{
    $primaryCatalog = db_primary_catalog_name();
    $piCatalog = db_permission_intel_catalog_name();
    $primaryHead = db_schema_head_for_catalog($primaryCatalog);
    $piHead = db_schema_head_for_catalog($piCatalog);

    return [
        'primary_catalog' => $primaryCatalog,
        'primary_head' => $primaryHead,
        'permission_intel_catalog' => $piCatalog,
        'permission_intel_head' => $piHead,
        'split_enabled' => db_permission_intel_split_enabled(),
        'heads_match' => $primaryHead !== null && $primaryHead === $piHead,
    ];
}

function db_schema_head_for_catalog(string $catalog): ?string
{
    $catalogSql = db_quote_identifier($catalog);
    $row = db_one("
        SELECT version
        FROM {$catalogSql}.schema_migrations
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$row || !array_key_exists('version', $row) || $row['version'] === null) {
        return null;
    }
    return (string)$row['version'];
}

function db_platform_workflow_debt_summary(): array
{
    $triageRows = db_all("
        SELECT LOWER(TRIM(triage_status)) AS triage_status, COUNT(*) AS cnt
        FROM " . db_catalog_table('android_permission_dict_unknown') . "
        GROUP BY LOWER(TRIM(triage_status))
    ");
    $configured = array_map('strtolower', perm_triage_status_keys());
    $operator = array_map('strtolower', perm_extract_keys(perm_operator_triage_statuses()));

    $live = [];
    $unexpectedLive = [];
    foreach ($triageRows as $row) {
        $status = strtolower(trim((string)($row['triage_status'] ?? '')));
        if ($status === '') {
            continue;
        }
        $count = (int)($row['cnt'] ?? 0);
        $live[] = ['key' => $status, 'count' => $count];
        if (!in_array($status, $configured, true)) {
            $unexpectedLive[] = ['key' => $status, 'count' => $count];
        }
    }

    $queueRows = db_all("
        SELECT LOWER(TRIM(queue_action)) AS queue_action, LOWER(TRIM(status)) AS status, COUNT(*) AS cnt
        FROM " . db_catalog_table('android_permission_dict_queue') . "
        GROUP BY LOWER(TRIM(queue_action)), LOWER(TRIM(status))
    ");
    $legacyQueueTotal = [];
    $legacyQueueActive = [];
    foreach ($queueRows as $row) {
        $rawAction = strtolower(trim((string)($row['queue_action'] ?? '')));
        $status = strtolower(trim((string)($row['status'] ?? '')));
        if ($rawAction === '') {
            continue;
        }
        $normalized = perm_normalize_queue_action($rawAction);
        if ($normalized === $rawAction) {
            continue;
        }
        $entry = [
            'raw' => $rawAction,
            'normalized' => $normalized,
            'status' => $status,
            'count' => (int)($row['cnt'] ?? 0),
        ];
        $legacyQueueTotal[] = $entry;
        if (in_array($status, ['queued', 'claimed', 'pending'], true)) {
            $legacyQueueActive[] = $entry;
        }
    }

    return [
        'configured_triage_statuses' => $configured,
        'operator_triage_statuses' => $operator,
        'deprecated_configured_triage_statuses' => [],
        'live_triage_statuses' => $live,
        'deprecated_live_triage_statuses' => [],
        'unexpected_live_triage_statuses' => $unexpectedLive,
        'legacy_queue_actions_total' => $legacyQueueTotal,
        'legacy_queue_actions_active' => $legacyQueueActive,
    ];
}

/**
 * Schema guard: verify key tables/columns exist to prevent drift breakage.
 */
function db_schema_guard(): array
{
    $schema = db_schema_requirements_status(db_known_schema_requirements());

    return [
        'status' => $schema['ok'] ? 'ok' : 'warn',
        'missing' => $schema['missing'],
        'missing_count' => $schema['missing_count'],
        'checked_at_utc' => $schema['checked_at_utc'],
    ];
}

/**
 * Collation guard: ensure permission_string columns can join safely.
 */
function db_permission_collation_guard(): array
{
    $columns = [
        ['table' => 'android_permission_dict_unknown', 'column' => 'permission_string', 'role' => 'workflow'],
        ['table' => 'android_permission_dict_queue', 'column' => 'permission_string', 'role' => 'queue'],
        ['table' => 'android_permission_enrich_vt_event', 'column' => 'permission_string', 'role' => 'evidence'],
        ['table' => 'android_permission_obs_sample', 'column' => 'permission_string', 'role' => 'evidence'],
    ];

    $params = [];
    $where = [];
    foreach ($columns as $idx => $col) {
        $schemaKey = 's' . $idx;
        $tableKey = 't' . $idx;
        $columnKey = 'c' . $idx;
        $where[] = '(table_schema = :' . $schemaKey . ' AND table_name = :' . $tableKey . ' AND column_name = :' . $columnKey . ')';
        $params[$schemaKey] = db_table_catalog_name($col['table']);
        $params[$tableKey] = $col['table'];
        $params[$columnKey] = $col['column'];
    }

    $sql = "
        SELECT table_name, column_name, character_set_name, collation_name
        FROM information_schema.columns
        WHERE " . implode(' OR ', $where) . "
    ";
    $rows = db_all($sql, $params);

    $found = [];
    foreach ($rows as $row) {
        $table = (string)($row['table_name'] ?? '');
        $column = (string)($row['column_name'] ?? '');
        if ($table === '' || $column === '') {
            continue;
        }
        $key = strtolower($table . '.' . $column);
        $found[$key] = [
            'table' => $table,
            'column' => $column,
            'role' => '',
            'charset' => (string)($row['character_set_name'] ?? ''),
            'collation' => (string)($row['collation_name'] ?? ''),
        ];
    }

    $missing = [];
    foreach ($columns as $col) {
        $key = strtolower($col['table'] . '.' . $col['column']);
        if (!isset($found[$key])) {
            $missing[] = [
                'table' => $col['table'],
                'column' => $col['column'],
            ];
            continue;
        }
        $found[$key]['role'] = $col['role'];
    }

    $columnsOut = array_values($found);

    $pairKeys = [
        ['android_permission_dict_unknown.permission_string', 'android_permission_enrich_vt_event.permission_string'],
        ['android_permission_dict_unknown.permission_string', 'android_permission_dict_queue.permission_string'],
        ['android_permission_dict_unknown.permission_string', 'android_permission_obs_sample.permission_string'],
    ];

    $mismatches = [];
    foreach ($pairKeys as $pair) {
        [$leftKey, $rightKey] = $pair;
        $left = $found[strtolower($leftKey)] ?? null;
        $right = $found[strtolower($rightKey)] ?? null;
        if (!$left || !$right) {
            continue;
        }
        if ($left['charset'] !== $right['charset'] || $left['collation'] !== $right['collation']) {
            $mismatches[] = [
                'left' => $leftKey,
                'right' => $rightKey,
                'left_charset' => $left['charset'],
                'right_charset' => $right['charset'],
                'left_collation' => $left['collation'],
                'right_collation' => $right['collation'],
            ];
        }
    }

    return [
        'status' => ($missing || $mismatches) ? 'warn' : 'ok',
        'columns' => $columnsOut,
        'missing' => $missing,
        'missing_count' => count($missing),
        'mismatches' => $mismatches,
        'mismatch_count' => count($mismatches),
        'checked_at_utc' => gmdate('Y-m-d H:i:s'),
    ];
}
