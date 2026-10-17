<?php
// 把旧留言板的 messages 一次性导入成论坛帖子。
// 幂等：导过的行会在 messages.migrated_post_id 打标记，重复点击不会产生第二份。
define('BOYA_ADMIN_API', true);
require_once __DIR__ . '/../admin-files/_auth.php';   // 管理员会话 + CSRF 校验函数
require_once __DIR__ . '/../inc/audit.php';
require_once __DIR__ . '/../inc/forum.php';

$conn = forum_conn();

// 保证 messages 表有 migrated_post_id 列（结构同步只跑一次，见 inc/db.php）
boya_schema_once('legacy-messages-migrated-column', '2026-10-17-1', function () use ($conn) {
    $res = $conn->query("SHOW COLUMNS FROM `messages` LIKE 'migrated_post_id'");
    if ($res && (int)$res->num_rows === 0) {
        if (!$conn->query("ALTER TABLE `messages` ADD COLUMN `migrated_post_id` int(11) DEFAULT NULL")) {
            error_log('migrate: add migrated_post_id failed: ' . $conn->error);
            return false;
        }
    }
    return true;
});

$pending = 0;
$res = $conn->query('SELECT COUNT(*) AS c FROM messages WHERE migrated_post_id IS NULL');
if ($res) {
    $pending = (int)($res->fetch_assoc()['c'] ?? 0);
}

// GET：只返回待导入数量（预览）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    forum_json(['ok' => true, 'pending' => $pending]);
}

if (!admin_verify_csrf($_POST['csrf_token'] ?? '')) {
    forum_json(['message' => 'CSRF 校验失败，请刷新页面重试'], 403);
}

$insert = $conn->prepare('INSERT INTO forum_posts
    (author_id, author_name, title, content, created_at, updated_at, last_reply_at)
    VALUES (0, ?, ?, ?, ?, ?, NULL)');
if (!$insert) {
    error_log('migrate insert prepare failed: ' . $conn->error);
    forum_json(['message' => '数据库准备失败，请稍后重试'], 500);
}

$mark = $conn->prepare('UPDATE messages SET migrated_post_id = ? WHERE id = ?');
if (!$mark) {
    error_log('migrate mark prepare failed: ' . $conn->error);
    forum_json(['message' => '数据库准备失败，请稍后重试'], 500);
}

$result = $conn->query('SELECT id, title, author, content, created_at FROM messages WHERE migrated_post_id IS NULL ORDER BY id ASC');
$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

$imported = 0;
$failed = 0;
foreach ($rows as $row) {
    $content = forum_clean_text($row['content'] ?? '', 5000);
    if ($content === '') {
        $content = '（这条旧留言没有正文）';
    }

    $title = forum_clean_text($row['title'] ?? '', 60);
    if ($title === '') {
        $title = mb_substr(preg_replace('/\s+/u', ' ', $content), 0, 20);
        if ($title === '') {
            $title = '历史留言';
        }
    }

    $authorName = forum_clean_text($row['author'] ?? '', 30);
    if ($authorName === '') {
        $authorName = '匿名';
    }

    $createdAt = (string)($row['created_at'] ?? '');
    if ($createdAt === '' || strtotime($createdAt) === false) {
        $createdAt = forum_now();
    }

    $insert->bind_param('sssss', $authorName, $title, $content, $createdAt, $createdAt);
    if (!$insert->execute()) {
        error_log('migrate insert failed: ' . $insert->error);
        $failed++;
        continue;
    }

    $postId = (int)$conn->insert_id;
    $messageId = (int)$row['id'];
    $mark->bind_param('ii', $postId, $messageId);
    if (!$mark->execute()) {
        error_log('migrate mark failed: ' . $mark->error);
    }
    $imported++;
}

$insert->close();
$mark->close();

boya_audit('forum-migrate-legacy', 'imported=' . $imported . ' failed=' . $failed);
forum_json([
    'ok' => true,
    'imported' => $imported,
    'failed' => $failed,
    'pending' => max(0, $pending - $imported),
    'message' => '导入完成：成功 ' . $imported . ' 条，失败 ' . $failed . ' 条',
]);
