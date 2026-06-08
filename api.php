<?php
declare(strict_types=1);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/api.php'));
$baseDir = rtrim(dirname($scriptName), '/');
$target = ($baseDir === '' ? '' : $baseDir) . '/public/api.php';
$pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');
$query = (string)($_SERVER['QUERY_STRING'] ?? '');

if ($pathInfo !== '') {
    $target .= $pathInfo;
}

if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 307);
exit;
