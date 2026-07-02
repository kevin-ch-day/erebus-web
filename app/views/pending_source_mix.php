<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/pipeline_panel.php';
require_once __DIR__ . '/../database/db_func.php';
require_once __DIR__ . '/../database/services/intake_service.php';

$title = 'Pending Source Mix';
$laneFilter = trim((string)($_GET['lane'] ?? ''));
$limit = clamp_int($_GET['limit'] ?? 50, 5, 100, 50);
$loadError = null;
$sources = [];
try {
    $mixSnapshot = db_pending_source_mix_snapshot($laneFilter !== '' ? $laneFilter : null, $limit);
    $pipelinePanel = [
        'pipeline' => is_array($mixSnapshot['pipeline'] ?? null) ? $mixSnapshot['pipeline'] : [],
        'hint' => db_pipeline_operator_hint(is_array($mixSnapshot['pipeline'] ?? null) ? $mixSnapshot['pipeline'] : []),
        'lane_summary' => db_pipeline_lane_summary(
            is_array($mixSnapshot['pipeline']['queue_lanes'] ?? null) ? $mixSnapshot['pipeline']['queue_lanes'] : []
        ),
        'recommended_lane' => is_string($mixSnapshot['recommended_lane'] ?? null) ? $mixSnapshot['recommended_lane'] : null,
        'source' => (string)(is_array($mixSnapshot['pipeline'] ?? null) ? ($mixSnapshot['pipeline']['source'] ?? 'db') : 'db'),
    ];
} catch (Throwable $ignored) {
    $pipelinePanel = pipeline_panel_load(true);
}

$snapshotApiUrl = api_url('pending_source_mix_snapshot.php');
if ($laneFilter !== '') {
    $snapshotApiUrl .= '?' . http_build_query(['lane' => $laneFilter, 'limit' => $limit]);
} else {
    $snapshotApiUrl .= '?' . http_build_query(['limit' => $limit]);
}

try {
    $sources = db_ingest_backlog_pending_sources($limit, $laneFilter !== '' ? $laneFilter : null);
} catch (Throwable $e) {
    $loadError = defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Unable to load pending source mix right now.';
}

$androidSources = [];
$genericSources = [];
$otherSources = [];
foreach ($sources as $sourceRow) {
    $sourceValue = (string)($sourceRow['artifact_source'] ?? '');
    if (db_ingest_is_android_ingest_source($sourceValue)) {
        $androidSources[] = $sourceRow;
        continue;
    }
    if (db_ingest_is_generic_reservoir_source($sourceValue)) {
        $genericSources[] = $sourceRow;
        continue;
    }
    $otherSources[] = $sourceRow;
}
$orderedSources = array_merge($androidSources, $genericSources, $otherSources);
$backlogHref = page_url('ingest_backlog', ['lane' => $laneFilter, 'limit' => 20, 'preview' => 10]);

$countRows = static function (array $rows): int {
    return count($rows);
};

$sumPendingRows = static function (array $rows): int {
    return array_reduce(
        $rows,
        static fn(int $carry, array $row): int => $carry + (int)($row['pending_rows'] ?? 0),
        0
    );
};

$topSource = static function (array $rows): ?array {
    if ($rows === []) {
        return null;
    }
    return $rows[0];
};

$listedSourceCount = $countRows($orderedSources);
$androidSourceCount = $countRows($androidSources);
$genericSourceCount = $countRows($genericSources);
$otherSourceCount = $countRows($otherSources);
$androidPendingTotal = $sumPendingRows($androidSources);
$genericPendingTotal = $sumPendingRows($genericSources);
$otherPendingTotal = $sumPendingRows($otherSources);
$pendingRowTotal = $sumPendingRows($orderedSources);
$topAndroidSource = $topSource($androidSources);
$topGenericSource = $topSource($genericSources);
$topOtherSource = $topSource($otherSources);
?>

<div id="pending-source-mix-page"
     style="display:none;"
     data-endpoint="<?= h($snapshotApiUrl) ?>"
     data-refresh-seconds="30"></div>

<style>
.source-mix-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.source-mix-class-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.source-mix-class-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.source-mix-class-metric {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.1;
}

.source-mix-class-sub {
    color: var(--muted);
    font-size: 0.92rem;
}

.source-mix-top-source {
    padding-top: 8px;
    border-top: 1px solid var(--border);
}

.source-mix-top-source code,
.source-mix-source-name {
    word-break: break-word;
    overflow-wrap: anywhere;
}

.source-mix-table td {
    vertical-align: top;
}

.source-mix-class-cell {
    min-width: 150px;
}

.source-mix-source-cell {
    min-width: 420px;
}

.source-mix-source-name {
    font-family: var(--font-mono, ui-monospace, SFMono-Regular, Menlo, monospace);
    font-size: 0.95rem;
}

.source-mix-source-actions {
    margin-top: 8px;
}

.source-mix-count-cell {
    white-space: nowrap;
    text-align: right;
    font-weight: 700;
}

.source-mix-anchor-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.source-mix-empty {
    padding: 14px 0;
}
</style>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Threat Workspace</div>
        <div class="page-kicker">Source posture</div>
        <h1 class="page-hero-title">Pending Source Mix</h1>
        <p class="page-hero-lede muted">
            Review pending sources on their own page when the source list is too dense for the main backlog screen.
            Android feeds stay first, then the generic reservoir, then other feeds.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('pipeline_ops')) ?>">Pipeline Ops</a>
            <a class="btn" href="<?= h($backlogHref) ?>">Back to Ingest Backlog</a>
            <a class="btn" href="<?= h(page_url('check_hash')) ?>">Check Hash</a>
            <a class="btn" href="<?= h(page_url('submit_artifact')) ?>">Submit Artifact</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">What to scan first</h2>
        <p>Start with the dominant source class, then pivot into a focused backlog slice only when one source is clearly driving queue pressure.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Listed sources</div>
                <div class="hero-metric-value" id="source-mix-source-count"><?= h(number_format($listedSourceCount)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Pending rows shown</div>
                <div class="hero-metric-value" id="source-mix-pending-total"><?= h(number_format($pendingRowTotal)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Largest class</div>
                <div class="hero-metric-value"><?= h($genericPendingTotal > $androidPendingTotal && $genericPendingTotal > $otherPendingTotal ? 'Reservoir' : ($androidPendingTotal >= $otherPendingTotal ? 'Android' : 'Other')) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Lane filter</div>
                <div class="hero-metric-value"><?= h($laneFilter !== '' ? $laneFilter : 'All lanes') ?></div>
            </div>
        </div>
    </aside>
</section>

<?php if ($loadError !== null): ?>
    <div class="notice error" style="margin-bottom: 16px;"><?= h($loadError) ?></div>
<?php endif; ?>

<?php
render_pipeline_engine_panel($pipelinePanel, [
    'id_prefix' => 'source-mix-engine',
    'variant' => 'notice',
    'title' => 'Engine context',
    'slice_note' => 'Source mix is local; engine recommendation reflects full-queue posture.',
    'live_meta_id' => 'source-mix-live-meta',
]);
?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Active slice</h2>
            <p class="section-shell-copy">Use the lane filter from Ingest Backlog when you want the source mix for one workload lane instead of the full queue.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Lane filter</div>
            <div class="detail-value"><?= h($laneFilter !== '' ? $laneFilter : 'All workload lanes') ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Listed sources</div>
            <div class="detail-value"><?= number_format($listedSourceCount) ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Pending rows shown</div>
            <div class="detail-value"><?= number_format($pendingRowTotal) ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Largest source</div>
            <div class="detail-value">
                <?= h((string)(($topAndroidSource['artifact_source'] ?? $topGenericSource['artifact_source'] ?? $topOtherSource['artifact_source'] ?? 'None'))) ?>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Source classes</h2>
            <p class="section-shell-copy">Split the backlog by source class first, then drill into one source at a time. This keeps Android intake, the generic reservoir, and other feed families visually separate.</p>
        </div>
        <div class="source-mix-anchor-links">
            <a class="btn btn-small" href="#source-class-android">Android feeds</a>
            <a class="btn btn-small" href="#source-class-generic">Generic reservoir</a>
            <a class="btn btn-small" href="#source-class-other">Other feeds</a>
        </div>
    </div>
    <div class="source-mix-summary-grid">
        <article class="detail-card source-mix-class-card" id="source-class-android">
            <div class="source-mix-class-head">
                <div>
                    <div class="detail-card-title">Android feeds</div>
                    <div class="source-mix-class-sub">Governed APK-oriented intake sources.</div>
                </div>
                <span class="badge ok"><span id="source-mix-android-count"><?= number_format($androidSourceCount) ?></span> source(s)</span>
            </div>
            <div class="source-mix-class-metric" id="source-mix-android-pending"><?= number_format($androidPendingTotal) ?></div>
            <div class="source-mix-class-sub">Pending rows across Android feed sources</div>
            <div class="source-mix-top-source">
                <div class="detail-label">Top source</div>
                <div class="detail-value">
                    <?php if ($topAndroidSource !== null): ?>
                        <code><?= h((string)$topAndroidSource['artifact_source']) ?></code>
                        <div class="muted"><?= number_format((int)$topAndroidSource['pending_rows']) ?> pending rows</div>
                    <?php else: ?>
                        <span class="muted">No Android feed rows in this slice.</span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <article class="detail-card source-mix-class-card" id="source-class-generic">
            <div class="source-mix-class-head">
                <div>
                    <div class="detail-card-title">Generic reservoir</div>
                    <div class="source-mix-class-sub">Broad discovery backlog kept separate from governed intake.</div>
                </div>
                <span class="badge warn"><span id="source-mix-generic-count"><?= number_format($genericSourceCount) ?></span> source(s)</span>
            </div>
            <div class="source-mix-class-metric" id="source-mix-generic-pending"><?= number_format($genericPendingTotal) ?></div>
            <div class="source-mix-class-sub">Pending rows across generic reservoir sources</div>
            <div class="source-mix-top-source">
                <div class="detail-label">Top source</div>
                <div class="detail-value">
                    <?php if ($topGenericSource !== null): ?>
                        <code><?= h((string)$topGenericSource['artifact_source']) ?></code>
                        <div class="muted"><?= number_format((int)$topGenericSource['pending_rows']) ?> pending rows</div>
                    <?php else: ?>
                        <span class="muted">No generic reservoir rows in this slice.</span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <article class="detail-card source-mix-class-card" id="source-class-other">
            <div class="source-mix-class-head">
                <div>
                    <div class="detail-card-title">Other feeds</div>
                    <div class="source-mix-class-sub">LAMDA and other non-Android/non-reservoir sources.</div>
                </div>
                <span class="badge muted"><span id="source-mix-other-count"><?= number_format($otherSourceCount) ?></span> source(s)</span>
            </div>
            <div class="source-mix-class-metric" id="source-mix-other-pending"><?= number_format($otherPendingTotal) ?></div>
            <div class="source-mix-class-sub">Pending rows across other feed sources</div>
            <div class="source-mix-top-source">
                <div class="detail-label">Top source</div>
                <div class="detail-value">
                    <?php if ($topOtherSource !== null): ?>
                        <code><?= h((string)$topOtherSource['artifact_source']) ?></code>
                        <div class="muted"><?= number_format((int)$topOtherSource['pending_rows']) ?> pending rows</div>
                    <?php else: ?>
                        <span class="muted">No other-feed rows in this slice.</span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </div>
</section>

<?php
$sections = [
    [
        'id' => 'android',
        'title' => 'Android feeds',
        'copy' => 'Governed APK-oriented sources stay first so Android intake pressure is visible without being drowned out by generic discovery backlog.',
        'rows' => $androidSources,
        'badge_class' => 'ok',
        'badge_label' => 'Android feed',
    ],
    [
        'id' => 'generic',
        'title' => 'Generic reservoir',
        'copy' => 'Broad discovery backlog remains separate so operators can distinguish raw reservoir pressure from governed feed intake.',
        'rows' => $genericSources,
        'badge_class' => 'warn',
        'badge_label' => 'Generic reservoir',
    ],
    [
        'id' => 'other',
        'title' => 'Other feeds',
        'copy' => 'Other feed classes, including LAMDA cohorts and smaller external feeds, stay grouped together for quick comparison.',
        'rows' => $otherSources,
        'badge_class' => 'muted',
        'badge_label' => 'Other feed',
    ],
];
?>

<?php foreach ($sections as $section): ?>
    <section class="section-shell" id="source-class-<?= h($section['id']) ?>-table">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title"><?= h($section['title']) ?></h2>
                <p class="section-shell-copy"><?= h($section['copy']) ?></p>
            </div>
            <div class="muted">
                <?= number_format(count($section['rows'])) ?> source(s) ·
                <?= number_format($sumPendingRows($section['rows'])) ?> pending rows
            </div>
        </div>
        <div class="table-scroll">
            <table class="table source-mix-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Artifact source</th>
                        <th>Pending rows</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($section['rows'] === []): ?>
                        <tr>
                            <td colspan="3" class="muted source-mix-empty">No rows in this class for the current slice.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($section['rows'] as $source): ?>
                            <?php $sourceValue = (string)($source['artifact_source'] ?? ''); ?>
                            <tr>
                                <td class="source-mix-class-cell">
                                    <span class="badge <?= h((string)$section['badge_class']) ?>"><?= h((string)$section['badge_label']) ?></span>
                                </td>
                                <td class="source-mix-source-cell">
                                    <div class="source-mix-source-name"><?= h($sourceValue) ?></div>
                                    <div class="source-mix-source-actions">
                                        <a class="table-link" href="<?= h(page_url('ingest_backlog', ['limit' => 20, 'preview' => 10, 'source' => $sourceValue, 'lane' => $laneFilter])) ?>">Focus source in backlog</a>
                                    </div>
                                </td>
                                <td class="source-mix-count-cell"><?= number_format((int)($source['pending_rows'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
