<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../database/db_func.php';

$title = 'Pipeline Ops';
$pipelineApiUrl = api_url('pipeline_status.php');
$backlogApiUrl = api_url('ingest_backlog_snapshot.php');
$refreshSeconds = (int)DASHBOARD_REFRESH_SECONDS;

$pipeline = db_pipeline_status(true);
$activity = db_pipeline_activity_snapshot(6);
$pipelineCore = is_array($pipeline['pipeline'] ?? null) ? $pipeline['pipeline'] : [];
$pipelineVt = is_array($pipeline['vt'] ?? null) ? $pipeline['vt'] : [];
$engineHint = db_pipeline_operator_hint($pipeline);
$laneSummary = db_pipeline_lane_summary(
    is_array($pipeline['queue_lanes'] ?? null) ? $pipeline['queue_lanes'] : []
);
$recommendedLane = db_pipeline_recommended_lane($pipeline);
$engineSource = (string)($pipeline['source'] ?? 'db');
?>

<div id="pipeline-ops-page"
     style="display:none;"
     data-pipeline-endpoint="<?= h($pipelineApiUrl) ?>"
     data-activity-endpoint="<?= h(api_url('pipeline_activity.php')) ?>"
     data-backlog-endpoint="<?= h($backlogApiUrl) ?>"
     data-health-url="<?= h(page_url('health')) ?>"
     data-ingest-url="<?= h(page_url('ingest_backlog')) ?>"
     data-runs-url="<?= h(page_url('runs')) ?>"
     data-refresh-seconds="<?= h((string)$refreshSeconds) ?>"></div>

<section class="page-hero pipeline-ops-hero">
    <div class="page-hero-body">
        <div class="eyebrow">VirusTotal API</div>
        <div class="page-kicker">Headless mining control deck</div>
        <h1 class="page-hero-title">Pipeline Ops</h1>
        <p class="page-hero-lede muted">
            Live posture for intake → queue → VT → <code>vt_state</code>. Mirrors
            <code>erebus pipeline status</code> and recommends the next bounded CLI step.
            Execution stays on the engine — this page is read-only control-plane visibility.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('ingest_backlog')) ?>">Open Ingest Backlog</a>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
            <a class="btn" href="<?= h(page_url('runs')) ?>">Run Ledger</a>
        </div>
    </div>
    <aside class="page-hero-side surface-panel surface-panel-soft">
        <div class="landing-panel-heading">Engine snapshot</div>
        <div class="pipeline-ops-meta muted mono" id="pipeline-ops-source">source: <?= h($engineSource) ?></div>
        <div class="pipeline-ops-meta muted" id="pipeline-ops-refreshed">Initial SSR load</div>
        <?php if ($engineHint !== ''): ?>
            <div class="notice info pipeline-ops-notice" id="pipeline-ops-notice-ssr"><?= h($engineHint) ?></div>
        <?php endif; ?>
        <?php if ($laneSummary !== ''): ?>
            <div class="muted" style="margin-top:10px;" id="pipeline-ops-lanes-ssr"><?= h($laneSummary) ?></div>
        <?php endif; ?>
        <?php if ($recommendedLane !== null): ?>
            <div class="muted" style="margin-top:8px;">Suggested lane: <span class="mono"><?= h($recommendedLane) ?></span></div>
        <?php endif; ?>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Live posture</h2>
            <p class="section-shell-copy">Auto-refreshes every <?= h((string)$refreshSeconds) ?> seconds. Proxies the Python engine when <code>EREBUS_ENGINE_API_URL</code> is reachable.</p>
        </div>
        <div class="flow-inline">
            <button type="button" class="btn" id="pipeline-ops-copy-cmd">Copy CLI command</button>
            <button type="button" class="btn" id="pipeline-ops-refresh">Refresh now</button>
        </div>
    </div>
    <div id="pipeline-ops-dashboard" class="pipeline-ops-dashboard">
        <div class="health-tiles">
            <div class="health-tile">
                <div class="health-tile-label">Queue pending</div>
                <div class="health-tile-value" id="pipeline-ops-queue-pending"><?= number_format((int)($pipelineCore['queue_pending'] ?? 0)) ?></div>
            </div>
            <div class="health-tile">
                <div class="health-tile-label">Processing</div>
                <div class="health-tile-value" id="pipeline-ops-queue-processing"><?= number_format((int)($pipelineCore['queue_processing'] ?? 0)) ?></div>
            </div>
            <div class="health-tile">
                <div class="health-tile-label">vt_state eligible</div>
                <div class="health-tile-value" id="pipeline-ops-state-eligible"><?= number_format((int)($pipelineCore['state_eligible_now'] ?? 0)) ?></div>
            </div>
            <div class="health-tile">
                <div class="health-tile-label">VT keys ready</div>
                <div class="health-tile-value" id="pipeline-ops-vt-keys"><?= number_format((int)($pipelineVt['keys_ready'] ?? 0)) ?></div>
            </div>
            <div class="health-tile">
                <div class="health-tile-label">Quota remaining</div>
                <div class="health-tile-value" id="pipeline-ops-vt-quota"><?= number_format((int)($pipelineVt['quota_remaining'] ?? 0)) ?></div>
            </div>
        </div>

        <div class="detail-grid pipeline-ops-grid" style="margin-top: 16px;">
            <div class="detail-card pipeline-ops-card-wide">
                <div class="detail-card-title">Engine recommendation</div>
                <div class="detail-value" id="pipeline-ops-action"><?= h((string)(is_array($pipeline['recommendation'] ?? null) ? ($pipeline['recommendation']['action'] ?? '--') : '--')) ?></div>
                <div class="muted" style="margin-top:8px;" id="pipeline-ops-summary"><?= h((string)(is_array($pipeline['recommendation'] ?? null) ? ($pipeline['recommendation']['summary'] ?? 'Loading…') : 'Loading…')) ?></div>
                <div class="mono pipeline-ops-command" id="pipeline-ops-command"><?= h((string)(is_array($pipeline['recommendation'] ?? null) ? ($pipeline['recommendation']['command'] ?? '') : '')) ?></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Run plan</div>
                <div class="detail-value mono" id="pipeline-ops-run-mode"><?= h((string)(is_array($pipeline['run_plan'] ?? null) ? ($pipeline['run_plan']['mode'] ?? '--') : '--')) ?></div>
                <div class="muted" id="pipeline-ops-run-reason"><?= h((string)(is_array($pipeline['run_plan'] ?? null) ? ($pipeline['run_plan']['reason'] ?? '') : '')) ?></div>
                <div style="margin-top:10px;">
                    <a class="table-link" id="pipeline-ops-lane-link" href="<?= h(page_url('ingest_backlog', $recommendedLane !== null ? ['lane' => $recommendedLane] : [])) ?>">Focus suggested lane</a>
                </div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Lane posture</div>
                <div class="landing-chip-row" id="pipeline-ops-lane-chips">
                    <?php if ($laneSummary !== ''): ?>
                        <?php foreach (explode(' · ', $laneSummary) as $chip): ?>
                            <span class="landing-chip"><?= h($chip) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="landing-chip landing-empty">No lane data</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">VT hold</div>
                <div class="detail-value" id="pipeline-ops-hold"><?= !empty($pipelineVt['hold_active']) ? 'Active' : 'Clear' ?></div>
                <div class="muted" id="pipeline-ops-hold-detail">
                    <?= !empty($pipelineVt['hold_active'])
                        ? h('Until ' . (string)($pipelineVt['hold_until_utc'] ?? '') . ' · ' . (string)($pipelineVt['hold_reason_code'] ?? ''))
                        : 'No global hold blocking VT.' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="notice error" id="pipeline-ops-error" style="display:none; margin-top: 14px;"></div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Recent run ledger</h2>
            <p class="section-shell-copy">Last bounded VT batches from <code>virustotal_run_ledger</code>. Pair with Pipeline Ops recommendation before starting another wave.</p>
        </div>
        <a class="btn" href="<?= h(page_url('runs')) ?>">Open full Run Ledger</a>
    </div>
    <div class="muted" id="pipeline-ops-run-meta" style="margin-bottom: 10px;">
        <?php
        $runSummary = is_array($activity['run_summary'] ?? null) ? $activity['run_summary'] : [];
        ?>
        24h: <?= h(number_format((int)($runSummary['runs_24h'] ?? 0))) ?> runs ·
        <?= h(number_format((int)($runSummary['processed_24h'] ?? 0))) ?> processed ·
        last stopped: <?= h((string)($runSummary['latest_stopped_reason'] ?? '--')) ?>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Run ID</th>
                    <th>Finished</th>
                    <th>Processed</th>
                    <th>OK</th>
                    <th>Errors</th>
                    <th>Stopped reason</th>
                </tr>
            </thead>
            <tbody id="pipeline-ops-recent-runs">
                <?php foreach (is_array($activity['recent_runs'] ?? null) ? $activity['recent_runs'] : [] as $runRow): ?>
                    <tr>
                        <td><?= h((string)($runRow['run_id'] ?? '')) ?></td>
                        <td><?= h(fmt_utc_display((string)($runRow['finished_at_utc'] ?? ''), 'M d g:i A') ?: '--') ?></td>
                        <td><?= h(number_format((int)($runRow['processed_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($runRow['ok_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($runRow['error_count'] ?? 0))) ?></td>
                        <td><?= h((string)($runRow['stopped_reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Operator CLI map</h2>
            <p class="section-shell-copy">These match the engine menus — web stays read-only; run on the host with <code>./run.sh</code> or <code>python -m erebus</code>.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Posture</div>
            <div class="mono">erebus pipeline status --json</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">One tick</div>
            <div class="mono">erebus pipeline tick --limit 500</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Queue mining</div>
            <div class="mono">erebus pipeline run --limit 500</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">State batch</div>
            <div class="mono">erebus pipeline state --limit 1000</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">LAMDA burst</div>
            <div class="mono">erebus pipeline lamda --prepare --wait-for-keys --rows 4 --bursts 1</div>
        </div>
    </div>
</section>
