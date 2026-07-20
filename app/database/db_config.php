<?php
// app/database/db_config.php
// Load DB credentials from environment first, with explicit primary/PI catalog support.
//
// Erebus Engine owns the canonical shared names (EREBUS_DB_* and
// EREBUS_PERMISSION_INTEL_DB_*). The shorter DB_* and
// PERMISSION_INTEL_DB_* names remain supported for older Web-only installs,
// but deliberately have lower precedence. This prevents a receiver host from
// silently pointing the CLI and Web console at different catalogs.

declare(strict_types=1);

$GLOBALS['EREBUS_WEB_DB_ENV_FILES_LOADED'] = [];

function load_repo_env_file(string $path): void
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

    $loaded = $GLOBALS['EREBUS_WEB_DB_ENV_FILES_LOADED'] ?? [];
    if (is_array($loaded) && !in_array($path, $loaded, true)) {
        $loaded[] = $path;
        $GLOBALS['EREBUS_WEB_DB_ENV_FILES_LOADED'] = $loaded;
    }
}

if (defined('APP_ROOT')) {
    load_repo_env_file(APP_ROOT . '/.env');
    load_repo_env_file(APP_ROOT . '/.env.local');
}

function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function env_first_or_default(array $keys, string $default): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

function mysql_client_defaults(): array
{
    static $defaults = null;
    if (is_array($defaults)) {
        return $defaults;
    }

    $defaults = [];
    $home = getenv('HOME');
    if ($home === false || $home === '') {
        return $defaults;
    }

    $candidatePaths = [rtrim($home, '/') . '/.my.cnf'];
    if (defined('APP_ROOT')) {
        $candidatePaths[] = APP_ROOT . '/.my.cnf';
    }

    $path = '';
    foreach ($candidatePaths as $candidatePath) {
        if (is_readable($candidatePath)) {
            $path = $candidatePath;
            break;
        }
    }
    if ($path === '') {
        return $defaults;
    }

    $parsed = @parse_ini_file($path, true, INI_SCANNER_RAW);
    if (!is_array($parsed)) {
        return $defaults;
    }

    $client = $parsed['client'] ?? [];
    if (!is_array($client)) {
        return $defaults;
    }

    foreach (['host', 'port', 'user', 'password', 'socket'] as $key) {
        if (array_key_exists($key, $client) && $client[$key] !== '') {
            $defaults[$key] = (string)$client[$key];
        }
    }

    return $defaults;
}

function db_config_present_environment_keys(array $keys): array
{
    $present = [];
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            $present[] = $key;
        }
    }
    return $present;
}

function db_config_contract_summary(): array
{
    $canonical = db_config_present_environment_keys([
        'EREBUS_DB_HOST',
        'EREBUS_DB_PORT',
        'EREBUS_DB_NAME',
        'EREBUS_DB_USER',
        'EREBUS_DB_PASSWORD',
        'EREBUS_DB_SOCKET',
        'EREBUS_PERMISSION_INTEL_DB_NAME',
        'EREBUS_PERMISSION_INTEL_DB_HOST',
        'EREBUS_PERMISSION_INTEL_DB_PORT',
        'EREBUS_PERMISSION_INTEL_DB_USER',
        'EREBUS_PERMISSION_INTEL_DB_PASSWORD',
        'EREBUS_PERMISSION_INTEL_DB_SOCKET',
    ]);
    $legacy = db_config_present_environment_keys([
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'DB_SOCKET',
        'PERMISSION_INTEL_DB_NAME',
        'PERMISSION_INTEL_DB_HOST',
        'PERMISSION_INTEL_DB_PORT',
        'PERMISSION_INTEL_DB_USER',
        'PERMISSION_INTEL_DB_PASS',
        'PERMISSION_INTEL_DB_SOCKET',
        'ANDROID_PERMISSION_INTEL_DB_NAME',
    ]);

    $state = $canonical !== []
        ? ($legacy !== [] ? 'mixed_precedence' : 'canonical')
        : ($legacy !== [] ? 'legacy_compatibility' : 'defaults_only');

    return [
        'state' => $state,
        'canonical_keys_present' => $canonical,
        'legacy_alias_keys_present' => $legacy,
    ];
}

// Public health responses may use this; never add connection values or local paths.
function db_config_diagnostics(): array
{
    return [
        'app_env' => defined('APP_ENV') ? (string)APP_ENV : '',
        'configuration_contract' => db_config_contract_summary(),
        'primary_catalog' => db_primary_catalog_name(),
        'permission_intel_catalog' => db_permission_intel_catalog_name(),
        'split_enabled' => db_permission_intel_split_enabled(),
        'socket_configured' => ((string)DB_SOCKET !== ''),
        'permission_intel_connection_matches_primary' => db_permission_intel_connection_matches_primary(),
    ];
}

function env_first_or_mysql_default(array $keys, string $default, ?string $mysqlKey = null): string
{
    $value = env_first_or_default($keys, '');
    if ($value !== '') {
        return $value;
    }

    if ($mysqlKey !== null) {
        $defaults = mysql_client_defaults();
        if (isset($defaults[$mysqlKey]) && $defaults[$mysqlKey] !== '') {
            return (string)$defaults[$mysqlKey];
        }
    }

    return $default;
}

define('DB_HOST', env_first_or_mysql_default(['EREBUS_DB_HOST', 'DB_HOST'], '127.0.0.1', 'host'));
define('DB_PORT', (int)env_first_or_mysql_default(['EREBUS_DB_PORT', 'DB_PORT'], '3306', 'port'));
define('DB_NAME', env_first_or_default(['EREBUS_DB_NAME', 'DB_NAME'], 'erebus_threat_intel_prod'));
define('DB_USER', env_first_or_mysql_default(['EREBUS_DB_USER', 'DB_USER'], 'root', 'user'));
define('DB_PASS', env_first_or_mysql_default(['EREBUS_DB_PASSWORD', 'DB_PASS'], '', 'password'));
define('DB_SOCKET', env_first_or_mysql_default(['EREBUS_DB_SOCKET', 'DB_SOCKET', 'MYSQL_UNIX_PORT'], '', 'socket'));
define(
    'PERMISSION_INTEL_DB_NAME_RAW',
    env_first_or_default(
        ['EREBUS_PERMISSION_INTEL_DB_NAME', 'ANDROID_PERMISSION_INTEL_DB_NAME', 'PERMISSION_INTEL_DB_NAME'],
        ''
    )
);
define('PERMISSION_INTEL_DB_NAME', PERMISSION_INTEL_DB_NAME_RAW !== '' ? PERMISSION_INTEL_DB_NAME_RAW : DB_NAME);
define('PERMISSION_INTEL_DB_HOST', env_first_or_default(['EREBUS_PERMISSION_INTEL_DB_HOST', 'PERMISSION_INTEL_DB_HOST'], DB_HOST));
define('PERMISSION_INTEL_DB_PORT', (int)env_first_or_default(['EREBUS_PERMISSION_INTEL_DB_PORT', 'PERMISSION_INTEL_DB_PORT'], (string)DB_PORT));
define('PERMISSION_INTEL_DB_USER', env_first_or_default(['EREBUS_PERMISSION_INTEL_DB_USER', 'PERMISSION_INTEL_DB_USER'], DB_USER));
define('PERMISSION_INTEL_DB_PASS', env_first_or_default(['EREBUS_PERMISSION_INTEL_DB_PASSWORD', 'PERMISSION_INTEL_DB_PASS'], DB_PASS));
define('PERMISSION_INTEL_DB_SOCKET', env_first_or_default(['EREBUS_PERMISSION_INTEL_DB_SOCKET', 'PERMISSION_INTEL_DB_SOCKET'], DB_SOCKET));

function db_primary_catalog_name(): string
{
    return (string)DB_NAME;
}

function db_permission_intel_catalog_name(): string
{
    static $resolved = null;
    if (is_string($resolved) && $resolved !== '') {
        return $resolved;
    }

    if (PERMISSION_INTEL_DB_NAME_RAW !== '') {
        $resolved = (string)PERMISSION_INTEL_DB_NAME;
        return $resolved;
    }

    $resolved = db_auto_detect_permission_intel_catalog_name();
    return $resolved;
}

function db_permission_intel_split_enabled(): bool
{
    return db_permission_intel_catalog_name() !== db_primary_catalog_name();
}

function db_permission_intel_connection_matches_primary(): bool
{
    return
        (string)PERMISSION_INTEL_DB_HOST === (string)DB_HOST &&
        (int)PERMISSION_INTEL_DB_PORT === (int)DB_PORT &&
        (string)PERMISSION_INTEL_DB_USER === (string)DB_USER &&
        (string)PERMISSION_INTEL_DB_PASS === (string)DB_PASS &&
        (string)PERMISSION_INTEL_DB_SOCKET === (string)DB_SOCKET;
}

function db_probe_pdo(): ?PDO
{
    static $pdo = null;
    static $attempted = false;
    if ($attempted) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $attempted = true;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    $candidates = [];
    if (DB_SOCKET !== '') {
        $candidates[] = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', DB_SOCKET, DB_NAME);
    }
    $candidates[] = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, (int)DB_PORT, DB_NAME);
    if (DB_SOCKET === '' && in_array((string)DB_HOST, ['127.0.0.1', 'localhost'], true)) {
        foreach (['/run/mysqld/mysqld.sock', '/var/run/mysqld/mysqld.sock', '/run/mariadb/mariadb.sock', '/var/lib/mysql/mysql.sock'] as $socketPath) {
            if (file_exists($socketPath)) {
                $candidates[] = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socketPath, DB_NAME);
            }
        }
    }

    foreach (array_values(array_unique($candidates)) as $dsn) {
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (Throwable $ignored) {
            // best-effort detection only
        }
    }

    return null;
}

function db_auto_detect_permission_intel_catalog_name(): string
{
    $pdo = db_probe_pdo();
    if (!$pdo instanceof PDO) {
        return (string)DB_NAME;
    }

    $coreTables = [
        'android_permission_obs_sample',
        'android_permission_dict_unknown',
        'android_permission_dict_queue',
        'android_permission_enrich_vt_event',
    ];

    $placeholders = implode(',', array_fill(0, count($coreTables), '?'));
    $sql = "
        SELECT table_schema, COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema IN (?, ?)
          AND table_name IN ({$placeholders})
        GROUP BY table_schema
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $params = array_merge([DB_NAME, 'android_permission_intel'], $coreTables);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)($row['table_schema'] ?? '')] = (int)($row['table_count'] ?? 0);
        }
        $primaryCount = (int)($counts[DB_NAME] ?? 0);
        $splitCount = (int)($counts['android_permission_intel'] ?? 0);
        if ($primaryCount === 0 && $splitCount > 0) {
            return 'android_permission_intel';
        }
    } catch (Throwable $ignored) {
        return (string)DB_NAME;
    }

    return (string)DB_NAME;
}

function db_is_permission_intel_table(string $table): bool
{
    return str_starts_with($table, 'android_permission_')
        || str_starts_with($table, 'v_android_permission_')
        || str_starts_with($table, 'vw_permission_')
        || str_starts_with($table, 'android_attack_')
        || str_starts_with($table, 'permission_governance_')
        || str_starts_with($table, 'permission_signal_');
}

function db_table_catalog_name(string $table): string
{
    return db_is_permission_intel_table($table) ? db_permission_intel_catalog_name() : db_primary_catalog_name();
}

function db_quote_identifier(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

function db_catalog_table(string $table): string
{
    $catalog = db_table_catalog_name($table);
    return db_quote_identifier($catalog) . '.' . db_quote_identifier($table);
}
