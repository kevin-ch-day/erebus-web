<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Tech Stack Audit';
$endpoint = api_url('stack_audit.php');
$openApiEndpoint = api_url('openapi.php');
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Admin</div>
        <div class="page-kicker">Platform audit</div>
        <h1 class="page-hero-title">Tech Stack Audit</h1>
        <p class="page-hero-lede muted">
            Read-only inventory of the current Erebus Web stack, the biggest platform gaps, and the upgrade tracks worth considering before a larger rebuild.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('stack_audit')) ?>">Refresh</a>
            <a class="btn" href="<?= h($openApiEndpoint) ?>" target="_blank" rel="noreferrer noopener">OpenAPI JSON</a>
            <a class="btn" href="<?= h(page_url('schema_inventory')) ?>">Schema Inventory</a>
            <a class="btn" href="<?= h(page_url('admin_diagnostics')) ?>">Admin Diagnostics</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">What this page does</h2>
        <p>Separates runtime version facts from architecture debt, so the team can distinguish “already current” from “needs a real platform lift”.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Mode</div>
                <div class="hero-metric-value">Read-only</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Focus</div>
                <div class="hero-metric-value">Upgrade planning</div>
            </div>
        </div>
    </aside>
</section>

<div id="stack-audit-page" data-endpoint="<?= h($endpoint) ?>"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Runtime and toolchain</h2>
            <p class="muted">Local runtime and installed frontend tooling versions.</p>
        </div>
        <div class="muted" id="stack-audit-meta">Loading...</div>
    </div>
    <div class="detail-grid" id="stack-audit-runtime">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Inspecting runtime...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Architecture profile</h2>
            <p class="muted">Current app shape and how much surface area already exists on each layer.</p>
        </div>
    </div>
    <div class="detail-grid" id="stack-audit-architecture">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Building architecture profile...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Platform gaps</h2>
            <p class="muted">The biggest structural issues blocking a larger modernization effort.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>Gap</th>
                        <th>Why it matters</th>
                    </tr>
                </thead>
                <tbody id="stack-audit-gaps-body">
                    <tr>
                        <td colspan="3" class="muted">Loading platform gaps...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Upgrade tracks</h2>
            <p class="muted">Three realistic modernization paths, from bounded hardening to a full API-first split.</p>
        </div>
    </div>
    <div class="detail-grid" id="stack-audit-tracks">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Evaluating upgrade tracks...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">CLI entry points</h2>
            <p class="muted">Shell-side visibility helpers for family debt and stack-upgrade planning. Use these when you need fast repeatable output outside the browser.</p>
        </div>
    </div>
    <div class="detail-grid" id="stack-audit-cli">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Building CLI entry points...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Research anchors</h2>
            <p class="muted">Official docs used to ground the upgrade recommendations.</p>
        </div>
    </div>
    <div class="detail-card">
        <ul class="maintenance-list" id="stack-audit-anchors">
            <li class="muted">Loading research anchors...</li>
        </ul>
    </div>
</section>

<div class="health-error" id="stack-audit-error"></div>
