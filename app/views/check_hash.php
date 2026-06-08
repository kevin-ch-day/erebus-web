<?php
// app/views/check_hash.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/artifact_sources.php';

$title = 'Check Hash';
$prefillHash = trim((string)($_GET['hash'] ?? ''));
$result = (string)($_GET['result'] ?? '');
$showIntake = $result === 'not_found';
$lookupEndpoint = api_url('artifact_hash_lookup.php');
$ingestEndpoint = api_url('artifact_ingest_queue.php');
$sampleBaseUrl = page_url('sample');
$backlogBaseUrl = page_url('ingest_backlog');
$submitArtifactUrl = page_url('submit_artifact');
?>

<div id="check-hash-page" style="display:none;"
     data-lookup-endpoint="<?= h($lookupEndpoint) ?>"
     data-ingest-endpoint="<?= h($ingestEndpoint) ?>"
     data-sample-base="<?= h($sampleBaseUrl) ?>"
     data-backlog-base="<?= h($backlogBaseUrl) ?>"></div>

<section class="page-hero">
    <div class="page-hero-media">
        <img src="<?= h(app_url('assets/img/artifact_hash_lookup.png')) ?>"
            alt="Artifact hash lookup" />
    </div>
    <div class="page-hero-body">
        <div class="eyebrow">Artifact Intake</div>
        <div class="page-kicker">Lookup before submission</div>
        <h1 class="page-hero-title">Check Hash</h1>
        <p class="page-hero-lede muted">
            Use this page to decide whether an artifact is already known, already waiting in intake,
            or still needs to be queued. Start here before adding new backlog.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="#hash-lookup">Check a hash</a>
            <a class="btn" href="<?= h($backlogBaseUrl) ?>">Open Ingest Backlog</a>
            <a class="btn" href="<?= h($submitArtifactUrl) ?>">Bulk Submit Artifact</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Operator model</h2>
        <p>This page should answer one question quickly: do we already have this artifact, or should it be routed into intake?</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">If found</div>
                <div class="hero-metric-value">Open sample</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If queued</div>
                <div class="hero-metric-value">Open backlog</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If missing</div>
                <div class="hero-metric-value">Queue intake</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Primary guardrail</div>
                <div class="hero-metric-value">No duplicate work</div>
            </div>
        </div>
    </aside>
</section>

<?php if ($prefillHash !== ''): ?>
    <div class="detail-card" style="margin-bottom: 16px;">
        <div class="detail-card-title">Artifact hash</div>
        <div class="detail-row">
            <div class="detail-label">Prefilled</div>
            <div class="detail-value mono"><?= h($prefillHash) ?></div>
        </div>
        <div class="muted">Hash value is preloaded to reduce copy errors.</div>
    </div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Decision path</h2>
            <p class="section-shell-copy">This page is most useful when it reduces duplicate effort. Use the result state to decide what happens next.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Known artifact</div>
            <div class="muted">If the hash already resolves to a catalog record, stop here and pivot into the sample instead of re-queuing it.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Already in intake</div>
            <div class="muted">If the hash is already queued, review intake pressure and avoid creating another copy of the same work.</div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Still missing</div>
            <div class="muted">If nothing matches, capture just enough metadata to route the artifact cleanly into the queue.</div>
        </div>
    </div>
</section>

<section class="section-shell" id="hash-lookup">
    <div class="detail-card">
        <div class="check-hash-header">
            <div class="detail-card-title">Lookup a single artifact hash</div>
            <div class="check-hash-actions" id="hash-actions" style="display:none;"></div>
        </div>
        <div class="muted check-hash-preview-row" id="hash-preview-row" style="margin-top: 6px;">
            <a class="check-hash-link" href="<?= h(page_url('check_hash', ['result' => 'not_found'])) ?>">Preview the no-match intake path</a>
        </div>
        <div class="notice info" id="hash-result" style="display:none; margin-top: 10px;"></div>
        <div class="notice error" id="hash-error" style="display:none; margin-top: 10px;"></div>
        <div class="detail-grid" id="hash-detail-grid" style="display:none; margin-top: 12px;"></div>
        <div class="filters" style="margin: 12px 0 0;">
            <div class="filter-field" style="min-width: 280px;">
                <label for="hash-input">Artifact hash</label>
                <input id="hash-input" type="search" placeholder="MD5, SHA-1, or SHA-256"
                       value="<?= h($prefillHash) ?>" />
                <div class="muted" style="margin-top: 4px;">
                    Detected: <span id="hash-type-hint">--</span>
                </div>
                <div class="muted">Expected length: 32 / 40 / 64 hex chars.</div>
                <div class="muted">Paste the strongest hash you have. SHA-256 is preferred when available.</div>
            </div>
            <div class="filter-field" style="min-width: 140px;">
                <label>&nbsp;</label>
                <button class="btn" type="button" id="hash-check-btn" disabled>Check hash</button>
            </div>
        </div>
        <div class="muted" style="margin-top: 8px;">
            Supported lookup types: MD5, SHA-1, and SHA-256.
        </div>
        <div class="flow-inline" style="margin-top: 12px;">
            <a class="btn" href="<?= h($backlogBaseUrl) ?>">Review intake backlog</a>
            <a class="btn" href="<?= h($submitArtifactUrl) ?>">Switch to bulk intake</a>
        </div>
    </div>
</section>

<section class="section-shell" id="artifact-intake" style="<?= $showIntake ? '' : 'display:none;' ?>">
    <div class="detail-card">
        <div class="detail-card-title">Queue a new artifact</div>
        <div class="muted">
            Use this only when the lookup result stays empty. Keep the metadata lightweight and operator-useful so the intake lane can process it without extra cleanup.
        </div>
        <div class="notice info" id="artifact-queue-status" style="display:none; margin-top: 10px;"></div>
        <div class="notice error" id="artifact-queue-error" style="display:none; margin-top: 10px;"></div>
        <div class="filters" style="margin: 12px 0 0;">
            <div class="filter-field" style="min-width: 260px;">
                <label for="artifact-name">Artifact name</label>
                <input id="artifact-name" type="text" placeholder="Sample label or operator note" />
                </div>
                <div class="filter-field" style="min-width: 220px;">
                    <label for="artifact-family">Artifact family</label>
                    <input id="artifact-family" type="text" placeholder="Known family, if any" />
                </div>
                <div class="filter-field" style="min-width: 320px;">
                    <label for="artifact-hash">Artifact hash</label>
                    <input id="artifact-hash" type="text" placeholder="md5, sha1, or sha256..."
                           value="<?= h($prefillHash) ?>" />
                    <div class="muted" style="margin-top: 4px;">
                        Detected: <span id="artifact-hash-hint">--</span>
                    </div>
                    <div class="muted">Expected length: 32 / 40 / 64 hex chars.</div>
                </div>
                <div class="filter-field" style="min-width: 200px;">
                    <label for="artifact-category">Artifact category</label>
                    <input id="artifact-category" type="text" placeholder="Android, Windows, IoT, ..." />
                </div>
                <div class="filter-field" style="min-width: 200px;">
                    <label for="artifact-subtype">Artifact subtype</label>
                    <input id="artifact-subtype" type="text" placeholder="APK, DLL, firmware, ..." />
                </div>
                <div class="filter-field" style="min-width: 220px;">
                    <label for="artifact-source">Artifact source</label>
                    <select id="artifact-source">
                        <?= render_artifact_source_options() ?>
                    </select>
                </div>
                <div class="filter-field" style="min-width: 220px;">
                    <label for="artifact-source-other">Source detail (optional)</label>
                    <input id="artifact-source-other" type="text" placeholder="Other source" />
                </div>
                <div class="filter-field" style="min-width: 160px;">
                    <label>&nbsp;</label>
                    <button class="btn btn-primary" type="button" id="artifact-queue-btn" disabled>Queue artifact</button>
                </div>
            </div>
            <div class="muted" style="margin-top: 8px;">
                Provide the source that best explains where this hash came from. Use source detail only when the source needs clarification.
            </div>
            <div class="flow-inline" style="margin-top: 12px;">
                <a class="btn" href="<?= h($backlogBaseUrl) ?>">Review intake pressure first</a>
                <a class="btn" href="<?= h($submitArtifactUrl) ?>">Need multiple rows? Use bulk submit</a>
            </div>
        </div>
</section>
