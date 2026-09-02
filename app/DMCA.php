<?php
/*
 * 使用条款页面
 */
define('EASYIMAGE_FRONTEND_STYLE', true);
require_once __DIR__ . '/header.php';

/** 顶部广告 */
if ($config['ad_top']) echo $config['ad_top_info'];
?>

<main class="easy-terms-page">
  <section class="easy-terms-hero">
    <a class="easy-terms-back" href="<?php echo $config['domain']; ?>"><i class="icon icon-chevron-left"></i> 返回首页</a>
    <h1>使用条款</h1>
    <p>这里包含服务条款、隐私政策与版权投诉说明。上传或访问图片前，请先了解这些基本规则。</p>
  </section>

  <section class="easy-terms-card">
    <div class="easy-terms-heading">
      <div>
        <h2>站点协议</h2>
        <p>Terms / Privacy / DMCA</p>
      </div>
    </div>
    <div class="easy-terms-content">
      <?php
      /** 加载使用条款 */
      if (empty($config['terms'])) {
        echo '<div class="alert alert-danger">Terms not set!<br />未设置使用条款</div>';
      } else {
        echo $config['terms'];
      }
      ?>
    </div>
  </section>
</main>

<?php
/** 底部广告 */
if ($config['ad_bot']) echo $config['ad_bot_info'];
?>

<script>
  // Title
  document.title = <?php echo json_encode('使用条款 - ' . $config['title'], JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php
require_once __DIR__ . '/footer.php';
