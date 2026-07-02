<?php
declare(strict_types=1);

require_once __DIR__ . '/intake/backlog_service.php';
require_once __DIR__ . '/vt_service.php';
require_once __DIR__ . '/../queries/health_queries.php';

/**
 * Base URL for the Python Erebus engine read API (no trailing slash).
 * Example: http://127.0.0.1:45342
 */
function erebus_engine_api_base_url(): string
{
    $raw = getenv('EREBUS_ENGINE_API_URL');
    if ($raw === false || trim((string)$raw) === '') {
        return 'http://127.0.0.1:45342';
    }
    return rtrim(trim((string)$raw), '/');
}

function db_pipeline_lamda_vt_ready_count(): ?int
{
    $queueTable = db_catalog_table('malware_artifact_ingest_queue');
    $stateTable = db_catalog_table('virustotal_sample_state');
    $row = db_one(
        "
        SELECT COUNT(*) AS cnt
        FROM {$queueTable} q
        LEFT JOIN {$stateTable} s
          ON s.sha256 = q.artifact_hash_sha256
        WHERE q.queue_status = 'PENDING'
          AND TRIM(COALESCE(q.workload_lane, '')) = 'lamda_android_apk'
          AND COALESCE(TRIM(q.artifact_hash_sha256), '') <> ''
          AND (
              s.sha256 IS NULL
              OR s.vt_status_code NOT IN ('LOOKED_UP', 'NO_DATA', 'QUARANTINED', 'DISABLED')
          )
        ",
        []
    );

    return is_array($row) ? (int)($row['cnt'] ?? 0) : null;
}

function db_pipeline_resolve_run_plan(array $queueLanes): ?array
{
    $lamdaPending = (int)($queueLanes['lamda_pending'] ?? 0);
    $reservoirPending = (int)($queueLanes['reservoir_pending'] ?? 0);
    $lamdaVtReady = (int)($queueLanes['lamda_vt_ready'] ?? 0);
    $topLane = trim((string)($queueLanes['top_workload_lane'] ?? ''));

    if ($lamdaVtReady >= 4 && $lamdaPending > 0) {
        return [
            'mode' => 'lamda_burst',
            'lane' => 'lamda_android_apk',
            'reason' => 'lamda_vt_ready_burst',
            'command' => 'erebus pipeline lamda --prepare --wait-for-keys --rows 4 --bursts 1',
        ];
    }
    if ($reservoirPending >= $lamdaPending && $reservoirPending > 0) {
        return [
            'mode' => 'queue_batch',
            'lane' => 'raw_hash_reservoir',
            'reason' => 'reservoir_dominant',
            'command' => 'erebus pipeline run --lane raw_hash_reservoir --limit 500',
        ];
    }
    if ($topLane === 'raw_hash_reservoir' && $reservoirPending > 0) {
        return [
            'mode' => 'queue_batch',
            'lane' => 'raw_hash_reservoir',
            'reason' => 'top_lane_reservoir',
            'command' => 'erebus pipeline run --lane raw_hash_reservoir --limit 500',
        ];
    }
    if ($topLane !== '') {
        return [
            'mode' => 'queue_batch',
            'lane' => $topLane,
            'reason' => 'default_lane',
            'command' => sprintf('erebus pipeline run --lane %s --limit 500', $topLane),
        ];
    }

    return null;
}

function db_pipeline_recommend(
    int $queuePending,
    int $queueProcessing,
    int $stateEligible,
    int $reanalyzeOrphans,
    ?int $vtReadyKeys,
    bool $vtHoldActive,
    ?int $vtQuotaRemaining,
    ?array $queueLanes = null,
): array {
    $readyKeys = (int)($vtReadyKeys ?? 0);
    $quota = (int)($vtQuotaRemaining ?? 0);

    if ($vtHoldActive) {
        return [
            'action' => 'wait_vt_blocked',
            'summary' => 'Global VT hold is active — wait or clear hold before mining.',
            'command' => 'erebus vt status --full',
        ];
    }
    if ($readyKeys <= 0) {
        return [
            'action' => 'wait_vt_blocked',
            'summary' => 'No VT keys are ready — check key pool and cooldowns.',
            'command' => 'erebus vt check-keys',
        ];
    }
    if ($quota <= 0 && $stateEligible <= 0 && $queuePending <= 0) {
        return [
            'action' => 'wait_vt_blocked',
            'summary' => 'VT daily quota appears exhausted and no backlog work is queued.',
            'command' => 'erebus vt status',
        ];
    }

    $lamdaVtReady = (int)($queueLanes['lamda_vt_ready'] ?? 0);
    $lamdaPending = (int)($queueLanes['lamda_pending'] ?? 0);
    if (
        $queuePending > 0
        && $lamdaVtReady >= 4
        && $lamdaPending > 0
    ) {
        return [
            'action' => 'run_queue',
            'summary' => sprintf(
                '%s LAMDA row(s) VT-ready — run a paced burst before generic reservoir backlog.',
                number_format($lamdaVtReady)
            ),
            'command' => 'erebus pipeline lamda --prepare --wait-for-keys --rows 4 --bursts 1',
        ];
    }

    if ($queuePending > 0 && $stateEligible === 0) {
        return [
            'action' => 'run_queue',
            'summary' => sprintf(
                '%s ingest-queue row(s) pending with no vt_state-eligible work — feed the queue first.',
                number_format($queuePending)
            ),
            'command' => 'erebus pipeline run --limit 500',
        ];
    }
    if ($queuePending > 0) {
        return [
            'action' => 'run_queue',
            'summary' => sprintf(
                '%s ingest-queue row(s) waiting — queue mining feeds new hashes (%s vt_state-eligible also available).',
                number_format($queuePending),
                number_format($stateEligible)
            ),
            'command' => 'erebus pipeline run --limit 500',
        ];
    }
    if ($stateEligible > 0) {
        $orphanNote = $reanalyzeOrphans > 0
            ? sprintf(' (%s REANALYZE orphans)', number_format($reanalyzeOrphans))
            : '';
        return [
            'action' => 'run_state',
            'summary' => sprintf(
                '%s vt_state-eligible row(s)%s — run a bounded state batch.',
                number_format($stateEligible),
                $orphanNote
            ),
            'command' => 'erebus pipeline state --limit 1000',
        ];
    }
    if ($queueProcessing > 0) {
        return [
            'action' => 'idle',
            'summary' => sprintf(
                'No pending queue rows; %s PROCESSING residue — reclaim or wait before starting a new wave.',
                number_format($queueProcessing)
            ),
            'command' => 'erebus pipeline status --json',
        ];
    }

    return [
        'action' => 'idle',
        'summary' => 'Pipeline idle — no queue backlog and no vt_state-eligible work.',
        'command' => null,
    ];
}

function db_pipeline_queue_lanes(): array
{
    $queueTable = db_catalog_table('malware_artifact_ingest_queue');
    $laneRows = db_all(
        "
        SELECT TRIM(workload_lane) AS lane_value, COUNT(*) AS cnt
        FROM {$queueTable}
        WHERE queue_status = 'PENDING'
          AND TRIM(COALESCE(workload_lane, '')) <> ''
          AND TRIM(workload_lane) IN ('lamda_android_apk', 'raw_hash_reservoir')
        GROUP BY TRIM(workload_lane)
        ",
        []
    ) ?? [];

    $lamdaPending = 0;
    $reservoirPending = 0;
    foreach ($laneRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $lane = (string)($row['lane_value'] ?? '');
        $count = (int)($row['cnt'] ?? 0);
        if ($lane === 'lamda_android_apk') {
            $lamdaPending = $count;
        } elseif ($lane === 'raw_hash_reservoir') {
            $reservoirPending = $count;
        }
    }

    $topLaneRow = db_one(
        "
        SELECT TRIM(workload_lane) AS lane_value, COUNT(*) AS cnt
        FROM {$queueTable}
        WHERE queue_status = 'PENDING'
          AND TRIM(COALESCE(workload_lane, '')) <> ''
        GROUP BY TRIM(workload_lane)
        ORDER BY cnt DESC, lane_value ASC
        LIMIT 1
        ",
        []
    );

    $lamdaVtReady = db_pipeline_lamda_vt_ready_count();

    return [
        'lamda_pending' => $lamdaPending,
        'reservoir_pending' => $reservoirPending,
        'lamda_vt_ready' => $lamdaVtReady,
        'top_workload_lane' => is_array($topLaneRow)
            ? (string)($topLaneRow['lane_value'] ?? '')
            : null,
        'top_lane_count' => is_array($topLaneRow) ? (int)($topLaneRow['cnt'] ?? 0) : 0,
    ];
}

function db_pipeline_status_assemble(
    array $queueTotals,
    int $stateEligible,
    array $vtKeyStatus,
    ?int $reanalyzeOrphans = null,
): array {
    $queuePending = (int)($queueTotals['pending_rows'] ?? 0);
    $queueProcessing = (int)($queueTotals['processing_rows'] ?? 0);
    $queueFailed = (int)($queueTotals['failed_rows'] ?? 0);

    $keyPosture = is_array($vtKeyStatus['key_posture'] ?? null) ? $vtKeyStatus['key_posture'] : [];
    $hold = is_array($vtKeyStatus['hold'] ?? null) ? $vtKeyStatus['hold'] : [];

    $readyKeys = isset($keyPosture['eligible_keys']) ? (int)$keyPosture['eligible_keys'] : null;
    $quotaRemaining = isset($keyPosture['eligible_remaining_quota'])
        ? (int)$keyPosture['eligible_remaining_quota']
        : null;
    $holdActive = (bool)($hold['active_hold'] ?? false);

    $stateTotal = null;
    $stateTable = db_catalog_table('virustotal_sample_state');
    $stateTotalRow = db_one("SELECT COUNT(*) AS cnt FROM {$stateTable}");
    if (is_array($stateTotalRow)) {
        $stateTotal = (int)($stateTotalRow['cnt'] ?? 0);
    }

    if ($reanalyzeOrphans === null) {
        $queueTable = db_catalog_table('malware_artifact_ingest_queue');
        $orphanRow = db_one(
            "
            SELECT COUNT(*) AS cnt
            FROM {$stateTable} s
            LEFT JOIN {$queueTable} q
              ON q.artifact_hash_sha256 = s.sha256
             AND q.queue_status IN ('PENDING', 'PROCESSING')
            WHERE s.vt_status_code = 'REANALYZE'
              AND s.claim_token IS NULL
              AND q.ingest_id IS NULL
            "
        );
        $reanalyzeOrphans = is_array($orphanRow) ? (int)($orphanRow['cnt'] ?? 0) : 0;
    }

    $queueLanes = db_pipeline_queue_lanes();

    $recommendation = db_pipeline_recommend(
        $queuePending,
        $queueProcessing,
        $stateEligible,
        (int)$reanalyzeOrphans,
        $readyKeys,
        $holdActive,
        $quotaRemaining,
        $queueLanes,
    );

    $runPlan = null;
    if (($recommendation['action'] ?? '') === 'run_queue') {
        $runPlan = db_pipeline_resolve_run_plan($queueLanes);
        if (is_array($runPlan) && !empty($runPlan['command'])) {
            $recommendation['command'] = (string)$runPlan['command'];
        }
    }

    $safeForBatch = !$holdActive
        && (int)($readyKeys ?? 0) > 0
        && ((int)($quotaRemaining ?? 0) > 0 || $queuePending > 0 || $stateEligible > 0);

    return [
        'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        'database' => db_primary_catalog_name(),
        'pipeline' => [
            'queue_pending' => $queuePending,
            'queue_processing' => $queueProcessing,
            'queue_failed' => $queueFailed,
            'queue_label' => db_ingest_queue_status_label(
                $queuePending,
                $queueProcessing,
                $queueFailed,
                (int)($queueTotals['done_rows'] ?? 0),
                (int)($queueTotals['queue_rows'] ?? 0),
            ),
            'state_eligible_now' => $stateEligible,
            'state_total' => $stateTotal,
            'reanalyze_orphans' => $reanalyzeOrphans,
        ],
        'vt' => [
            'hold_active' => $holdActive,
            'hold_until_utc' => (string)($hold['hold_until_utc'] ?? ''),
            'hold_reason_code' => (string)($hold['hold_reason_code'] ?? ''),
            'keys_enabled' => isset($keyPosture['enabled_keys']) ? (int)$keyPosture['enabled_keys'] : null,
            'keys_ready' => $readyKeys,
            'quota_remaining' => $quotaRemaining,
            'safe_for_controlled_batch' => $safeForBatch,
        ],
        'recommendation' => $recommendation,
        'queue_lanes' => $queueLanes,
        'run_plan' => $runPlan,
    ];
}

function db_pipeline_status_from_db(): array
{
    $totals = db_ingest_backlog_totals();
    $eligibleRow = db_one(sql_count_eligible_now());
    $stateEligible = (int)($eligibleRow['eligible_now'] ?? 0);
    $vtKeyStatus = db_vt_key_status_snapshot();
    $payload = db_pipeline_status_assemble($totals, $stateEligible, $vtKeyStatus);
    $payload['source'] = 'db';
    return $payload;
}

function db_pipeline_status_fetch_engine_api(): ?array
{
    $base = erebus_engine_api_base_url();
    if ($base === '') {
        return null;
    }

    $url = $base . '/api/v1/pipeline/status';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 2.0,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !array_key_exists('pipeline', $decoded)) {
        return null;
    }

    $decoded['source'] = 'engine_api';
    $decoded['ok'] = true;
    return $decoded;
}

function db_pipeline_status(bool $preferEngineApi = true): array
{
    static $requestCache = null;
    if (is_array($requestCache)) {
        return $requestCache;
    }

    if ($preferEngineApi) {
        $remote = db_pipeline_status_fetch_engine_api();
        if (is_array($remote)) {
            $requestCache = $remote;
            return $requestCache;
        }
    }

    $requestCache = db_pipeline_status_from_db();
    return $requestCache;
}

function db_pipeline_lane_summary(array $queueLanes): string
{
    $parts = [];
    $lamdaPending = (int)($queueLanes['lamda_pending'] ?? 0);
    $reservoirPending = (int)($queueLanes['reservoir_pending'] ?? 0);
    $lamdaVtReady = $queueLanes['lamda_vt_ready'] ?? null;

    if ($lamdaPending > 0) {
        $parts[] = sprintf('LAMDA %s pending', number_format($lamdaPending));
    }
    if ($reservoirPending > 0) {
        $parts[] = sprintf('reservoir %s pending', number_format($reservoirPending));
    }
    if ($lamdaVtReady !== null && (int)$lamdaVtReady > 0) {
        $parts[] = sprintf('%s LAMDA VT-ready', number_format((int)$lamdaVtReady));
    }

    $topLane = trim((string)($queueLanes['top_workload_lane'] ?? ''));
    if ($topLane !== '' && $parts === []) {
        $parts[] = 'top lane ' . $topLane;
    }

    return implode(' · ', $parts);
}

function db_pipeline_operator_hint(array $payload): string
{
    $recommendation = is_array($payload['recommendation'] ?? null) ? $payload['recommendation'] : [];
    $summary = trim((string)($recommendation['summary'] ?? ''));
    $command = trim((string)($recommendation['command'] ?? ''));
    if ($summary === '') {
        return '';
    }

    return $command !== '' ? $summary . ' · CLI: ' . $command : $summary;
}

function db_pipeline_recommended_lane(array $payload): ?string
{
    $runPlan = is_array($payload['run_plan'] ?? null) ? $payload['run_plan'] : [];
    $lane = trim((string)($runPlan['lane'] ?? ''));
    return $lane !== '' ? $lane : null;
}
