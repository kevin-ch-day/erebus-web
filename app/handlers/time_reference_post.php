<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app_config.php';
require_once __DIR__ . '/../lib/time.php';
require_once __DIR__ . '/../lib/url.php';

function time_reference_redirect(array $params = []): void
{
    header('Location: ' . page_url('time_reference', $params));
    exit;
}

$currentPrimary = tz_current_key();
$primaryInput = (string)($_POST['tz'] ?? $currentPrimary);
$primaryCanonical = tz_canonical_key($primaryInput);
$primaryKey = $primaryCanonical ?? $currentPrimary;

if (!tz_set_cookie($primaryKey)) {
    time_reference_redirect(['warn' => 'primary']);
}

$secondaryInput = (string)($_POST['tz_secondary'] ?? 'none');
$secondaryRaw = strtolower(trim($secondaryInput));
$secondaryValid = ($secondaryRaw === '' || $secondaryRaw === 'none')
    ? true
    : (tz_canonical_key($secondaryRaw) !== null && tz_canonical_key($secondaryRaw) !== $primaryKey);

if (!$secondaryValid || !tz_set_secondary_cookie($secondaryRaw)) {
    time_reference_redirect([
        'saved' => '1',
        'warn' => 'secondary',
    ]);
}

time_reference_redirect(['saved' => '1']);
