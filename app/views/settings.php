<?php
// app/views/settings.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../lib/theme.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Settings';
$pageScripts = ['assets/js/pages/settings_page.js'];

$success = false;
$error = null;
$warning = null;
$logSuccess = null;
$logError = null;
$logWarning = null;

$currentKey = tz_current_key();
$currentTz  = tz_current_id();
$currentTheme = theme_current();

function read_log_tail(string $path, int $lines): string
{
    if ($lines <= 0 || !is_file($path) || !is_readable($path)) {
        return '';
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }

    $buffer = '';
    $chunkSize = 4096;
    fseek($handle, 0, SEEK_END);
    $pos = ftell($handle);
    $lineCount = 0;

    while ($pos > 0 && $lineCount <= $lines) {
        $readSize = ($pos - $chunkSize) >= 0 ? $chunkSize : $pos;
        $pos -= $readSize;
        fseek($handle, $pos);
        $chunk = fread($handle, $readSize);
        if ($chunk === false) {
            break;
        }
        $buffer = $chunk . $buffer;
        $lineCount = substr_count($buffer, "\n");
    }

    fclose($handle);
    $buffer = trim($buffer);
    if ($buffer === '') {
        return '';
    }
    $parts = explode("\n", $buffer);
    if (count($parts) > $lines) {
        $parts = array_slice($parts, -$lines);
    }
    return implode("\n", $parts);
}

$logDir = defined('LOG_DIR') ? (string)LOG_DIR : (APP_ROOT . '/logs');
$logFiles = [];
if (is_dir($logDir)) {
    $paths = glob(rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.log') ?: [];
    foreach ($paths as $path) {
        $name = basename($path);
        $logFiles[$name] = [
            'path' => $path,
            'size' => @filesize($path) ?: 0,
            'mtime' => @filemtime($path) ?: 0,
        ];
    }
}
ksort($logFiles);

$selectedLog = trim((string)($_GET['log'] ?? ''));
$tailLines = (int)($_GET['lines'] ?? 200);
$tailLines = max(20, min(1000, $tailLines));

if ($selectedLog === '' && !empty($logFiles)) {
    $selectedLog = array_key_first($logFiles);
}
if ($selectedLog !== '' && !isset($logFiles[$selectedLog])) {
    $logWarning = 'Selected log was not found.';
    $selectedLog = !empty($logFiles) ? array_key_first($logFiles) : '';
}

// v1: settings page is the ONLY place timezone can be changed.
if (($_GET['saved'] ?? '') === '1') {
    $success = true;
}

if (($_GET['err'] ?? '') === 'theme') {
    $error = 'Invalid theme selection.';
}

if (($_GET['warn'] ?? '') === 'tz') {
    $warning = 'Timezone unchanged (invalid input).';
}

if (($_GET['log_cleared'] ?? '') === '1') {
    $logSuccess = 'Log cleared.';
}

$logErr = (string)($_GET['log_err'] ?? '');
if ($logErr === 'not_found') {
    $logError = 'Log file not found.';
} elseif ($logErr === 'not_writable') {
    $logError = 'Log file is not writable.';
} elseif ($logErr !== '') {
    $logError = 'Log action failed.';
}

$logTail = '';
if ($selectedLog !== '' && isset($logFiles[$selectedLog])) {
    $logTail = read_log_tail($logFiles[$selectedLog]['path'], $tailLines);
}
?>

<h1>Settings</h1>

<p class="muted">
    Display settings only. Database write/read behavior remains UTC.
</p>
<div class="muted" style="margin-top: 6px;">
    Scope: timezone + theme for this browser session. No VT pipeline or classification data changes.
</div>

<?php if ($success): ?>
    <div class="notice success">
        Saved. Display timezone: <strong><?= htmlspecialchars($currentTz) ?></strong>
        | Theme: <strong><?= htmlspecialchars(ucfirst($currentTheme)) ?></strong>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="notice error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($warning): ?>
    <div class="notice warn"><?= htmlspecialchars($warning) ?></div>
<?php endif; ?>

<form method="post" id="settings-form">
    <section class="settings-section">
        <h2>Display preferences</h2>
        <p class="muted">Set how timestamps and colors are shown across the web console.</p>
        <div class="settings-fields">
            <div class="settings-field">
                <label for="tz">Display timezone</label>
                <select id="tz" name="tz" required>
                    <?php foreach (tz_display_options() as $opt): ?>
                        <option
                            value="<?= htmlspecialchars($opt['key']) ?>"
                            data-tz="<?= htmlspecialchars($opt['tz']) ?>"
                            <?= ($opt['key'] === $currentKey ? 'selected' : '') ?>>
                            <?= htmlspecialchars($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="muted">Affects all timestamps shown across the UI.</div>
                <div class="muted" id="settings-tz-preview">Current selection: --</div>
            </div>
            <div class="settings-field">
                <label>Theme</label>
                <label class="toggle">
                    <input type="checkbox" name="theme_dark" value="1" <?= $currentTheme === 'dark' ? 'checked' : '' ?> />
                    <span class="toggle-track" aria-hidden="true"></span>
                    <span class="toggle-text">Dark mode</span>
                </label>
                <div class="muted">Applies to all screens after save.</div>
            </div>
        </div>
        <div class="settings-actions">
            <button type="submit" class="btn btn-primary">Save settings</button>
            <a class="btn btn-muted" href="<?= htmlspecialchars(page_url('landing')) ?>">Back to Home</a>
            <a class="muted" href="<?= htmlspecialchars(page_url('health')) ?>">Open Health</a>
        </div>
    </section>
</form>

<section class="settings-section">
    <details>
        <summary class="muted">Advanced: local logs</summary>
        <p class="muted" style="margin-top:8px;">View recent app/api/db log lines for troubleshooting. Clear only truncates local log files.</p>

        <?php if ($logSuccess): ?>
            <div class="notice success"><?= htmlspecialchars($logSuccess) ?></div>
        <?php endif; ?>
        <?php if ($logError): ?>
            <div class="notice error"><?= htmlspecialchars($logError) ?></div>
        <?php endif; ?>
        <?php if ($logWarning): ?>
            <div class="notice warn"><?= htmlspecialchars($logWarning) ?></div>
        <?php endif; ?>

        <?php if (empty($logFiles)): ?>
            <div class="notice warn">No log files found in <?= htmlspecialchars($logDir) ?>.</div>
        <?php else: ?>
            <form method="get" class="settings-fields" action="<?= htmlspecialchars(page_url('settings')) ?>">
                <div class="settings-field">
                    <label for="log-file">Log file</label>
                    <select id="log-file" name="log">
                        <?php foreach ($logFiles as $name => $meta): ?>
                            <option value="<?= htmlspecialchars($name) ?>" <?= $name === $selectedLog ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="muted">Log dir: <?= htmlspecialchars($logDir) ?></div>
                </div>
                <div class="settings-field">
                    <label for="log-lines">Lines</label>
                    <input id="log-lines" name="lines" type="number" min="20" max="1000" value="<?= (int)$tailLines ?>" />
                    <div class="muted">Show last N lines (max 1000).</div>
                </div>
                <div class="settings-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">Show log</button>
                </div>
            </form>

            <?php if ($selectedLog !== '' && isset($logFiles[$selectedLog])): ?>
                <?php
                $logMeta = $logFiles[$selectedLog];
                $logUpdated = $logMeta['mtime'] ? gmdate('Y-m-d H:i:s', $logMeta['mtime']) . ' UTC' : '--';
                ?>
                <div class="muted" style="margin-top: 8px;">
                    Last modified: <?= htmlspecialchars($logUpdated) ?> | Size: <?= (int)$logMeta['size'] ?> bytes
                </div>
                <pre><?= htmlspecialchars($logTail !== '' ? $logTail : 'No log entries found.') ?></pre>
                <form method="post" style="margin-top: 8px;" onsubmit="return confirm('Clear this log file?');">
                    <input type="hidden" name="action" value="log_clear" />
                    <input type="hidden" name="log" value="<?= htmlspecialchars($selectedLog) ?>" />
                    <input type="hidden" name="lines" value="<?= (int)$tailLines ?>" />
                    <button type="submit" class="btn btn-muted">Clear log</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </details>
</section>

<section class="settings-section">
    <details>
        <summary class="muted">Reference: timezone table</summary>
        <p class="muted" style="margin-top:8px;">Reference only. DB logic remains UTC.</p>
        <div class="table-scroll">
        <?php
        $timeRows = [
            ['label' => 'Minneapolis', 'key' => 'minneapolis', 'tz' => TZ_MINNEAPOLIS],
            ['label' => 'Las Vegas', 'key' => 'las_vegas', 'tz' => TZ_LAS_VEGAS],
            ['label' => 'UTC', 'key' => 'utc', 'tz' => TZ_UTC],
            ['label' => 'Dubai', 'key' => 'dubai', 'tz' => TZ_DUBAI],
        ];
        $nowMap = now_in_all_timezones();
        ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>TZ</th>
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
    </details>
</section>
