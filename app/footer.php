<?php

defined('APP_ROOT') ?: exit;

if ($config['notice_status'] > 0) : ?>
  <div class="modal fade" id="notice">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">
            <span aria-hidden="true">x</span>
            <span class="sr-only">关闭</span>
          </button>
          <p class="modal-title icon icon-bell" style="text-align: center"> 网站公告</p>
        </div>
        <div class="modal-body">
          <?php echo $config['notice']; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-mini btn-primary" data-dismiss="modal">知道了</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if (!defined('EASYIMAGE_FRONTEND_STYLE')) : ?>
  <div class="col-md-12 clo-xs-12" style="margin-bottom: 108px;position:relative;"></div>
<?php endif; ?>

<footer class="container text-muted small navbar-fixed-bottom <?php echo defined('EASYIMAGE_FRONTEND_STYLE') ? 'easy-footer' : ''; ?>" style="text-align: center;background-color:rgba(255,255,255,0.7);z-index: 0;">
  <hr>
  <?php echo $config['footer']; ?>
  <p>
    <a href="https://github.com/HH2XX/EasyImages" target="_blank" rel="nofollow noopener" data-toggle="tooltip" title="HH2XX 维护的 EasyImage">© Since 2018 · HH2XX</a>
    <a href="<?php echo $config['domain']; ?>" target="_blank" data-toggle="tooltip" title="EasyImage"><?php echo htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8'); ?></a>
    <a href="/app/DMCA.php" target="_blank" data-toggle="tooltip" title="使用协议">服务条款</a>
    <a href="/app/contact.php" target="_blank" data-toggle="tooltip" title="联系站点管理">联系我们</a>
    <?php if (!empty($config['dark-mode'])) : ?>
      <a id="dark" data-toggle="tooltip" title="黑暗模式"><i class="icon icon-lightbulb" id="dark_ico"></i></a>
    <?php endif; ?>
  </p>
</footer>

<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/nprogress/nprogress.min.js"></script>
<script>
  NProgress.configure({
    barColor: '<?php echo $config['NProgress_default']; ?>'
  });
  NProgress.start();
  NProgress.done();

  $('.nav-pills').find('a').each(function() {
    if (this.pathname === location.pathname) {
      $(this).parent().addClass('active');
    }
  });

  <?php if ($config['notice_status'] == 1 && !empty($config['notice'])) : ?>
    if (document.cookie.indexOf('noticed=') === -1) {
      $('#notice').modal({
        backdrop: false,
        loadingIcon: 'icon-spin',
        scrollInside: true,
        moveable: 'inside',
        rememberPos: true
      }).on('hidden.zui.modal', function() {
        document.cookie = 'noticed=1';
      });
    }
  <?php endif; ?>

  <?php if ($config['language'] == 1) : ?>
    $.getScript("<?php static_cdn(); ?>/public/static/i18n/jquery.s2t.js", function() {
      $('*').s2t();
    });
  <?php endif; ?>

  <?php if (!empty($config['dark-mode'])) : ?>
    var cookieUtil = {
      setItem: function(name, value, days) {
        var date = new Date();
        date.setDate(date.getDate() + days);
        document.cookie = name + '=' + value + ';expires=' + date + ';path=/';
      },
      getItem: function(name) {
        var arr = document.cookie.replace(/\s/g, '').split(';');
        for (var i = 0; i < arr.length; i++) {
          var tempArr = arr[i].split('=');
          if (tempArr[0] === name) {
            return decodeURIComponent(tempArr[1]);
          }
        }
        return '';
      },
      removeItem: function(name) {
        this.setItem(name, '1', -1);
      }
    };

    var darkKey = 'easyimage_dark_mode';
    var applyDarkMode = function(enabled) {
      if (enabled) {
        $('body').addClass('dark');
      } else {
        $('body').removeClass('dark');
      }
    };

    applyDarkMode(cookieUtil.getItem(darkKey) === '1');

    $(document).on('click', '#dark', function() {
      var enabled = !$('body').hasClass('dark');
      applyDarkMode(enabled);
      cookieUtil.setItem(darkKey, enabled ? '1' : '0', 365);
    });
  <?php endif; ?>
</script>
