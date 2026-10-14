<?php
// 留言板写入接口：发帖、回复、删除自己 10 分钟内的内容。
// 必须登录 + CSRF + 已设置论坛用户名；频率与每日配额写在 data/（与 Cookie/IP 无关）。
require_once __DIR__ . '/../_member.php';
require_once __DIR__ . '/../_rate_limit.php';
require_once __DIR__ . '/../_audit.php';
require_once __DIR__ . '/../_forum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    forum_json(['message' => '仅支持 POST'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = (string)($input['action'] ?? '');

$memberId = member_id();
if ($memberId <= 0) {
    forum_json(['message' => '请先登录后再发言', 'needLogin' => true], 401);
}
if (!member_verify_csrf($input['csrf_token'] ?? '')) {
    forum_json(['message' => 'CSRF 校验失败，请刷新页面后重试'], 403);
}

// 论坛不显示真实姓名，所以必须先设置用户名
$profiles = forum_member_profiles([$memberId]);
$username = trim((string)($profiles[$memberId]['username'] ?? ''));
if ($username === '') {
    forum_json([
        'message' => '你还没有设置论坛用户名，先去「编辑我的信息」里设置一个再来发言',
        'needUsername' => true,
    ], 409);
}
$authorName = member_display_name($username, $memberId);

$conn = forum_conn();

// ------------------------------------------------------------- 发新帖
if ($action === 'create-post') {
    $title = forum_clean_text($input['title'] ?? '', 60);
    $content = forum_clean_text($input['content'] ?? '', 5000);

    if ($title === '') {
        forum_json(['message' => '标题不能为空'], 400);
    }
    if ($content === '') {
        forum_json(['message' => '正文不能为空'], 400);
    }
    if (forum_text_forbidden($title) || forum_text_forbidden($content)) {
        forum_json(['message' => '内容里包含不允许的代码片段（例如 <script>、onclick=），请去掉后重试'], 400);
    }

    $rate = boya_rate_hit('post|member:' . $memberId, 1, 60);
    if (!$rate['allowed']) {
        forum_json([
            'message' => '发帖太快了，请等 ' . (int)$rate['retry_after'] . ' 秒后再试',
            'retryAfter' => (int)$rate['retry_after'],
        ], 429);
    }
    $daily = boya_rate_hit('postday|member:' . $memberId, 20, 86400);
    if (!$daily['allowed']) {
        forum_json(['message' => '今天发帖数量已达上限（20 条），明天再来吧'], 429);
    }

    $now = forum_now();
    $stmt = $conn->prepare('INSERT INTO forum_posts
        (author_id, author_name, title, content, created_at, updated_at, last_reply_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL)');
    if (!$stmt) {
        error_log('forum post insert prepare failed: ' . $conn->error);
        forum_json(['message' => '写入失败，请稍后重试'], 500);
    }
    $stmt->bind_param('isssss', $memberId, $authorName, $title, $content, $now, $now);
    $success = $stmt->execute();
    $postId = (int)$conn->insert_id;
    if (!$success) {
        error_log('forum post insert failed: ' . $stmt->error);
    }
    $stmt->close();

    if (!$success) {
        forum_json(['message' => '写入失败，请稍后重试'], 500);
    }

    boya_audit('forum-post-add', 'post=' . $postId . ' title=' . $title);
    member_touch_active($memberId);
    forum_json(['ok' => true, 'id' => $postId, 'message' => '发布成功']);
}

// ------------------------------------------------------------- 回复
if ($action === 'create-reply') {
    $postId = (int)($input['post_id'] ?? 0);
    $content = forum_clean_text($input['content'] ?? '', 2000);

    if ($postId <= 0) {
        forum_json(['message' => '缺少帖子 id'], 400);
    }
    if ($content === '') {
        forum_json(['message' => '回复内容不能为空'], 400);
    }
    if (forum_text_forbidden($content)) {
        forum_json(['message' => '内容里包含不允许的代码片段（例如 <script>、onclick=），请去掉后重试'], 400);
    }

    $stmt = $conn->prepare('SELECT id, is_locked, is_deleted FROM forum_posts WHERE id = ? LIMIT 1');
    if (!$stmt) {
        error_log('forum reply post-check prepare failed: ' . $conn->error);
        forum_json(['message' => '写入失败，请稍后重试'], 500);
    }
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$post || (int)$post['is_deleted'] === 1) {
        forum_json(['message' => '帖子不存在或已被删除'], 404);
    }
    if ((int)$post['is_locked'] === 1) {
        forum_json(['message' => '这个帖子已被锁定，不能再回复'], 403);
    }

    $rate = boya_rate_hit('reply|member:' . $memberId, 1, 15);
    if (!$rate['allowed']) {
        forum_json([
            'message' => '回复太快了，请等 ' . (int)$rate['retry_after'] . ' 秒后再试',
            'retryAfter' => (int)$rate['retry_after'],
        ], 429);
    }
    $daily = boya_rate_hit('replyday|member:' . $memberId, 100, 86400);
    if (!$daily['allowed']) {
        forum_json(['message' => '今天回复数量已达上限（100 条），明天再来吧'], 429);
    }

    $now = forum_now();
    $stmt = $conn->prepare('INSERT INTO forum_replies (post_id, author_id, author_name, content, created_at)
                            VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        error_log('forum reply insert prepare failed: ' . $conn->error);
        forum_json(['message' => '写入失败，请稍后重试'], 500);
    }
    $stmt->bind_param('iisss', $postId, $memberId, $authorName, $content, $now);
    $success = $stmt->execute();
    $replyId = (int)$conn->insert_id;
    if (!$success) {
        error_log('forum reply insert failed: ' . $stmt->error);
    }
    $stmt->close();

    if (!$success) {
        forum_json(['message' => '写入失败，请稍后重试'], 500);
    }

    $stmt = $conn->prepare('UPDATE forum_posts SET reply_count = reply_count + 1, last_reply_at = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $now, $postId);
        $stmt->execute();
        $stmt->close();
    }

    boya_audit('forum-reply-add', 'post=' . $postId . ' reply=' . $replyId);
    member_touch_active($memberId);
    forum_json(['ok' => true, 'id' => $replyId, 'message' => '回复成功']);
}

// --------------------------------------------- 删除自己 10 分钟内的内容
if ($action === 'delete-own') {
    $type = (string)($input['type'] ?? 'post');
    $targetId = (int)($input['id'] ?? 0);
    $windowSeconds = 600;

    if ($targetId <= 0) {
        forum_json(['message' => '缺少 id'], 400);
    }

    if ($type === 'reply') {
        $stmt = $conn->prepare('SELECT id, post_id, author_id, created_at, is_deleted FROM forum_replies WHERE id = ? LIMIT 1');
        if (!$stmt) {
            forum_json(['message' => '操作失败，请稍后重试'], 500);
        }
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || (int)$row['is_deleted'] === 1) {
            forum_json(['message' => '回复不存在或已被删除'], 404);
        }
        if ((int)$row['author_id'] !== $memberId) {
            forum_json(['message' => '只能删除自己的回复'], 403);
        }
        if (strtotime((string)$row['created_at']) < time() - $windowSeconds) {
            forum_json(['message' => '回复超过 10 分钟就不能自己删除了，可以联系管理员'], 403);
        }

        $postId = (int)$row['post_id'];
        $conn->query('UPDATE forum_replies SET is_deleted = 1 WHERE id = ' . $targetId);
        $conn->query('UPDATE forum_posts SET reply_count = GREATEST(reply_count - 1, 0) WHERE id = ' . $postId);

        // 重算"最后回复时间"
        $last = null;
        $lastRes = $conn->query('SELECT MAX(created_at) AS m FROM forum_replies WHERE post_id = ' . $postId . ' AND is_deleted = 0');
        if ($lastRes) {
            $last = $lastRes->fetch_assoc()['m'] ?? null;
        }
        $stmt = $conn->prepare('UPDATE forum_posts SET last_reply_at = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $last, $postId);
            $stmt->execute();
            $stmt->close();
        }

        boya_audit('forum-reply-delete-own', 'reply=' . $targetId . ' post=' . $postId);
        forum_json(['ok' => true, 'message' => '已删除']);
    }

    $stmt = $conn->prepare('SELECT id, author_id, created_at, is_deleted FROM forum_posts WHERE id = ? LIMIT 1');
    if (!$stmt) {
        forum_json(['message' => '操作失败，请稍后重试'], 500);
    }
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (int)$row['is_deleted'] === 1) {
        forum_json(['message' => '帖子不存在或已被删除'], 404);
    }
    if ((int)$row['author_id'] !== $memberId) {
        forum_json(['message' => '只能删除自己的帖子'], 403);
    }
    if (strtotime((string)$row['created_at']) < time() - $windowSeconds) {
        forum_json(['message' => '帖子超过 10 分钟就不能自己删除了，可以联系管理员'], 403);
    }

    $conn->query('UPDATE forum_posts SET is_deleted = 1 WHERE id = ' . $targetId);
    boya_audit('forum-post-delete-own', 'post=' . $targetId);
    forum_json(['ok' => true, 'message' => '已删除']);
}

forum_json(['message' => '未知操作'], 400);
