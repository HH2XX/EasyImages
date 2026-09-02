<?php
define('EASYIMAGE_FRONTEND_STYLE', true);
include_once __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="<?php static_cdn(); ?>/public/static/viewjs/viewer.min.css">
<div class="easy-history">
    <div class="easy-page-bar">
        <div>
            <h1>上传历史</h1>
            <p>记录仅保存在当前浏览器。</p>
        </div>
        <button type="button" class="btn btn-danger" id="clearHistory" disabled><i class="icon icon-trash"></i> 清空记录</button>
    </div>
    <div class="easy-history-empty hidden" id="historyEmpty">
        <i class="icon icon-history" aria-hidden="true"></i>
        <h2>暂无上传记录</h2>
        <p>上传完成后，记录会显示在这里。</p>
    </div>
    <div id="viewjs" class="cards listNum" aria-live="polite"></div>
</div>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/viewjs/viewer.min.js"></script>
<script type="application/javascript" src="<?php static_cdn(); ?>/public/static/zui/lib/clipboard/clipboard.min.js"></script>
<script>
    (function() {
        var historyList = document.getElementById('viewjs');
        var historyEmpty = document.getElementById('historyEmpty');
        var clearHistoryButton = document.getElementById('clearHistory');
        var clipboard;
        var viewer;

        function showMessage(message, type, icon) {
            new $.zui.Messager(message, {
                type: type,
                icon: icon
            }).show();
        }

        function safeUrl(value) {
            if (typeof value !== 'string' || value === '') return '';

            try {
                var parsed = new URL(value, window.location.origin);
                return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.href : '';
            } catch (error) {
                return '';
            }
        }

        function createAction(href, icon, label, className) {
            var link = document.createElement('a');
            link.href = href;
            link.className = className || '';
            link.title = label;
            link.setAttribute('data-toggle', 'tooltip');

            var iconElement = document.createElement('i');
            iconElement.className = 'icon icon-' + icon;
            link.appendChild(iconElement);
            return link;
        }

        function createHistoryCard(key, item) {
            var imageUrl = safeUrl(item.url);
            if (!imageUrl) return null;

            var thumbnailUrl = safeUrl(item.thumb) || imageUrl;
            var urlObject = new URL(imageUrl);
            var fileName = item.srcName || urlObject.pathname.split('/').pop() || '未命名图片';
            var column = document.createElement('div');
            var card = document.createElement('div');
            var image = document.createElement('img');
            var fileNameElement = document.createElement('p');
            var actions = document.createElement('div');

            column.className = 'col-lg-3 col-md-4 col-sm-6 col-xs-12';
            card.className = 'card easy-history-card';
            image.src = thumbnailUrl;
            image.dataset.image = thumbnailUrl;
            image.dataset.original = imageUrl;
            image.alt = fileName;
            image.loading = 'lazy';
            fileNameElement.className = 'easy-history-name text-ellipsis';
            fileNameElement.textContent = fileName;
            fileNameElement.title = fileName;
            actions.className = 'bottom-bar';

            var openLink = createAction(imageUrl, 'picture', '打开原图');
            openLink.target = '_blank';
            openLink.rel = 'noopener';
            actions.appendChild(openLink);

            var copyLink = createAction('#', 'copy', '复制链接', 'history-copy');
            copyLink.dataset.clipboardText = imageUrl;
            actions.appendChild(copyLink);

            var infoLink = createAction('info.php?history=' + encodeURIComponent(urlObject.pathname), 'info-sign', '详细信息');
            infoLink.target = '_blank';
            infoLink.rel = 'noopener';
            actions.appendChild(infoLink);

            var downloadLink = createAction('down.php?history=' + encodeURIComponent(urlObject.pathname), 'cloud-download', '下载文件');
            downloadLink.target = '_blank';
            downloadLink.rel = 'noopener';
            actions.appendChild(downloadLink);

            var deleteUrl = safeUrl(item.del);
            if (deleteUrl) {
                var deleteFileLink = createAction(deleteUrl, 'trash', '删除文件');
                deleteFileLink.target = '_blank';
                deleteFileLink.rel = 'noopener';
                actions.appendChild(deleteFileLink);
            }

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'history-remove';
            removeButton.title = '删除记录';
            removeButton.setAttribute('data-history-key', key);
            removeButton.setAttribute('data-toggle', 'tooltip');
            removeButton.innerHTML = '<i class="icon icon-remove-sign"></i>';
            actions.appendChild(removeButton);

            card.appendChild(image);
            card.appendChild(fileNameElement);
            card.appendChild(actions);
            column.appendChild(card);
            return column;
        }

        function initClipboard() {
            if (clipboard) clipboard.destroy();

            clipboard = new Clipboard('.history-copy');
            clipboard.on('success', function() {
                showMessage('链接已复制', 'success', 'ok-sign');
            });
            clipboard.on('error', function() {
                showMessage('复制失败，请手动复制链接', 'danger', 'exclamation-sign');
            });
        }

        function initTooltips() {
            $(historyList).find('[data-toggle="tooltip"]').tooltip({container: 'body'});
        }

        function getHistoryEntries() {
            var entries = [];
            $.zui.store.forEach(function(key, value) {
                if (value && safeUrl(value.url)) {
                    entries.push({key: key, value: value});
                }
            });
            return entries;
        }

        function renderHistory() {
            var entries = getHistoryEntries();
            historyList.replaceChildren();
            historyEmpty.classList.toggle('hidden', entries.length > 0);
            clearHistoryButton.disabled = entries.length === 0;

            entries.forEach(function(entry) {
                var card = createHistoryCard(entry.key, entry.value);
                if (card) historyList.appendChild(card);
            });

            initClipboard();
            initTooltips();
            if (viewer) viewer.update();
        }

        historyList.addEventListener('click', function(event) {
            var removeButton = event.target.closest('[data-history-key]');
            if (!removeButton) return;

            event.preventDefault();
            var key = removeButton.getAttribute('data-history-key');
            if (!window.confirm('确定删除这条本地上传记录吗？')) return;

            $.zui.store.remove(key);
            renderHistory();
            showMessage('已删除上传记录', 'success', 'ok-sign');
        });

        clearHistoryButton.addEventListener('click', function() {
            var entries = getHistoryEntries();
            if (!entries.length || !window.confirm('确定清空全部本地上传记录吗？')) return;

            $.zui.store.clear();
            renderHistory();
            showMessage('已清空 ' + entries.length + ' 条上传记录', 'success', 'ok-sign');
        });

        renderHistory();
        viewer = new Viewer(historyList, {
            url: 'data-original'
        });
        document.title = '上传记录 - <?php echo htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8'); ?>';
    })();
</script>
<?php require_once __DIR__ . '/footer.php';
