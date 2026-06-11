<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../database/services/intake_service.php';

$title = 'Ingest Backlog';
$limit = clamp_int($_GET['limit'] ?? 20, 5, 100, 20);
$previewLimit = clamp_int($_GET['preview'] ?? 10, 5, 25, 10);
$displayTimeFormat = 'g:i A n/j/Y';
$sourceFilter = trim((string)($_GET['source'] ?? ''));
$laneFilter = trim((string)($_GET['lane'] ?? ''));
$hasSlice = $sourceFilter !== '' || $laneFilter !== '';
$resetHref = page_url('ingest_backlog', ['limit' => $limit, 'preview' => $previewLimit]);
$loadError = null;
$totals = [];
$lanes = [];
$batches = [];
$sources = [];
$operator = [];
$cleanup = [];
$previewRows = [];

try {
    $totals = db_ingest_backlog_totals($sourceFilter !== '' ? $sourceFilter : null, $laneFilter !== '' ? $laneFilter : null);
    $operator = db_ingest_operator_snapshot($sourceFilter !== '' ? $sourceFilter : null, $laneFilter !== '' ? $laneFilter : null);
    $cleanup = db_ingest_cleanup_pressure($sourceFilter !== '' ? $sourceFilter : null, $laneFilter !== '' ? $laneFilter : null);
    $lanes = db_ingest_backlog_lane_summary(null, $laneFilter !== '' ? $laneFilter : null);
    $batches = db_ingest_backlog_batch_summary($limit, $sourceFilter !== '' ? $sourceFilter : null, $laneFilter !== '' ? $laneFilter : null);
    $sources = db_ingest_backlog_pending_sources(50, $laneFilter !== '' ? $laneFilter : null);
    $previewRows = db_ingest_backlog_preview_rows($previewLimit, $sourceFilter !== '' ? $sourceFilter : null, $laneFilter !== '' ? $laneFilter : null);
} catch (Throwable $e) {
    $loadError = defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Unable to load ingest backlog right now.';
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
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Threat Workspace</div>
        <div class="page-kicker">Queue posture</div>
        <h1 class="page-hero-title">Ingest Backlog</h1>
        <p class="page-hero-lede muted">
            Review queue pressure before adding more artifacts. This page keeps the backlog, lane pressure,
            pending age, and source mix on one screen.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('check_hash')) ?>">Check Hash</a>
            <a class="btn" href="<?= h(page_url('submit_artifact')) ?>">Submit Artifact</a>
        </div>
    </div>
</section>

<?php if ($loadError !== null): ?>
    <div class="notice error" style="margin-bottom: 16px;"><?= h($loadError) ?></div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Active slice</h2>
            <p class="section-shell-copy">Use lightweight slicing here before you decide whether the backlog is a source problem, a lane problem, or a recovery problem.</p>
        </div>
        <?php if ($hasSlice): ?>
            <div class="flow-inline">
                <a class="btn" href="<?= h($resetHref) ?>">Reset slice</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Source filter</div>
            <div class="detail-value"><?= h($sourceFilter !== '' ? $sourceFilter : 'All pending sources') ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Lane filter</div>
            <div class="detail-value"><?= h($laneFilter !== '' ? $laneFilter : 'All workload lanes') ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Batch view limit</div>
            <div class="detail-value"><?= number_format($limit) ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Preview rows</div>
            <div class="detail-value"><?= number_format($previewLimit) ?></div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Operator snapshot</h2>
            <p class="section-shell-copy">Core queue posture from the intake side.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-row">
            <div class="detail-label">Queue</div>
            <div class="detail-value"><?= h((string)($operator['queue_label'] ?? '--')) ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Sample catalog</div>
            <div class="detail-value">
                <?= number_format((int)($operator['catalog_rows'] ?? 0)) ?>
                | VT terminal coverage:
                <?= number_format((int)($operator['vt_terminal_rows'] ?? 0)) ?> / <?= number_format((int)($operator['state_rows'] ?? 0)) ?>
                | Android PI current rows:
                <?= ($operator['pi_current_rows'] ?? null) === null ? 'unavailable' : number_format((int)$operator['pi_current_rows']) ?>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Import readiness</div>
            <div class="detail-value"><?= h((string)($operator['import_readiness'] ?? 'unavailable')) ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Scale warning</div>
            <div class="detail-value"><?= h((string)($operator['scale_warning'] ?? 'unavailable')) ?></div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Backlog snapshot</h2>
            <p class="section-shell-copy">This is the intake-side equivalent of a queue health check. Start here before adding more work.</p>
        </div>
    </div>
    <div class="health-tiles">
        <div class="health-tile">
            <div class="health-tile-label">Pending</div>
            <div class="health-tile-value"><?= number_format((int)($totals['pending_rows'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Processing</div>
            <div class="health-tile-value"><?= number_format((int)($totals['processing_rows'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Failed</div>
            <div class="health-tile-value"><?= number_format((int)($totals['failed_rows'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Queue rows</div>
            <div class="health-tile-value"><?= number_format((int)($totals['queue_rows'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Active lanes</div>
            <div class="health-tile-value"><?= number_format((int)($totals['lane_count'] ?? 0)) ?></div>
        </div>
    </div>
    <div class="detail-grid" style="margin-top: 14px;">
        <div class="detail-card">
            <div class="detail-card-title">Oldest pending</div>
            <div class="detail-value"><?= h(fmt_utc_display((string)($totals['oldest_pending_at_utc'] ?? ''), $displayTimeFormat)) ?: '--' ?></div>
            <div class="muted">Shows whether intake pressure is fresh or aging out.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Newest pending</div>
            <div class="detail-value"><?= h(fmt_utc_display((string)($totals['newest_pending_at_utc'] ?? ''), $displayTimeFormat)) ?: '--' ?></div>
            <div class="muted">Useful for spotting whether the queue is still actively receiving work.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Low-context pending</div>
            <div class="detail-value"><?= number_format((int)($totals['low_context_rows'] ?? 0)) ?></div>
            <div class="muted">Low-context backlog usually needs routing discipline, not just more submissions.</div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Source posture</h2>
            <p class="section-shell-copy">Android feeds are tracked separately from the generic reservoir so the queue can be steered toward governed APK intake instead of broad discovery noise.</p>
        </div>
    </div>
    <div class="health-tiles">
        <div class="health-tile">
            <div class="health-tile-label">Android feed rows</div>
            <div class="health-tile-value"><?= number_format((int)($operator['android_pending_rows'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Android feed sources</div>
            <div class="health-tile-value"><?= number_format((int)($operator['android_source_count'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Generic reservoir rows</div>
            <div class="health-tile-value"><?= number_format((int)($operator['generic_reservoir_pending_rows'] ?? 0)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Generic reservoir sources</div>
            <div class="health-tile-value"><?= number_format((int)($operator['generic_reservoir_source_count'] ?? 0)) ?></div>
        </div>
    </div>
    <?php if (($operator['top_android_source_value'] ?? null) !== null): ?>
        <div class="notice info" style="margin-top: 14px;">
            Android intake focus: <?= h(db_ingest_friendly_source_label((string)$operator['top_android_source_value'])) ?> (<?= number_format((int)($operator['top_android_source_rows'] ?? 0)) ?>)
            <?php if (($operator['top_generic_reservoir_value'] ?? null) !== null): ?>
                · Generic reservoir: <?= h(db_ingest_friendly_source_label((string)$operator['top_generic_reservoir_value'])) ?> (<?= number_format((int)($operator['top_generic_reservoir_rows'] ?? 0)) ?>)
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Cleanup pressure</h2>
            <p class="section-shell-copy">Recovery pressure that can block clean intake and queue decisions.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Done rows ready to purge</div>
            <div class="detail-value"><?= number_format((int)($cleanup['done_rows'] ?? 0)) ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Pending rows already terminal</div>
            <div class="detail-value"><?= ($cleanup['pending_terminal_rows'] ?? null) === null ? 'unavailable' : number_format((int)$cleanup['pending_terminal_rows']) ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Stale PROCESSING rows</div>
            <div class="detail-value"><?= ($cleanup['stale_processing_rows'] ?? null) === null ? 'unavailable' : number_format((int)$cleanup['stale_processing_rows']) ?></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Failed rows ready for review</div>
            <div class="detail-value"><?= number_format((int)($cleanup['failed_rows'] ?? 0)) ?></div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Workload lanes</h2>
            <p class="section-shell-copy">This is the routing surface. If one lane dominates, intake should adapt before new artifacts are queued into the same path.</p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Lane</th>
                    <th>Surface</th>
                    <th>Platform</th>
                    <th>Policy</th>
                    <th>Pending</th>
                    <th>Processing</th>
                    <th>Failed</th>
                    <th>Low context</th>
                    <th>Hints</th>
                    <th>Oldest pending</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lanes === []): ?>
                    <tr><td colspan="10" class="muted">No workload lanes available.</td></tr>
                <?php else: ?>
                    <?php foreach ($lanes as $lane): ?>
                        <?php
                        $hints = [];
                        if ((int)($lane['is_android_focused'] ?? 0) === 1) {
                            $hints[] = 'android';
                        }
                        if ((int)($lane['is_generic_backlog'] ?? 0) === 1) {
                            $hints[] = 'generic';
                        }
                        if ((int)($lane['keep_separate_from_lamda'] ?? 0) === 1) {
                            $hints[] = 'separate';
                        }
                        if ((int)($lane['android_apk_hint_rows'] ?? 0) > 0) {
                            $hints[] = 'apk=' . number_format((int)$lane['android_apk_hint_rows']);
                        }
                        if ((int)($lane['windows_pe_hint_rows'] ?? 0) > 0) {
                            $hints[] = 'pe=' . number_format((int)$lane['windows_pe_hint_rows']);
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= h((string)($lane['display_name'] ?? '')) ?></strong>
                                <div class="muted mono"><?= h((string)($lane['workload_lane'] ?? '')) ?></div>
                                <div style="margin-top: 8px;">
                                    <a class="table-link" href="<?= h(page_url('ingest_backlog', ['limit' => $limit, 'preview' => $previewLimit, 'lane' => (string)($lane['workload_lane'] ?? '')])) ?>">Focus lane</a>
                                </div>
                            </td>
                            <td><?= h((string)($lane['operator_surface'] ?? '')) ?></td>
                            <td><?= h((string)($lane['intended_platform_default'] ?? '')) ?></td>
                            <td>
                                <div><?= h((string)($lane['queue_policy_default'] ?? '')) ?></div>
                                <div class="muted"><?= h((string)($lane['context_level_expectation'] ?? '')) ?></div>
                            </td>
                            <td><?= number_format((int)($lane['pending_rows'] ?? 0)) ?></td>
                            <td><?= number_format((int)($lane['processing_rows'] ?? 0)) ?></td>
                            <td><?= number_format((int)($lane['failed_rows'] ?? 0)) ?></td>
                            <td><?= number_format((int)($lane['low_context_rows'] ?? 0)) ?></td>
                            <td><?= h($hints === [] ? '--' : implode(' | ', $hints)) ?></td>
                            <td><?= h(fmt_utc_display((string)($lane['oldest_pending_at_utc'] ?? ''), $displayTimeFormat)) ?: '--' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Pending queue preview</h2>
            <p class="section-shell-copy">Bounded web-side preview of pending rows so you can confirm the active queue shape without dropping back into the terminal menu.</p>
        </div>
        <div class="flow-inline">
            <a class="btn" href="<?= h(page_url('ingest_backlog', ['limit' => $limit, 'preview' => 10, 'source' => $sourceFilter, 'lane' => $laneFilter])) ?>">Preview 10</a>
            <a class="btn" href="<?= h(page_url('ingest_backlog', ['limit' => $limit, 'preview' => 25, 'source' => $sourceFilter, 'lane' => $laneFilter])) ?>">Preview 25</a>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Hash</th>
                    <th>Source</th>
                    <th>Created UTC</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($previewRows === []): ?>
                    <tr><td colspan="5" class="muted">Queue is empty.</td></tr>
                <?php else: ?>
                    <?php foreach ($previewRows as $row): ?>
                        <?php
                        $hashValue = (string)($row['artifact_hash_value'] ?? '');
                        $hashDisplay = $hashValue;
                        if (strlen($hashValue) > 20) {
                            $hashDisplay = substr($hashValue, 0, 8) . '...' . substr($hashValue, -6);
                        }
                        ?>
                        <tr>
                            <td><?= number_format((int)($row['ingest_id'] ?? 0)) ?></td>
                            <td><?= h((string)($row['artifact_hash_type'] ?? '--')) ?></td>
                            <td class="mono"><?= h($hashDisplay) ?></td>
                            <td>
                                <div><?= h((string)($row['artifact_source'] ?? '')) ?></div>
                                <?php if (trim((string)($row['workload_lane'] ?? '')) !== ''): ?>
                                    <div class="muted"><?= h((string)$row['workload_lane']) ?></div>
                                <?php endif; ?>
                                <div style="margin-top: 8px;">
                                    <a class="table-link" href="<?= h(page_url('ingest_backlog', ['limit' => $limit, 'preview' => $previewLimit, 'source' => (string)($row['artifact_source'] ?? ''), 'lane' => (string)($row['workload_lane'] ?? '')])) ?>">Slice to this backlog</a>
                                </div>
                            </td>
                            <td><?= h(fmt_utc_display((string)($row['record_created_at_utc'] ?? ''), $displayTimeFormat)) ?: '--' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Batches carrying backlog</h2>
            <p class="section-shell-copy">Batch pressure is where intake debt becomes operational debt. This list is intentionally bounded.</p>
        </div>
        <div class="flow-inline">
            <a class="btn" href="<?= h(page_url('ingest_backlog', ['limit' => 20])) ?>">Top 20</a>
            <a class="btn" href="<?= h(page_url('ingest_backlog', ['limit' => 50])) ?>">Top 50</a>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Lane</th>
                    <th>Platform</th>
                    <th>Pending</th>
                    <th>Processing</th>
                    <th>Failed</th>
                    <th>Low context</th>
                    <th>First seen</th>
                    <th>Oldest pending</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($batches === []): ?>
                    <tr><td colspan="9" class="muted">No active ingest batches found.</td></tr>
                <?php else: ?>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td>
                                <strong><?= h((string)($batch['batch_label'] ?? '')) ?></strong>
                                <div class="muted mono">#<?= h((string)($batch['ingest_batch_id'] ?? '')) ?></div>
                                <div style="margin-top: 8px;">
                                    <a class="table-link" href="<?= h(page_url('ingest_backlog', ['limit' => $limit, 'preview' => $previewLimit, 'source' => (string)($batch['batch_label'] ?? ''), 'lane' => (string)($batch['workload_lane'] ?? '')])) ?>">Open matching slice</a>
                                </div>
                            </td>
                            <td><?= h((string)($batch['workload_lane'] ?? '')) ?></td>
                            <td>
                                <div><?= h((string)($batch['intended_platform'] ?? '')) ?></div>
                                <div class="muted"><?= h((string)($batch['queue_policy'] ?? '')) ?> / <?= h((string)($batch['context_level'] ?? '')) ?></div>
                            </td>
                            <td><?= number_format((int)($batch['pending_rows'] ?? 0)) ?></td>
                            <td><?= number_format((int)($batch['processing_rows'] ?? 0)) ?></td>
                            <td><?= number_format((int)($batch['failed_rows'] ?? 0)) ?></td>
                            <td><?= number_format((int)($batch['low_context_rows'] ?? 0)) ?></td>
                            <td><?= h(fmt_utc_display((string)($batch['first_seen_in_queue_at_utc'] ?? ''), $displayTimeFormat)) ?: '--' ?></td>
                            <td><?= h(fmt_utc_display((string)($batch['oldest_pending_at_utc'] ?? ''), $displayTimeFormat)) ?: '--' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Pending source mix</h2>
            <p class="section-shell-copy">This moved to its own page so the main backlog view stays readable when source-heavy queues expand.</p>
        </div>
        <div class="flow-inline">
            <a class="btn" href="<?= h(page_url('pending_source_mix', ['lane' => $laneFilter, 'limit' => 50])) ?>">Open Pending Source Mix</a>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Listed sources</div>
            <div class="detail-value"><?= number_format(count($sources)) ?></div>
            <div class="muted" style="margin-top:8px;">Open the dedicated page for the full ordered source list and source-level backlog focus links.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Android feeds first</div>
            <div class="detail-value"><?= number_format(count($androidSources)) ?></div>
            <div class="muted" style="margin-top:8px;">Governed APK-oriented feeds stay ahead of the generic reservoir so the operator can separate policy lanes quickly.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Generic reservoir</div>
            <div class="detail-value"><?= number_format(count($genericSources)) ?></div>
            <div class="muted" style="margin-top:8px;">Broad discovery backlog remains visible, but no longer dominates the main backlog page.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Other feeds</div>
            <div class="detail-value"><?= number_format(count($otherSources)) ?></div>
            <div class="muted" style="margin-top:8px;">LAMDA and other non-Android sources remain accessible on the dedicated page.</div>
        </div>
    </div>
</section>
