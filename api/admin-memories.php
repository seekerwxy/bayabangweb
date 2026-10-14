<?php
// 时光轴管理接口（仅管理员可用）。对应页面：admin-files/admin-memories.php
// 这样 api/ 只放接口脚本，后台页面只留 HTML/JS。
define('BOYA_ADMIN_API', true);
require_once __DIR__ . '/../admin-files/_auth.php';   // 内含 config.php、session、登录校验、CSRF 校验函数
require_once __DIR__ . '/../_photo.php';
require_once __DIR__ . '/../_audit.php';

header('Content-Type: application/json; charset=utf-8');

function timeline_response($payload, $status = 200)
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
    error_log('admin memories DB connect failed: ' . $conn->connect_error);
    timeline_response(['success' => false, 'error' => '数据库连接失败，请稍后重试'], 500);
}
$conn->set_charset('utf8mb4');

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

$action = $_GET['action'] ?? $_GET['api'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !admin_verify_csrf($_POST['csrf_token'] ?? '')) {
    timeline_response(['success' => false, 'error' => 'CSRF验证失败，请刷新页面重试'], 403);
}

// ---------- 列表 ----------
if ($action === 'list') {
    $result = $conn->query("SELECT * FROM timeline_events ORDER BY display_order ASC, id ASC");
    if (!$result) {
        error_log('admin timeline list failed: ' . $conn->error);
        timeline_response(['success' => false, 'error' => '查询失败'], 500);
    }
    $events = [];
    while ($row = $result->fetch_assoc()) {
        // 后台编辑框里也显示归一化后的路径，避免同一条数据出现多种写法
        $row['photo'] = boya_normalize_photo($row['photo'] ?? '');
        $events[] = $row;
    }
    timeline_response(['success' => true, 'data' => $events]);
}

// ---------- 添加 ----------
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['event_date'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $photo = boya_normalize_photo($_POST['photo'] ?? '');
    $order = intval($_POST['display_order'] ?? 0);
    if (empty($date) || empty($title)) {
        timeline_response(['success' => false, 'error' => '日期和标题不能为空'], 400);
    }
    // 列宽限制：event_date varchar(50)、title varchar(100)、photo varchar(255)
    if (mb_strlen($date) > 50 || mb_strlen($title) > 100 || mb_strlen($photo) > 255) {
        timeline_response(['success' => false, 'error' => '日期/标题/图片路径过长（日期≤50、标题≤100、路径≤255）'], 400);
    }
    // 防呆：图片路径必须在服务器上真实存在，否则前台只会是一张裂图
    if ($photo !== '' && !boya_photo_exists($photo)) {
        $suggestions = boya_photo_suggestions();
        timeline_response([
            'success' => false,
            'error' => '图片路径在服务器上找不到：' . $photo
                . ($suggestions ? '（候选路径已写进输入框的提示里）' : ''),
            'photo_suggestions' => $suggestions
        ], 400);
    }
    $stmt = $conn->prepare("INSERT INTO timeline_events (event_date, title, description, photo, display_order) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log('admin timeline add prepare failed: ' . $conn->error);
        timeline_response(['success' => false, 'error' => '数据库准备失败'], 500);
    }
    $stmt->bind_param("ssssi", $date, $title, $desc, $photo, $order);
    $success = $stmt->execute();
    $newId = (int)$conn->insert_id;
    if (!$success) {
        error_log('admin timeline add failed: ' . $stmt->error);
    }
    $stmt->close();
    if ($success) {
        boya_audit('timeline-add', 'id=' . $newId . ' title=' . $title . ' date=' . $date);
    }
    timeline_response(['success' => (bool)$success, 'error' => $success ? null : '保存失败']);
}

// ---------- 编辑 ----------
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $date = trim($_POST['event_date'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $photo = boya_normalize_photo($_POST['photo'] ?? '');
    $order = intval($_POST['display_order'] ?? 0);
    if ($id <= 0 || empty($date) || empty($title)) {
        timeline_response(['success' => false, 'error' => '参数不完整'], 400);
    }
    // 列宽限制：event_date varchar(50)、title varchar(100)、photo varchar(255)
    if (mb_strlen($date) > 50 || mb_strlen($title) > 100 || mb_strlen($photo) > 255) {
        timeline_response(['success' => false, 'error' => '日期/标题/图片路径过长（日期≤50、标题≤100、路径≤255）'], 400);
    }
    // 防呆：图片路径必须在服务器上真实存在，否则前台只会是一张裂图
    if ($photo !== '' && !boya_photo_exists($photo)) {
        $suggestions = boya_photo_suggestions();
        timeline_response([
            'success' => false,
            'error' => '图片路径在服务器上找不到：' . $photo
                . ($suggestions ? '（候选路径已写进输入框的提示里）' : ''),
            'photo_suggestions' => $suggestions
        ], 400);
    }
    $stmt = $conn->prepare("UPDATE timeline_events SET event_date=?, title=?, description=?, photo=?, display_order=? WHERE id=?");
    if (!$stmt) {
        error_log('admin timeline edit prepare failed: ' . $conn->error);
        timeline_response(['success' => false, 'error' => '数据库准备失败'], 500);
    }
    $stmt->bind_param("ssssii", $date, $title, $desc, $photo, $order, $id);
    $success = $stmt->execute();
    if (!$success) {
        error_log('admin timeline edit failed: ' . $stmt->error);
    }
    $stmt->close();
    if ($success) {
        boya_audit('timeline-edit', 'id=' . $id . ' title=' . $title . ' date=' . $date);
    }
    timeline_response(['success' => (bool)$success, 'error' => $success ? null : '保存失败']);
}

// ---------- 删除 ----------
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        timeline_response(['success' => false, 'error' => '无效ID'], 400);
    }
    $stmt = $conn->prepare("DELETE FROM timeline_events WHERE id = ?");
    if (!$stmt) {
        error_log('admin timeline delete prepare failed: ' . $conn->error);
        timeline_response(['success' => false, 'error' => '数据库准备失败'], 500);
    }
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    if (!$success) {
        error_log('admin timeline delete failed: ' . $stmt->error);
    }
    $stmt->close();
    if ($success) {
        boya_audit('timeline-delete', 'id=' . $id);
    }
    timeline_response(['success' => (bool)$success]);
}

timeline_response(['success' => false, 'error' => '未知操作'], 400);
