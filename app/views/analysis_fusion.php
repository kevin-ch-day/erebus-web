<?php
// app/views/analysis_fusion.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Analysis Fusion';
$endpoint = api_url('analysis_fusion.php');
$queryLimit = max(1, min((int)($_GET['limit'] ?? 50), 250));
$pageScripts = [
    'assets/js/permission_intel_shared.js',
    'assets/js/pages/analysis_fusion_page.js',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Cross-Database Analysis</div>
        <div class="page-kicker">VT confidence x Permission ATT&amp;CK</div>
        <h1 class="page-hero-title">Analysis Fusion</h1>
        <p class="page-hero-lede muted">
            Joins Erebus VT confidence with Permission Intel ATT&amp;CK behavior so analysts can find disagreement, corroboration, and missing signal coverage.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('analysis_fusion')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('vt_confidence')) ?>">VT Confidence</a>
            <a class="btn" href="<?= h(page_url('permissions_overview')) ?>">Permission Overview</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Decision model</h2>
        <p>Prioritize rows where behavior outpaces VT confidence, then rows where strong VT evidence lacks mapped Permission Intel behavior.</p>
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

<div id="analysis-fusion-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-limit="<?= (int)$queryLimit ?>"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Fusion buckets</h2>
            <p class="muted">How sample evidence splits across VT confidence and Permission Intel behavior.</p>
        </div>
        <div class="muted" id="analysis-fusion-meta">Loading...</div>
    </div>
    <div class="detail-grid" id="analysis-fusion-summary">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Fetching fused analysis buckets...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Priority rows</h2>
            <p class="muted">Disagreement rows first, then corroborated high-risk rows.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Samples needing analyst attention</div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sample</th>
                        <th>Fusion bucket</th>
                        <th>Family check</th>
                        <th>VT confidence</th>
                        <th>ATT&amp;CK behavior</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody id="analysis-fusion-rows-body">
                    <tr>
                        <td colspan="6" class="muted">Loading fusion rows...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">ATT&amp;CK surface</h2>
            <p class="muted">Top mapped mobile ATT&amp;CK techniques from Permission Intel.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-card-title">Technique summary</div>
        <ul class="maintenance-list" id="analysis-fusion-attack-summary">
            <li class="muted">Loading ATT&amp;CK summary...</li>
        </ul>
    </div>
</section>

<div class="health-error" id="analysis-fusion-error"></div>
