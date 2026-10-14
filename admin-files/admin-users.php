<?php
// 同学账号管理（仅管理员）：名单、称号、论坛管理员、重置口令、分配用户名、新增同学。
require_once __DIR__ . '/_auth.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号管理 | 温二外25级博雅班</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; padding: 24px 20px; }
        .container { max-width: 1120px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eaeaea; }
        h1 { font-size: 1.5rem; font-weight: 500; }
        h2 { font-size: 1.05rem; font-weight: 600; margin-bottom: 12px; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; border: 1px solid #ececec; margin-bottom: 18px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
        .btn { border: none; background: #111; color: white; padding: 6px 14px; border-radius: 40px; cursor: pointer; font-size: 0.78rem; }
        .btn.ghost { background: #eee; color: #333; }
        .btn.danger { background: #c0392b; }
        .btn:disabled { background: #999; cursor: not-allowed; }
        input[type=text], input[type=password] { padding: 6px 9px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.8rem; font-family: inherit; }
        input.mini { width: 110px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
        th, td { text-align: left; padding: 7px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        th { color: #666; font-weight: 500; }
        .muted { color: #888; font-size: 0.76rem; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 12px; color: #856404; font-size: 0.8rem; margin-top: 12px; }
        .grid { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .grid label { display: block; font-size: 0.75rem; color: #666; margin-bottom: 3px; }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-header">
        <h1>👤 账号管理</h1>
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
        <h2>同学名单</h2>
        <div class="toolbar">
            <input type="text" id="q" placeholder="按姓名 / 用户名 / 昵称搜索">
            <button class="btn ghost" id="searchBtn">搜索</button>
            <button class="btn ghost" id="assignBtn">给未设用户名的同学批量分配</button>
            <span class="muted" id="summary"></span>
        </div>
        <div id="list"><span class="muted">加载中…</span></div>
        <div class="toolbar" id="pager" style="margin-top:14px; justify-content:center;"></div>
        <div class="alert" id="result" style="display:none;"></div>
    </div>

    <div class="card">
        <h2>新增同学</h2>
        <div class="grid">
            <div><label>姓名</label><input type="text" id="newName" placeholder="真实姓名"></div>
            <div><label>用户名（可留空）</label><input type="text" id="newUsername" placeholder="3-20 位字母数字下划线"></div>
            <div><label>初始口令（留空自动生成）</label><input type="text" id="newPassword" placeholder="留空自动生成"></div>
            <div><button class="btn" id="addBtn">添加</button></div>
        </div>
        <div class="alert" id="addResult" style="display:none;"></div>
    </div>
</div>

<script>
    var CSRF = '<?= htmlspecialchars(admin_csrf_token()) ?>';
    var API = '../api/admin-users.php';
    var state = { page: 1, q: '' };

    function esc(text) {
        if (text === null || text === undefined) { return ''; }
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function shortTime(value) {
        var text = String(value === null || value === undefined ? '' : value);
        return text.length >= 16 ? text.slice(0, 16) : text;
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
        var url = API + '?page=' + state.page + '&q=' + encodeURIComponent(state.q);
        fetch(url, { cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    document.getElementById('list').innerHTML = '<span class="muted">' + esc(data.message || '加载失败') + '</span>';
                    return;
                }
                var users = data.users || [];
                document.getElementById('summary').textContent = '共 ' + data.total + ' 位';

                if (!users.length) {
                    document.getElementById('list').innerHTML = '<span class="muted">没有匹配的同学</span>';
                    document.getElementById('pager').innerHTML = '';
                    return;
                }

                var html = '<table><thead><tr><th>UID</th><th>姓名</th><th>用户名</th><th>昵称</th><th>称号</th><th>论坛管理</th><th>帖子/回复</th><th>加入 / 最后活跃</th><th>操作</th></tr></thead><tbody>';
                users.forEach(function (user) {
                    html += '<tr>'
                        + '<td>' + user.id + '</td>'
                        + '<td>' + esc(user.name) + '</td>'
                        + '<td><input class="mini" type="text" data-field="username" data-uid="' + user.id + '" value="' + esc(user.username || '') + '" placeholder="未设置">'
                        + '<button class="btn ghost" data-save-username="' + user.id + '">保存</button></td>'
                        + '<td>' + esc(user.nickname || '') + '</td>'
                        + '<td><input class="mini" type="text" data-field="title" data-uid="' + user.id + '" value="' + esc(user.title || '') + '" placeholder="称号">'
                        + '<button class="btn ghost" data-save-title="' + user.id + '">保存</button></td>'
                        + '<td><button class="btn ghost" data-toggle-admin="' + user.id + '" data-admin="' + user.is_admin + '">'
                        + (user.is_admin ? '是（点此取消）' : '否（点此设为）') + '</button></td>'
                        + '<td>' + user.post_count + ' / ' + user.reply_count + '</td>'
                        + '<td><span class="muted">' + shortTime(user.created_at) + '<br>' + shortTime(user.last_active_at) + '</span></td>'
                        + '<td><button class="btn danger" data-reset="' + user.id + '">重置口令</button></td>'
                        + '</tr>';
                });
                html += '</tbody></table>';
                document.getElementById('list').innerHTML = html;

                document.querySelectorAll('#list button[data-save-username]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var uid = btn.dataset.saveUsername;
                        var input = document.querySelector('input[data-field="username"][data-uid="' + uid + '"]');
                        post({ action: 'set_username', id: uid, username: input ? input.value.trim() : '' });
                    });
                });
                document.querySelectorAll('#list button[data-save-title]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var uid = btn.dataset.saveTitle;
                        var input = document.querySelector('input[data-field="title"][data-uid="' + uid + '"]');
                        post({ action: 'set_title', id: uid, title: input ? input.value.trim() : '' });
                    });
                });
                document.querySelectorAll('#list button[data-toggle-admin]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var next = btn.dataset.admin === '1' ? '0' : '1';
                        if (next === '1' && !confirm('把这位同学设为论坛管理员？（可以置顶/锁定/删除帖子）')) { return; }
                        post({ action: 'set_admin', id: btn.dataset.toggleAdmin, is_admin: next });
                    });
                });
                document.querySelectorAll('#list button[data-reset]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('重置这位同学的口令？新口令只显示一次。')) { return; }
                        post({ action: 'reset_password', id: btn.dataset.reset });
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

    function post(params) {
        var body = new URLSearchParams();
        Object.keys(params).forEach(function (key) { body.append(key, String(params[key])); });
        body.append('csrf_token', CSRF);

        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.password) {
                    alert(data.message || ('新口令：' + data.password));
                }
                showResult(data.message || (data.ok ? '操作完成' : '操作失败'), !data.ok);
                load();
                return data;
            })
            .catch(function () {
                showResult('网络错误，操作失败', true);
            });
    }

    document.getElementById('searchBtn').addEventListener('click', function () {
        state.q = document.getElementById('q').value.trim();
        state.page = 1;
        load();
    });
    document.getElementById('q').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            document.getElementById('searchBtn').click();
        }
    });

    document.getElementById('assignBtn').addEventListener('click', function () {
        if (!confirm('给所有还没设用户名的同学自动分配 boya001 这种用户名？')) { return; }
        post({ action: 'assign_missing' });
    });

    document.getElementById('addBtn').addEventListener('click', function () {
        var name = document.getElementById('newName').value.trim();
        if (!name) {
            alert('请先填姓名');
            return;
        }
        var box = document.getElementById('addResult');
        post({
            action: 'add_member',
            name: name,
            username: document.getElementById('newUsername').value.trim(),
            password: document.getElementById('newPassword').value
        }).then(function (data) {
            if (data && data.ok) {
                document.getElementById('newName').value = '';
                document.getElementById('newUsername').value = '';
                document.getElementById('newPassword').value = '';
                box.textContent = data.message || '已添加';
                box.style.background = '#e8f5e9';
                box.style.borderLeftColor = '#27ae60';
                box.style.color = '#1b5e20';
                box.style.display = 'block';
            }
        });
    });

    load();
</script>
</body>
</html>
