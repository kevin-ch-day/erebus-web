<?php
// app/views/permissions_review.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Permission Review';
$permission = trim((string)($_GET['permission'] ?? ''));
$returnParam = trim((string)($_GET['return'] ?? ''));
$returnUrl = page_url('permissions_triage');
$returnUrl = resolve_internal_return_url($returnParam, $returnUrl);

$reviewUrl = api_url('android_permission_review.php');
$updateUrl = api_url('android_permission_triage_update.php');
$queueUrl = api_url('android_permission_queue_update.php');
$lovUrl = api_url('android_permission_lov.php');
$pageScripts = [
    'assets/js/permission_intel_shared.js',
    'assets/js/pages/permissions_review_page.js',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Permission Decision Surface</div>
        <div class="page-kicker">Evidence-backed single-permission decision</div>
        <h1 class="page-hero-title">Permission Review</h1>
        <p class="page-hero-lede muted">Review one permission with current evidence, ledger context, and backend-owned classification/bucket state. Record the triage decision here; queue only when dictionary maintenance intent should be handed to Python.</p>
        <div class="page-hero-actions">
            <a class="btn" href="<?= h($returnUrl) ?>">Back to workflow</a>
            <?php if (defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY): ?>
                <a class="btn" href="<?= h(page_url('analysis_fusion')) ?>">Analysis Fusion</a>
            <?php endif; ?>
            <a class="btn" href="<?= h(page_url('health')) ?>">VT &amp; Pipeline Health</a>
        </div>
    </div>
</section>
<div class="notice success" id="review-save-message" style="display:none;"></div>

<div id="perm-review-page" style="display:none;"
     data-review-endpoint="<?= h($reviewUrl) ?>"
     data-update-endpoint="<?= h($updateUrl) ?>"
     data-queue-endpoint="<?= h($queueUrl) ?>"
     data-lov-endpoint="<?= h($lovUrl) ?>"
     data-permission="<?= h($permission) ?>"
     data-return-url="<?= h($returnUrl) ?>"></div>

<div class="review-shell" id="review-shell">
    <div class="detail-card review-loading-card" id="review-loading-card">
        <div class="detail-card-title">Loading review context</div>
        <div class="muted" id="review-loading-text">Fetching triage status options and permission context.</div>
    </div>

    <div class="review-shell-content review-shell-content-hidden" id="review-shell-content">
<section class="section-shell">
<div class="review-layout" id="review-layout">
    <div class="review-primary">
        <div class="detail-card">
            <div class="review-summary">
                <div class="review-summary-line">
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Permission</span>
                        <span class="review-summary-value mono" id="review-permission">--</span>
                    </span>
                    <button class="btn btn-small" id="review-copy" type="button">Copy</button>
                </div>
                <div class="review-summary-line">
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Namespace</span>
                        <span class="review-summary-value mono" id="review-namespace">--</span>
                    </span>
                    <span class="review-summary-sep">|</span>
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Risk</span>
                        <span class="review-summary-value" id="review-risk">--</span>
                    </span>
                </div>
                <div class="review-summary-line">
                    <span class="review-summary-pair">
                        <span class="review-summary-label">VT events</span>
                        <span class="review-summary-value" id="review-seen">--</span>
                    </span>
                    <span class="review-summary-sep">|</span>
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Last seen</span>
                        <span class="review-summary-value" id="review-last-seen">--</span>
                    </span>
                </div>
                <div class="review-summary-line">
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Current status</span>
                        <span class="review-summary-value" id="review-status-pill">--</span>
                    </span>
                    <span class="review-summary-sep">|</span>
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Classification</span>
                        <span class="review-summary-value" id="review-classification">--</span>
                    </span>
                    <span class="review-summary-sep">|</span>
                    <span class="review-summary-pair">
                        <span class="review-summary-label">Bucket</span>
                        <span class="review-summary-value" id="review-bucket">--</span>
                    </span>
                </div>
            </div>
            <div class="muted" style="margin-top: 8px;">
                Counts on this page reflect backend evidence context for the selected permission. The UI displays classification and bucket values from the database; it does not infer them client-side.
                <span title="Counts are event-based in the current web contract.">(i)</span>
            </div>
            <div class="notice info review-ledger-note" id="review-ledger-note" style="display:none;"></div>
        </div>

        <div class="detail-card" style="margin-top: 16px;">
            <div class="review-step" id="review-step-decision">
                <div class="review-step-title">Step 1: Triage decision</div>
                <div class="review-decision-grid">
                    <div class="modal-field">
                        <label for="review-status">Triage status</label>
                        <select id="review-status"></select>
                    </div>
                    <div class="review-quick-actions" id="review-quick-actions"></div>
                </div>
                <div class="muted review-impact" id="review-impact"></div>
                <div class="review-decision-summary" id="review-decision-summary"></div>
                <div class="muted" id="review-status-note"></div>
            </div>

            <div class="review-step review-step-hidden" id="review-step-notes">
                <div class="review-step-title">Step 2: Notes (optional)</div>
                <div class="modal-field">
                    <label for="review-notes">Operator notes</label>
                    <textarea id="review-notes" rows="4" placeholder="Why did you choose this status?"></textarea>
                </div>
            </div>

            <details class="review-step review-step-hidden" id="review-step-queue">
                <summary class="review-step-title">Step 3: Queue dictionary update (optional)</summary>
                <div class="muted">Use queue update only when the triage decision should become dictionary maintenance work. The web UI records intent; Python owns apply, audit, and final dictionary mutation.</div>
                <div class="review-queue-fields" id="review-queue-fields">
                    <div class="review-queue-grid">
                        <div class="modal-field">
                            <label for="review-queue-action">Queue action</label>
                            <select id="review-queue-action"></select>
                        </div>
                        <div class="modal-field">
                            <label for="review-queue-bucket">Proposed bucket</label>
                            <select id="review-queue-bucket"></select>
                        </div>
                        <div class="modal-field">
                            <label for="review-queue-classification">Proposed classification</label>
                            <select id="review-queue-classification"></select>
                        </div>
                    </div>
                    <div class="modal-field">
                        <label for="review-queue-notes">Queue notes (optional)</label>
                        <textarea id="review-queue-notes" rows="3" placeholder="Queue context or references."></textarea>
                    </div>
                    <div class="review-queue-actions">
                        <button class="btn" id="review-queue-submit" type="button">Queue update</button>
                        <div class="muted" id="review-queue-note"></div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <aside class="review-side" id="review-side">
        <div class="detail-card" id="review-evidence-card">
            <div class="detail-card-title">Evidence snapshot</div>
            <div class="muted" id="review-evidence-summary">--</div>
            <div class="review-side-actions">
                <a class="btn btn-small" id="review-evidence-link" href="#">Open Evidence</a>
            </div>
        </div>
    </aside>
</div>
</section>

<div class="review-actions review-page-actions">
    <div class="muted review-action-note">Save the evidence-backed triage decision first. Queue update remains optional maintenance handoff.</div>
    <button class="btn btn-primary" id="review-save" type="button">Save decision</button>
</div>
    </div>
</div>

<div class="health-error" id="review-error"></div>
