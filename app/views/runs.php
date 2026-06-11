<?php
// app/views/runs.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';

$title = 'Run Ledger';

$querySearch = trim((string)($_GET['q'] ?? ''));
$queryPage = max(1, (int)($_GET['page'] ?? 1));
$queryPageSize = max(1, (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE));
$runsApiUrl = api_url('runs_list.php');
$pageScripts = ['assets/js/pages/runs_page.js'];
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
            <a class="btn btn-primary" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
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

<div id="runs-page-root" style="display:none;" data-endpoint="<?= h($runsApiUrl) ?>"></div>

<div class="muted" style="margin: 10px 0;">
    Display TZ: <strong><?= htmlspecialchars(tz_current_id()) ?></strong>
    | <a href="<?= htmlspecialchars(page_url('settings')) ?>">Change</a>
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
