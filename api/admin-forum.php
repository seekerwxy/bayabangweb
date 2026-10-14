<?php
// 论坛管理接口：config 管理员（后台页面）或被标为 is_admin 的同学（论坛页面）都能调用。
// GET：列出帖子（后台用，可选 filter=all|visible|deleted）
// POST：pin / unpin / lock / unlock / delete / restore（JSON 或表单都支持）
require_once __DIR__ . '/../_member.php';
require_once __DIR__ . '/../_audit.php';
require_once __DIR__ . '/../_forum.php';

// ---------------------------------------------------------------- 列表（后台用）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!forum_can_moderate()) {
        forum_json(['message' => '没有管理权限'], 403);
    }

    $conn = forum_conn();
    $filter = (string)($_GET['filter'] ?? 'all');
    $where = $filter === 'visible' ? 'WHERE is_deleted = 0' : ($filter === 'deleted' ? 'WHERE is_deleted = 1' : '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = 20;
    $offset = ($page - 1) * $pageSize;

    $total = 0;
    $res = $conn->query('SELECT COUNT(*) AS c FROM forum_posts ' . $where);
    if ($res) {
        $total = (int)($res->fetch_assoc()['c'] ?? 0);
    }

    $stmt = $conn->prepare('SELECT id, author_id, author_name, title, created_at, last_reply_at,
                                   reply_count, view_count, is_pinned, is_locked, is_deleted
                            FROM forum_posts ' . $where . '
                            ORDER BY id DESC LIMIT ? OFFSET ?');
    if (!$stmt) {
        error_log('admin forum list prepare failed: ' . $conn->error);
        forum_json(['message' => '查询失败'], 500);
    }
    $stmt->bind_param('ii', $pageSize, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $posts = [];
    $authorIds = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['author_id'] = (int)$row['author_id'];
        $row['reply_count'] = (int)$row['reply_count'];
        $row['view_count'] = (int)$row['view_count'];
        $row['is_pinned'] = (int)$row['is_pinned'];
        $row['is_locked'] = (int)$row['is_locked'];
        $row['is_deleted'] = (int)$row['is_deleted'];
        $posts[] = $row;
        if ((int)$row['author_id'] > 0) {
            $authorIds[] = (int)$row['author_id'];
        }
    }
    $stmt->close();

    $profiles = forum_member_profiles($authorIds);
    foreach ($posts as &$post) {
        $profile = $profiles[(int)$post['author_id']] ?? null;
        $post['author_username'] = $profile && trim((string)$profile['username']) !== ''
            ? (string)$profile['username']
            : (string)$post['author_name'];
    }
    unset($post);

    forum_json([
        'ok' => true,
        'filter' => $filter,
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'totalPages' => max(1, (int)ceil($total / $pageSize)),
        'posts' => $posts,
    ]);
}

// ---------------------------------------------------------------- 管理动作
$raw = file_get_contents('php://input');
$input = json_decode((string)$raw, true);
if (!is_array($input) || !$input) {
    $input = $_POST;   // 后台页面用表单提交
}

if (!forum_can_moderate()) {
    forum_json(['message' => '没有管理权限'], 403);
}
if (!forum_csrf_ok($input['csrf_token'] ?? '')) {
    forum_json(['message' => 'CSRF 校验失败，请刷新页面重试'], 403);
}

$action = (string)($input['action'] ?? '');
$type = (string)($input['type'] ?? 'post') === 'reply' ? 'reply' : 'post';
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    forum_json(['message' => '缺少 id'], 400);
}
if (in_array($action, ['pin', 'unpin', 'lock', 'unlock'], true) && $type !== 'post') {
    forum_json(['message' => '只有帖子能置顶或锁定'], 400);
}

$table = $type === 'reply' ? 'forum_replies' : 'forum_posts';
$setMap = [
    'pin' => 'is_pinned = 1',
    'unpin' => 'is_pinned = 0',
    'lock' => 'is_locked = 1',
    'unlock' => 'is_locked = 0',
    'delete' => 'is_deleted = 1',
    'restore' => 'is_deleted = 0',
];
if (!isset($setMap[$action])) {
    forum_json(['message' => '未知操作'], 400);
}
$set = $setMap[$action];

$conn = forum_conn();

// 删/恢复回复时要同步帖子的回复数与最后回复时间
$replyPostId = 0;
if ($type === 'reply' && ($action === 'delete' || $action === 'restore')) {
    $row = $conn->query('SELECT post_id FROM forum_replies WHERE id = ' . $id);
    if ($row) {
        $replyPostId = (int)($row->fetch_assoc()['post_id'] ?? 0);
    }
}

if (!$conn->query('UPDATE ' . $table . ' SET ' . $set . ' WHERE id = ' . $id)) {
    error_log('admin forum ' . $action . ' failed: ' . $conn->error);
    forum_json(['message' => '操作失败，请稍后重试'], 500);
}

if ($replyPostId > 0) {
    $count = 0;
    $countRes = $conn->query('SELECT COUNT(*) AS c FROM forum_replies WHERE post_id = ' . $replyPostId . ' AND is_deleted = 0');
    if ($countRes) {
        $count = (int)($countRes->fetch_assoc()['c'] ?? 0);
    }
    $conn->query('UPDATE forum_posts SET reply_count = ' . $count . ' WHERE id = ' . $replyPostId);

    $last = null;
    $lastRes = $conn->query('SELECT MAX(created_at) AS m FROM forum_replies WHERE post_id = ' . $replyPostId . ' AND is_deleted = 0');
    if ($lastRes) {
        $last = $lastRes->fetch_assoc()['m'] ?? null;
    }
    $stmt = $conn->prepare('UPDATE forum_posts SET last_reply_at = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $last, $replyPostId);
        $stmt->execute();
        $stmt->close();
    }
}

boya_audit('forum-' . $action, 'type=' . $type . ' id=' . $id);
forum_json(['ok' => true, 'message' => '操作完成']);
