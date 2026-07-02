<?php
declare(strict_types=1);

require_once __DIR__ . '/runs_service.php';
require_once __DIR__ . '/pipeline_service.php';

function db_run_ledger_recent_summary(): array
{
    $latest = db_one(
        "
        SELECT
            run_id,
            started_at_utc,
            finished_at_utc,
            processed_count,
            ok_count,
            error_count,
            stopped_reason
        FROM virustotal_run_ledger
        ORDER BY run_id DESC
        LIMIT 1
        "
    );

    $window = db_one(
        "
        SELECT
            COUNT(*) AS runs_24h,
            COALESCE(SUM(processed_count), 0) AS processed_24h,
            COALESCE(SUM(ok_count), 0) AS ok_24h,
            COALESCE(SUM(error_count), 0) AS errors_24h
        FROM virustotal_run_ledger
        WHERE finished_at_utc IS NOT NULL
          AND finished_at_utc >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)
        "
    );

    return [
        'latest_run_id' => isset($latest['run_id']) ? (int)$latest['run_id'] : null,
        'latest_started_at_utc' => (string)($latest['started_at_utc'] ?? ''),
        'latest_finished_at_utc' => (string)($latest['finished_at_utc'] ?? ''),
        'latest_processed_count' => isset($latest['processed_count']) ? (int)$latest['processed_count'] : null,
        'latest_ok_count' => isset($latest['ok_count']) ? (int)$latest['ok_count'] : null,
        'latest_error_count' => isset($latest['error_count']) ? (int)$latest['error_count'] : null,
        'latest_stopped_reason' => (string)($latest['stopped_reason'] ?? ''),
        'runs_24h' => (int)($window['runs_24h'] ?? 0),
        'processed_24h' => (int)($window['processed_24h'] ?? 0),
        'ok_24h' => (int)($window['ok_24h'] ?? 0),
        'errors_24h' => (int)($window['errors_24h'] ?? 0),
    ];
}

function db_pipeline_activity_snapshot(int $recentRuns = 8): array
{
    $recentRuns = max(1, min($recentRuns, 25));
    $pipeline = db_pipeline_status(true);
    $ledger = db_run_ledger_list([
        'page' => 1,
        'page_size' => $recentRuns,
        'q' => '',
    ]);

    return [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'pipeline' => $pipeline,
        'recommended_lane' => db_pipeline_recommended_lane($pipeline),
        'run_summary' => db_run_ledger_recent_summary(),
        'recent_runs' => is_array($ledger['rows'] ?? null) ? $ledger['rows'] : [],
        'platform_context' => is_array($ledger['platform_context'] ?? null) ? $ledger['platform_context'] : [],
    ];
}

function db_pending_source_mix_snapshot(?string $laneFilter = null, int $limit = 50): array
{
    $lane = $laneFilter !== null && trim($laneFilter) !== '' ? trim($laneFilter) : null;
    $limit = clamp_int($limit, 5, 100, 50);
    $pipeline = db_pipeline_status(true);

    return [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'filters' => ['lane' => $lane],
        'sources' => db_ingest_backlog_pending_sources($limit, $lane),
        'totals' => db_ingest_backlog_totals(null, $lane),
        'pipeline' => $pipeline,
        'recommended_lane' => db_pipeline_recommended_lane($pipeline),
    ];
}
