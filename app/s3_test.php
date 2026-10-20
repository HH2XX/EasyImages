<?php

require __DIR__ . '/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(array('code' => 405, 'msg' => 'Only POST requests are allowed'), JSON_UNESCAPED_UNICODE));
}

if (!validate_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    http_response_code(403);
    exit(json_encode(array('code' => 403, 'msg' => 'CSRF validation failed'), JSON_UNESCAPED_UNICODE));
}

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
        'msg'  => 'PHP curl 扩展未开启，无法检测 S3',
        'type' => 'danger',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

$s3Endpoint = isset($_POST['s3_endpoint']) ? trim((string) $_POST['s3_endpoint']) : '';
$s3Bucket = isset($_POST['s3_bucket']) ? trim((string) $_POST['s3_bucket']) : '';
$s3AccessKey = isset($_POST['s3_access_key']) ? trim((string) $_POST['s3_access_key']) : '';
$s3SecretKey = isset($_POST['s3_secret_key']) ? (string) $_POST['s3_secret_key'] : '';

if ($s3Endpoint === '' || $s3Bucket === '' || $s3AccessKey === '' || $s3SecretKey === '') {
    exit(json_encode(array(
        'code' => 422,
        'msg'  => '请先填写 Endpoint、Bucket、AccessKey 和 SecretKey',
        'type' => 'warning',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

if (!preg_match('#^https?://#i', $s3Endpoint)) {
    exit(json_encode(array(
        'code' => 422,
        'msg'  => 'Endpoint 必须以 http:// 或 https:// 开头',
        'type' => 'warning',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}

$config['s3_endpoint'] = $s3Endpoint;
$config['s3_bucket'] = $s3Bucket;
$config['s3_region'] = isset($_POST['s3_region']) ? trim((string) $_POST['s3_region']) : 'auto';
$config['s3_access_key'] = $s3AccessKey;
$config['s3_secret_key'] = $s3SecretKey;
$config['s3_path'] = isset($_POST['s3_path']) ? trim((string) $_POST['s3_path']) : '';
$config['s3_timeout'] = isset($_POST['s3_timeout']) ? max(1, (int) $_POST['s3_timeout']) : 30;
$config['s3_verify_ssl'] = !empty($_POST['s3_verify_ssl']) ? 1 : 0;
$config['s3_path_style'] = !empty($_POST['s3_path_style']) ? 1 : 0;

try {
    $client = s3_client_from_config();
    $client->test();

    exit(json_encode(array(
        'code' => 200,
        'msg'  => 'S3 连接成功，写入和删除权限可用',
        'type' => 'success',
        'icon' => 'ok-sign'
    ), JSON_UNESCAPED_UNICODE));
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $hint = '';

    if (stripos($errorMessage, 'unable to get local issuer certificate') !== false || stripos($errorMessage, 'SSL certificate problem') !== false) {
        $hint = '。这是 PHP cURL 的 CA 证书问题，可临时关闭“校验 HTTPS 证书”，更推荐给 php.ini 配置 curl.cainfo';
    } elseif (stripos($errorMessage, 'Bad access') !== false || stripos($errorMessage, 'Failed to connect') !== false) {
        $hint = '。这是服务器到 S3 Endpoint 的网络连接问题，请检查代理/TUN、防火墙、DNS 或服务器出站 443';
    } elseif (stripos($errorMessage, 'HTTP status: 403') !== false || stripos($errorMessage, 'HTTP status: 401') !== false) {
        $hint = '。这是鉴权或权限问题，请检查 AccessKey、SecretKey、Bucket 权限和 Region';
    }

    exit(json_encode(array(
        'code' => 502,
        'msg'  => 'S3 连接失败: ' . $errorMessage . $hint,
        'type' => 'danger',
        'icon' => 'exclamation-sign'
    ), JSON_UNESCAPED_UNICODE));
}
