<?php
// app/views/permissions_triage.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/permissions.php';

$title = 'Permission Triage';

$queryLimit = max(10, min((int)($_GET['limit'] ?? 50), 100));
$intelUrl = api_url('android_permission_intelligence.php');
$triageStatuses = perm_operator_triage_statuses_with_metadata();
$actionableStatuses = perm_actionable_triage_status_keys();
$resolvedStatuses = perm_resolved_triage_status_keys();
$pageScripts = [
    'assets/js/permission_intel_shared.js',
    'assets/js/modules/perm_triage/permission_triage_table_renderer.js',
    'assets/js/modules/perm_triage/permission_triage_session.js',
    'assets/js/pages/permissions_triage_page.js',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Permission Intel</div>
        <div class="page-kicker">Backlog review and evidence-first decisions</div>
        <h1 class="page-hero-title">Permission Triage</h1>
        <p class="page-hero-lede muted">Review current evidence-backed UNKNOWNs by default, then switch to governed rows or ledger diagnostics when needed. Queue state is diagnostic context, not the default triage endpoint.</p>
        <div class="page-hero-actions">
            <a class="btn" href="<?= h(page_url('permissions_overview')) ?>">Back to Overview</a>
            <?php if (defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY): ?>
                <a class="btn" href="<?= h(page_url('analysis_fusion')) ?>">Analysis Fusion</a>
            <?php endif; ?>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
        </div>
    </div>
</section>
<div class="notice success" id="perm-triage-message" style="display:none;"></div>
<div id="perm-triage-page" style="display:none;"
     data-intel-endpoint="<?= h($intelUrl) ?>"
     data-triage-statuses="<?= h(json_encode($triageStatuses, JSON_UNESCAPED_SLASHES)) ?>"
     data-actionable-statuses="<?= h(json_encode($actionableStatuses, JSON_UNESCAPED_SLASHES)) ?>"
     data-resolved-statuses="<?= h(json_encode($resolvedStatuses, JSON_UNESCAPED_SLASHES)) ?>"
     data-limit="<?= (int)$queryLimit ?>"></div>

<div class="pi-page-shell" id="perm-triage-shell">
    <div class="detail-card pi-page-loading-card" id="perm-triage-loading-card">
        <div class="detail-card-title">Loading triage workspace</div>
        <div class="muted" id="perm-triage-loading-text">Fetching backlog rows, queue signals, and filter options.</div>
    </div>

    <div class="pi-shell-content-hidden" id="perm-triage-shell-content">
<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Triage workspace</h2>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Backlog snapshot</div>
            <div class="detail-row">
                <div class="detail-label">Evidence high-risk</div>
                <div class="detail-value" id="perm-session-high">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Evidence medium-risk</div>
                <div class="detail-value" id="perm-session-medium">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Evidence low-risk</div>
                <div class="detail-value" id="perm-session-low">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Current evidence review</div>
                <div class="detail-value" id="perm-session-total">--</div>
            </div>
            <div class="muted" id="perm-session-note" style="margin-top: 8px;">--</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Decision queue status</div>
            <div class="detail-row">
                <div class="detail-label">Queued current work</div>
                <div class="detail-value" id="perm-queue-total">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last current queued</div>
                <div class="detail-value" id="perm-queue-last">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Applied total</div>
                <div class="detail-value" id="perm-queue-applied-count">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last applied</div>
                <div class="detail-value" id="perm-queue-applied">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Apply errors</div>
                <div class="detail-value" id="perm-queue-error-count">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last error</div>
                <div class="detail-value" id="perm-queue-error">--</div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Session controls</div>
            <div class="detail-row">
                <div class="detail-label">Last OK run</div>
                <div class="detail-value" id="perm-session-last-ok">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Taxonomy version</div>
                <div class="detail-value" id="perm-session-taxonomy">--</div>
            </div>
            <div class="landing-actions" style="margin-top: 12px;">
                <button class="btn btn-primary" type="button" id="perm-session-start-high">Start with High-risk current review</button>
                <button class="btn" type="button" id="perm-session-review-next">Review next</button>
                <button class="btn btn-muted" type="button" id="perm-session-resume">Resume last reviewed</button>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Review controls</h2>
        </div>
    </div>
    <div class="detail-card">
        <div class="filters">
            <div class="filter-field">
                <label for="perm-review-lane">Review lane</label>
                <select id="perm-review-lane">
                    <option value="active">Active review</option>
                    <option value="governed">Governed current UNKNOWNs</option>
                    <option value="ledger">Ledger diagnostics</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="perm-unknown-limit">Limit</label>
                <select id="perm-unknown-limit">
                    <?php foreach ([25, 50, 100, 200] as $size): ?>
                        <option value="<?= (int)$size ?>" <?= $queryLimit === $size ? 'selected' : '' ?>><?= (int)$size ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="perm-unknown-search">Search</label>
                <input id="perm-unknown-search" type="search" placeholder="Permission or namespace" />
            </div>
            <div class="filter-field">
                <label for="perm-unknown-namespace">Namespace</label>
                <select id="perm-unknown-namespace">
                    <option value="">All namespaces</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="perm-unknown-risk">Risk hint</label>
                <select id="perm-unknown-risk">
                    <option value="">All risk levels</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="perm-unknown-status">Status</label>
                <select id="perm-unknown-status">
                    <option value="">All statuses</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="perm-unknown-queued">Queue status</label>
                <select id="perm-unknown-queued">
                    <option value="">All queue states</option>
                    <option value="queued">Queued</option>
                    <option value="claimed">Claimed</option>
                    <option value="applied">Applied</option>
                    <option value="error">Error</option>
                    <option value="rejected">Rejected</option>
                    <option value="skipped">Skipped</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="perm-unknown-sort">Sort</label>
                <select id="perm-unknown-sort">
                    <option value="seen_desc">Seen (high -> low)</option>
                    <option value="seen_asc">Seen (low -> high)</option>
                    <option value="last_seen_desc">Last seen (newest)</option>
                    <option value="last_seen_asc">Last seen (oldest)</option>
                    <option value="risk_desc">Risk (high -> low)</option>
                    <option value="risk_asc">Risk (low -> high)</option>
                    <option value="permission_asc">Permission (A -> Z)</option>
                    <option value="permission_desc">Permission (Z -> A)</option>
                    <option value="namespace_asc">Namespace (A -> Z)</option>
                    <option value="namespace_desc">Namespace (Z -> A)</option>
                </select>
            </div>
            <div class="filter-field" style="align-self: end;">
                <label>
                    <input id="perm-unknown-show-resolved" type="checkbox" />
                    Show app-defined + resolved
                </label>
            </div>
        </div>

        <div class="triage-action-strip">
            <div class="triage-action-strip-title">Quick focus</div>
            <div class="triage-action-strip-buttons">
                <button class="btn btn-small" type="button" id="perm-quick-high-new">High-risk NEW</button>
                <button class="btn btn-small" type="button" id="perm-quick-oem">OEM Candidate</button>
                <button class="btn btn-small" type="button" id="perm-quick-queued">Current queued</button>
                <button class="btn btn-small btn-muted" type="button" id="perm-quick-reset">Reset</button>
            </div>
        </div>
        <div class="triage-filter-summary" id="perm-filter-summary"></div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Review lane</h2>
        </div>
    </div>
    <div class="muted" style="margin-bottom: 10px;" id="perm-review-lane-note">
        Active review is evidence-backed by default. Switch lanes to inspect governed rows or ledger diagnostics.
    </div>
    <div class="table-scroll">
        <table class="table" id="perm-unknown-table">
            <thead>
                <tr>
                    <th>Permission name</th>
                    <th>Lane</th>
                    <th>Current UNKNOWN samples</th>
                    <th>Current UNKNOWN obs</th>
                    <th>Risk hint</th>
                    <th>Last observed</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="perm-unknown-body">
                <tr>
                    <td colspan="7" class="muted">Loading review lane...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="landing-actions" style="margin-top: 8px; align-items: center;">
        <button class="btn btn-small btn-muted" type="button" id="perm-page-prev">Previous</button>
        <span class="muted" id="perm-page-info">Page --</span>
        <button class="btn btn-small btn-muted" type="button" id="perm-page-next">Next</button>
    </div>
    <div class="muted" style="margin-top: 8px;">
        Active and governed lanes are current-evidence views; ledger diagnostics are historical workflow context. Use Evidence or Fusion when you need sample-level proof.
    </div>
</section>

<div class="health-error" id="perm-triage-error"></div>
    </div>
</div>
