<?php

require_once 'config.php';

session_start();

// ================== 数据库配置 ==================
$db_host = $config['messages_db']['host'];     // 数据库主机
$db_user = $config['messages_db']['username'];    // 数据库用户名
$db_pass = $config['messages_db']['password'];   // 数据库密码
$db_name = $config['messages_db']['database'];      // 数据库名

// 管理员登录凭证（请修改为强密码）
$admin_username = 'admin';
$admin_password = 'adminwxy';

// 开启错误显示（便于诊断，部署后可关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTTP Basic 认证（无 Cookie）
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $admin_username || $_SERVER['PHP_AUTH_PW'] !== $admin_password) {
    header('WWW-Authenticate: Basic realm="留言管理后台"');
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
    // 自动创建/修复表（增加 color 字段）
    $conn->query("CREATE TABLE IF NOT EXISTS `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) DEFAULT '',
        `author` varchar(30) NOT NULL,
        `content` text NOT NULL,
        `color` varchar(7) DEFAULT '',
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");
    // 如果表存在但没有 color 字段，则添加
    $res = $conn->query("SHOW COLUMNS FROM `messages` LIKE 'color'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE `messages` ADD COLUMN `color` varchar(7) DEFAULT ''");
    }
}

// ========== API 路由 ==========
if (!$db_error && isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];

    if ($action === 'list') {
        $result = $conn->query("SELECT id, title, author, content, color, created_at FROM messages ORDER BY id DESC");
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $row['content_preview'] = mb_substr(strip_tags($row['content']), 0, 100);
            $messages[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $messages]);
        exit;
    }

    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $color = trim($_POST['color'] ?? '');
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '';
        if ($id <= 0 || empty($author) || empty($content)) {
            echo json_encode(['success' => false, 'error' => '参数不完整']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE messages SET title = ?, author = ?, content = ?, color = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $title, $author, $content, $color, $id);
        $success = $stmt->execute();
        echo json_encode(['success' => $success, 'error' => $success ? null : $stmt->error]);
        $stmt->close();
        exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => '无效ID']); exit; }
        $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
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
    <title>留言管理 · 零Cookie版</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #fafafa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; padding: 24px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px 20px; margin-bottom: 20px; border-radius: 12px; color: #856404; }
        .alert-error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .admin-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 32px; padding-bottom: 16px; border-bottom: 1px solid #eaeaea; }
        h1 { font-size: 1.6rem; font-weight: 500; }
        .stats { font-size: 0.85rem; color: #666; background: #f0f0f0; padding: 4px 12px; border-radius: 30px; }
        .info-badge { font-size: 0.7rem; background: #e9ecef; padding: 4px 10px; border-radius: 30px; }
        .messages-list { display: flex; flex-direction: column; gap: 16px; }
        .message-item { background: white; border-radius: 20px; padding: 18px 20px; border: 1px solid #ececec; }
        .message-header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-bottom: 10px; }
        .msg-title { font-weight: 500; }
        .msg-author { font-size: 0.8rem; color: #5e5e5e; background: #f5f5f5; padding: 2px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px; }
        .color-dot { display: inline-block; width: 12px; height: 12px; border-radius: 12px; }
        .msg-content-preview { font-size: 0.85rem; color: #3a3a3a; margin: 12px 0; padding-left: 4px; border-left: 2px solid #eaeaea; }
        .msg-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; flex-wrap: wrap; gap: 8px; }
        .msg-time { font-size: 0.7rem; color: #aaa; }
        .edit-btn, .delete-btn { border: none; background: none; cursor: pointer; font-size: 0.75rem; padding: 4px 12px; border-radius: 40px; }
        .edit-btn { background: #f0f0f0; color: #2c2c2c; }
        .delete-btn { color: #b91c1c; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); align-items: center; justify-content: center; z-index: 1000; }
        .modal-card { background: white; width: 90%; max-width: 540px; border-radius: 28px; padding: 28px; }
        .color-picker { display: flex; flex-wrap: wrap; gap: 10px; margin: 8px 0 18px; }
        .color-swatch { width: 32px; height: 32px; border-radius: 32px; cursor: pointer; border: 2px solid transparent; }
        .color-swatch.selected { border-color: #111; transform: scale(1.05); }
        .save-btn { background: #111; color: white; border: none; padding: 8px 20px; border-radius: 40px; cursor: pointer; }
        .cancel-btn { background: #eaeaea; border: none; padding: 8px 20px; border-radius: 40px; cursor: pointer; }
        .toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #111; color: white; font-size: 0.8rem; padding: 8px 20px; border-radius: 40px; opacity: 0; transition: 0.15s; pointer-events: none; z-index: 1100; }
    </style>
</head>
<body>
<div class="container">
    <?php if ($db_error): ?>
        <div class="alert alert-error">
            <strong>❌ 数据库连接错误</strong><br>
            <?= htmlspecialchars($db_error) ?><br><br>
            <strong>解决方法：</strong><br>
            1. 打开本文件（admin.php），检查开头的 <code>$db_host</code>、<code>$db_user</code>、<code>$db_pass</code>、<code>$db_name</code> 是否正确。<br>
            2. 在 InfinityFree 控制面板中确认数据库的详细信息（主机名通常不是 localhost）。<br>
            3. 如果密码包含特殊字符，尝试用原文（不要转义）。<br>
            4. 保存文件后重新刷新本页面。
        </div>
    <?php endif; ?>
    <div class="admin-header">
        <div style="display: flex; gap: 12px; align-items: baseline;">
            <h1>📝 留言管理</h1>
            <span class="stats" id="totalCount">-</span>
            <span class="info-badge">🔓 无Cookie·HTTP认证</span>
        </div>
        <div style="font-size: 0.7rem; color:#888;">认证有效期：浏览器标签页关闭前</div>
    </div>
    <div class="messages-list" id="messagesContainer">
        <?php if ($db_error): ?>
            <div class="alert">数据库未连接，无法加载留言</div>
        <?php else: ?>
            <div class="alert">加载中...</div>
        <?php endif; ?>
    </div>
</div>

<!-- 编辑模态框 -->
<div id="editModal" class="modal">
    <div class="modal-card">
        <h3>编辑留言</h3>
        <input type="hidden" id="editId">
        <label>标题</label><input type="text" id="editTitle" placeholder="标题（可选）">
        <label>署名</label><input type="text" id="editAuthor" placeholder="署名" required>
        <label>内容</label><textarea id="editContent" placeholder="留言内容" required></textarea>
        <label>文字颜色</label><div class="color-picker" id="colorPicker"></div>
        <div class="modal-buttons"><button class="cancel-btn" id="closeModalBtn">取消</button><button class="save-btn" id="saveEditBtn">保存修改</button></div>
    </div>
</div>
<div id="toastMsg" class="toast"></div>

<script>
    const colorList = ["#000000","#e60000","#ff9900","#008000","#0000ff","#800080","#ff1493","#00ced1","#32cd32","#ffd700","#ff4500","#8a2be2"];
    let currentColor = "";

    function initColorPicker(selectedColor = "") {
        const container = document.getElementById('colorPicker');
        if (!container) return;
        container.innerHTML = '';
        colorList.forEach(color => {
            const swatch = document.createElement('div');
            swatch.className = 'color-swatch';
            swatch.style.backgroundColor = color;
            swatch.dataset.color = color;
            if (color === selectedColor) swatch.classList.add('selected');
            swatch.addEventListener('click', () => {
                document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
                swatch.classList.add('selected');
                currentColor = swatch.dataset.color;
            });
            container.appendChild(swatch);
        });
        const clearSwatch = document.createElement('div');
        clearSwatch.className = 'color-swatch';
        clearSwatch.style.background = "linear-gradient(135deg, #ccc 0%, #ccc 100%)";
        clearSwatch.style.border = "1px solid #aaa";
        clearSwatch.innerHTML = "✕";
        clearSwatch.style.display = "flex";
        clearSwatch.style.alignItems = "center";
        clearSwatch.style.justifyContent = "center";
        clearSwatch.addEventListener('click', () => {
            document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
            currentColor = "";
            clearSwatch.classList.add('selected');
        });
        container.appendChild(clearSwatch);
        if (!selectedColor) clearSwatch.classList.add('selected');
        else currentColor = selectedColor;
    }

    async function loadMessages() {
        const container = document.getElementById('messagesContainer');
        container.innerHTML = '<div class="alert">加载留言中...</div>';
        <?php if ($db_error): ?>
        container.innerHTML = '<div class="alert">数据库连接错误，无法加载留言</div>';
        return;
        <?php endif; ?>
        try {
            const res = await fetch('?api=list');
            if (res.status === 401) {
                container.innerHTML = '<div class="alert">认证失败，请刷新页面重新输入密码</div>';
                return;
            }
            const result = await res.json();
            if (result.success && result.data) {
                const msgs = result.data;
                document.getElementById('totalCount').innerText = `共 ${msgs.length} 条`;
                if (msgs.length === 0) {
                    container.innerHTML = '<div class="alert">暂无留言 ✨</div>';
                    return;
                }
                let html = '';
                for (let msg of msgs) {
                    const time = new Date(msg.created_at).toLocaleString('zh-CN', {hour12: false});
                    const titleDisplay = escapeHtml(msg.title) || '<span style="color:#aaa;">无标题</span>';
                    const preview = escapeHtml(msg.content_preview || msg.content.substring(0, 100));
                    const colorDot = msg.color ? `<span class="color-dot" style="background:${msg.color}"></span>` : '';
                    html += `<div class="message-item">
                        <div class="message-header"><div class="msg-title">${titleDisplay}</div><div class="msg-author">${colorDot}${escapeHtml(msg.author)}</div></div>
                        <div class="msg-content-preview">${preview}${msg.content.length > 100 ? '…' : ''}</div>
                        <div class="msg-meta"><span class="msg-time">${time}</span>
                        <div class="actions"><button class="edit-btn" data-id="${msg.id}" data-title="${escapeHtml(msg.title)}" data-author="${escapeHtml(msg.author)}" data-content="${escapeHtml(msg.content)}" data-color="${msg.color || ''}">编辑</button>
                        <button class="delete-btn" data-id="${msg.id}">删除</button></div></div></div>`;
                }
                container.innerHTML = html;
                document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => openEditModal(btn.dataset.id, btn.dataset.title, btn.dataset.author, btn.dataset.content, btn.dataset.color)));
                document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', async () => { if (confirm('确定删除这条留言？')) await deleteMessage(btn.dataset.id); }));
            } else {
                container.innerHTML = '<div class="alert">加载失败，请刷新页面重试</div>';
            }
        } catch (err) {
            container.innerHTML = '<div class="alert">网络错误，请检查网络或刷新页面</div>';
        }
    }

    function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); }
    function openEditModal(id, title, author, content, color) {
        document.getElementById('editId').value = id;
        document.getElementById('editTitle').value = title === 'null' ? '' : title;
        document.getElementById('editAuthor').value = author;
        document.getElementById('editContent').value = content;
        initColorPicker(color);
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeModal() { document.getElementById('editModal').style.display = 'none'; }
    async function saveEdit() {
        const id = document.getElementById('editId').value;
        const title = document.getElementById('editTitle').value.trim();
        const author = document.getElementById('editAuthor').value.trim();
        const content = document.getElementById('editContent').value.trim();
        if (!author || !content) { showToast('署名和内容不能为空'); return; }
        const formData = new URLSearchParams();
        formData.append('id', id); formData.append('title', title); formData.append('author', author);
        formData.append('content', content); formData.append('color', currentColor);
        try {
            const res = await fetch('?api=update', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData });
            const result = await res.json();
            if (result.success) { showToast('✅ 更新成功'); closeModal(); loadMessages(); }
            else showToast('更新失败: ' + (result.error || '未知错误'));
        } catch (err) { showToast('网络错误'); }
    }
    async function deleteMessage(id) {
        const formData = new URLSearchParams(); formData.append('id', id);
        try {
            const res = await fetch('?api=delete', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) { showToast('🗑️ 已删除'); loadMessages(); }
            else showToast('删除失败');
        } catch (err) { showToast('请求失败'); }
    }
    function showToast(msg) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = msg; toast.style.opacity = '1';
        setTimeout(() => toast.style.opacity = '0', 2000);
    }
    document.getElementById('closeModalBtn').addEventListener('click', closeModal);
    document.getElementById('saveEditBtn').addEventListener('click', saveEdit);
    window.addEventListener('click', (e) => { if (e.target === document.getElementById('editModal')) closeModal(); });
    loadMessages();
</script>
</body>
</html>