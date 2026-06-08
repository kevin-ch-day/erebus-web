<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/family_taxonomy_queue_export.php?limit=5';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $lines = preg_split("/\\r\\n|\\n|\\r/", trim($response));
    $headerLine = $lines[0] ?? '';
    if ($headerLine === '') {
        $errors[] = 'Missing CSV header row.';
    } else {
        $headers = str_getcsv($headerLine);
        foreach ([
            'sample_id',
            'sha256',
            'family_label',
            'popular_threat_name',
            'issue_kind',
            'suggested_fix_action',
            'decision_mode',
            'review_lane',
        ] as $required) {
            if (!in_array($required, $headers, true)) {
                $errors[] = sprintf('Missing CSV header %s.', $required);
            }
        }
    }
}

if ($errors !== []) {
    echo "FAIL: family taxonomy queue export contract failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: family taxonomy queue export contract passed.\n";
exit(0);
