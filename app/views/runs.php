<?php
// app/views/runs.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../database/db_func.php';

$title = 'Run Ledger';

$querySearch = trim((string)($_GET['q'] ?? ''));
$queryStoppedReason = trim((string)($_GET['stopped_reason'] ?? ''));
$queryPage = max(1, (int)($_GET['page'] ?? 1));
$queryPageSize = max(1, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
$stoppedReasonOptions = db_run_ledger_stopped_reasons();
$runsApiUrl = api_url('runs_list.php');
$activityApiUrl = api_url('pipeline_activity.php');
$activity = db_pipeline_activity_snapshot(5);
$activityPipeline = is_array($activity['pipeline'] ?? null) ? $activity['pipeline'] : [];
$activityHint = db_pipeline_operator_hint($activityPipeline);
$activityRunSummary = is_array($activity['run_summary'] ?? null) ? $activity['run_summary'] : [];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">VirusTotal API</div>
        <div class="page-kicker">Execution history and operator residue</div>
        <h1 class="page-hero-title">Run Ledger</h1>
        <p class="page-hero-lede muted">
            Execution history for VirusTotal runs stored in <code>virustotal_run_ledger</code>.
            Use this page to inspect recent run batches, visible ledger drift, and execution context without dropping back to the terminal.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('pipeline_ops')) ?>">Pipeline Ops</a>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
            <a class="btn" href="<?= h(page_url('ingest_backlog')) ?>">Ingest Backlog</a>
            <a class="btn" href="<?= h(page_url('vt_snapshot_inventory')) ?>">Snapshot Inventory</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Operator use</h2>
        <p>Read this page as the bounded execution history surface. Health explains whether the pipeline can move now; this page explains what recently happened.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Focus</div>
                <div class="hero-metric-value">Recent runs</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Time basis</div>
                <div class="hero-metric-value"><?= h(tz_current_id()) ?></div>
            </div>
        </div>
    </aside>
</section>

<div id="runs-page-root" style="display:none;"
     data-endpoint="<?= h($runsApiUrl) ?>"
     data-activity-endpoint="<?= h($activityApiUrl) ?>"
     data-pipeline-ops-url="<?= h(page_url('pipeline_ops')) ?>"
     data-refresh-seconds="30"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Pipeline ↔ execution bridge</h2>
            <p class="section-shell-copy">Connects live engine posture with recent <code>virustotal_run_ledger</code> activity. Health says whether work can move; this shows what actually ran.</p>
        </div>
        <div class="flow-inline">
            <span class="muted" id="runs-activity-meta">
                Last run #<?= h((string)($activityRunSummary['latest_run_id'] ?? '--')) ?>
                · 24h: <?= h(number_format((int)($activityRunSummary['runs_24h'] ?? 0))) ?> runs
                · <?= h(number_format((int)($activityRunSummary['processed_24h'] ?? 0))) ?> processed
            </span>
        </div>
    </div>
    <?php if ($activityHint !== ''): ?>
        <div class="notice info" id="runs-activity-summary"><?= h($activityHint) ?></div>
    <?php else: ?>
        <div class="notice info" id="runs-activity-summary">Engine recommendation unavailable.</div>
    <?php endif; ?>
    <div class="table-scroll" style="margin-top: 14px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Run ID</th>
                    <th>Finished</th>
                    <th>Processed</th>
                    <th>OK</th>
                    <th>Stopped reason</th>
                </tr>
            </thead>
            <tbody id="runs-activity-recent">
                <?php foreach (is_array($activity['recent_runs'] ?? null) ? $activity['recent_runs'] : [] as $runRow): ?>
                    <tr>
                        <td><?= h((string)($runRow['run_id'] ?? '')) ?></td>
                        <td><?= h(fmt_utc_display((string)($runRow['finished_at_utc'] ?? ''), 'M d g:i A') ?: '--') ?></td>
                        <td><?= h(number_format((int)($runRow['processed_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($runRow['ok_count'] ?? 0))) ?></td>
                        <td><?= h((string)($runRow['stopped_reason'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="muted" style="margin: 10px 0;">
    Display TZ: <strong><?= htmlspecialchars(tz_current_id()) ?></strong>
    | <a href="<?= htmlspecialchars(page_url('time_reference')) ?>">Change clocks</a>
</div>

<div class="detail-grid" style="margin-bottom: 14px;">
    <div class="detail-card">
        <div class="detail-card-title">Platform context</div>
        <div class="detail-row">
            <div class="detail-label">Primary catalog</div>
            <div class="detail-value" id="runs-platform-primary">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">PI catalog</div>
            <div class="detail-value" id="runs-platform-pi">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Schema heads</div>
            <div class="detail-value" id="runs-platform-heads">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Latest perm taxonomy</div>
            <div class="detail-value" id="runs-platform-taxonomy">--</div>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Visible ledger drift</div>
        <div class="muted" id="runs-platform-summary">Checking run ledger context...</div>
        <ul class="maintenance-list" id="runs-platform-list"></ul>
    </div>
</div>

<div class="filters">
    <div class="filter-field">
        <label for="runs-search">Search</label>
        <input id="runs-search" type="search" placeholder="Run ID, DB name, or key" value="<?= htmlspecialchars($querySearch) ?>" />
    </div>
    <div class="filter-field">
        <label for="runs-stopped-reason">Stopped reason</label>
        <select id="runs-stopped-reason">
            <option value="">All reasons</option>
            <?php foreach ($stoppedReasonOptions as $reason): ?>
                <option value="<?= h($reason) ?>" <?= $queryStoppedReason === $reason ? 'selected' : '' ?>><?= h($reason) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="runs-page-size">Page size</label>
        <select id="runs-page-size">
            <?php foreach ([25, 50, 100, 200] as $size): ?>
                <option value="<?= (int)$size ?>" <?= $queryPageSize === $size ? 'selected' : '' ?>><?= (int)$size ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="table-scroll">
    <table class="table samples-table">
        <thead>
            <tr>
                <th>Run ID</th>
                <th>Started</th>
                <th>Finished</th>
                <th>DB / Key</th>
                <th>Processed</th>
                <th>OK</th>
                <th>No data</th>
                <th>Retry wait</th>
                <th>Error</th>
                <th>Perm taxonomy</th>
                <th>Stopped reason</th>
            </tr>
        </thead>
        <tbody id="runs-body">
            <tr>
                <td colspan="11" class="muted">Loading runs...</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="table-footer">
    <div class="table-meta muted" id="runs-meta">--</div>
    <div class="table-controls">
        <button class="btn" id="runs-prev">Prev</button>
        <div class="table-page">
            Page <input id="runs-page" type="number" min="1" value="<?= (int)$queryPage ?>" /> / <span id="runs-pages">--</span>
        </div>
        <button class="btn" id="runs-next">Next</button>
    </div>
</div>

<div class="health-error" id="runs-error"></div>
