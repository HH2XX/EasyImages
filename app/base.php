<?php

if (!ob_get_level()) {
    ob_start();
}

/**
 * @author      Icret (upstream)
 * @maintainer  HH2XX
 * @project     EasyImage - 简单图床
 * @repository  https://github.com/HH2XX/EasyImages
 * @upstream    https://github.com/icret/EasyImages2.0
 * @Last        2026-10-20

 * 上传服务器后第一次打开会检查运行环境，请根据提示操作；
 * 检查环境仅会在第一开始开始出现，并在config目录下生成EasyImage.lock文件，如需再次查看请删除此文件。

 * 本项目由 HH2XX 维护，基于 Icret 的 EasyImages2.0 二次开发，并遵循 GPL-2.0 许可证。
 * 请勿将本程序用于违法违规用途；二次开发时请保留维护者、上游与第三方开源组件的版权声明。
 */

/*---------------基础配置开始-------------------*/

// 设置html为utf8
header('Content-Type:text/html;charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// 定义根目录
define('APP_ROOT', str_replace(DIRECTORY_SEPARATOR, '/', realpath(dirname(__FILE__) . '/../')));
// 时区设置 https://www.php.net/manual/zh/timezones.php
require_once APP_ROOT . '/config/config.php';
empty($config['timezone']) ? date_default_timezone_set('Asia/Shanghai') : date_default_timezone_set($config['timezone']);
// 修改内存限制 根据服务器配置选择，低于128M容易出现上传失败，你懂得图片挺占用内存的
ini_set('memory_limit', '512M');
if (empty($_SERVER['SERVER_ADDR'])) {
    $_SERVER['SERVER_ADDR'] = $_SERVER['SERVER_NAME'] ?? '127.0.0.1';
}
// 判断当前系统是否为windows
define('IS_WIN', strstr(PHP_OS, 'WIN') ? 1 : 0);
// 定义程序版本
define('APP_VERSION', '2.8.7');

/*---------------基础配置结束-------------------*/
