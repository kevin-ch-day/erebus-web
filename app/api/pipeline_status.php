<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $preferEngine = !isset($_GET['db_only']) || !filter_var((string)$_GET['db_only'], FILTER_VALIDATE_BOOLEAN);
    api_ok(db_pipeline_status($preferEngine), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'pipeline_status_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load pipeline status payload.', 500, 'ERR_PIPELINE_STATUS', [], $e);
}
