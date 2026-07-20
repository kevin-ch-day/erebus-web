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
        $cache[$cacheKey] = $staleCached;
        return $cache[$cacheKey];
    }

    // Cold full-catalog taxonomy checks routinely exceed php-fpm memory (128M)
    // and the reverse-proxy timeout. Never compute them inline for web requests.
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
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

    $cache[$cacheKey] = taxonomy_view_lightweight_payload(
        platform: (string)($filters['platform'] ?? ''),
        limit: $limit,
        includeRows: $includeRows,
    );
    return $cache[$cacheKey];
}

/**
 * Fast fallback when taxonomy check cache is cold.
 * Built from the cached family scorecard (or empty zeros).
 */
function taxonomy_view_lightweight_payload(
    string $platform = '',
    int $limit = 25,
    bool $includeRows = false,
): array {
    $scorecard = function_exists('db_family_taxonomy_scorecard_cached_only')
        ? db_family_taxonomy_scorecard_cached_only()
        : null;
    if (!is_array($scorecard)) {
        $scorecard = [];
    }

    $summary = [];
    foreach (
        [
            'aligned' => 'aligned_rows',
            'mismatch' => 'mismatch_rows',
            'signal_only' => 'signal_only_rows',
            'catalog_only' => 'catalog_only_rows',
            'unlabeled' => 'unlabeled_rows',
        ] as $status => $field
    ) {
        $count = (int)($scorecard[$field] ?? 0);
        if ($count <= 0 && empty($scorecard)) {
            continue;
        }
        $summary[] = [
            'alignment_status' => $status,
            'row_count' => $count,
            'generic_label_count' => $status === 'aligned' ? 0 : (int)($scorecard['generic_label_rows'] ?? 0),
        ];
    }

    $mismatchPairs = function_exists('db_family_taxonomy_top_mismatch_pairs_cached')
        ? db_family_taxonomy_top_mismatch_pairs_cached(6, false)
        : [];

    return [
        'data' => [
            'summary' => $summary,
            'authority_mismatch_summary' => [],
            'rows' => [],
            'mismatch_pairs' => $mismatchPairs,
            'issue_inventory' => [
                'total_rows' => (int)($scorecard['total_rows'] ?? 0),
                'issue_kind_counts' => new stdClass(),
                'top_catalog_labels' => [],
                'top_signal_labels' => [],
            ],
            'fix_action_inventory' => [
                'total_rows' => 0,
                'action_counts' => new stdClass(),
                'top_target_families' => [],
            ],
            'decision_inventory' => [
                'total_rows' => (int)($scorecard['total_rows'] ?? 0),
                'decision_mode_counts' => new stdClass(),
                'decision_priority_counts' => new stdClass(),
            ],
            'ask_why_inventory' => [
                'total_rows' => 0,
                'issue_kind_counts' => new stdClass(),
                'platform_counts' => new stdClass(),
                'issue_platform_counts' => new stdClass(),
            ],
            'platform_inventory' => [
                'total_rows' => (int)($scorecard['total_rows'] ?? 0),
                'platform_counts' => new stdClass(),
                'platform_alignment_counts' => new stdClass(),
                'platform_decision_counts' => new stdClass(),
                'platform_held_mismatch_counts' => new stdClass(),
                'platform_repair_now_counts' => new stdClass(),
            ],
            'governance_inventory' => [
                'total_rows' => 0,
                'targeted_rows' => 0,
                'untargeted_rows' => 0,
                'target_groups' => [],
                'untargeted_pair_groups' => [],
                'untargeted_top_signal_labels' => [],
                'untargeted_top_catalog_labels' => [],
            ],
            'apply_plan' => [
                'dry_run' => true,
                'supported_actions' => [],
                'plan_rows' => [],
                'summary' => [
                    'candidate_rows' => 0,
                    'plan_group_count' => 0,
                    'excluded_rows' => 0,
                    'excluded_reasons' => new stdClass(),
                ],
            ],
            'repair_opportunities' => [],
            'queue_presets' => [],
            'remediation_summary' => [
                'priority_lanes' => [],
                'math' => [
                    'mismatch_rows' => (int)($scorecard['mismatch_rows'] ?? 0),
                    'high_conflict_rows' => (int)($scorecard['high_conflict_rows'] ?? 0),
                    'risk_class' => (string)($scorecard['risk_class'] ?? 'unknown'),
                ],
                'mismatch_pair_classes' => new stdClass(),
                'row_pattern_summary' => new stdClass(),
                'top_mismatch_pairs' => $mismatchPairs,
            ],
            'cache_state' => empty($scorecard) ? 'missing' : 'lightweight',
        ],
        'meta' => [
            'schema_available' => !empty($scorecard['available']),
            'primary_database' => db_primary_catalog_name(),
            'limit' => $limit,
            'alignment' => '',
            'platform' => $platform,
            'query' => '',
            'pattern' => '',
            'pair_catalog' => '',
            'pair_signal' => '',
            'fix_action' => '',
            'target_family' => '',
            'decision_mode' => '',
            'include_rows' => $includeRows,
            'generated_at_utc' => gmdate('Y-m-d H:i:s'),
            'cache_state' => empty($scorecard) ? 'missing' : 'lightweight',
            'warm_hint' => 'php bin/warm_landing_cache.php',
        ],
    ];
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
        $cache[$cacheKey] = $staleCached;
        return $cache[$cacheKey];
    }

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        $cache[$cacheKey] = db_family_taxonomy_catalog_only_authority_summary($platform);
        app_transient_cache_write($namespace, $cacheKey, $cache[$cacheKey]);
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = [
        'total_rows' => 0,
        'authority_family_typed_rows' => 0,
        'resolved_unknown_rows' => 0,
        'generic_label_candidate_rows' => 0,
        'residual_review_rows' => 0,
        'missing_signal_row_rows' => 0,
        'coarse_vt_only_rows' => 0,
        'empty_signal_surface_rows' => 0,
        'source_batch_backed_rows' => 0,
        'authority_coverage_pct' => 0.0,
        'cache_state' => 'missing',
    ];
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
        $cache[$cacheKey] = $staleCached;
        return $cache[$cacheKey];
    }

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        $cache[$cacheKey] = db_family_taxonomy_catalog_only_anchor_families($platform, $limit);
        app_transient_cache_write($namespace, $cacheKey, $cache[$cacheKey]);
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = [];
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
