<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Time';
$pageScripts = ['assets/js/pages/time_reference_page.js'];
$currentKey = tz_current_key();
$currentTz = tz_current_id();
$currentPrimaryLabel = tz_display_label($currentKey);
$currentSecondaryKey = tz_current_secondary_key();
$currentSecondaryTz = tz_current_secondary_id();
$currentSecondaryLabel = $currentSecondaryKey !== null ? tz_display_label($currentSecondaryKey) : 'No second operator clock';
$primaryOptions = tz_display_options();
$secondaryOptions = tz_secondary_options();
$usDefaults = tz_us_defaults();
$success = (($_GET['saved'] ?? '') === '1');
$warning = null;
if (($_GET['warn'] ?? '') === 'primary') {
    $warning = 'Primary clock unchanged because the selected timezone was invalid.';
} elseif (($_GET['warn'] ?? '') === 'secondary') {
    $warning = 'Second operator clock unchanged because it matched the primary clock or was invalid.';
}
$timeRows = [
    ['label' => 'New York', 'key' => 'new_york', 'tz' => TZ_NEW_YORK],
    ['label' => 'Minneapolis', 'key' => 'minneapolis', 'tz' => TZ_MINNEAPOLIS],
    ['label' => 'Denver', 'key' => 'denver', 'tz' => TZ_DENVER],
    ['label' => 'Las Vegas', 'key' => 'las_vegas', 'tz' => TZ_LAS_VEGAS],
    ['label' => 'Anchorage', 'key' => 'anchorage', 'tz' => TZ_ANCHORAGE],
    ['label' => 'Honolulu', 'key' => 'honolulu', 'tz' => TZ_HONOLULU],
    ['label' => 'UTC', 'key' => 'utc', 'tz' => TZ_UTC],
    ['label' => 'Amsterdam', 'key' => 'amsterdam', 'tz' => TZ_AMSTERDAM],
    ['label' => 'Paris', 'key' => 'paris', 'tz' => TZ_PARIS],
    ['label' => 'Tokyo', 'key' => 'tokyo', 'tz' => TZ_TOKYO],
    ['label' => 'Dubai', 'key' => 'dubai', 'tz' => TZ_DUBAI],
];
$nowMap = now_in_all_timezones('n/j/Y g:i:A');
usort($timeRows, static function (array $left, array $right) use ($nowMap): int {
    $leftSort = 0;
    $rightSort = 0;
    try {
        $leftSort = (int)(new DateTimeImmutable('now', new DateTimeZone((string)$left['tz'])))->format('YmdHi');
        $rightSort = (int)(new DateTimeImmutable('now', new DateTimeZone((string)$right['tz'])))->format('YmdHi');
    } catch (Throwable $e) {
        // Keep fallback zero values if a timezone config is unexpectedly invalid.
    }
    return $rightSort <=> $leftSort;
});
?>

<section class="page-hero settings-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Platform Time</div>
        <div class="page-kicker">Reference only</div>
        <h1 class="page-hero-title">Time</h1>
        <p class="page-hero-lede muted">
            Use this page to choose the header clocks and compare the supported operator timezones. Database logic, VT pacing, and queue timing remain UTC.
        </p>
        <div class="page-hero-actions">
            <button type="submit" form="time-settings-form" class="btn btn-primary">Save clock settings</button>
            <a class="btn" href="<?= htmlspecialchars(page_url('settings')) ?>">Open Settings</a>
            <a class="btn" href="<?= htmlspecialchars(page_url('landing')) ?>">Back to Home</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Header clock model</h2>
        <p>UTC is always shown. Add one required operator clock and one optional second operator clock for the current browser session.</p>
    </aside>
</section>

<?php if ($success): ?>
    <div class="notice success">
        Saved. Header clocks now show <strong><?= htmlspecialchars($currentPrimaryLabel) ?></strong>
        <?php if ($currentSecondaryKey !== null): ?>
            and <strong><?= htmlspecialchars($currentSecondaryLabel) ?></strong>
        <?php else: ?>
            and no second operator clock
        <?php endif; ?>.
    </div>
<?php endif; ?>

<?php if ($warning): ?>
    <div class="notice warn"><?= htmlspecialchars($warning) ?></div>
<?php endif; ?>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">U.S. default clocks</h2>
            <p class="section-shell-copy">Quick-set row for the six major U.S. time zones. Each default maps to one representative city clock.</p>
        </div>
    </div>
    <div class="detail-card us-defaults-card">
        <div class="us-defaults-grid">
            <?php foreach ($usDefaults as $default): ?>
                <button
                    class="btn us-default-chip"
                    type="button"
                    data-primary-tz-key="<?= htmlspecialchars($default['key']) ?>">
                    <span class="us-default-chip-zone"><?= htmlspecialchars($default['zone']) ?></span>
                    <span class="us-default-chip-label"><?= htmlspecialchars($default['label']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <p class="muted us-defaults-note">Use these to set the primary operator clock quickly, then optionally add a second clock below.</p>
    </div>
</section>

<form method="post" id="time-settings-form">
    <section class="section-shell settings-primary-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Header clocks</h2>
                <p class="section-shell-copy">UTC always stays visible in the header. Choose one required operator clock and one optional second clock.</p>
            </div>
        </div>
        <div class="detail-card settings-primary-card time-settings-card">
            <div class="settings-fields">
                <div class="settings-field">
                    <label for="tz">Primary operator clock</label>
                    <select id="tz" name="tz" required>
                        <?php foreach ($primaryOptions as $opt): ?>
                            <option
                                value="<?= htmlspecialchars($opt['key']) ?>"
                                data-tz="<?= htmlspecialchars($opt['tz']) ?>"
                                <?= ($opt['key'] === $currentKey ? 'selected' : '') ?>>
                                <?= htmlspecialchars($opt['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="muted">This is the main non-UTC clock used across the UI.</div>
                    <div class="muted" id="time-primary-preview">Primary clock: --</div>
                </div>
                <div class="settings-field">
                    <label for="tz_secondary">Second operator clock</label>
                    <select id="tz_secondary" name="tz_secondary">
                        <?php foreach ($secondaryOptions as $opt): ?>
                            <option
                                value="<?= htmlspecialchars($opt['key']) ?>"
                                data-tz="<?= htmlspecialchars($opt['tz']) ?>"
                                <?= ($opt['key'] === ($currentSecondaryKey ?? 'none') ? 'selected' : '') ?>>
                                <?= htmlspecialchars($opt['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="muted">Optional. Leave disabled if UTC plus one operator clock is enough.</div>
                    <div class="muted" id="time-secondary-preview">Second clock: --</div>
                </div>
            </div>
        </div>
    </section>
</form>

<section class="section-shell">
    <div class="section-shell-header">
        <div>
            <h2 class="section-shell-title">Timezone table</h2>
            <p class="section-shell-copy">Reference table for the supported operator timezones. DB logic remains UTC.</p>
        </div>
    </div>
    <div class="detail-card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Timezone ID</th>
                        <th>Now</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['label']) ?></td>
                            <td><?= htmlspecialchars($row['tz']) ?></td>
                            <td><?= htmlspecialchars($nowMap[$row['key']]['now'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
