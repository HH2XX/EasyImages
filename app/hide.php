<?php

/**
 * Program: EasyImage
 * Upstream Author: Icret
 * Maintainer: Community
 * Date: 2022/3/13 20:11
 * For: 源图保护解密
 */

require_once __DIR__ . '/function.php';

if (isset($_GET['key'])) {
    $hide_original = $_GET['key'];
    $real_path = resolve_upload_file(urlHash($hide_original, 1, crc32($config['hide_key'])));
} else {
    $real_path = APP_ROOT . '/public/images/404.png';
}

// 文件不存在
if (!is_file($real_path)) {
    $real_path = APP_ROOT . '/public/images/404.png';
}

// 获取文件后缀
if (!is_file($real_path)) {
    $real_path = APP_ROOT . '/public/images/404.png';
}
$ex = pathinfo($real_path, PATHINFO_EXTENSION);

// 设置头
header("Content-Type: image/" . $ex . ";text/html; charset=utf-8");

//输出文件
$mimeTypes = array(
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'bmp' => 'image/bmp',
    'ico' => 'image/x-icon',
    'svg' => 'image/svg+xml',
);
$extension = strtolower((string) pathinfo($real_path, PATHINFO_EXTENSION));
header('Content-Type: ' . (isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream'));
header('Content-Length: ' . filesize($real_path));
header('X-Content-Type-Options: nosniff');
readfile($real_path);

exit;
