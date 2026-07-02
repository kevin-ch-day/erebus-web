<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../lib/input.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $laneFilter = get_str('lane', 128, '');
    $limit = get_int('limit', 50, 5, 100);
    if ($laneFilter === '') {
        $laneFilter = null;
    }

    api_ok(db_pending_source_mix_snapshot($laneFilter, $limit), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'pending_source_mix_snapshot_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load pending source mix snapshot.', 500, 'ERR_PENDING_SOURCE_MIX', [], $e);
}
