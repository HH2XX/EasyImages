<?php

namespace Verot\Upload;

require_once __DIR__ . '/../app/function.php';
require_once APP_ROOT . '/app/class.upload.php';
require_once APP_ROOT . '/config/api_key.php';

// 允许跨域 https://stackoverflow.com/questions/8719276/cross-origin-request-headerscors-with-php-headers
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
// 定义返回头信息为Json
header("Content-type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    exit(json_encode(array(
        'result' => 'failed',
        'code' => 405,
        'message' => 'Only POST requests are allowed',
    ), JSON_UNESCAPED_UNICODE));
}

// 无文件
if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
    exit(json_encode(
        array(
            "result"  =>  "failed",
            "code"    =>  204,
            "message" =>  "没有选择上传的文件",
        ),
        JSON_UNESCAPED_UNICODE
    ));
}

if (!isset($_FILES['image']['error']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode(array(
        'result' => 'failed',
        'code' => 400,
        'message' => 'Upload failed',
    ), JSON_UNESCAPED_UNICODE));
}

// 黑/白IP名单上传
if ($config['check_ip']) {
    if (checkIP(null, $config['check_ip_list'], $config['check_ip_model'])) {
        // 上传错误 code:205 未授权IP
        exit(json_encode(array(
            "result"  =>  "failed",
            "code"    =>  205,
            "message" =>  "黑名单内或白名单外用户不允许上传",
        ), JSON_UNESCAPED_UNICODE));
    }
}

// 获取Token并过滤非字母数字, 删除空格
$token = isset($_POST['token']) && is_string($_POST['token']) ? trim($_POST['token']) : '';

// 检查api合法性
check_api($token);
check_api($token);
$tokenID = isset($tokenList[$token]['id']) ? (int) $tokenList[$token]['id'] : 0;

// 分片上传
if (!$config['chunks']) {
    $handle = new Upload($_FILES['image'], 'zh_CN');
} else {
    $chunkName = isset($_POST['name']) && is_string($_POST['name']) ? $_POST['name'] : '';
    $chunk = chunk($chunkName);
    if ($chunk === null) {
        exit(json_encode(array(
            "result" => "success",
            "code" => 201,
            "message" => "分片已接收",
        ), JSON_UNESCAPED_UNICODE));
    }
    $handle = new Upload($chunk, 'zh_CN');
}

if ($handle->uploaded) {
    // 允许上传的mime类型
    if ($config['allowed']) {
        $handle->allowed = array('image/*');
    }

    // SVG is disabled by default because blacklist-only sanitization is unsafe.
    if ($handle->file_src_name_ext === 'svg') {
        if (empty($config['allow_svg'])) {
            exit(json_encode(array(
                "result" => "failed",
                "code" => 415,
                "message" => "SVG 上传已关闭，请转换为 PNG 或 WebP。",
            ), JSON_UNESCAPED_UNICODE));
        }
        $svg = file_get_contents($handle->file_src_pathname);
        if (preg_match('/<script[\s\S]*?<\/script>/', $svg) || stripos($svg, 'href=')) {
            exit(json_encode(
                array(
                    "result"  => "failed",
                    "code"    => 205,
                    "message" => "请勿上传非法文件",
                ),
                JSON_UNESCAPED_UNICODE
            ));
        }
    }

    // 文件命名
    $handle->file_new_name_body = imgName($handle->file_src_name_body);
    // 添加Token ID
    $handle->file_name_body_add = '-' . $tokenID;
    // 最大上传限制
    $handle->file_max_size = $config['maxSize'];
    // 最大宽度
    $handle->image_max_width = $config['maxWidth'];
    // 最大高度
    $handle->image_max_height = $config['maxHeight'];
    // 最小宽度
    $handle->image_min_width = $config['minWidth'];
    // 最小高度
    $handle->image_min_height = $config['minHeight'];
    // 2023-01-06 转换图片为指定格式 只转换非webp格式和非动态图片
    if ($handle->file_src_name_ext !== 'webp' && !isGifAnimated($handle->file_src_pathname)) {
        $handle->image_convert = $config['imgConvert'];
    }
    // 2023-01-06 PNG 图像的压缩级别，介于 1（快速但大文件）和 9（慢但较小文件）之间
    $handle->png_compression = 9 - round($config['compress_ratio'] / 11.2);
    // WEBP 图像的压缩质量 1-100
    $handle->webp_quality = $config['compress_ratio'];
    // JPEG 图像的压缩质量 1-100
    $handle->jpeg_quality = $config['compress_ratio'];
    // 默认目录
    $Img_path = config_path();
    //Token分离
    if ($config['token_path_status']) {
        $Img_path = config_path($tokenID . date('/Y/m/d/'));
    }
    // 存储图片路径:images/201807/
    $handle->process(APP_ROOT . $Img_path);

    // 图片完整相对路径:/i/2021/05/03/k88e7p.jpg
    if ($handle->processed) {
        // 黑名单文件 - 通过MD5检测
        if ($config['md5_black']) {
            $befor_upload_file_md5 = md5_file($handle->file_src_pathname);
            $after_upload_file_md5 = md5_file($handle->file_dst_pathname);
            if (stristr($config['md5_blacklist'], $befor_upload_file_md5) || stristr($config['md5_blacklist'], $after_upload_file_md5)) {
                if (file_exists($handle->file_dst_pathname)) unlink($handle->file_dst_pathname);
                exit(json_encode(
                    array(
                        "result"  => "failed",
                        "code"    => 205,
                        "message" => "当前文件禁止上传",
                    ),
                    JSON_UNESCAPED_UNICODE
                ));
            }
        }
        // 图片相对路径
        $pathIMG = $Img_path . $handle->file_dst_name;
        $selectedStorageDriver = function_exists('remote_storage_driver') ? remote_storage_driver() : 'local';
        $directRemoteStorage = in_array($selectedStorageDriver, array('webdav', 's3'), true);
        // 图片访问网址
        $imageUrl = $directRemoteStorage
            ? remote_storage_public_url($pathIMG, $selectedStorageDriver)
            : rand_imgurl() . $pathIMG;
        // 后续处理地址
        $processUrl = $config['domain'] . $pathIMG;
        // 隐藏config文件中的path目录,需要搭配网站设置
        if ($config['hide_path']) {
            $imageUrl = str_replace($config['path'], '/', $imageUrl);
        }
        // 源图保护 key值是由crc32加密的hide_key
        if ($config['hide'] && !$directRemoteStorage) {
            $imageUrl = $config['domain'] . '/app/hide.php?key=' . urlHash($pathIMG, 0, crc32($config['hide_key']));
        }
        // 删除文件链接
        if ($config['show_user_hash_del']) {
            $delUrl = $config['domain'] . '/app/del.php?hash=' . urlHash($pathIMG, 0);
            if ($directRemoteStorage) {
                $delUrl .= '&storage=' . $selectedStorageDriver;
            }
        } else {
            $delUrl = "Admin closed user delete";
        }
        // 当设置访问生成缩略图时自动生成 2022-12-30 修正 2023-01-30
        $handleThumb = $config['domain'] . '/app/thumb.php?img=' . $pathIMG;
        if ($config['thumbnail'] === 2) {
            // 自定义缩略图长宽
            $handle->image_resize = true;
            $handle->image_x = $config['thumbnail_w'];
            $handle->image_y = $config['thumbnail_h'];
            // 如果调整后的图像大于原始图像，则取消调整大小，以防止放大
            $handle->image_no_enlarging = true;
            $handle->file_new_name_body = date('Y_m_d_') . $handle->file_dst_name_body;
            $handle->process(APP_ROOT . $config['path'] . 'cache/');
            $handleThumb = $config['domain'] . $config['path'] . 'cache/' . $handle->file_dst_name;
        }

        if ($directRemoteStorage) {
            $handleThumb = $imageUrl;
            @water($handle->file_dst_pathname);
            @process_compress($handle->file_dst_pathname);
            @write_ip_upload_count_logs();
            @write_upload_logs($pathIMG, $handle->file_src_name, $handle->file_dst_pathname, $handle->file_src_size, $tokenID);
            @process_checkImg($processUrl);

            $oldCompleteDelLocal = isset($config['ftp_complete_del_local']) ? $config['ftp_complete_del_local'] : 0;
            $config['ftp_complete_del_local'] = 1;
            $remoteUploaded = any_upload($pathIMG, APP_ROOT . $pathIMG, 'upload');
            $config['ftp_complete_del_local'] = $oldCompleteDelLocal;
            if (!$remoteUploaded) {
                unset($handle);
                exit(json_encode(array(
                    "result" => "failed",
                    "code" => 502,
                    "message" => "远程存储上传失败，请检查存储配置和目录权限。",
                ), JSON_UNESCAPED_UNICODE));
            }
        }

        // 上传成功后返回json数据
        $reJson = array(
            "result"  => "success",
            "code"    => 200,
            "url"     => $imageUrl,
            "srcName" => $handle->file_src_name_body,
            "thumb"   => $handleThumb,
            "del"     => $delUrl,
            "id"      => $tokenID,  // 2023-02-11 增加返回Token ID
            "message" => "success", // 2023-04-22 增加返回信息
            "storage" => $selectedStorageDriver,
            // "memory" => getDistUsed(memory_get_peak_usage()), // 占用内存 2023-02-12
        );
        echo json_encode($reJson, JSON_UNESCAPED_UNICODE);
        $handle->clean();
    } else {
        // 上传错误 code:206 客户端文件有问题
        $reJson = array(
            "result"  => "failed",
            "code"    => 400,
            "srcName" => $handle->file_src_name_body, // 2023-04-03 原始上传文件名称
            "id"      => $tokenID, // 2023-04-03 增加 Token ID
            "message" => $handle->error,
            "memory"  => getDistUsed(memory_get_peak_usage()), // 占用内存 2023-02-12
            // 'log' => $handle->log, // 仅用作调试用
        );
        unset($handle);
        exit(json_encode($reJson, JSON_UNESCAPED_UNICODE));
    }

    /** 后续处理 */
    if (!$directRemoteStorage) {
        // 使用fastcgi_finish_request操作
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        // 同IP上传日志
        @write_ip_upload_count_logs();
        // 日志
        @write_upload_logs($pathIMG, $handle->file_src_name, $handle->file_dst_pathname, $handle->file_src_size);
        // 鉴黄
        @process_checkImg($processUrl);
        // 水印
        @water($handle->file_dst_pathname);
        // 压缩
        @process_compress($handle->file_dst_pathname);
        // 上传至其他位置
        @any_upload($pathIMG, APP_ROOT . $pathIMG, 'upload');
    }

    unset($handle);
}
