<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/dataset_readiness_data.php';

$title = 'Dataset Readiness';
$payload = dataset_readiness_fetch_overview(false);
$surfaces = is_array($payload['surfaces'] ?? null) ? $payload['surfaces'] : [];
$authorityConsistencyPayload = dataset_readiness_fetch_authority_consistency_debt();
$authorityConsistencySummary = is_array($authorityConsistencyPayload['summary'] ?? null) ? $authorityConsistencyPayload['summary'] : [];
$activeAuthorityConsistencyDebt = (int)($authorityConsistencySummary['affected_rows_count'] ?? 0) > 0;
$derivationPolicy = [
    'A' => 'Prefer persisted family-to-type authority from malware_family_authority_fact when present.',
    'B' => 'Otherwise use governed type authority from label_authority_resolution_view, then catalog family type if available.',
    'C' => 'Treat effective type authority as reviewable support, not first-class benchmark truth when stronger family authority disagrees.',
    'D' => 'Otherwise use normalized classification_subtype, then classification_primary, only when they map cleanly to governed type values.',
    'E' => 'Use VT popular_threat_category as proposal-only fallback; otherwise leave type_slug unresolved.',
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Dataset Curation</div>
        <div class="page-kicker">Deep-learning readiness MVP</div>
        <h1 class="page-hero-title">Dataset Readiness</h1>
        <p class="page-hero-lede muted">
            Read-only overview for the candidate label surfaces. This page stays lightweight on purpose and points deeper benchmark work to <code>type_benchmark</code>.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('dataset_readiness')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Open Type Benchmark</a>
            <a class="btn" href="<?= h(page_url('label_surfaces')) ?>">Open Label Surfaces</a>
            <a class="btn" href="<?= h(page_url('authority_consistency_debt')) ?>">Authority Consistency Debt</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Policy</h2>
        <p><code>type_slug</code> is the primary type target. <code>major_family_benchmark</code> stays separate from the broad all-current family census, <code>category_subtype</code> is auxiliary, <code>category_primary</code> is not a scientific target, and exports are not implemented yet.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Primary target</div>
                <div class="hero-metric-value">type_slug</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Benchmark page</div>
                <div class="hero-metric-value">type_benchmark</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Auxiliary</div>
                <div class="hero-metric-value">category_subtype</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Not scientific</div>
                <div class="hero-metric-value">category_primary</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Debt page</div>
                <div class="hero-metric-value">authority_consistency_debt</div>
            </div>
        </div>
    </aside>
</section>

<?php if ($activeAuthorityConsistencyDebt): ?>
    <div class="notice warn">
        <code>authority_consistency_debt</code> is tracked separately from <code>projection_materialization_debt</code>. Persisted facts are treated as strongest only when they agree with current governed family/type policy or have explicit reviewed status.
    </div>
<?php else: ?>
    <div class="notice info">
        The tracked seven-family <code>authority_consistency_debt</code> watchlist is currently at zero live affected rows. Persisted facts still remain strongest only when they agree with current governed family/type policy or have explicit reviewed status.
    </div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Surface Inventory</h2>
            <p class="muted">Status and intended use only. Detailed authority and benchmark metrics live on <code>type_benchmark</code>. Export artifacts are not implemented yet and do not need a primary operator page.</p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Surface</th>
                    <th>Status</th>
                    <th>Authority posture</th>
                    <th>Recommended use</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($surfaces as $surface): ?>
                    <tr>
                        <td><strong><?= h((string)($surface['label'] ?? '')) ?></strong></td>
                        <td><?= h((string)($surface['status'] ?? 'pending')) ?></td>
                        <td><?= h((string)($surface['surface_key'] === 'type_slug' ? 'authority_first' : 'pending')) ?></td>
                        <td><?= h((string)($surface['recommended_use'] ?? '--')) ?></td>
                        <td class="muted"><?= h((string)($surface['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Authority Vocabulary</h2>
            <p class="muted">Canonical readiness terms used across the authority-aware pages.</p>
        </div>
    </div>
    <div class="detail-grid">
        <?php foreach ([
            'persisted_authority_fact' => 'Persisted governed family/type truth from malware_family_authority_fact.',
            'authority_projection' => 'Derived governed family/type projection from authority views.',
            'projection_materialization_debt' => 'Projection-backed typed rows that still do not have a persisted authority fact.',
            'generic_policy_hold' => 'Rows intentionally held out because tokens are generic or policy-noisy.',
            'unresolved_authority' => 'Rows that still need authority resolution or governance review.',
            'governance_conflict_case' => 'Formal governance conflict cases from the v2 queue layer.',
            'row_conflict_review' => 'Row-level review rows with disagreement pressure inside the typed surface.',
            'semantic_conflict' => 'True family naming disagreement after alias/generic stripping.',
            'noisy_signal_mismatch' => 'Mismatch caused by noisy VT-family signal rather than catalog truth failure.',
        ] as $term => $definition): ?>
            <div class="detail-card">
                <div class="detail-card-title"><code><?= h($term) ?></code></div>
                <p class="muted"><?= h($definition) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Type Derivation Policy</h2>
            <p class="muted">Current read-only precedence for governed <code>type_slug</code> in Erebus Web.</p>
        </div>
    </div>
    <div class="detail-grid">
        <?php foreach ($derivationPolicy as $step => $policy): ?>
            <div class="detail-card">
                <div class="detail-card-title">Step <?= h((string)$step) ?></div>
                <p class="muted"><?= h((string)$policy) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
