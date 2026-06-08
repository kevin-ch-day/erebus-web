<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Taxonomy Workspace';
$endpoint = api_url('family_taxonomy_check.php');
$queryLimit = max(1, min((int)($_GET['limit'] ?? 100), 250));
$queryAlignment = trim((string)($_GET['alignment'] ?? ''));
$queryPlatform = strtolower(trim((string)($_GET['platform'] ?? 'android')));
$querySearch = trim((string)($_GET['q'] ?? ''));
$queryPattern = trim((string)($_GET['pattern'] ?? ''));
$queryPairCatalog = trim((string)($_GET['pair_catalog'] ?? ''));
$queryPairSignal = trim((string)($_GET['pair_signal'] ?? ''));
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Malware Family Truth</div>
        <div class="page-kicker">Taxonomy dashboard and workload map</div>
        <h1 class="page-hero-title">Taxonomy Workspace</h1>
        <p class="page-hero-lede muted">
            Operator landing page for the taxonomy domain. This page should show pressure, separate authority gaps from noisy signal, and route you into the lane that owns the work.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_check', ['platform' => $queryPlatform])) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_gaps', ['platform' => $queryPlatform])) ?>">Coverage &amp; Gaps</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Open repair queue</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_conflicts', ['platform' => $queryPlatform])) ?>">Conflicts &amp; Governance</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_signal_hygiene', ['platform' => $queryPlatform])) ?>">Signal Hygiene</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Authority pressure</h2>
        <p>Keep the home page narrow: unresolved authority, generic-policy holds, projection materialization debt, and formal governance conflict cases.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">unresolved_authority</div>
                <div class="hero-metric-value" id="family-taxonomy-hero-unresolved">--</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">generic_policy_hold</div>
                <div class="hero-metric-value" id="family-taxonomy-hero-policy-hold">--</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">projection_materialization_debt</div>
                <div class="hero-metric-value" id="family-taxonomy-hero-projection-debt">--</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">governance_conflict_case</div>
                <div class="hero-metric-value" id="family-taxonomy-hero-conflict">--</div>
            </div>
        </div>
    </aside>
</section>

<div id="family-taxonomy-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-include-rows="0"
     data-limit="<?= (int)$queryLimit ?>"
     data-platform="<?= h($queryPlatform) ?>"
     data-alignment=""
     data-pattern=""
     data-pair-catalog=""
     data-pair-signal=""
     data-query=""></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Taxonomy workspace</h2>
            <p class="muted">Each page owns a different kind of workload. Use this split to keep bulk gap cleanup, direct repair, governance review, and signal-noise policy from collapsing back into one screen.</p>
        </div>
    </div>
    <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Coverage &amp; Gaps</div>
                <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value" id="family-taxonomy-workspace-coverage">--</div></div>
                <p class="muted">Owns unlabeled, signal-only, catalog-only, and placeholder visibility debt.</p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_gaps', ['platform' => $queryPlatform])) ?>">Open Coverage &amp; Gaps</a>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Repair Queue</div>
                <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value" id="family-taxonomy-workspace-repair">--</div></div>
                <p class="muted">Owns bounded repair candidates and direct row-level repair slices.</p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Open Repair Queue</a>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Conflicts &amp; Governance</div>
                <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value" id="family-taxonomy-workspace-conflicts">--</div></div>
                <p class="muted">Owns missing-family governance, weak generic alignments, and true semantic conflicts.</p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_conflicts', ['platform' => $queryPlatform])) ?>">Open Conflicts &amp; Governance</a>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Signal Hygiene</div>
                <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value" id="family-taxonomy-workspace-signal">--</div></div>
                <p class="muted">Owns generic VT tokens, overlap labels, and governed alias handling.</p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_signal_hygiene', ['platform' => $queryPlatform])) ?>">Open Signal Hygiene</a>
            </div>
        </div>
    </section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Alignment summary</h2>
            <p class="muted">Raw family-label geometry across catalog truth and VT signal surfaces. This is not the same as repair-ready backlog.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-summary">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Fetching family taxonomy summary...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Authority Pressure</h2>
            <p class="muted">High-value authority buckets only. This keeps unresolved authority, generic-policy holds, projection materialization debt, governance conflict cases, and top repair queues visible without replaying every metric already shown elsewhere.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-pressure">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Calculating authority pressure...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Actionable lanes</h2>
            <p class="muted">Preset triage slices that separate repair-now work from governance review, generic-signal holds, and already-resolved alias rows.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-presets">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Loading actionable queue presets...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Remediation priorities</h2>
            <p class="muted">Quantitative tuning view: where taxonomy debt is concentrated, and which fixes are likely to close the most rows first.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-remediation">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Calculating remediation priorities...</div>
        </div>
    </div>
</section>

<div class="health-error" id="family-taxonomy-error"></div>
