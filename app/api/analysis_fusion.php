<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/transient_cache.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $limit = get_int('limit', 50, 1, 250);
    $cacheKey = md5(json_encode([
        'limit' => $limit,
        'primary' => db_primary_catalog_name(),
        'permission' => db_permission_intel_catalog_name(),
        'split_enabled' => db_permission_intel_split_enabled() ? '1' : '0',
        'version' => APP_VERSION,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $cached = app_transient_cache_read('analysis_fusion_api', $cacheKey, 180);
    if (is_array($cached)) {
        api_ok($cached['data'] ?? [], array_merge((array)($cached['meta'] ?? []), ['cache_hit' => true]));
        exit;
    }
    $staleCached = app_transient_cache_read_stale('analysis_fusion_api', $cacheKey);
    if (is_array($staleCached)) {
        api_ok($staleCached['data'] ?? [], array_merge((array)($staleCached['meta'] ?? []), ['cache_hit' => true, 'cache_stale' => true]));
        exit;
    }
    $payload = db_analysis_fusion($limit);
    app_transient_cache_write('analysis_fusion_api', $cacheKey, [
        'data' => $payload['data'] ?? [],
        'meta' => $payload['meta'] ?? [],
    ]);
    api_ok($payload['data'], $payload['meta']);
} catch (Throwable $e) {
    api_error('Failed to load analysis fusion.', 500, 'ERR_ANALYSIS_FUSION', [], $e);
}
