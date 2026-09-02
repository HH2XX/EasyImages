<?php
define('EASYIMAGE_FRONTEND_STYLE', true);
require_once __DIR__ . '/app/header.php';
/** 顶部广告 */
if ($config['ad_top']) echo $config['ad_top_info'];
/** 检查登陆 */
mustLogin();
$uploadToken = get_upload_token();
$frontIsLoggedIn = is_who_login('status');
$localUploadAllowed = $frontIsLoggedIn;
$frontStorageDriver = function_exists('remote_storage_driver') ? remote_storage_driver() : 'local';
$frontStorageDriver = in_array($frontStorageDriver, array('local', 'webdav', 's3'), true) ? $frontStorageDriver : 'local';
$webdavReady = !empty($config['webdav_url']);
$s3Ready = !empty($config['s3_endpoint']) && !empty($config['s3_bucket']) && !empty($config['s3_access_key']) && !empty($config['s3_secret_key']);
$availableStorageDrivers = array();
if ($localUploadAllowed) $availableStorageDrivers[] = 'local';
if ($webdavReady) $availableStorageDrivers[] = 'webdav';
if ($s3Ready) $availableStorageDrivers[] = 's3';
if (!in_array($frontStorageDriver, $availableStorageDrivers, true)) {
    $frontStorageDriver = !empty($availableStorageDrivers) ? $availableStorageDrivers[0] : 'none';
}
$frontUploadAvailable = $frontStorageDriver !== 'none';
?>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/zui/lib/uploader/zui.uploader.min.css">
<div class="easy-home">
  <section class="easy-hero">
    <h1>简单、干净、好用的图片上传</h1>
    <p>拖拽、粘贴或选择图片上传。公开上传走 WebDAV/S3，本地存储仅作为站点维护通道。</p>
    <div class="easy-hero-meta">
      <span><i class="icon icon-picture"></i> 仅支持图片</span>
      <span><i class="icon icon-bolt"></i> 单文件 <?php echo getDistUsed($config['maxSize']); ?></span>
      <span><i class="icon icon-th-large"></i> 最多 <?php echo $config['maxUploadFiles']; ?> 张</span>
    </div>
  </section>

  <!-- 公告 -->
  <?php if (!empty($config['tips'])) : ?>
    <aside class="easy-notice" aria-label="上传提示">
      <i class="icon icon-bullhorn" aria-hidden="true"></i>
      <div class="easy-notice-viewport">
        <div class="easy-notice-track">
          <div class="easy-notice-item"><?php echo $config['tips']; ?></div>
        </div>
      </div>
    </aside>
  <?php endif; ?>

  <section class="easy-upload-card" id="uploadPanel">
    <?php if (!$frontUploadAvailable) : ?>
      <div class="alert alert-warning easy-upload-unavailable">
        当前 WebDAV/S3 未配置，暂时没有可用上传方式。请先在后台配置远程上传渠道，或通过维护入口使用本地上传。
      </div>
    <?php endif; ?>
    <div id='upShowID' class="uploader col-md-12 clo-xs-12 <?php if (!$frontUploadAvailable) echo 'easy-uploader-disabled'; ?>" data-ride="uploader" data-url="/app/upload.php" data-upload-token="<?php echo htmlspecialchars($uploadToken, ENT_QUOTES, 'UTF-8'); ?>" data-upload-available="<?php echo $frontUploadAvailable ? '1' : '0'; ?>">
      <div class="uploader-message text-center">
        <div class="content"></div>
        <button type="button" class="close">x</button>
      </div>
      <div class="easy-upload-title">
        <i class="icon icon-cloud-upload"></i>
        <div>
          <h2>上传图片</h2>
          <p>选择图片、拖入虚线区域，或直接 Ctrl+V 粘贴图片。</p>
        </div>
      </div>
      <div class="uploader-files file-list file-list-lg file-rename-by-click" data-drag-placeholder="选择图片 / Ctrl+V 粘贴 / 拖拽到这里" style="min-height: 188px; border-style: dashed;"></div>
      <div class="uploader-actions">
        <button type="button" class="btn btn-link uploader-btn-browse"><i class="icon icon-plus"></i> 选择图片</button>
        <button type="button" class="btn btn-link uploader-btn-start"><i class="icon icon-cloud-upload"></i> 开始上传</button>
        <button type="button" class="btn btn-link uploader-btn-stop"><i class="icon icon-pause"></i> 暂停上传</button>
        <div class="uploader-status pull-right text-muted hidden-xs"></div>
        <div class="uploader-status pull-right text-muted col-xs-12 text-ellipsis visible-xs"></div>
      </div>
    </div>
    <div class="easy-storage-switch" aria-label="上传方式">
      <div class="easy-storage-title">
        <span>图片存储</span>
        <small>本次上传生效</small>
      </div>
      <?php if ($localUploadAllowed) : ?>
        <label class="easy-storage-option <?php if ($frontStorageDriver === 'local') echo 'active'; ?>">
          <input type="radio" name="storage_driver_choice" value="local" <?php if ($frontStorageDriver === 'local') echo 'checked'; ?>>
          <span><i class="icon icon-hdd"></i> 本地</span>
        </label>
      <?php endif; ?>
      <label class="easy-storage-option <?php if ($frontStorageDriver === 'webdav') echo 'active'; ?> <?php if (!$webdavReady) echo 'disabled'; ?>" <?php if (!$webdavReady) echo 'data-toggle="tooltip" title="请先到后台上传渠道里填写 WebDAV 地址"'; ?>>
        <input type="radio" name="storage_driver_choice" value="webdav" <?php if ($frontStorageDriver === 'webdav') echo 'checked'; ?> <?php if (!$webdavReady) echo 'disabled'; ?>>
        <span><i class="icon icon-cloud"></i> WebDAV</span>
      </label>
      <label class="easy-storage-option <?php if ($frontStorageDriver === 's3') echo 'active'; ?> <?php if (!$s3Ready) echo 'disabled'; ?>" <?php if (!$s3Ready) echo 'data-toggle="tooltip" title="请先到后台上传渠道里填写 S3 配置"'; ?>>
        <input type="radio" name="storage_driver_choice" value="s3" <?php if ($frontStorageDriver === 's3') echo 'checked'; ?> <?php if (!$s3Ready) echo 'disabled'; ?>>
        <span><i class="icon icon-cubes"></i> S3</span>
      </label>
      <?php if ($localUploadAllowed) : ?>
        <p>本地存储仅供维护使用；WebDAV/S3 会保存到远程空间，本地仅临时处理。</p>
      <?php else : ?>
        <p>公开上传仅使用 WebDAV/S3；本地存储不在前台开放。</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="easy-result-card">
    <div class="easy-section-heading">
      <div>
        <h2>上传结果</h2>
        <p>上传完成后，可在这里复制链接。</p>
      </div>
      <p class="easy-upload-rule">保留原有输出结果，按需切换复制。</p>
    </div>
    <div class="easy-output-toolbar">
      <span class="easy-output-label">输出格式</span>
      <ul class="nav nav-tabs easy-output-tabs">
      <li <?php if ($config['upload_first_show'] == 1) echo 'class="active"'; ?> data-toggle="tooltip" title="图片直链">
        <a href="#" data-target="#tab2Content1" data-toggle="tab"><i class="icon icon-picture"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 2) echo 'class="active"'; ?> data-toggle="tooltip" title="论坛代码">
        <a href="#" data-target="#tab2Content2" data-toggle="tab"><i class="icon icon-chat"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 3) echo 'class="active"'; ?> data-toggle="tooltip" title="Markdown">
        <a href="#" data-target="#tab2Content3" data-toggle="tab"><i class="icon icon-code"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 4) echo 'class="active"'; ?> data-toggle="tooltip" title="HTML链接">
        <a href="#" data-target="#tab2Content4" data-toggle="tab"><i class="icon icon-html5"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 5) echo 'class="active"'; ?> data-toggle="tooltip" title="缩略图">
        <a href="#" data-target="#tab2Content5" data-toggle="tab"><i class="icon icon-camera"></i></a>
      </li>
      <li <?php if ($config['upload_first_show'] == 6) echo 'class="active"';
          if ($config['show_user_hash_del'] == 0) echo 'style="display:none;"' ?> data-toggle="tooltip" title="删除链接">
        <a href="#" data-target="#tab2Content6" data-toggle="tab"><i class="icon icon-trash"></i></a>
      </li>
      </ul>
    </div>
    <div class="tab-content" style="text-align:right;">
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 1) echo 'active in';  ?>" id="tab2Content1">
        <textarea class="form-control" rows="5" id="links" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnLinks" onclick="uploadCopy('links','.btnLinks')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 2) echo 'active in'; ?>" id="tab2Content2">
        <textarea class="form-control" rows="5" id="bbscode" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnBbscode" onclick="uploadCopy('bbscode','.btnBbscode')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 3) echo 'active in'; ?>" id="tab2Content3">
        <textarea class="form-control" rows="5" id="markdown" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnMarkDown" onclick="uploadCopy('markdown','.btnMarkDown')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 4) echo 'active in';  ?>" id="tab2Content4">
        <textarea class="form-control" rows="5" id="html" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnHtml" onclick="uploadCopy('html','.btnHtml')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 5) echo 'active in';  ?>" id="tab2Content5">
        <textarea class="form-control" rows="5" id="thumb" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnThumb" onclick="uploadCopy('thumb','.btnThumb')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
      <div class="tab-pane fade <?php if ($config['upload_first_show'] == 6) echo 'active in';  ?>" id="tab2Content6">
        <textarea class="form-control" rows="5" id="del" readonly></textarea>
        <button class="btn btn-primary" data-toggle="tooltip" data-original-title="刷新" style="margin-top:5px;" onclick="location.reload()"><i class="icon icon-refresh"></i></button>
        <button class="btn btn-primary btnDel" onclick="uploadCopy('del','.btnDel')" data-toggle="tooltip" data-original-title="复制" data-loading-text="已复制" style="margin-top:5px;"><i class="icon icon-copy"></i></button>
      </div>
    </div>
  </section>
</div>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/uploader/zui.uploader.min.js"></script>
<script>
  window.EASYIMAGE_UPLOAD_TOKEN = '<?php echo htmlspecialchars($uploadToken, ENT_QUOTES, 'UTF-8'); ?>';
  window.EASYIMAGE_DEFAULT_STORAGE_DRIVER = '<?php echo $frontStorageDriver; ?>';
  window.EASYIMAGE_UPLOAD_AVAILABLE = <?php echo $frontUploadAvailable ? 'true' : 'false'; ?>;
  window.easyimageSelectedStorageDriver = function() {
    var checked = document.querySelector('input[name="storage_driver_choice"]:checked');
    return checked ? checked.value : window.EASYIMAGE_DEFAULT_STORAGE_DRIVER;
  };
  window.easyimageStorageChangeBound = true;
  document.addEventListener('change', function(event) {
    if (!event.target || event.target.name !== 'storage_driver_choice') return;
    var options = document.querySelectorAll('.easy-storage-option');
    for (var i = 0; i < options.length; i++) options[i].classList.remove('active');
    event.target.closest('.easy-storage-option').classList.add('active');
  });
</script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/EasyImage.js"></script>
<script>
  (function() {
    var track = document.querySelector('.easy-notice-track');

    if (!track) return;

    var item = track.querySelector('.easy-notice-item');
    if (!item) return;

    var clone = item.cloneNode(true);
    clone.setAttribute('data-notice-clone', 'true');
    clone.setAttribute('aria-hidden', 'true');
    clone.querySelectorAll('a').forEach(function(link) {
      link.setAttribute('tabindex', '-1');
    });
    track.appendChild(clone);
    track.classList.add('is-animated');
  })();

  // 上传控制
  if (window.EASYIMAGE_UPLOAD_AVAILABLE) {
    $('#upShowID').uploader({
    // 自动上传
    autoUpload: false,
    // 文件上传提交地址
    url: './app/upload.php',
    // 最大支持的上传文件
    max_file_size: <?php echo $config['maxSize']; ?>,
    // 分片上传 0为不分片 分片容易使图片上传失败
    chunk_size: <?php echo $config['chunks']; ?>,
    // 点击文件列表上传文件
    browseByClickList: true,
    // flash 上传组件地址
    flash_swf_url: '<?php static_cdn(); ?>/public/static/zui/lib/uploader/Moxie.swf',
    // silverlight 上传组件地址
    flash_swf_url: '<?php static_cdn(); ?>/public/static/zui/lib/uploader/Moxie.xap',
    // 上传令牌，防止跨站伪造上传
    multipart_params: function() {
      return {
        'upload_token': '<?php echo htmlspecialchars($uploadToken, ENT_QUOTES, 'UTF-8'); ?>',
        'storage_driver': window.easyimageSelectedStorageDriver ? window.easyimageSelectedStorageDriver() : '<?php echo $frontStorageDriver; ?>'
      };
    },
    // 预览图尺寸
    previewImageSize: {
      'width': 80,
      'height': 80
    },
    // 上传格式过滤
    filters: {
      mime_types: [{
        title: '图片',
        extensions: '<?php echo $config['extensions']; ?>'
      }],
      prevent_duplicates: true
    },
    // 限制文件上传数目
    limitFilesCount: <?php echo $config['maxUploadFiles']; ?>,
    // 移除文件进行确认
    deleteConfirm: true,
    // 重置上传失败的文件
    autoResetFails: true,
    // 当文件上传进度发送变化时触发，此回调函数会在上传文件的过程中反复触发
    onUploadProgress: function(file) {
      NProgress.configure({
        barColor: '<?php echo $config['NProgress_Progress']; ?>'
      });
      NProgress.set(0)
      NProgress.set(file.percent / 100)
    },
    // 显示上传成功消息
    uploadedMessage: '已上传 {uploaded} 个文件，{failed} 个文件上传失败',
    // 当启用分片上传选项后，每个文件片段上传完成时触发
    onChunkUploaded: function(file, responseObject) {
      NProgress.set(responseObject.offset / responseObject.total);
    },
    <?php echo imgRatio(); ?>,
    responseHandler: function(responseObject, file) {
      var obj = JSON.parse(responseObject.response); //由JSON字符串转换为JSON对象
      if (obj.code === 200) {
        $("#links").append(obj.url + "\r\n");
        $("#bbscode").append("[img]" + obj.url + "[/img]\r\n");
        $("#markdown").append("![" + obj.srcName + "](" + obj.url + ")\r\n");
        $("#html").append('&lt;img src="' + obj.url + '" alt="' + obj.srcName + '" /&gt;\r\n');
        $("#thumb").append(obj.thumb + "\r\n");
        $("#del").append(obj.del + "\r\n");

        // 上传成功提示
        var storageName = obj.storage === "webdav" ? "WebDAV" : (obj.storage === "s3" ? "S3" : "本地");
        new $.zui.Messager(obj.srcName + " 上传成功 · " + storageName, {
          type: "primary",
          placement: 'bottom-right',
          icon: "check"
        }).show();

        try { // 储存上传历史
          $.zui.store.set(obj.srcName, obj)
        } catch (err) {
          // 失败提示
          $.zui.messager.show('存储上传记录失败' + err, {
            icon: 'bell',
            time: 4000,
            type: 'danger',
            placement: 'top'
          });
        }
      } else {
        // 上传失败提示
        new $.zui.Messager(obj.message, {
          type: "danger",
          placement: 'bottom-right',
          icon: "times"
        }).show();
        return;
      }
    },
    });
  }
</script>
<?php
/** 环境检测 */
require_once APP_ROOT . '/app/check.php';
/** 底部广告 */
if ($config['ad_bot']) echo $config['ad_bot_info'];
/** 引入底部 */
require_once __DIR__ . '/app/footer.php';
