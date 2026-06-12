<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/services/family_service.php';
require_once __DIR__ . '/transient_cache.php';

function taxonomy_view_force_refresh_requested(): bool
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }

    $refresh = strtolower(trim((string)($_GET['refresh'] ?? '')));
    return in_array($refresh, ['1', 'true', 'yes', 'y'], true);
}

function taxonomy_view_schedule_refresh_once(string $key, callable $refresh): void
{
    static $scheduled = [];
    if (isset($scheduled[$key])) {
        return;
    }
    $scheduled[$key] = true;
    register_shutdown_function(static function () use ($refresh): void {
        try {
            $refresh();
        } catch (Throwable $e) {
            // Ignore deferred refresh failures; pages should still render stale data.
        }
    });
}

/**
 * Small view-layer wrapper around the taxonomy check so pages can use named
 * filters instead of long positional empty-argument chains.
 */
function taxonomy_view_fetch(int $limit = 25, array $filters = []): array
{
    ksort($filters);
    $cacheKey = md5(json_encode([
        'limit' => $limit,
        'filters' => $filters,
        'cache_generation' => function_exists('db_cache_generation_token') ? db_cache_generation_token() : '0',
    ], JSON_UNESCAPED_SLASHES) ?: '');
    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $includeRows = !array_key_exists('include_rows', $filters) || (bool)$filters['include_rows'];
    $ttlSeconds = $includeRows ? 60 : 1800;
    $namespace = 'taxonomy_view_fetch_v3';
    $forceRefresh = taxonomy_view_force_refresh_requested();
    $cached = $forceRefresh ? null : app_transient_cache_read($namespace, $cacheKey, $ttlSeconds);
    if (is_array($cached)) {
        $cache[$cacheKey] = $cached;
        return $cache[$cacheKey];
    }
    $staleCached = app_transient_cache_read_stale($namespace, $cacheKey);
    if (is_array($staleCached)) {
        if ($forceRefresh) {
            taxonomy_view_schedule_refresh_once($namespace . ':' . $cacheKey, static function () use ($limit, $filters, $namespace, $cacheKey): void {
                $includeRows = !array_key_exists('include_rows', $filters) || (bool)$filters['include_rows'];
                $payload = db_family_taxonomy_check(
                    limit: $limit,
                    alignment: (string)($filters['alignment'] ?? ''),
                    platform: (string)($filters['platform'] ?? ''),
                    query: (string)($filters['query'] ?? $filters['q'] ?? ''),
                    pattern: (string)($filters['pattern'] ?? ''),
                    pairCatalog: (string)($filters['pair_catalog'] ?? ''),
                    pairSignal: (string)($filters['pair_signal'] ?? ''),
                    fixAction: (string)($filters['fix_action'] ?? ''),
                    targetFamily: (string)($filters['target_family'] ?? ''),
                    decisionMode: (string)($filters['decision_mode'] ?? ''),
                    includeRows: $includeRows,
                );
                app_transient_cache_write($namespace, $cacheKey, $payload);
            });
        }
        $cache[$cacheKey] = $staleCached;
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = db_family_taxonomy_check(
        limit: $limit,
        alignment: (string)($filters['alignment'] ?? ''),
        platform: (string)($filters['platform'] ?? ''),
        query: (string)($filters['query'] ?? $filters['q'] ?? ''),
        pattern: (string)($filters['pattern'] ?? ''),
        pairCatalog: (string)($filters['pair_catalog'] ?? ''),
        pairSignal: (string)($filters['pair_signal'] ?? ''),
        fixAction: (string)($filters['fix_action'] ?? ''),
        targetFamily: (string)($filters['target_family'] ?? ''),
        decisionMode: (string)($filters['decision_mode'] ?? ''),
        includeRows: $includeRows,
    );
    app_transient_cache_write($namespace, $cacheKey, $cache[$cacheKey]);
    return $cache[$cacheKey];
}

function taxonomy_view_summary_by_alignment(array $payload): array
{
    $summary = [];
    foreach (($payload['data']['summary'] ?? []) as $row) {
        $summary[(string)($row['alignment_status'] ?? '')] = $row;
    }
    return $summary;
}

function taxonomy_view_issue_counts(array $payload): array
{
    return is_array($payload['data']['issue_inventory']['issue_kind_counts'] ?? null)
        ? $payload['data']['issue_inventory']['issue_kind_counts']
        : [];
}

function taxonomy_view_decision_counts(array $payload): array
{
    return is_array($payload['data']['decision_inventory']['decision_mode_counts'] ?? null)
        ? $payload['data']['decision_inventory']['decision_mode_counts']
        : [];
}

function taxonomy_view_catalog_only_authority_summary(string $platform = 'android'): array
{
    static $cache = [];
    $platform = strtolower(trim($platform));
    $cacheKey = md5(json_encode([
        'platform' => $platform,
        'cache_generation' => function_exists('db_cache_generation_token') ? db_cache_generation_token() : '0',
    ], JSON_UNESCAPED_SLASHES) ?: '');
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $namespace = 'taxonomy_catalog_only_authority_summary_v2';
    $forceRefresh = taxonomy_view_force_refresh_requested();
    $cached = $forceRefresh ? null : app_transient_cache_read($namespace, $cacheKey, 1800);
    if (is_array($cached)) {
        $cache[$cacheKey] = $cached;
        return $cache[$cacheKey];
    }
    $staleCached = app_transient_cache_read_stale($namespace, $cacheKey);
    if (is_array($staleCached)) {
        if ($forceRefresh) {
            taxonomy_view_schedule_refresh_once($namespace . ':' . $cacheKey, static function () use ($platform, $namespace, $cacheKey): void {
                $payload = db_family_taxonomy_catalog_only_authority_summary($platform);
                app_transient_cache_write($namespace, $cacheKey, $payload);
            });
        }
        $cache[$cacheKey] = $staleCached;
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = db_family_taxonomy_catalog_only_authority_summary($platform);
    app_transient_cache_write($namespace, $cacheKey, $cache[$cacheKey]);
    return $cache[$cacheKey];
}

function taxonomy_view_catalog_only_anchor_families(string $platform = 'android', int $limit = 10): array
{
    static $cache = [];
    $platform = strtolower(trim($platform));
    $cacheKey = md5(json_encode([
        'platform' => $platform,
        'limit' => $limit,
        'cache_generation' => function_exists('db_cache_generation_token') ? db_cache_generation_token() : '0',
    ], JSON_UNESCAPED_SLASHES) ?: '');
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $namespace = 'taxonomy_catalog_only_anchor_families_v2';
    $forceRefresh = taxonomy_view_force_refresh_requested();
    $cached = $forceRefresh ? null : app_transient_cache_read($namespace, $cacheKey, 1800);
    if (is_array($cached)) {
        $cache[$cacheKey] = $cached;
        return $cache[$cacheKey];
    }
    $staleCached = app_transient_cache_read_stale($namespace, $cacheKey);
    if (is_array($staleCached)) {
        if ($forceRefresh) {
            taxonomy_view_schedule_refresh_once($namespace . ':' . $cacheKey, static function () use ($platform, $limit, $namespace, $cacheKey): void {
                $payload = db_family_taxonomy_catalog_only_anchor_families($platform, $limit);
                app_transient_cache_write($namespace, $cacheKey, $payload);
            });
        }
        $cache[$cacheKey] = $staleCached;
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = db_family_taxonomy_catalog_only_anchor_families($platform, $limit);
    app_transient_cache_write($namespace, $cacheKey, $cache[$cacheKey]);
    return $cache[$cacheKey];
}

function taxonomy_view_issue_display_label(string $value): string
{
    static $map = [
        'generic_signal' => 'generic_policy_hold',
        'signal_overlap' => 'signal_overlap',
        'alias_resolved' => 'alias_resolved',
        'alias_candidate' => 'alias_candidate',
        'placeholder_catalog' => 'placeholder_catalog',
        'short_signal_token' => 'short_signal_token',
        'catalog_missing' => 'catalog_missing',
    ];

    $key = trim($value);
    return $map[$key] ?? $key;
}

function taxonomy_view_signal_hygiene_data(string $platform = 'android'): array
{
    $payload = taxonomy_view_fetch(filters: [
        'platform' => strtolower(trim($platform)),
        'include_rows' => false,
    ]);

    $issueCounts = taxonomy_view_issue_counts($payload);
    $repairOps = array_values(array_filter(
        (array)($payload['data']['repair_opportunities'] ?? []),
        static fn($row): bool => is_array($row)
    ));

    $genericHoldOp = null;
    $overlapHoldOp = null;
    $resolvedOps = [];
    foreach ($repairOps as $row) {
        $decisionMode = (string)($row['decision_mode'] ?? '');
        $issueKind = (string)($row['dominant_issue_kind'] ?? '');
        if ($decisionMode === 'hold_generic_signal' && $genericHoldOp === null) {
            $genericHoldOp = $row;
        }
        if ($decisionMode === 'hold_signal_overlap' && $overlapHoldOp === null) {
            $overlapHoldOp = $row;
        }
        if ($issueKind === 'alias_resolved') {
            $resolvedOps[] = $row;
        }
    }

    $genericIssues = [];
    foreach (['generic_signal', 'placeholder_catalog', 'short_signal_token', 'catalog_missing'] as $issueKey) {
        $count = (int)($issueCounts[$issueKey] ?? 0);
        if ($count > 0) {
            $genericIssues[$issueKey] = $count;
        }
    }

    return [
        'payload' => $payload,
        'issue_counts' => $issueCounts,
        'repair_ops' => $repairOps,
        'generic_hold_op' => $genericHoldOp,
        'overlap_hold_op' => $overlapHoldOp,
        'resolved_ops' => $resolvedOps,
        'generic_issues' => $genericIssues,
        'counts' => [
            'generic_signal' => (int)($issueCounts['generic_signal'] ?? 0),
            'generic_policy_hold' => (int)($genericHoldOp['row_count'] ?? array_sum($genericIssues)),
            'signal_overlap' => (int)($issueCounts['signal_overlap'] ?? 0),
            'alias_resolved' => (int)($issueCounts['alias_resolved'] ?? 0),
            'alias_candidate' => (int)($issueCounts['alias_candidate'] ?? 0),
        ],
    ];
}
