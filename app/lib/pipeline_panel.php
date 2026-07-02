<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/services/pipeline_service.php';

/**
 * @return array{
 *   pipeline: array<string, mixed>,
 *   hint: string,
 *   lane_summary: string,
 *   recommended_lane: ?string,
 *   source: string
 * }
 */
function pipeline_panel_load(bool $preferEngine = true): array
{
    try {
        $pipeline = db_pipeline_status($preferEngine);
        $queueLanes = is_array($pipeline['queue_lanes'] ?? null) ? $pipeline['queue_lanes'] : [];

        return [
            'pipeline' => $pipeline,
            'hint' => db_pipeline_operator_hint($pipeline),
            'lane_summary' => db_pipeline_lane_summary($queueLanes),
            'recommended_lane' => db_pipeline_recommended_lane($pipeline),
            'source' => trim((string)($pipeline['source'] ?? 'db')) !== '' ? (string)$pipeline['source'] : 'db',
        ];
    } catch (Throwable) {
        return [
            'pipeline' => [],
            'hint' => '',
            'lane_summary' => '',
            'recommended_lane' => null,
            'source' => 'db',
        ];
    }
}

/**
 * @param array{
 *   pipeline?: array<string, mixed>,
 *   hint?: string,
 *   lane_summary?: string,
 *   recommended_lane?: ?string,
 *   source?: string
 * } $ctx
 * @param array{
 *   id_prefix?: string,
 *   variant?: 'full'|'compact'|'notice',
 *   slice_note?: string|null,
 *   title?: string,
 *   copy?: bool,
 *   live_meta_id?: string|null
 * } $options
 */
function render_pipeline_engine_panel(array $ctx, array $options = []): void
{
    $pipeline = is_array($ctx['pipeline'] ?? null) ? $ctx['pipeline'] : [];
    if ($pipeline === [] && trim((string)($ctx['hint'] ?? '')) === '') {
        return;
    }

    $idPrefix = trim((string)($options['id_prefix'] ?? 'pipeline-engine'));
    $variant = (string)($options['variant'] ?? 'full');
    $sliceNote = $options['slice_note'] ?? null;
    $title = trim((string)($options['title'] ?? 'Engine recommendation'));
    $showCopy = (bool)($options['copy'] ?? false);
    $liveMetaId = $options['live_meta_id'] ?? null;

    $hint = trim((string)($ctx['hint'] ?? ''));
    $laneSummary = trim((string)($ctx['lane_summary'] ?? ''));
    $recommendedLane = $ctx['recommended_lane'] ?? null;
    $source = trim((string)($ctx['source'] ?? 'db')) !== '' ? (string)$ctx['source'] : 'db';

    $pipelineCore = is_array($pipeline['pipeline'] ?? null) ? $pipeline['pipeline'] : [];
    $pipelineVt = is_array($pipeline['vt'] ?? null) ? $pipeline['vt'] : [];
    $runPlan = is_array($pipeline['run_plan'] ?? null) ? $pipeline['run_plan'] : [];
    $recommendation = is_array($pipeline['recommendation'] ?? null) ? $pipeline['recommendation'] : [];

    $queuePending = (int)($pipelineCore['queue_pending'] ?? 0);
    $stateEligible = (int)($pipelineCore['state_eligible_now'] ?? 0);
    $action = trim((string)($recommendation['action'] ?? ''));
    $noticeClass = $action === 'wait_vt_blocked' ? 'warn' : 'info';
    $runCommand = trim((string)($runPlan['command'] ?? ($recommendation['command'] ?? '')));

    include __DIR__ . '/../views/partials/pipeline_engine_panel.php';
}
