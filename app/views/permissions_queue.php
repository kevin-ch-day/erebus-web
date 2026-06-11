<?php
// app/views/permissions_queue.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';

$title = 'Permission Queue';
$queueUrl = api_url('fallback_permission_queue.php');
$pageScripts = ['assets/js/readonly_table.js', 'assets/js/pages/permissions_queue_page.js'];

$querySearch = trim((string)($_GET['search'] ?? ''));
$queryStatus = trim((string)($_GET['status'] ?? ''));
$queryAction = trim((string)($_GET['queue_action'] ?? ''));
$queryLimit = max(1, (int)($_GET['limit'] ?? 25));
?>

<div id="permission-queue-root">
    <section class="page-hero">
        <div class="page-hero-body">
            <div class="eyebrow">Permission Intel</div>
            <div class="page-kicker">Dictionary maintenance handoff diagnostics</div>
            <h1 class="page-hero-title">Permission Queue</h1>
            <p class="page-hero-lede muted">Read-only maintenance ledger for queued dictionary proposals, apply outcomes, and residue diagnostics. This is not the primary triage surface; use it to inspect handoff state after Review records intent.</p>
        <div class="page-hero-actions">
            <a class="btn" href="<?= h(page_url('permissions_overview')) ?>">Back to Overview</a>
            <a class="btn" href="<?= h(page_url('permissions_triage')) ?>">Open Triage</a>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
        </div>
    </div>
</section>
    <div class="notice" id="permission-queue-api-banner">API server: --</div>
    <div class="notice info">
        Queue rows are current maintenance intent, not evidence truth. Evidence lives in observation/VT event tables; Python owns queue apply and dictionary mutation.
    </div>
    <div id="permission-queue-page" style="display:none;"
         data-endpoint="<?= h($queueUrl) ?>"
         data-default-limit="<?= (int)$queryLimit ?>"></div>

<div class="filters">
    <div class="filter-field">
        <label for="permission-queue-search">Search</label>
        <input id="permission-queue-search" type="search" placeholder="Permission string"
               value="<?= h($querySearch) ?>" />
    </div>
    <div class="filter-field">
        <label for="permission-queue-status">Status</label>
        <select id="permission-queue-status">
            <option value="">All statuses</option>
            <option value="queued" <?= $queryStatus === 'queued' ? 'selected' : '' ?>>Queued</option>
            <option value="claimed" <?= $queryStatus === 'claimed' ? 'selected' : '' ?>>Claimed</option>
            <option value="applied" <?= $queryStatus === 'applied' ? 'selected' : '' ?>>Applied</option>
            <option value="error" <?= $queryStatus === 'error' ? 'selected' : '' ?>>Error</option>
            <option value="rejected" <?= $queryStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="skipped" <?= $queryStatus === 'skipped' ? 'selected' : '' ?>>Skipped</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="permission-queue-action">Queue action</label>
        <select id="permission-queue-action">
            <option value="">All actions</option>
            <option value="aosp" <?= $queryAction === 'aosp' ? 'selected' : '' ?>>AOSP</option>
            <option value="oem" <?= $queryAction === 'oem' ? 'selected' : '' ?>>OEM</option>
            <option value="google" <?= $queryAction === 'google' ? 'selected' : '' ?>>Google</option>
            <option value="defer" <?= $queryAction === 'defer' ? 'selected' : '' ?>>Defer</option>
            <option value="app_defined" <?= $queryAction === 'app_defined' ? 'selected' : '' ?>>App Defined</option>
            <option value="reject" <?= $queryAction === 'reject' ? 'selected' : '' ?>>Reject</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="permission-queue-limit">Page size</label>
        <select id="permission-queue-limit">
            <?php foreach ([25, 50, 100, 200] as $size): ?>
                <option value="<?= (int)$size ?>" <?= $queryLimit === $size ? 'selected' : '' ?>><?= (int)$size ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field filter-toggle">
        <label>&nbsp;</label>
        <button class="btn btn-small" type="button" id="permission-queue-refresh">Refresh</button>
    </div>
    <div class="filter-field filter-toggle">
        <label>&nbsp;</label>
        <button class="btn btn-small" type="button" id="permission-queue-compact-toggle">Compact view</button>
    </div>
    <div class="filter-field">
        <label>Page</label>
        <div class="muted" id="permission-queue-page-index">1</div>
    </div>
</div>

<div class="detail-card" style="margin-bottom: 16px;">
    <div class="detail-card-title">Decision queue summary</div>
    <div class="detail-grid" id="permission-queue-summary"></div>
    <div class="detail-card-title" style="margin-top: 16px;">Queue populations</div>
    <div class="detail-grid" id="permission-queue-population-summary"></div>
    <div class="muted" id="permission-queue-summary-note">Counts unavailable (backend not providing summary).</div>
</div>

<div class="notice warn" id="permission-queue-unavailable" style="display:none;"></div>

<div class="table-scroll">
    <table class="table">
        <thead>
            <tr>
                <th>Permission</th>
                <th>Population</th>
                <th>Signals</th>
                <th>Action</th>
                <th>Status</th>
                <th>Triage</th>
                <th>Proposed classification</th>
                <th>Proposed bucket</th>
                <th>Timeline</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody id="permission-queue-body">
            <tr>
                <td colspan="10" class="muted">Loading queue...</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="table-footer">
    <div class="table-meta muted" id="permission-queue-meta">--</div>
    <div class="table-controls">
        <button class="btn" type="button" id="permission-queue-prev">Prev</button>
        <button class="btn" type="button" id="permission-queue-next">Next</button>
    </div>
</div>

<div class="health-error" id="permission-queue-error"></div>
</div>
