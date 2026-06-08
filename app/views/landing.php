<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';

$title = APP_NAME . ' - Landing';
$version = defined('APP_VERSION') ? (string)APP_VERSION : 'v1';
$env = defined('APP_ENV') ? (string)APP_ENV : '';
$tz = tz_current_id();

$landingApiUrl = api_url('landing_snapshot.php');
?>

<div id="landing-page"
     data-endpoint="<?= h($landingApiUrl) ?>"
     data-version="<?= h($version) ?>"
     data-environment="<?= h($env) ?>"
     data-timezone="<?= h($tz) ?>"></div>

<section class="landing-command">
    <div class="landing-command-main">
        <div class="eyebrow">Operator Command Surface</div>
        <div class="landing-command-kicker">Live pressure, repair lanes, and operator readiness in one place</div>
        <h1 class="landing-command-title"><?= h(APP_NAME) ?> Control Deck</h1>
        <p class="landing-command-lede">
            See live pressure first, then move straight into the queue or governed curation surface that actually needs attention.
        </p>
        <div class="landing-command-actions">
            <a class="btn btn-primary" href="<?= h(page_url('health')) ?>">Open VT &amp; Pipeline Health</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => 'android'])) ?>">Open Family Repair Queue</a>
            <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Open Type Benchmark</a>
        </div>
        <div class="landing-command-strip">
            <div class="landing-inline-metric surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-inline-label">Version</div>
                <div class="landing-inline-value"><?= h($version) ?></div>
            </div>
            <?php if ($env !== ''): ?>
            <div class="landing-inline-metric surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-inline-label">Environment</div>
                <div class="landing-inline-value"><?= h($env) ?></div>
            </div>
            <?php endif; ?>
            <div class="landing-inline-metric surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-inline-label">Display timezone</div>
                <div class="landing-inline-value"><?= h($tz) ?></div>
            </div>
        </div>
    </div>
    <aside class="landing-command-side surface-panel surface-panel-soft">
        <div class="landing-panel-heading">Live control snapshot</div>
        <div class="landing-snapshot-grid">
            <div class="landing-snapshot-tile surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-snapshot-label">VT eligible now</div>
                <div class="landing-snapshot-value" id="landing-metric-eligible">--</div>
                <div class="landing-snapshot-note muted" id="landing-metric-eligible-note">Loading health...</div>
            </div>
            <div class="landing-snapshot-tile surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-snapshot-label">Retry / stale pressure</div>
                <div class="landing-snapshot-value" id="landing-metric-retry">--</div>
                <div class="landing-snapshot-note muted" id="landing-metric-retry-note">Loading health...</div>
            </div>
            <div class="landing-snapshot-tile surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-snapshot-label">Family mismatch rows</div>
                <div class="landing-snapshot-value" id="landing-metric-family-mismatch">--</div>
                <div class="landing-snapshot-note muted" id="landing-metric-family-note">Loading taxonomy...</div>
            </div>
            <div class="landing-snapshot-tile surface-panel surface-panel-soft surface-panel-compact">
                <div class="landing-snapshot-label">Clean benchmark rows</div>
                <div class="landing-snapshot-value" id="landing-metric-stack-gaps">--</div>
                <div class="landing-snapshot-note muted" id="landing-metric-stack-note">Loading dataset readiness...</div>
            </div>
        </div>
        <div class="landing-panel-heading">Operator recommendation</div>
        <div class="landing-priority-notice surface-panel surface-panel-compact" id="landing-priority-notice">
            Loading recommendation...
        </div>
    </aside>
</section>

<section class="landing-top-grid">
    <div class="landing-top-main">
        <section class="section-shell landing-section-compact">
            <div class="section-shell-header">
                <div>
                    <h2 class="section-shell-title">Primary Lanes</h2>
                    <p class="muted">The three highest-value operator entry points. These should stay above the fold and match the sidebar priority lanes.</p>
                </div>
            </div>
            <div class="landing-lane-grid">
                <div class="detail-card landing-lane-card">
                    <div class="landing-lane-title">VT &amp; Pipeline</div>
                    <div class="landing-lane-metric" id="landing-health-metric">--</div>
                    <p class="muted" id="landing-health-summary">Loading pipeline pressure...</p>
                    <div class="landing-chip-row" id="landing-health-chips">
                        <span class="landing-chip">Loading</span>
                    </div>
                    <a class="btn btn-primary" href="<?= h(page_url('health')) ?>">Open Health</a>
                </div>
                <div class="detail-card landing-lane-card">
                    <div class="landing-lane-title">Family Taxonomy Repair</div>
                    <div class="landing-lane-metric" id="landing-family-metric">--</div>
                    <p class="muted" id="landing-family-summary">Loading family-label debt...</p>
                    <div class="landing-chip-row" id="landing-family-chips">
                        <span class="landing-chip">Loading</span>
                    </div>
                    <div class="landing-actions">
                        <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_queue', ['platform' => 'android'])) ?>">Open Repair Queue</a>
                        <a class="btn" href="<?= h(page_url('family_taxonomy_gaps', ['platform' => 'android'])) ?>">Open Coverage &amp; Gaps</a>
                    </div>
                </div>
                <div class="detail-card landing-lane-card">
                <div class="landing-lane-title">Dataset Curation</div>
                    <div class="landing-lane-metric" id="landing-stack-metric">--</div>
                    <p class="muted" id="landing-stack-summary">Loading governed dataset metrics...</p>
                    <div class="landing-chip-row" id="landing-stack-chips">
                        <span class="landing-chip">Loading</span>
                    </div>
                    <a class="btn btn-primary" href="<?= h(page_url('type_benchmark')) ?>">Open Type Benchmark</a>
                </div>
            </div>
        </section>
    </div>

    <aside class="landing-top-side">
        <section class="section-shell landing-section-compact landing-hotspot-shell">
            <div class="section-shell-header">
                <div>
                    <h2 class="section-shell-title">Conflict Hotspots</h2>
                    <p class="muted">Largest family-label disputes. This is the real analyst backlog, not just navigation.</p>
                </div>
            </div>
            <div class="landing-hotspot-grid" id="landing-hotspots">
                <div class="detail-card">
                    <div class="landing-hotspot-title">Loading</div>
                    <div class="muted">Building hotspot inventory...</div>
                </div>
            </div>
        </section>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Secondary Paths</h2>
            <p class="muted">Useful supporting surfaces, kept smaller so they do not compete with the live pressure lanes above.</p>
        </div>
    </div>
    <div class="landing-map-grid">
        <div class="detail-card landing-map-card landing-map-card-dataset">
            <div class="landing-map-tag">Governed labels</div>
            <div class="landing-map-title">Dataset Curation</div>
            <p class="muted">Use readiness, type benchmark, and label surfaces when you need a governed view of what is trainable versus still held, unresolved, or projection-only.</p>
            <div class="landing-actions">
                <a class="btn" href="<?= h(page_url('dataset_readiness')) ?>">Readiness</a>
                <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Type Benchmark</a>
                <a class="btn" href="<?= h(page_url('label_surfaces')) ?>">Label Surfaces</a>
            </div>
        </div>
        <div class="detail-card landing-map-card landing-map-card-permissions">
            <div class="landing-map-tag">Evidence workflow</div>
            <div class="landing-map-title">Permission Intel</div>
            <p class="muted">Work from overview into triage and review after state is clear. Keep queue/diagnostic residue separate from live evidence and governed rows.</p>
            <div class="landing-actions">
                <a class="btn" href="<?= h(page_url('permissions_overview')) ?>">Overview</a>
                <a class="btn" href="<?= h(page_url('permissions_triage')) ?>">Triage</a>
                <a class="btn" href="<?= h(page_url('permissions_drift')) ?>">Drift</a>
            </div>
        </div>
        <div class="detail-card landing-map-card landing-map-card-admin">
            <div class="landing-map-tag">Admin detail</div>
            <div class="landing-map-title">Platform &amp; Admin</div>
            <p class="muted">Use settings and admin-only audit surfaces when you need deployment, schema, or shell diagnostics. These are supporting tools, not primary curation lanes.</p>
            <div class="landing-actions">
                <a class="btn" href="<?= h(page_url('settings')) ?>">Settings</a>
                <a class="btn" href="<?= h(page_url('stack_audit')) ?>">Platform Audit</a>
                <a class="btn" href="<?= h(page_url('schema_inventory')) ?>">Schema Inventory</a>
            </div>
        </div>
        <div class="detail-card landing-map-card landing-map-card-samples">
            <div class="landing-map-tag">Catalog edge</div>
            <div class="landing-map-title">Samples &amp; Intake</div>
            <p class="muted">Inspect sample rows in the catalog, then move into artifact intake when you need to confirm a hash, inspect queue pressure, or submit new work.</p>
            <div class="landing-actions">
                <a class="btn" href="<?= h(page_url('samples')) ?>">Samples</a>
                <a class="btn" href="<?= h(page_url('check_hash')) ?>">Check Hash</a>
                <a class="btn" href="<?= h(page_url('ingest_backlog')) ?>">Ingest Backlog</a>
                <a class="btn" href="<?= h(page_url('submit_artifact')) ?>">Submit Artifact</a>
            </div>
        </div>
    </div>
</section>
