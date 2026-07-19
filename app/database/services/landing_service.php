<?php
declare(strict_types=1);

require_once __DIR__ . '/health_service.php';
require_once __DIR__ . '/family_service.php';
require_once __DIR__ . '/dataset_readiness_service.php';
require_once __DIR__ . '/stack_service.php';
require_once __DIR__ . '/../../lib/transient_cache.php';

/**
 * Landing control-deck snapshot.
 *
 * Intentionally avoids cold full-corpus scans (type benchmark row materialization,
 * uncached family taxonomy scorecard/mismatch pairs). Those paths can exceed the
 * reverse-proxy timeout and/or php-fpm memory limit, which left the Home page stuck
 * on "Snapshot unavailable".
 */
function db_landing_snapshot(): array
{
    $degraded = [];

    $health = db_landing_health_metrics();
    $pipeline = db_landing_pipeline_status($degraded);
    $familySummary = db_landing_family_summary($degraded);
    $mismatchPairs = db_landing_mismatch_pairs($degraded);
    $dataset = db_landing_dataset_summary($degraded);
    $stack = db_landing_stack_summary($degraded);

    return [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'pipeline' => $pipeline,
        'health' => $health,
        'family' => [
            'summary' => $familySummary,
            'top_mismatch_pairs' => $mismatchPairs,
        ],
        'stack' => $stack,
        'dataset' => $dataset,
        'degraded' => $degraded,
    ];
}

function db_landing_health_metrics(): array
{
    $control = db_one(sql_system_control()) ?? [];
    $eligible = db_one(sql_count_eligible_now()) ?? [];
    $processing = db_one(sql_count_processing_now()) ?? [];
    $errors = db_one(sql_count_error()) ?? [];
    $retryWait = db_one(sql_count_retry_wait()) ?? [];
    $stale = db_one(sql_count_stale_claims(), ['mins' => STALE_CLAIM_MINUTES]) ?? [];
    $reasons = db_all(sql_reason_breakdown(20));

    return [
        'eligible_now' => (int)($eligible['eligible_now'] ?? 0),
        'processing_now' => (int)($processing['processing_now'] ?? 0),
        'error_count' => (int)($errors['error_count'] ?? 0),
        'retry_wait_count' => (int)($retryWait['retry_wait_count'] ?? 0),
        'stale_claims' => (int)($stale['stale_claims'] ?? 0),
        'reason_breakdown' => array_slice(is_array($reasons) ? $reasons : [], 0, 6),
        'hold_until_utc' => (string)($control['hold_until_utc'] ?? ''),
        'hold_reason_code' => (string)($control['hold_reason_code'] ?? ''),
    ];
}

function db_landing_pipeline_status(array &$degraded): array
{
    try {
        return db_pipeline_status(true);
    } catch (Throwable $e) {
        $degraded[] = 'pipeline';
        return [
            'ok' => false,
            'source' => 'error',
            'pipeline' => [
                'queue_pending' => 0,
                'state_eligible_now' => 0,
            ],
            'recommendation' => [
                'summary' => 'Pipeline posture unavailable.',
            ],
        ];
    }
}

function db_landing_family_summary(array &$degraded): array
{
    try {
        $summary = db_family_taxonomy_scorecard_cached_only();
        if (!is_array($summary) || empty($summary)) {
            $degraded[] = 'family_summary';
            return ['available' => false];
        }
        return $summary;
    } catch (Throwable $e) {
        $degraded[] = 'family_summary';
        return ['available' => false];
    }
}

/**
 * Family taxonomy scorecard for landing: cache/stale only.
 * Cold scorecard scans the full catalog (~20-30s) and trips the gateway timeout.
 */
function db_family_taxonomy_scorecard_cached_only(): ?array
{
    $cacheKey = md5(json_encode([
        'primary' => db_primary_catalog_name(),
        'version' => APP_VERSION,
        'payload_contract' => 'health_family_taxonomy_scorecard_v1',
    ], JSON_UNESCAPED_SLASHES) ?: '');

    $cached = app_transient_cache_read('health_family_taxonomy_scorecard', $cacheKey, 120);
    if (is_array($cached)) {
        return $cached;
    }

    $staleCached = app_transient_cache_read_stale('health_family_taxonomy_scorecard', $cacheKey);
    if (is_array($staleCached)) {
        return $staleCached;
    }

    return null;
}

function db_landing_mismatch_pairs(array &$degraded): array
{
    try {
        return db_family_taxonomy_top_mismatch_pairs_cached(6);
    } catch (Throwable $e) {
        $degraded[] = 'family_hotspots';
        return [];
    }
}

function db_landing_dataset_summary(array &$degraded): array
{
    $empty = [
        'clean_benchmark_rows' => 0,
        'persisted_authority_fact_count' => 0,
        'held_persisted_authority_consistency_debt_count' => 0,
        'projection_materialization_debt_count' => 0,
        'unresolved_authority_count' => 0,
        'generic_policy_hold_count' => 0,
        'class_count' => 0,
        'trainable_class_count_n10' => 0,
        'top_class' => '',
        'top_class_share' => null,
        'authority_consistency_families' => 0,
        'cache_state' => 'missing',
    ];

    try {
        $benchmark = db_dataset_type_benchmark_cached_only();
        if ($benchmark === null) {
            $degraded[] = 'dataset';
            return $empty;
        }

        $typeSummary = is_array($benchmark['summary'] ?? null) ? $benchmark['summary'] : [];
        $authorityConsistency = is_array($benchmark['authority_consistency_summary'] ?? null)
            ? $benchmark['authority_consistency_summary']
            : [];

        return [
            'clean_benchmark_rows' => (int)($typeSummary['clean_benchmark_rows'] ?? 0),
            'persisted_authority_fact_count' => (int)($typeSummary['persisted_authority_fact_count'] ?? 0),
            'held_persisted_authority_consistency_debt_count' => (int)($typeSummary['held_persisted_authority_consistency_debt_count'] ?? 0),
            'projection_materialization_debt_count' => (int)($typeSummary['projection_without_persisted_fact_count'] ?? 0),
            'unresolved_authority_count' => (int)($typeSummary['unresolved_authority_count'] ?? 0),
            'generic_policy_hold_count' => (int)($typeSummary['generic_token_policy_hold_count'] ?? 0),
            'class_count' => (int)($typeSummary['class_count'] ?? 0),
            'trainable_class_count_n10' => (int)($typeSummary['trainable_class_count_n10'] ?? 0),
            'top_class' => (string)($typeSummary['top_class'] ?? ''),
            'top_class_share' => $typeSummary['top_class_share'] ?? null,
            'authority_consistency_families' => (int)($authorityConsistency['family_count'] ?? 0),
            'cache_state' => (string)($benchmark['cache_state'] ?? 'hit'),
        ];
    } catch (Throwable $e) {
        $degraded[] = 'dataset';
        return $empty;
    }
}

function db_landing_stack_summary(array &$degraded): array
{
    try {
        $stackAudit = db_stack_audit();
        $stackCapabilities = is_array($stackAudit['capabilities'] ?? null) ? $stackAudit['capabilities'] : [];
        $stackGaps = is_array($stackAudit['gap_inventory'] ?? null) ? $stackAudit['gap_inventory'] : [];
        return [
            'gap_count' => count($stackGaps),
            'gaps' => $stackGaps,
            'ui_spec_count' => (int)($stackCapabilities['ui_spec_count'] ?? 0),
            'api_contract_count' => (int)($stackCapabilities['api_contract_count'] ?? 0),
            'typed_source_page_count' => (int)($stackCapabilities['typed_source_page_count'] ?? 0),
            'ts_page_count' => (int)($stackCapabilities['ts_page_count'] ?? 0),
        ];
    } catch (Throwable $e) {
        $degraded[] = 'stack';
        return [
            'gap_count' => 0,
            'gaps' => [],
            'ui_spec_count' => 0,
            'api_contract_count' => 0,
            'typed_source_page_count' => 0,
            'ts_page_count' => 0,
        ];
    }
}

/**
 * Family mismatch hotspots with transient cache.
 * Landing never blocks on a cold full-catalog scan when no cache exists.
 */
function db_family_taxonomy_top_mismatch_pairs_cached(int $limit = 6, bool $allowColdCompute = false): array
{
    static $requestCache = [];
    $limit = max(1, min($limit, 20));
    $modeKey = $allowColdCompute ? 'cold' : 'cached_only';
    if (array_key_exists($modeKey, $requestCache)) {
        return $requestCache[$modeKey];
    }

    $cacheKey = md5(json_encode([
        'primary' => db_primary_catalog_name(),
        'limit' => $limit,
        'version' => APP_VERSION,
        'payload_contract' => 'family_mismatch_pairs_v1',
    ], JSON_UNESCAPED_SLASHES) ?: '');

    $cached = app_transient_cache_read('family_mismatch_pairs', $cacheKey, 300);
    if (is_array($cached)) {
        $requestCache[$modeKey] = $cached;
        return $requestCache[$modeKey];
    }

    $staleCached = app_transient_cache_read_stale('family_mismatch_pairs', $cacheKey);
    if (is_array($staleCached)) {
        $requestCache[$modeKey] = $staleCached;
        return $requestCache[$modeKey];
    }

    if (!$allowColdCompute) {
        // Do not memoize the skip path — a later warm/cold call in-process must still compute.
        return [];
    }

    $rows = db_family_taxonomy_top_mismatch_pairs($limit);
    app_transient_cache_write('family_mismatch_pairs', $cacheKey, $rows);
    $requestCache[$modeKey] = $rows;
    return $requestCache[$modeKey];
}

/**
 * Type-benchmark summary for landing: cache/stale only.
 * Cold materialization loads the full corpus and can OOM php-fpm (128MB).
 */
function db_dataset_type_benchmark_cached_only(): ?array
{
    $schema = db_dataset_readiness_schema_status();
    $includeResolution = db_dataset_readiness_include_resolution_surface();
    $includeLabelAuthority = db_dataset_readiness_include_label_authority_surface();
    $includeTypeAuthority = db_dataset_readiness_include_type_authority_surface();

    if (!$schema['ok']) {
        return [
            'ok' => false,
            'summary' => [],
            'cache_state' => 'schema_missing',
        ];
    }

    $transientKey = md5(json_encode([
        'primary' => db_primary_catalog_name(),
        'include_resolution' => $includeResolution,
        'include_label_authority' => $includeLabelAuthority,
        'include_type_authority' => $includeTypeAuthority,
        'readiness_model' => 'authority_tiers_v2_android_scope_clean_benchmark_v4',
        'version' => APP_VERSION,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $cacheNamespace = 'dataset_type_benchmark_clean_v3';

    $cached = app_transient_cache_read($cacheNamespace, $transientKey, 21600);
    if (is_array($cached)) {
        $cached['cache_state'] = 'hit';
        return $cached;
    }

    $staleCached = app_transient_cache_read_stale($cacheNamespace, $transientKey);
    if (is_array($staleCached)) {
        $staleCached['cache_state'] = 'stale';
        return $staleCached;
    }

    return null;
}
