<?php
// app/views/health.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../database/db_func.php';

$title = "VT & Pipeline Health";

$healthApiUrl = api_url('health.php');
$healthDiagnosticsApiUrl = api_url('health.php') . '?include_diagnostics=1';
$healthLight = db_health(false);
$systemControl = is_array($healthLight['system_control'] ?? null) ? $healthLight['system_control'] : [];
$metrics = is_array($healthLight['metrics'] ?? null) ? $healthLight['metrics'] : [];
$catalogs = is_array($healthLight['catalogs'] ?? null) ? $healthLight['catalogs'] : [];
$schemaHeads = is_array($healthLight['schema_heads'] ?? null) ? $healthLight['schema_heads'] : [];
$vtKeyStatus = db_vt_key_status_snapshot();
$vtKeyPosture = is_array($vtKeyStatus['key_posture'] ?? null) ? $vtKeyStatus['key_posture'] : [];
$vtHold = is_array($vtKeyStatus['hold'] ?? null) ? $vtKeyStatus['hold'] : [];
$vtKeys = is_array($vtKeyStatus['keys'] ?? null) ? $vtKeyStatus['keys'] : [];

$fmtValue = static function ($value, string $fallback = '--'): string {
    if ($value === null || $value === '') {
        return $fallback;
    }
    return (string)$value;
};

$fmtInt = static function ($value, string $fallback = '--'): string {
    if ($value === null || $value === '') {
        return $fallback;
    }
    if (is_numeric($value)) {
        return number_format((int)$value);
    }
    return (string)$value;
};

$fmtBool = static function ($value): string {
    return ((int)$value) === 1 ? 'yes' : 'no';
};

$fmtUtcDisplay = static function ($value, string $fallback = '--'): string {
    $formatted = fmt_utc_display($value, 'M d Y g:i A');
    if ($formatted === '') {
        return $fallback;
    }
    return $formatted . ' ' . tz_current_id();
};

$fmtKeyStatus = static function ($value): string {
    $status = strtolower(trim((string)$value));
    return match ($status) {
        'eligible' => 'Eligible',
        'cooling' => 'Cooling',
        'quota_blocked' => 'Quota blocked',
        'leased' => 'Leased',
        'disabled' => 'Disabled',
        'hidden' => 'Hidden',
        default => $status === '' ? '--' : ucwords(str_replace('_', ' ', $status)),
    };
};

$holdUntil = (string)($systemControl['hold_until_utc'] ?? '');
$holdTs = $holdUntil !== '' ? strtotime($holdUntil . ' UTC') : false;
$isHoldActive = $holdTs !== false && $holdTs > time();
$vtActiveHold = (bool)($vtHold['active_hold'] ?? false);
$eligibleNow = (int)($metrics['eligible_now'] ?? 0);
$processingNow = (int)($metrics['processing_now'] ?? 0);
$errorCount = (int)($metrics['error_count'] ?? 0);
$retryWaitCount = (int)($metrics['retry_wait_count'] ?? 0);
$staleClaims = (int)($metrics['stale_claims'] ?? 0);
$pendingLike = $retryWaitCount + $processingNow;
$reasonBreakdown = is_array($metrics['reason_breakdown'] ?? null) ? $metrics['reason_breakdown'] : [];
$headsMatch = (bool)($schemaHeads['heads_match'] ?? false);
$primaryHead = (string)($schemaHeads['primary_head'] ?? '');
$permissionIntelHead = (string)($schemaHeads['permission_intel_head'] ?? '');
$topReasonCode = isset($reasonBreakdown[0]['reason_code']) ? (string)$reasonBreakdown[0]['reason_code'] : '';

?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">VT State First</div>
        <div class="page-kicker">Pipeline control surface</div>
        <h1 class="page-hero-title">VT &amp; Pipeline Health</h1>
        <p class="page-hero-lede muted">
            Start here when VirusTotal enrichment is blocked, idle, unexpectedly quiet, or producing workflow residue.
            DB logic is UTC; timestamps are displayed in your selected timezone.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('runs')) ?>">Open Run Ledger</a>
            <a class="btn" href="<?= h(page_url('ingest_backlog')) ?>">Open Ingest Backlog</a>
            <a class="btn" href="#vt-key-posture">Jump to Key Posture</a>
            <a class="btn" href="<?= h(page_url('permissions_overview')) ?>">Permission Overview</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Operator model</h2>
        <p>Use this page to answer three questions before touching Permission Intel: is VT held, is work eligible, and is scheduler residue masking the real problem?</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Primary move</div>
                <div class="hero-metric-value">State</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If blocked</div>
                <div class="hero-metric-value">Keys / holds</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If quiet</div>
                <div class="hero-metric-value">Eligibility</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">If drifting</div>
                <div class="hero-metric-value">Guards</div>
            </div>
        </div>
    </div>
</section>
<div id="health-page" style="display:none;"
     data-endpoint="<?= h($healthApiUrl) ?>"
     data-diagnostics-endpoint="<?= h($healthDiagnosticsApiUrl) ?>"
     data-samples-base="<?= h(page_url('samples')) ?>"
     data-refresh-seconds="<?= (int)DASHBOARD_REFRESH_SECONDS ?>"
     data-diagnostics-refresh-seconds="300"></div>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Current blockers</h2>
            <p class="section-shell-copy">Promote the issues that are actually stopping clean interpretation: VT hold state, queue pressure, key capacity, and catalog split drift.</p>
        </div>
    </div>
    <div class="detail-grid" id="health-blockers-grid">
        <div class="detail-card">
            <div class="detail-card-title">VT hold state</div>
            <div class="detail-value"><?= h($isHoldActive ? 'Blocked' : 'Clear') ?></div>
            <div class="muted" style="margin-top:8px;">
                <?= h($isHoldActive ? 'Hold until ' . $fmtValue($holdUntil) . ' | reason ' . $fmtValue($systemControl['hold_reason_code'] ?? null) : 'No active hold is blocking enrichment right now.') ?>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Scheduler pressure</div>
            <div class="detail-value"><?= h($fmtInt($pendingLike > 0 ? $pendingLike : $eligibleNow)) ?></div>
            <div class="muted" style="margin-top:8px;">
                <?= h($pendingLike > 0 ? 'Retry wait + processing pressure is visible. Check backlog before assuming the pipeline is quiet.' : 'No queue-like residue is visible here. Eligible now: ' . $fmtInt($eligibleNow) . '.') ?>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">VT capacity now</div>
            <div class="detail-value"><?= h($fmtInt($vtKeyPosture['eligible_remaining_quota'] ?? null)) ?></div>
            <div class="muted" style="margin-top:8px;">
                <?= h('Eligible keys: ' . $fmtInt($vtKeyPosture['eligible_keys'] ?? null) . ' | Cooling: ' . $fmtInt($vtKeyPosture['cooling_keys'] ?? null) . ' | Quota blocked: ' . $fmtInt($vtKeyPosture['quota_blocked_keys'] ?? null)) ?>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Catalog split</div>
            <div class="detail-value"><?= h($headsMatch ? 'Aligned' : 'Diverged') ?></div>
            <div class="muted" style="margin-top:8px;">
                <?= h('Primary head ' . ($primaryHead !== '' ? $primaryHead : '--') . ' | PI head ' . ($permissionIntelHead !== '' ? $permissionIntelHead : '--')) ?>
            </div>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">VT state</h2>
            <p class="section-shell-copy">This is the live status pane. Treat it as the web equivalent of “can enrichment move right now?”</p>
        </div>
        <div class="flow-inline">
            <?php if (defined('FEATURE_PHASE3_OPS') && FEATURE_PHASE3_OPS): ?>
                <a class="btn" href="<?= h(page_url('vt_key_controls')) ?>">VT Key Controls</a>
            <?php endif; ?>
            <a class="btn" href="#vt-key-posture">VT Key Posture</a>
            <a class="btn" href="<?= h(page_url('permissions_drift')) ?>">Drift Details</a>
            <a class="btn" href="<?= h(page_url('vt_snapshot_inventory')) ?>">VT Snapshot Inventory</a>
        </div>
    </div>
    <div class="health-stoplight <?= $isHoldActive ? 'health-stoplight-hold' : 'health-stoplight-ok' ?>" id="health-stoplight">
        <div class="health-stoplight-title"><?= h($isHoldActive ? 'VT HOLD ACTIVE' : 'VT ENRICHMENT ALLOWED') ?></div>
        <div class="health-stoplight-sub muted" id="health-stoplight-sub"><?= h($isHoldActive ? 'Hold until: ' . $fmtValue($holdUntil) . ' | reason: ' . $fmtValue($systemControl['hold_reason_code'] ?? null) : 'No active holds.') ?></div>
    </div>
</section>

<section class="section-shell" id="vt-key-posture">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">VT key posture</h2>
            <p class="section-shell-copy">This page now owns the read-only VT key surface. Use key controls only when you need a write-capable operator action.</p>
        </div>
        <div class="flow-inline">
            <a class="btn" href="<?= h(page_url('vt_ops_dashboard')) ?>">Open VT Ops Dashboard</a>
            <?php if (defined('FEATURE_PHASE3_OPS') && FEATURE_PHASE3_OPS): ?>
                <a class="btn" href="<?= h(page_url('vt_key_controls')) ?>">Open VT Key Controls</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Key summary</div>
            <div class="detail-row"><div class="detail-label">Total keys</div><div class="detail-value" id="vt-key-total"><?= h($fmtInt($vtKeyPosture['total_keys'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Enabled + visible</div><div class="detail-value" id="vt-key-enabled-visible"><?= h($fmtInt($vtKeyPosture['enabled_visible_keys'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Eligible keys</div><div class="detail-value" id="vt-key-eligible"><?= h($fmtInt($vtKeyPosture['eligible_keys'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Cooling keys</div><div class="detail-value" id="vt-key-cooling"><?= h($fmtInt($vtKeyPosture['cooling_keys'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Quota blocked</div><div class="detail-value" id="vt-key-quota-blocked"><?= h($fmtInt($vtKeyPosture['quota_blocked_keys'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Leased keys</div><div class="detail-value" id="vt-key-leased"><?= h($fmtInt($vtKeyPosture['leased_keys'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Total remaining quota</div><div class="detail-value" id="vt-key-total-remaining"><?= h($fmtInt($vtKeyPosture['total_remaining_quota'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Eligible remaining</div><div class="detail-value" id="vt-key-eligible-remaining"><?= h($fmtInt($vtKeyPosture['eligible_remaining_quota'] ?? null)) ?></div></div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Hold &amp; last 429</div>
            <div class="detail-row"><div class="detail-label">Current hold</div><div class="detail-value" id="vt-key-hold-reason"><?= h($vtActiveHold ? $fmtValue($vtHold['hold_reason_code'] ?? null) : 'No active hold') ?></div></div>
            <div class="detail-row"><div class="detail-label" id="vt-key-hold-until-label"><?= h($vtActiveHold ? 'Hold until' : 'Last hold expired') ?></div><div class="detail-value" id="vt-key-hold-until"><?= h($vtActiveHold ? $fmtUtcDisplay($vtHold['hold_until_utc'] ?? null) : $fmtUtcDisplay($vtHold['hold_until_utc'] ?? null, '--')) ?></div></div>
            <div class="detail-row"><div class="detail-label">Last 429 key</div><div class="detail-value" id="vt-key-last-429-key"><?= h($fmtValue($vtHold['last_429_key_id'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Last 429 endpoint</div><div class="detail-value" id="vt-key-last-429-endpoint"><?= h($fmtValue($vtHold['last_429_endpoint'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Retry-after s</div><div class="detail-value" id="vt-key-last-429-retry"><?= h($fmtInt($vtHold['last_429_retry_after_seconds'] ?? null)) ?></div></div>
            <div class="detail-row"><div class="detail-label">Supports leases</div><div class="detail-value" id="vt-key-supports-leases"><?= h(($vtKeyStatus['supports_leases'] ?? false) ? 'yes' : 'no') ?></div></div>
        </div>
    </div>
    <div class="table-scroll" style="margin-top: 16px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Status</th>
                    <th>Enabled</th>
                    <th>Visible</th>
                    <th>Daily quota</th>
                    <th>Remaining</th>
                    <th>Quota day (UTC)</th>
                    <th>Cooldown until (UTC)</th>
                    <th>Last 429 at (UTC)</th>
                    <th>Retry-after s</th>
                </tr>
            </thead>
            <tbody id="vt-key-body">
                <?php if ($vtKeys === []): ?>
                    <tr><td colspan="11" class="muted">No VT keys configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($vtKeys as $row): ?>
                        <tr>
                            <td><?= h('#' . $fmtValue($row['api_key_id'] ?? null) . ' • ' . $fmtValue($row['last6'] ?? null)) ?></td>
                            <td><?= h($fmtKeyStatus($row['operator_status'] ?? null)) ?></td>
                            <td><?= h($fmtBool($row['is_enabled'] ?? 0)) ?></td>
                            <td><?= h($fmtBool($row['is_visible'] ?? 0)) ?></td>
                            <td><?= h($fmtInt($row['daily_quota_limit'] ?? null)) ?></td>
                            <td><?= h($fmtInt($row['remaining_quota'] ?? null)) ?></td>
                            <td><?= h($fmtValue($row['quota_day_utc'] ?? null)) ?></td>
                            <td><?= h($fmtUtcDisplay($row['cooldown_until_utc'] ?? null)) ?></td>
                            <td><?= h($fmtUtcDisplay($row['last_429_at_utc'] ?? null)) ?></td>
                            <td><?= h($fmtInt($row['last_429_retry_after_seconds'] ?? null)) ?></td>
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
            <h2 class="section-shell-title">Scheduler pressure</h2>
            <p class="section-shell-copy">Use these counts to separate hold/pacing problems from retry backlog, stale claims, or simply no eligible work.</p>
        </div>
    </div>
    <div class="health-tiles">
        <div class="health-tile">
            <div class="health-tile-label">Eligible now</div>
            <div class="health-tile-value" id="tile-eligible"><?= h($fmtInt($eligibleNow)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Processing now</div>
            <div class="health-tile-value" id="tile-processing"><?= h($fmtInt($processingNow)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Error</div>
            <div class="health-tile-value" id="tile-error"><?= h($fmtInt($errorCount)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Retry wait</div>
            <div class="health-tile-value" id="tile-retry"><?= h($fmtInt($retryWaitCount)) ?></div>
        </div>
        <div class="health-tile">
            <div class="health-tile-label">Stale claims</div>
            <div class="health-tile-value" id="tile-stale"><?= h($fmtInt($staleClaims)) ?></div>
        </div>
    </div>
    <div class="health-reasons">
        <h3>Top reasons</h3>
        <ol class="health-reasons-list" id="health-reasons-list">
            <?php foreach (array_slice($reasonBreakdown, 0, 5) as $reason): ?>
                <li>
                    <a class="health-reason-link" href="<?= h(page_url('samples', ['reason' => (string)($reason['reason_code'] ?? 'UNKNOWN')])) ?>"><?= h((string)($reason['reason_code'] ?? 'UNKNOWN')) ?></a>
                    <span class="badge"><?= h($fmtInt($reason['count'] ?? null, '0')) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="muted" id="health-reasons-empty" style="<?= $reasonBreakdown === [] ? '' : 'display:none;' ?>">No reason codes yet.</div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Operator paths</h2>
            <p class="section-shell-copy">Use these only when the blocker cards and next-path notice are not already sufficient.</p>
        </div>
    </div>
    <details>
        <summary class="muted">Open operator path reference</summary>
        <div class="health-stage-grid" style="margin-top:16px;">
            <div class="health-stage surface-panel">
                <div class="health-stage-label">If VT is blocked</div>
                <strong>Stay on state and key truth first</strong>
                <div class="muted">Check hold state, key availability, and recent run errors first before blaming downstream analytics or queue quality.</div>
            </div>
            <div class="health-stage surface-panel">
                <div class="health-stage-label">If backlog is large</div>
                <strong>Review intake pressure before asking for more VT work</strong>
                <div class="muted">Open Ingest Backlog to confirm whether queued rows, processing residue, or recovery pressure are the real bottleneck.</div>
            </div>
            <div class="health-stage surface-panel">
                <div class="health-stage-label">If PI backlog is growing</div>
                <strong>Move into Permission Intel only after state is clear</strong>
                <div class="muted">Go from Overview to Triage to Review only after VT state and intake pressure are understood.</div>
            </div>
            <div class="health-stage surface-panel">
                <div class="health-stage-label">If counts drift</div>
                <strong>Use guards before manual cleanup</strong>
                <div class="muted">Use schema heads, rollup guards, and workflow debt here before trusting cross-surface comparisons.</div>
            </div>
        </div>
    </details>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Catalog routing</h2>
            <p class="section-shell-copy">Verify which MariaDB catalogs this web app is reading before interpreting Permission Intel counts.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Catalog map</div>
            <div class="detail-row">
                <div class="detail-label">Primary catalog</div>
                <div class="detail-value" id="catalog-primary"><?= h($fmtValue($catalogs['primary'] ?? null)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Permission Intel catalog</div>
                <div class="detail-value" id="catalog-permission-intel"><?= h($fmtValue($catalogs['permission_intel'] ?? null)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Split mode</div>
                <div class="detail-value" id="catalog-split-mode"><?= h(($catalogs['split_enabled'] ?? false) ? 'yes' : 'no') ?></div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Schema heads</div>
            <div class="detail-row">
                <div class="detail-label">Primary head</div>
                <div class="detail-value" id="schema-head-primary"><?= h($fmtValue($schemaHeads['primary_head'] ?? null)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">PI head</div>
                <div class="detail-value" id="schema-head-pi"><?= h($fmtValue($schemaHeads['permission_intel_head'] ?? null)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Heads match</div>
                <div class="detail-value" id="schema-head-match"><?= h(($schemaHeads['heads_match'] ?? false) ? 'yes' : 'no') ?></div>
            </div>
        </div>
    </div>
</section>

<section class="section-shell" id="advanced-diagnostics-section" style="display:none;">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Advanced diagnostics</h2>
        </div>
    </div>
    <details id="advanced-diagnostics-details">
        <summary class="muted">Diagnostics</summary>
        <div class="detail-grid" style="margin-top:16px;">
            <div class="detail-card" id="schema-guard-card" style="display:none;">
                <div class="detail-card-title">Schema guard</div>
                <div class="muted" id="schema-guard-summary">Checking schema...</div>
                <ul class="maintenance-list" id="schema-guard-list"></ul>
            </div>
            <div class="detail-card" id="schema-inventory-card" style="display:none;">
                <div class="detail-card-title">Known DB surfaces</div>
                <div class="muted" id="schema-inventory-summary">Checking web schema inventory...</div>
                <div class="detail-row">
                    <div class="detail-label">Known surfaces</div>
                    <div class="detail-value" id="schema-inventory-total">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Available</div>
                    <div class="detail-value" id="schema-inventory-available">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Missing columns</div>
                    <div class="detail-value" id="schema-inventory-missing-columns">--</div>
                </div>
            </div>
            <div class="detail-card" id="vt-surface-card" style="display:none;">
                <div class="detail-card-title">VT evidence surfaces</div>
                <div class="muted" id="vt-surface-summary">Checking VT vendor and signal surfaces...</div>
                <div class="detail-row">
                    <div class="detail-label">Known VT surfaces</div>
                    <div class="detail-value" id="vt-surface-total">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Available</div>
                    <div class="detail-value" id="vt-surface-available">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Missing</div>
                    <div class="detail-value" id="vt-surface-missing">--</div>
                </div>
            </div>
            <div class="detail-card" id="family-taxonomy-card" style="display:none;">
                <div class="detail-card-title">Family taxonomy scorecard</div>
                <div class="muted" id="family-taxonomy-summary">Checking family label drift...</div>
                <div class="detail-row">
                    <div class="detail-label">Mismatch rows</div>
                    <div class="detail-value" id="family-taxonomy-mismatch">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Signal only</div>
                    <div class="detail-value" id="family-taxonomy-signal-only">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Catalog only</div>
                    <div class="detail-value" id="family-taxonomy-catalog-only">--</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">High-conflict rows</div>
                    <div class="detail-value" id="family-taxonomy-high-conflict">--</div>
                </div>
            </div>
            <div class="detail-card" id="rollup-guard-card" style="display:none;">
                <div class="detail-card-title">Permission rollup guard</div>
                <div class="notice" id="rollup-guard-status">Checking rollups...</div>
                <div class="muted" id="rollup-guard-summary">--</div>
                <details id="rollup-guard-details" style="display:none;">
                    <summary class="muted">Show drifted permissions</summary>
                    <ul class="maintenance-list" id="rollup-guard-list"></ul>
                </details>
            </div>
            <div class="detail-card" id="workflow-debt-card" style="display:none;">
                <div class="detail-card-title">Workflow debt</div>
                <div class="muted" id="workflow-debt-summary">Checking live workflow vocabulary...</div>
                <ul class="maintenance-list" id="workflow-debt-list"></ul>
            </div>
        </div>
    </details>
</section>

<div class="health-error" id="health-error"></div>
