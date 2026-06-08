<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$errors = [];

$cases = [
    'active' => [
        'url' => $baseUrl . '/api.php/android_permission_intelligence.php?mode=triage&limit=25&page_size=25&view=active',
        'expect_current_rows' => true,
        'expect_governed_rows' => false,
        'expect_ledger_rows' => false,
    ],
    'governed' => [
        'url' => $baseUrl . '/api.php/android_permission_intelligence.php?mode=triage&limit=25&page_size=25&view=governed',
        'expect_current_rows' => true,
        'expect_governed_rows' => true,
        'expect_ledger_rows' => false,
    ],
    'ledger' => [
        'url' => $baseUrl . '/api.php/android_permission_intelligence.php?mode=triage&limit=25&page_size=25&view=ledger',
        'expect_current_rows' => true,
        'expect_governed_rows' => false,
        'expect_ledger_rows' => true,
    ],
];

foreach ($cases as $label => $case) {
    $response = @file_get_contents($case['url']);
    if ($response === false) {
        $errors[] = sprintf('Request failed for %s lane.', $label);
        continue;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response for %s lane: %s', $label, json_last_error_msg());
        continue;
    }

    $payload = $decoded['data'] ?? [];
    $meta = $decoded['meta'] ?? [];
    $currentRows = $payload['current_evidence_review_rows'] ?? null;
    $governedRows = $payload['governed_current_unknown_rows'] ?? null;
    $ledgerRows = $payload['ledger_diagnostic_rows'] ?? null;
    $currentPage = is_array($payload['current_evidence_review_page'] ?? null) ? $payload['current_evidence_review_page'] : [];
    $governedPage = is_array($payload['governed_current_unknown_page'] ?? null) ? $payload['governed_current_unknown_page'] : [];
    $ledgerPage = is_array($payload['ledger_diagnostic_page'] ?? null) ? $payload['ledger_diagnostic_page'] : [];

    if (($meta['triage_view'] ?? null) !== $label) {
        $errors[] = sprintf('Expected meta.triage_view=%s for %s lane.', $label, $label);
    }
    if (!is_array($currentRows)) {
        $errors[] = sprintf('Expected current_evidence_review_rows to be an array for %s lane.', $label);
    } elseif (
        $case['expect_current_rows']
        && (int)($currentPage['total_count'] ?? 0) > 0
        && count($currentRows) < 1
    ) {
        $errors[] = sprintf('Expected current_evidence_review_rows to be populated for %s lane.', $label);
    }

    if (!is_array($governedRows)) {
        $errors[] = sprintf('Expected governed_current_unknown_rows to be an array for %s lane.', $label);
    } elseif (
        $case['expect_governed_rows']
        && (int)($governedPage['total_count'] ?? 0) > 0
        && count($governedRows) < 1
    ) {
        $errors[] = sprintf('Expected governed_current_unknown_rows to be populated for %s lane.', $label);
    } elseif (!$case['expect_governed_rows'] && count($governedRows) !== 0) {
        $errors[] = sprintf('Expected governed_current_unknown_rows to stay empty for %s lane.', $label);
    }

    if (!is_array($ledgerRows)) {
        $errors[] = sprintf('Expected ledger_diagnostic_rows to be an array for %s lane.', $label);
    } elseif (
        $case['expect_ledger_rows']
        && (int)($ledgerPage['total_count'] ?? 0) > 0
        && count($ledgerRows) < 1
    ) {
        $errors[] = sprintf('Expected ledger_diagnostic_rows to be populated for %s lane.', $label);
    } elseif (!$case['expect_ledger_rows'] && count($ledgerRows) !== 0) {
        $errors[] = sprintf('Expected ledger_diagnostic_rows to stay empty for %s lane.', $label);
    }
}

if ($errors !== []) {
    echo "FAIL: permission intelligence lane-scope contract check failed.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "PASS: permission intelligence lane-scope contract check passed.\n";
exit(0);
