<?php
declare(strict_types=1);

/** @var string $idPrefix */
/** @var string $variant */
/** @var string|null $sliceNote */
/** @var string $title */
/** @var bool $showCopy */
/** @var string|null $liveMetaId */
/** @var string $hint */
/** @var string $laneSummary */
/** @var string|null $recommendedLane */
/** @var string $source */
/** @var int $queuePending */
/** @var int $stateEligible */
/** @var string $noticeClass */
/** @var string $runCommand */
/** @var array<string, mixed> $pipelineVt */
/** @var array<string, mixed> $runPlan */

$sectionId = $idPrefix . '-panel';
?>

<section class="section-shell pipeline-engine-panel pipeline-engine-panel--<?= h($variant) ?>" id="<?= h($sectionId) ?>">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title"><?= h($title) ?></h2>
            <p class="section-shell-copy">
                Matches the Python engine posture (<code>erebus pipeline …</code>).
                <?php if ($variant === 'notice' && is_string($sliceNote) && $sliceNote !== ''): ?>
                    <?= h($sliceNote) ?>
                <?php elseif ($variant === 'compact'): ?>
                    Review queue pressure before adding more intake work.
                <?php else: ?>
                    Use this before starting another queue wave.
                    <?php if (is_string($sliceNote) && $sliceNote !== ''): ?>
                        <?= h($sliceNote) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flow-inline">
            <span class="muted mono" style="font-size: 12px;" id="<?= h($idPrefix) ?>-source">source: <?= h($source) ?></span>
            <?php if (is_string($liveMetaId) && $liveMetaId !== ''): ?>
                <span class="muted" style="font-size: 12px; margin-left: 12px;" id="<?= h($liveMetaId) ?>">Live refresh pending…</span>
            <?php endif; ?>
            <?php if ($showCopy && $runCommand !== ''): ?>
                <button type="button" class="btn btn-small pipeline-engine-copy" data-panel-prefix="<?= h($idPrefix) ?>" data-copy-command="<?= h($runCommand) ?>" style="margin-left: 12px;">Copy CLI</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="notice <?= h($noticeClass) ?>" id="<?= h($idPrefix) ?>-notice">
        <?= h($hint !== '' ? $hint : 'Engine recommendation unavailable.') ?>
    </div>

    <?php if ($variant !== 'notice'): ?>
        <div class="detail-grid" style="margin-top: 14px;">
            <div class="detail-card">
                <div class="detail-card-title">Queue pending</div>
                <div class="detail-value" id="<?= h($idPrefix) ?>-queue-pending"><?= number_format($queuePending) ?></div>
                <div class="muted">vt_state eligible now: <span id="<?= h($idPrefix) ?>-state-eligible"><?= number_format($stateEligible) ?></span></div>
            </div>
            <div class="detail-card">
                <div class="detail-card-title">Lane posture</div>
                <div class="detail-value" id="<?= h($idPrefix) ?>-lane-summary"><?= h($laneSummary !== '' ? $laneSummary : 'No lane breakdown') ?></div>
                <div class="muted">Suggested lane: <span class="mono" id="<?= h($idPrefix) ?>-recommended-lane"><?= h($recommendedLane ?? '--') ?></span></div>
            </div>
            <?php if ($variant === 'full'): ?>
                <div class="detail-card">
                    <div class="detail-card-title">VT keys</div>
                    <div class="detail-value"><span id="<?= h($idPrefix) ?>-keys-ready"><?= number_format((int)($pipelineVt['keys_ready'] ?? 0)) ?></span> ready</div>
                    <div class="muted">
                        quota <?= number_format((int)($pipelineVt['quota_remaining'] ?? 0)) ?>
                        <?= !empty($pipelineVt['hold_active']) ? ' · hold active' : '' ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="detail-card">
                <div class="detail-card-title">Run plan</div>
                <div class="detail-value mono" style="font-size: 14px;" id="<?= h($idPrefix) ?>-run-command"><?= h($runCommand !== '' ? $runCommand : '--') ?></div>
                <div class="muted"><?= h((string)($runPlan['reason'] ?? '')) ?></div>
            </div>
        </div>
        <?php if ($variant === 'compact'): ?>
            <div class="flow-inline" style="margin-top: 14px;">
                <a class="btn" href="<?= h(page_url('pipeline_ops')) ?>">Pipeline Ops</a>
                <a class="btn" href="<?= h(page_url('ingest_backlog')) ?>">Ingest Backlog</a>
                <?php if ($recommendedLane !== null): ?>
                    <a class="btn" href="<?= h(page_url('ingest_backlog', ['lane' => $recommendedLane])) ?>">Focus suggested lane</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
