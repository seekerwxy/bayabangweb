<?php
// 旧留言导入（仅管理员）：把 messages 表里还没导入的留言一次性变成论坛帖子。
require_once __DIR__ . '/_layout.php';

admin_page_head('旧留言导入', ['width' => 820, 'title' => '旧留言导入']);
?>
<div class="card">
    <h2>把旧留言导入成帖子</h2>
    <p>旧留言板（<code>messages</code> 表）里的内容会原样变成论坛帖子，作者显示为原来的署名并带「历史留言」标记。旧数据不会被修改或删除，只在导入过的行上记一个 <code>migrated_post_id</code>，所以这个按钮可以安全地重复点。</p>
    <p>待导入：<b id="pendingCount">读取中…</b></p>
    <button class="btn" id="runBtn" type="button">开始导入</button>
    <div class="alert" id="result" style="display:none;"></div>
</div>

<script src="<?= boya_asset('js/admin-common.js') ?>"></script>
<script>
(function () {
    'use strict';
    var Admin = window.BoyaAdmin;
    var API = Admin.url('api/admin-migrate-messages.php');

    function loadPending() {
        Admin.fetchJson(API).then(function (res) {
            var pending = res.data.pending;
            Admin.$('pendingCount').textContent = (pending === undefined ? '?' : pending) + ' 条';
        });
    }

    Admin.$('runBtn').addEventListener('click', function () {
        if (!confirm('确定把还没导入的旧留言全部导入成帖子吗？')) {
            return;
        }
        var btn = this;
        btn.disabled = true;
        btn.textContent = '导入中…';

        Admin.postForm(API, {}).then(function (res) {
            var ok = res.ok && res.data.ok;
            Admin.showResult('result', res.data.message || (ok ? '导入完成' : '导入失败'), ok ? 'ok' : 'error');
            loadPending();
        }).finally(function () {
            btn.disabled = false;
            btn.textContent = '开始导入';
        });
    });

    loadPending();
})();
</script>
<?php
admin_page_foot();
