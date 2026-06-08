<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/dataset_readiness_data.php';

$title = 'Label Surfaces';
$query = trim((string)($_GET['q'] ?? ''));
$filterTypeSlug = trim((string)($_GET['type_slug'] ?? ''));
$filterTypeSource = trim((string)($_GET['type_slug_source'] ?? ''));
$filterTypeConfidence = trim((string)($_GET['type_slug_confidence'] ?? ''));
$filterRecommendedUse = trim((string)($_GET['recommended_use'] ?? ''));
$conflictOnly = (string)($_GET['conflict_only'] ?? '') === '1';
$benchmarkOnly = (string)($_GET['benchmark_only'] ?? '') === '1';
$reviewOnly = (string)($_GET['review_only'] ?? '') === '1';
$advancedMode = (string)($_GET['advanced'] ?? '') === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = max(10, min((int)($_GET['page_size'] ?? 50), 200));
$payload = dataset_readiness_fetch_label_surfaces([
    'q' => $query,
    'type_slug' => $filterTypeSlug,
    'type_slug_source' => $filterTypeSource,
    'type_slug_confidence' => $filterTypeConfidence,
    'recommended_use' => $filterRecommendedUse,
    'conflict_only' => $conflictOnly ? '1' : '0',
    'benchmark_only' => $benchmarkOnly ? '1' : '0',
    'review_only' => $reviewOnly ? '1' : '0',
    'advanced' => $advancedMode ? '1' : '0',
    'page' => $page,
    'page_size' => $pageSize,
]);
$fastPageScopeCounts = (bool)($payload['fast_page_scope_counts'] ?? false);
$rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
$totalPages = (int)($payload['total_pages'] ?? 1);
$prevPage = max(1, $page - 1);
$nextPage = min($totalPages, $page + 1);
$sourceCounts = is_array($payload['source_counts'] ?? null) ? $payload['source_counts'] : [];
$confidenceCounts = is_array($payload['confidence_counts'] ?? null) ? $payload['confidence_counts'] : [];
$benchmarkMetricLabel = $fastPageScopeCounts ? 'Page benchmark-ready' : 'Filtered benchmark-ready';
$conflictMetricLabel = $fastPageScopeCounts ? 'Page conflict cases' : 'Filtered conflict cases';
$reviewMetricLabel = $fastPageScopeCounts ? 'Page review-only' : 'Filtered review-only';
$typeSourceOptions = [
    'family_type_authority',
    'governed_type_authority',
    'catalog_family_type',
    'effective_type_authority',
    'classification_subtype',
    'classification_primary',
    'vt_popular_threat_category',
    'unresolved',
];
$recommendedUseOptions = [
    'type_slug_target',
    'type_slug_target_with_conflict_review',
    'type_slug_projection_materialization_review',
    'hold_generic_signal_not_for_benchmark',
    'governance_conflict_review',
    'type_slug_effective_authority_review',
    'type_slug_subtype_fallback_review',
    'type_slug_primary_fallback_review',
    'proposal_only_not_for_benchmark',
    'category_subtype_aux_only',
    'category_primary_not_target',
    'unresolved',
];
$basePageParams = [
    'q' => $query,
    'type_slug' => $filterTypeSlug,
    'type_slug_source' => $filterTypeSource,
    'type_slug_confidence' => $filterTypeConfidence,
    'recommended_use' => $filterRecommendedUse,
    'conflict_only' => $conflictOnly ? '1' : '0',
    'benchmark_only' => $benchmarkOnly ? '1' : '0',
    'review_only' => $reviewOnly ? '1' : '0',
    'advanced' => $advancedMode ? '1' : '0',
    'page_size' => $pageSize,
];
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Dataset Curation</div>
        <div class="page-kicker">Sample-level label comparison</div>
        <h1 class="page-hero-title">Label Surfaces</h1>
        <p class="page-hero-lede muted">
            One row per sample, with the default view compressed around catalog family, governed family, governed type, and authority status. Advanced audit fields stay available when needed.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('label_surfaces')) ?>">Reset</a>
            <a class="btn" href="<?= h(page_url('dataset_readiness')) ?>">Dataset Readiness</a>
            <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Type Benchmark</a>
            <a class="btn" href="<?= h(page_url('label_surfaces', $basePageParams + ['advanced' => $advancedMode ? '0' : '1', 'page' => 1])) ?>"><?= h($advancedMode ? 'Compact mode' : 'Advanced mode') ?></a>
            <a class="btn" href="<?= h(page_url('label_surfaces', $basePageParams + ['page' => 1, 'refresh' => '1'])) ?>">Refresh</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Current derivation</h2>
        <p>Governed <code>type_slug</code> is now authority-first. Erebus Web prefers family-to-type authority, then clean subtype fallback, then clean primary fallback, and uses VT category only as proposal-only context.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Rows shown</div>
                <div class="hero-metric-value"><?= h(number_format(count($rows))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Total rows</div>
                <div class="hero-metric-value"><?= h(number_format((int)($payload['total_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label"><?= h($benchmarkMetricLabel) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)($payload['benchmark_eligible_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label"><?= h($conflictMetricLabel) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)($payload['conflict_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label"><?= h($reviewMetricLabel) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)($payload['review_only_count'] ?? 0))) ?></div>
            </div>
        </div>
    </aside>
</section>

<?php if (!(bool)($payload['ok'] ?? false)): ?>
    <div class="notice warn">
        Label surface view is blocked by missing schema surfaces.
        <?php foreach (($payload['schema_missing'] ?? []) as $missing): ?>
            <div class="mono"><?= h((string)($missing['catalog'] ?? '')) ?>.<?= h((string)($missing['table'] ?? '')) ?>.<?= h((string)($missing['column'] ?? '')) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Filters</h2>
            <p class="muted"><?= h($advancedMode
                ? 'Advanced mode exposes raw classification, VT, and authority-audit filters.'
                : 'Compact mode keeps the default operator controls focused on search, type, and benchmark/review scope.') ?></p>
        </div>
    </div>
    <form method="get" action="<?= h(app_url('index.php')) ?>" class="filters">
        <input type="hidden" name="p" value="label_surfaces" />
        <div class="filter-field">
            <label for="dataset-label-q">Search</label>
            <input id="dataset-label-q" type="search" name="q" value="<?= h($query) ?>" placeholder="SHA256, family, category, package" />
        </div>
        <div class="filter-field">
            <label for="dataset-label-page-size">Rows</label>
            <select id="dataset-label-page-size" name="page_size">
                <?php foreach ([25, 50, 100, 200] as $size): ?>
                    <option value="<?= $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field">
            <label for="dataset-label-type">Type slug</label>
            <input id="dataset-label-type" type="search" name="type_slug" value="<?= h($filterTypeSlug) ?>" placeholder="banker, spyware, trojan" />
        </div>
        <div class="filter-field">
            <label for="dataset-label-conflict">Conflict only</label>
            <select id="dataset-label-conflict" name="conflict_only">
                <option value="0" <?= !$conflictOnly ? 'selected' : '' ?>>No</option>
                <option value="1" <?= $conflictOnly ? 'selected' : '' ?>>Yes</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="dataset-label-benchmark">Benchmark only</label>
            <select id="dataset-label-benchmark" name="benchmark_only">
                <option value="0" <?= !$benchmarkOnly ? 'selected' : '' ?>>No</option>
                <option value="1" <?= $benchmarkOnly ? 'selected' : '' ?>>Yes</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="dataset-label-review">Review only</label>
            <select id="dataset-label-review" name="review_only">
                <option value="0" <?= !$reviewOnly ? 'selected' : '' ?>>No</option>
                <option value="1" <?= $reviewOnly ? 'selected' : '' ?>>Yes</option>
            </select>
        </div>
        <?php if ($advancedMode): ?>
            <div class="filter-field">
                <label for="dataset-label-source">Type source</label>
                <select id="dataset-label-source" name="type_slug_source">
                    <option value="">Any</option>
                    <?php foreach ($typeSourceOptions as $source): ?>
                        <option value="<?= h($source) ?>" <?= $filterTypeSource === $source ? 'selected' : '' ?>><?= h(dataset_readiness_display_label('type_source', $source)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="dataset-label-confidence">Confidence</label>
                <select id="dataset-label-confidence" name="type_slug_confidence">
                    <option value="">Any</option>
                    <?php foreach (['high', 'medium', 'low', 'proposal', 'none'] as $confidence): ?>
                        <option value="<?= h($confidence) ?>" <?= $filterTypeConfidence === $confidence ? 'selected' : '' ?>><?= h($confidence) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="dataset-label-use">Use</label>
                <select id="dataset-label-use" name="recommended_use">
                    <option value="">Any</option>
                    <?php foreach ($recommendedUseOptions as $use): ?>
                        <option value="<?= h($use) ?>" <?= $filterRecommendedUse === $use ? 'selected' : '' ?>><?= h(dataset_readiness_display_label('recommended_use', $use)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <input type="hidden" name="advanced" value="<?= $advancedMode ? '1' : '0' ?>" />
        <div class="filter-field">
            <label>&nbsp;</label>
            <button class="btn" type="submit">Run</button>
        </div>
    </form>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Scope Snapshot</h2>
            <p class="muted">
                <?= $fastPageScopeCounts
                    ? 'Counts below reflect the currently rendered page in fast browse mode. Use narrower filters when you need whole-scope count precision.'
                    : 'Counts below reflect the current filter scope, not the whole catalog.' ?>
            </p>
        </div>
    </div>
    <div class="detail-grid">
        <?php if ($advancedMode): ?>
            <div class="detail-card">
                <div class="detail-card-title">Scope</div>
                <div class="detail-row"><div class="detail-label"><?= h($benchmarkMetricLabel) ?></div><div class="detail-value"><?= h(number_format((int)($payload['benchmark_eligible_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label"><?= h($reviewMetricLabel) ?></div><div class="detail-value"><?= h(number_format((int)($payload['review_only_count'] ?? 0))) ?></div></div>
                <div class="detail-row"><div class="detail-label"><?= h($conflictMetricLabel) ?></div><div class="detail-value"><?= h(number_format((int)($payload['conflict_count'] ?? 0))) ?></div></div>
            </div>
        <?php endif; ?>
        <div class="detail-card">
            <div class="detail-card-title">Authority mix</div>
            <?php foreach (array_slice($sourceCounts, 0, $advancedMode ? 5 : 4, true) as $label => $count): ?>
                <div class="detail-row"><div class="detail-label"><?= h(dataset_readiness_display_label('source_bucket', (string)$label)) ?></div><div class="detail-value"><?= h(number_format((int)$count)) ?></div></div>
            <?php endforeach; ?>
        </div>
        <?php if ($advancedMode): ?>
            <div class="detail-card">
                <div class="detail-card-title">Interpretation mix</div>
                <?php foreach ($confidenceCounts as $label => $count): ?>
                    <div class="detail-row"><div class="detail-label"><?= h(dataset_readiness_display_label('confidence', (string)$label)) ?></div><div class="detail-value"><?= h(number_format((int)$count)) ?></div></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Sample Comparison</h2>
            <p class="muted">This is a read-only comparison surface. It does not create exports or perform bulk edits. <?= h($advancedMode ? 'Advanced audit columns are enabled.' : 'Use Advanced mode only when you need raw classification, VT, and authority-audit fields.') ?> Compact governed type display is conservative: policy-hold and unknown-placeholder rows keep proposed types out of the main governed-type column.</p>
        </div>
        <div class="muted">Page <?= h((string)$page) ?> / <?= h((string)$totalPages) ?></div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Sample</th>
                    <th>Package</th>
                    <th>Catalog family</th>
                    <th>Governed family</th>
                    <th>Governed type</th>
                    <th>Authority tier</th>
                    <th>Resolution status</th>
                    <th>Recommended use</th>
                    <?php if ($advancedMode): ?>
                        <th>Primary</th>
                        <th>Subtype</th>
                        <th>VT family</th>
                        <th>VT category</th>
                        <th>Authority source</th>
                        <th>Authority method</th>
                        <th>Review status</th>
                        <th>Persisted fact</th>
                        <th>Authority bucket</th>
                        <th>Gap reason</th>
                        <th>Raw vs authority</th>
                        <th>Generic token</th>
                        <th>VT tail token</th>
                        <th>Mismatch bucket</th>
                        <th>Type source</th>
                        <th>Confidence</th>
                        <th>Proposed type</th>
                        <th>Conflict hold</th>
                        <th>Family status</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="<?= $advancedMode ? '27' : '8' ?>" class="muted">No rows found for the current filter.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $shaValue = (string)($row['sha256'] ?? $row['sample_label'] ?? '');
                    $shaDisplay = $shaValue !== '' && strlen($shaValue) > 16
                        ? substr($shaValue, 0, 16) . '...'
                        : ($shaValue !== '' ? $shaValue : '--');
                    $packageValue = (string)($row['package_name'] ?? $row['package'] ?? '--');
                    $catalogFamilyValue = (string)($row['family_label'] ?? '--');
                    $governedFamilyValue = (string)($row['governed_family_slug'] ?? $row['canonical_family_label'] ?? '--');
                    $displayTypeValue = (string)($row['display_type_slug'] ?? $row['governed_type_slug'] ?? $row['type_slug'] ?? '');
                    $proposedTypeValue = (string)($row['proposed_type_slug_display'] ?? $row['proposed_type_slug'] ?? '');
                    $governedTypeTitle = $displayTypeValue !== ''
                        ? $displayTypeValue
                        : ($proposedTypeValue !== '' ? 'Held; proposed type: ' . $proposedTypeValue : 'No governed type');
                    ?>
                    <tr>
                        <td>
                            <a class="table-link" href="<?= h(page_url('sample', ['sample_id' => (int)($row['sample_id'] ?? 0)])) ?>">
                                #<?= h((string)($row['sample_id'] ?? '')) ?>
                            </a><br>
                            <span class="muted" title="<?= h($shaValue) ?>"><?= h($shaDisplay) ?></span>
                        </td>
                        <td title="<?= h($packageValue) ?>"><?= h($packageValue) ?></td>
                        <td title="<?= h($catalogFamilyValue) ?>"><?= h($catalogFamilyValue) ?></td>
                        <td title="<?= h($governedFamilyValue) ?>"><?= h($governedFamilyValue) ?></td>
                        <td title="<?= h($governedTypeTitle) ?>"><?= h($displayTypeValue !== '' ? $displayTypeValue : '--') ?></td>
                        <td title="<?= h((string)($row['authority_tier'] ?? '')) ?>"><?= h(dataset_readiness_display_label('authority_tier', (string)($row['authority_tier'] ?? ''))) ?></td>
                        <td title="<?= h((string)($row['type_slug_resolution_status'] ?? $row['review_status'] ?? '')) ?>"><?= h(dataset_readiness_display_label('resolution_status', (string)($row['type_slug_resolution_status'] ?? $row['review_status'] ?? ''))) ?></td>
                        <td title="<?= h((string)($row['recommended_use'] ?? '')) ?>"><?= h(dataset_readiness_display_label('recommended_use', (string)($row['recommended_use'] ?? ''))) ?></td>
                        <?php if ($advancedMode): ?>
                            <td><?= h((string)($row['classification_primary'] ?? '--')) ?></td>
                            <td><?= h((string)($row['classification_subtype'] ?? '--')) ?></td>
                            <td><?= h((string)($row['popular_threat_name'] ?? '--')) ?></td>
                            <td><?= h((string)($row['popular_threat_category'] ?? '--')) ?></td>
                            <td title="<?= h((string)($row['authority_source'] ?? '')) ?>"><?= h((string)($row['authority_source'] ?? '--')) ?></td>
                            <td><?= h((string)($row['authority_resolution_method'] ?? '--')) ?></td>
                            <td><?= h((string)($row['review_status'] ?? '--')) ?></td>
                            <td><?= h((bool)($row['has_persisted_authority_fact'] ?? false) ? 'yes' : 'no') ?></td>
                            <td><?= h((string)($row['authority_bucket'] ?? '--')) ?></td>
                            <td><?= h((string)($row['authority_gap_reason'] ?? '--')) ?></td>
                            <td><?= h((string)($row['raw_vs_authority_status'] ?? '--')) ?></td>
                            <td><?= h((string)($row['generic_token_kind'] ?? '--')) ?></td>
                            <td><?= h((string)($row['vt_tail_token_kind'] ?? '--')) ?></td>
                            <td><?= h((string)($row['mismatch_bucket'] ?? '--')) ?></td>
                            <td title="<?= h((string)($row['type_slug_source'] ?? '')) ?>"><?= h(dataset_readiness_display_label('type_source', (string)($row['type_slug_source'] ?? ''))) ?></td>
                            <td title="<?= h((string)($row['type_slug_confidence'] ?? '')) ?>"><?= h(dataset_readiness_display_label('confidence', (string)($row['type_slug_confidence'] ?? ''))) ?></td>
                            <td><?= h((string)($row['proposed_type_slug_display'] ?? $row['proposed_type_slug'] ?? '--')) ?></td>
                            <td>
                                <?= h((bool)($row['type_slug_conflict_flag'] ?? false) ? 'yes' : 'no') ?>
                                <?php if ((bool)($row['type_slug_conflict_flag'] ?? false) && !empty($row['type_slug_conflict_reason'])): ?>
                                    <br><span class="muted"><?= h((string)$row['type_slug_conflict_reason']) ?></span>
                                <?php elseif ((bool)($row['type_slug_candidate_disagreement_flag'] ?? false)): ?>
                                    <br><span class="muted">candidate disagreement only</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h((string)($row['family_resolution_status'] ?? '--')) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <div class="table-meta muted">
            Resolution view available:
            <strong><?= h((bool)($payload['include_resolution_surface'] ?? false) ? 'yes' : 'no') ?></strong>
            | Label authority:
            <strong><?= h((bool)($payload['include_label_authority_surface'] ?? false) ? 'yes' : 'no') ?></strong>
            | Family/type authority:
            <strong><?= h((bool)($payload['include_type_authority_surface'] ?? false) ? 'yes' : 'no') ?></strong>
            | Persisted authority facts:
            <strong><?= h((bool)($payload['include_persisted_authority_surface'] ?? false) ? 'yes' : 'no') ?></strong>
        </div>
        <div class="table-controls">
            <a class="btn" href="<?= h(page_url('label_surfaces', $basePageParams + ['page' => $prevPage])) ?>">Prev</a>
            <div class="table-page">Page <?= h((string)$page) ?> / <?= h((string)$totalPages) ?></div>
            <a class="btn" href="<?= h(page_url('label_surfaces', $basePageParams + ['page' => $nextPage])) ?>">Next</a>
        </div>
    </div>
</section>
