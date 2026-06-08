<?php
// app/handlers/settings_post.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../lib/theme.php';
require_once __DIR__ . '/../lib/url.php';

function settings_redirect(array $params = []): void
{
    header('Location: ' . page_url('settings', $params));
    exit;
}

$action = (string)($_POST['action'] ?? 'save_settings');
$currentKey = tz_current_key();

if ($action === 'log_clear') {
    $logDir = defined('LOG_DIR') ? (string)LOG_DIR : (APP_ROOT . '/logs');
    $logKey = basename((string)($_POST['log'] ?? ''));
    $tailLines = (int)($_POST['lines'] ?? 200);
    $tailLines = max(20, min(1000, $tailLines));
    $logPath = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $logKey;

    if ($logKey === '' || !is_file($logPath)) {
        settings_redirect([
            'log' => $logKey,
            'lines' => (string)$tailLines,
            'log_err' => 'not_found',
        ]);
    }

    if (!is_writable($logPath)) {
        settings_redirect([
            'log' => $logKey,
            'lines' => (string)$tailLines,
            'log_err' => 'not_writable',
        ]);
    }

    file_put_contents($logPath, '');
    settings_redirect([
        'log' => $logKey,
        'lines' => (string)$tailLines,
        'log_cleared' => '1',
    ]);
}

$tzInput = (string)($_POST['tz'] ?? $currentKey);
$tzCanonical = tz_canonical_key($tzInput);
if ($tzCanonical === null) {
    $tzKey = $currentKey;
} else {
    $tzKey = $tzCanonical;
}
$themeKey = isset($_POST['theme_dark']) ? 'dark' : 'light';

if (theme_canonical($themeKey) === null) {
    settings_redirect(['err' => 'theme']);
}

tz_set_cookie($tzKey);
theme_set_cookie($themeKey);

$params = ['saved' => '1'];
if ($tzCanonical === null) {
    $params['warn'] = 'tz';
}

settings_redirect($params);
