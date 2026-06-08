<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $sampleId = get_int('sample_id', 0, 1, PHP_INT_MAX);
    $sha256 = get_str('sha256', 64, '');

    $sampleIdValue = $sampleId > 0 ? $sampleId : null;
    $shaValue = $sha256 !== '' ? $sha256 : null;

    if ($sampleIdValue === null && $shaValue === null) {
        api_error('sample_id or sha256 required', 400, 'ERR_SAMPLE_INPUT');
        exit;
    }

    $payload = db_sample_detail($sampleIdValue, $shaValue);
    if (!($payload['ok'] ?? false)) {
        api_error($payload['error'] ?? 'Sample not found', 404, 'ERR_SAMPLE_NOT_FOUND');
        exit;
    }

    api_ok($payload);
} catch (Throwable $e) {
    api_error('Failed to load sample detail.', 500, 'ERR_SAMPLE_DETAIL', [], $e);
}
