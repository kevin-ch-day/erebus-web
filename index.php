<?php
declare(strict_types=1);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$baseDir = rtrim(dirname($scriptName), '/');
$target = ($baseDir === '' ? '' : $baseDir) . '/public/index.php';
$query = (string)($_SERVER['QUERY_STRING'] ?? '');

if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 302);
exit;
