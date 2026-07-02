<?php
// app/views/permissions_drift.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Permission Drift';

$queryLimit = max(10, min((int)($_GET['namespace_limit'] ?? 50), 100));
$intelUrl = api_url('android_permission_intelligence.php');
$lovUrl = api_url('android_permission_lov.php');
?>

<!-- Anchors: backend decides truth; drift is diagnostic only. -->
<h1>Permission Drift</h1>
<p class="muted page-helper">
    Diagnostic namespace pressure view. Use it to find OEM/anomalous namespace movement, then confirm real sample behavior in Triage, Evidence, or Analysis Fusion before making a decision.
</p>
<div id="perm-drift-page" style="display:none;"
     data-intel-endpoint="<?= h($intelUrl) ?>"
     data-lov-endpoint="<?= h($lovUrl) ?>"
     data-namespace-limit="<?= (int)$queryLimit ?>"
     data-refresh-seconds="45"></div>
<p class="muted" style="font-size:12px;" id="perm-drift-live-meta">Live refresh pending…</p>

<section class="perm-section">
    <div class="detail-card">
        <div class="detail-card-title">Fast Workflow</div>
        <ul class="maintenance-list">
            <li>Click <strong>Review first</strong> to focus on OEM + Anomalous.</li>
            <li>Use search for vendor/namespace, then open Triage from the row.</li>
            <li>Use "New" badges to prioritize recent namespace drift, not as final classification truth.</li>
            <li>Use Fusion when namespace pressure and VT confidence disagree.</li>
        </ul>
        <div class="landing-actions" style="margin-top: 10px;">
            <a class="btn btn-primary" href="<?= h(page_url('permissions_triage')) ?>">Open Triage</a>
            <?php if (defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY): ?>
                <a class="btn" href="<?= h(page_url('analysis_fusion')) ?>">Analysis Fusion</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="perm-section">
    <h2>Review Workspace</h2>
    <div class="muted" id="perm-drift-note">Loading...</div>
</section>

<div class="drift-quick-filters" id="perm-drift-quick-filters">
    <button type="button" class="btn btn-small drift-quick-btn" data-mode="all">All</button>
    <button type="button" class="btn btn-small drift-quick-btn is-active" data-mode="review">Review first (OEM + Anomalous)</button>
    <button type="button" class="btn btn-small drift-quick-btn" data-mode="anomalous">Anomalous only</button>
    <button type="button" class="btn btn-small drift-quick-btn" data-mode="oem">OEM only</button>
</div>

<div class="filters">
    <div class="filter-field">
        <label for="perm-namespace-limit">Limit</label>
        <select id="perm-namespace-limit">
            <?php foreach ([25, 50, 100, 200] as $size): ?>
                <option value="<?= (int)$size ?>" <?= $queryLimit === $size ? 'selected' : '' ?>><?= (int)$size ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="perm-namespace-search">Search</label>
        <input id="perm-namespace-search" type="search" placeholder="Namespace or vendor" />
    </div>
</div>

<div class="table-scroll">
    <table class="table" id="perm-namespace-table">
        <thead>
            <tr>
                <th>Namespace</th>
                <th>Events</th>
                <th>Unique perms</th>
                <th>Classification</th>
                <th>Last seen</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="perm-namespace-body">
            <tr>
                <td colspan="6" class="muted">Loading namespace drift...</td>
            </tr>
        </tbody>
    </table>
</div>
<div class="muted" style="margin-top: 8px;">
    Counts on this page reflect observed VT event volume by namespace in the current web contract.
    <span title="Occurrences on this page reflect VT event volume.">(i)</span>
</div>

<div class="health-error" id="perm-drift-error"></div>
