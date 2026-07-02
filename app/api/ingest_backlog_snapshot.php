<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/api_helpers.php';
require_once __DIR__ . '/../database/db_func.php';

require_get();

try {
    $sourceFilter = isset($_GET['source']) ? trim((string)$_GET['source']) : null;
    $laneFilter = isset($_GET['lane']) ? trim((string)$_GET['lane']) : null;
    if ($sourceFilter === '') {
        $sourceFilter = null;
    }
    if ($laneFilter === '') {
        $laneFilter = null;
    }

    api_ok(db_ingest_backlog_snapshot($sourceFilter, $laneFilter), [
        'generated_at_utc' => gmdate('Y-m-d H:i:s'),
        'schema_surface' => 'ingest_backlog_snapshot_v1',
    ]);
} catch (Throwable $e) {
    api_error('Failed to load ingest backlog snapshot.', 500, 'ERR_INGEST_BACKLOG_SNAPSHOT', [], $e);
}
