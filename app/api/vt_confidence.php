<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/transient_cache.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $limit = get_int('limit', 25, 1, 250);
    $cacheKey = md5(json_encode([
        'limit' => $limit,
        'primary' => db_primary_catalog_name(),
        'version' => APP_VERSION,
    ], JSON_UNESCAPED_SLASHES) ?: '');
    $cached = app_transient_cache_read('vt_confidence_api', $cacheKey, 120);
    if (is_array($cached)) {
        api_ok($cached['data'] ?? [], array_merge((array)($cached['meta'] ?? []), ['cache_hit' => true]));
        exit;
    }
    $staleCached = app_transient_cache_read_stale('vt_confidence_api', $cacheKey);
    if (is_array($staleCached)) {
        api_ok($staleCached['data'] ?? [], array_merge((array)($staleCached['meta'] ?? []), ['cache_hit' => true, 'cache_stale' => true]));
        exit;
    }
    $payload = db_vt_confidence($limit);
    app_transient_cache_write('vt_confidence_api', $cacheKey, [
        'data' => $payload['data'] ?? [],
        'meta' => $payload['meta'] ?? [],
    ]);
    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to load VT confidence.', 500, 'ERR_VT_CONFIDENCE', [], $e);
}
