<?php
// app/views/vt_ops_dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'VT Ops Dashboard';
$endpoint = api_url('vt_ops_summary.php');
$pageScripts = ['assets/js/pages/vt_ops_dashboard_page.js'];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">VirusTotal</div>
        <div class="page-kicker">Evidence and surface status</div>
        <h1 class="page-hero-title">VT Ops Dashboard</h1>
        <p class="page-hero-lede muted">
            Use this page for VT evidence-surface readiness only: vendor-model coverage, delta activity,
            signal-surface coverage, and confidence-schema presence. Pipeline state and key posture now live on
            VT &amp; Pipeline Health.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('vt_ops_dashboard')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('health')) ?>">VT &amp; Pipeline Health</a>
            <a class="btn" href="<?= h(page_url('vt_confidence')) ?>">VT Confidence</a>
            <?php if (defined('FEATURE_PHASE3_OPS') && FEATURE_PHASE3_OPS): ?>
                <a class="btn" href="<?= h(page_url('vt_key_controls')) ?>">VT Key Controls</a>
            <?php endif; ?>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Page ownership</h2>
        <p>Health owns hold state, scheduler pressure, and key posture. This page owns the evidence-side VT surfaces that support confidence and downstream interpretation.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Primary job</div>
                <div class="hero-metric-value">Evidence</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Pipeline state</div>
                <div class="hero-metric-value">Health page</div>
            </div>
        </div>
    </aside>
</section>

<div id="vt-ops-dashboard-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-refresh-seconds="<?= (int)DASHBOARD_REFRESH_SECONDS ?>"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Interpretation</h2>
            <p class="muted">This page should not be used as the primary stoplight for hold state or scheduler movement.</p>
        </div>
    </div>
    <div class="notice info">
        Use <a href="<?= h(page_url('health')) ?>">VT &amp; Pipeline Health</a> for global hold state, scheduler pressure, key posture, and pipeline routing.
        Use this page only for vendor/evidence/signal surface coverage.
    </div>
    <div class="notice info" id="vt-ops-next-path" style="margin-top: 12px;">Next path: --</div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Evidence surface context</h2>
            <p class="muted">Minimal context needed to interpret vendor, delta, signal, and confidence surfaces.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Catalog alignment</div>
            <div class="detail-row">
                <div class="detail-label">Primary head</div>
                <div class="detail-value" id="vt-ops-primary-head">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">PI head</div>
                <div class="detail-value" id="vt-ops-pi-head">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Heads match</div>
                <div class="detail-value" id="vt-ops-heads-match">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Confidence schema</div>
                <div class="detail-value" id="vt-ops-confidence-schema">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">VT evidence surfaces</div>
                <div class="detail-value" id="vt-ops-vt-surfaces">--</div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Vendor model</h2>
            <p class="muted">Current canonical vendor rows, drift mass, and projection posture from the newer VT database model.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Row inventory</div>
            <div class="detail-row">
                <div class="detail-label">Canonical verdict rows</div>
                <div class="detail-value" id="vt-ops-vendor-canonical-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Projection rows</div>
                <div class="detail-value" id="vt-ops-vendor-projection-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reliability rows</div>
                <div class="detail-value" id="vt-ops-vendor-reliability-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Projection profile rows</div>
                <div class="detail-value" id="vt-ops-vendor-profile-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Collision rows</div>
                <div class="detail-value" id="vt-ops-vendor-collision-rows">--</div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Current behavior</div>
            <div class="detail-row">
                <div class="detail-label">Avg reliability</div>
                <div class="detail-value" id="vt-ops-vendor-avg-weight">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Avg FP tendency</div>
                <div class="detail-value" id="vt-ops-vendor-avg-fp">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Avg instability</div>
                <div class="detail-value" id="vt-ops-vendor-avg-instability">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Avg projection fill</div>
                <div class="detail-value" id="vt-ops-vendor-avg-fill">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Low-fill candidates</div>
                <div class="detail-value" id="vt-ops-vendor-low-fill">--</div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Drift and signals</h2>
            <p class="muted">Recent vendor drift volume and current signal-surface coverage from the live catalog.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">30-day drift</div>
            <div class="detail-row">
                <div class="detail-label">Delta rows</div>
                <div class="detail-value" id="vt-ops-delta-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Changed engines</div>
                <div class="detail-value" id="vt-ops-delta-changed-engines">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">New engines</div>
                <div class="detail-value" id="vt-ops-delta-new-engines">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Removed engines</div>
                <div class="detail-value" id="vt-ops-delta-removed-engines">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Label/category changes</div>
                <div class="detail-value" id="vt-ops-delta-label-category">--</div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Signal coverage</div>
            <div class="detail-row">
                <div class="detail-label">Signal current rows</div>
                <div class="detail-value" id="vt-ops-signal-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Confidence rows</div>
                <div class="detail-value" id="vt-ops-confidence-rows">--</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Top parse version</div>
                <div class="detail-value" id="vt-ops-signal-parse-version">--</div>
            </div>
        </div>
    </div>
</section>

<div class="health-meta muted" id="vt-ops-meta">Last refresh: --</div>
<div class="health-error" id="vt-ops-error"></div>
