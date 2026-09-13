<?php

declare(strict_types=1);

$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
header('Content-Type: application/json; charset=utf-8');

if ($path !== '/api/v1/pipeline/status') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'fixture route not found']);
    return;
}

echo json_encode([
    'pipeline' => [
        'queue_pending' => 7,
        'queue_processing' => 0,
        'state_eligible_now' => 3,
    ],
    'vt' => [
        'ready_keys' => 2,
        'quota_remaining' => 100,
    ],
    'recommendation' => [
        'action' => 'run_queue',
        'summary' => 'Deterministic CI fixture has pending work.',
        'command' => null,
    ],
    'queue_lanes' => [
        'lamda_pending' => 2,
        'reservoir_pending' => 5,
    ],
]);
