<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $sampleId = get_int('sample_id', 0, 1, PHP_INT_MAX);
    if ($sampleId <= 0) {
        api_error('sample_id required', 400, 'ERR_SAMPLE_INPUT');
        exit;
    }

    $payload = db_android_permissions_summary($sampleId);
    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to load android permissions summary.', 500, 'ERR_ANDROID_PERMS_SUMMARY', [], $e);
}
