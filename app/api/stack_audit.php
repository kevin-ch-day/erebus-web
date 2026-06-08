<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $payload = db_stack_audit();
    api_ok($payload, [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'stack_audit_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load stack audit.', 500, 'ERR_STACK_AUDIT', [], $e);
}
