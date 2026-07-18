<?php
require_once '../config.php';  // 引入配置

// ================== 数据库配置 ==================
$db_host = $config['messages_db']['host'];
$db_user = $config['messages_db']['username'];
$db_pass = $config['messages_db']['password'];
$db_name = $config['messages_db']['database'];

// 管理员凭证
$admin_username = $config['admin_db']['username'];
$admin_password = $config['admin_db']['password'];

error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTTP Basic 认证（与 admin-messages.php 完全一致）
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $admin_username || $_SERVER['PHP_AUTH_PW'] !== $admin_password) {
    header('WWW-Authenticate: Basic realm="时光轴管理后台"');
    header('HTTP/1.0 401 Unauthorized');
    echo '需要管理员权限 - 请刷新页面并输入用户名和密码';
    exit;
}

// 连接数据库
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$db_error = null;
if ($conn->connect_error) {
    $db_error = "数据库连接失败：" . $conn->connect_error;
} else {
    $conn->set_charset("utf8mb4");
    // 自动创建表（如果不存在）
    $conn->query("CREATE TABLE IF NOT EXISTS `timeline_events` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `event_date` varchar(50) NOT NULL,
        `title` varchar(100) NOT NULL,
        `description` text,
        `photo` varchar(255) DEFAULT NULL,
        `display_order` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");
}

// ========== API 路由 ==========
if (!$db_error && isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];

    // ---------- 列表 ----------
    if ($action === 'list') {
        $result = $conn->query("SELECT * FROM timeline_events ORDER BY display_order ASC, id ASC");
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $events]);
        exit;
    }

    // ---------- 添加 ----------
    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $date = trim($_POST['event_date'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $photo = trim($_POST['photo'] ?? '');
        $order = intval($_POST['display_order'] ?? 0);
        if (empty($date) || empty($title)) {
            echo json_encode(['success' => false, 'error' => '日期和标题不能为空']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO timeline_events (event_date, title, description, photo, display_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $date, $title, $desc, $photo, $order);
        $success = $stmt->execute();
        echo json_encode(['success' => $success, 'error' => $success ? null : $stmt->error]);
        $stmt->close();
        exit;
    }

    // ---------- 编辑 ----------
    if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = intval($_POST['id'] ?? 0);
        $date = trim($_POST['event_date'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $photo = trim($_POST['photo'] ?? '');
        $order = intval($_POST['display_order'] ?? 0);
        if ($id <= 0 || empty($date) || empty($title)) {
            echo json_encode(['success' => false, 'error' => '参数不完整']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE timeline_events SET event_date=?, title=?, description=?, photo=?, display_order=? WHERE id=?");
        $stmt->bind_param("ssssii", $date, $title, $desc, $photo, $order, $id);
        $success = $stmt->execute();
        echo json_encode(['success' => $success, 'error' => $success ? null : $stmt->error]);
        $stmt->close();
        exit;
    }

    // ---------- 删除 ----------
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => '无效ID']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM timeline_events WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        echo json_encode(['success' => $success]);
        $stmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'error' => '未知API']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>博雅班时光轴管理</title>
    <meta name="description" content="博雅班时光轴管理，用于管理班级的时光回忆事件。">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; padding: 24px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; margin-bottom: 20px; border-radius: 12px; color: #856404; }
        .alert-error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .admin-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 32px; padding-bottom: 16px; border-bottom: 1px solid #eaeaea; }
        h1 { font-size: 1.6rem; font-weight: 500; }
        .stats { font-size: 0.85rem; color: #666; background: #f0f0f0; padding: 4px 12px; border-radius: 30px; }
        .btn { border: none; background: #111; color: white; padding: 6px 14px; border-radius: 40px; cursor: pointer; font-size: 0.8rem; }
        .btn-add { background: #27ae60; }
        .btn-edit { background: #f39c12; }
        .btn-delete { background: #e74c3c; }
        .btn-cancel { background: #999; }
        .event-list { display: flex; flex-direction: column; gap: 12px; }
        .event-item { background: white; border-radius: 16px; padding: 16px 18px; border: 1px solid #ececec; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .event-info { flex: 1; min-width: 200px; }
        .event-date { font-weight: 500; font-size: 0.9rem; }
        .event-title { font-weight: 600; }
        .event-desc { font-size: 0.8rem; color: #555; margin-top: 4px; }
        .event-photo-preview { max-height: 50px; max-width: 70px; object-fit: cover; margin-right: 10px; }
        .event-actions { display: flex; gap: 6px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); align-items: center; justify-content: center; z-index: 1000; }
        .modal-card { background: white; width: 90%; max-width: 500px; border-radius: 28px; padding: 28px; }
        .modal-card label { display: block; margin-top: 12px; font-size: 0.8rem; font-weight: 500; }
        .modal-card input, .modal-card textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 8px; margin-top: 4px; }
        .modal-card textarea { height: 80px; }
        .modal-buttons { margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end; }
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #111; color: white; font-size: 0.8rem; padding: 8px 20px; border-radius: 40px; opacity: 0; transition: 0.15s; pointer-events: none; z-index: 1100; }
    </style>
</head>
<body>
<div class="container">
    <?php if ($db_error): ?>
        <div class="alert alert-error">
            <strong>❌ 数据库连接错误</strong><br>
            <?= htmlspecialchars($db_error) ?><br><br>
            <strong>解决方法：</strong>检查 config.php 中的数据库配置。
        </div>
    <?php endif; ?>
    <div class="admin-header">
        <div style="display: flex; gap: 12px; align-items: baseline;">
            <h1>📅 时光轴管理</h1>
            <span class="stats" id="totalCount">-</span>
        </div>
        <div>
            <button class="btn btn-add" onclick="openAddForm()">➕ 添加事件</button>
        </div>
    </div>
    <div class="event-list" id="eventList">
        <?php if ($db_error): ?>
            <div class="alert">数据库未连接，无法加载</div>
        <?php else: ?>
            <div class="alert">加载中...</div>
        <?php endif; ?>
    </div>
</div>

<!-- 编辑/添加模态框 -->
<div id="editModal" class="modal">
    <div class="modal-card">
        <h3 id="modalTitle">添加事件</h3>
        <input type="hidden" id="editId">
        <label>日期</label><input type="text" id="editDate" placeholder="如 2025.10.17">
        <label>标题</label><input type="text" id="editTitle" placeholder="事件标题">
        <label>描述</label><textarea id="editDesc" placeholder="详细描述"></textarea>
        <label>图片路径</label><input type="text" id="editPhoto" placeholder="如 photos/shijian1.jpg">
        <label>排序数字</label><input type="number" id="editOrder" value="0" min="0">
        <div class="modal-buttons">
            <button class="btn btn-cancel" id="closeModalBtn">取消</button>
            <button class="btn" id="saveBtn">保存</button>
        </div>
    </div>
</div>
<div id="toastMsg" class="toast"></div>

<script>
    const API_URL = '?api=';  // 相对路径，调用自身

    // 加载列表
    async function loadEvents() {
        const container = document.getElementById('eventList');
        container.innerHTML = '<div class="alert">加载中...</div>';
        <?php if ($db_error): ?>
        container.innerHTML = '<div class="alert">数据库连接错误，无法加载</div>';
        return;
        <?php endif; ?>
        try {
            const res = await fetch(API_URL + 'list');
            if (res.status === 401) {
                container.innerHTML = '<div class="alert">认证失败，请刷新页面重新输入密码</div>';
                return;
            }
            const result = await res.json();
            if (result.success && result.data) {
                const events = result.data;
                document.getElementById('totalCount').innerText = `共 ${events.length} 条`;
                if (events.length === 0) {
                    container.innerHTML = '<div class="alert">暂无事件，点击“添加事件”创建第一条。</div>';
                    return;
                }
                let html = '';
                events.forEach(ev => {
                    const photoHtml = ev.photo ? `<img src="${escapeHtml(ev.photo)}" class="event-photo-preview" onerror="this.style.display='none'">` : '';
                    html += `
                        <div class="event-item">
                            <div class="event-info">
                                <div><span class="event-date">${escapeHtml(ev.event_date)}</span>  <span class="event-title">${escapeHtml(ev.title)}</span></div>
                                <div class="event-desc">${escapeHtml(ev.description)}</div>
                                ${photoHtml}
                                <small style="color:#999;">排序: ${ev.display_order}</small>
                            </div>
                            <div class="event-actions">
                                <button class="btn btn-edit" onclick="editEvent(${ev.id})">编辑</button>
                                <button class="btn btn-delete" onclick="deleteEvent(${ev.id})">删除</button>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert">加载失败，请刷新</div>';
            }
        } catch (err) {
            container.innerHTML = '<div class="alert">网络错误，请检查网络</div>';
        }
    }

    // 打开添加表单
    function openAddForm() {
        document.getElementById('modalTitle').innerText = '添加事件';
        document.getElementById('editId').value = '';
        document.getElementById('editDate').value = '';
        document.getElementById('editTitle').value = '';
        document.getElementById('editDesc').value = '';
        document.getElementById('editPhoto').value = '';
        document.getElementById('editOrder').value = '0';
        document.getElementById('editModal').style.display = 'flex';
    }

    // 编辑事件
    async function editEvent(id) {
        try {
            const res = await fetch(API_URL + 'list');
            const result = await res.json();
            if (!result.success) return;
            const ev = result.data.find(e => e.id === id);
            if (!ev) return alert('未找到该事件');
            document.getElementById('modalTitle').innerText = '编辑事件';
            document.getElementById('editId').value = ev.id;
            document.getElementById('editDate').value = ev.event_date || '';
            document.getElementById('editTitle').value = ev.title || '';
            document.getElementById('editDesc').value = ev.description || '';
            document.getElementById('editPhoto').value = ev.photo || '';
            document.getElementById('editOrder').value = ev.display_order || 0;
            document.getElementById('editModal').style.display = 'flex';
        } catch (err) {
            alert('加载详情失败');
        }
    }

    // 保存（添加或更新）
    async function saveEvent() {
        const id = document.getElementById('editId').value;
        const date = document.getElementById('editDate').value.trim();
        const title = document.getElementById('editTitle').value.trim();
        const desc = document.getElementById('editDesc').value.trim();
        const photo = document.getElementById('editPhoto').value.trim();
        const order = parseInt(document.getElementById('editOrder').value) || 0;

        if (!date || !title) {
            showToast('日期和标题不能为空');
            return;
        }

        const action = id ? 'edit' : 'add';
        const formData = new URLSearchParams();
        formData.append('event_date', date);
        formData.append('title', title);
        formData.append('description', desc);
        formData.append('photo', photo);
        formData.append('display_order', order);
        if (id) formData.append('id', id);

        try {
            const res = await fetch(API_URL + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                showToast('保存成功');
                closeModal();
                loadEvents();
            } else {
                showToast('保存失败: ' + (result.error || '未知错误'));
            }
        } catch (err) {
            showToast('网络错误');
        }
    }

    // 删除事件
    async function deleteEvent(id) {
        if (!confirm('确定删除这条事件吗？')) return;
        const formData = new URLSearchParams();
        formData.append('id', id);
        try {
            const res = await fetch(API_URL + 'delete', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                showToast('已删除');
                loadEvents();
            } else {
                showToast('删除失败');
            }
        } catch (err) {
            showToast('请求失败');
        }
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function showToast(msg) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = msg;
        toast.style.opacity = '1';
        setTimeout(() => toast.style.opacity = '0', 2000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));
    }

    // 绑定事件
    document.getElementById('saveBtn').addEventListener('click', saveEvent);
    document.getElementById('closeModalBtn').addEventListener('click', closeModal);
    window.addEventListener('click', (e) => {
        if (e.target === document.getElementById('editModal')) closeModal();
    });

    // 初始化加载
    loadEvents();
</script>
</body>
</html>