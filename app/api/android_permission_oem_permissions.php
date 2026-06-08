<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $filters = [
        'page' => get_int('page', 1, 1, 100000),
        'page_size' => get_int('page_size', DEFAULT_PAGE_SIZE, 1, MAX_PAGE_SIZE),
        'q' => get_str('q', 255, ''),
        'namespace' => get_str('namespace', 120, ''),
        'status' => get_enum('status', perm_triage_status_keys(), null),
    ];

    $payload = db_android_permission_oem_permissions($filters);
    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to load OEM permissions.', 500, 'ERR_PERMISSION_OEM_PERMS', [], $e);
}

