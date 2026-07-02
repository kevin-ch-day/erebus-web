<?php

declare(strict_types=1);

$baseUrl = getenv('BASE_URL') ?: 'http://localhost';
$baseUrl = rtrim($baseUrl, '/');
$url = $baseUrl . '/api.php/pipeline_status.php';

$response = @file_get_contents($url);
$errors = [];

if ($response === false) {
    $errors[] = sprintf('Request failed for %s', $url);
} else {
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = sprintf('Invalid JSON response: %s', json_last_error_msg());
    } else {
        foreach (['ok', 'data', 'meta'] as $key) {
            if (!array_key_exists($key, $decoded)) {
                $errors[] = sprintf('Missing required key: %s', $key);
            }
        }

        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        foreach (['pipeline', 'vt', 'recommendation', 'queue_lanes'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('Missing pipeline payload key: %s', $key);
            }
        }

        $queueLanes = is_array($data['queue_lanes'] ?? null) ? $data['queue_lanes'] : [];
        foreach (['lamda_pending', 'reservoir_pending'] as $key) {
            if (!array_key_exists($key, $queueLanes)) {
                $errors[] = sprintf('Missing pipeline.queue_lanes key: %s', $key);
            }
        }

        $pipeline = is_array($data['pipeline'] ?? null) ? $data['pipeline'] : [];
        foreach (['queue_pending', 'state_eligible_now'] as $key) {
            if (!array_key_exists($key, $pipeline)) {
                $errors[] = sprintf('Missing pipeline.pipeline key: %s', $key);
            }
        }

        $recommendation = is_array($data['recommendation'] ?? null) ? $data['recommendation'] : [];
        foreach (['action', 'summary'] as $key) {
            if (!array_key_exists($key, $recommendation)) {
                $errors[] = sprintf('Missing pipeline.recommendation key: %s', $key);
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("OK pipeline_status contract (%s)\n", $url));
exit(0);
