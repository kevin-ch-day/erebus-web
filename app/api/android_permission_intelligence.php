<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../lib/transient_cache.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

function android_permission_intelligence_cache_key(array $params): string
{
    ksort($params);
    return md5(json_encode($params, JSON_UNESCAPED_SLASHES) ?: '');
}

try {
    $mode = get_enum('mode', ['full', 'overview', 'triage', 'drift'], 'overview');
    $limit = get_int('limit', 100, 10, 200);
    $namespaceLimit = get_int('namespace_limit', $limit, 10, 200);
    $page = get_int('page', 1, 1, 100000);
    $pageSize = get_int('page_size', $limit, 10, 500);
    $search = get_str('q', 255, '');
    $namespace = get_str('namespace', 255, '');
    $risk = get_enum('risk', ['high', 'medium', 'low'], null);
    $status = get_enum('status', array_map('strtolower', perm_triage_status_keys()), null);
    $queued = get_enum('queued', array_map('strtolower', perm_queue_status_keys()), null);
    $triageView = get_enum('view', ['active', 'governed', 'ledger'], null);
    if ($triageView === null) {
        $triageView = get_enum('lane', ['active', 'governed', 'ledger'], 'active');
    }
    $sort = get_enum(
        'sort',
        [
            'seen_desc', 'seen_asc',
            'last_seen_desc', 'last_seen_asc',
            'permission_asc', 'permission_desc',
            'status_asc', 'status_desc',
            'namespace_asc', 'namespace_desc',
            'risk_desc', 'risk_asc',
        ],
        'seen_desc'
    );
    $includeResolved = get_bool('include_resolved', true);

    $triageFilters = [
        'page' => $page,
        'page_size' => $pageSize,
        'q' => $search,
        'namespace' => $namespace,
        'risk' => $risk,
        'status' => $status,
        'queued' => $queued,
        'triage_view' => $triageView,
        'sort' => $sort,
        'include_resolved' => $includeResolved,
        'actionable_statuses' => perm_actionable_triage_status_keys(),
    ];

    $cacheableMode = in_array($mode, ['overview', 'drift'], true)
        || ($mode === 'triage' && in_array($triageView, ['governed', 'ledger'], true));
    $allowStaleApiCache = ($mode === 'drift');
    if ($cacheableMode) {
        $cacheNamespace = 'permission_intel_' . $mode;
        if ($mode === 'triage') {
            $cacheNamespace .= '_' . $triageView;
        }
        $cacheParams = [
            'mode' => $mode,
            'triage_view' => $triageView,
            'limit' => $limit,
            'namespace_limit' => $namespaceLimit,
            'page' => $page,
            'page_size' => $pageSize,
            'q' => $search,
            'namespace' => $namespace,
            'risk' => $risk,
            'status' => $status,
            'queued' => $queued,
            'triage_view' => $triageView,
            'sort' => $sort,
            'include_resolved' => $includeResolved ? '1' : '0',
            'primary_catalog' => db_primary_catalog_name(),
            'permission_catalog' => db_permission_intel_catalog_name(),
            'split_enabled' => db_permission_intel_split_enabled() ? '1' : '0',
            'app_version' => APP_VERSION,
            'surface_contract' => '2026-06-08-governed-residue-v1',
        ];
        $cacheKey = android_permission_intelligence_cache_key($cacheParams);
        $cacheTtlSeconds = $mode === 'overview'
            ? 300
            : ($mode === 'drift' ? 180 : 120);
        $cached = app_transient_cache_read($cacheNamespace, $cacheKey, $cacheTtlSeconds);
        if ($cached !== null) {
            $cachedMeta = is_array($cached['meta'] ?? null) ? $cached['meta'] : [];
            $cachedMeta['cache_hit'] = true;
            api_ok($cached['data'] ?? [], $cachedMeta);
            exit;
        }
        if ($allowStaleApiCache) {
            $staleCached = app_transient_cache_read_stale($cacheNamespace, $cacheKey);
            if ($staleCached !== null) {
                $staleMeta = is_array($staleCached['meta'] ?? null) ? $staleCached['meta'] : [];
                $staleMeta['cache_hit'] = true;
                $staleMeta['cache_stale'] = true;
                api_ok($staleCached['data'] ?? [], $staleMeta);
                exit;
            }
        }
    }

    $payload = db_android_permission_intelligence($pageSize, $namespaceLimit, $triageFilters, $mode);
    $meta = $payload['meta'] ?? [];
    $namespaceSource = $meta['namespace_drift_source'] ?? '';
    if (!empty($meta['warnings']) || ($namespaceSource !== '' && $namespaceSource !== 'vt_event')) {
        $requestId = api_request_id();
        log_event(
            'WARN',
            'api',
            'WARN_PERMISSION_INTEL',
            'Permission intelligence warnings.',
            $requestId,
            array_merge(api_log_context(), [
                'warnings' => $meta['warnings'] ?? [],
                'namespace_drift_source' => $meta['namespace_drift_source'] ?? null,
                'namespace_drift_reason' => $meta['namespace_drift_reason'] ?? null,
            ]),
            'api'
        );
    }
    if ($cacheableMode) {
        $cacheNamespace = 'permission_intel_' . $mode;
        if ($mode === 'triage') {
            $cacheNamespace .= '_' . $triageView;
        }
        app_transient_cache_write($cacheNamespace, $cacheKey, [
            'data' => $payload['data'] ?? [],
            'meta' => array_merge($meta, ['cache_hit' => false]),
        ]);
        $meta['cache_hit'] = false;
    }
    api_ok($payload['data'] ?? [], $meta);
} catch (Throwable $e) {
    api_error('Failed to load permission intelligence.', 500, 'ERR_PERMISSION_INTEL', [], $e);
}
