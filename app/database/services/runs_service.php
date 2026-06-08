<?php
// app/database/services/runs_service.php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/app_config.php';
require_once __DIR__ . '/../db_engine.php';
require_once __DIR__ . '/../queries/runs_queries.php';

function bind_runs_params(PDOStatement $stmt, array $params): void
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
 * Fetch paginated run ledger list with filters.
 */
function db_run_ledger_list(array $filters): array
{
    $page = (int)($filters['page'] ?? 1);
    $pageSize = (int)($filters['page_size'] ?? DEFAULT_PAGE_SIZE);
    $offset = max(0, ($page - 1) * $pageSize);

    $where = [];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(CAST(run_id AS CHAR) = :q_exact OR db_name LIKE :q_like OR key_id LIKE :q_like)';
        $params['q_exact'] = $filters['q'];
        $params['q_like'] = '%' . $filters['q'] . '%';
    }

    $sql = sql_run_ledger_list_base();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY run_id DESC LIMIT :limit OFFSET :offset';

    $params['limit'] = $pageSize;
    $params['offset'] = $offset;

    $stmt = db()->prepare($sql);
    bind_runs_params($stmt, $params);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countSql = sql_run_ledger_count_base();
    if ($where) {
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $countParams = $params;
    unset($countParams['limit'], $countParams['offset']);
    $countRow = db_one($countSql, $countParams);
    $totalCount = (int)($countRow['total_count'] ?? 0);
    $totalPages = $pageSize > 0 ? (int)ceil($totalCount / $pageSize) : 1;
    $platformContext = db_run_platform_context($rows);

    return [
        'ok' => true,
        'page' => $page,
        'page_size' => $pageSize,
        'total_count' => $totalCount,
        'total_pages' => max(1, $totalPages),
        'has_more' => ($offset + $pageSize) < $totalCount,
        'platform_context' => $platformContext,
        'rows' => $rows,
    ];
}

function db_run_platform_context(array $rows): array
{
    $primaryCatalog = function_exists('db_primary_catalog_name') ? db_primary_catalog_name() : (defined('DB_NAME') ? (string)DB_NAME : '');
    $permissionIntelCatalog = function_exists('db_permission_intel_catalog_name') ? db_permission_intel_catalog_name() : $primaryCatalog;
    $primaryHead = db_runs_schema_head_for_catalog($primaryCatalog);
    $permissionIntelHead = db_runs_schema_head_for_catalog($permissionIntelCatalog);
    $latestTaxonomy = db_one(sql_perm_taxonomy_latest());

    $runDbNames = [];
    $runSchemaVersions = [];
    $runPermTaxonomyVersions = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $dbName = trim((string)($row['db_name'] ?? ''));
        $schemaVersion = trim((string)($row['schema_version'] ?? ''));
        $permTaxonomyVersion = trim((string)($row['perm_taxonomy_version'] ?? ''));
        if ($dbName !== '') {
            $runDbNames[$dbName] = true;
        }
        if ($schemaVersion !== '') {
            $runSchemaVersions[$schemaVersion] = true;
        }
        if ($permTaxonomyVersion !== '') {
            $runPermTaxonomyVersions[$permTaxonomyVersion] = true;
        }
    }

    return [
        'primary_catalog' => $primaryCatalog,
        'permission_intel_catalog' => $permissionIntelCatalog,
        'split_enabled' => $permissionIntelCatalog !== $primaryCatalog,
        'primary_schema_head' => $primaryHead,
        'permission_intel_schema_head' => $permissionIntelHead,
        'schema_heads_match' => $primaryHead !== null && $primaryHead === $permissionIntelHead,
        'latest_perm_taxonomy_version' => $latestTaxonomy['perm_taxonomy_version'] ?? null,
        'latest_perm_taxonomy_finished_at_utc' => $latestTaxonomy['finished_at_utc'] ?? null,
        'visible_run_db_names' => array_values(array_keys($runDbNames)),
        'visible_run_schema_versions' => array_values(array_keys($runSchemaVersions)),
        'visible_run_perm_taxonomy_versions' => array_values(array_keys($runPermTaxonomyVersions)),
        'mixed_visible_run_db_names' => count($runDbNames) > 1,
        'mixed_visible_run_schema_versions' => count($runSchemaVersions) > 1,
        'mixed_visible_run_perm_taxonomy_versions' => count($runPermTaxonomyVersions) > 1,
    ];
}

function db_runs_schema_head_for_catalog(string $catalog): ?string
{
    $catalog = trim($catalog);
    if ($catalog === '') {
        return null;
    }
    $catalogSql = db_quote_identifier($catalog);
    try {
        $row = db_one("
            SELECT version
            FROM {$catalogSql}.schema_migrations
            ORDER BY id DESC
            LIMIT 1
        ");
    } catch (Throwable $ignored) {
        return null;
    }
    if (!$row || !array_key_exists('version', $row) || $row['version'] === null) {
        return null;
    }
    return (string)$row['version'];
}
