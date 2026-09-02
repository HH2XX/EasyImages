<?php
/*
 * 联系我们页面
 */
define('EASYIMAGE_FRONTEND_STYLE', true);
require_once __DIR__ . '/header.php';

/** 顶部广告 */
if ($config['ad_top']) echo $config['ad_top_info'];

$contactTarget = isset($config['report']) ? trim((string)$config['report']) : '';
$contactType = 'none';
if ($contactTarget !== '') {
  $contactType = filter_var($contactTarget, FILTER_VALIDATE_EMAIL) ? 'email' : 'url';
}
?>

<main class="easy-contact-page">
  <section class="easy-contact-hero">
    <a class="easy-terms-back" href="<?php echo $config['domain']; ?>"><i class="icon icon-chevron-left"></i> 返回首页</a>
    <h1>联系我们</h1>
    <p>图片删除、版权投诉、上传问题或功能建议，都可以从这里发起。把信息写清楚一点，我们处理起来也会更快。</p>
  </section>

  <section class="easy-contact-card">
    <div class="easy-contact-copy">
      <span class="easy-contact-kicker">EasyImage</span>
      <h2>让问题准确抵达</h2>
      <p>建议在消息中附上相关图片链接、问题出现时间和浏览器环境。DMCA 或删除请求请尽量提供权利说明，避免来回补材料。</p>
      <div class="easy-contact-points">
        <span><i class="icon icon-shield"></i> 版权与隐私请求</span>
        <span><i class="icon icon-comment-alt"></i> 问题反馈与建议</span>
        <span><i class="icon icon-time"></i> 请尽量附上图片链接</span>
      </div>
    </div>

    <form class="easy-contact-form" id="easyContactForm" data-contact-target="<?php echo htmlspecialchars($contactTarget, ENT_QUOTES, 'UTF-8'); ?>" data-contact-type="<?php echo $contactType; ?>">
      <label>
        <span>姓名</span>
        <input class="form-control" type="text" name="contact_name" placeholder="怎么称呼你" required>
      </label>
      <label>
        <span>邮箱</span>
        <input class="form-control" type="email" name="contact_email" placeholder="用于接收回复" required>
      </label>
      <label class="easy-contact-full">
        <span>主题</span>
        <select class="form-control" name="contact_subject" required>
          <option value="" selected disabled>请选择主题</option>
          <option value="DMCA 版权投诉">DMCA 版权投诉</option>
          <option value="图片删除请求">图片删除请求</option>
          <option value="上传或访问问题">上传或访问问题</option>
          <option value="功能建议">功能建议</option>
          <option value="其他问题">其他问题</option>
        </select>
      </label>
      <label class="easy-contact-full">
        <span>消息内容</span>
        <textarea class="form-control" name="contact_message" rows="5" placeholder="请描述问题，涉及图片时建议附上完整链接。" required></textarea>
      </label>
      <div class="easy-contact-actions easy-contact-full">
        <button class="btn easy-contact-submit" type="submit"><i class="icon icon-envelope"></i> 发送消息</button>
        <button class="btn easy-contact-reset" type="reset">取消</button>
      </div>
      <?php if ($contactType === 'none') : ?>
        <p class="easy-contact-hint easy-contact-full">站点暂未配置联系地址，请管理员在后台“举报地址”中填写邮箱或表单链接。</p>
      <?php else : ?>
        <p class="easy-contact-hint easy-contact-full">提交后会<?php echo $contactType === 'email' ? '打开你的邮件客户端' : '跳转到站点配置的联系表单'; ?>，请确认信息后发送。</p>
      <?php endif; ?>
    </form>
  </section>
</main>

<?php
/** 底部广告 */
if ($config['ad_bot']) echo $config['ad_bot_info'];
?>

<script>
  // Title
  document.title = <?php echo json_encode('联系我们 - ' . $config['title'], JSON_UNESCAPED_UNICODE); ?>;

  (function() {
    var form = document.getElementById('easyContactForm');
    if (!form) return;

    function notify(message, type) {
      if (window.$ && $.zui && $.zui.Messager) {
        new $.zui.Messager(message, {
          type: type || 'info',
          placement: 'bottom-right',
          icon: type === 'danger' ? 'times' : 'info-sign'
        }).show();
      } else {
        alert(message);
      }
    }

    form.addEventListener('submit', function(event) {
      event.preventDefault();
      if (form.checkValidity && !form.checkValidity()) {
        if (form.reportValidity) form.reportValidity();
        notify('请先填写完整的联系信息。', 'warning');
        return;
      }

      var target = form.getAttribute('data-contact-target') || '';
      var type = form.getAttribute('data-contact-type') || 'none';
      var name = form.elements.contact_name.value;
      var email = form.elements.contact_email.value;
      var subject = form.elements.contact_subject.value;
      var message = form.elements.contact_message.value;
      var body = [
        '姓名：' + name,
        '邮箱：' + email,
        '主题：' + subject,
        '页面：' + window.location.href,
        '',
        message
      ].join('\n');

      if (!target || type === 'none') {
        notify('站点暂未配置联系地址，请稍后再试。', 'warning');
        return;
      }

      if (type === 'email') {
        window.location.href = 'mailto:' + target.replace(/[\r\n]/g, '') +
          '?subject=' + encodeURIComponent('[' + <?php echo json_encode($config['title'], JSON_UNESCAPED_UNICODE); ?> + '] ' + subject) +
          '&body=' + encodeURIComponent(body);
        notify('已为你打开邮件客户端，请确认后发送。', 'success');
        return;
      }

      if (!/^https?:\/\//i.test(target) && target.charAt(0) !== '/') {
        target = 'https://' + target;
      }

      try {
        var url = new URL(target, window.location.origin);
        url.searchParams.set('name', name);
        url.searchParams.set('email', email);
        url.searchParams.set('subject', subject);
        url.searchParams.set('message', message);
        window.open(url.toString(), '_blank');
      } catch (error) {
        window.open(target, '_blank');
      }
      notify('已打开联系表单，请在新页面确认提交。', 'success');
    });
  })();
</script>

<?php
require_once __DIR__ . '/footer.php';
