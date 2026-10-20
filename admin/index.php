<?php
/*
 * 登录页面
 */
define('EASYIMAGE_FRONTEND_STYLE', true);
define('EASYIMAGE_LOGIN_STYLE', true);
require_once __DIR__ . '/../app/function.php';
require_once APP_ROOT . '/app/header.php';
require_once APP_ROOT . '/config/config.guest.php';

// 退出
if (isset($_GET['login']) && $_GET['login'] === 'logout') {
    if ($_GET['login'] === 'logout') {

        if (is_who_login('status') || isset($_COOKIE['auth'])) {
            clear_auth_cookie();
            header("Refresh:2;url=../index.php");
            echo '
				<script>
					new $.zui.Messager("退出成功", {
						type: "success", // 定义颜色主题 
						icon: "ok-sign" // 定义消息图标
					}).show();
					// 延时2s跳转
					window.setTimeout("window.location=\'../index.php\'",2000);
				</script>
        ';
        } else {
            echo '
				<script>
				new $.zui.Messager("尚未登录", {
					type: "danger", // 定义颜色主题 
					icon: "exclamation-sign" // 定义消息图标
				}).show();
				// 延时2s跳转
				window.setTimeout("window.location=\'./index.php\'",2000);
				</script>
        ';
        }
    }
    exit(require_once APP_ROOT . '/app/footer.php');
}

// 提交登录
if (isset($_POST['password']) and isset($_POST['user'])) {

    // 验证码
    if ($config['captcha']) {
        if (empty($_REQUEST['code'])) {
            echo '
            <script>
                new $.zui.Messager("请填写验证码!", {type: "danger" // 定义颜色主题 
                }).show();
                // 延时2s跳转
                window.setTimeout("window.location=\'./index.php\'",2000);
            </script>';
            exit(require_once APP_ROOT . '/app/footer.php');
        } else {
            ensure_auth_session();
            if (strtolower($_REQUEST['code']) !== $_SESSION['code']) {
                echo '
                <script>
                    new $.zui.Messager("验证码错误!", {type: "danger" // 定义颜色主题 
                    }).show();
                    // 延时2s跳转
				    window.setTimeout("window.location=\'./index.php\'",2000);
                </script>';
                exit(require_once APP_ROOT . '/app/footer.php');
            }
        }
    }

    $login = _login($_POST['user'], $_POST['password']);
    $login = json_decode($login, true);
    $loginMessage = json_encode(
        isset($login['messege']) ? (string) $login['messege'] : 'Login failed',
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );

    if ($login['code'] == 200) {
        echo '
        <script> 
            new $.zui.Messager(' . $loginMessage . ' , {
            type: "primary", // 定义颜色主题
            icon: "check" // 定义消息图标
            }).show();
        </script>';
        $redirectUrl = $login['level'] == 1 ? $config['domain'] . '/admin/admin.inc.php' : $config['domain'];
        header("refresh:2;url=" . $redirectUrl);
    } else {
        echo '
        <script> 
            new $.zui.Messager(' . $loginMessage . ' , {
            type: "danger", // 定义颜色主题
            icon: "times" // 定义消息图标
            }).show();
        </script>';
        header("refresh:2;");
    }

    // 登录日志
    write_login_log($_POST['user'], $_POST['password'], $login["messege"]);
}
?>
<!-- 忘记密码 -->
<div class="modal fade" id="fogot">
    <div class="modal-dialog ">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">x</span><span class="sr-only">关闭</span></button>
                <h4 class="modal-title">
                    忘记账号/密码?
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-primary">忘记账号可以打开<code>/config/config.php</code>文件找到<code data-toggle="tooltip" title="'user'=><strong>admin</strong>'">user</code>对应的键值->填入</p>
                <p class="text-success">忘记密码请通过服务器控制台或重新执行安装流程重置，密码会使用安全哈希保存，不建议手动修改配置文件。</p>
                <h4 class="text-danger">更改后会立即生效并重新登录,请务必牢记账号和密码! </h4>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>
<section class="login-shell">
    <div class="container login-panel">
        <div class="user singinBx">
            <div class="imgBx">
                <img src="<?php echo $config['login_bg']; ?>" alt="简单图床登陆界面背景图" />
                <div class="login-visual-copy">
                    <span>Private Console</span>
                    <h1>EasyImage 控制台</h1>
                    <p>本地维护入口仅供站点管理员使用，用来管理上传渠道、图片策略和系统配置。</p>
                </div>
            </div>
            <div class="formBx">
                <form class="form-horizontal" action="index.php" method="post" onsubmit="return md5_post()">
                    <div class="login-form-head">
                        <span><i class="icon icon-shield"></i> Admin Only</span>
                        <a class="login-home-link" href="<?php echo $config['domain']; ?>"><i class="icon icon-home"></i> 返回首页</a>
                    </div>
                    <?php if (is_who_login('admin')) : ?>
                        <div class="login-signed-in">
                            <p>已登录：<?php echo htmlspecialchars(get_login_user_name(), ENT_QUOTES, 'UTF-8'); ?></p>
                            <div>
                                <a class="btn btn-sm btn-primary" href="<?php echo $config['domain']; ?>/admin/admin.inc.php">进入后台</a>
                                <a class="btn btn-sm btn-link" href="<?php echo $config['domain']; ?>/admin/index.php?login=logout">退出登录</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!is_who_login('admin')) : ?>
                    <h2>管理员登录</h2>
                    <p class="login-description">使用管理员账号进入私有控制台，管理图床设置与存储通道。</p>
                    <input type="text" name="user" id="account" class="form-control" value="" placeholder="登录账号" autocomplete="username" required="required">
                    <input type="password" name="password" id="password" class="form-control" value="" placeholder="登录密码" autocomplete="current-password" required="required"><input type="hidden" name="password" id="md5_password">
                    <?php if ($config['captcha']) : ?>
                        <input class="form-control" type="text" name="code" value="" placeholder="请输入验证码" autocomplete="off" required="required" />
                        <div class="form-group">
                            <div class="col">
                                <label><img src="../app/captcha.php" width="185px" onClick="this.src='../app/captcha.php?nocache='+Math.random()" title="点击换一张" /></label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-block btn-primary">登 录</button>
                    <p class="signup">忘记账号或密码请查看<a href="#fogot" data-moveable="inside" data-remember-pos="false" data-toggle="modal" data-target="#fogot" data-position="center">帮助信息</a></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</section>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/crypto/SHA256.js"></script>
<script>
    function md5_post() {
        var password = document.getElementById('password');
        var md5pwd = document.getElementById('md5_password');
        md5pwd.value = SHA256(password.value);
        // fix https://github.com/icret/EasyImages2.0/pull/163
        password.value = "Null";
        // 可以校验判断表单内容，true就是通过提交，false，阻止提交
        return true;
    }
</script>
<?php require_once APP_ROOT . '/app/footer.php';
