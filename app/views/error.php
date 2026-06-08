<?php
// app/views/error.php
declare(strict_types=1);
?>
<section class="page-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Error handling</div>
        <div class="page-kicker"><?= h(($errorHttpStatus ?? 500) === 404 ? 'Route recovery' : 'Application recovery') ?></div>
        <h1 class="page-hero-title"><?= h($errorTitle ?? 'Application Error') ?></h1>
        <p class="page-hero-lede muted">
            <?= h($errorSummary ?? ($errorMessage ?? 'An unexpected error occurred.')) ?>
        </p>
        <div class="page-hero-actions">
            <?php foreach (($errorRecoveryLinks ?? []) as $link): ?>
                <a class="<?= !empty($link['primary']) ? 'btn btn-primary' : 'btn' ?>" href="<?= h((string)$link['href']) ?>">
                    <?= h((string)$link['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">What to do now</h2>
        <p><?= h($errorHint ?? 'Retry once, then inspect the request context and logs if the issue persists.') ?></p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">HTTP status</div>
                <div class="hero-metric-value"><?= h((string)($errorHttpStatus ?? 500)) ?></div>
            </div>
            <div class="hero-metric">
                <div class="hero-metric-label">Request ID</div>
                <div class="hero-metric-value mono"><?= h($requestId ?? 'unknown') ?></div>
            </div>
        </div>
    </aside>
</section>

<section class="section-shell">
    <div class="notice error">
        <?= h($errorMessage ?? 'An unexpected error occurred.') ?>
    </div>
</section>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Request context</h2>
            <p class="section-shell-copy">Use this context to verify whether the problem is a bad route, stale bookmark, or a real app failure.</p>
        </div>
    </div>
    <div class="detail-grid">
        <div class="detail-card">
            <div class="detail-card-title">Route</div>
            <div class="detail-row">
                <div class="detail-label">Requested page</div>
                <div class="detail-value mono"><?= h($errorRouteLabel ?? '(none)') ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Request path</div>
                <div class="detail-value mono"><?= h($errorRequestUri ?? '') ?></div>
            </div>
        </div>
        <div class="detail-card">
            <div class="detail-card-title">Timing</div>
            <div class="detail-row">
                <div class="detail-label">UTC</div>
                <div class="detail-value"><?= h($utcNow ?? '') ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Request ID</div>
                <div class="detail-value mono"><?= h($requestId ?? 'unknown') ?></div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($showDebug) && isset($errorException) && $errorException instanceof Throwable): ?>
    <section class="section-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Debug details</h2>
                <p class="section-shell-copy">Visible only in debug-capable environments.</p>
            </div>
        </div>
        <details class="detail-card">
            <summary>Show exception trace</summary>
            <pre><?php
                echo h($errorException->getMessage() . "\n\n");
                echo h($errorException->getFile() . ':' . $errorException->getLine() . "\n\n");
                echo h($errorException->getTraceAsString());
            ?></pre>
        </details>
    </section>
<?php endif; ?>
