<?php
// app/views/settings.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../lib/theme.php';
require_once __DIR__ . '/../lib/url.php';

$title = 'Settings';
$success = false;
$error = null;
$warning = null;
$logSuccess = null;
$logError = null;
$logWarning = null;

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

if (($_GET['saved'] ?? '') === '1') {
    $success = true;
}

if (($_GET['err'] ?? '') === 'theme') {
    $error = 'Invalid theme selection.';
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

<section class="page-hero settings-hero">
    <div class="page-hero-body">
        <div class="eyebrow">Workspace Settings</div>
        <div class="page-kicker">Display preferences only</div>
        <h1 class="page-hero-title">Settings</h1>
        <p class="page-hero-lede muted">
            Change appearance settings for this browser session. Database reads, writes, and VT pipeline behavior remain UTC.
        </p>
        <div class="page-hero-actions">
            <button type="submit" form="settings-form" class="btn btn-primary">Save settings</button>
            <a class="btn" href="<?= htmlspecialchars(page_url('landing')) ?>">Back to Home</a>
            <a class="btn" href="<?= htmlspecialchars(page_url('health')) ?>">Open Health</a>
            <a class="btn" href="<?= htmlspecialchars(page_url('time_reference')) ?>">Open Time</a>
        </div>
    </div>
    <aside class="page-hero-side">
        <h2 class="page-hero-side-title">Scope</h2>
        <p>Applies only to this browser session. No VirusTotal, queue, or classification data changes happen here.</p>
        <div class="hero-metric-grid">
            <div class="hero-metric">
                <div class="hero-metric-label">Theme</div>
                <div class="hero-metric-value"><?= htmlspecialchars(ucfirst($currentTheme)) ?></div>
            </div>
        </div>
    </aside>
</section>

<?php if ($success): ?>
    <div class="notice success">
        Saved. Theme: <strong><?= htmlspecialchars(ucfirst($currentTheme)) ?></strong>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="notice error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($warning): ?>
    <div class="notice warn"><?= htmlspecialchars($warning) ?></div>
<?php endif; ?>

<div class="settings-page-shell">
<form method="post" id="settings-form">
    <section class="section-shell settings-primary-shell">
        <div class="section-shell-header">
            <div>
                <h2 class="section-shell-title">Display preferences</h2>
                <p class="section-shell-copy">Set how the web console looks. Clock selection now lives on the Time page.</p>
            </div>
        </div>
        <div class="detail-card settings-primary-card">
            <div class="settings-fields">
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
        </div>
    </section>
</form>

<section class="section-shell settings-secondary-shell">
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

</div>
