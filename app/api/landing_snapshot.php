<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    api_ok(db_landing_snapshot(), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'landing_snapshot_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load landing snapshot.', 500, 'ERR_LANDING_SNAPSHOT', [], $e);
}
