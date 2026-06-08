<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_post();
require_write_access();

try {
    $sampleId = get_int('sample_id', 0, 1, 2147483647, $_POST);
    if ($sampleId <= 0) {
        api_error('sample_id required', 400, 'ERR_SAMPLE_INPUT');
        exit;
    }

    $label = get_str('sample_label', 255, '', $_POST);
    $family = get_str('family_label', 255, '', $_POST);
    $primary = get_str('classification_primary', 255, '', $_POST);
    $subtype = get_str('classification_subtype', 255, '', $_POST);

    $payload = db_update_sample_metadata($sampleId, [
        'sample_label' => $label,
        'family_label' => $family,
        'classification_primary' => $primary,
        'classification_subtype' => $subtype,
    ]);

    api_ok($payload['data'] ?? [], $payload['meta'] ?? []);
} catch (Throwable $e) {
    api_error('Failed to update sample metadata.', 500, 'ERR_SAMPLE_UPDATE', [], $e);
}
