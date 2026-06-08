<?php
// app/views/vt_key_controls.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'VT Key Drilldown';
$vtStatusUrl = api_url('fallback_vt_status.php');
$vtHealthUrl = api_url('fallback_vt_health.php');
$fallbackStatusUrl = $vtStatusUrl;
$fallbackHealthUrl = $vtHealthUrl;
$pageScripts = ['assets/js/readonly_table.js', 'assets/js/pages/vt_key_controls_page.js'];
?>

<h1>VT Key Drilldown</h1>
<p class="muted">
    Detailed per-key drilldown for quota, cooldown, lease, and recent 429 state.
    Use VT &amp; Pipeline Health for the primary VT stoplight and use this page only when you need row-level key timing detail.
</p>
<div class="notice info">All timestamps are displayed in UTC.</div>
<div class="notice" id="vt-ops-api-banner">API server: --</div>

<div id="vt-key-ops" style="display:none;"
     data-status-endpoint="<?= h($vtStatusUrl) ?>"
     data-health-endpoint="<?= h($vtHealthUrl) ?>"
     data-fallback-status-endpoint="<?= h($fallbackStatusUrl) ?>"
     data-fallback-health-endpoint="<?= h($fallbackHealthUrl) ?>"></div>

<div class="detail-grid" style="margin-bottom: 16px;">
    <div class="detail-card">
        <div class="detail-card-title">Key pool summary</div>
        <div class="detail-row">
            <div class="detail-label">Eligible keys</div>
            <div class="detail-value" id="vt-ops-eligible">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Cooling keys</div>
            <div class="detail-value" id="vt-ops-cooling">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Leased keys</div>
            <div class="detail-value" id="vt-ops-leased">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Supports leases</div>
            <div class="detail-value" id="vt-ops-supports-leases">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Last refresh</div>
            <div class="detail-value" id="vt-ops-last-refresh">--</div>
        </div>
        <div style="margin-top: 10px;">
            <button class="btn btn-small" type="button" id="vt-ops-refresh">Refresh</button>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Page ownership</div>
        <div class="muted">
            Health owns hold state, scheduler pressure, and the primary key posture summary.
            VT Ops Dashboard owns vendor, drift, signal, and confidence-surface readiness.
            This page owns the per-key drilldown only.
        </div>
        <div style="margin-top: 10px;">
            <a class="btn btn-small" href="<?= h(page_url('health')) ?>">Open VT &amp; Pipeline Health</a>
            <a class="btn btn-small" href="<?= h(page_url('vt_ops_dashboard')) ?>">Open VT Ops Dashboard</a>
        </div>
    </div>
</div>

<div class="notice warn" id="vt-ops-unavailable" style="display:none;"></div>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Key</th>
                <th>Status</th>
                <th>Enabled</th>
                <th>Visible</th>
                <th>Requests left</th>
                <th>Quota used/limit</th>
                <th>Reset time (UTC)</th>
                <th>Time to reset</th>
                <th>Cooldown until (UTC)</th>
                <th>Lease until (UTC)</th>
                <th>Lease owner</th>
                <th>Last 429 at (UTC)</th>
                <th>Retry-after (sec)</th>
                <th>429 count</th>
            </tr>
        </thead>
        <tbody id="vt-ops-body">
            <tr>
                <td colspan="14" class="muted">Loading key ops...</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="muted" id="vt-ops-meta" style="margin-top: 10px;">--</div>
<div class="health-error" id="vt-ops-error"></div>
