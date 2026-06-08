<?php
// app/database/services/samples_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/../queries/samples_queries.php';
require_once __DIR__ . '/../queries/runs_queries.php';
require_once __DIR__ . '/runs_service.php';

/**
 * Build WHERE clauses and params for samples list/count queries.
 */
function build_samples_filters(array $filters): array
{
    $where = [];
    $params = [];
    $familyAlignmentExpr = "
        CASE
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'unlabeled'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NOT NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NULL
                THEN 'catalog_only'
            WHEN NULLIF(TRIM(COALESCE(c.family_label, '')), '') IS NULL
                 AND NULLIF(TRIM(COALESCE(sig.popular_threat_name, '')), '') IS NOT NULL
                THEN 'signal_only'
            WHEN LOWER(TRIM(COALESCE(c.family_label, ''))) = LOWER(TRIM(COALESCE(sig.popular_threat_name, '')))
                THEN 'aligned'
            ELSE 'mismatch'
        END
    ";
    $genericFamilyExpr = "
        LOWER(TRIM(COALESCE(c.family_label, ''))) IN ('trojan', 'adware', 'android', 'malware', 'riskware', 'generic', 'unknown')
    ";

    if (!empty($filters['status'])) {
        $where[] = 's.vt_status_code = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['reason'])) {
        $where[] = 's.reason_code = :reason';
        $params['reason'] = $filters['reason'];
    }

    if (!empty($filters['q'])) {
        $where[] = '(c.sha256 = :q_exact OR c.sha256 LIKE :q_like_sha OR c.sample_label LIKE :q_like_label OR c.family_label LIKE :q_like_family)';
        $params['q_exact'] = $filters['q'];
        $params['q_like_sha'] = '%' . $filters['q'] . '%';
        $params['q_like_label'] = '%' . $filters['q'] . '%';
        $params['q_like_family'] = '%' . $filters['q'] . '%';
    }

    if (!empty($filters['family'])) {
        $where[] = 'c.family_label LIKE :family_like';
        $params['family_like'] = '%' . $filters['family'] . '%';
    }

    if (!empty($filters['family_alignment'])) {
        $alignment = strtolower(trim((string)$filters['family_alignment']));
        if ($alignment === 'generic_label') {
            $where[] = $genericFamilyExpr;
        } elseif (in_array($alignment, ['aligned', 'mismatch', 'signal_only', 'catalog_only', 'unlabeled'], true)) {
            $where[] = $familyAlignmentExpr . ' = :family_alignment';
            $params['family_alignment'] = $alignment;
        }
    }

    if (!empty($filters['eligible_now'])) {
        $where[] = "s.claim_token IS NULL";
        $where[] = "s.vt_status_code <> 'QUARANTINED'";
        $where[] = "(
            s.vt_status_code IN ('NEW','QUEUED')
            OR (
                s.vt_status_code IN ('REANALYZE','RETRY_WAIT')
                AND (s.next_eligible_at_utc IS NULL OR s.next_eligible_at_utc <= UTC_TIMESTAMP())
            )
        )";
    }

    if (!empty($filters['claimed'])) {
        $where[] = 's.claim_token IS NOT NULL';
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

/**
 * Bind named parameters safely for list queries.
 */
function bind_samples_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $paramKey = ':' . $key;
        if (in_array($key, ['limit', 'offset'], true)) {
            $stmt->bindValue($paramKey, (int)$value, PDO::PARAM_INT);
            continue;
        }
        $stmt->bindValue($paramKey, $value);
    }
}

/**
 * Build ORDER BY with strict allowlist to prevent SQL injection.
 */
function build_samples_order(array $filters): string
{
    $rawSortBy = strtolower(trim((string)($filters['sort_by'] ?? 'id')));
    $rawSortDir = strtoupper(trim((string)($filters['sort_dir'] ?? 'DESC')));

    $sortMap = [
        'id' => 'c.sample_id',
        'label' => 'c.sample_label',
        'family' => 'c.family_label',
        'alignment' => 'family_alignment_status',
    ];

    $sortExpr = $sortMap[$rawSortBy] ?? $sortMap['id'];
    $sortDir = in_array($rawSortDir, ['ASC', 'DESC'], true) ? $rawSortDir : 'DESC';

    if ($rawSortBy === 'id') {
        return " ORDER BY {$sortExpr} {$sortDir}, c.sha256 ASC";
    }

    return " ORDER BY COALESCE({$sortExpr}, '') {$sortDir}, c.sample_id DESC";
}

/**
 * Fetch paginated samples list with filters.
 */
function db_samples_list(array $filters): array
{
    $page = (int)($filters['page'] ?? 1);
    $pageSize = (int)($filters['page_size'] ?? DEFAULT_PAGE_SIZE);
    $offset = max(0, ($page - 1) * $pageSize);

    $filterData = build_samples_filters($filters);
    $where = $filterData['where'];
    $params = $filterData['params'];

    $sql = sql_samples_list_base();
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= build_samples_order($filters);
    $sql .= ' LIMIT :limit OFFSET :offset';

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $stmt = db()->prepare($sql);
    bind_samples_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countSql = sql_samples_count_base();
    if ($where) {
        $countSql .= " WHERE " . implode(' AND ', $where);
    }
    $countParams = $params;
    unset($countParams['limit'], $countParams['offset']);
    $countRow = db_one($countSql, $countParams);
    $totalCount = (int)($countRow['total_count'] ?? 0);
    $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;

    return [
        'page' => $page,
        'page_size' => $pageSize,
        'total_count' => $totalCount,
        'total_pages' => max(1, $totalPages),
        'has_more' => ($offset + $pageSize) < $totalCount,
        'rows' => $rows,
    ];
}

/**
 * Fetch sample detail by id or sha256.
 */
function db_sample_detail(?int $sampleId, ?string $sha256): array
{
    if ($sampleId !== null) {
        $row = db_one(sql_sample_detail_by_id(), ['sample_id' => $sampleId]);
    } elseif ($sha256 !== null) {
        $row = db_one(sql_sample_detail_by_sha(), ['sha256' => $sha256]);
    } else {
        $row = null;
    }

    if ($row === null) {
        return [
            'ok' => false,
            'error' => 'Sample not found',
        ];
    }

    $lastRun = null;
    if (!empty($row['last_run_id'])) {
        $lastRun = db_one(sql_run_ledger_by_id(), ['run_id' => $row['last_run_id']]);
    }

    $platformContext = db_sample_platform_context($lastRun);

    return [
        'ok' => true,
        'sample' => $row,
        'last_run' => $lastRun,
        'platform_context' => $platformContext,
    ];
}

function db_sample_platform_context(?array $lastRun): array
{
    $primaryCatalog = function_exists('db_primary_catalog_name') ? db_primary_catalog_name() : (defined('DB_NAME') ? (string)DB_NAME : '');
    $permissionIntelCatalog = function_exists('db_permission_intel_catalog_name') ? db_permission_intel_catalog_name() : $primaryCatalog;
    $primaryHead = db_runs_schema_head_for_catalog($primaryCatalog);
    $permissionIntelHead = db_runs_schema_head_for_catalog($permissionIntelCatalog);
    $latestTaxonomy = db_one(sql_perm_taxonomy_latest());

    $lastRunDbName = trim((string)($lastRun['db_name'] ?? ''));
    $lastRunSchemaVersion = trim((string)($lastRun['schema_version'] ?? ''));
    $lastRunPermTaxonomyVersion = trim((string)($lastRun['perm_taxonomy_version'] ?? ''));
    $latestPermTaxonomyVersion = trim((string)($latestTaxonomy['perm_taxonomy_version'] ?? ''));

    $runAgainstCurrentPrimary = $lastRunDbName !== '' && $lastRunDbName === $primaryCatalog;
    $runAgainstKnownCatalog = $lastRunDbName !== '' && in_array($lastRunDbName, [$primaryCatalog, $permissionIntelCatalog], true);
    $schemaMatchesPrimaryHead = $lastRunSchemaVersion !== '' && $primaryHead !== null && $lastRunSchemaVersion === $primaryHead;
    $permTaxonomyMatchesLatest = $lastRunPermTaxonomyVersion !== '' && $latestPermTaxonomyVersion !== '' && $lastRunPermTaxonomyVersion === $latestPermTaxonomyVersion;

    return [
        'primary_catalog' => $primaryCatalog,
        'permission_intel_catalog' => $permissionIntelCatalog,
        'split_enabled' => $permissionIntelCatalog !== $primaryCatalog,
        'primary_schema_head' => $primaryHead,
        'permission_intel_schema_head' => $permissionIntelHead,
        'schema_heads_match' => $primaryHead !== null && $primaryHead === $permissionIntelHead,
        'latest_perm_taxonomy_version' => $latestTaxonomy['perm_taxonomy_version'] ?? null,
        'latest_perm_taxonomy_finished_at_utc' => $latestTaxonomy['finished_at_utc'] ?? null,
        'sample_last_run_db_name' => $lastRunDbName !== '' ? $lastRunDbName : null,
        'sample_last_run_schema_version' => $lastRunSchemaVersion !== '' ? $lastRunSchemaVersion : null,
        'sample_last_run_perm_taxonomy_version' => $lastRunPermTaxonomyVersion !== '' ? $lastRunPermTaxonomyVersion : null,
        'sample_has_last_run' => $lastRun !== null,
        'sample_last_run_against_current_primary' => $runAgainstCurrentPrimary,
        'sample_last_run_against_known_catalog' => $runAgainstKnownCatalog,
        'sample_last_run_schema_matches_primary_head' => $schemaMatchesPrimaryHead,
        'sample_last_run_perm_taxonomy_matches_latest' => $permTaxonomyMatchesLatest,
        'sample_platform_state_mismatch' => $lastRun !== null && (!$runAgainstCurrentPrimary || !$schemaMatchesPrimaryHead || !$permTaxonomyMatchesLatest),
    ];
}

function db_ensure_web_change_audit_table(): bool
{
    static $checked = false;
    static $available = false;
    if ($checked) {
        return $available;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS web_change_audit (
            audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            changed_at_utc DATETIME NOT NULL DEFAULT UTC_TIMESTAMP(),
            actor VARCHAR(128) NULL,
            endpoint VARCHAR(255) NULL,
            table_name VARCHAR(128) NOT NULL,
            pk_name VARCHAR(64) NOT NULL,
            pk_value VARCHAR(128) NOT NULL,
            change_type VARCHAR(16) NOT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            PRIMARY KEY (audit_id),
            KEY idx_web_change_audit_table_pk (table_name, pk_name, pk_value),
            KEY idx_web_change_audit_changed_at (changed_at_utc)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    try {
        db_exec($sql);
        $available = true;
    } catch (Throwable $e) {
        $available = false;
    }
    $checked = true;
    return $available;
}

function db_web_actor(): string
{
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? '-'));
    $user = trim((string)($_SERVER['REMOTE_USER'] ?? ''));
    if ($user !== '') {
        return $user . '@' . $remote;
    }
    return 'web@' . $remote;
}

/**
 * Update editable catalog metadata fields.
 */
function db_update_sample_metadata(int $sampleId, array $fields): array
{
    $label = trim((string)($fields['sample_label'] ?? ''));
    $family = trim((string)($fields['family_label'] ?? ''));
    $primary = trim((string)($fields['classification_primary'] ?? ''));
    $subtype = trim((string)($fields['classification_subtype'] ?? ''));

    $params = [
        'sample_id' => $sampleId,
        'sample_label' => $label === '' ? null : $label,
        'family_label' => $family === '' ? null : $family,
        'classification_primary' => $primary === '' ? null : $primary,
        'classification_subtype' => $subtype === '' ? null : $subtype,
    ];

    db_assert_write_allowlist('malware_sample_catalog', [
        'sample_label',
        'family_label',
        'classification_primary',
        'classification_subtype',
        'record_updated_at_utc',
    ]);

    $updated = 0;
    $before = null;
    $after = null;
    $auditWritten = 0;
    $auditWarning = null;
    db_tx(function () use ($sampleId, $params, &$updated, &$before, &$after): void {
        $before = db_one(
            "
            SELECT sample_id, sample_label, family_label, classification_primary, classification_subtype
            FROM malware_sample_catalog
            WHERE sample_id = :sample_id
            LIMIT 1
            ",
            ['sample_id' => $sampleId]
        );

        $updated = db_exec(sql_sample_metadata_update(), $params);
        $after = db_one(
            "
            SELECT sample_id, sample_label, family_label, classification_primary, classification_subtype
            FROM malware_sample_catalog
            WHERE sample_id = :sample_id
            LIMIT 1
            ",
            ['sample_id' => $sampleId]
        );
    });

    if ($before !== null && db_ensure_web_change_audit_table()) {
        try {
            $auditWritten = db_exec(
                "
                INSERT INTO web_change_audit (
                    actor,
                    endpoint,
                    table_name,
                    pk_name,
                    pk_value,
                    change_type,
                    before_json,
                    after_json
                ) VALUES (
                    :actor,
                    :endpoint,
                    'malware_sample_catalog',
                    'sample_id',
                    :pk_value,
                    'UPDATE',
                    :before_json,
                    :after_json
                )
                ",
                [
                    'actor' => db_web_actor(),
                    'endpoint' => (string)($_SERVER['REQUEST_URI'] ?? ''),
                    'pk_value' => (string)$sampleId,
                    'before_json' => json_encode($before, JSON_UNESCAPED_SLASHES),
                    'after_json' => json_encode($after, JSON_UNESCAPED_SLASHES),
                ]
            );
        } catch (Throwable $e) {
            $auditWarning = 'audit_write_failed';
        }
    } elseif ($before !== null) {
        $auditWarning = 'audit_unavailable';
    }

    if ($before !== null && $after !== null && $updated === 0) {
        $beforeJson = json_encode($before, JSON_UNESCAPED_SLASHES);
        $afterJson = json_encode($after, JSON_UNESCAPED_SLASHES);
        if ($beforeJson === $afterJson) {
            $updated = 1;
        }
    }

    return [
        'data' => [
            'updated' => $updated,
            'audit_written' => $auditWritten,
            'warning' => $auditWarning,
        ],
    ];
}
