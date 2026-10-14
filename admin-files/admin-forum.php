<?php
// 论坛管理（仅管理员）：帖子列表 + 置顶 / 锁定 / 删除 / 恢复。
require_once __DIR__ . '/_auth.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>论坛管理 | 温二外25级博雅班</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; padding: 24px 20px; }
        .container { max-width: 1040px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eaeaea; }
        h1 { font-size: 1.5rem; font-weight: 500; }
        h2 { font-size: 1.05rem; font-weight: 600; margin-bottom: 12px; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; border: 1px solid #ececec; margin-bottom: 18px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
        .btn { border: none; background: #111; color: white; padding: 6px 14px; border-radius: 40px; cursor: pointer; font-size: 0.78rem; }
        .btn.ghost { background: #eee; color: #333; }
        .btn.active { background: #111; color: #fff; }
        .btn.danger { background: #c0392b; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        th { color: #666; font-weight: 500; }
        .muted { color: #888; font-size: 0.78rem; }
        .badge { display: inline-block; font-size: 0.68rem; padding: 1px 6px; border-radius: 20px; background: #eee; color: #555; margin-left: 4px; }
        .badge.warn { background: #fdecea; color: #b3261e; }
        a { color: #111; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 12px; color: #856404; font-size: 0.8rem; margin-top: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-header">
        <h1>💬 论坛管理</h1>
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
        <h2>帖子列表</h2>
        <div class="toolbar">
            <button class="btn ghost" data-filter="all">全部</button>
            <button class="btn ghost" data-filter="visible">正常</button>
            <button class="btn ghost" data-filter="deleted">已删除</button>
            <span class="muted" id="summary"></span>
        </div>
        <div id="list"><span class="muted">加载中…</span></div>
        <div class="toolbar" id="pager" style="margin-top:14px; justify-content:center;"></div>
        <div class="alert" id="result" style="display:none;"></div>
    </div>
</div>

<script>
    var CSRF = '<?= htmlspecialchars(admin_csrf_token()) ?>';
    var API = '../api/admin-forum.php';
    var state = { filter: 'all', page: 1 };

    function esc(text) {
        if (text === null || text === undefined) { return ''; }
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function showResult(text, isError) {
        var box = document.getElementById('result');
        box.textContent = text;
        box.style.background = isError ? '#fdecea' : '#e8f5e9';
        box.style.borderLeftColor = isError ? '#c0392b' : '#27ae60';
        box.style.color = isError ? '#b3261e' : '#1b5e20';
        box.style.display = 'block';
    }

    function load() {
        fetch(API + '?filter=' + encodeURIComponent(state.filter) + '&page=' + state.page, { cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    document.getElementById('list').innerHTML = '<span class="muted">' + esc(data.message || '加载失败') + '</span>';
                    return;
                }
                var posts = data.posts || [];
                document.getElementById('summary').textContent = '共 ' + data.total + ' 条';

                if (!posts.length) {
                    document.getElementById('list').innerHTML = '<span class="muted">没有帖子</span>';
                    document.getElementById('pager').innerHTML = '';
                    return;
                }

                var html = '<table><thead><tr><th>ID</th><th>标题</th><th>作者</th><th>回复</th><th>浏览</th><th>状态</th><th>操作</th></tr></thead><tbody>';
                posts.forEach(function (post) {
                    var badges = '';
                    if (post.is_pinned) { badges += '<span class="badge">置顶</span>'; }
                    if (post.is_locked) { badges += '<span class="badge">锁定</span>'; }
                    if (post.is_deleted) { badges += '<span class="badge warn">已删除</span>'; }

                    html += '<tr>'
                        + '<td>' + post.id + '</td>'
                        + '<td><a href="../post.php?id=' + post.id + '" target="_blank" rel="noopener">' + esc(post.title) + '</a>' + badges + '</td>'
                        + '<td>' + esc(post.author_username || post.author_name) + '<br><span class="muted">UID ' + post.author_id + '</span></td>'
                        + '<td>' + post.reply_count + '</td>'
                        + '<td>' + post.view_count + '</td>'
                        + '<td>' + (post.is_deleted ? '已删除' : '正常') + '</td>'
                        + '<td>'
                        + '<button class="btn ghost" data-act="' + (post.is_pinned ? 'unpin' : 'pin') + '" data-id="' + post.id + '">' + (post.is_pinned ? '取消置顶' : '置顶') + '</button> '
                        + '<button class="btn ghost" data-act="' + (post.is_locked ? 'unlock' : 'lock') + '" data-id="' + post.id + '">' + (post.is_locked ? '解锁' : '锁定') + '</button> '
                        + (post.is_deleted
                            ? '<button class="btn" data-act="restore" data-id="' + post.id + '">恢复</button>'
                            : '<button class="btn danger" data-act="delete" data-id="' + post.id + '">删除</button>')
                        + '</td></tr>';
                });
                html += '</tbody></table>';
                document.getElementById('list').innerHTML = html;

                document.querySelectorAll('#list button[data-act]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (btn.dataset.act === 'delete' && !confirm('确定删除这条帖子吗？（可以恢复）')) { return; }
                        act(btn.dataset.act, 'post', Number(btn.dataset.id));
                    });
                });

                var pager = document.getElementById('pager');
                if (data.totalPages <= 1) {
                    pager.innerHTML = '';
                } else {
                    pager.innerHTML = '<button class="btn ghost" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>上一页</button>'
                        + '<span class="muted">第 ' + data.page + ' / ' + data.totalPages + ' 页</span>'
                        + '<button class="btn ghost" data-page="' + (data.page + 1) + '"' + (data.page >= data.totalPages ? ' disabled' : '') + '>下一页</button>';
                    pager.querySelectorAll('button[data-page]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            state.page = Number(btn.dataset.page);
                            load();
                        });
                    });
                }
            })
            .catch(function () {
                document.getElementById('list').innerHTML = '<span class="muted">网络错误，请刷新重试</span>';
            });
    }

    function act(action, type, id) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('type', type);
        body.append('id', String(id));
        body.append('csrf_token', CSRF);

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                showResult(data.message || (data.ok ? '操作完成' : '操作失败'), !data.ok);
                load();
            })
            .catch(function () {
                showResult('网络错误，操作失败', true);
            });
    }

    document.querySelectorAll('button[data-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.filter = btn.dataset.filter;
            state.page = 1;
            document.querySelectorAll('button[data-filter]').forEach(function (other) {
                other.classList.remove('active');
            });
            btn.classList.add('active');
            load();
        });
    });
    document.querySelector('button[data-filter="all"]').classList.add('active');

    load();
</script>
</body>
</html>
