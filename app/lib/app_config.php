<?php
// app/lib/app_config.php
// Erebus Web Console v1 (trusted internal tool)
//
// Rules:
// - DB time remains UTC (authoritative for scheduling/backoff).
// - UI may display timestamps in a selected timezone.
// - Avoid DB credentials here (db_config.php owns that).
//
// Keep this file small, stable, and boring.

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
define('LOG_DIR', APP_ROOT . '/logs');
define('LOG_LEVEL', 'INFO');

if (!function_exists('app_guard_direct_internal_web_access')) {
    function app_guard_direct_internal_web_access(): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }

        $scriptFilename = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptFilename === '' || $scriptName === '') {
            return;
        }

        $appRoot = str_replace('\\', '/', APP_ROOT);
        $viewsPrefix = $appRoot . '/app/views/';
        $apiPrefix = $appRoot . '/app/api/';

        if (str_starts_with($scriptFilename, $viewsPrefix)) {
            $webBase = preg_replace('#/app/views/[^/]+$#', '', $scriptName);
            $webBase = is_string($webBase) ? rtrim($webBase, '/') : '';
            $route = strtolower(pathinfo($scriptFilename, PATHINFO_FILENAME));
            $target = ($webBase === '' ? '' : $webBase) . '/public/index.php?p=' . rawurlencode($route);
            $query = (string)($_SERVER['QUERY_STRING'] ?? '');
            if ($query !== '') {
                parse_str($query, $queryParams);
                unset($queryParams['p']);
                $extra = http_build_query($queryParams);
                if ($extra !== '') {
                    $target .= '&' . $extra;
                }
            }
            header('Location: ' . $target, true, 302);
            exit;
        }

        if (str_starts_with($scriptFilename, $apiPrefix)) {
            $webBase = preg_replace('#/app/api/[^/]+$#', '', $scriptName);
            $webBase = is_string($webBase) ? rtrim($webBase, '/') : '';
            $relative = substr($scriptFilename, strlen($apiPrefix));
            $target = ($webBase === '' ? '' : $webBase) . '/public/api.php/' . ltrim($relative, '/');
            $query = (string)($_SERVER['QUERY_STRING'] ?? '');
            if ($query !== '') {
                $target .= '?' . $query;
            }
            header('Location: ' . $target, true, 307);
            exit;
        }
    }
}

app_guard_direct_internal_web_access();

if (!function_exists('app_load_env_file')) {
    function app_load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $raw = trim((string)$line);
            if ($raw === '' || str_starts_with($raw, '#')) {
                continue;
            }
            if (str_starts_with($raw, 'export ')) {
                $raw = trim(substr($raw, 7));
            }
            $pos = strpos($raw, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($raw, 0, $pos));
            $value = trim(substr($raw, $pos + 1));
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

app_load_env_file(APP_ROOT . '/.env');
app_load_env_file(APP_ROOT . '/.env.local');
// -----------------------------
// App identity / routing
// -----------------------------
define('APP_NAME', 'Erebus Web');
define('APP_VERSION', 'v1.1.2'); // align with active CLI line
define('APP_DISPLAY_NAME', APP_NAME . ' ' . APP_VERSION);
define('APP_API_CONTRACT_VERSION', '2026-02-18');

// Optional git commit hash for operator diagnostics (safe to expose internally).
$gitSha = getenv('APP_GIT_SHA');
if ($gitSha === false || $gitSha === '') {
    $headFile = APP_ROOT . '/.git/HEAD';
    if (is_file($headFile)) {
        $head = trim((string)@file_get_contents($headFile));
        if (str_starts_with($head, 'ref:')) {
            $refPath = APP_ROOT . '/.git/' . trim(substr($head, 4));
            if (is_file($refPath)) {
                $gitSha = trim((string)@file_get_contents($refPath));
            }
        } elseif ($head !== '') {
            $gitSha = $head;
        }
    }
}
$gitSha = $gitSha ? substr(trim((string)$gitSha), 0, 8) : 'unknown';
define('APP_GIT_SHA', $gitSha);

// Base URL must point to your /public directory.
// Example: http://localhost/web/erebus-web/public/  ->  /web/erebus-web/public
// Allow override via BASE_URL env for local/test servers.
$baseUrl = getenv('BASE_URL');
if ($baseUrl === false || $baseUrl === '') {
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptName = str_replace('\\', '/', $scriptName);
    if ($scriptName !== '') {
        if (strpos($scriptName, '/public/') !== false) {
            $baseUrl = rtrim(dirname($scriptName), '/');
        } elseif (strpos($scriptName, '/app/api/') !== false) {
            $baseUrl = rtrim(str_replace('/app/api', '/public', dirname($scriptName)), '/');
        } elseif (preg_match('#/(index|api)\\.php$#', $scriptName) === 1) {
            $baseDir = rtrim(dirname($scriptName), '/');
            $baseUrl = $baseDir === '' ? '/' : $baseDir;
        }
    }
}
if ($baseUrl === false || $baseUrl === '') {
    $baseUrl = '/public';
}
define('BASE_URL', $baseUrl);

// Optional: environment tag for banners/badges (dev/test/prod)
define('APP_ENV', strtolower(getenv('APP_ENV') ?: 'prod'));
// Feature flags (simple env toggles).
$featurePhase2b = getenv('FEATURE_PHASE2B_READONLY');
if ($featurePhase2b === false || $featurePhase2b === '') {
    $featurePhase2b = (APP_ENV !== 'prod');
}
define('FEATURE_PHASE2B_READONLY', filter_var($featurePhase2b, FILTER_VALIDATE_BOOLEAN));

$featurePhase3 = getenv('FEATURE_PHASE3_OPS');
if ($featurePhase3 === false || $featurePhase3 === '') {
    $featurePhase3 = false;
}
define('FEATURE_PHASE3_OPS', filter_var($featurePhase3, FILTER_VALIDATE_BOOLEAN));

// -----------------------------
// Timezone display (UI only)
// -----------------------------
define('TZ_MINNEAPOLIS', 'America/Chicago');       // Minneapolis (Central Time)
define('TZ_DENVER',      'America/Denver');        // Denver (Mountain Time)
define('TZ_LAS_VEGAS',   'America/Los_Angeles');   // Las Vegas (Pacific Time)
define('TZ_NEW_YORK',    'America/New_York');      // New York (Eastern Time)
define('TZ_ANCHORAGE',   'America/Anchorage');     // Anchorage (Alaska Time)
define('TZ_HONOLULU',    'Pacific/Honolulu');      // Honolulu (Hawaii-Aleutian Time)
define('TZ_UTC',         'UTC');                   // UTC
define('TZ_AMSTERDAM',   'Europe/Amsterdam');      // Amsterdam (CET/CEST)
define('TZ_PARIS',       'Europe/Paris');          // Paris (CET/CEST)
define('TZ_TOKYO',       'Asia/Tokyo');            // Tokyo (JST)
define('TZ_DUBAI',       'Asia/Dubai');            // Dubai (GST)

// Allowed keys for v1 timezone selection (stored in cookie)
// NOTE: these keys must match time.php tz_map()
define('TZ_KEYS', [
    'minneapolis',
    'denver',
    'las_vegas',
    'new_york',
    'anchorage',
    'honolulu',
    'utc',
    'amsterdam',
    'paris',
    'tokyo',
    'dubai',
]);

// Default primary display timezone (UI only)
define('APP_TZ_DISPLAY_DEFAULT', TZ_MINNEAPOLIS);

// Optional fallback secondary display timezone (UI-only). Set to null to disable.
define('APP_TZ_DISPLAY_SECONDARY', null); // e.g., TZ_UTC

// -----------------------------
// Theme (UI only)
// -----------------------------
define('APP_THEME_DEFAULT', 'dark'); // dark | light
define('THEME_COOKIE_NAME', 'blackspider_theme');

// Settings storage (v1: cookie-based, internal tool)
define('TZ_COOKIE_NAME', 'blackspider_tz'); // stores primary key from TZ_KEYS
define('TZ_SECONDARY_COOKIE_NAME', 'blackspider_tz_secondary'); // stores secondary key from TZ_KEYS or "none"
define('TZ_COOKIE_DAYS', 30);

// Cookie scope:
// BASE_URL points to /public, but the cookie should cover the whole app folder,
// so it works for both /public/* and /app/api/* requests.
define('COOKIE_PATH', rtrim(dirname(BASE_URL), '/') . '/');

// -----------------------------
// Diagnostics / behavior
// -----------------------------
define('STALE_CLAIM_MINUTES', 30); // dev default (prod can be 60)

// Android permission taxonomy (UI consumes via API; do not hardcode in JS).
define('PERM_TAXONOMY_BUCKETS', [
    [
        'key' => 'AOSP_EXACT',
        'label' => 'AOSP Exact',
        'aliases' => ['AOSP', 'AOSP_EXACT'],
    ],
    [
        'key' => 'AOSP_HIDDEN_PRIV',
        'label' => 'AOSP Hidden / Privileged',
        'aliases' => ['AOSP_HIDDEN_PRIV', 'AOSP_HIDDEN', 'AOSP_PRIV'],
    ],
    [
        'key' => 'GOOGLE_GMS',
        'label' => 'Google GMS',
        'aliases' => ['GOOGLE_GMS', 'GMS'],
    ],
    [
        'key' => 'APP_DEFINED_OTHER',
        'label' => 'App Defined / Other',
        'aliases' => ['APP_DEFINED_OTHER', 'APP_DEFINED', 'OTHER'],
    ],
    [
        'key' => 'OEM_EXACT',
        'label' => 'OEM Exact',
        'aliases' => ['OEM_EXACT', 'OEM'],
    ],
    [
        'key' => 'UNKNOWN',
        'label' => 'Unknown / Unclassified',
        'aliases' => ['UNCLASSIFIED', 'UNKNOWN_UNCLASSIFIED'],
    ],
]);

// Permission classification enum (origin-focused).
define('PERM_CLASSIFICATIONS', [
    ['key' => 'AOSP', 'label' => 'AOSP'],
    ['key' => 'GOOGLE', 'label' => 'Google'],
    ['key' => 'OEM', 'label' => 'OEM'],
    ['key' => 'APP_DEFINED', 'label' => 'App-defined'],
    ['key' => 'UNKNOWN', 'label' => 'Unknown'],
]);

// Triage statuses for unknown permissions (UI consumes via API).
define('PERM_TRIAGE_STATUSES', [
    ['key' => 'new', 'label' => 'Unreviewed'],
    ['key' => 'in_review', 'label' => 'In review'],
    ['key' => 'deferred', 'label' => 'Deferred'],
    ['key' => 'aosp_missing', 'label' => 'AOSP gap'],
    ['key' => 'malformed', 'label' => 'Malformed'],
    ['key' => 'gms_known', 'label' => 'Google-defined'],
    ['key' => 'oem_candidate', 'label' => 'OEM candidate'],
    ['key' => 'launcher_ecosystem', 'label' => 'Launcher ecosystem'],
    ['key' => 'app_defined', 'label' => 'App-defined (auto)'],
    ['key' => 'brand_spoof', 'label' => 'Brand spoof'],
    ['key' => 'malicious_dga', 'label' => 'Malicious DGA'],
    ['key' => 'resolved_aosp', 'label' => 'AOSP resolved'],
    ['key' => 'resolved_oem', 'label' => 'OEM resolved'],
]);

// Namespace classes for drift/oem guidance (prefix-based).
define('PERM_NAMESPACE_CLASSES', [
    [
        'key' => 'core',
        'label' => 'Core',
        'class_name' => 'ok',
        'prefixes' => ['android', 'android.permission'],
    ],
    [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'prefixes' => ['com.google'],
    ],
    [
        'key' => 'oem',
        'label' => 'OEM',
        'class_name' => 'warn',
        'prefixes' => [
            'com.samsung',
            'com.sec',
            'com.huawei',
            'huawei.permission',
            'huawei.android.permission',
            'com.oppo',
            'com.coloros',
            'com.nearme',
            'com.heytap',
            'heytap.permission',
            'com.oplus',
            'oppo.permission',
            'oplus.permission',
            'com.xiaomi',
            'com.miui',
            'com.vivo',
            'com.bbk',
            'com.lenovo',
            'com.motorola',
            'com.lge',
            'com.meizu',
            'com.sony',
            'com.sonymobile',
            'com.sonyericsson',
            'com.htc',
            'com.asus',
            'com.zte',
            'com.nubia',
        ],
    ],
    [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'prefixes' => [],
    ],
    [
        'key' => 'anomalous',
        'label' => 'Anomalous',
        'class_name' => 'err',
        'prefixes' => [],
    ],
]);

// Exact namespace overrides for known exceptions to broad prefix rules.
define('PERM_NAMESPACE_CLASS_EXACT_OVERRIDES', [
    'com.android.launcher' => [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'review_bucket' => 'android_platform_app',
        'validation_label' => 'Known platform ecosystem',
        'review_hint' => 'Android platform launcher namespace. Treat as expected platform ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.android.launcher2' => [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'review_bucket' => 'android_platform_app',
        'validation_label' => 'Known platform ecosystem',
        'review_hint' => 'Android platform launcher namespace. Treat as expected platform ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.android.launcher3' => [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'review_bucket' => 'android_platform_app',
        'validation_label' => 'Known platform ecosystem',
        'review_hint' => 'Android platform launcher namespace. Treat as expected platform ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.android.browser' => [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'review_bucket' => 'android_platform_app',
        'validation_label' => 'Known platform ecosystem',
        'review_hint' => 'Android platform browser namespace. Treat as expected platform ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.android.alarm' => [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'review_bucket' => 'android_platform_app',
        'validation_label' => 'Known platform ecosystem',
        'review_hint' => 'Android platform alarm namespace. Treat as expected platform ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'me.everything.badger' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_sdk_or_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party badge ecosystem namespace. Treat as application/library ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.anddoes.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.nttdocomo.android' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'carrier_ecosystem',
        'validation_label' => 'Known carrier ecosystem',
        'review_hint' => 'Known NTT Docomo carrier/app ecosystem namespace. Treat as carrier/platform-adjacent drift, not OEM authority or a fresh anomaly.',
    ],
    'com.jb.gokeyboard' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_sdk_or_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known GO Keyboard ecosystem namespace. Treat as third-party app ecosystem drift, not OEM authority or a fresh anomaly.',
    ],
    'telecom.mdesk.permission' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_sdk_or_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known launcher/settings permission ecosystem namespace. Treat as ecosystem drift, not OEM authority or a fresh anomaly.',
    ],
    'com.android.mylauncher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Launcher settings ecosystem namespace. Treat as third-party launcher drift, not OEM authority or a fresh anomaly.',
    ],
    'com.ebproductions.android' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Launcher permission ecosystem namespace. Treat as third-party launcher drift, not OEM authority or a fresh anomaly.',
    ],
    'ir.devixor.app' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'app_defined_dynamic_receiver',
        'validation_label' => 'Known app-defined residue',
        'review_hint' => 'Observed only as an app-local dynamic receiver permission suffix. Treat as app-defined parser residue, not OEM authority or a fresh anomaly.',
    ],
    'com.scrap.praise' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'app_defined_dynamic_receiver',
        'validation_label' => 'Known app-defined residue',
        'review_hint' => 'Observed only as an app-local dynamic receiver permission suffix. Treat as app-defined parser residue, not OEM authority or a fresh anomaly.',
    ],
    'ir.shz.shzkisi' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'app_defined_push_permission',
        'validation_label' => 'Known app-defined residue',
        'review_hint' => 'Observed as an app-local C2D_MESSAGE permission namespace. Treat as app-defined push ecosystem residue, not OEM authority or a fresh anomaly.',
    ],
    'com.moutai.mall' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'app_defined_push_permission',
        'validation_label' => 'Known app-defined residue',
        'review_hint' => 'Observed as app-local push/provider permission namespace. Treat as app-defined push ecosystem residue, not OEM authority or a fresh anomaly.',
    ],
    'com.majeur.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.fede.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'org.adw.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'org.adw.launcher_donut' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'org.adwfreak.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'net.qihoo.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.qihoo360.launcher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.tencent.qqlauncher' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_launcher',
        'validation_label' => 'Known third-party ecosystem',
        'review_hint' => 'Known third-party launcher namespace. Treat as application ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.android.vending' => [
        'key' => 'expected',
        'label' => 'Expected',
        'class_name' => 'ok',
        'review_bucket' => 'google_play_ecosystem',
        'validation_label' => 'Known platform ecosystem',
        'review_hint' => 'Google Play / Android vending namespace. Treat as expected ecosystem drift, not OEM authority or anomalous vendor pressure.',
    ],
    'com.asus.msa' => [
        'key' => 'known_ecosystem',
        'label' => 'Known ecosystem',
        'class_name' => 'muted',
        'review_bucket' => 'third_party_sdk',
        'validation_label' => 'Known identifier ecosystem',
        'review_hint' => 'MSA/OAID identifier ecosystem namespace observed across third-party apps and SDK disclosures; treat as known ecosystem drift, not OEM authority or a fresh anomaly.',
    ],
]);

// OEM permission review outcomes (read-only until workflow is wired).
define('PERM_OEM_REVIEW_OUTCOMES', [
    ['key' => 'reviewed_benign', 'label' => 'Reviewed - benign'],
    ['key' => 'restricted', 'label' => 'Restricted'],
    ['key' => 'watch', 'label' => 'Watch'],
    ['key' => 'needs_review', 'label' => 'Needs review'],
]);

// Queue actions for permission dictionary maintenance (triage -> pipeline).
define('PERM_QUEUE_ACTIONS', [
    ['key' => 'defer', 'label' => 'Defer (needs more evidence)'],
    ['key' => 'aosp', 'label' => 'Queue for AOSP dictionary'],
    ['key' => 'google', 'label' => 'Queue for Google catalog'],
    ['key' => 'oem', 'label' => 'Queue for OEM registry'],
    ['key' => 'app_defined', 'label' => 'Mark as app-defined'],
    ['key' => 'reject', 'label' => 'Reject / no dictionary action'],
]);

// Queue lifecycle statuses (read from queue table).
define('PERM_QUEUE_STATUSES', [
    ['key' => 'queued', 'label' => 'Queued (not applied)'],
    ['key' => 'claimed', 'label' => 'Claimed (processing)'],
    ['key' => 'applied', 'label' => 'Applied'],
    ['key' => 'error', 'label' => 'Apply error'],
    ['key' => 'rejected', 'label' => 'Rejected'],
    ['key' => 'skipped', 'label' => 'Skipped'],
]);

// Pagination defaults (always paginate; avoid SELECT * / huge loads)
define('DEFAULT_PAGE_SIZE', 200);
define('MAX_PAGE_SIZE', 500);

// Auto-refresh cadence (UI only; keep DB load reasonable)
define('DASHBOARD_REFRESH_SECONDS', 15);

// -----------------------------
// Debug flags (safe for v1 internal)
// -----------------------------
define('APP_DEBUG', in_array(APP_ENV, ['dev', 'test'], true));
