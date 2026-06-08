<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $permission = get_str('permission', 255, '', $_GET);
    if ($permission === '') {
        api_error('permission required', 400, 'ERR_PERMISSION_REVIEW_INPUT');
        exit;
    }

    $payload = db_android_permission_review($permission);
    if (empty($payload['data'])) {
        api_error('Permission not found.', 404, 'ERR_PERMISSION_NOT_FOUND');
        exit;
    }

    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to load permission review.', 500, 'ERR_PERMISSION_REVIEW', [], $e);
}
