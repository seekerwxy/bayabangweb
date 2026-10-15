<?php
// 留言板读取接口（游客可读）：帖子列表、帖子详情 + 回复。
require_once __DIR__ . '/../_forum.php';

$conn = forum_conn();
$action = (string)($_GET['action'] ?? 'list');

/** 把作者信息补到一行数据上（用户名取活的，取不到用发帖时的快照）。 */
function forum_attach_author(array &$row, array $profiles)
{
    $authorId = (int)$row['author_id'];
    $profile = $profiles[$authorId] ?? null;
    $liveUsername = $profile ? trim((string)$profile['username']) : '';

    $row['author_id'] = $authorId;
    $row['author_uid'] = $authorId;
    $row['author_username'] = $liveUsername !== ''
        ? $liveUsername
        : member_display_name($row['author_name'] ?? '', $authorId);
    $row['author_nickname'] = $profile ? (string)$profile['nickname'] : '';
    $row['author_title'] = $profile ? (string)$profile['title'] : '';
    $row['author_is_admin'] = $profile ? (int)$profile['is_admin'] : 0;
    $row['author_avatar'] = $profile ? (string)$profile['avatar'] : '';
    $row['is_history'] = $authorId === 0;
}

// ---------------------------------------------------------------- 帖子列表
if ($action === 'list') {
    [$page, $pageSize, $offset] = forum_pager(20);

    $total = 0;
    $res = $conn->query('SELECT COUNT(*) AS c FROM forum_posts WHERE is_deleted = 0');
    if ($res) {
        $total = (int)($res->fetch_assoc()['c'] ?? 0);
    }

    $stmt = $conn->prepare("SELECT id, author_id, author_name, title, LEFT(content, 160) AS excerpt,
                                   created_at, last_reply_at, reply_count, view_count, is_pinned, is_locked
                            FROM forum_posts
                            WHERE is_deleted = 0
                            ORDER BY is_pinned DESC, COALESCE(last_reply_at, created_at) DESC, id DESC
                            LIMIT ? OFFSET ?");
    if (!$stmt) {
        error_log('forum list prepare failed: ' . $conn->error);
        forum_json(['message' => '查询失败，请稍后重试'], 500);
    }
    $stmt->bind_param('ii', $pageSize, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $posts = [];
    $authorIds = [];
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
        if ((int)$row['author_id'] > 0) {
            $authorIds[] = (int)$row['author_id'];
        }
    }
    $stmt->close();

    $profiles = forum_member_profiles($authorIds);
    foreach ($posts as &$post) {
        forum_attach_author($post, $profiles);
        $post['id'] = (int)$post['id'];
        $post['reply_count'] = (int)$post['reply_count'];
        $post['view_count'] = (int)$post['view_count'];
        $post['is_pinned'] = (int)$post['is_pinned'];
        $post['is_locked'] = (int)$post['is_locked'];
    }
    unset($post);

    forum_json([
        'ok' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'totalPages' => max(1, (int)ceil($total / $pageSize)),
        'loggedIn' => member_id() > 0,
        'canModerate' => forum_can_moderate(),
        'posts' => $posts,
    ]);
}

// ------------------------------------------------------- 帖子详情 + 回复
if ($action === 'post') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        forum_json(['message' => '缺少帖子 id'], 400);
    }

    $stmt = $conn->prepare('SELECT * FROM forum_posts WHERE id = ? LIMIT 1');
    if (!$stmt) {
        error_log('forum post prepare failed: ' . $conn->error);
        forum_json(['message' => '查询失败，请稍后重试'], 500);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$post) {
        forum_json(['message' => '帖子不存在'], 404);
    }
    if ((int)$post['is_deleted'] === 1) {
        forum_json(['message' => '这个帖子已经被删除'], 404);
    }

    // 浏览量 +1：同一个访客（按访问 IP 归并，与 cookie / session 无关）对同一篇帖子
    // 默认 6 小时内只算一次。以前是「每次请求详情都 +1」，而翻页、发回复、删除、管理
    // 都会重新拉详情，自己刷新几下就能把数字刷上去；作者本人始终不计数。
    $viewerId = member_id();
    $isAuthor = $viewerId > 0 && $viewerId === (int)$post['author_id'];
    $viewCounted = false;
    if (!$isAuthor) {
        $viewCounted = forum_view_hit((int)$post['id']);
        if ($viewCounted) {
            $conn->query('UPDATE forum_posts SET view_count = view_count + 1 WHERE id = ' . (int)$post['id']);
            $post['view_count'] = (int)$post['view_count'] + 1;
        }
    }

    [$page, $pageSize, $offset] = forum_pager(20);

    $replyTotal = 0;
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM forum_replies WHERE post_id = ? AND is_deleted = 0');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $replyTotal = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $stmt->close();
    }

    $stmt = $conn->prepare('SELECT id, author_id, author_name, content, created_at
                            FROM forum_replies
                            WHERE post_id = ? AND is_deleted = 0
                            ORDER BY id ASC
                            LIMIT ? OFFSET ?');
    if (!$stmt) {
        error_log('forum replies prepare failed: ' . $conn->error);
        forum_json(['message' => '查询失败，请稍后重试'], 500);
    }
    $stmt->bind_param('iii', $id, $pageSize, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $replies = [];
    $authorIds = [(int)$post['author_id']];
    $floor = $offset + 1;
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['floor'] = $floor++;
        $replies[] = $row;
        if ((int)$row['author_id'] > 0) {
            $authorIds[] = (int)$row['author_id'];
        }
    }
    $stmt->close();

    $profiles = forum_member_profiles($authorIds);
    forum_attach_author($post, $profiles);
    foreach ($replies as &$reply) {
        forum_attach_author($reply, $profiles);
    }
    unset($reply);

    $postPayload = [
        'id' => (int)$post['id'],
        'title' => (string)$post['title'],
        'content' => (string)$post['content'],
        'author_id' => (int)$post['author_id'],
        'author_uid' => (int)$post['author_id'],
        'author_name' => (string)$post['author_name'],
        'author_username' => $post['author_username'],
        'author_nickname' => $post['author_nickname'],
        'author_title' => $post['author_title'],
        'author_is_admin' => $post['author_is_admin'],
        'author_avatar' => $post['author_avatar'],
        'is_history' => $post['is_history'],
        'created_at' => (string)$post['created_at'],
        'last_reply_at' => $post['last_reply_at'],
        'reply_count' => (int)$post['reply_count'],
        'view_count' => (int)$post['view_count'],
        // 这次请求有没有把浏览量 +1（作者本人和同一访客的重复访问都是 false）
        'view_counted' => $viewCounted,
        'is_pinned' => (int)$post['is_pinned'],
        'is_locked' => (int)$post['is_locked'],
        'can_moderate' => forum_can_moderate(),
    ];

    forum_json([
        'ok' => true,
        'post' => $postPayload,
        'replies' => $replies,
        'page' => $page,
        'pageSize' => $pageSize,
        'replyTotal' => $replyTotal,
        'totalPages' => max(1, (int)ceil($replyTotal / $pageSize)),
        'loggedIn' => member_id() > 0,
        'myId' => member_id(),
    ]);
}

forum_json(['message' => '未知操作'], 400);
