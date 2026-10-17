<?php
// 旧留言板管理：列表 + 编辑 / 删除（管的是旧表 messages，论坛之前的留言）。接口在 api/admin-messages.php。
require_once __DIR__ . '/_layout.php';

admin_page_head('留言管理', [
    'width' => 1000,
    'stats' => '<span class="stats" id="totalCount">-</span>',
]);
?>
<div class="messages-list" id="messagesContainer">
    <div class="alert">加载中...</div>
</div>

<!-- 编辑模态框 -->
<div id="editModal" class="modal">
    <div class="modal-card">
        <h3>编辑留言</h3>
        <input type="hidden" id="editId">
        <label for="editTitle">标题</label><input type="text" id="editTitle" placeholder="标题（可选）">
        <label for="editAuthor">署名</label><input type="text" id="editAuthor" placeholder="署名" required>
        <label for="editContent">内容</label><textarea id="editContent" placeholder="留言内容" required></textarea>
        <label>文字颜色</label><div class="color-picker" id="colorPicker"></div>
        <div class="modal-buttons">
            <button class="cancel-btn" id="closeModalBtn" type="button">取消</button>
            <button class="save-btn" id="saveEditBtn" type="button">保存修改</button>
        </div>
    </div>
</div>
<div id="toastMsg" class="toast"></div>

<script src="<?= boya_asset('js/admin-common.js') ?>"></script>
<script>
(function () {
    'use strict';
    var Admin = window.BoyaAdmin;
    var API = Admin.url('api/admin-messages.php') + '?action=';
    var COLOR_LIST = ['#000000', '#e60000', '#ff9900', '#008000', '#0000ff', '#800080',
        '#ff1493', '#00ced1', '#32cd32', '#ffd700', '#ff4500', '#8a2be2'];
    var currentColor = '';

    function initColorPicker(selectedColor) {
        var container = Admin.$('colorPicker');
        if (!container) { return; }
        container.innerHTML = '';
        currentColor = '';

        COLOR_LIST.forEach(function (color) {
            var swatch = document.createElement('div');
            swatch.className = 'color-swatch';
            swatch.style.backgroundColor = color;
            swatch.dataset.color = color;
            if (color === selectedColor) { swatch.classList.add('selected'); }
            swatch.addEventListener('click', function () {
                container.querySelectorAll('.color-swatch').forEach(function (s) { s.classList.remove('selected'); });
                swatch.classList.add('selected');
                currentColor = color;
            });
            container.appendChild(swatch);
        });

        // 「清除颜色」色块
        var clearSwatch = document.createElement('div');
        clearSwatch.className = 'color-swatch';
        clearSwatch.style.background = 'linear-gradient(135deg, #ccc 0%, #ccc 100%)';
        clearSwatch.style.border = '1px solid #aaa';
        clearSwatch.style.display = 'flex';
        clearSwatch.style.alignItems = 'center';
        clearSwatch.style.justifyContent = 'center';
        clearSwatch.textContent = '✕';
        clearSwatch.addEventListener('click', function () {
            container.querySelectorAll('.color-swatch').forEach(function (s) { s.classList.remove('selected'); });
            currentColor = '';
            clearSwatch.classList.add('selected');
        });
        container.appendChild(clearSwatch);

        if (!selectedColor) {
            clearSwatch.classList.add('selected');
        } else {
            currentColor = selectedColor;
        }
    }

    function loadMessages() {
        var container = Admin.$('messagesContainer');
        container.innerHTML = '<div class="alert">加载留言中...</div>';
        Admin.fetchJson(API + 'list').then(function (res) {
            if (res.status === 401) {
                container.innerHTML = '<div class="alert">认证失败，请刷新页面重新输入密码</div>';
                return;
            }
            var result = res.data;
            if (!result.success || !result.data) {
                container.innerHTML = '<div class="alert">' + Admin.esc(result.error || '加载失败，请刷新页面重试') + '</div>';
                return;
            }
            var msgs = result.data;
            Admin.$('totalCount').textContent = '共 ' + msgs.length + ' 条';
            if (!msgs.length) {
                container.innerHTML = '<div class="alert">暂无留言</div>';
                return;
            }
            container.innerHTML = msgs.map(function (msg) {
                var time = new Date(msg.created_at).toLocaleString('zh-CN', { hour12: false });
                var titleDisplay = Admin.esc(msg.title) || '<span style="color:#aaa;">无标题</span>';
                var content = String(msg.content || '');
                var preview = Admin.esc(msg.content_preview || content.substring(0, 100));
                var safeColor = /^#[0-9a-fA-F]{6}$/.test(msg.color || '') ? msg.color : '';
                var colorDot = safeColor ? '<span class="color-dot" style="background:' + safeColor + '"></span>' : '';
                return '<div class="message-item">'
                    + '<div class="message-header"><div class="msg-title">' + titleDisplay + '</div>'
                    + '<div class="msg-author">' + colorDot + Admin.esc(msg.author) + '</div></div>'
                    + '<div class="msg-content-preview">' + preview + (content.length > 100 ? '…' : '') + '</div>'
                    + '<div class="msg-meta"><span class="msg-time">' + Admin.esc(time) + '</span>'
                    + '<div class="actions">'
                    + '<button class="edit-btn" type="button"'
                    + ' data-act="edit" data-id="' + msg.id + '"'
                    + ' data-title="' + Admin.esc(msg.title) + '"'
                    + ' data-author="' + Admin.esc(msg.author) + '"'
                    + ' data-content="' + Admin.esc(content) + '"'
                    + ' data-color="' + Admin.esc(msg.color || '') + '">编辑</button>'
                    + '<button class="delete-btn" data-act="delete" data-id="' + msg.id + '" type="button">删除</button>'
                    + '</div></div></div>';
            }).join('');
        });
    }

    function openEditModal(btn) {
        Admin.$('editId').value = btn.dataset.id;
        Admin.$('editTitle').value = btn.dataset.title === 'null' ? '' : btn.dataset.title;
        Admin.$('editAuthor').value = btn.dataset.author;
        Admin.$('editContent').value = btn.dataset.content;
        initColorPicker(btn.dataset.color || '');
        Admin.openModal('editModal');
    }

    function saveEdit() {
        var id = Admin.$('editId').value;
        var title = Admin.$('editTitle').value.trim();
        var author = Admin.$('editAuthor').value.trim();
        var content = Admin.$('editContent').value.trim();
        if (!author || !content) {
            Admin.toast('署名和内容不能为空');
            return;
        }
        Admin.postForm(API + 'update', {
            id: id,
            title: title,
            author: author,
            content: content,
            color: currentColor
        }).then(function (res) {
            if (res.data.success) {
                Admin.toast('更新成功');
                Admin.closeModal('editModal');
                loadMessages();
            } else {
                Admin.toast('更新失败: ' + (res.data.error || '未知错误'));
            }
        });
    }

    function deleteMessage(id) {
        if (!confirm('确定删除这条留言？')) { return; }
        Admin.postForm(API + 'delete', { id: id }).then(function (res) {
            if (res.data.success) {
                Admin.toast('已删除');
                loadMessages();
            } else {
                Admin.toast('删除失败');
            }
        });
    }

    Admin.$('closeModalBtn').addEventListener('click', function () { Admin.closeModal('editModal'); });
    Admin.$('saveEditBtn').addEventListener('click', saveEdit);
    Admin.$('editModal').addEventListener('click', function (event) {
        if (event.target === Admin.$('editModal')) { Admin.closeModal('editModal'); }
    });
    Admin.$('messagesContainer').addEventListener('click', function (event) {
        var btn = event.target.closest('button[data-act]');
        if (!btn) { return; }
        if (btn.dataset.act === 'edit') {
            openEditModal(btn);
        } else {
            deleteMessage(btn.dataset.id);
        }
    });

    loadMessages();
})();
</script>
<?php
admin_page_foot();
