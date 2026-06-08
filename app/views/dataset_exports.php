<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/dataset_readiness_data.php';

$title = 'Export Readiness';
$artifacts = dataset_readiness_fetch_export_readiness();
?>

<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Dataset Curation</div>
        <div class="page-kicker">Future artifact readiness</div>
        <h1 class="page-hero-title">Export Readiness</h1>
        <p class="page-hero-lede muted">
            Read-only export readiness page for the future dataset release layer. This milestone does not generate files yet; it only exposes the expected artifact inventory and marks it as not implemented.
        </p>
        <div class="page-hero-actions">
            <a class="btn btn-primary" href="<?= h(page_url('dataset_exports')) ?>">Refresh</a>
            <a class="btn" href="<?= h(page_url('dataset_readiness')) ?>">Dataset Readiness</a>
            <a class="btn" href="<?= h(page_url('type_benchmark')) ?>">Type Benchmark</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Current policy</h2>
        <p>No release files are created here. Erebus Web remains the curation console, and export generation stays deferred until the governed label surfaces and split policy are stable.</p>
    </aside>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Expected Artifact Inventory</h2>
            <p class="muted">These are the planned release artifacts for a future dataset-export milestone.</p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Artifact</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($artifacts as $artifact): ?>
                    <tr>
                        <td><strong><?= h((string)($artifact['artifact_name'] ?? '')) ?></strong></td>
                        <td><?= h((string)($artifact['status'] ?? 'not_implemented')) ?></td>
                        <td class="muted"><?= h((string)($artifact['notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
