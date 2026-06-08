<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/taxonomy_view_data.php';

$title = 'Signal Hygiene';
$queryPlatform = strtolower(trim((string)($_GET['platform'] ?? 'android')));
$hygiene = taxonomy_view_signal_hygiene_data($queryPlatform);
$genericHoldOp = is_array($hygiene['generic_hold_op'] ?? null) ? $hygiene['generic_hold_op'] : null;
$resolvedOps = is_array($hygiene['resolved_ops'] ?? null) ? $hygiene['resolved_ops'] : [];
$genericIssues = is_array($hygiene['generic_issues'] ?? null) ? $hygiene['generic_issues'] : [];
$counts = is_array($hygiene['counts'] ?? null) ? $hygiene['counts'] : [];

$resolvedCount = (int)($counts['alias_resolved'] ?? 0);
$overlapCount = (int)($counts['signal_overlap'] ?? 0);
$genericHoldCount = (int)($counts['generic_policy_hold'] ?? 0);
$aliasCandidateCount = (int)($counts['alias_candidate'] ?? 0);

$signalExamples = static function (array $row): string {
    $examples = array_values(array_filter(array_map('strval', (array)($row['signal_label_examples'] ?? []))));
    if ($examples === []) {
        return '--';
    }
    return implode(', ', array_slice($examples, 0, 3));
};
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Malware Family Truth</div>
        <div class="page-kicker">VT token hygiene, alias handling, and governed holds</div>
        <h1 class="page-hero-title">Signal Hygiene</h1>
        <p class="page-hero-lede muted">
            This page isolates the VT-side naming noise that should not be promoted into catalog family truth. It also shows where alias handling is already governed and can stay out of the repair queue.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('family_taxonomy_signal_hygiene', ['platform' => $queryPlatform])) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'decision_mode' => 'hold_generic_signal'])) ?>">Open generic-signal hold</a>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform])) ?>">Repair Queue</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Lane ownership</h2>
        <p>Use this page for noisy VT-token policy holds. Route alias-candidate repair work out of this lane, and read governed alias keep-as-is rows as context rather than active backlog.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">generic_policy_hold</div>
                <div class="hero-metric-value"><?= h(number_format($genericHoldCount)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">signal_overlap</div>
                <div class="hero-metric-value"><?= h(number_format($overlapCount)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">alias_candidate</div>
                <div class="hero-metric-value"><?= h(number_format($aliasCandidateCount)) ?></div>
            </div>
        </div>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Policy Hold Lanes</h2>
            <p class="muted">Rows that should stay visible but out of direct repair because the VT family token is noisy, generic, or composite. This section owns the live hold lanes only.</p>
        </div>
    </div>
    <div class="detail-grid">
        <?php if (is_array($genericHoldOp)): ?>
            <div class="detail-card">
                <div class="detail-card-title"><?= h(taxonomy_view_issue_display_label((string)($genericHoldOp['dominant_issue_kind'] ?? 'Issue'))) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)($genericHoldOp['row_count'] ?? 0))) ?></div>
                <p class="muted"><?= h((string)($genericHoldOp['decision_why'] ?? '')) ?></p>
                <a class="btn" href="<?= h(page_url('family_taxonomy_queue', array_filter([
                    'platform' => $queryPlatform,
                    'decision_mode' => (string)($genericHoldOp['decision_mode'] ?? ''),
                    'pattern' => 'generic_signal',
                ], static fn($value): bool => $value !== ''))) ?>">Open hold slice</a>
            </div>
        <?php endif; ?>
        <div class="detail-card">
            <div class="detail-card-title">signal_overlap</div>
            <div class="hero-metric-value"><?= h(number_format($overlapCount)) ?></div>
            <p class="muted">Composite VT labels that mix a family token with detector-style or noisy secondary tokens.</p>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'decision_mode' => 'hold_signal_overlap'])) ?>">Open overlap hold</a>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">alias_candidate</div>
            <div class="hero-metric-value"><?= h(number_format($aliasCandidateCount)) ?></div>
            <p class="muted">Alias-candidate rows are nearby but not owned by signal hygiene. Keep them in the repair queue or grouped repair follow-up instead of treating them as generic/noisy holds.</p>
            <a class="btn" href="<?= h(page_url('family_taxonomy_queue', ['platform' => $queryPlatform, 'pattern' => 'alias_candidate'])) ?>">Open alias review slice</a>
        </div>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Hold Composition</h2>
            <p class="muted"><code>generic_policy_hold = <?= h(number_format($genericHoldCount)) ?></code> is the full hold lane. The cards below show the component issue kinds inside that lane.</p>
        </div>
    </div>
    <div class="detail-grid">
        <?php foreach ($genericIssues as $kind => $count): ?>
            <?php if ((int)$count <= 0) { continue; } ?>
            <div class="detail-card">
                <div class="detail-card-title"><?= h(taxonomy_view_issue_display_label((string)$kind)) ?></div>
                <div class="hero-metric-value"><?= h(number_format((int)$count)) ?></div>
                <a class="btn" href="<?= h(page_url('family_taxonomy_queue', array_filter([
                    'platform' => $queryPlatform,
                    'decision_mode' => 'hold_generic_signal',
                    'pattern' => match ($kind) {
                        'short_signal_token' => 'short_signal',
                        'generic_signal' => 'generic_signal',
                        'placeholder_catalog' => 'placeholder_catalog',
                        'catalog_missing' => 'catalog_missing',
                        default => '',
                    },
                ], static fn($value): bool => $value !== ''))) ?>">Open slice</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Governed Alias Keep-As-Is</h2>
            <p class="muted">The largest governed alias rows that already resolve cleanly. Keep them out of repair pressure and use them only as context for VT naming noise.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Canonical family</th>
                        <th>Rows</th>
                        <th>Signal examples</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($resolvedOps, 0, 8) as $row): ?>
                        <tr>
                            <td><?= h((string)($row['suggested_target_family'] ?? '')) ?></td>
                            <td><?= h(number_format((int)($row['row_count'] ?? 0))) ?></td>
                            <td><?= h($signalExamples((array)$row)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($resolvedOps === []): ?>
                        <tr>
                            <td colspan="3" class="muted">No governed alias keep-as-is families found in the current snapshot.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
