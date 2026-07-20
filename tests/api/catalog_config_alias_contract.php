<?php

declare(strict_types=1);

/**
 * Keeps Erebus Web's catalog configuration in agreement with Erebus Engine.
 *
 * The Engine-owned EREBUS_* names must win when a receiver host accidentally
 * retains older Web-only aliases. Legacy aliases remain a supported migration
 * path, so check those separately in a clean child process.
 */

$configPath = realpath(__DIR__ . '/../../app/database/db_config.php');
if ($configPath === false) {
    fwrite(STDERR, "FAIL: database configuration file was not found.\n");
    exit(1);
}

function resolve_catalog_config(string $configPath, array $environment): array
{
    $code = sprintf(
        'require %s; echo json_encode([DB_HOST, DB_PORT, DB_NAME, DB_USER, PERMISSION_INTEL_DB_NAME, db_config_contract_summary()["state"]]);',
        var_export($configPath, true)
    );
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-r', $code], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, $environment);

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start isolated PHP configuration check.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $decoded = json_decode($stdout, true);
    if ($exitCode !== 0 || !is_array($decoded)) {
        throw new RuntimeException('Configuration check failed: ' . trim($stderr));
    }

    return $decoded;
}

$cases = [
    'canonical names take precedence' => [
        'environment' => [
            'EREBUS_DB_HOST' => 'canonical-host',
            'EREBUS_DB_PORT' => '3307',
            'EREBUS_DB_NAME' => 'canonical_primary',
            'EREBUS_DB_USER' => 'canonical_user',
            'EREBUS_PERMISSION_INTEL_DB_NAME' => 'canonical_pi',
            'DB_HOST' => 'legacy-host',
            'DB_PORT' => '3308',
            'DB_NAME' => 'legacy_primary',
            'DB_USER' => 'legacy_user',
            'PERMISSION_INTEL_DB_NAME' => 'legacy_pi',
        ],
        'expected' => ['canonical-host', 3307, 'canonical_primary', 'canonical_user', 'canonical_pi', 'mixed_precedence'],
    ],
    'legacy aliases remain supported' => [
        'environment' => [
            'DB_HOST' => 'legacy-host',
            'DB_PORT' => '3308',
            'DB_NAME' => 'legacy_primary',
            'DB_USER' => 'legacy_user',
            'PERMISSION_INTEL_DB_NAME' => 'legacy_pi',
        ],
        'expected' => ['legacy-host', 3308, 'legacy_primary', 'legacy_user', 'legacy_pi', 'legacy_compatibility'],
    ],
];

$errors = [];
foreach ($cases as $label => $case) {
    try {
        $actual = resolve_catalog_config($configPath, $case['environment']);
        if ($actual !== $case['expected']) {
            $errors[] = sprintf('%s: unexpected resolved catalog configuration.', $label);
        }
    } catch (Throwable $error) {
        $errors[] = sprintf('%s: %s', $label, $error->getMessage());
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL: catalog configuration alias contract failed.\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}

echo "PASS: catalog configuration alias contract passed.\n";
