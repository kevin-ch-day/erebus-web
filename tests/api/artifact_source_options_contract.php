<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/lib/app_config.php';
require_once __DIR__ . '/../../app/lib/url.php';
require_once __DIR__ . '/../../app/lib/artifact_sources.php';

$errors = [];
$values = artifact_source_allowed_values();

foreach (['csv', 'ioc_repo', 'sample_repo', 'vendor_report', 'research_blog', 'sandbox_report', 'manual', 'other'] as $required) {
    if (!in_array($required, $values, true)) {
        $errors[] = sprintf('Missing required artifact source value: %s', $required);
    }
}

if (artifact_source_is_valid('') !== false) {
    $errors[] = 'Expected empty artifact source to be invalid.';
}

if (artifact_source_is_valid('definitely_not_a_real_source') !== false) {
    $errors[] = 'Expected unknown artifact source to be invalid.';
}

if (artifact_source_is_valid('csv') !== true) {
    $errors[] = 'Expected csv artifact source to be valid.';
}

$html = render_artifact_source_options('csv');
if (strpos($html, 'value="csv" selected') === false) {
    $errors[] = 'Expected render_artifact_source_options to mark csv as selected.';
}

if ($errors !== []) {
    echo "FAIL: artifact source options contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: artifact source options contract passed.\n";
exit(0);
