<?php

/** 广场页面 */
define('EASYIMAGE_FRONTEND_STYLE', true);
require_once __DIR__ . '/header.php';

/** 顶部广告 */
if ($config['ad_top']) echo $config['ad_top_info'];
?>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/viewjs/viewer.min.css">
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/zui/lib/bootbox/bootbox.min.css">
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/zui/lib/datetimepicker/datetimepicker.min.css">
<div class="row">
  <div class="col-md-12">
    <?php
    if (!$config['showSwitch'] && !is_who_login('admin')) : ?>
      <div class="alert alert-info">管理员关闭了预览哦~~</div>
      <?php exit(require_once __DIR__ . '/footer.php'); ?>
      <?php else :
      $listDate = max(0, (int) $config['listDate']);
      $today = new DateTimeImmutable('today');
      $path = $today->format('Y/m/d/');
      $requestedDate = isset($_GET['date']) ? trim((string) $_GET['date']) : '';

      if ($requestedDate !== '') {
        $selectedDate = DateTimeImmutable::createFromFormat('!Y/m/d/', $requestedDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validDate = $selectedDate instanceof DateTimeImmutable
          && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
          && $selectedDate->format('Y/m/d/') === $requestedDate
          && $selectedDate >= $today->modify("-{$listDate} days")
          && $selectedDate <= $today;

        if ($validDate) {
          $path = $selectedDate->format('Y/m/d/');
        } else {
          echo '
          <script>
            new $.zui.Messager("日期无效或超出可浏览范围，已返回今日上传列表", {
              type: "info",
              icon: "exclamation-sign"
            }).show();
          </script>';
        }
      }

      $keyNum = isset($_GET['num']) && ctype_digit((string) $_GET['num']) ? (int) $_GET['num'] : (int) $config['listNumber'];
      $keyNum = min(max($keyNum, 1), 200);
      $allowedExtensions = array_filter(array_map('trim', explode(',', strtolower($config['extensions']))));
      $allowedExtensions[] = 'ico';
      $requestedExtension = isset($_GET['search']) ? strtolower((string) $_GET['search']) : '';
      $fileType = in_array($requestedExtension, $allowedExtensions, true) ? '*.' . $requestedExtension : '*.*';
      $storagePath = config_path($path);
      $fileArr = get_file_by_glob(APP_ROOT . $storagePath . $fileType, 'list');
      $allUploud = get_file_by_glob(APP_ROOT . $storagePath, 'number');
      $httpUrl = array('date' => $path, 'num' => $keyNum);

      // 隐藏path目录获取图片复制与原图地址
      if ($config['hide_path']) {
        $config_path = str_replace($config['path'], '/', $storagePath);
      } else {
        $config_path = $storagePath;
      }
      $todayUpload = get_file_by_glob(APP_ROOT . config_path(), 'number');
      $yesterdayPath = $config['path'] . date('Y/m/d/', strtotime('-1 day'));
      $yesterdayUpload = get_file_by_glob(APP_ROOT . $yesterdayPath, 'number');
      ?>
      <div class="easy-gallery-toolbar">
        <div class="easy-gallery-group">
          <span class="easy-toolbar-label">浏览</span>
          <div class="btn-group">
            <a class="btn btn-mini btn-danger" href="?<?php echo http_build_query($httpUrl); ?>">当前 <?php echo $allUploud; ?></a>
            <a class="btn btn-mini btn-primary" href="list.php">今日 <?php echo $todayUpload; ?></a>
            <a class="btn btn-mini" href="?date=<?php echo date('Y/m/d/', strtotime('-1 day')); ?>">昨日 <?php echo $yesterdayUpload; ?></a>
            <?php for ($x = 2; $x <= $listDate; $x++) : ?>
              <a class="btn btn-mini hidden-xs inline-block" href="?date=<?php echo date('Y/m/d/', strtotime("-$x day")); ?>"><?php echo date('j号', strtotime("-$x day")); ?></a>
            <?php endfor; ?>
          </div>
        </div>
        <div class="easy-gallery-group">
          <span class="easy-toolbar-label">格式</span>
          <div class="btn-group">
            <?php foreach (array('jpg' => 'JPG', 'png' => 'PNG', 'gif' => 'GIF', 'webp' => 'WebP') as $extension => $label) : ?>
              <a class="btn btn-mini<?php echo $requestedExtension === $extension ? ' active' : ''; ?>" href="?<?php echo http_build_query(array_merge($httpUrl, array('search' => $extension))); ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <form class="easy-gallery-date" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8'); ?>" method="get">
          <div class="input-group">
            <input type="hidden" name="num" value="<?php echo $keyNum; ?>">
            <?php if ($requestedExtension !== '') : ?>
              <input type="hidden" name="search" value="<?php echo htmlspecialchars($requestedExtension, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <input type="text" class="form-control form-date input-sm" name="date" value="<?php echo $path; ?>" readonly="readonly">
            <span class="input-group-btn">
              <button type="submit" class="btn btn-primary input-sm"><i class="icon icon-calendar"></i> 日期</button>
            </span>
          </div>
        </form>
        <?php if (is_who_login('admin')) : ?>
          <div class="easy-gallery-group easy-gallery-admin-actions">
            <div class="btn-group">
              <button type="button" class="btn btn-mini" onclick="opcheckboxed('checkbox', 'checkall')">全选</button>
              <button type="button" class="btn btn-mini" onclick="opcheckboxed('checkbox', 'reversecheck')">反选</button>
              <button type="button" class="btn btn-mini" onclick="opcheckboxed('checkbox', 'uncheckall')">取消</button>
              <button type="button" class="btn btn-mini" onclick="recycle_img()">回收</button>
              <button type="button" class="btn btn-mini btn-danger" onclick="delete_img()">删除</button>
            </div>
          </div>
        <?php endif; ?>
        <button type="button" class="btn btn-mini btn-primary btn-back-to-top" data-toggle="tooltip" title="返回顶部"><i class="icon icon-arrow-up"></i></button>
      </div>

      <?php if (empty($fileArr[0])) : ?>
        <div class="alert alert-danger">今天还没有上传的图片哟~~ <br />快来上传第一张吧~!</div>
      <?php else : ?>
        <div id="viewjs" class="cards listNum">
            <?php foreach ($fileArr as $key => $value) {
              if ($key < $keyNum) {
                $relative_path = $storagePath . $value;
                $imgUrl = $config['domain'] . $relative_path;     // 图片地址
                $linkUrl = rand_imgurl() . $config_path . $value; // 图片复制与原图地址
                $thumbUrl = creat_thumbnail_by_list($imgUrl);
                $relativePathAttr = htmlspecialchars($relative_path, ENT_QUOTES, 'UTF-8');
                $imgUrlAttr = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
                $linkUrlAttr = htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8');
                $thumbUrlAttr = htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8');
                $relativePathJs = htmlspecialchars(json_encode($relative_path, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="col-lg-3 col-md-4 col-sm-6 col-xs-12">
                  <div class="card">
                    <img src="<?php static_cdn(); ?>/public/images/loading.svg" data-image="<?php echo $thumbUrlAttr; ?>" data-original="<?php echo $imgUrlAttr; ?>" alt="简单图床图片">
                    <div class="bottom-bar">
                      <a href="<?php echo $linkUrlAttr; ?>" target="_blank" rel="noopener"><i class="icon icon-picture" data-toggle="tooltip" title="打开"></i></a>
                      <a href="#" class="copy" data-clipboard-text="<?php echo $linkUrlAttr; ?>" data-toggle="tooltip" title="复制链接"><i class="icon icon-copy"></i></a>
                      <?php if ($config['show_exif_info'] || is_who_login('admin')) : ?>
                        <a href="/app/info.php?img=<?php echo rawurlencode($relative_path); ?>" data-toggle="tooltip" title="详细信息" target="_blank" rel="noopener"><i class="icon icon-info-sign"></i></a>
                      <?php endif; ?>
                      <a href="/app/down.php?dw=<?php echo rawurlencode($relative_path); ?>" data-toggle="tooltip" title="下载文件" target="_blank" rel="noopener"><i class="icon icon-cloud-download"></i></a>
                      <?php if (!empty($config['report'])) : ?>
                        <a href="<?php echo htmlspecialchars($config['report'] . '?Website1=' . rawurlencode($linkUrl), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><i class="icon icon-question-sign" data-toggle="tooltip" title="举报文件"></i></a>
                      <?php endif; ?>
                      <?php if (is_who_login('admin')) : ?>
                        <a href="#" onclick="ajax_post(<?php echo $relativePathJs; ?>, 'recycle'); return false;" data-toggle="tooltip" title="回收文件"><i class="icon icon-undo"></i></a>
                        <a href="#" onclick="ajax_post(<?php echo $relativePathJs; ?>); return false;" data-toggle="tooltip" title="删除文件"><i class="icon icon-trash"></i></a>
                        <label class="text-primary"><input type="checkbox" name="checkbox" value="<?php echo $relativePathAttr; ?>"> 选择</label>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
            <?php
              }
            }
            ?>
        </div>
    <?php
      endif;
    endif;
    /** 底部广告 */
    if ($config['ad_bot']) echo $config['ad_bot_info']; ?>
  </div>
  <script type="application/javascript" src="<?php static_cdn(); ?>/public/static/viewjs/viewer.min.js"></script>
  <script type="application/javascript" src="<?php static_cdn(); ?>/public/static/lazyload/lazyload.min.js"></script>
  <script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/bootbox/bootbox.min.js"></script>
  <script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/clipboard/clipboard.min.js"></script>
  <script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/datetimepicker/datetimepicker.min.js"></script>
  <script>
    // viewjs
    var viewContainer = document.getElementById('viewjs');
    if (viewContainer) {
      new Viewer(viewContainer, {
        url: 'data-original',
      });
    }

    // POST 删除提交
    function ajax_post(url, mode = 'delete') {

      bootbox.confirm({
        message: "确认执行 " + mode + " 操作?",
        buttons: {
          confirm: {
            label: '确定',
            className: 'btn-success'
          },
          cancel: {
            label: '取消',
            className: 'btn-danger'
          }
        },
        callback: function(result) {
          if (result == true) {
            $.post("del.php", {
                url: url,
                mode: mode
              },
              function(data, status) {
                let res = JSON.parse(data);
                new $.zui.Messager(res.msg, {
                  type: res.type,
                  icon: res.icon
                }).show();
                // 延时2秒刷新
                window.setTimeout(function() {
                  window.location.reload();
                }, 2000)
              });
          } else {
            new $.zui.Messager("取消 " + mode, {
              type: "primary", // 定义颜色主题 
              icon: "info-sign" // 定义消息图标
            }).show();
          }
        }
      });
    }

    // 复制url
    var clipboard = new Clipboard('.copy');
    clipboard.on('success', function(e) {
      new $.zui.Messager("复制直链成功", {
        type: "success", // 定义颜色主题 
        icon: "ok-sign" // 定义消息图标
      }).show();

    });
    clipboard.on('error', function(e) {
      document.querySelector('.copy');
      new $.zui.Messager("复制失败", {
        type: "danger", // 定义颜色主题 
        icon: "exclamation-sign" // 定义消息图标
      }).show();
    });

    // 取消/全选文件
    function opcheckboxed(objName, type) {
      var objNameList = document.getElementsByName(objName);
      if (null != objNameList) {
        for (var i = 0; i < objNameList.length; i++) {
          if (objNameList[i].checked == true) {
            if (type != 'checkall') { // 非全选
              objNameList[i].checked = false;
            }
          } else {
            if (type != 'uncheckall') { // 非取消全选
              objNameList[i].checked = true;
            }
          }
        }
      }
    }
    // 回收图片
    function recycle_img() {
      bootbox.confirm({
        message: "确认要放入回收站? <br /> 可在可疑图片中恢复!",
        buttons: {
          confirm: {
            label: '确定',
            className: 'btn-success'
          },
          cancel: {
            label: '取消',
            className: 'btn-danger'
          }
        },
        callback: function(result) {
          if (result == true) {
            obj = document.getElementsByName("checkbox");
            check_val = [];
            for (k in obj) {
              //判断复选框是否被选中
              if (obj[k].checked)
                //获取被选中的复选框的值
                check_val.push(obj[k].value);
            }
            $.post("del.php", {
              'recycle_url_array': check_val
            }, );
            new $.zui.Messager("放入回收站成功", {
              type: "success", // 定义颜色主题 
              icon: "ok-sign" // 定义消息图标
            }).show();
            // 延时2秒刷新
            window.setTimeout(function() {
              window.location.reload();
            }, 1500)
          } else {
            new $.zui.Messager("取消回收", {
              type: "primary", // 定义颜色主题 
              icon: "info-sign" // 定义消息图标
            }).show();
          }
        }
      });

    }
    // 删除图片
    function delete_img() {
      bootbox.confirm({
        message: "确认要删除?<br />* 删除文件夹后将无法恢复!",
        buttons: {
          confirm: {
            label: '确定',
            className: 'btn-success'
          },
          cancel: {
            label: '取消',
            className: 'btn-danger'
          }
        },
        callback: function(result) {
          if (result == true) {
            obj = document.getElementsByName("checkbox");
            check_val = [];
            for (k in obj) {
              //判断复选框是否被选中
              if (obj[k].checked)
                //获取被选中的复选框的值
                check_val.push(obj[k].value);
            }
            $.post("del.php", {
                'del_url_array': check_val
              },
              function(data) {
                if (data.search('success') > 0) {
                  new $.zui.Messager("删除成功", {
                    type: "success", // 定义颜色主题 
                    icon: "ok-sign" // 定义消息图标
                  }).show();
                } else {
                  new $.zui.Messager("删除失败 请登录后再删除!", {
                    type: "danger", // 定义颜色主题 
                    icon: "exclamation-sign" // 定义消息图标
                  }).show();
                }
                // 延时2秒刷新
                window.setTimeout(function() {
                  window.location.reload();
                }, 1500)
              });
          } else {
            new $.zui.Messager("取消删除", {
              type: "primary", // 定义颜色主题 
              icon: "info-sign" // 定义消息图标
            }).show();
          }
        }
      });
    }

    //懒加载
    var lazy = new Lazy({
      delay: 300,
    })

    // 返回顶部
    var back_to_top_button = jQuery('.btn-back-to-top');
    jQuery(window).scroll(function() {
      if (jQuery(this).scrollTop() > 100 && !back_to_top_button.hasClass('scrolled')) {
        back_to_top_button.addClass('scrolled');

      } else if (jQuery(this).scrollTop() < 100 && back_to_top_button.hasClass('scrolled')) {
        back_to_top_button.removeClass('scrolled');

      }
    });
    // 返回顶部
    back_to_top_button.click(function() {
      jQuery('html, body').animate({
        scrollTop: 0
      }, 800);
      return false;
    });

    // 按日期浏览
    $(".form-date").datetimepicker({
      weekStart: 1,
      todayBtn: 1,
      autoclose: 1,
      todayHighlight: 1,
      startView: 2,
      minView: 2,
      forceParse: 0,
      pickerPosition: "top-right",
      format: "yyyy/mm/dd/",
      endDate: new Date() // 只能选当前日期之前
    });

    // 更改网页标题
    document.title = "图床广场 - 今日上传<?php echo get_file_by_glob(APP_ROOT . config_path(), 'number'); ?>张 昨日<?php echo get_file_by_glob(APP_ROOT . $config['path'] . date("Y/m/d/", strtotime("-1 day")) . '*.*', 'number'); ?>张 - <?php echo $config['title']; ?>"
  </script>
  <?php
  /** 引入底部 */
  require_once __DIR__ . '/footer.php';
