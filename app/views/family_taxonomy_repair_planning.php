<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/taxonomy_view_data.php';

$title = 'Repair Planning';
$endpoint = api_url('family_taxonomy_check.php');
$queryLimit = max(1, min((int)($_GET['limit'] ?? 100), 250));
$queryAlignment = trim((string)($_GET['alignment'] ?? ''));
$queryPlatform = strtolower(trim((string)($_GET['platform'] ?? 'android')));
$querySearch = trim((string)($_GET['q'] ?? ''));
$queryPattern = trim((string)($_GET['pattern'] ?? ''));
$queryPairCatalog = trim((string)($_GET['pair_catalog'] ?? ''));
$queryPairSignal = trim((string)($_GET['pair_signal'] ?? ''));
$queryFixAction = trim((string)($_GET['fix_action'] ?? ''));
$queryTargetFamily = trim((string)($_GET['target_family'] ?? ''));
$queryDecisionMode = trim((string)($_GET['decision_mode'] ?? ''));
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Malware Family Truth</div>
        <div class="page-kicker">Batching, repair grouping, and dry-run planning</div>
        <h1 class="page-hero-title">Repair Planning</h1>
        <p class="page-hero-lede muted">
            Specialist follow-up surface for grouped repair work after the row queue makes sense. It stays secondary to the main repair lanes and is only for grouped opportunities, action buckets, and dry-run planning.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_repair_planning', ['platform' => $queryPlatform])) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Back to repair queue</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_conflicts', ['platform' => $queryPlatform])) ?>">Conflicts &amp; Governance</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">When to use this</h2>
        <p>Start in the repair queue first. Use this page when you need grouped repair candidates, action buckets, or dry-run planning rather than row-by-row review.</p>
    </aside>
</section>

<div id="family-taxonomy-repair-planning-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-limit="<?= (int)$queryLimit ?>"
     data-alignment="<?= h($queryAlignment) ?>"
     data-platform="<?= h($queryPlatform) ?>"
     data-pattern="<?= h($queryPattern) ?>"
     data-pair-catalog="<?= h($queryPairCatalog) ?>"
     data-pair-signal="<?= h($queryPairSignal) ?>"
     data-fix-action="<?= h($queryFixAction) ?>"
     data-target-family="<?= h($queryTargetFamily) ?>"
     data-decision-mode="<?= h($queryDecisionMode) ?>"
     data-query="<?= h($querySearch) ?>"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Planning focus</h2>
            <p class="muted">Compact summary of what this planning slice is, where to start, and whether it should stay grouped or go back to row review.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-repair-planning-focus">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Building planning focus...</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Active slice</h2>
            <p class="muted">These filters are shaping the current planning view.</p>
        </div>
    </div>
    <div class="detail-card" id="family-taxonomy-repair-planning-active-slice">
        <div class="detail-card-title">Active slice</div>
        <div class="muted">Loading current planning filters...</div>
    </div>
    <div class="detail-grid" id="family-taxonomy-repair-planning-summary" style="margin-top: 16px;">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Fetching planning summary...</div>
        </div>
    </div>
    <div class="muted" id="family-taxonomy-repair-planning-meta" style="margin-top: 12px;">Loading...</div>
</section>

<section class="section-shell" id="family-taxonomy-repair-planning-presets-section">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Queue presets</h2>
            <p class="muted">Jump into the main repair lanes without rebuilding the same planning slices by hand.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-repair-planning-presets">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Building queue presets...</div>
        </div>
    </div>
</section>

<section class="section-shell" id="family-taxonomy-repair-planning-actions-section">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Repair actions</h2>
            <p class="muted">Grouped action buckets for the current slice.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-repair-planning-actions">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Building repair actions...</div>
        </div>
    </div>
</section>

<section class="section-shell" id="family-taxonomy-repair-planning-opportunities-section">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Batch repair opportunities</h2>
            <p class="muted">Top grouped repair candidates by suggested action and target family, with direct links into the exact filtered records.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Target family</th>
                        <th>Rows</th>
                        <th>High-confidence</th>
                        <th>Dominant issue</th>
                        <th>Examples</th>
                        <th>Open rows</th>
                    </tr>
                </thead>
                <tbody id="family-taxonomy-repair-planning-opportunities-body">
                    <tr>
                        <td colspan="7" class="muted">Loading repair opportunities...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section-shell" id="family-taxonomy-repair-planning-plan-section">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Dry-run repair plan</h2>
            <p class="muted">Grouped preview of bounded catalog writes that are currently safe enough to plan.</p>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-repair-planning-plan-summary">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Building dry-run plan summary...</div>
        </div>
    </div>
    <div class="detail-card" style="margin-top: 16px;">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Plan action</th>
                        <th>Target family</th>
                        <th>Rows</th>
                        <th>Sample IDs</th>
                        <th>Decision modes</th>
                        <th>Confidence</th>
                        <th>SQL preview</th>
                        <th>Open rows</th>
                    </tr>
                </thead>
                <tbody id="family-taxonomy-repair-planning-plan-body">
                    <tr>
                        <td colspan="8" class="muted">Loading dry-run repair plan...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="health-error" id="family-taxonomy-repair-planning-error"></div>
