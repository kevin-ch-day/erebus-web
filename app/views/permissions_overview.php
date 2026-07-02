<?php
// app/views/permissions_overview.php

declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Permission Overview';

$queryLimit = max(10, min((int)($_GET['limit'] ?? 25), 100));
$intelUrl = api_url('android_permission_intelligence.php');
$classificationGapsUrl = api_url('android_permission_classification_gaps.php');
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Permission Intel</div>
        <div class="page-kicker">Workflow posture and next move</div>
        <h1 class="page-hero-title">Permission Overview</h1>
        <p class="page-hero-lede muted">Single-screen snapshot of current evidence, governed residue, ledger diagnostics, and cross-signal analysis posture.</p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('permissions_triage')) ?>">Open Triage</a>
            <?php if (defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY): ?>
                <a class="btn" href="<?= h(page_url('analysis_fusion')) ?>">Analysis Fusion</a>
            <?php endif; ?>
            <a class="btn" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Operator path</h2>
        <p>Read this page as a posture board, not an execution surface. It should tell you whether to triage current evidence, inspect ledger residue, or escalate to cross-database fusion.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Best next page</div>
                <div class="hero-metric-value">Triage</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If blocked</div>
                <div class="hero-metric-value">Health</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If signals disagree</div>
                <div class="hero-metric-value">Fusion</div>
            </div>
        </div>
    </aside>
</section>
<div class="notice info" id="perm-next-action">Next action: --</div>
<div id="perm-overview-page" style="display:none;"
     data-intel-endpoint="<?= h($intelUrl) ?>"
     data-classification-gaps-endpoint="<?= h($classificationGapsUrl) ?>"
     data-limit="<?= (int)$queryLimit ?>"
     data-flag-phase2b="<?= FEATURE_PHASE2B_READONLY ? '1' : '0' ?>"
     data-refresh-seconds="60"></div>

<div class="pi-page-shell" id="perm-overview-shell">
    <div class="detail-card pi-page-loading-card" id="perm-overview-loading-card">
        <div class="detail-card-title">Loading overview</div>
        <div class="muted" id="perm-overview-loading-text">Fetching workflow posture, backlog, and maintenance signals.</div>
    </div>

    <div class="pi-shell-content-hidden" id="perm-overview-shell-content">
<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Workflow posture</h2>
        </div>
        <div class="muted" id="perm-health-updated">Updated: --</div>
        <div class="muted" style="font-size:12px;" id="perm-overview-live-meta">Live refresh pending…</div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Managed vs Workflow Backlog</div>
            <div class="perm-health-status" id="perm-health-status">--</div>
            <div class="perm-health-bar">
                <div class="perm-health-bar-known" id="perm-health-known"></div>
                <div class="perm-health-bar-unknown" id="perm-health-unknown"></div>
            </div>
            <div class="perm-health-metrics">
                <div><strong id="perm-known-pct">--</strong> known</div>
                <div><strong id="perm-unknown-pct">--</strong> unknown</div>
            </div>
            <div class="perm-health-counts muted" id="perm-health-counts">--</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Workflow Signals</div>
            <div class="detail-row">
                <div class="detail-label">Taxonomy version</div>
                <div class="detail-value" id="perm-taxonomy-version">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last taxonomy refresh</div>
                <div class="detail-value" id="perm-last-taxonomy-refresh">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Unknown trend (7 days)</div>
                <div class="detail-value" id="perm-unknown-trend">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">New unknowns (24h)</div>
                <div class="detail-value" id="perm-backlog-new">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Current evidence review</div>
                <div class="detail-value" id="perm-backlog-effective-unknown">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Governed current residue</div>
                <div class="detail-value" id="perm-backlog-governed">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Ledger diagnostics</div>
                <div class="detail-value" id="perm-backlog-ledger-diagnostics">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">AOSP gap</div>
                <div class="detail-value" id="perm-backlog-aosp">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">OEM candidate</div>
                <div class="detail-value" id="perm-backlog-oem">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">OEM resolved</div>
                <div class="detail-value" id="perm-backlog-resolved-oem">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Queued current work</div>
                <div class="detail-value" id="perm-backlog-queued">--</div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Classification gap review</h2>
            <p class="muted">Cross-check Permission Intel ATT&amp;CK behavior against VT confidence so strong behavior signals do not stay hidden behind weak or missing VT verdicts.</p>
        </div>
        <div class="muted" id="perm-classification-gaps-meta">Loading...</div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">High ROI review candidates</div>
        <div class="muted" id="perm-classification-gaps-summary">Looking for cross-signal disagreements.</div>
        <div class="triage-action-strip" style="margin-top: 10px;">
            <div class="triage-action-strip-title">Workflow filter</div>
            <div class="triage-action-strip-buttons" id="perm-classification-gaps-filters">
                <button class="btn btn-small btn-primary" type="button" data-gap-filter="all">All</button>
                <button class="btn btn-small" type="button" data-gap-filter="behavior_strong_vt_missing">Strong behavior, VT missing</button>
                <button class="btn btn-small" type="button" data-gap-filter="evidence_missing">Evidence missing</button>
                <button class="btn btn-small" type="button" data-gap-filter="behavior_vt_conflict">Behavior/VT conflict</button>
            </div>
        </div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sample</th>
                        <th>ATT&amp;CK signal</th>
                        <th>VT confidence</th>
                        <th>Reason</th>
                        <th>Priority</th>
                    </tr>
                </thead>
                <tbody id="perm-classification-gaps-body">
                    <tr>
                        <td colspan="5" class="muted">Loading classification gaps...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Immediate operator routes</h2>
        </div>
    </div>
    <div class="perm-route-grid">
        <article class="perm-route-card surface-panel">
            <h3 class="perm-route-title">If current evidence needs review</h3>
            <p class="muted">Open Triage first. Review active evidence-backed UNKNOWNs, then inspect governed residue if policy triage is needed.</p>
            <div class="perm-route-links">
                <a class="btn btn-small" href="<?= h(page_url('permissions_triage')) ?>">Go to Triage</a>
            </div>
        </article>
        <article class="perm-route-card surface-panel">
            <h3 class="perm-route-title">If VT and Permission Intel disagree</h3>
            <p class="muted">Open Analysis Fusion to compare VT confidence with mapped Permission ATT&amp;CK behavior and find false positives, missing behavior mappings, or corroborated high-risk rows.</p>
            <div class="perm-route-links">
                <?php if (defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY): ?>
                    <a class="btn btn-small" href="<?= h(page_url('analysis_fusion')) ?>">Open Fusion</a>
                    <a class="btn btn-small btn-muted" href="<?= h(page_url('vt_confidence')) ?>">VT Confidence</a>
                <?php endif; ?>
            </div>
        </article>
        <article class="perm-route-card surface-panel">
            <h3 class="perm-route-title">If queued decisions are accumulating</h3>
            <p class="muted">Queue is now a diagnostics surface. Use it only when current queued work is non-zero or when investigating static-import residue.</p>
            <div class="perm-route-links">
                <?php if (defined('FEATURE_PHASE2B_READONLY') && FEATURE_PHASE2B_READONLY): ?>
                    <a class="btn btn-small btn-muted" href="<?= h(page_url('permissions_queue')) ?>">Queue Diagnostics</a>
                <?php endif; ?>
                <a class="btn btn-small btn-muted" href="<?= h(page_url('health')) ?>">Check VT Health</a>
            </div>
        </article>
        <article class="perm-route-card surface-panel">
            <h3 class="perm-route-title">If counts or namespaces drift</h3>
            <p class="muted">Open Drift or Pipeline Health to separate scheduler residue, derived-rollup drift, and real classification gaps.</p>
            <div class="perm-route-links">
                <a class="btn btn-small" href="<?= h(page_url('permissions_drift')) ?>">Open Drift</a>
                <a class="btn btn-small btn-muted" href="<?= h(page_url('health')) ?>">Pipeline Health</a>
            </div>
        </article>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Review lanes</h2>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Active review candidates</div>
        <div class="muted">Primary review priority is ranked by current UNKNOWN evidence prevalence, not historical ledger seen_count.</div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Current UNKNOWN samples</th>
                        <th>Current UNKNOWN obs</th>
                        <th>Risk</th>
                        <th>Last observed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="perm-top-unknown-body">
                    <tr>
                        <td colspan="6" class="muted">Loading unknown permissions...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="detail-card" style="margin-top: 16px;">
        <div class="detail-card-title">Governed current residue</div>
        <div class="muted">Current evidence already explained by governed policy, known ecosystems, malformed tokens, or missing ledger context.</div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Lane</th>
                        <th>Current governed samples</th>
                        <th>Last observed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="perm-governed-unknown-body">
                    <tr>
                        <td colspan="5" class="muted">Loading governed current residue...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="detail-card" style="margin-top: 16px;">
        <div class="detail-card-title">Ledger diagnostics</div>
        <div class="muted">Historical ledger inventory and residue. High seen_count lives here as diagnostics/context, not primary review priority.</div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Diagnostic</th>
                        <th>Historical ledger seen</th>
                        <th>Ledger last seen</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="perm-ledger-diagnostics-body">
                    <tr>
                        <td colspan="5" class="muted">Loading ledger diagnostics...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="perm-section">
    <div class="notice warn" id="perm-status-model" style="display:none;">
        <div><strong id="perm-status-model-title">Status model drift detected.</strong></div>
        <div class="muted" id="perm-status-model-summary">--</div>
        <ul class="maintenance-list" id="perm-status-model-list"></ul>
    </div>
    <div class="notice" id="perm-rollup-guard" style="display:none;">
        <div><strong>Rollup drift detected.</strong></div>
        <div class="muted" id="perm-rollup-guard-summary">--</div>
        <details id="perm-rollup-guard-details" style="display:none;">
            <summary class="muted">Show drifted permissions</summary>
            <ul class="maintenance-list" id="perm-rollup-guard-list"></ul>
        </details>
    </div>
    <div class="notice" id="perm-maintenance-status" style="display:none;"></div>
    <ul class="maintenance-list" id="perm-maintenance-list" style="display:none;"></ul>
</section>

<div class="health-error" id="perm-overview-error"></div>
    </div>
</div>
