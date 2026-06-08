<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/transient_cache.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

function family_taxonomy_check_force_refresh_requested(): bool
{
    $refresh = strtolower(trim((string)($_GET['refresh'] ?? '')));
    return in_array($refresh, ['1', 'true', 'yes', 'y'], true);
}

function family_taxonomy_check_cache_key(array $params): string
{
    ksort($params);
    return hash('sha256', json_encode($params, JSON_UNESCAPED_SLASHES));
}

function family_taxonomy_check_cache_read(string $cacheKey, int $ttlSeconds): ?array
{
    return app_transient_cache_read('family_taxonomy_check', $cacheKey, $ttlSeconds);
}

function family_taxonomy_check_cache_read_stale(string $cacheKey): ?array
{
    return app_transient_cache_read_stale('family_taxonomy_check', $cacheKey);
}

function family_taxonomy_check_cache_write(string $cacheKey, array $payload): void
{
    app_transient_cache_write('family_taxonomy_check', $cacheKey, $payload);
}

try {
    $limit = get_int('limit', 100, 1, 250);
    $alignment = trim((string)($_GET['alignment'] ?? ''));
    $platform = strtolower(trim((string)($_GET['platform'] ?? '')));
    $query = trim((string)($_GET['q'] ?? ''));
    $pattern = trim((string)($_GET['pattern'] ?? ''));
    $pairCatalog = trim((string)($_GET['pair_catalog'] ?? ''));
    $pairSignal = trim((string)($_GET['pair_signal'] ?? ''));
    $fixAction = trim((string)($_GET['fix_action'] ?? ''));
    $targetFamily = trim((string)($_GET['target_family'] ?? ''));
    $decisionMode = trim((string)($_GET['decision_mode'] ?? ''));
    $includeRows = get_bool('include_rows', true);
    $cacheParams = [
        'limit' => $limit,
        'alignment' => $alignment,
        'platform' => $platform,
        'query' => $query,
        'pattern' => $pattern,
        'pair_catalog' => $pairCatalog,
        'pair_signal' => $pairSignal,
        'fix_action' => $fixAction,
        'target_family' => $targetFamily,
        'decision_mode' => $decisionMode,
        'include_rows' => $includeRows ? '1' : '0',
    ];
    $cacheKey = family_taxonomy_check_cache_key($cacheParams);
    $cacheTtlSeconds = $includeRows ? 90 : 1800;
    $forceRefresh = family_taxonomy_check_force_refresh_requested();
    $cached = $forceRefresh ? null : family_taxonomy_check_cache_read($cacheKey, $cacheTtlSeconds);
    if ($cached !== null) {
        api_ok($cached['data'] ?? [], $cached['meta'] ?? []);
        return;
    }
    if (!$forceRefresh) {
        $staleCached = family_taxonomy_check_cache_read_stale($cacheKey);
        if ($staleCached !== null) {
            api_ok($staleCached['data'] ?? [], $staleCached['meta'] ?? []);
            return;
        }
    }
    $payload = db_family_taxonomy_check($limit, $alignment, $platform, $query, $pattern, $pairCatalog, $pairSignal, $fixAction, $targetFamily, $decisionMode, $includeRows);
    family_taxonomy_check_cache_write($cacheKey, $payload);
    api_ok($payload['data'], $payload['meta']);
} catch (Throwable $e) {
    api_error('Failed to load family taxonomy check.', 500, 'ERR_FAMILY_TAXONOMY_CHECK', [], $e);
}
