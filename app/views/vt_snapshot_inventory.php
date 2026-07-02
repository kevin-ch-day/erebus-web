<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Snapshot Inventory';
$inventoryApiUrl = api_url('vt_snapshot_inventory.php');
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">VirusTotal API</div>
        <div class="page-kicker">Snapshot event distribution and surface mix</div>
        <h1 class="page-hero-title">Snapshot Inventory</h1>
        <p class="page-hero-lede muted">
            Read-only summary of VirusTotal snapshot events stored in <code>virustotal_file_report_event</code>.
            Use this page to verify source mix, status mix, and top observed attributes.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
            <a class="btn" href="<?= h(page_url('runs')) ?>">Run Ledger</a>
            <button class="btn btn-muted" id="vt-inv-refresh">Refresh</button>
        </div>
        <div class="muted" style="margin-top:10px;font-size:12px;" id="vt-inv-live-meta">Live refresh pending…</div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Read model</h2>
        <p>This page stays read-only and summarizes snapshot event mix. Use it to understand what the VT pipeline is emitting, not to assess whether the scheduler can move now.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Mode</div>
                <div class="hero-metric-value">Read-only</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Focus</div>
                <div class="hero-metric-value">Snapshots</div>
            </div>
        </div>
    </aside>
</section>

<div id="vt-snapshot-inventory-root"
     style="display:none;"
     data-endpoint="<?= h($inventoryApiUrl) ?>"
     data-refresh-seconds="<?= (int)DASHBOARD_REFRESH_SECONDS ?>"></div>

<div class="filters">
    <div class="filter-field">
        <label for="vt-inv-max-rows">Max rows</label>
        <input id="vt-inv-max-rows" type="number" min="1" max="20000" value="5000" />
    </div>
    <div class="filter-field">
        <label for="vt-inv-recent-hours">Recent window (hours)</label>
        <input id="vt-inv-recent-hours" type="number" min="1" max="720" value="24" />
    </div>
</div>

<div class="detail-grid">
    <div class="detail-card">
        <div class="detail-card-title">Snapshot Summary</div>
        <div class="muted" id="vt-inv-summary">Loading...</div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">HTTP Status Mix</div>
        <ul class="maintenance-list" id="vt-inv-status-mix"></ul>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Source Mix</div>
        <ul class="maintenance-list" id="vt-inv-source-mix"></ul>
    </div>
</div>

<section class="perm-section">
    <div class="perm-section-header">
        <h2>Top Attributes</h2>
        <div class="muted">Top 25 attributes by presence across parsed snapshots.</div>
    </div>
    <div class="table-scroll">
        <table class="table samples-table">
            <thead>
                <tr>
                    <th>Attribute</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody id="vt-inv-attrs-body">
                <tr><td colspan="2" class="muted">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="perm-section">
    <div class="perm-section-header">
        <h2>Recent New Attributes</h2>
        <div class="muted" id="vt-inv-recent-caption">Loading...</div>
    </div>
    <ul class="maintenance-list" id="vt-inv-recent-new"></ul>
</section>

<div class="health-error" id="vt-inv-error"></div>
