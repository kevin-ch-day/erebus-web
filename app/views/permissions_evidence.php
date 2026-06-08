<?php
// app/views/permissions_evidence.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';

$title = 'Permission Evidence';

$permission = trim((string)($_GET['permission'] ?? ''));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 25)));
$returnParam = trim((string)($_GET['return'] ?? ''));
$defaultReturn = page_url('permissions_triage');
$returnUrl = resolve_internal_return_url($returnParam, $defaultReturn);
$reviewUrl = $permission !== '' ? page_url('permissions_review', ['permission' => $permission, 'return' => $returnUrl]) : '';
$evidenceUrl = api_url('android_permission_evidence.php');
$pageScripts = [
    'assets/js/permission_intel_shared.js',
    'assets/js/pages/permissions_evidence_page.js',
];
?>

<!-- Anchors: backend decides truth; evidence is read-only. -->
<h1>Permission Evidence</h1>
<p class="muted page-helper">
    Read-only evidence rows for one permission string across enrichment events. Use this page to inspect supporting context, then return to Triage or Review to record the decision.
</p>
<div style="margin: 8px 0;">
    <a class="btn btn-small" href="<?= h($returnUrl) ?>">Back to workflow</a>
    <a class="btn btn-small btn-primary<?= $reviewUrl === '' ? ' btn-disabled' : '' ?>"
       id="perm-evidence-review-link"
       href="<?= h($reviewUrl !== '' ? $reviewUrl : '#') ?>"
       <?= $reviewUrl === '' ? 'aria-disabled="true"' : '' ?>>Open Review</a>
</div>
<div class="muted" style="margin: 10px 0;">
    Display TZ: <strong><?= htmlspecialchars(tz_current_id()) ?></strong>
    | <a href="<?= htmlspecialchars(page_url('settings')) ?>">Change</a>
</div>
<div id="perm-evidence-page" style="display:none;"
     data-evidence-endpoint="<?= h($evidenceUrl) ?>"
     data-permission="<?= h($permission) ?>"
     data-return-url="<?= h($returnUrl) ?>"
     data-limit="<?= (int)$limit ?>"></div>

<div class="filters">
    <div class="filter-field">
        <label for="perm-evidence-input">Permission</label>
        <input id="perm-evidence-input" type="search" placeholder="android.permission.READ_SMS"
               value="<?= h($permission) ?>" />
    </div>
    <div class="filter-field" style="min-width: 160px;">
        <label for="perm-evidence-limit">Rows</label>
        <select id="perm-evidence-limit">
            <?php foreach ([25, 50, 100, 200] as $opt): ?>
                <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label>&nbsp;</label>
        <button class="btn" id="perm-evidence-search" type="button">Load evidence</button>
    </div>
</div>

<div class="detail-card">
    <div class="detail-card-title" id="perm-evidence-title">Select a permission to view evidence</div>
    <div class="muted" id="perm-evidence-meta">--</div>
    <div class="table-scroll">
        <table class="table" id="perm-evidence-table">
            <thead>
                <tr>
                    <th>Sample</th>
                    <th>Package</th>
                    <th>Run ID</th>
                    <th>Run status</th>
                    <th>Taxonomy</th>
                    <th>VT status</th>
                    <th>Observed at</th>
                </tr>
            </thead>
            <tbody id="perm-evidence-body">
                <tr>
                    <td colspan="7" class="muted">No evidence loaded.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="health-error" id="perm-evidence-error"></div>
