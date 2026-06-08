<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/dataset_readiness_data.php';

$title = 'Type Benchmark';
$payload = dataset_readiness_fetch_type_benchmark();
$summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
$classes = is_array($payload['classes'] ?? null) ? $payload['classes'] : [];
$allTypedClasses = is_array($payload['all_typed_classes'] ?? null) ? $payload['all_typed_classes'] : [];
$materializationDebt = is_array($payload['authority_materialization_debt'] ?? null) ? $payload['authority_materialization_debt'] : [];
$authorityConsistencySummary = is_array($payload['authority_consistency_summary'] ?? null)
    ? $payload['authority_consistency_summary']
    : [];
$mismatchSummary = is_array($payload['mismatch_summary'] ?? null) ? $payload['mismatch_summary'] : [];
$governanceQueue = is_array($payload['v2_governance_queue'] ?? null) ? $payload['v2_governance_queue'] : [];
$mismatchLabels = [
    'resolved_catalog_truth_vs_noisy_signal' => 'noisy_signal_mismatch',
    'unresolved_governance_gap' => 'unresolved_authority',
    'true_semantic_conflict' => 'semantic_conflict',
    'generic_signal_token' => 'generic_policy_hold',
    'projection_without_persisted_fact' => 'projection_materialization_debt',
];
$activeAuthorityConsistencyDebt = (int)($authorityConsistencySummary['affected_rows_count'] ?? 0) > 0;
$benchmarkClassKeys = [];
foreach ($classes as $row) {
    $benchmarkClassKeys[(string)($row['governed_type_slug'] ?? '')] = true;
}
$reviewClasses = [];
foreach ($allTypedClasses as $row) {
    $slug = (string)($row['governed_type_slug'] ?? '');
    if ($slug === '' || isset($benchmarkClassKeys[$slug])) {
        continue;
    }
    $reviewClasses[] = $row;
}

$reviewUseLabels = [
    'trainable_n10' => 'projection_only_n10',
    'trainable_n3_review' => 'not_clean_benchmark',
    'insufficient_support' => 'insufficient_support',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Dataset Curation</div>
        <div class="page-kicker">Primary target-readiness surface</div>
        <h1 class="page-hero-title">Type Benchmark</h1>
        <p class="page-hero-lede muted">
            Governed type-target readiness view for Erebus Web. This page is intentionally focused on <code>type_slug</code> as the current primary deep-learning target and does not broaden into family export generation yet.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('type_benchmark', ['refresh' => '1'])) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('dataset_readiness')) ?>">Dataset Readiness</a>
            <a class="btn" href="<?= h(page_url('label_surfaces')) ?>">Label Surfaces</a>
            <a class="btn" href="<?= h(page_url('authority_consistency_debt')) ?>">Authority Consistency Debt</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Benchmark rules</h2>
        <p>
            <?php if ($activeAuthorityConsistencyDebt): ?>
                Clean benchmark truth is smaller than <code>persisted_authority_fact</code>. Benchmark metrics now use authority-first <code>type_slug</code> resolution, exclude the active <code>authority_consistency_debt</code> block, and count only clean aligned persisted rows as benchmark truth.
            <?php else: ?>
                Benchmark metrics use authority-first <code>type_slug</code> resolution. The previous seven-family consistency holdout is currently at zero live rows, so clean benchmark truth currently matches the persisted typed authority count.
            <?php endif; ?>
        </p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">clean_benchmark_rows</div>
                <div class="hero-metric-value"><?= h(number_format((int)($summary['clean_benchmark_rows'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">projection_materialization_debt</div>
                <div class="hero-metric-value"><?= h(number_format((int)($summary['projection_without_persisted_fact_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">held_persisted_rows</div>
                <div class="hero-metric-value"><?= h(number_format((int)($summary['held_persisted_authority_consistency_debt_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">generic_policy_hold</div>
                <div class="hero-metric-value"><?= h(number_format((int)($summary['generic_token_policy_hold_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">authority_consistency_debt</div>
                <div class="hero-metric-value"><?= h(number_format((int)($authorityConsistencySummary['affected_rows_count'] ?? 0))) ?></div>
            </div>
        </div>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Projection-Only / Review-Required Type Classes</h2>
            <p class="muted">Typed classes visible in curation but excluded from the clean benchmark because they currently rely on projection or fallback resolution rather than clean aligned persisted authority.</p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Type slug</th>
                    <th>Typed rows</th>
                    <th>Families</th>
                    <th>High-conf rows</th>
                    <th>row_conflict_review</th>
                    <th>Top family</th>
                    <th>Recommended use</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reviewClasses === []): ?>
                    <tr>
                        <td colspan="7" class="muted">No review-only classes at the moment.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($reviewClasses as $row): ?>
                    <tr>
                        <td><strong><?= h((string)($row['governed_type_slug'] ?? '')) ?></strong></td>
                        <td><?= h(number_format((int)($row['sample_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['family_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['high_confidence_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['conflict_count'] ?? 0))) ?></td>
                        <td><?= h((string)($row['top_family'] ?? '--')) ?></td>
                        <td><?= h((string)($reviewUseLabels[(string)($row['recommended_use'] ?? '')] ?? 'not_clean_benchmark')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Benchmark Definition</h2>
            <p class="muted">Dataset-card preview for the governed Android type target.</p>
        </div>
    </div>
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Surface</div>
                <div class="detail-row"><div class="detail-label">Surface name</div><div class="detail-value">governed Android type benchmark</div></div>
                <div class="detail-row"><div class="detail-label">Primary target</div><div class="detail-value"><code>governed_type_slug</code> / <code>type_slug</code></div></div>
            <div class="detail-row"><div class="detail-label">Intended use</div><div class="detail-value">type-level malware classification readiness</div></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Not Intended For</div>
            <div class="detail-row"><div class="detail-label">Broad family benchmark claims</div><div class="detail-value">not supported</div></div>
            <div class="detail-row"><div class="detail-label">Subtype ontology claims</div><div class="detail-value">not supported</div></div>
            <div class="detail-row"><div class="detail-label">Benign-vs-malware claims</div><div class="detail-value">not supported</div></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Label Authority Model</div>
            <div class="detail-row"><div class="detail-label">Benchmark rows</div><div class="detail-value"><code>clean_benchmark_rows</code> = persisted fact present, typed, and not in the known <code>authority_consistency_debt</code> block</div></div>
            <div class="detail-row"><div class="detail-label">Exploratory typed rows</div><div class="detail-value"><code>authority_projection</code></div></div>
            <div class="detail-row"><div class="detail-label">Held or unresolved rows</div><div class="detail-value"><code>generic_policy_hold</code>, <code>unresolved_authority</code>, <code>row_conflict_review</code>, <code>authority_consistency_debt</code></div></div>
            <div class="detail-row"><div class="detail-label">Governance backlog</div><div class="detail-value"><code>governance_conflict_case</code></div></div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Label Authority Interpretation</h2>
            <p class="muted">How to read the authority tiers on this benchmark page.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title"><code>persisted_authority_fact</code></div>
            <p class="muted">Traceability total for persisted authority rows. This is not the same thing as clean benchmark truth when persisted facts disagree with current governed family/type policy.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>clean_benchmark_rows</code></div>
            <p class="muted">Conservative clean benchmark subset. These are the persisted typed rows after the known <code>authority_consistency_debt</code> block is held out.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>authority_projection</code></div>
            <p class="muted">Usable for exploratory or projection-augmented training, but lower confidence than persisted facts.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>projection_materialization_debt</code></div>
            <p class="muted">Projection-derived typed rows that still need persisted fact materialization.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>generic_policy_hold</code></div>
            <p class="muted">Not benchmark truth. These rows are held out because the family or signal surface is still too generic or policy-noisy.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>unresolved_authority</code></div>
            <p class="muted">Not benchmark truth. Authority resolution is still incomplete.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>governance_conflict_case</code></div>
            <p class="muted">Governance backlog, tracked separately from row-level benchmark eligibility.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>row_conflict_review</code></div>
            <p class="muted">Row-level hold or review state inside the typed surface. This is distinct from the formal governance conflict backlog.</p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title"><code>authority_consistency_debt</code></div>
            <p class="muted">Persisted facts that disagree with current governed family or type policy. These rows should not be read as clean persisted benchmark truth until adjudicated.</p>
        </div>
    </div>
</section>

<?php if (!(bool)($payload['ok'] ?? false)): ?>
    <div class="notice warn">
        Type benchmark metrics are blocked by missing schema surfaces.
        <?php foreach (($payload['schema_missing'] ?? []) as $missing): ?>
            <div class="mono"><?= h((string)($missing['catalog'] ?? '')) ?>.<?= h((string)($missing['table'] ?? '')) ?>.<?= h((string)($missing['column'] ?? '')) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Overall Metrics</h2>
            <p class="muted">Compact benchmark snapshot for the governed type target. This keeps only the metrics that materially change readiness interpretation.</p>
        </div>
    </div>
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Trainability</div>
                <div class="detail-row"><div class="detail-label">Clean benchmark rows</div><div class="detail-value"><?= h(number_format((int)($summary['clean_benchmark_rows'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Trainable classes n&gt;=3</div><div class="detail-value"><?= h(number_format((int)($summary['trainable_class_count_n3'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Trainable classes n&gt;=10</div><div class="detail-value"><?= h(number_format((int)($summary['trainable_class_count_n10'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Recommended use</div><div class="detail-value"><?= h((string)($summary['recommended_use'] ?? '--')) ?></div></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Concentration</div>
                <div class="detail-row"><div class="detail-label">Top class</div><div class="detail-value"><?= h((string)($summary['top_class'] ?? '--')) ?></div></div>
                <div class="detail-row"><div class="detail-label">Top class count</div><div class="detail-value"><?= h(number_format((int)($summary['top_class_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Top class share</div><div class="detail-value"><?= h(isset($summary['top_class_share']) ? number_format((float)$summary['top_class_share'], 2) . '%' : '--') ?></div></div>
                <div class="detail-row"><div class="detail-label">Top 5 share</div><div class="detail-value"><?= h(isset($summary['top_5_share']) ? number_format((float)$summary['top_5_share'], 2) . '%' : '--') ?></div></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Traceability</div>
                <div class="detail-row"><div class="detail-label">persisted_authority_fact</div><div class="detail-value"><?= h(number_format((int)($summary['persisted_authority_fact_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">held_persisted_rows</div><div class="detail-value"><?= h(number_format((int)($summary['held_persisted_authority_consistency_debt_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">projection_materialization_debt</div><div class="detail-value"><?= h(number_format((int)($summary['projection_without_persisted_fact_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">authority_consistency_debt</div><div class="detail-value"><?= h(number_format((int)($authorityConsistencySummary['affected_rows_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">governance_conflict_case</div><div class="detail-value"><?= h(number_format((int)($summary['conflict_case_count'] ?? 0))) ?></div></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Held / Non-benchmark rows</div>
                <div class="detail-row"><div class="detail-label">generic_policy_hold</div><div class="detail-value"><?= h(number_format((int)($summary['generic_token_policy_hold_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">unresolved_authority</div><div class="detail-value"><?= h(number_format((int)($summary['unresolved_authority_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">row_conflict_review</div><div class="detail-value"><?= h(number_format((int)($summary['conflict_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">proposal_only</div><div class="detail-value"><?= h(number_format((int)($summary['proposed_only_count'] ?? 0))) ?></div></div>
            </div>
        </div>
        <div class="notice info">
            In this benchmark scope, <code>authority_projection</code>, <code>projection_materialization_debt</code>, and <code>typed_without_fact</code> refer to the same projection-only typed population. The page shows <code>projection_materialization_debt</code> as the primary metric and keeps the others as aliases only.
        </div>
    </section>

    <section class="section-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Claim Guidance</h2>
                <p class="muted">What this page can and cannot support today.</p>
            </div>
        </div>
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Supported</div>
                <div class="detail-row"><div class="detail-label">Type-level readiness analysis</div><div class="detail-value">yes</div></div>
                <div class="detail-row"><div class="detail-label">Authority-tier comparison</div><div class="detail-value">yes</div></div>
                <div class="detail-row"><div class="detail-label">Persisted vs projection coverage</div><div class="detail-value">yes</div></div>
                <div class="detail-row"><div class="detail-label">Deep-learning preparation discussion</div><div class="detail-value">yes</div></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Not Yet Supported</div>
                <div class="detail-row"><div class="detail-label">Final release dataset claims</div><div class="detail-value">no</div></div>
                <div class="detail-row"><div class="detail-label">Train/test split claims</div><div class="detail-value">no</div></div>
                <div class="detail-row"><div class="detail-label">Family-level benchmark claims</div><div class="detail-value">no</div></div>
                <div class="detail-row"><div class="detail-label">Subtype taxonomy claims</div><div class="detail-value">no</div></div>
                <div class="detail-row"><div class="detail-label">Benign-vs-malicious deep learning claims</div><div class="detail-value">no</div></div>
                <div class="detail-row"><div class="detail-label">Dynamic-analysis claims</div><div class="detail-value">no</div></div>
            </div>
        </div>
    </section>

    <section class="section-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Mismatch Split</h2>
                <p class="muted">Mismatch is no longer treated as one undifferentiated bucket.</p>
            </div>
        </div>
        <div class="detail-grid">
            <?php foreach ($mismatchSummary as $label => $count): ?>
                <div class="detail-card">
                    <div class="detail-card-title"><?= h((string)($mismatchLabels[$label] ?? $label)) ?></div>
                    <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value"><?= h(number_format((int)$count)) ?></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Authority Materialization Debt</h2>
                <p class="muted">Top governed family/type pairs where projection says typed but no persisted fact exists.</p>
            </div>
        </div>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Family slug</th>
                        <th>Type slug</th>
                        <th>Rows</th>
                        <th>Highlight</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($materializationDebt === []): ?>
                        <tr><td colspan="4" class="muted">No materialization debt rows.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($materializationDebt as $row): ?>
                        <tr>
                            <td><strong><?= h((string)($row['governed_family_slug'] ?? '')) ?></strong></td>
                            <td><?= h((string)($row['governed_type_slug'] ?? '')) ?></td>
                            <td><?= h(number_format((int)($row['row_count'] ?? 0))) ?></td>
                            <td><?= h((bool)($row['highlight'] ?? false) ? 'priority' : '--') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Next Data Closure Targets</h2>
                <p class="muted">Read-only closure priorities before this benchmark can support stronger release-style claims.</p>
            </div>
        </div>
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Immediate</div>
                <div class="detail-row"><div class="detail-label">Materialize projection debt</div><div class="detail-value">persisted fact closure</div></div>
                <div class="detail-row"><div class="detail-label">Resolve <code>generic_policy_hold</code></div><div class="detail-value">reduce policy-noisy rows</div></div>
                <div class="detail-row"><div class="detail-label">Reduce <code>unresolved_authority</code></div><div class="detail-value">close authority gaps</div></div>
                <div class="detail-row"><div class="detail-label">Review <code>governance_conflict_case</code></div><div class="detail-value">governance backlog</div></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Later</div>
                <div class="detail-row"><div class="detail-label">Exclusion ledger</div><div class="detail-value">planned later</div></div>
                <div class="detail-row"><div class="detail-label">Split assignment</div><div class="detail-value">planned later</div></div>
                <div class="detail-row"><div class="detail-label">Release/export manifest</div><div class="detail-value">planned later</div></div>
            </div>
        </div>
    </section>

    <section class="section-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">V2 Governance Queue</h2>
                <p class="muted">Read-only summary from the existing v2 governance queue views.</p>
            </div>
        </div>
        <div class="detail-grid">
            <div class="detail-card">
                <div class="detail-card-title">Queue headlines</div>
                <div class="detail-row"><div class="detail-label">Total queue rows</div><div class="detail-value"><?= h(number_format((int)($governanceQueue['total_queue_rows'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">governance_conflict_case</div><div class="detail-value"><?= h(number_format((int)($governanceQueue['open_conflict_case_rows'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Missing alias surface</div><div class="detail-value"><?= h(number_format((int)($governanceQueue['missing_alias_surface_rows'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Missing structural assertions</div><div class="detail-value"><?= h(number_format((int)($governanceQueue['missing_structural_assertion_rows'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label">Missing external mappings</div><div class="detail-value"><?= h(number_format((int)($governanceQueue['missing_external_mapping_rows'] ?? 0))) ?></div></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Governance states</div>
                <?php foreach (array_slice((array)($governanceQueue['governance_queue_state_counts'] ?? []), 0, 6, true) as $label => $count): ?>
                    <div class="detail-row"><div class="detail-label"><?= h((string)$label) ?></div><div class="detail-value"><?= h(number_format((int)$count)) ?></div></div>
                <?php endforeach; ?>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Repair states</div>
                <?php foreach (array_slice((array)($governanceQueue['governance_and_repair_state_counts'] ?? []), 0, 6, true) as $label => $count): ?>
                    <div class="detail-row"><div class="detail-label"><?= h((string)$label) ?></div><div class="detail-value"><?= h(number_format((int)$count)) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<section class="section-shell">
        <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Governed Type Classes</h2>
            <p class="muted">
                <?php if ($activeAuthorityConsistencyDebt): ?>
                    Per-class clean benchmark counts only. Rows in the active <code>authority_consistency_debt</code> block are excluded from this table.
                <?php else: ?>
                    Per-class clean benchmark counts only. The tracked seven-family consistency watchlist is currently resolved, so no additional family-consistency holdout is being applied here.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Type slug</th>
                    <th>Samples</th>
                    <th>Families</th>
                    <th>Trainable n&gt;=3</th>
                    <th>Trainable n&gt;=10</th>
                    <th>Top family</th>
                    <th>Top family count</th>
                    <th>Top family share</th>
                    <th>Generic family rows</th>
                    <th>Mismatch rows</th>
                    <th>Unresolved family rows</th>
                    <th>High-conf rows</th>
                    <th>row_conflict_review</th>
                    <th>persisted_authority_fact</th>
                    <th>generic_policy_hold</th>
                    <th>unresolved_authority</th>
                    <th>Recommended use</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($classes === []): ?>
                    <tr>
                        <td colspan="17" class="muted">No governed type classes available yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($classes as $row): ?>
                    <tr>
                        <td><strong><?= h((string)($row['governed_type_slug'] ?? '')) ?></strong></td>
                        <td><?= h(number_format((int)($row['sample_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['family_count'] ?? 0))) ?></td>
                        <td><?= h((bool)($row['trainable_n3'] ?? false) ? 'yes' : 'no') ?></td>
                        <td><?= h((bool)($row['trainable_n10'] ?? false) ? 'yes' : 'no') ?></td>
                        <td><?= h((string)($row['top_family'] ?? '--')) ?></td>
                        <td><?= h(number_format((int)($row['top_family_count'] ?? 0))) ?></td>
                        <td><?= h(isset($row['top_family_share']) ? number_format((float)$row['top_family_share'], 2) . '%' : '--') ?></td>
                        <td><?= h(number_format((int)($row['generic_label_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['taxonomy_mismatch_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['unresolved_family_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['high_confidence_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['conflict_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['persisted_fact_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['generic_policy_hold_count'] ?? 0))) ?></td>
                        <td><?= h(number_format((int)($row['unresolved_authority_count'] ?? 0))) ?></td>
                        <td><?= h((string)($row['recommended_use'] ?? '--')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
