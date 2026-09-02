<?php

require __DIR__ . '/function.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_who_login('admin')) {
    http_response_code(403);
    exit(json_encode(array(
        'code' => 403,
        'msg'  => '请先使用管理员账户登录',
        'type' => 'danger',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

if (!extension_loaded('curl')) {
    exit(json_encode(array(
        'code' => 500,
        'msg'  => 'PHP curl 扩展未开启，无法检测 WebDAV',
        'type' => 'danger',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

$webdavUrl = isset($_POST['webdav_url']) ? trim((string) $_POST['webdav_url']) : '';
if ($webdavUrl === '') {
    exit(json_encode(array(
        'code' => 422,
        'msg'  => '请先填写 WebDAV 地址',
        'type' => 'warning',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

if (!preg_match('#^https?://#i', $webdavUrl)) {
    exit(json_encode(array(
        'code' => 422,
        'msg'  => 'WebDAV 地址必须以 http:// 或 https:// 开头',
        'type' => 'warning',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

$config['webdav_url'] = $webdavUrl;
$config['webdav_user'] = isset($_POST['webdav_user']) ? trim((string) $_POST['webdav_user']) : '';
$config['webdav_pass'] = isset($_POST['webdav_pass']) ? (string) $_POST['webdav_pass'] : '';
$config['webdav_path'] = isset($_POST['webdav_path']) ? trim((string) $_POST['webdav_path']) : '';
$config['webdav_timeout'] = isset($_POST['webdav_timeout']) ? max(1, (int) $_POST['webdav_timeout']) : 30;
$config['webdav_verify_ssl'] = !empty($_POST['webdav_verify_ssl']) ? 1 : 0;

try {
    $client = webdav_client_from_config();
    $client->test();

    exit(json_encode(array(
        'code' => 200,
        'msg'  => 'WebDAV 连接成功，地址、账号和目录权限可用',
        'type' => 'success',
        'icon' => 'ok-sign'
    ), JSON_UNESCAPED_UNICODE));
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $hint = '';

    if (stripos($errorMessage, 'unable to get local issuer certificate') !== false || stripos($errorMessage, 'SSL certificate problem') !== false) {
        $hint = '。这是 PHP cURL 的 CA 证书问题，可临时关闭“校验 HTTPS 证书”，更推荐给 php.ini 配置 curl.cainfo';
    } elseif (stripos($errorMessage, 'Bad access') !== false || stripos($errorMessage, 'Failed to connect') !== false) {
        $hint = '。这是服务器到 WebDAV 地址的网络连接问题，请检查代理/TUN、防火墙、DNS 或服务器出站 443';
    } elseif (stripos($errorMessage, 'HTTP status: 403') !== false || stripos($errorMessage, 'HTTP status: 401') !== false) {
        $hint = '。这是鉴权或权限问题，请检查账号、密码/Token 和目录权限';
    }

    exit(json_encode(array(
        'code' => 502,
        'msg'  => 'WebDAV 连接失败: ' . $errorMessage . $hint,
        'type' => 'danger',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}
