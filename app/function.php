<?php
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/WaterMask.php';
require_once APP_ROOT . '/config/config.guest.php';

/**
 * 判断GIF图片是否为动态
 * @param $filename string 文件
 * @return int 是|否
 */
function isGifAnimated($filename)
{
    return file_contains_marker($filename, chr(0x21) . chr(0xff) . chr(0x0b) . 'NETSCAPE2.0') ? 1 : 0;
}

/** Scan a file incrementally so large uploads do not consume another full copy in memory. */
function file_contains_marker($filename, $marker, $chunkSize = 8192)
{
    if (!is_string($filename) || !is_string($marker) || $marker === '' || !is_file($filename)) {
        return false;
    }

    $handle = @fopen($filename, 'rb');
    if ($handle === false) {
        return false;
    }

    $carry = '';
    $carryLength = max(0, strlen($marker) - 1);
    while (!feof($handle)) {
        $data = fread($handle, $chunkSize);
        if ($data === false || $data === '') {
            break;
        }

        $buffer = $carry . $data;
        if (strpos($buffer, $marker) !== false) {
            fclose($handle);
            return true;
        }

        $carry = $carryLength > 0 ? substr($buffer, -$carryLength) : '';
    }

    fclose($handle);
    return false;
}

/**
 * 判断webp是否为动态图片
 * @param string $src 图像文件
 * @return bool 是|否
 */
function isWebpAnimated($src)
{
    return file_contains_marker($src, 'ANMF');

    /* 2023-01-24 判断webp是否为动画
    $result = false;
    $fh = fopen($src, "rb");
    fseek($fh, 12);
    if (fread($fh, 4) === 'VP8X') {
        fseek($fh, 16);
        $myByte = fread($fh, 1);
        $result = ((ord($myByte) >> 1) & 1) ? true : false;
    }
    fclose($fh);
    return $result;
    */
}

/**
 * 判断webp或gif动图是否为动态图片
 * @param $src 图片的绝对路径
 * @return bool 是|否
 */
function is_Gif_Webp_Animated($src)
{
    if (!is_string($src) || !is_file($src)) {
        return false;
    }

    $ext = strtolower((string) pathinfo($src, PATHINFO_EXTENSION));

    if ($ext == 'webp') {
        return file_contains_marker($src, 'ANMF');
    }

    return file_contains_marker($src, chr(0x21) . chr(0xff) . chr(0x0b) . 'NETSCAPE2.0');
}


/**
 * 2023-01-06 校验登录
 * @param $user String 登录用户名
 * @param $password 登录密码
 * 返回参数解析 code=>状态码 200成功，400失败; 登录用户级别level => 0无状态, 1管理员, 2上传者, messege => 提示信息
 */

function _login($user = null, $password = null)
{
    global $config;
    global $guestConfig;

    ensure_auth_session();
    if ($user === null && $password === null) {
        $session_auth = get_auth_session();
        if ($session_auth) {
            if ($session_auth[0] === $config['user']) {
                return auth_secret_matches($config['password'], $session_auth[1])
                    ? json_encode(array('code' => 200, 'level' => 1, 'messege' => 'admin'))
                    : json_encode(array('code' => 400, 'level' => 0, 'messege' => 'password error'));
            }

            if (!array_key_exists($session_auth[0], $guestConfig)) {
                return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'account not found'));
            }

            if (!auth_secret_matches($guestConfig[$session_auth[0]]['password'], $session_auth[1])) {
                return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'password error'));
            }

            if ($guestConfig[$session_auth[0]]['expired'] < time()) {
                return json_encode(array('code' => 400, 'level' => 0, 'messege' => $session_auth[0] . ' expired'));
            }

            return json_encode(array('code' => 200, 'level' => 2, 'messege' => $session_auth[0] . ' logged in'));
        }
    }

    // cookie验证
    if ($user === null and $password === null) {
        // 无cookie
        if (empty($_COOKIE['auth'])) {
            return json_encode(array('code' => 400, 'level' => 0, 'messege' => '请登录'));
        }
        // 存在cookie
        if (isset($_COOKIE['auth'])) {
            $browser_cookie = get_auth_cookie();
            if (!$browser_cookie) {
                return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'login expired'));
            }

            if ($browser_cookie[0] === $config['user']) {
                if (auth_secret_matches($config['password'], $browser_cookie[1])) {
                    return json_encode(array('code' => 200, 'level' => 1, 'messege' => 'admin'));
                }

                return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'password error'));
            }

            if (array_key_exists($browser_cookie[0], $guestConfig)) {
                if (!auth_secret_matches($guestConfig[$browser_cookie[0]]['password'], $browser_cookie[1])) {
                    return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'password error'));
                }

                if ($guestConfig[$browser_cookie[0]]['expired'] < time()) {
                    return json_encode(array('code' => 400, 'level' => 0, 'messege' => $browser_cookie[0] . ' expired'));
                }

                return json_encode(array('code' => 200, 'level' => 2, 'messege' => $browser_cookie[0] . ' logged in'));
            }

            return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'account not found'));
        }
    }

    // 前端验证
    $user = strip_tags($user);
    $password = strip_tags($password);
    if ($user === $config['user']) {
        if (auth_secret_matches($config['password'], $password)) {
            set_auth_session($user, $password);
            return json_encode(array('code' => 200, 'level' => 1, 'messege' => 'admin login success'));
        }

        return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'password error'));
    }

    if (array_key_exists($user, $guestConfig)) {
        if (!auth_secret_matches($guestConfig[$user]['password'], $password)) {
            return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'password error'));
        }

        if ($guestConfig[$user]['expired'] < time()) {
            return json_encode(array('code' => 400, 'level' => 0, 'messege' => $user . ' expired'));
        }

        set_auth_session($user, $password);
        return json_encode(array('code' => 200, 'level' => 2, 'messege' => $user . ' login success'));
    }

    return json_encode(array('code' => 400, 'level' => 0, 'messege' => 'account not found'));
}

/**
 * 校验登录 2023-01-05弃用
 */
function checkLogin()
{
    ensure_auth_session();

    global $guestConfig;
    global $config;

    $session_auth = get_auth_session();
    if ($session_auth) {
        if ($session_auth[0] === $config['user']) {
            return auth_secret_matches($config['password'], $session_auth[1]) ? 204 : 203;
        }

        if (!array_key_exists($session_auth[0], $guestConfig)) {
            return 203;
        }

        if (!auth_secret_matches($guestConfig[$session_auth[0]]['password'], $session_auth[1])) {
            return 203;
        }

        return $guestConfig[$session_auth[0]]['expired'] < time() ? 206 : 205;
    }

    // 无cookie
    if (empty($_COOKIE['auth'])) {
        return 201;
    }

    // 存在cookie
    if (isset($_COOKIE['auth'])) {

        $getCOK = get_auth_cookie();
        if (!$getCOK) {
            return 202;
        }

        if ($getCOK[0] === $config['user']) {
            return auth_secret_matches($config['password'], $getCOK[1]) ? 204 : 203;
        }

        if (!array_key_exists($getCOK[0], $guestConfig)) {
            return 203;
        }

        if (!auth_secret_matches($guestConfig[$getCOK[0]]['password'], $getCOK[1])) {
            return 203;
        }

        return $guestConfig[$getCOK[0]]['expired'] < time() ? 206 : 205;
    }
}

/**
 * 2023-01-06 仅允许登录上传
 */
function ensure_auth_session()
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
    }

    session_start();
}

function get_auth_session()
{
    if (empty($_SESSION['easyimage_auth']) || !is_array($_SESSION['easyimage_auth'])) {
        return null;
    }

    $auth = $_SESSION['easyimage_auth'];
    if (!isset($auth['user'], $auth['password']) || !is_string($auth['user']) || !is_string($auth['password'])) {
        return null;
    }

    return array($auth['user'], $auth['password']);
}

function set_auth_session($user, $password)
{
    ensure_auth_session();

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['easyimage_auth'] = array(
            'user' => $user,
            'password' => $password,
        );
    }

    clear_legacy_auth_cookie();
}

function auth_hash_equals($known, $userInput)
{
    if (!is_string($known) || !is_string($userInput)) {
        return false;
    }

    if (function_exists('hash_equals')) {
        return hash_equals($known, $userInput);
    }

    return $known === $userInput;
}

/**
 * Verify a submitted client-side secret against both new password hashes and
 * legacy SHA-256 values. The browser still sends SHA-256 for compatibility.
 */
function auth_secret_matches($stored, $submitted)
{
    if (!is_string($stored) || !is_string($submitted)) {
        return false;
    }

    $passwordInfo = password_get_info($stored);
    if (!empty($passwordInfo['algo'])) {
        return password_verify($submitted, $stored);
    }

    return auth_hash_equals($stored, $submitted);
}

function hash_auth_secret($submitted)
{
    if (!is_string($submitted)) {
        return '';
    }

    return function_exists('password_hash')
        ? password_hash($submitted, PASSWORD_DEFAULT)
        : hash('sha256', $submitted);
}

function create_secure_token($bytes = 32)
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($bytes));
    }

    return hash('sha256', uniqid('', true) . mt_rand());
}

function get_upload_token()
{
    ensure_auth_session();

    if (empty($_SESSION['easyimage_upload_token']) || !is_string($_SESSION['easyimage_upload_token'])) {
        $_SESSION['easyimage_upload_token'] = create_secure_token();
    }

    return $_SESSION['easyimage_upload_token'];
}

function validate_upload_token($token)
{
    ensure_auth_session();

    if (!is_string($token) || $token === '') {
        return false;
    }

    if (empty($_SESSION['easyimage_upload_token']) || !is_string($_SESSION['easyimage_upload_token'])) {
        return false;
    }

    return auth_hash_equals($_SESSION['easyimage_upload_token'], $token);
}

function get_csrf_token()
{
    ensure_auth_session();

    if (empty($_SESSION['easyimage_csrf_token']) || !is_string($_SESSION['easyimage_csrf_token'])) {
        $_SESSION['easyimage_csrf_token'] = create_secure_token();
    }

    return $_SESSION['easyimage_csrf_token'];
}

function validate_csrf_token($token)
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    return auth_hash_equals(get_csrf_token(), $token);
}

function get_auth_cookie()
{
    if (empty($_COOKIE['auth'])) {
        return null;
    }

    $browser_cookie = json_decode($_COOKIE['auth'], true);
    if (!is_array($browser_cookie) || count($browser_cookie) < 2) {
        return null;
    }

    if (!isset($browser_cookie[0], $browser_cookie[1]) || !is_string($browser_cookie[0]) || !is_string($browser_cookie[1])) {
        return null;
    }

    return array($browser_cookie[0], $browser_cookie[1]);
}

function set_auth_cookie($user, $password)
{
    $browser_cookie = json_encode(array($user, $password));
    $expires = time() + 3600 * 24 * 14;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if (PHP_VERSION_ID >= 70300) {
        return setcookie('auth', $browser_cookie, array(
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    return setcookie('auth', $browser_cookie, $expires, '/; samesite=Lax', '', $secure, true);
}

function clear_legacy_auth_cookie()
{
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if (PHP_VERSION_ID >= 70300) {
        return setcookie('auth', '', array(
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    return setcookie('auth', '', time() - 3600, '/; samesite=Lax', '', $secure, true);
}

function clear_auth_cookie()
{
    ensure_auth_session();
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['easyimage_auth']);
        session_regenerate_id(true);
    }

    return clear_legacy_auth_cookie();
}

function get_login_user_name()
{
    ensure_auth_session();

    $session_auth = get_auth_session();
    if ($session_auth) {
        return $session_auth[0];
    }

    $cookie_auth = get_auth_cookie();
    if ($cookie_auth) {
        return $cookie_auth[0];
    }

    return '';
}

function mustLogin()
{
    global $config;
    if ($config['mustLogin']) {
        $status = _login();
        $status = json_decode($status, true);
        $message = isset($status['messege']) ? (string) $status['messege'] : '';
        $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($status['code'] === 200) {
            echo '
            <script> 
                new $.zui.Messager(' . $message . ', {
                type: "success", // 定义颜色主题 
                icon: "linux", // 定义消息图标
                placement:"bottom-right" // 消息位置
                }).show();
            </script>';
        }

        if ($status['code'] === 400) {
            echo '
            <script>
                new $.zui.Messager(' . $message . ', {
                type: "danger", // 定义颜色主题 
                icon: "bullhorn" // 定义消息图标
            }).show();
            </script>';
            header("refresh:2;url=" . $config['domain'] . "/admin/index.php");
        }
    }
}

/**
 * 检查配置文件中目录是否存在是否可写并创建相应目录
 * @param null $path 要创建的路径
 * @return string
 */
function config_path($path = null)
{
    global $config;

    if (empty($path)) {
        if (array_key_exists('storage_path', $config)) {
            $path = date($config['storage_path']);
        } else {
            $path = date('Y/m/d/');
        }
    }
    // 2023-01-06弃用 php5.6 兼容写法：
    // $path = isset($path) ? $path : date('Y/m/d/');
    // php7.0 $path = $path ?? date('Y/m/d/');

    $img_path = $config['path'] . $path;
    $real_img_path = APP_ROOT . $img_path;

    if (!is_dir($real_img_path)) {
        @mkdir($real_img_path, 0755, true);
    }

    if (!is_writable($real_img_path)) {
        @chmod($real_img_path, 0755);
    }

    return $img_path;
}

/** Resolve a request path to an existing file inside the configured upload directory. */
function resolve_upload_file($path)
{
    global $config;

    if (!is_string($path) || trim($path) === '') {
        return false;
    }

    $path = trim($path);
    if (preg_match('#^https?://#i', $path)) {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : '';
    }

    $path = rawurldecode(str_replace('\\', '/', $path));
    $uploadRoot = realpath(APP_ROOT . '/' . ltrim((string) $config['path'], '/\\'));
    if ($uploadRoot === false) {
        return false;
    }

    $candidate = realpath(APP_ROOT . '/' . ltrim($path, '/'));
    if ($candidate === false || !is_file($candidate)) {
        return false;
    }

    $uploadRoot = rtrim(str_replace('\\', '/', $uploadRoot), '/');
    $candidate = str_replace('\\', '/', $candidate);
    if (strncmp($candidate, $uploadRoot . '/', strlen($uploadRoot) + 1) !== 0) {
        return false;
    }

    return $candidate;
}

/**
 * 图片命名规则
 * @param null $source 源文件名称
 * @return false|int|string|null
 */
function imgName($source = null)
{
    global $config;

    if (!function_exists('create_guid')) {
        function create_guid() // guid生成函数
        {
            if (function_exists('com_create_guid') === true) {
                return trim(com_create_guid(), '{}');
            }

            return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
        }
    }

    if (!function_exists('uuid')) {
        function uuid() // 生成uuid
        {
            $chars = md5(uniqid(mt_rand(), true));
            return substr($chars, 0, 8) . '-' . substr($chars, 8, 4) . '-' . substr($chars, 12, 4) . '-' . substr($chars, 16, 4) . '-' . substr($chars, 20, 12); // return $uuid;
        }
    }

    switch ($config['imgName']) {

        case "default":
            return base_convert(date('His') . mt_rand(1001, 9999), 10, 36); // 将上传时间+随机数转换为36进制 例：vx77yu
            break;
        case "source":
            // 以上传文件名称 例：微信图片_20211228214754
            // 过滤非法名称 $source = preg_replace("/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/","",$source);
            return $source;
            break;
        case "date":
            // 以上传时间 例：192704
            return date("His");
            break;
        case "unix":
            // 以Unix时间 例：1635074840
            return time();
            break;
        case "uniqid":
            // 基于以微秒计的当前时间 例：6175436c73418
            return uniqid(true);
            break;
        case "guid":
            // 全球唯一标识符 例：6EDAD0CC-AB0C-4F61-BCCA-05FAD65BF0FA
            return create_guid();
            break;
        case "md5":
            // md5加密时间 例：3888aa69eb321a2b61fcc63520bf6c82
            return md5(microtime());
            break;
        case "sha1":
            // sha1加密微秒 例：654faac01499e0cb5fb0e9d78b21e234c63d842a
            return sha1(microtime());
            break;
        case "crc32":
            // crc32加密微秒 例：2495551279
            return crc32(microtime());
            break;
        case "snowflake":
            include __DIR__ . '/class.snowflake.php';
            return SnowFlake::createOnlyId(); // 分布式id
            break;
        case "uuid":
            return uuid(); // uuid
            break;
        default:
            return base_convert(date('His') . mt_rand(1001, 9999), 10, 36); // 将上传时间+随机数转换为36进制 例：vx77yu
    }
}

/**
 * 静态文件CDN
 */
function static_cdn()
{
    global $config;
    if ($config['static_cdn']) {
        echo $config['static_cdn_url'];
    } else {
        echo $config['domain'];
    }
}

/**
 * 获取允许上传的扩展名
 */
function getExtensions()
{
    global $config;
    $arr = explode(',', $config['extensions']);
    $mime = '';
    for ($i = 0; $i < count($arr); $i++) {
        $mime .= $arr . ',';
    }
    return rtrim($mime, ',');
}

/**
 * 获取目录大小 如果目录文件较多将很费时
 * @param $path string 路径
 * @return int
 */
function getDirectorySize($path)
{
    $bytestotal = 0;
    $path = realpath($path);
    if ($path !== false && $path != '' && file_exists($path)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object) {
            $bytestotal += $object->getSize();
        }
    }
    return $bytestotal;
}

/**
 * 获取指定文件夹文件数量
 * @param $dir 传入一个路径如：/apps/web
 * @return int 返回文件数量
 */
function getFileNumber($dir)
{
    $num = 0;
    $arr = glob($dir);
    foreach ($arr as $v) {
        if (is_file($v)) {
            $num++;
        } else {
            $num += getFileNumber($v . "/*");
        }
    }
    return $num;
}

/**
 * 图片展示页面
 * getDir()取文件夹列表，getFile()取对应文件夹下面的文件列表,二者的区别在于判断有没有“.”后缀的文件，其他都一样
 * 获取文件目录列表,该方法返回数组
 * @param $dir string 路径
 * @return mixed
 * @example getDir("./dir")
 */
function getDirList($dir)
{
    $dirArray[] = NULL;
    if (false != ($handle = opendir($dir))) {
        $i = 0;
        while (false !== ($file = readdir($handle))) {
            //去掉"“.”、“..”以及带“.xxx”后缀的文件
            if ($file != "." && $file != ".." && !strpos($file, ".")) {
                $dirArray[$i] = $file;
                $i++;
            }
        }
        //关闭句柄
        closedir($handle);
    }
    return $dirArray;
}

/**
 * 获取文件列表
 * @param $dir string 目录
 * @return mixed
 */
function getFile($dir)
{
    $fileArray[] = NULL;
    if (is_dir($dir)) {
        if (false != ($handle = opendir($dir))) {
            $i = 0;
            while (false !== ($file = readdir($handle))) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ($file != "." && $file != ".." && strpos($file, ".")) {
                    $fileArray[$i] = $file;
                    if ($i == 1000) {
                        break;
                    }
                    $i++;
                }
            }
            //关闭句柄
            closedir($handle);
        }
    }
    return $fileArray;
}

/**
 * 获取文件夹文件列表或数量
 * @param string $dir_fileName_suffix 获取文件列表：目录+文件名*:全匹配+文件后缀 *: 全匹配 {jpg,png,gif}:匹配指定格式
 *                                    递归文件数量：目录
 * @example get_file_by_glob(__DIR__ . '/i/cache/*.*', $type = 'list'); // 获取目录文件列表
 * @example get_file_by_glob(__DIR__ . '/i/', $type = 'number');        // 递归获取文件夹数量
 * @param string $type list|number 返回列表还是数量
 * @return array|int 返回数组|数量
 */
function get_file_by_glob($dir_fileName_suffix, $type = 'list')
{
    global $config;

    // 获取所有文件
    if ($type == 'list') {
        $flag = defined('GLOB_BRACE') ? GLOB_BRACE : 0;
        $glob = glob($dir_fileName_suffix, $flag);

        if ($glob) {
            foreach ($glob as $v) {
                if (is_file($v)) $res[] = basename($v);
            }
            // 排序
            if ($res) {
                switch ($config['showSort']) {
                    case 1:
                        $res = array_reverse($res);
                        break;
                }
            }
        } else {
            $res = array();
        }
    }

    if ($type == 'number') {
        $res = 0;
        $glob = glob($dir_fileName_suffix); //把该路径下所有的文件存到一个数组里面;
        if ($glob) {
            foreach ($glob as $v) {
                if (is_file($v)) {
                    $res++;
                } else {
                    $res += get_file_by_glob($v . "/*", $type = 'number');
                }
            }
        } else {
            $res = 0;
        }
    }
    return $res;
}

/**
 * 递归函数实现遍历指定文件下的目录与文件数量
 * 用来统计一个目录下的文件和目录的个数
 * echo "目录数为:{$dirn}<br>";
 * echo "文件数为:{$filen}<br>";
 * @param $file string 目录
 */
function getdirnum($file)
{
    $dirn = 0; //目录数
    $filen = 0; //文件数
    $dir = opendir($file);
    while ($filename = readdir($dir)) {
        if ($filename != "." && $filename != "..") {
            $filename = $file . "/" . $filename;
            if (is_dir($filename)) {
                $dirn++;
                getdirnum($filename);
                //递归，就可以查看所有子目录
            } else {
                $filen++;
            }
        }
    }
    closedir($dir);
}

/**
 * 把文件或目录的大小转化为容易读的方式
 * disk_free_space - 磁盘可用空间(比如填写D盘某文件夹，则会现在D盘剩余空间）
 * disk_total_space — 磁盘总空间(比如填写D盘某文件夹，则会现在D盘总空间）
 * @param $number
 * @return string
 */
function getDistUsed($number)
{
    $dw = ''; // 指定文件或目录统计的单位方式
    if ($number > pow(2, 30)) {
        $dw = "GB";
        $number = round($number / pow(2, 30), 2);
    } else if ($number > pow(2, 20)) {
        $dw = "MB";
        $number = round($number / pow(2, 20), 2);
    } else if ($number > pow(2, 10)) {
        $dw = "KB";
        $number = round($number / pow(2, 10), 2);
    } else {
        $dw = "bytes";
    }
    return $number . $dw;
}

/**
 * 加密/解密图片路径
 * @param string $data 要加密的内容
 * @param int $mode =1或0 1解密 0加密
 * @param String $key 盐
 */
function urlHash($data, $mode, $key = null)
{
    global $config;

    if (!$key) {
        $key = crc32($config['password']);
    }

    $iv = 'sciCuBC7orQtDhTO';
    if ($mode) {
        return openssl_decrypt(base64_decode($data), "AES-128-XTS", $key, 0, $iv);
    } else {
        return base64_encode(openssl_encrypt($data, "AES-128-XTS", $key, 0, $iv));
    }
}

/**
 * 删除指定文件
 * @param $url string 文件
 * @param $type string 模式
 */
function getDel($url, $type)
{
    $resolvedPath = resolve_upload_file($url);
    if ($resolvedPath !== false && @unlink($resolvedPath)) {
        return true;
    }
    return false;

    global $config;
    // url本地化
    $url = htmlspecialchars(str_replace($config['domain'], '', $url)); // 过滤html 获取url path
    $url = urldecode(trim($url));

    if ($type == 'url') {
        $url = APP_ROOT . $url;
    }
    if ($type == 'hash') {
        $url = APP_ROOT . $url;
    }

    // 文件是否存在 限制删除目录
    if (is_file($url) && strrpos($url, $config['path'])) {
        // 执行删除
        if (@unlink($url)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
}


/**
 * 删除指定文件
 * @param $url string 文件
 * @param $type string 模式
 */
function easyimage_delete($url, $type)
{
    $resolvedPath = resolve_upload_file($url);
    if ($resolvedPath !== false && @unlink($resolvedPath)) {
        clearstatcache(true, $resolvedPath);
        return true;
    }
    return false;

    global $config;
    // url本地化
    $url = htmlspecialchars(str_replace($config['domain'], '', $url)); // 过滤html 获取url path
    $url = urldecode(trim($url));

    if ($type == 'url') {
        $url = APP_ROOT . $url;
    }
    if ($type == 'hash') {
        $url = APP_ROOT . $url;
    }

    // 文件是否存在 限制删除目录
    if (is_file($url) && strrpos($url, $config['path'])) {
        // 执行删除
        if (@unlink($url)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    return FALSE;
    // 清除查询
    clearstatcache();
}

/**
 * 判断是否此用户登录
 * @param string $user 判断登录者权限 当$user=null 时检查是否登录, 不区分身份
 * @return bool 是|否
 */
function is_who_login($user)
{
    // 将状态转码
    $status = json_decode(_login(), true);
    // 查询是否登录
    if ($user === 'status') if ($status['level'] > 0) return true;
    // 是否管理员登录
    if ($user === 'admin') if ($status['level'] == 1) return true;
    // 是否上传者登录
    if ($user === 'guest') if ($status['level'] == 2) return true;

    return false;
}

/**
 * 检查PHP缺少简单图床必备的扩展
 * 需检测的扩展：'fileinfo', 'iconv', 'gd', 'mbstring', 'openssl','zip',
 * zip 扩展不是必须的，但会影响tinyfilemanager文件压缩(本次不检测)。
 *
 * 检测是否更改默认域名
 *
 * 检测是否修改默认密码
 * @param $mode bool 是否开启检测
 */
function checkEnv($mode)
{
    // 初始化安装
    if (!is_file(APP_ROOT . '/config/install.lock') and is_file(APP_ROOT . '/install/install.php')) {
        echo '<script type="text/javascript">window.location.href="' . get_whole_url('/') . '/install/index.php"</script>';
    }

    if ($mode) {
        require_once __DIR__ . '/check.php';
    }
}

/**
 * 前端改变图片长宽
 * @return string 裁剪参数
 */
function imgRatio()
{
    global $config;
    if ($config['imgRatio']) {

        if ($config['imgRatio_crop'] === 1) {
            $imgRatio_crop = 'true';
        } else {
            $imgRatio_crop = 'false';
        }

        if ($config['imgRatio_preserve_headers'] === 1) {
            $imgRatio_preserve_headers = 'true';
        } else {
            $imgRatio_preserve_headers = 'false';
        }

        if ($config['image_x'] != 0) {
            $image_x = "width:" . $config['image_x'] . ',';
        } else {
            $image_x = null;
        }

        if ($config['image_y'] != 0) {
            $image_y = "height:" . $config['image_y'] . ',';
        } else {
            $image_y = null;
        }

        return '
		resize:{
			' . $image_x . '
			' . $image_y . '
			crop: ' . $imgRatio_crop . ',
			quality:' . $config['imgRatio_quality'] . ',
			preserve_headers: ' . $imgRatio_preserve_headers . ',
		}';
    } else {
        return "file_data_name:'file'";
    }
}

/**
 * 定时获取GitHub 最新版本
 * @return mixed|null 读取版本信息
 */
function getVersion($name = 'tag_name')
{
    global $config;

    $fallback = $name === 'tag_name' ? APP_VERSION : ($name === 'name' ? 'EasyImage ' . APP_VERSION : '在线版本检查未开启或暂无发布版本。');
    if (!$config['checkEnv']) {
        return $fallback;
    }

    require_once __DIR__ . '/class.version.php';
    $url = 'https://api.github.com/repos/HH2XX/EasyImages/releases/latest';
    $getVersion = new getVersion($url);

    try {
        $version = $getVersion->readJson($name);
        if ($version === null || $version === '') {
            return $fallback;
        }

        return $name === 'tag_name' ? ltrim((string) $version, 'vV') : $version;
    } catch (Throwable $e) {
        return $fallback;
    }
}

/**
 * 删除非空目录
 * @param $dir string 要删除的目录
 * @return bool true|false
 */
function deldir($dir)
{
    if (file_exists($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    deldir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
        return true;
    } else {
        return false;
    }
}

/**
 * 图片监黄curl 访问网站并返回解码过的json信息
 * @param $img string 图片url
 * @param null $url 访问的网址
 * @return mixed
 */
function moderatecontent_json($img, $url = null)
{
    global $config;

    if (empty($config['moderatecontent_key'])) {
        exit;
    }

    $url = 'https://api.moderatecontent.com/moderate/?key=' . rawurlencode($config['moderatecontent_key']) . '&url=' . rawurlencode($img);
    $headerArray = array("Content-type:application/json;", "Accept:application/json");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36');
    $output = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($output, true);
    return $output;
}

/**
 * 使用curl方式实现get或post请求
 * @param $url 请求的url地址
 * @param $data 发送的post数据 如果为空则为get方式请求
 * return 请求后获取到的数据
 */

function nsfwjs_json($url, $data = '')
{
    global $config;

    if (empty($config['nsfwjs_url'])) {
        exit;
    }

    $ch = curl_init();
    $params[CURLOPT_URL] = $config['nsfwjs_url'] . $url; //请求url地址
    $params[CURLOPT_HEADER] = false; //是否返回响应头信息
    $params[CURLOPT_RETURNTRANSFER] = true; //是否将结果返回
    $params[CURLOPT_FOLLOWLOCATION] = true; //是否重定向
    $params[CURLOPT_TIMEOUT] = 30; //超时时间
    if (!empty($data)) {
        $params[CURLOPT_POST] = true;
        $params[CURLOPT_POSTFIELDS] = $data;
    }
    $params[CURLOPT_SSL_VERIFYPEER] = true;
    $params[CURLOPT_SSL_VERIFYHOST] = 2;
    curl_setopt_array($ch, $params); //传入curl参数
    $content = curl_exec($ch); //执行
    curl_close($ch); //关闭连接
    $content = json_decode($content, true);
    return $content;
}

/**
 * 检查图片是否违规
 * @param $imageUrl 图片链接
 * @param int $type 模式: 1|moderatecontent 2|nsfwjs 3|移入回收站
 * @param string $dir 移入的目录
 * @return bool
 */
function checkImg($imageUrl, $type = 1, $dir = 'suspic/')
{
    global $config;
    $bad_pic = false;

    /** # 使用moderatecontent */
    if ($type === 1) {
        $response = moderatecontent_json($imageUrl);
        $response = is_array($response) ? $response : array();
        $response['rating_index'] = isset($response['rating_index']) ? (int) $response['rating_index'] : 0;
        if (!isset($response['predictions']) || !is_array($response['predictions'])) {
            $response['predictions'] = array();
        }
        $response['predictions']['adult'] = isset($response['predictions']['adult']) ? (float) $response['predictions']['adult'] : 0;
        if ($response['rating_index'] == 3 or $response['predictions']['adult'] > $config['checkImg_value']) { // (1 = everyone, 2 = teen, 3 = adult)
            $bad_pic = true;
        }
    }

    /** # 使用nsfwjs */
    if ($type === 2) {
        /**
         * probability，概率
         * className，类型
         * 
         * 上传图片后，总共会返回 5 个维度的数值来鉴别该图片的尺度:
         * 
         * 绘画（Drawing）—— 无害的艺术，或艺术绘画；
         * 变态（Hentai）—— 色情艺术，不适合大多数工作环境；
         * 中立（Neutral）—— 一般，无害的内容；
         * 色情（Porn）—— 不雅的内容和行为，通常涉及生殖器；
         * 性感（Sexy）—— 不合时宜的挑衅内容。
         * 
         * 当porn评分超过>=0.6左右,就几乎是一张带有色情性质的图片了。
         */

        $file = nsfwjs_json($imageUrl);
        $file = is_array($file) ? $file : array();
        $res = array('Sexy' => 0, 'Porn' => 0, 'Hentai' => 0);

        // 将获取的值删除className后组建数组
        foreach ($file as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['className']) && $item['className'] == 'Drawing') {
                $res['Drawing'] = isset($item['probability']) ? (float) $item['probability'] : 0;
            }
            if (isset($item['className']) && $item['className'] == 'Hentai') {
                $res['Hentai'] = isset($item['probability']) ? (float) $item['probability'] : 0;
            }
            if (isset($item['className']) && $item['className'] == 'Neutral') {
                $res['Neutral'] = isset($item['probability']) ? (float) $item['probability'] : 0;
            }
            if (isset($item['className']) && $item['className'] == 'Porn') {
                $res['Porn'] = isset($item['probability']) ? (float) $item['probability'] : 0;
            }
            if (isset($item['className']) && $item['className'] == 'Sexy') {
                $res['Sexy'] = isset($item['probability']) ? (float) $item['probability'] : 0;
            }
        }

        // 测试数组是否正确
        // foreach ($file as $k => $v) {
        //     foreach ($v as $k1 => $v1) {
        //         echo $k1 . '=>' . $v1 . '<br/>';
        //     }
        // }

        if (
            $res['Sexy'] * 100 > $config['checkImg_value'] ||
            $res['Porn'] * 100 > $config['checkImg_value'] ||
            $res['Hentai'] * 100 > $config['checkImg_value']
        ) {
            $bad_pic = true;
        }
    }

    // 移入回收站
    if ($type === 3) {
        $bad_pic = true;
        $dir = 'recycle/';
    }

    /** # 如果违规则移动图片到违规文件夹 */
    if ($bad_pic === true) {
        $old_path = APP_ROOT . parse_url($imageUrl)['path'];   // 提交网址中的文件路径 /i/2021/10/29/p8vypd.png
        $name = parse_url($imageUrl)['path'];                  // 获得图片的相对地址
        $name = str_replace($config['path'], '', $name);       // 去除 path目录
        $name = str_replace('/', '_', $name);                  // 文件名 2021_10_30_p8vypd.png
        $new_path = APP_ROOT . $config['path'] . $dir . $name; // 新路径含文件名
        $suspic_dir = APP_ROOT . $config['path'] . $dir;       // suspic路径

        if (!is_dir($suspic_dir)) {                            // 创建suspic目录并移动
            mkdir($suspic_dir, 0777, true);
        }
        if (is_file($old_path)) {
            rename($old_path, $new_path);

            // FTP
            if ($config['ftp_status'] === 1) {
                any_upload(parse_url($imageUrl)['path'], $config['path'] . $dir . $name, 'rename');
            }

            return true;
        } else {
            return false;
        }
    }
}

/**
 * 还原被审查的图片
 * @param $name string 要还原的图片
 */
function re_checkImg($name, $dir = 'suspic/')
{
    global $config;

    $fileToPath = str_replace('_', '/', $name);                 // 将图片名称还原为带路径的名称，eg:2021_11_03_pbmn1a.jpg =>2021/11/03/pbmn1a.jpg
    $now_path_file = APP_ROOT . $config['path'] . $dir . $name; // 当前图片绝对位置 */i/suspic/2021_10_30_p8vypd.png
    if (is_file($now_path_file)) {
        $to_file = APP_ROOT . $config['path'] . $fileToPath;    // 要还原图片的绝对位置 */i/2021/10/30/p8vypd.png
        rename($now_path_file, $to_file);                       // 移动文件
        // FTP
        if ($config['ftp_status'] === 1) {
            any_upload($config['path'] . $dir . $name, $config['path'] . $fileToPath, 'rename');
        }
        return true;
    }
}

/**
 * 创建缩略图
 * @param $imgName string 需要创建缩略图的名称
 */
function creat_thumbnail_images($imgName)
{
    require_once __DIR__ . '/class.thumb.php';
    global $config;

    $old_img_path = APP_ROOT . config_path() . $imgName;                                          // 获取要创建缩略图文件的绝对路径
    $cache_path = APP_ROOT . $config['path'] . 'cache/';                                          // cache目录的绝对路径

    if (!is_dir($cache_path)) {                                                                   // 创建cache目录
        mkdir($cache_path, 0777, true);
    }
    if (!isGifAnimated($old_img_path)) {                                                          // 仅针对非gif创建图片缩略图
        $new_imgName = APP_ROOT . $config['path'] . 'cache/' . date('Y_m_d') . '_' . $imgName;    // 缩略图缓存的绝对路径
        Thumb::out($old_img_path, $new_imgName, $config['thumbnail_w'], $config['thumbnail_h']);  // 保存缩略图
    }
}

/**
 * 根据请求网址路径返回缩略图网址
 * @param $url string 图片链接
 * @return string
 */
function return_thumbnail_images($url)
{
    global $config;
    $cache_image_file = str_replace($config['imgurl'], '', $url);

    if (isGifAnimated(APP_ROOT . $cache_image_file)) {                                 // 仅读取非gif的缩略图
        return $url;                                                                   // 如果是gif则直接返回url
    } else {
        $cache_image_file = str_replace($config['path'], '', $cache_image_file);       // 将网址中的/i/去除
        $cache_image_file = str_replace('/', '_', $cache_image_file);                  // 将文件的/转换为_
        $isFile = APP_ROOT . $config['path'] . 'cache/' . $cache_image_file;           // 缓存文件的绝对路径
        if (file_exists($isFile)) {                                                    // 缓存文件是否存在
            return $config['imgurl'] . $config['path'] . 'cache/' . $cache_image_file; // 存在则返回缓存文件
        } else {
            return $url;                                                                    // 不存在直接返回url
        }
    }
}

/**
 * 在线输出缩略图
 * @param $imgUrl string 图片链接
 * @return string 缩略图链接
 */
function get_online_thumbnail($imgUrl)
{
    global $config;
    if ($config['thumbnail']) {
        $imgUrl = str_replace($config['domain'], '', $imgUrl);
        return $config['domain'] . '/app/thumb.php?img=' . $imgUrl;
    }

    return $imgUrl;
}

/**
 * 用户浏览广场的时候生成缩略图，由此解决上传生成缩略图时服务器超时响应
 * @param $imgUrl string 源图片网址
 * @return string 缩略图地址
 */
function creat_thumbnail_by_list($imgUrl)
{

    global $config;
    ini_set('max_execution_time', '300'); // 脚本运行的时间（以秒为单位）0不限制

    $extension = pathinfo($imgUrl, PATHINFO_EXTENSION);
    // 过滤非指定格式
    if (!in_array($extension, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'bmp'))) {
        // ico和svg格式直接返回直链
        if ($extension === 'ico' || $extension === 'svg') return $imgUrl;
        // 其他格式直接返回指定图标
        return '../public/images/file.svg';
    }

    switch ($config['thumbnail']) {
            // 输出原图
        case 0:
            return $imgUrl;
            break;
            // 访问生成
        case 1:
            return get_online_thumbnail($imgUrl);
            break;
    }

    // 将网址图片转换为相对路径
    $pathName = parse_url($imgUrl, PHP_URL_PATH);
    // 图片绝对路径
    $abPathName = APP_ROOT . $pathName;
    // 将网址中的/i/去除
    $pathName = str_replace($config['path'], '', $pathName);
    // 将文件的/转换为_
    $thumbnail = str_replace('/', '_', $pathName);

    // 缓存文件是否存在
    if (is_file(APP_ROOT . $config['path'] . 'cache/' . $thumbnail)) {
        // 存在则返回缓存文件
        return $config['domain'] . $config['path'] . 'cache/' . $thumbnail;
    } else {

        // 如果图像是gif则直接返回网址
        if (isGifAnimated($abPathName)) {
            return $imgUrl;
        }
        // 如果是webp动图则直接返回网址
        if (isWebpAnimated($abPathName)) {
            return $imgUrl;
        }

        /** 创建缓存文件并输出文件链接 */

        // thumbnails目录的绝对路径
        $cache_path = APP_ROOT . $config['path'] . 'cache/';
        // 创建cache目录
        if (!is_dir($cache_path)) {
            mkdir($cache_path, 0755, true);
        }
        // 缩略图缓存的绝对路径 $imgName 是不带/i/的相对路径
        $new_imgName = $cache_path . $thumbnail;
        // 创建并保存缩略图
        if ($config['thumbnail'] == 2) {
            require_once __DIR__ . '/Zebra_Image.php';
            $image = new Zebra_Image();
            $image->source_path = $abPathName;
            $image->target_path = $new_imgName;
            $image->resize($config['thumbnail_w'], $config['thumbnail_h'], ZEBRA_IMAGE_CROP_CENTER);
        } else {
            require_once __DIR__ . '/class.thumb.php';
            Thumb::out($abPathName, $new_imgName, $config['thumbnail_w'], $config['thumbnail_h']);
        }

        // 输出缩略图
        return $new_imgName;
    }
}

/**
 * 获取当前页面完整URL地址
 * https://www.php.cn/php-weizijiaocheng-28181.html
 * @param null $search string 返回指定搜索文字之前的内容(不含搜索文字)
 * @return false|string 返回读取网址
 */
function get_whole_url($search = null)
{
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    $php_self = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : $_SERVER['SCRIPT_NAME'];
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
    $whole_domain = $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
    if ($search) {
        // 返回指定符号之前
        return substr($whole_domain, 0, strrpos($whole_domain, $search));
    }
    return $whole_domain;
}

/**
 * 配置写入
 * @param $filename string 要存储的源文件名称
 * @param $values array 获取到的数组
 * @param string $var 源文件的数组名称
 * @param bool $format 不知道啥作用
 * @return bool
 */
function cache_write($filename, $values, $var = 'config', $format = false)
{
    $cachefile = $filename;
    $cachetext = "<?php\r\n" . '$' . $var . '=' . arrayeval($values, $format) . ";";
    $result = writefile($cachefile, $cachetext);

    // 清除Opcache缓存
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    return $result;
}

/**
 * 数组转换成字串
 * @param array $array 要转换的数组
 * @param bool $format 不知道啥作用
 * @param int $level 层级
 * @return string
 */
function arrayeval($array, $format = false, $level = 0)
{
    $space = $line = '';
    if (!$format) {
        for ($i = 0; $i <= $level; $i++) {
            $space .= "\t";
        }
        $line = "\n";
    }
    $evaluate = 'Array' . $line . $space . '(' . $line;
    $comma = $space;
    foreach ($array as $key => $val) {
        $key = is_string($key) ? '\'' . addcslashes($key, '\'\\') . '\'' : $key;
        $val = !is_array($val) && (!preg_match('/^\-?\d+$/', $val) || strlen($val) > 12) ? '\'' . addcslashes($val, '\'\\') . '\'' : $val;
        if (is_array($val)) {
            $evaluate .= $comma . $key . '=>' . arrayeval($val, $format, $level + 1);
        } else {
            $evaluate .= $comma . $key . '=>' . $val;
        }
        $comma = ',' . $line . $space;
    }
    $evaluate .= $line . $space . ')';
    return $evaluate;
}

/**
 * 配置写入文件
 * @param $filename string 要写入的文件名
 * @param $writetext array 要写入的文字
 * @param string $openmod 写文件模式
 * @return bool
 */
function writefile($filename, $writetext, $openmod = 'w')
{
    // Lock before truncating so concurrent config/log writes cannot overwrite
    // each other between fopen() and flock().
    $mode = $openmod === 'w' ? 'c+' : $openmod;
    $fp = @fopen($filename, $mode);
    if ($fp === false || !@flock($fp, LOCK_EX)) {
        if ($fp !== false) fclose($fp);
        return false;
    }

    $ok = @ftruncate($fp, 0);
    if ($ok) {
        rewind($fp);
        $ok = false !== @fwrite($fp, $writetext);
        fflush($fp);
    }

    @flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

/**
 * 获得用户的真实IP地址
 * 来源：ecshop
 * @return mixed|string string
 */
function real_ip()
{
    global $config;
    static $realip = NULL;
    if ($realip !== NULL) {
        return $realip;
    }
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $realip = $remoteAddr;

    // Only trust forwarding headers from explicitly configured proxies.
    $trustedProxies = isset($config['trusted_proxies']) ? $config['trusted_proxies'] : '';
    $trustedProxies = is_array($trustedProxies) ? $trustedProxies : explode(',', (string) $trustedProxies);
    $trustedProxies = array_filter(array_map('trim', $trustedProxies));
    if (in_array($remoteAddr, $trustedProxies, true)) {
        $forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
        foreach (explode(',', $forwarded) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $realip = $candidate;
                break;
            }
        }
    }

    if (!filter_var($realip, FILTER_VALIDATE_IP)) {
        $realip = '0.0.0.0';
    }
    return $realip;
}

/**
 * IP黑白名单检测，支持IP段检测
 * @param string $ipNow 要检测的IP
 * @param string|array $ipList 白名单IP或者黑名单IP
 * @return boolean false|true true:白名单模式，false:黑名单模式
 * @return bool
 */
function checkIP($ipNow = null, $ipList = null, $model = false)
{
    $ipNow = $ipNow ?: real_ip();

    // 将IP文本转换为数组
    if (is_string($ipList)) {
        $ipList = explode(",", $ipList);
    } else {
        echo 'IP名单错误';
    }

    $ipregexp = implode('|', str_replace(array('*', '.'), array('\d+', '\.'), $ipList));
    $result = preg_match("/^(" . $ipregexp . ")$/", $ipNow);

    // 白名单模式
    if ($model) {
        if (in_array($ipNow, $ipList)) {
            return false;
        }
    }
    // 黑名单模式
    if ($result) {
        return true;
    }
}

/**
 * 测试IP或者url是否可以ping通
 * @param $host string ip或网址
 * @param $port int 端口
 * @param $timeout float 过期时间
 * @return bool true|false
 */
function IP_URL_Ping($host, $port, $timeout)
{
    $errno = 444;
    $errstr = 'fSockOpen 错误';
    $fP = fSockOpen($host, $port, $errno, $errstr, $timeout);
    if (!$fP) {
        return false;
    }
    return true;
}

/**
 * 生成Token
 * @param int $length Token长度
 * @return string 返回Token
 */
function privateToken($length = 32)
{
    $output = '';
    for ($a = 0; $a < $length; $a++) {
        $output .= chr(mt_rand(65, 122)); //生成php随机数
    }
    return md5($output);
}

/**
 * 检查Token
 * @param $token 要检查的Token
 * @return string 201 访问成功但是服务端关闭API上传
 * @return string 202 访问成功但是Token错误
 */
function check_api($token)
{
    global $config;
    global $tokenList;

    if (!$config['apiStatus']) {
        // 关闭API
        $reJson = array(
            "result" => 'failed',
            'code' => 201,
            'message' => 'API Closed',
        );
        exit(json_encode($reJson, JSON_UNESCAPED_UNICODE));
    }

    if ($token === '' || !isset($tokenList[$token]) || !is_array($tokenList[$token])) {
        // Token 不存在
        $reJson = array(
            "result" => 'failed',
            'code' => 202,
            'message' => 'Token Error',
        );
        exit(json_encode($reJson, JSON_UNESCAPED_UNICODE));
    }

    if (!isset($tokenList[$token]['id']) || !is_scalar($tokenList[$token]['id'])
        || !isset($tokenList[$token]['expired']) || !is_numeric($tokenList[$token]['expired'])
        || $tokenList[$token]['expired'] < time()) {
        // Token 过期
        $reJson = array(
            "result" => 'failed',
            'code' => 203,
            'message' => 'Token Expired',
        );
        exit(json_encode($reJson, JSON_UNESCAPED_UNICODE));
    }
}

/**
 * 根据URL判断是否本地局域网访问(PHP代码函数)
 * https://blog.csdn.net/monxinmonxin0/article/details/44854383
 * @param string $url 要判断的网址
 * @return bool 是|否
 */
function is_local($url)
{
    if (stristr($url, '://localhost') || stristr($url, '://127.') || stristr($url, '://192.') || stristr($url, '://10.')) return true;

    return false;
}

/**
 * 将图片域名转换为数组并随即输出
 * @param string $text 字符串
 * @return String 随机网址
 */
function rand_imgurl($text = null)
{
    global $config;
    $url = isset($text) ? $text : $config['imgurl'];
    $url = explode(',', $url);
    return $url[array_rand($url, 1)];
}

/**
 * 获取当前版本号 | 读取字符串
 * @param String $file 文件相对路径
 * @return String 内容信息
 */
function get_current_version($file = '/admin/version.php')
{
    $file = APP_ROOT . $file;

    if (is_file($file)) {
        return file_get_contents($file);
    }

    return 'file does not exist';
}

/**
 * 压缩图片 process 模式
 * @param String $absolutePath 图片绝对路径
 */
function process_compress($absolutePath)
{
    global $config;

    if ($config['compress']) {
        if (!is_Gif_Webp_Animated($absolutePath)) {
            require_once __DIR__ . '/compress/Imagick/class.Imgcompress.php';
            $percent = $config['compress_ratio'] / 100; // 压缩率
            $img = new Imgcompress($absolutePath, $percent);
            $img->compressImg($absolutePath);
            // 释放
            ob_flush();
            flush();
        }
    }
}

/**
 * 设置水印 process 模式
 * @param String $source 图片路径
 */
function water($source)
{
    global $config;

    // 判断是否图片格式




    // 文字水印
    if ($config['watermark'] === 1) {
        // 过滤gif
        if (!is_Gif_Webp_Animated($source)) {
            $arr = [
                # 水印图片路径（如果不存在将会被当成是字符串水印）
                'res' => $config['waterText'],
                # 水印显示位置
                'pos' => $config['waterPosition'],
                # 不指定name(会覆盖原图，也就是保存成thumb.jpeg)
                'name' => $source,
                'font' => APP_ROOT . $config['textFont'],
                'fontSize' => $config['textSize'],
                'color' => str_replace(array('rgba', '(', ')'), '', $config['textColor']),
            ];
            Imgs::setWater($source, $arr);
        }
    }

    // 图片水印
    if ($config['watermark'] === 2) {
        // 过滤gif
        if (!is_Gif_Webp_Animated($source)) {
            $arr = [
                #  水印图片路径（如果不存在将会被当成是字符串水印）
                'res' => APP_ROOT . $config['waterImg'],
                #  水印显示位置
                'pos' => $config['waterPosition'],
                #  不指定name(会覆盖原图，也就是保存成thumb.jpeg)
                'name' => $source,
            ];
            Imgs::setWater($source, $arr);
        }
    }
}
/**
 * 图片违规检查 process
 * @param String $imgurl 图片链接
 */
function process_checkImg($imgurl)
{
    global $config;

    if ($config['checkImg'] == 1) {
        checkImg($imgurl, 1);
    }

    if ($config['checkImg'] == 2) {
        checkImg($imgurl, 2);
    }
}

/**
 * 写日志
 * 
 * 格式：
 * {
 *  上传图片名称{
 *      source:源文件名称,
 *      date:上传日期(Asia/Shanghai),
 *      ip:上传者IP,port:IP端口,
 *      user_agent:上传者浏览器信息,
 *      path:文件相对路径,
 *      size:文件大小(格式化),
 *      md5:文件MD5,
 *      checkImg:鉴黄状态,
 *      form:上传方式web/API ID
 *  }
 * }
 * 
 * $filePath 文件相对路径
 * $sourceName 源文件名称
 * $absolutePath 图片的绝对路径
 * $fileSize 图片的大小
 * $form 来源如果是网页上传直接显示网页,如果是API上传则显示ID
 */
function write_upload_logs($filePath, $sourceName, $absolutePath, $fileSize, $from = "web")
{
    global $config;

    if (!$config['upload_logs']) {
        return null;
    }

    $checkImg = $config['checkImg'] == true ? "ON" : "OFF";

    // $name = trim(basename($filePath), " \t\n\r\0\x0B"); // 当前图片名称
    $log = array(basename($filePath) => array(             // 以上传图片名称为Array
        'source'     => htmlspecialchars($sourceName),     // 原始文件名称
        'date'       => date('Y-m-d H:i:s'),               // 上传日期
        'ip'         => real_ip(),                         // 上传IP
        'port'       => $_SERVER['REMOTE_PORT'],           // IP端口
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],       // 浏览器信息
        'path'       => $filePath,                         // 文件相对路径
        'size'       => getDistUsed($fileSize),            // 文件大小(格式化)
        'md5'        => md5_file($absolutePath),           // 文件的md5
        'checkImg'   => $checkImg,                         // 鉴黄状态
        'from'       => $from,                             // 图片上传来源
    ));

    // 创建日志文件夹
    if (!is_dir(APP_ROOT . '/admin/logs/upload/')) {
        mkdir(APP_ROOT . '/admin/logs/upload', 0755, true);
    }

    // logs文件组成
    $logFileName = APP_ROOT . '/admin/logs/upload/' . date('Y-m') . '.php';

    // 创建logs文件
    if (!is_file($logFileName)) {
        file_put_contents($logFileName, '<?php $logs=Array();?>');
    }

    // 引入logs
    include $logFileName;

    // // 写入禁止浏览器直接访问
    // if (filesize($logFileName) == 0) {
    //     $php_exit = '<?php /** {图片名称{source:源文件名称,date:上传日期(Asia/Shanghai),ip:上传者IP,port:IP端口,user_agent:上传者浏览器信息,path:文件相对路径,size:文件大小(格式化),md5:文件MD5,checkImg:鉴黄状态,form:上传方式web/API ID}} */ exit;? >';
    //     file_put_contents($logFileName, $php_exit);
    // }

    // $log = json_encode($log, JSON_UNESCAPED_UNICODE);
    // file_put_contents($logFileName, PHP_EOL . $log, FILE_APPEND | LOCK_EX);

    $log = array_replace($logs, $log);
    cache_write($logFileName, $log, 'logs');
}

/**
 * IP地址查询
 * @param int $ip IP地址
 */
function ip2region($IP)
{
    if (!is_file(__DIR__ . '/ip2region/ip2region.xdb')) return '<a href="https://raw.githubusercontent.com/lionsoul2014/ip2region/v3.17.0/data/ip2region_v4.xdb" target="_blank"><span class="label label-primary" data-toggle="tooltip" title="点击下载 IP数据库 并上传到<br/><code>/app/ip2region/</code>">IP数据库不存在</span></a>';

    try {
        require_once __DIR__ . '/ip2region/Ip2Region.php';
        $ip2region = new Ip2Region();
        /* 显示完整信息 
        $location = $ip2region->memorySearch($IP); 
        $location = $location['region'];        
        $location =  str_replace(array('0', '||'), '', $location);
        */
        return $ip2region->simple($IP); // 显示简易信息
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * 记录同IP每日上传次数 process
 */
function write_ip_upload_count_logs()
{
    global $config;

    if ($config['ip_upload_counts'] > 0) {
        // 上传IP地址        
        $ip = real_ip();
        // 日志目录
        $dir = APP_ROOT . '/admin/logs/ipcounts/';
        // 日志文件
        $file = $dir . date('Y-m-d') . '.php';
        // 创建日志目录
        if (!is_dir($dir)) mkdir($dir, 0777);
        // 创建日志文件
        if (!is_file($file)) file_put_contents($file, '<?php $ipcounts=array(); ?>' . PHP_EOL, FILE_APPEND | LOCK_EX);
        // 引入日志
        require $file;
        // 获取存在的IP
        if (isset($ipcounts[$ip])) {
            $info[$ip] = $ipcounts[$ip] + 1;
        } else {
            $info[$ip] = 1;
        }
        // 写入日志
        $info = array_replace($ipcounts, $info);
        cache_write($file, $info, 'ipcounts');
    }
}
/**
 * 限制IP每日上传次数
 * @param Sting $ip IP地址
 */
function get_ip_upload_log_counts($ip)
{
    global $config;
    $file = APP_ROOT . '/admin/logs/ipcounts/' . date('Y-m-d') . '.php';

    if (!is_file($file)) return true;
    require_once $file;

    if (!isset($ipcounts[$ip])) return true;
    if ($ipcounts[$ip] >= $config['ip_upload_counts']) return false;

    return true;
}

/**
 * 自动删除
 * 开启自动删除后会先重命名文件夹作为备份
 * 自动删除日期超过定时的2倍时间后再彻底删除重命名的文件夹
 */
function auto_delete()
{
    global $config;
    if ($config['auto_delete'] && $config['storage_path'] == 'Y/m/d/') {

        /** 重命名要删除的文件夹 */
        $Odir = APP_ROOT . $config['path'] . date('Y/m/d', strtotime(-$config['auto_delete'] . 'day')); // 重命名文件夹路径
        $Rdir = APP_ROOT . $config['path'] . date('Y/m/d', strtotime(-$config['auto_delete'] . 'day')) . '_auto_delete'; // 新命名文件夹路径
        if (is_dir($Odir)) { // 执行重命名
            rename($Odir, $Rdir);
            $log = $Rdir . ' rename succees ' . date('Y-m-d H:i:s');
        } else {
            $log = $Rdir . ' rename failed ' . date('Y-m-d H:i:s');
        }

        /**  以定时的2倍倒叙时间删除已经重命名的文件夹路径 */
        $Ddir = APP_ROOT . $config['path'] . date('Y/m/d', strtotime(-$config['auto_delete'] * 2 . 'day')) . '_auto_delete'; // 文件夹路径
        if (is_dir($Ddir)) { // 执行删除
            try {
                if (deldir($Ddir)) {
                    $log = $Ddir . ' delete succees ' . date('Y-m-d H:i:s');
                } else {
                    throw new Exception($Ddir . ' delete failed ' . date('Y-m-d H:i:s'));
                }
            } catch (Exception $e) {
                $log = $e->getMessage();
            }
        }

        /** 创建日志文件夹及文件 */
        if (!is_dir(APP_ROOT . '/admin/logs/tasks/')) {
            mkdir(APP_ROOT . '/admin/logs/tasks/', 0755, true);
        }

        if (!is_file(APP_ROOT . '/admin/logs/tasks/auto_delete.php')) {
            file_put_contents(APP_ROOT . '/admin/logs/tasks/auto_delete.php', '<?php /** 自动删除日志 */ return; ?>' . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        // 写入日志
        file_put_contents(APP_ROOT . '/admin/logs/tasks/auto_delete.php', $log . PHP_EOL, FILE_APPEND | LOCK_EX);
        return true;
    }
    return false;
}

/**
 * 登录日志
 * @param String $user 用户
 * @param String $pass 密码
 * @param String $msg  提示
 */
function write_login_log($user, $pass, $msg)
{
    $log_path = APP_ROOT . '/admin/logs/login';
    $log_file = $log_path . '/' . date('Y-m-') . 'logs.php';

    /** 创建日志文件夹及文件 */
    if (!is_dir($log_path) && !@mkdir($log_path, 0755, true)) {
        error_log('EasyImage login log directory is not writable: ' . $log_path);
        return false;
    }

    if (!is_writable($log_path)) {
        @chmod($log_path, 0755);
    }

    if (!is_writable($log_path)) {
        error_log('EasyImage login log directory is not writable: ' . $log_path);
        return false;
    }

    if (!is_file($log_file) && false === @file_put_contents($log_file, '<?php /** 登录日志 */ exit; ?>' . PHP_EOL, FILE_APPEND | LOCK_EX)) {
        error_log('EasyImage login log file can not be created: ' . $log_file);
        return false;
    }

    /** 写入日志 */
    $log = htmlentities(date('Y-m-d H:i:s') . ' IP: ' . real_ip() . ' 账号: ' . $user . ' 密码: ****** 提示: ' . $msg);
    return false !== @file_put_contents($log_file, $log . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * curl检查远程链接是否有效
 * @param String $url 
 * @param return boll 
 */
function validUrl($url)
{
    $ch = curl_init();
    $timeout = 10;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1); //将文件的信息作为数据流输出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //将获取的信息以字符串返回
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //设置等待时间
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //禁止验证对等证书
    $contents = curl_exec($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); //获取请求状态码
    curl_close($ch);
    if ($http_code != '200') {
        return false;
    }
    return true;
}

/**
 * 其他上传
 * 支持: FTP上传
 * @link https://github.com/dg/ftp-php
 * @param String $remoteFile 远程地址
 * @param String $localFile 本地地址
 * @param String $way 使用方式 upload 上传 | delete 删除 
 */
function any_upload($remoteFile = null, $localFile = null, $way = 'upload')
{
    global $config;

    if (function_exists('remote_storage_driver')) {
        $storageDriver = remote_storage_driver();
        if ($storageDriver === 'webdav') {
            return webdav_remote_upload($remoteFile, $localFile, $way);
        }
        if ($storageDriver === 's3') {
            return s3_remote_upload($remoteFile, $localFile, $way);
        }
    }

    if (!$config['ftp_status']) return null;
    require_once __DIR__ . '/Ftp.php';

    // 登录FTP
    try {
        $ftp = new Ftp;

        if ($config['ftp_ssl'] === 1) {
            $ftp->sslConnect($config['ftp_host'], $config['ftp_port'], $config['ftp_time']);
        } else {
            $ftp->connect($config['ftp_host'], $config['ftp_port'], $config['ftp_time']);
        }

        $ftp->login($config['ftp_user'], $config['ftp_pass']);
        $ftp->pasv($config['ftp_pasv']);
    } catch (FtpException $e) {
        echo 'Error: ', $e->getMessage();
    }

    // 获取文件相对目录
    $dir = pathinfo($remoteFile, PATHINFO_DIRNAME);
    // 隐藏上传目录
    if ($config['hide_path']) {
        $dir = str_replace($config['path'], '', $dir);
        $remoteFile = str_replace($config['path'], '', $remoteFile);
    }

    switch ($way) {
        case 'upload':
            try {
                // 递归创建目录
                if (!$ftp->isDir($dir)) {
                    $ftp->mkDirRecursive($dir);
                }
                // 上传文件 远端->本地 模式: $config['ftp_mode']
                $ftp->put($remoteFile, $localFile, 2);
            } catch (FtpException $e) {
                echo 'Error: ', $e->getMessage();
            }
            break;
        case 'delete':
            if ($config['ftp_delloc_sync'] === 0) return true;
            $ftp->tryDelete($remoteFile);
            break;
        case 'list':
            return $ftp->nList($remoteFile);
            break;
        case 'rename':
            try {
                // 获取文件相对目录
                $dir = pathinfo($localFile, PATHINFO_DIRNAME);
                // 隐藏上传目录
                if ($config['hide_path']) {
                    $dir = str_replace($config['path'], '', $dir);
                    $localFile = str_replace($config['path'], '', $localFile);
                }
                // 递归创建目录
                if (!$ftp->isDir($dir)) {
                    $ftp->mkDirRecursive($dir);
                }
                // 此处 $remoteFile为源文件名及路径 $localFile为新文件名及路径
                $ftp->rename($remoteFile, $localFile);
            } catch (FtpException $e) {
                echo 'Error: ', $e->getMessage();
            }
            break;
    }
    // 关闭FTP
    $ftp->close();

    // FTP上传后是否删除本地文件
    if ($config['ftp_complete_del_local']) {
        @unlink($localFile);
    }
}

/**
 * 分片上传
 * @param $target_name 名称
 * @return Sting $target_name
 */
function chunk($target_name)
{
    global $config;
    $target_name = is_string($target_name) ? $target_name : '';
    if (!preg_match('/^[A-Za-z0-9._-]{1,128}$/', $target_name)) {
        die('Invalid chunk name');
    }

    $chunkIndex = isset($_REQUEST['chunk']) && ctype_digit((string) $_REQUEST['chunk'])
        ? (int) $_REQUEST['chunk'] : -1;
    $chunkCount = isset($_REQUEST['chunks']) && ctype_digit((string) $_REQUEST['chunks'])
        ? (int) $_REQUEST['chunks'] : 0;
    if ($chunkIndex < 0 || $chunkCount < 1 || $chunkCount > 10000 || $chunkIndex >= $chunkCount) {
        die('Invalid chunk input');
    }
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        die('Invalid chunk file');
    }

    $cacheRoot = APP_ROOT . $config['path'] . 'cache/';
    $chunkKey = hash('sha256', $target_name);
    $tempDir = $cacheRoot . $chunkKey . '/';
    $targetFile = $cacheRoot . $chunkKey . '.upload';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
        die('Unable to create chunk directory');
    }

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $tempDir . $chunkIndex)) {
        die('Unable to save chunk');
    }

    if ($chunkIndex !== $chunkCount - 1) {
        return null;
    }

    if ($chunkIndex === $chunkCount - 1) {
        $handle = @fopen($targetFile, 'wb');
        if ($handle === false) {
            die('Unable to merge chunks');
        }

        $maxSize = isset($config['maxSize']) && is_numeric($config['maxSize']) ? (int) $config['maxSize'] : 0;
        $totalSize = 0;
        for ($i = 0; $i < $chunkCount; $i++) {
            $part = $tempDir . $i;
            if (!is_file($part)) {
                fclose($handle);
                @unlink($targetFile);
                die('Missing chunk');
            }
            $partSize = filesize($part);
            $totalSize += $partSize === false ? 0 : $partSize;
            if ($maxSize > 0 && $totalSize > $maxSize) {
                fclose($handle);
                @unlink($targetFile);
                die('File exceeds maximum size');
            }
            $input = @fopen($part, 'rb');
            if ($input === false || false === stream_copy_to_stream($input, $handle)) {
                if ($input !== false) {
                    fclose($input);
                }
                fclose($handle);
                @unlink($targetFile);
                die('Unable to merge chunks');
            }
            fclose($input);
        }
        fclose($handle);
        deldir($tempDir);
    }

    return $targetFile;
}

// Remote storage helpers for WebDAV/S3 upload channels.
function remote_storage_driver()
{
    global $config;

    $driver = isset($config['storage_driver']) ? strtolower(trim((string) $config['storage_driver'])) : '';

    if ($driver === 'webdav') {
        return (!empty($config['webdav_status']) || !empty($config['webdav_url'])) ? 'webdav' : 'local';
    }

    if ($driver === 's3') {
        return (!empty($config['s3_status']) || !empty($config['s3_endpoint'])) ? 's3' : 'local';
    }

    if ($driver === 'ftp') {
        return !empty($config['ftp_status']) ? 'ftp' : 'local';
    }

    if ($driver === '' || $driver === 'local') {
        if (!empty($config['s3_status']) && !empty($config['s3_endpoint'])) {
            return 's3';
        }
        if (!empty($config['webdav_status']) && !empty($config['webdav_url'])) {
            return 'webdav';
        }
        if (!empty($config['ftp_status'])) {
            return 'ftp';
        }
    }

    return 'local';
}

function remote_storage_enabled()
{
    return remote_storage_driver() !== 'local';
}

function remote_storage_clean_path($path, $stripUploadBase = false)
{
    global $config;

    $path = trim(str_replace('\\', '/', (string) $path));
    if (preg_match('#^https?://#i', $path)) {
        $urlPath = parse_url($path, PHP_URL_PATH);
        $path = $urlPath === false ? '' : $urlPath;
    }

    $path = urldecode($path);
    $path = '/' . ltrim($path, '/');

    $normalizedParts = array();
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            if (empty($normalizedParts)) {
                return '/';
            }
            array_pop($normalizedParts);
            continue;
        }
        $normalizedParts[] = $part;
    }
    $path = '/' . implode('/', $normalizedParts);

    if ($stripUploadBase && !empty($config['path'])) {
        $uploadBase = '/' . trim(str_replace('\\', '/', (string) $config['path']), '/') . '/';
        if (strpos($path, $uploadBase) === 0) {
            $path = substr($path, strlen($uploadBase) - 1);
        }
    }

    return $path;
}

function remote_storage_hash_encode($path)
{
    $hash = urlHash(remote_storage_clean_path($path), 0);
    return rtrim(strtr($hash, '+/', '-_'), '=');
}

function remote_storage_hash_decode($hash)
{
    $hash = trim(rawurldecode((string) $hash));
    if ($hash === '') {
        return false;
    }

    $normalized = strtr($hash, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }

    $path = urlHash($normalized, 1);
    if (is_string($path) && $path !== '') {
        return $path;
    }

    return urlHash($hash, 1);
}

function remote_storage_public_url($path, $driver = null)
{
    global $config;

    $driver = $driver ?: remote_storage_driver();
    $path = remote_storage_clean_path($path);
    $publicPath = $path;
    $imageDomain = rtrim((string) rand_imgurl(), '/');
    $siteDomain = isset($config['domain']) ? rtrim((string) $config['domain'], '/') : '';
    if ($siteDomain === '') {
        $siteDomain = $imageDomain;
    }

    if ($imageDomain !== '' && $siteDomain !== '' && $imageDomain !== $siteDomain) {
        $rootPathKey = $driver === 's3' ? 's3_path' : ($driver === 'webdav' ? 'webdav_path' : '');
        $remoteRoot = $rootPathKey !== '' && !empty($config[$rootPathKey]) ? remote_storage_clean_path($config[$rootPathKey]) : '';
        if ($remoteRoot !== '' && $remoteRoot !== '/') {
            $publicPath = rtrim($remoteRoot, '/') . $path;
        }

        return $imageDomain . $publicPath;
    }

    $shortCode = remote_storage_create_short_code($path, $driver);
    if ($shortCode !== '') {
        return $siteDomain . '/r.php?i=' . rawurlencode($shortCode);
    }

    $driverAlias = $driver === 'webdav' ? 'w' : ($driver === 's3' ? 'r' : 'd');
    return $siteDomain . '/r.php?' . rawurlencode($driverAlias) . '=' . remote_storage_hash_encode($path);
}

function remote_storage_short_file()
{
    return APP_ROOT . '/config/short_urls.php';
}

function remote_storage_load_short_urls()
{
    $shortUrls = array();
    $file = remote_storage_short_file();
    if (is_file($file)) {
        include $file;
    }

    return is_array($shortUrls) ? $shortUrls : array();
}

function remote_storage_write_short_urls(array $shortUrls)
{
    $file = remote_storage_short_file();
    $dir = dirname($file);

    if (!is_dir($dir) || !is_writable($dir)) {
        error_log('EasyImage short url directory is not writable: ' . $dir);
        return false;
    }

    $content = "<?php\r\n" . '$shortUrls=' . arrayeval($shortUrls) . ';';
    return false !== @file_put_contents($file, $content, LOCK_EX);
}

function remote_storage_short_candidate($path, $driver, $attempt, $length)
{
    global $config;

    $secret = isset($config['password']) ? (string) $config['password'] : 'easyimage';
    $raw = hash_hmac('sha256', $driver . '|' . $path . '|' . $attempt, $secret, true);
    $code = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

    return substr($code, 0, $length);
}

function remote_storage_create_short_code($path, $driver)
{
    $path = remote_storage_clean_path($path);
    $driver = strtolower((string) $driver);
    if (!in_array($driver, array('webdav', 's3'), true)) {
        return '';
    }

    $lockFile = remote_storage_short_file() . '.lock';
    $lockHandle = @fopen($lockFile, 'c');
    if (!$lockHandle) {
        return '';
    }

    if (!@flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        return '';
    }

    $shortUrls = remote_storage_load_short_urls();
    foreach ($shortUrls as $code => $item) {
        if (isset($item['d'], $item['p']) && $item['d'] === $driver && $item['p'] === $path) {
            @flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return (string) $code;
        }
    }

    for ($length = 6; $length <= 10; $length++) {
        for ($attempt = 0; $attempt < 32; $attempt++) {
            $code = remote_storage_short_candidate($path, $driver, $attempt, $length);
            if (!isset($shortUrls[$code])) {
                $shortUrls[$code] = array(
                    'd' => $driver,
                    'p' => $path,
                    't' => time(),
                );

                if (remote_storage_write_short_urls($shortUrls)) {
                    @flock($lockHandle, LOCK_UN);
                    fclose($lockHandle);
                    return $code;
                }

                @flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                return '';
            }
        }
    }

    @flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    return '';
}

function remote_storage_resolve_short_code($code)
{
    $code = trim((string) $code);
    if (!preg_match('/^[A-Za-z0-9_-]{4,32}$/', $code)) {
        return false;
    }

    $shortUrls = remote_storage_load_short_urls();
    if (!isset($shortUrls[$code]) || !is_array($shortUrls[$code])) {
        return false;
    }

    $item = $shortUrls[$code];
    if (isset($item['d'], $item['p']) && in_array($item['d'], array('webdav', 's3'), true)) {
        return array(
            'driver' => $item['d'],
            'path' => remote_storage_clean_path($item['p']),
        );
    }

    return false;
}

function webdav_client_from_config()
{
    global $config;

    if (!extension_loaded('curl')) {
        throw new RuntimeException('PHP curl extension is not loaded.');
    }

    require_once __DIR__ . '/WebDav.php';
    $verifySsl = !isset($config['webdav_verify_ssl']) || (int) $config['webdav_verify_ssl'] === 1;

    return new WebDavClient(
        isset($config['webdav_url']) ? $config['webdav_url'] : '',
        isset($config['webdav_user']) ? $config['webdav_user'] : '',
        isset($config['webdav_pass']) ? $config['webdav_pass'] : '',
        isset($config['webdav_path']) ? $config['webdav_path'] : '',
        isset($config['webdav_timeout']) ? (int) $config['webdav_timeout'] : 30,
        $verifySsl
    );
}

function webdav_remote_upload($remoteFile = null, $localFile = null, $way = 'upload')
{
    global $config;

    if ($way === 'delete' && isset($config['ftp_delloc_sync']) && (int) $config['ftp_delloc_sync'] === 0) {
        return true;
    }

    try {
        $client = webdav_client_from_config();
        $stripBase = !empty($config['hide_path']);
        $remoteFile = remote_storage_clean_path($remoteFile, $stripBase);

        switch ($way) {
            case 'upload':
                $client->putFile($remoteFile, $localFile);
                if (!empty($config['ftp_complete_del_local'])) {
                    @unlink($localFile);
                }
                return true;

            case 'delete':
                return $client->delete($remoteFile);

            case 'rename':
                return true;
        }
    } catch (Exception $e) {
        error_log('WebDAV remote storage error: ' . $e->getMessage());
    }

    return false;
}

function s3_client_from_config()
{
    global $config;

    if (!extension_loaded('curl')) {
        throw new RuntimeException('PHP curl extension is not loaded.');
    }

    require_once __DIR__ . '/S3Storage.php';

    return new S3StorageClient(
        isset($config['s3_endpoint']) ? $config['s3_endpoint'] : '',
        isset($config['s3_bucket']) ? $config['s3_bucket'] : '',
        isset($config['s3_access_key']) ? $config['s3_access_key'] : '',
        isset($config['s3_secret_key']) ? $config['s3_secret_key'] : '',
        isset($config['s3_region']) ? $config['s3_region'] : 'auto',
        isset($config['s3_path']) ? $config['s3_path'] : '',
        isset($config['s3_timeout']) ? (int) $config['s3_timeout'] : 30,
        !isset($config['s3_verify_ssl']) || (int) $config['s3_verify_ssl'] === 1,
        !isset($config['s3_path_style']) || (int) $config['s3_path_style'] === 1
    );
}

function s3_remote_upload($remoteFile = null, $localFile = null, $way = 'upload')
{
    global $config;

    if ($way === 'delete' && isset($config['ftp_delloc_sync']) && (int) $config['ftp_delloc_sync'] === 0) {
        return true;
    }

    try {
        $client = s3_client_from_config();
        $stripBase = !empty($config['hide_path']);
        $remoteFile = remote_storage_clean_path($remoteFile, $stripBase);

        switch ($way) {
            case 'upload':
                $client->putFile($remoteFile, $localFile);
                if (!empty($config['ftp_complete_del_local'])) {
                    @unlink($localFile);
                }
                return true;

            case 'delete':
                return $client->delete($remoteFile);

            case 'rename':
                return true;
        }
    } catch (Exception $e) {
        error_log('S3 remote storage error: ' . $e->getMessage());
    }

    return false;
}

function remote_storage_read($path, $driver = null)
{
    $driver = $driver ?: remote_storage_driver();
    $path = remote_storage_clean_path($path);

    if ($driver === 'webdav') {
        $client = webdav_client_from_config();
        return $client->getFile($path);
    }

    if ($driver === 's3') {
        $client = s3_client_from_config();
        return $client->getObject($path);
    }

    return false;
}
