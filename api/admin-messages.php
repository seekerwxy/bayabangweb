<?php
// 留言管理接口（仅管理员可用）。对应页面：admin-files/admin-messages.php
// 这样 api/ 只放接口脚本，后台页面只留 HTML/JS。
define('BOYA_ADMIN_API', true);
require_once __DIR__ . '/../admin-files/_auth.php';   // 内含 config.php、session、登录校验、CSRF 校验函数
require_once __DIR__ . '/../_audit.php';

header('Content-Type: application/json; charset=utf-8');

function admin_messages_response($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = new mysqli(
    $config['messages_db']['host'],
    $config['messages_db']['username'],
    $config['messages_db']['password'],
    $config['messages_db']['database']
);
if ($conn->connect_error) {
    error_log('admin messages DB connect failed: ' . $conn->connect_error);
    admin_messages_response(['success' => false, 'error' => '数据库连接失败，请稍后重试'], 500);
}
$conn->set_charset('utf8mb4');

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
$res = $conn->query("SHOW COLUMNS FROM `messages` LIKE 'color'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE `messages` ADD COLUMN `color` varchar(7) DEFAULT ''");
}

$action = $_GET['action'] ?? $_GET['api'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !admin_verify_csrf($_POST['csrf_token'] ?? '')) {
    admin_messages_response(['success' => false, 'error' => 'CSRF验证失败，请刷新页面重试'], 403);
}

// ---------- 列表 ----------
if ($action === 'list') {
    $result = $conn->query("SELECT id, title, author, content, color, created_at FROM messages ORDER BY id DESC");
    if (!$result) {
        error_log('admin message list failed: ' . $conn->error);
        admin_messages_response(['success' => false, 'error' => '查询失败'], 500);
    }
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $row['content_preview'] = mb_substr(strip_tags((string)$row['content']), 0, 100);
        $messages[] = $row;
    }
    admin_messages_response(['success' => true, 'data' => $messages]);
}

// ---------- 编辑 ----------
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $color = trim($_POST['color'] ?? '');
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '';
    }
    if ($id <= 0 || $author === '' || $content === '') {
        admin_messages_response(['success' => false, 'error' => '参数不完整'], 400);
    }
    // 列宽限制：author varchar(30)、title varchar(255)，正文与留言板前台保持一致上限 2000 字
    if (mb_strlen($author) > 30) {
        admin_messages_response(['success' => false, 'error' => '署名不能超过30个字符'], 400);
    }
    if (mb_strlen($title) > 255) {
        admin_messages_response(['success' => false, 'error' => '标题不能超过255个字符'], 400);
    }
    if (mb_strlen($content) > 2000) {
        admin_messages_response(['success' => false, 'error' => '内容不能超过2000个字符'], 400);
    }
    $stmt = $conn->prepare("UPDATE messages SET title = ?, author = ?, content = ?, color = ? WHERE id = ?");
    if (!$stmt) {
        error_log('admin message update prepare failed: ' . $conn->error);
        admin_messages_response(['success' => false, 'error' => '数据库准备失败'], 500);
    }
    $stmt->bind_param("ssssi", $title, $author, $content, $color, $id);
    $success = $stmt->execute();
    if (!$success) {
        error_log('admin message update failed: ' . $stmt->error);
    }
    $stmt->close();
    if ($success) {
        boya_audit('message-edit', 'id=' . $id . ' author=' . $author);
    }
    admin_messages_response(['success' => (bool)$success, 'error' => $success ? null : '更新失败']);
}

// ---------- 删除 ----------
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        admin_messages_response(['success' => false, 'error' => '无效ID'], 400);
    }
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    if (!$stmt) {
        error_log('admin message delete prepare failed: ' . $conn->error);
        admin_messages_response(['success' => false, 'error' => '数据库准备失败'], 500);
    }
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    if (!$success) {
        error_log('admin message delete failed: ' . $stmt->error);
    }
    $stmt->close();
    if ($success) {
        boya_audit('message-delete', 'id=' . $id);
    }
    admin_messages_response(['success' => (bool)$success]);
}

admin_messages_response(['success' => false, 'error' => '未知操作'], 400);
