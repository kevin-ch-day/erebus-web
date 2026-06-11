<?php
// app/views/vt_confidence.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'VT Confidence';
$endpoint = api_url('vt_confidence.php');
$classificationGapsEndpoint = api_url('android_permission_classification_gaps.php');
$queryLimit = max(1, min((int)($_GET['limit'] ?? 25), 250));
$pageScripts = [
    'assets/js/pages/vt_confidence_page.js',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">VirusTotal</div>
        <div class="page-kicker">Evidence confidence and false-positive review</div>
        <h1 class="page-hero-title">VT Confidence</h1>
        <p class="page-hero-lede muted">
            Database-backed confidence buckets from the current VT scoring layer. Use this page to separate strong VT evidence from low-consensus review candidates.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('vt_confidence')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('permissions_overview')) ?>">Permission Overview</a>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Source of truth</h2>
        <p>Reads `v_vt_evidence_confidence_summary` and prefers `v_vt_false_positive_review_candidates_effective` when available, falling back to `v_vt_false_positive_review_candidates`.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Mode</div>
                <div class="hero-metric-value">Read-only</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Limit</div>
                <div class="hero-metric-value"><?= h((string)$queryLimit) ?></div>
            </div>
        </div>
    </aside>
</section>

<div id="vt-confidence-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-classification-gaps-endpoint="<?= h($classificationGapsEndpoint) ?>"
     data-limit="<?= (int)$queryLimit ?>"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Confidence buckets</h2>
            <p class="muted">Current scoring distribution by bucket and recommended action.</p>
        </div>
        <div class="muted" id="vt-confidence-meta">Loading...</div>
    </div>
    <div class="detail-grid" id="vt-confidence-buckets">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Fetching confidence buckets...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Surface context</h2>
            <p class="muted">Why this confidence layer should be trusted right now: canonical vendor rows, drift activity, and signal coverage behind the buckets.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Vendor model</div>
            <div class="detail-row">
                <div class="detail-label">Canonical rows</div>
                <div class="detail-value" id="vt-confidence-vendor-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Projection rows</div>
                <div class="detail-value" id="vt-confidence-projection-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reliability rows</div>
                <div class="detail-value" id="vt-confidence-reliability-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">30d changed engines</div>
                <div class="detail-value" id="vt-confidence-drift-rows">--</div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Signal coverage</div>
            <div class="detail-row">
                <div class="detail-label">Signal current rows</div>
                <div class="detail-value" id="vt-confidence-signal-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Confidence rows</div>
                <div class="detail-value" id="vt-confidence-confidence-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Top parse version</div>
                <div class="detail-value" id="vt-confidence-parse-version">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Avg FP tendency</div>
                <div class="detail-value" id="vt-confidence-fp-tendency">--</div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Evidence completion priorities</h2>
            <p class="muted">Strong Android behavior with missing or conflicting VT evidence. Use this queue to prioritize enrichment and contradiction review.</p>
        </div>
    </div>
    <div class="detail-grid" id="vt-evidence-gap-cards">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Fetching evidence-completion priorities...</div>
        </div>
    </div>
    <div class="detail-card" style="margin-top: 12px;">
        <div class="detail-card-title">Priority evidence rows</div>
        <div class="muted" id="vt-evidence-gap-summary">Loading evidence-completion queue...</div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sample</th>
                        <th>Workflow</th>
                        <th>ATT&amp;CK behavior</th>
                        <th>VT state</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="vt-evidence-gap-body">
                    <tr>
                        <td colspan="5" class="muted">Loading evidence completion rows...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">False-positive review buckets</h2>
            <p class="muted">Reasons why low-confidence rows require analyst review before promotion.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Review reason summary</div>
        <ul class="maintenance-list" id="vt-fp-summary-list">
            <li class="muted">Loading false-positive review summary...</li>
        </ul>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Review candidates</h2>
            <p class="muted">Lowest confidence rows first. These should not be treated as strong malware labels without corroborating evidence.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Candidate rows</div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sample</th>
                        <th>Package</th>
                        <th>VT counts</th>
                        <th>Confidence</th>
                        <th>Review reason</th>
                    </tr>
                </thead>
                <tbody id="vt-confidence-candidates-body">
                    <tr>
                        <td colspan="5" class="muted">Loading candidates...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="health-error" id="vt-confidence-error"></div>
