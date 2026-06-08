<?php
// app/views/admin_diagnostics.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Admin Diagnostics';
$vtHealthUrl = api_url('fallback_vt_health.php');
$vtStatusUrl = api_url('fallback_vt_status.php');
$vtOpsSummaryUrl = api_url('vt_ops_summary.php');
$queueUrl = api_url('fallback_permission_queue.php');
$healthUrl = api_url('health.php');
$apiBase = app_url('api.php');
$pageScripts = ['assets/js/readonly_table.js', 'assets/js/diagnostics_panel.js', 'assets/js/pages/admin_diagnostics_page.js'];
?>

<h1>Admin Diagnostics</h1>
<p class="muted">
    This page answers: "Is the system alive and aligned?" It is read-only and
    intended for platform diagnostics and report sharing. It validates the VT fallback contracts and current
    DB-backed VT surfaces, but it is not a primary VT operator page.
</p>
<div class="notice warn phase-banner">
    Read-only diagnostics only. No mutations are performed on this page.
</div>

<div id="admin-smoke-page" style="display:none;"
     data-vt-health="<?= h($vtHealthUrl) ?>"
     data-vt-status="<?= h($vtStatusUrl) ?>"
     data-vt-ops-summary="<?= h($vtOpsSummaryUrl) ?>"
     data-permission-queue="<?= h($queueUrl) ?>"
     data-pipeline-health="<?= h($healthUrl) ?>"
     data-api-base="<?= h($apiBase) ?>"
     data-app-version="<?= h(APP_VERSION) ?>"
     data-app-sha="<?= h(APP_GIT_SHA) ?>"
     data-flag-phase2b="<?= FEATURE_PHASE2B_READONLY ? '1' : '0' ?>"
     data-flag-phase3="<?= FEATURE_PHASE3_OPS ? '1' : '0' ?>"></div>

<div class="detail-grid" style="margin-bottom: 16px;">
    <div class="detail-card">
        <div class="detail-card-title">Run controls</div>
        <div class="detail-row">
            <div class="detail-label">Last run (UTC)</div>
            <div class="detail-value" id="admin-smoke-last-run">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Last known good (UTC)</div>
            <div class="detail-value" id="admin-smoke-last-good">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Totals</div>
            <div class="detail-value" id="admin-smoke-totals">--</div>
        </div>
        <div style="margin-top: 10px;">
            <button class="btn btn-small" type="button" id="admin-smoke-run">Run checks</button>
            <button class="btn btn-small" type="button" id="admin-smoke-copy">Copy report</button>
            <button class="btn btn-small" type="button" id="admin-smoke-copy-good">Copy last good</button>
            <button class="btn btn-small" type="button" id="admin-smoke-save">Save report</button>
        </div>
        <div class="muted" style="margin-top: 8px;">
            Report includes environment, API base URL, PASS/WARN/FAIL results, and VT evidence-surface alignment.
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Connectivity / API diagnostics</div>
        <div class="detail-row">
            <div class="detail-label">API base URL</div>
            <div class="detail-value mono" id="admin-smoke-api-base">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Last fetch (UTC)</div>
            <div class="detail-value" id="admin-smoke-api-last-fetch">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Last HTTP status</div>
            <div class="detail-value" id="admin-smoke-api-last-status">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Latency</div>
            <div class="detail-value" id="admin-smoke-api-latency">--</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Capabilities</div>
            <div class="detail-value" id="admin-smoke-api-capabilities">--</div>
        </div>
    </div>
</div>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Check</th>
                <th>Status</th>
                <th>Detail</th>
                <th>Latency</th>
            </tr>
        </thead>
        <tbody id="admin-smoke-body">
            <tr>
                <td colspan="4" class="muted">Run checks to populate results.</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="health-error" id="admin-smoke-error"></div>
