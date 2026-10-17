<?php
// 论坛管理（仅管理员）：帖子列表 + 置顶 / 锁定 / 删除 / 恢复。
require_once __DIR__ . '/_layout.php';

admin_page_head('论坛管理', ['width' => 1040, 'title' => '论坛管理']);
?>
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

<script src="<?= boya_asset('js/admin-common.js') ?>"></script>
<script>
(function () {
    'use strict';
    var Admin = window.BoyaAdmin;
    var API = Admin.url('api/admin-forum.php');
    var state = { filter: 'all', page: 1 };

    function load() {
        Admin.fetchJson(API + '?filter=' + encodeURIComponent(state.filter) + '&page=' + state.page).then(function (res) {
            var data = res.data;
            if (!res.ok || !data.ok) {
                Admin.$('list').innerHTML = '<span class="muted">' + Admin.esc(data.error || data.message || '加载失败') + '</span>';
                return;
            }
            var posts = data.posts || [];
            Admin.$('summary').textContent = '共 ' + data.total + ' 条';

            if (!posts.length) {
                Admin.$('list').innerHTML = '<span class="muted">没有帖子</span>';
                Admin.$('pager').innerHTML = '';
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
                    + '<td><a href="' + Admin.url('post.php?id=' + post.id) + '" target="_blank" rel="noopener">' + Admin.esc(post.title) + '</a>' + badges + '</td>'
                    + '<td>' + Admin.esc(post.author_username || post.author_name) + '<br><span class="muted">UID ' + post.author_id + '</span></td>'
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
            Admin.$('list').innerHTML = html;

            Array.prototype.forEach.call(document.querySelectorAll('#list button[data-act]'), function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.dataset.act === 'delete' && !confirm('确定删除这条帖子吗？（可以恢复）')) { return; }
                    act(btn.dataset.act, 'post', Number(btn.dataset.id));
                });
            });

            Admin.pager('pager', Number(data.page || 1), Number(data.totalPages || 1), function (page) {
                state.page = page;
                load();
            });
        });
    }

    function act(action, type, id) {
        Admin.postForm(API, { action: action, type: type, id: id }).then(function (res) {
            var ok = res.ok && res.data.ok;
            Admin.showResult('result', res.data.message || (ok ? '操作完成' : '操作失败'), ok ? 'ok' : 'error');
            load();
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('button[data-filter]'), function (btn) {
        btn.addEventListener('click', function () {
            state.filter = btn.dataset.filter;
            state.page = 1;
            Array.prototype.forEach.call(document.querySelectorAll('button[data-filter]'), function (other) {
                other.classList.remove('active');
            });
            btn.classList.add('active');
            load();
        });
    });
    document.querySelector('button[data-filter="all"]').classList.add('active');

    load();
})();
</script>
<?php
admin_page_foot();
