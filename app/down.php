<?php

require_once __DIR__ . '/function.php';

$requestedPath = '';
if (isset($_GET['dw']) && is_string($_GET['dw'])) {
    $requestedPath = $_GET['dw'];
} elseif (isset($_GET['history']) && is_string($_GET['history'])) {
    $requestedPath = $_GET['history'];
}

if ($requestedPath === '') {
    http_response_code(400);
    exit('No file path');
}

$resolvedPath = resolve_upload_file($requestedPath);
if (isset($_GET['history']) && !empty($config['hide_path'])) {
    $resolvedPath = resolve_upload_file($config['path'] . ltrim($requestedPath, '/\\'));
}

if ($resolvedPath === false || !is_file($resolvedPath)) {
    http_response_code(404);
    exit('No File');
}

$extension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
$allowedExtensions = array_map('trim', explode(',', strtolower((string) $config['extensions'])));
$blockedExtensions = array('php', 'json', 'log', 'lock');

if (!in_array($extension, $allowedExtensions, true) || in_array($extension, $blockedExtensions, true)) {
    http_response_code(415);
    exit('Downfile Type Error');
}

$mimeType = function_exists('mime_content_type') ? mime_content_type($resolvedPath) : 'application/octet-stream';
if (!is_string($mimeType) || $mimeType === '') {
    $mimeType = 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode(basename($resolvedPath)));
header('Content-Length: ' . filesize($resolvedPath));
header('X-Content-Type-Options: nosniff');
readfile($resolvedPath);
