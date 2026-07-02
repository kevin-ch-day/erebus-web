<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $recentRuns = get_int('recent_runs', 8, 1, 25);
    api_ok(db_pipeline_activity_snapshot($recentRuns), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'pipeline_activity_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load pipeline activity snapshot.', 500, 'ERR_PIPELINE_ACTIVITY', [], $e);
}
