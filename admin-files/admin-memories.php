<?php
// 时光轴（班级回忆事件）管理：列表 + 添加 / 编辑 / 删除。接口在 api/admin-memories.php。
require_once __DIR__ . '/_layout.php';

admin_page_head('时光轴管理', [
    'width' => 1000,
    'title' => '管理时光轴',
    'stats' => '<span class="stats" id="totalCount">-</span>',
    'actions' => '<button class="btn btn-add" id="addBtn" type="button">➕ 添加事件</button>',
]);
?>
<div class="event-list" id="eventList">
    <div class="alert">加载中...</div>
</div>

<!-- 编辑/添加模态框 -->
<div id="editModal" class="modal">
    <div class="modal-card">
        <h3 id="modalTitle">添加事件</h3>
        <input type="hidden" id="editId">
        <label for="editDate">日期</label><input type="text" id="editDate" placeholder="如 2025.10.17">
        <label for="editTitle">标题</label><input type="text" id="editTitle" placeholder="事件标题">
        <label for="editDesc">描述</label><textarea id="editDesc" placeholder="详细描述"></textarea>
        <label for="editPhoto">图片路径</label><input type="text" id="editPhoto" placeholder="如 photos/shijian1.jpg（站点根相对路径）">
        <label for="editOrder">排序数字</label><input type="number" id="editOrder" value="0" min="0">
        <div class="modal-buttons">
            <button class="btn btn-cancel" id="closeModalBtn" type="button">取消</button>
            <button class="btn" id="saveBtn" type="button">保存</button>
        </div>
    </div>
</div>
<div id="toastMsg" class="toast"></div>

<script src="<?= boya_asset('js/admin-common.js') ?>"></script>
<script>
(function () {
    'use strict';
    var Admin = window.BoyaAdmin;
    var API = Admin.url('api/admin-memories.php');

    /** 数据库里存的是站点根相对路径（photos/x.jpg）或站点根绝对路径（/photos/x.jpg）。 */
    function photoUrl(path) {
        var value = String(path || '').trim();
        if (value === '') { return ''; }
        if (/^https?:\/\//i.test(value) || value.charAt(0) === '/') { return value; }
        return Admin.root() + value.replace(/^\.\//, '');
    }

    function loadEvents() {
        var container = Admin.$('eventList');
        container.innerHTML = '<div class="alert">加载中...</div>';
        Admin.fetchJson(API + '?action=list').then(function (res) {
            var result = res.data;
            if (!result.success || !result.data) {
                container.innerHTML = '<div class="alert">' + Admin.esc(result.error || '加载失败，请刷新') + '</div>';
                return;
            }
            var events = result.data;
            Admin.$('totalCount').textContent = '共 ' + events.length + ' 条';
            if (!events.length) {
                container.innerHTML = '<div class="alert">暂无事件，点击“添加事件”创建第一条。</div>';
                return;
            }
            container.innerHTML = events.map(function (ev) {
                var url = photoUrl(ev.photo);
                var photoHtml = url
                    ? '<img src="' + Admin.esc(url) + '" class="event-photo-preview" alt="" onerror="this.style.display=\'none\'">'
                    : '';
                return '<div class="event-item">'
                    + '<div class="event-info">'
                    + '<div><span class="event-date">' + Admin.esc(ev.event_date) + '</span>  <span class="event-title">' + Admin.esc(ev.title) + '</span></div>'
                    + '<div class="event-desc">' + Admin.esc(ev.description) + '</div>'
                    + photoHtml
                    + '<small style="color:#999;">排序: ' + Admin.esc(ev.display_order) + '</small>'
                    + '</div>'
                    + '<div class="event-actions">'
                    + '<button class="btn btn-edit" data-act="edit" data-id="' + ev.id + '" type="button">编辑</button>'
                    + '<button class="btn btn-delete" data-act="delete" data-id="' + ev.id + '" type="button">删除</button>'
                    + '</div></div>';
            }).join('');
        });
    }

    function openAddForm() {
        Admin.$('modalTitle').textContent = '添加事件';
        Admin.$('editId').value = '';
        Admin.$('editDate').value = '';
        Admin.$('editTitle').value = '';
        Admin.$('editDesc').value = '';
        Admin.$('editPhoto').value = '';
        Admin.$('editPhoto').placeholder = '如 photos/shijian1.jpg（站点根相对路径）';
        Admin.$('editOrder').value = '0';
        Admin.openModal('editModal');
    }

    function editEvent(id) {
        Admin.fetchJson(API + '?action=list').then(function (res) {
            var result = res.data;
            if (!result.success || !result.data) { return; }
            var ev = result.data.filter(function (item) { return Number(item.id) === Number(id); })[0];
            if (!ev) { alert('未找到该事件'); return; }
            Admin.$('modalTitle').textContent = '编辑事件';
            Admin.$('editId').value = ev.id;
            Admin.$('editDate').value = ev.event_date || '';
            Admin.$('editTitle').value = ev.title || '';
            Admin.$('editDesc').value = ev.description || '';
            Admin.$('editPhoto').value = ev.photo || '';
            Admin.$('editOrder').value = ev.display_order || 0;
            Admin.openModal('editModal');
        });
    }

    function saveEvent() {
        var id = Admin.$('editId').value;
        var date = Admin.$('editDate').value.trim();
        var title = Admin.$('editTitle').value.trim();
        var desc = Admin.$('editDesc').value.trim();
        var photo = Admin.$('editPhoto').value.trim();
        var order = parseInt(Admin.$('editOrder').value, 10) || 0;

        if (!date || !title) {
            Admin.toast('日期和标题不能为空');
            return;
        }

        var payload = {
            event_date: date,
            title: title,
            description: desc,
            photo: photo,
            display_order: order
        };
        if (id) { payload.id = id; }

        Admin.postForm(API + '?action=' + (id ? 'edit' : 'add'), payload).then(function (res) {
            var result = res.data;
            if (result.success) {
                Admin.toast('保存成功');
                Admin.closeModal('editModal');
                loadEvents();
                return;
            }
            Admin.toast('保存失败: ' + (result.error || '未知错误'));
            if (result.photo_suggestions && result.photo_suggestions.length) {
                Admin.$('editPhoto').placeholder = '可用：' + result.photo_suggestions.join('、');
            }
        });
    }

    function deleteEvent(id) {
        if (!confirm('确定删除这条事件吗？')) { return; }
        Admin.postForm(API + '?action=delete', { id: id }).then(function (res) {
            if (res.data.success) {
                Admin.toast('已删除');
                loadEvents();
            } else {
                Admin.toast('删除失败');
            }
        });
    }

    Admin.$('addBtn').addEventListener('click', openAddForm);
    Admin.$('saveBtn').addEventListener('click', saveEvent);
    Admin.$('closeModalBtn').addEventListener('click', function () { Admin.closeModal('editModal'); });
    Admin.$('editModal').addEventListener('click', function (event) {
        if (event.target === Admin.$('editModal')) { Admin.closeModal('editModal'); }
    });
    Admin.$('eventList').addEventListener('click', function (event) {
        var btn = event.target.closest('button[data-act]');
        if (!btn) { return; }
        if (btn.dataset.act === 'edit') {
            editEvent(Number(btn.dataset.id));
        } else {
            deleteEvent(Number(btn.dataset.id));
        }
    });

    loadEvents();
})();
</script>
<?php
admin_page_foot();
