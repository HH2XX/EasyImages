<?php
/**
 * Short URL: /r.php?i=<code>
 * Fallback URL: /r.php?w=<hash> or /r.php?r=<hash>
 * Path URL: /r.php/<driver-alias>/<hash>
 * Full URL: /app/remote.php/<driver>/<hash>
 * Compatible with CDN rewrite rules.
 * Also supports legacy query string: ?storage=webdav&hash=...
 */

require __DIR__ . '/function.php';

// Parse from PATH_INFO, REQUEST_URI, or fallback to query string.
$pathInfo = isset($_SERVER['PATH_INFO']) ? trim((string) $_SERVER['PATH_INFO'], '/') : '';
if ($pathInfo === '' && !empty($_SERVER['REQUEST_URI'])) {
    $requestPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    foreach (array('/remote.php/', '/r.php/') as $marker) {
        $markerPos = $requestPath === false ? false : strpos($requestPath, $marker);
        if ($markerPos !== false) {
            $pathInfo = trim(substr($requestPath, $markerPos + strlen($marker)), '/');
            break;
        }
    }
}

if ($pathInfo !== '' && !isset($_GET['storage'])) {
    $segments = explode('/', $pathInfo, 2);
    $_GET['s'] = $segments[0];           // s = storage driver (short param)
    if (isset($segments[1])) {
        $_GET['h'] = $segments[1];       // h = hash (short param)
    }
}

if (isset($_GET['i'])) {
    $shortItem = function_exists('remote_storage_resolve_short_code') ? remote_storage_resolve_short_code($_GET['i']) : false;
    if (!$shortItem) {
        http_response_code(404);
        exit('File not found');
    }

    $storage = $shortItem['driver'];
    $hash = '';
    $path = $shortItem['path'];
} elseif (isset($_GET['w'])) {
    $storage = 'webdav';
    $hash = trim((string) $_GET['w']);
} elseif (isset($_GET['r'])) {
    $storage = 's3';
    $hash = trim((string) $_GET['r']);
} else {
    $storage = isset($_GET['s']) ? strtolower(trim((string) $_GET['s'])) : (isset($_GET['storage']) ? strtolower(trim((string) $_GET['storage'])) : '');
    $hash = isset($_GET['h']) ? trim((string) $_GET['h']) : (isset($_GET['hash']) ? trim((string) $_GET['hash']) : '');
}

if ($storage === 'w') {
    $storage = 'webdav';
} elseif ($storage === 's' || $storage === 'r') {
    $storage = 's3';
}

if (!in_array($storage, array('webdav', 's3'), true) || ($hash === '' && empty($path))) {
    http_response_code(400);
    exit('Bad request');
}

if (empty($path)) {
    $path = function_exists('remote_storage_hash_decode') ? remote_storage_hash_decode($hash) : urlHash($hash, 1);
}
if (!is_string($path) || $path === '') {
    http_response_code(404);
    exit('File not found');
}

$path = remote_storage_clean_path($path);
$uploadBase = '/' . trim(str_replace('\\', '/', (string) $config['path']), '/') . '/';
if (strpos($path, $uploadBase) !== 0) {
    http_response_code(403);
    exit('Permission denied');
}

$config['storage_driver'] = $storage;
$config['webdav_status'] = $storage === 'webdav' ? 1 : 0;
$config['s3_status']     = $storage === 's3' ? 1 : 0;
$config['ftp_status']    = 0;

try {
    $content = remote_storage_read($path, $storage);
    if ($content === false || $content === '') {
        http_response_code(404);
        exit('File not found');
    }
} catch (Exception $e) {
    error_log('Remote image read error: ' . $e->getMessage());
    http_response_code(502);
    exit('Remote storage unavailable');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = array(
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'bmp'  => 'image/bmp',
    'ico'  => 'image/x-icon',
    'svg'  => 'image/svg+xml',
    'jfif' => 'image/jpeg',
    'tif'  => 'image/tiff',
    'tiff' => 'image/tiff',
);

header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
header('Content-Length: ' . strlen($content));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
echo $content;
