<?php
// 旧留言导入（仅管理员）：把 messages 表里还没导入的留言一次性变成论坛帖子。
require_once __DIR__ . '/_auth.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>旧留言导入 | 温二外25级博雅班</title>
    <meta name="robots" content="noindex, nofollow">
    <script>
    (function () {
        try {
            var saved = localStorage.getItem('theme');
            var theme = (saved === 'dark' || saved === 'light')
                ? saved
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; padding: 24px 20px; }
        .container { max-width: 820px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 28px; padding-bottom: 16px; border-bottom: 1px solid #eaeaea; }
        h1 { font-size: 1.5rem; font-weight: 500; }
        h2 { font-size: 1.05rem; font-weight: 600; margin-bottom: 10px; }
        .card { background: #fff; border-radius: 16px; padding: 22px 24px; border: 1px solid #ececec; }
        .card p { font-size: 0.85rem; color: #555; line-height: 1.8; margin-bottom: 10px; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 14px 18px; margin-top: 16px; border-radius: 12px; color: #856404; font-size: 0.85rem; }
        .alert-ok { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .alert-error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .btn { border: none; background: #111; color: white; padding: 9px 18px; border-radius: 40px; cursor: pointer; font-size: 0.82rem; }
        .btn:disabled { background: #999; cursor: not-allowed; }
        code { background: #f2f2f2; padding: 2px 6px; border-radius: 4px; }
    </style>
    <link rel="stylesheet" href="../css/admin-dark.css?v=<?= filemtime(__DIR__ . '/../css/admin-dark.css') ?>">
</head>
<body>
<div class="container">
    <div class="admin-header">
        <h1>📥 旧留言导入</h1>
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="admin-list.php" style="font-size:0.8rem; color:#555;">返回面板</a>
            <form method="post" action="?logout=1" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                <input type="hidden" name="logout" value="1">
                <button type="submit" style="border:0;background:none;cursor:pointer;padding:0;font-size:0.8rem;color:#555;">退出登录</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>把旧留言导入成帖子</h2>
        <p>旧留言板（<code>messages</code> 表）里的内容会原样变成论坛帖子，作者显示为原来的署名并带「历史留言」标记。旧数据不会被修改或删除，只在导入过的行上记一个 <code>migrated_post_id</code>，所以这个按钮可以安全地重复点。</p>
        <p>待导入：<b id="pendingCount">读取中…</b></p>
        <button class="btn" id="runBtn" type="button">开始导入</button>
        <div class="alert" id="result" style="display:none;"></div>
    </div>
</div>

<script>
    var CSRF_TOKEN = '<?= htmlspecialchars(admin_csrf_token()) ?>';
    var API = '../api/admin-migrate-messages.php';

    function showResult(text, kind) {
        var box = document.getElementById('result');
        box.textContent = text;
        box.className = 'alert' + (kind ? ' alert-' + kind : '');
        box.style.display = 'block';
    }

    function loadPending() {
        fetch(API, { cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                document.getElementById('pendingCount').textContent = (data.pending === undefined ? '?' : data.pending) + ' 条';
            })
            .catch(function () {
                document.getElementById('pendingCount').textContent = '读取失败';
            });
    }

    document.getElementById('runBtn').addEventListener('click', function () {
        if (!confirm('确定把还没导入的旧留言全部导入成帖子吗？')) {
            return;
        }
        var btn = this;
        btn.disabled = true;
        btn.textContent = '导入中…';
        var body = new URLSearchParams();
        body.append('csrf_token', CSRF_TOKEN);

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    showResult(data.message || '导入完成', 'ok');
                } else {
                    showResult(data.message || '导入失败', 'error');
                }
                loadPending();
            })
            .catch(function () {
                showResult('网络错误，导入未完成', 'error');
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = '开始导入';
            });
    });

    loadPending();
</script>
</body>
</html>
