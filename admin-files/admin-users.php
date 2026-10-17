<?php
// 同学账号管理（仅管理员）：名单、称号、论坛管理员、重置口令、分配用户名、新增同学。
// 接口在 api/admin-users.php。
require_once __DIR__ . '/_layout.php';

admin_page_head('账号管理', ['width' => 1120, 'title' => '账号管理']);
?>
<div class="card">
    <h2>同学名单</h2>
    <div class="toolbar">
        <input type="text" id="q" placeholder="按姓名 / 用户名 / 昵称搜索">
        <button class="btn ghost" id="searchBtn" type="button">搜索</button>
        <button class="btn ghost" id="assignBtn" type="button">给未设用户名的同学批量分配</button>
        <button class="btn ghost" id="dedupeBtn" type="button" title="把旧的个性签名并入座右铭，并删掉重复的 signature 字段">清理重复的个性签名字段</button>
        <span class="muted" id="summary"></span>
    </div>
    <div id="list"><span class="muted">加载中…</span></div>
    <div class="toolbar" id="pager" style="margin-top:14px; justify-content:center;"></div>
    <div class="alert" id="result" style="display:none;"></div>
</div>

<div class="card">
    <h2>新增同学</h2>
    <div class="grid">
        <div><label for="newName">姓名</label><input type="text" id="newName" placeholder="真实姓名"></div>
        <div><label for="newUsername">用户名（可留空）</label><input type="text" id="newUsername" placeholder="3-20 位字母数字下划线"></div>
        <div><label for="newPassword">初始口令（留空自动生成）</label><input type="text" id="newPassword" placeholder="留空自动生成"></div>
        <div><button class="btn" id="addBtn" type="button">添加</button></div>
    </div>
    <div class="alert" id="addResult" style="display:none;"></div>
</div>

<script src="<?= boya_asset('js/admin-common.js') ?>"></script>
<script>
(function () {
    'use strict';
    var Admin = window.BoyaAdmin;
    var API = Admin.url('api/admin-users.php');
    var state = { page: 1, q: '' };

    function load() {
        Admin.fetchJson(API + '?page=' + state.page + '&q=' + encodeURIComponent(state.q)).then(function (res) {
            var data = res.data;
            if (!res.ok || !data.ok) {
                Admin.$('list').innerHTML = '<span class="muted">' + Admin.esc(data.message || '加载失败') + '</span>';
                return;
            }
            var users = data.users || [];
            Admin.$('summary').textContent = '共 ' + data.total + ' 位';

            if (!users.length) {
                Admin.$('list').innerHTML = '<span class="muted">没有匹配的同学</span>';
                Admin.$('pager').innerHTML = '';
                return;
            }

            var html = '<table><thead><tr><th>UID</th><th>姓名</th><th>用户名</th><th>昵称</th><th>称号</th><th>论坛管理</th><th>帖子/回复</th><th>加入 / 最后活跃</th><th>操作</th></tr></thead><tbody>';
            users.forEach(function (user) {
                html += '<tr>'
                    + '<td>' + user.id + '</td>'
                    + '<td>' + Admin.esc(user.name) + '</td>'
                    + '<td><input class="mini" type="text" data-field="username" data-uid="' + user.id + '" value="' + Admin.esc(user.username || '') + '" placeholder="未设置">'
                    + '<button class="btn ghost" type="button" data-save-username="' + user.id + '">保存</button></td>'
                    + '<td>' + Admin.esc(user.nickname || '') + '</td>'
                    + '<td><input class="mini" type="text" data-field="title" data-uid="' + user.id + '" value="' + Admin.esc(user.title || '') + '" placeholder="称号">'
                    + '<button class="btn ghost" type="button" data-save-title="' + user.id + '">保存</button></td>'
                    + '<td><button class="btn ghost" type="button" data-toggle-admin="' + user.id + '" data-admin="' + user.is_admin + '">'
                    + (user.is_admin ? '是（点此取消）' : '否（点此设为）') + '</button></td>'
                    + '<td>' + user.post_count + ' / ' + user.reply_count + '</td>'
                    + '<td><span class="muted">' + Admin.esc(Admin.shortTime(user.created_at)) + '<br>' + Admin.esc(Admin.shortTime(user.last_active_at)) + '</span></td>'
                    + '<td><button class="btn danger" type="button" data-reset="' + user.id + '">重置口令</button></td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            Admin.$('list').innerHTML = html;

            Admin.pager('pager', Number(data.page || 1), Number(data.totalPages || 1), function (page) {
                state.page = page;
                load();
            });

            bindRowActions();
        });
    }

    function fieldValue(uid, field) {
        var input = document.querySelector('input[data-field="' + field + '"][data-uid="' + uid + '"]');
        return input ? input.value.trim() : '';
    }

    function bindRowActions() {
        var list = Admin.$('list');

        list.querySelectorAll('button[data-save-username]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uid = btn.dataset.saveUsername;
                post({ action: 'set_username', id: uid, username: fieldValue(uid, 'username') });
            });
        });
        list.querySelectorAll('button[data-save-title]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var uid = btn.dataset.saveTitle;
                post({ action: 'set_title', id: uid, title: fieldValue(uid, 'title') });
            });
        });
        list.querySelectorAll('button[data-toggle-admin]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var next = btn.dataset.admin === '1' ? '0' : '1';
                if (next === '1' && !confirm('把这位同学设为论坛管理员？（可以置顶/锁定/删除帖子）')) { return; }
                post({ action: 'set_admin', id: btn.dataset.toggleAdmin, is_admin: next });
            });
        });
        list.querySelectorAll('button[data-reset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('重置这位同学的口令？新口令只显示一次。')) { return; }
                post({ action: 'reset_password', id: btn.dataset.reset });
            });
        });
    }

    /** 发一个后台动作，返回接口的 data（失败时也返回，调用方可看 ok 字段）。 */
    function post(params) {
        return Admin.postForm(API, params).then(function (res) {
            var data = res.data || {};
            if (data.password) {
                alert(data.message || ('新口令：' + data.password));
            }
            Admin.showResult('result', data.message || (data.ok ? '操作完成' : '操作失败'), data.ok ? 'ok' : 'error');
            load();
            return data;
        });
    }

    Admin.$('searchBtn').addEventListener('click', function () {
        state.q = Admin.$('q').value.trim();
        state.page = 1;
        load();
    });
    Admin.$('q').addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            Admin.$('searchBtn').click();
        }
    });

    Admin.$('assignBtn').addEventListener('click', function () {
        if (!confirm('给所有还没设用户名的同学自动分配 boya001 这种用户名？')) { return; }
        post({ action: 'assign_missing' });
    });

    // 一次性清理：座右铭与个性签名重复，只留座右铭；幂等，可以放心多点几次
    Admin.$('dedupeBtn').addEventListener('click', function () {
        if (!confirm('把「个性签名」里还没搬过来的内容并入「座右铭」，然后删除 signature 字段？\n\n只会填充座右铭为空的行，不会覆盖同学自己填的座右铭。')) { return; }
        post({ action: 'dedupe_profile_fields' });
    });

    Admin.$('addBtn').addEventListener('click', function () {
        var name = Admin.$('newName').value.trim();
        if (!name) {
            alert('请先填姓名');
            return;
        }
        post({
            action: 'add_member',
            name: name,
            username: Admin.$('newUsername').value.trim(),
            password: Admin.$('newPassword').value
        }).then(function (data) {
            if (data && data.ok) {
                Admin.$('newName').value = '';
                Admin.$('newUsername').value = '';
                Admin.$('newPassword').value = '';
                Admin.showResult('addResult', data.message || '已添加', 'ok');
            }
        });
    });

    load();
})();
</script>
<?php
admin_page_foot();
