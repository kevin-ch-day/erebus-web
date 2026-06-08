<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/taxonomy_view_data.php';

$title = 'Coverage & Gaps';
$queryPlatform = strtolower(trim((string)($_GET['platform'] ?? 'android')));
$all = taxonomy_view_fetch(filters: ['platform' => $queryPlatform, 'include_rows' => false]);
$signalOnly = taxonomy_view_fetch(filters: ['alignment' => 'signal_only', 'platform' => $queryPlatform, 'include_rows' => false]);
$catalogOnly = taxonomy_view_fetch(filters: ['alignment' => 'catalog_only', 'platform' => $queryPlatform, 'include_rows' => false]);

$summaryByAlignment = taxonomy_view_summary_by_alignment($all);
$issueCounts = taxonomy_view_issue_counts($all);
$priorityLanes = $all['data']['remediation_summary']['priority_lanes'] ?? [];
$catalogOnlyAuthority = taxonomy_view_catalog_only_authority_summary($queryPlatform);

$signalGovernance = $signalOnly['data']['governance_inventory']['untargeted_top_signal_labels'] ?? [];
$catalogTop = taxonomy_view_catalog_only_anchor_families($queryPlatform, 10);
$signalOnlyRows = (int)($summaryByAlignment['signal_only']['row_count'] ?? 0);
$catalogOnlyRows = (int)($summaryByAlignment['catalog_only']['row_count'] ?? 0);
$unlabeledRows = (int)($summaryByAlignment['unlabeled']['row_count'] ?? 0);
$placeholderRows = (int)($issueCounts['placeholder_catalog'] ?? 0);
$genericCatalogRows = (int)($all['data']['remediation_summary']['row_pattern_summary']['generic_catalog_rows'] ?? 0);
$catalogOnlyGovernedRows = (int)($catalogOnlyAuthority['authority_family_typed_rows'] ?? 0);
$catalogOnlyUnknownRows = (int)($catalogOnlyAuthority['resolved_unknown_rows'] ?? 0);
$catalogOnlyResidualRows = (int)($catalogOnlyAuthority['residual_review_rows'] ?? 0);
$catalogOnlyGovernedPct = (float)($catalogOnlyAuthority['authority_coverage_pct'] ?? 0.0);
$catalogOnlyMissingSignalRows = (int)($catalogOnlyAuthority['missing_signal_row_rows'] ?? 0);
$catalogOnlyCoarseVtOnlyRows = (int)($catalogOnlyAuthority['coarse_vt_only_rows'] ?? 0);
$catalogOnlyEmptySignalRows = (int)($catalogOnlyAuthority['empty_signal_surface_rows'] ?? 0);
$catalogOnlySourceBatchRows = (int)($catalogOnlyAuthority['source_batch_backed_rows'] ?? 0);
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Malware Family Truth</div>
        <div class="page-kicker">Visibility debt and missing-family coverage</div>
        <h1 class="page-hero-title">Coverage &amp; Gaps</h1>
        <p class="page-hero-lede muted">
            This page owns the biggest taxonomy backlog: unlabeled rows, catalog-missing rows, catalog-only rows, and placeholder family labels. Use it to reduce missingness before spending time on low-volume semantic disputes.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_gaps', ['platform' => $queryPlatform])) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'alignment' => 'signal_only'])) ?>">Open signal-only queue</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Repair Queue</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Why this page exists</h2>
        <p>Live taxonomy debt is dominated by visibility gaps, not by direct naming conflict. This surface isolates the bulk debt so operators can close missing family coverage faster.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Signal only</div>
                <div class="hero-metric-value"><?= h(number_format($signalOnlyRows)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Catalog only</div>
                <div class="hero-metric-value"><?= h(number_format($catalogOnlyRows)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Unlabeled</div>
                <div class="hero-metric-value"><?= h(number_format($unlabeledRows)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Placeholders</div>
                <div class="hero-metric-value"><?= h(number_format($placeholderRows)) ?></div>
            </div>
        </div>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Gap pressure</h2>
            <p class="muted">These are the dominant gap lanes that should drive data-completeness work.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Signal-only families</div>
            <div class="hero-metric-value"><?= h(number_format($signalOnlyRows)) ?></div>
            <p class="muted">Rows where VT has a family token but the catalog family is blank. These are governance-heavy, not yet safe bulk repairs.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Catalog-only families</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyRows)) ?></div>
            <p class="muted">Rows where the catalog has family truth but VT family signal is absent. Most of this slice is already governed family+type truth and should not be mistaken for direct repair debt.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Unlabeled rows</div>
            <div class="hero-metric-value"><?= h(number_format($unlabeledRows)) ?></div>
            <p class="muted">Neither surface has a usable family token. This is intake/catalog visibility debt, not taxonomy naming work.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Generic catalog labels</div>
            <div class="hero-metric-value"><?= h(number_format($genericCatalogRows)) ?></div>
            <p class="muted">Catalog rows using broad family placeholders. These should be split from true conflicts.</p>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Catalog-only governance split</h2>
            <p class="muted">This separates already-governed catalog truth from the smaller residual slice that still needs governance attention.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Governed family+type</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyGovernedRows)) ?></div>
            <p class="muted"><?= h(number_format($catalogOnlyGovernedPct, 2)) ?>% of catalog-only rows already resolve to an authority-backed family and type.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Resolved unknown</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyUnknownRows)) ?></div>
            <p class="muted">These rows are explicitly landing in the authority pipeline as unknown rather than missing a join by accident.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Residual review</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyResidualRows)) ?></div>
            <p class="muted">This is the real catalog-only governance slice left after already-governed rows are removed.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Coarse VT only</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyCoarseVtOnlyRows)) ?></div>
            <p class="muted">VT row exists, but only a coarse category/label survived and the family token is blank.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Empty signal surface</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyEmptySignalRows)) ?></div>
            <p class="muted">VT row exists, but family name, label, and category are all blank.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Missing VT row</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlyMissingSignalRows)) ?></div>
            <p class="muted">No joined VT signal row exists for these catalog-only records.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Source-batch backed</div>
            <div class="hero-metric-value"><?= h(number_format($catalogOnlySourceBatchRows)) ?></div>
            <p class="muted">Catalog-only rows that still carry source-batch provenance even when VT family signal is absent.</p>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Signal-only backlog shape</h2>
            <p class="muted">The largest uncovered VT signal families driving `catalog_missing` debt right now.</p>
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
                    <?php foreach (array_slice($signalGovernance, 0, 10, true) as $label => $count): ?>
                        <tr>
                            <td><?= h((string)$label) ?></td>
                            <td><?= h(number_format((int)$count)) ?></td>
                            <td><a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'alignment' => 'signal_only', 'q' => (string)$label])) ?>">Open slice</a></td>
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
            <h2 class="section-shell-title">Catalog-only anchor families</h2>
            <p class="muted">Top families inside the `catalog_only` slice. This helps separate genuine signal gaps from already-governed catalog truth.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Catalog family</th>
                        <th>Rows</th>
                        <th>Governed family</th>
                        <th>Governed type</th>
                        <th>Status</th>
                        <th>Signal gap</th>
                        <th>Queue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogTop as $row): ?>
                        <tr>
                            <td><?= h((string)($row['catalog_family'] ?? '')) ?></td>
                            <td><?= h(number_format((int)($row['row_count'] ?? 0))) ?></td>
                            <td><?= h((string)($row['governed_family_name'] ?? '--')) ?></td>
                            <td><?= h((string)($row['governed_type_slug'] ?? '--')) ?></td>
                            <td><?= h((string)($row['governance_status'] ?? '--')) ?></td>
                            <td><?= h((string)($row['signal_gap_status'] ?? '--')) ?></td>
                            <td><a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'alignment' => 'catalog_only', 'q' => (string)($row['catalog_family'] ?? '')])) ?>">Open slice</a></td>
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
            <h2 class="section-shell-title">Priority lanes from home</h2>
            <p class="muted">The gap-heavy remediation lanes that justify this dedicated page.</p>
        </div>
    </div>
    <div class="detail-grid">
        <?php foreach ($priorityLanes as $lane): ?>
            <?php if (!in_array((string)($lane['lane'] ?? ''), ['visibility_gap', 'generic_label_cleanup', 'unknown_placeholder_cleanup'], true)) { continue; } ?>
            <div class="detail-card">
                <div class="detail-card-title"><?= h((string)($lane['title'] ?? 'Lane')) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)($lane['rows'] ?? 0))) ?></div>
                <p class="muted"><?= h((string)($lane['why'] ?? '')) ?></p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_queue', array_filter([
                    'platform' => $queryPlatform,
                    'alignment' => (string)($lane['alignment'] ?? ''),
                    'pattern' => (string)($lane['pattern'] ?? ''),
                ], static fn($value): bool => $value !== ''))) ?>">Open queue slice</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
