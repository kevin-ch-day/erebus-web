<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $includeDiagnostics = get_bool('include_diagnostics', false);
    api_ok(db_health($includeDiagnostics), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'health_v1',
        'include_diagnostics' => $includeDiagnostics,
    ]);
} catch (Throwable $e) {
    api_error('Failed to load health payload.', 500, 'ERR_HEALTH', [], $e);
}
