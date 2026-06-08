<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/taxonomy_view_data.php';

$title = 'Conflicts & Governance';
$queryPlatform = strtolower(trim((string)($_GET['platform'] ?? 'android')));
$whyFirst = taxonomy_view_fetch(filters: ['decision_mode' => 'ask_why_first', 'platform' => $queryPlatform, 'include_rows' => false]);
$semantic = taxonomy_view_fetch(filters: ['pattern' => 'semantic_conflict', 'platform' => $queryPlatform, 'include_rows' => false]);

$governance = $whyFirst['data']['governance_inventory'] ?? [];
$decisionCounts = $whyFirst['data']['decision_inventory']['decision_mode_counts'] ?? [];
$issueCounts = $whyFirst['data']['issue_inventory']['issue_kind_counts'] ?? [];
$repairOps = $whyFirst['data']['repair_opportunities'] ?? [];
$semanticPairs = $semantic['data']['mismatch_pairs'] ?? [];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Malware Family Truth</div>
        <div class="page-kicker">Analyst review and family-governance work</div>
        <h1 class="page-hero-title">Conflicts &amp; Governance</h1>
        <p class="page-hero-lede muted">
            This page is for the hard review lanes: unresolved family disagreements, missing catalog families that do not yet have stable governed targets, and weak generic alignments that need adjudication instead of bulk repair.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_conflicts', ['platform' => $queryPlatform])) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'decision_mode' => 'ask_why_first'])) ?>">Open why-first queue</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Repair Queue</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Current conflict truth</h2>
        <p>Most of this lane is not direct naming conflict. It is governance-heavy `catalog_missing` debt. True semantic family conflicts are currently a very small slice.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Why-first rows</div>
                <div class="hero-metric-value"><?= h(number_format((int)($decisionCounts['ask_why_first'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Semantic conflicts</div>
                <div class="hero-metric-value"><?= h(number_format((int)($issueCounts['semantic_conflict'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Weak generic align.</div>
                <div class="hero-metric-value"><?= h(number_format((int)($issueCounts['weak_generic_alignment'] ?? 0))) ?></div>
            </div>
        </div>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Governance backlog composition</h2>
            <p class="muted">This shows what the analyst-review lane really contains.</p>
        </div>
    </div>
    <div class="detail-grid">
        <?php foreach ($repairOps as $row): ?>
            <?php if (!in_array((string)($row['decision_mode'] ?? ''), ['ask_why_first'], true)) { continue; } ?>
            <div class="detail-card">
                <div class="detail-card-title"><?= h((string)($row['dominant_issue_kind'] ?? 'Issue')) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)($row['row_count'] ?? 0))) ?></div>
                <p class="muted"><?= h((string)($row['suggested_fix_reason'] ?? '')) ?></p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_queue', array_filter([
                    'platform' => $queryPlatform,
                    'decision_mode' => 'ask_why_first',
                    'pattern' => (string)($row['dominant_issue_kind'] ?? ''),
                ], static fn($value): bool => $value !== ''))) ?>">Open slice</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Untargeted signal families</h2>
            <p class="muted">Top signal families inside the why-first lane that still lack stable governed targets.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Signal family</th>
                        <th>Rows</th>
                        <th>Queue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($governance['untargeted_top_signal_labels'] ?? []) as $label => $count): ?>
                        <tr>
                            <td><?= h((string)$label) ?></td>
                            <td><?= h(number_format((int)$count)) ?></td>
                            <td><a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'decision_mode' => 'ask_why_first', 'q' => (string)$label])) ?>">Open slice</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">True semantic conflicts</h2>
            <p class="muted">The low-volume but high-importance direct disagreement lane.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Catalog family</th>
                        <th>Signal family</th>
                        <th>Rows</th>
                        <th>Queue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($semanticPairs, 0, 10) as $pair): ?>
                        <tr>
                            <td><?= h((string)($pair['catalog_family_label'] ?? '')) ?></td>
                            <td><?= h((string)($pair['signal_family_name'] ?? '')) ?></td>
                            <td><?= h(number_format((int)($pair['row_count'] ?? 0))) ?></td>
                            <td><a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'pattern' => 'semantic_conflict'])) ?>">Open conflicts</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
