<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $permission = get_str('permission', 200, '');
    if ($permission === '') {
        api_error('permission required', 400, 'ERR_PERMISSION_INPUT');
        exit;
    }

    $limit = get_int('limit', 25, 1, 200);
    $payload = db_android_permission_evidence($permission, $limit);
    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to load permission evidence.', 500, 'ERR_PERMISSION_EVIDENCE', [], $e);
}
