<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/services/dataset_readiness_service.php';
require_once __DIR__ . '/transient_cache.php';

function dataset_readiness_force_refresh_requested(): bool
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }

    $refresh = strtolower(trim((string)($_GET['refresh'] ?? '')));
    return in_array($refresh, ['1', 'true', 'yes', 'y'], true);
}

function dataset_readiness_cache_key(string $scope, array $payload = []): string
{
    return md5(json_encode([
        'scope' => $scope,
        'primary_catalog' => db_primary_catalog_name(),
        'permission_catalog' => db_permission_intel_catalog_name(),
        'split_enabled' => db_permission_intel_split_enabled() ? '1' : '0',
        'version' => defined('APP_VERSION') ? APP_VERSION : 'dev',
        'cache_generation' => function_exists('db_cache_generation_token') ? db_cache_generation_token() : '0',
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES) ?: '');
}

function dataset_readiness_fetch_overview(bool $includeTypeBenchmark = false): array
{
    static $cache = [];
    $cacheKey = $includeTypeBenchmark ? 'with_benchmark' : 'lightweight';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    $cache[$cacheKey] = db_dataset_readiness_overview($includeTypeBenchmark);
    return $cache[$cacheKey];
}

function dataset_readiness_fetch_label_surfaces(array $filters = []): array
{
    ksort($filters);
    $cacheKey = md5(json_encode($filters, JSON_UNESCAPED_SLASHES) ?: '');
    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $transientKey = dataset_readiness_cache_key('label_surfaces', $filters);
    $forceRefresh = dataset_readiness_force_refresh_requested();
    $cached = $forceRefresh ? null : app_transient_cache_read('dataset_readiness_label_surfaces', $transientKey, 120);
    if (is_array($cached)) {
        $cache[$cacheKey] = $cached;
        return $cache[$cacheKey];
    }
    if (!$forceRefresh) {
        $staleCached = app_transient_cache_read_stale('dataset_readiness_label_surfaces', $transientKey);
        if (is_array($staleCached)) {
            $cache[$cacheKey] = $staleCached;
            return $cache[$cacheKey];
        }
    }

    $cache[$cacheKey] = db_dataset_label_surfaces_page($filters);
    app_transient_cache_write('dataset_readiness_label_surfaces', $transientKey, $cache[$cacheKey]);
    return $cache[$cacheKey];
}

function dataset_readiness_fetch_type_benchmark(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = db_dataset_type_benchmark();
    return $cache;
}

function dataset_readiness_fetch_type_audit(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = db_dataset_type_slug_audit();
    return $cache;
}

function dataset_readiness_fetch_export_readiness(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = db_dataset_export_readiness();
    return $cache;
}

function dataset_readiness_fetch_authority_consistency_debt(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = db_dataset_authority_consistency_debt();
    return $cache;
}

function dataset_readiness_display_label(string $kind, ?string $value): string
{
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '') {
        return '--';
    }

    static $maps = [
        'authority_tier' => [
            'persisted_authority_fact' => 'Persisted authority fact',
            'derived_authority_projection' => 'Authority projection',
            'generic_token_policy_hold' => 'Generic/policy hold',
            'conflict_case' => 'Governance conflict case',
            'unresolved_authority' => 'Unresolved authority',
        ],
        'resolution_status' => [
            'authority_resolved' => 'Authority resolved',
            'projection_materialization_debt' => 'Projection materialization debt',
            'policy_hold' => 'Policy hold',
            'unknown_family_hold' => 'Unknown-family hold',
            'insufficient_context' => 'Insufficient context',
            'unresolved_authority' => 'Unresolved authority',
            'proposal_only' => 'Proposal only',
            'mapped_pending_review' => 'Mapped, pending review',
            'accepted_resolution' => 'Accepted resolution',
            'governed_alias' => 'Governed alias',
            'aligned' => 'Aligned',
            'taxonomy_mismatch' => 'Taxonomy mismatch',
            'coverage_gap' => 'Coverage gap',
            'needs_review' => 'Needs review',
        ],
        'recommended_use' => [
            'type_slug_target' => 'Clean type benchmark row',
            'type_slug_target_with_conflict_review' => 'Type row with conflict review',
            'type_slug_projection_materialization_review' => 'Projection row needing fact materialization',
            'hold_generic_signal_not_for_benchmark' => 'Hold, not benchmark truth',
            'governance_conflict_review' => 'Governance conflict review',
            'type_slug_effective_authority_review' => 'Effective-authority review row',
            'type_slug_subtype_fallback_review' => 'Subtype fallback review row',
            'type_slug_primary_fallback_review' => 'Primary fallback review row',
            'proposal_only_not_for_benchmark' => 'Proposal only, not benchmark truth',
            'category_subtype_aux_only' => 'Auxiliary subtype only',
            'category_primary_not_target' => 'Coarse primary only, not target',
            'unresolved' => 'Unresolved',
            'trainable_n10' => 'Trainable at n>=10',
            'trainable_n3_review' => 'Trainable at n>=3 with review',
            'insufficient_support' => 'Insufficient support',
        ],
        'type_source' => [
            'family_type_authority' => 'Family-to-type authority',
            'governed_type_authority' => 'Governed type authority',
            'catalog_family_type' => 'Catalog family type',
            'effective_type_authority' => 'Effective authority fallback',
            'classification_subtype' => 'Classification subtype fallback',
            'classification_primary' => 'Classification primary fallback',
            'vt_popular_threat_category' => 'VT category proposal',
            'unresolved' => 'Unresolved',
        ],
        'confidence' => [
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            'proposal' => 'Proposal only',
            'none' => 'None',
            'authority_resolved' => 'Authority resolved',
            'projection_review' => 'Projection review',
            'policy_hold' => 'Policy hold',
            'conflict_review' => 'Conflict review',
            'unknown_placeholder_hold' => 'Unknown-placeholder hold',
            'fallback_review' => 'Fallback review',
            'unresolved' => 'Unresolved',
        ],
        'source_bucket' => [
            'persisted_authority_fact' => 'Persisted authority fact',
            'authority_projection' => 'Authority projection',
            'projection_materialization_debt' => 'Projection materialization debt',
            'generic_policy_hold' => 'Generic/policy hold',
            'governance_conflict_case' => 'Governance conflict case',
            'unknown_family_hold' => 'Unknown-family hold',
            'proposal_only' => 'Proposal only',
            'fallback_review' => 'Fallback review',
            'unresolved_authority' => 'Unresolved authority',
        ],
    ];

    $map = $maps[$kind] ?? [];
    return $map[$normalized] ?? str_replace('_', ' ', $value ?? '');
}
