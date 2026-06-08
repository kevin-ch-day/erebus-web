<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/taxonomy_view_data.php';

$title = 'Repair Queue';
$endpoint = api_url('family_taxonomy_check.php');
$exportEndpoint = api_url('family_taxonomy_queue_export.php');
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
$queueHome = taxonomy_view_fetch(filters: ['platform' => $queryPlatform, 'include_rows' => false]);
$queueDecisionCounts = taxonomy_view_decision_counts($queueHome);
$queueIssueCounts = taxonomy_view_issue_counts($queueHome);
$coverageDebt = (int)($queueIssueCounts['catalog_missing'] ?? 0)
    + (int)($queueIssueCounts['signal_gap'] ?? 0)
    + (int)($queueIssueCounts['unlabeled'] ?? 0)
    + (int)($queueIssueCounts['placeholder_catalog'] ?? 0);
$governanceDebt = (int)($queueDecisionCounts['ask_why_first'] ?? 0);
$signalHoldDebt = (int)($queueDecisionCounts['hold_generic_signal'] ?? 0)
    + (int)($queueDecisionCounts['hold_signal_overlap'] ?? 0);
$repairNowDebt = (int)($queueDecisionCounts['repair_now_candidate'] ?? 0);
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Malware Family Truth</div>
        <div class="page-kicker">Row-level repair and bounded write planning</div>
        <h1 class="page-hero-title">Repair Queue</h1>
        <p class="page-hero-lede muted">
            Working surface for concrete sample rows that are closest to actionable repair. Use this page for direct row review, Coverage &amp; Gaps for missing-family debt, Conflicts &amp; Governance for why-first review, and Signal Hygiene for noisy VT token handling.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Reset queue</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_gaps', ['platform' => $queryPlatform])) ?>">Coverage &amp; Gaps</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_conflicts', ['platform' => $queryPlatform])) ?>">Conflicts &amp; Governance</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_signal_hygiene', ['platform' => $queryPlatform])) ?>">Signal Hygiene</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Queue focus</h2>
        <p>Search, alignment, issue pattern, and pair-focus filters apply directly to the row table below. Keep this page on row review, not grouped planning or broad taxonomy reporting.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Repair-now</div>
                <div class="hero-metric-value"><?= h(number_format($repairNowDebt)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Why-first</div>
                <div class="hero-metric-value"><?= h(number_format($governanceDebt)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Signal holds</div>
                <div class="hero-metric-value"><?= h(number_format($signalHoldDebt)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Coverage debt</div>
                <div class="hero-metric-value"><?= h(number_format($coverageDebt)) ?></div>
            </div>
        </div>
    </aside>
</section>

<div id="family-taxonomy-queue-page"
     data-endpoint="<?= h($endpoint) ?>"
     data-export-endpoint="<?= h($exportEndpoint) ?>"
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
            <h2 class="section-shell-title">Adjacent lanes</h2>
            <p class="muted">These lanes still matter, but they now have dedicated pages and should not dominate the repair queue.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Coverage &amp; Gaps</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value"><?= h(number_format($coverageDebt)) ?></div></div>
            <p class="muted">Missing catalog families, catalog-only signal gaps, unlabeled rows, and placeholder debt.</p>
            <a class="btn" href="<?= h(page_url('family_taxonomy_gaps', ['platform' => $queryPlatform])) ?>">Open Coverage &amp; Gaps</a>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Conflicts &amp; Governance</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value"><?= h(number_format($governanceDebt)) ?></div></div>
            <p class="muted">Why-first adjudication, true semantic conflicts, and weak generic alignments.</p>
            <a class="btn" href="<?= h(page_url('family_taxonomy_conflicts', ['platform' => $queryPlatform])) ?>">Open Conflicts &amp; Governance</a>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Signal Hygiene</div>
            <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value"><?= h(number_format($signalHoldDebt)) ?></div></div>
            <p class="muted">Generic VT tokens, overlap labels, and governed alias handling that should stay out of direct repair.</p>
            <a class="btn" href="<?= h(page_url('family_taxonomy_signal_hygiene', ['platform' => $queryPlatform])) ?>">Open Signal Hygiene</a>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Slice controls</h2>
            <p class="muted">Change the current repair slice here. Keep this page centered on row selection, filtering, export, and review.</p>
        </div>
    </div>
    <div class="detail-card" id="family-taxonomy-queue-active-slice">
        <div class="detail-card-title">Active slice</div>
        <div class="muted">Loading active queue filters...</div>
    </div>
    <div class="filters">
        <div class="filter-field" id="family-taxonomy-queue-search-field">
            <label for="family-taxonomy-queue-search">Search</label>
            <input id="family-taxonomy-queue-search" type="search" placeholder="Family, package, signal label, SHA256"
                   value="<?= h($querySearch) ?>" />
        </div>
        <div class="filter-field" id="family-taxonomy-queue-alignment-field">
            <label for="family-taxonomy-queue-alignment">Alignment</label>
            <select id="family-taxonomy-queue-alignment">
                <option value="">All rows</option>
                <option value="mismatch" <?= $queryAlignment === 'mismatch' ? 'selected' : '' ?>>Mismatch</option>
                <option value="signal_only" <?= $queryAlignment === 'signal_only' ? 'selected' : '' ?>>Signal only</option>
                <option value="catalog_only" <?= $queryAlignment === 'catalog_only' ? 'selected' : '' ?>>Catalog only</option>
                <option value="aligned" <?= $queryAlignment === 'aligned' ? 'selected' : '' ?>>Aligned</option>
                <option value="unlabeled" <?= $queryAlignment === 'unlabeled' ? 'selected' : '' ?>>Unlabeled</option>
                <option value="generic_label" <?= $queryAlignment === 'generic_label' ? 'selected' : '' ?>>Generic catalog label</option>
            </select>
        </div>
        <div class="filter-field" id="family-taxonomy-queue-platform-field">
            <label for="family-taxonomy-queue-platform">Platform</label>
            <select id="family-taxonomy-queue-platform">
                <option value="">All platforms</option>
                <option value="android" <?= $queryPlatform === 'android' ? 'selected' : '' ?>>Android</option>
                <option value="windows" <?= $queryPlatform === 'windows' ? 'selected' : '' ?>>Windows</option>
                <option value="linux" <?= $queryPlatform === 'linux' ? 'selected' : '' ?>>Linux</option>
                <option value="macos" <?= $queryPlatform === 'macos' ? 'selected' : '' ?>>macOS</option>
                <option value="unknown" <?= $queryPlatform === 'unknown' ? 'selected' : '' ?>>Unknown</option>
            </select>
        </div>
        <div class="filter-field" id="family-taxonomy-queue-pattern-field" style="min-width: 220px;">
            <label for="family-taxonomy-queue-pattern">Pattern</label>
            <select id="family-taxonomy-queue-pattern">
                <option value="">All patterns</option>
                <option value="unknown_catalog" <?= $queryPattern === 'unknown_catalog' ? 'selected' : '' ?>>Unknown catalog</option>
                <option value="generic_catalog" <?= $queryPattern === 'generic_catalog' ? 'selected' : '' ?>>Generic catalog</option>
                <option value="generic_signal" <?= $queryPattern === 'generic_signal' ? 'selected' : '' ?>>Generic signal</option>
                <option value="short_signal" <?= $queryPattern === 'short_signal' ? 'selected' : '' ?>>Short signal token</option>
                <option value="spy_bank_loader_signal" <?= $queryPattern === 'spy_bank_loader_signal' ? 'selected' : '' ?>>Spy/bank/loader signal</option>
                <option value="placeholder_catalog" <?= $queryPattern === 'placeholder_catalog' ? 'selected' : '' ?>>Placeholder catalog</option>
                <option value="alias_candidate" <?= $queryPattern === 'alias_candidate' ? 'selected' : '' ?>>Alias candidate</option>
                <option value="alias_resolved" <?= $queryPattern === 'alias_resolved' ? 'selected' : '' ?>>Governed alias resolved</option>
                <option value="semantic_conflict" <?= $queryPattern === 'semantic_conflict' ? 'selected' : '' ?>>Semantic conflict</option>
            </select>
        </div>
        <div class="filter-field" id="family-taxonomy-queue-limit-field" style="min-width: 160px;">
            <label for="family-taxonomy-queue-limit">Rows</label>
            <select id="family-taxonomy-queue-limit">
                <?php foreach ([50, 100, 150, 250] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $queryLimit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field" id="family-taxonomy-queue-run-field">
            <label>&nbsp;</label>
            <button class="btn" id="family-taxonomy-queue-refresh" type="button">Run queue</button>
        </div>
        <div class="filter-field" id="family-taxonomy-queue-export-field">
            <label>&nbsp;</label>
            <a class="btn" id="family-taxonomy-queue-export" href="<?= h($exportEndpoint) ?>">Export current slice</a>
        </div>
    </div>
    <div class="detail-grid" id="family-taxonomy-queue-summary">
        <div class="detail-card">
            <div class="detail-card-title">Loading</div>
            <div class="muted">Fetching queue summary...</div>
        </div>
    </div>
    <div class="muted" id="family-taxonomy-queue-meta" style="margin-top: 12px;">Loading...</div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Repair rows</h2>
            <p class="muted" id="family-taxonomy-queue-rows-copy">Rows ordered by repair priority. Use the quick filter here to narrow the active slice before opening grouped planning work.</p>
        </div>
    </div>
    <div class="detail-card" id="family-taxonomy-queue-rows-section">
        <div class="detail-card-title">Repair candidate rows</div>
        <div class="filters" style="margin-top: 10px;">
            <div class="filter-field" style="min-width: 280px;">
                <label for="family-taxonomy-queue-row-filter">Quick filter rows</label>
                <input id="family-taxonomy-queue-row-filter" type="search" placeholder="Sample ID, family, signal, issue, action" />
            </div>
        </div>
        <div class="table-scroll" style="margin-top: 10px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sample ID</th>
                        <th>Catalog family</th>
                        <th>VT signal</th>
                        <th>Issue</th>
                        <th>Action plan</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody id="family-taxonomy-queue-rows-body">
                    <tr>
                        <td colspan="6" class="muted">Loading family repair rows...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="health-error" id="family-taxonomy-queue-error"></div>
