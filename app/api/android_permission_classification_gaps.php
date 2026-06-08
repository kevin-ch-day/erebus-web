<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $limit = get_int('limit', 25, 1, 250);
    $payload = db_android_permission_classification_gaps($limit);
    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to load permission classification gaps.', 500, 'ERR_PERMISSION_CLASSIFICATION_GAPS', [], $e);
}
