<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/dataset_readiness_data.php';

$title = 'Authority Consistency Debt';
$payload = dataset_readiness_fetch_authority_consistency_debt();
$rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
$summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
$activeDebt = (int)($summary['affected_rows_count'] ?? 0) > 0;
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Dataset Curation</div>
        <div class="page-kicker"><?= h($activeDebt ? 'Read-only adjudication backlog' : 'Resolved consistency watchlist') ?></div>
        <h1 class="page-hero-title">Authority Consistency Debt</h1>
        <p class="page-hero-lede muted">
            <?= h($activeDebt
                ? 'Family-level backlog for rows where persisted authority facts disagree with the current governed family/type policy. This page is read-only and does not change labels, persisted facts, or benchmark logic.'
                : 'The seven-family watchlist remains visible for traceability, but there are currently no live rows where persisted authority facts disagree with current governed family/type policy.') ?>
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('authority_consistency_debt')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Type Benchmark</a>
            <a class="btn" href="<?= h(page_url('dataset_readiness')) ?>">Dataset Readiness</a>
            <a class="btn" href="<?= h(page_url('label_surfaces')) ?>">Label Surfaces</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Backlog scope</h2>
        <p><?= h($activeDebt ? 'These families are exposed for adjudication only. No authority rows are updated here.' : 'These families stay visible for audit traceability only. No authority rows are updated here.') ?></p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Families</div>
                <div class="hero-metric-value"><?= h(number_format((int)($summary['families_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Affected rows</div>
                <div class="hero-metric-value"><?= h(number_format((int)($summary['affected_rows_count'] ?? 0))) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Persisted status</div>
                <div class="hero-metric-value">auto</div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Mode</div>
                <div class="hero-metric-value">read-only</div>
            </div>
        </div>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Policy Note</h2>
            <p class="muted">Current handling guidance for persisted-vs-current-policy disagreements.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Persisted facts</div>
            <p class="muted"><?= h((string)($summary['persisted_auto_policy_note'] ?? '')) ?></p>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Authority priority</div>
            <p class="muted"><?= h((string)($summary['strongest_authority_note'] ?? '')) ?></p>
        </div>
            <div class="detail-card">
                <div class="detail-card-title">Benchmark hold</div>
                <p class="muted"><?= h((string)($summary['hold_note'] ?? '')) ?></p>
            </div>
        </div>
    </section>

<?php if (!$activeDebt): ?>
    <div class="notice info">
        The known seven-family consistency block is currently resolved in live persisted-vs-current-policy row counts. Erebus Web keeps this page as a watchlist so any future drift is visible immediately.
    </div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Decision Table</h2>
            <p class="muted"><?= h($activeDebt
                ? 'Seven-family authority consistency backlog with live affected-row counts and read-only adjudication guidance.'
                : 'Seven-family authority consistency watchlist with live affected-row counts and retained adjudication guidance.') ?></p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Family</th>
                    <th>Affected rows</th>
                    <th>Current family-table type</th>
                    <th>Persisted fact type</th>
                    <th>V2 canonical type</th>
                    <th>Migration basis</th>
                    <th>Evidence summary</th>
                    <th>Recommended winner</th>
                    <th>Confidence</th>
                    <th>Recommended action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="10" class="muted">No authority consistency debt families found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= h((string)($row['family_name'] ?? $row['family'] ?? '')) ?></strong><br><span class="muted"><?= h((string)($row['family'] ?? '')) ?></span></td>
                        <td><?= h(number_format((int)($row['row_count_affected'] ?? 0))) ?></td>
                        <td><?= h((string)($row['current_family_table_type'] ?? '--')) ?></td>
                        <td><?= h((string)($row['persisted_fact_type'] ?? '--')) ?></td>
                        <td><?= h((string)($row['v2_canonical_type'] ?? '--')) ?></td>
                        <td class="muted"><?= h((string)($row['migration_basis'] ?? '')) ?></td>
                        <td class="muted"><?= h((string)($row['evidence_summary'] ?? '')) ?></td>
                        <td><?= h((string)($row['recommended_winner'] ?? '--')) ?></td>
                        <td><?= h((string)($row['confidence'] ?? '--')) ?></td>
                        <td><?= h((string)($row['recommended_action'] ?? '--')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
