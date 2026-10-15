<?php
// 用户空间数据：个人主页的资料、动态时间线（帖子 + 回复，推特式）、回复列表。
require_once __DIR__ . '/../_member.php';
require_once __DIR__ . '/../_forum.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    forum_json(['message' => '缺少用户 id'], 400);
}
$tab = (string)($_GET['tab'] ?? 'profile');

$conn = member_conn();
if (!$conn) {
    forum_json(['message' => '数据库连接失败，请稍后重试'], 500);
}

$stmt = $conn->prepare('SELECT id, name, username, nickname, gender, birthday, hometown, hobbies, skills,
                               contact_info, quote, avatar, title, is_admin, created_at, last_active_at
                        FROM classmates WHERE id = ? LIMIT 1');
if (!$stmt) {
    error_log('user profile prepare failed: ' . $conn->error);
    forum_json(['message' => '查询失败，请稍后重试'], 500);
}
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    forum_json(['message' => '没有找到这位同学'], 404);
}

$isSelf = member_id() === (int)$user['id'];
$user['id'] = (int)$user['id'];
$user['is_admin'] = (int)$user['is_admin'];
$user['is_self'] = $isSelf;
$user['username'] = (string)($user['username'] ?? '');
// 联系方式对游客也可见（需求变更），不再按本人过滤 contact_info

$forum = forum_conn();

// 统计：发帖数 / 回复数
$stats = ['post_count' => 0, 'reply_count' => 0];
$stmt = $forum->prepare('SELECT COUNT(*) AS c FROM forum_posts WHERE author_id = ? AND is_deleted = 0');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stats['post_count'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
}
$stmt = $forum->prepare('SELECT COUNT(*) AS c FROM forum_replies WHERE author_id = ? AND is_deleted = 0');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stats['reply_count'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
}

$response = [
    'ok' => true,
    'user' => $user,
    'stats' => $stats,
    'loggedIn' => member_id() > 0,
    'myId' => member_id(),
    'canModerate' => forum_can_moderate(),
];

if ($tab === 'timeline' || $tab === 'replies') {
    [$page, $pageSize, $offset] = forum_pager(20);
    $total = $tab === 'replies' ? $stats['reply_count'] : ($stats['post_count'] + $stats['reply_count']);

    if ($tab === 'replies') {
        $stmt = $forum->prepare('SELECT r.id, r.post_id, r.content, r.created_at, p.title AS post_title
                                 FROM forum_replies r
                                 JOIN forum_posts p ON p.id = r.post_id
                                 WHERE r.author_id = ? AND r.is_deleted = 0 AND p.is_deleted = 0
                                 ORDER BY r.id DESC
                                 LIMIT ? OFFSET ?');
        if ($stmt) {
            $stmt->bind_param('iii', $id, $pageSize, $offset);
        }
    } else {
        // 帖子和回复合并成一条时间线（两表同库，可以 UNION）
        $stmt = $forum->prepare("SELECT * FROM (
                SELECT 'post' AS kind, p.id AS id, p.id AS post_id, p.title AS post_title,
                       LEFT(p.content, 200) AS excerpt, p.created_at AS created_at,
                       p.reply_count AS reply_count, p.view_count AS view_count, 0 AS post_deleted
                FROM forum_posts p
                WHERE p.author_id = ? AND p.is_deleted = 0
                UNION ALL
                SELECT 'reply' AS kind, r.id AS id, r.post_id AS post_id, p.title AS post_title,
                       LEFT(r.content, 200) AS excerpt, r.created_at AS created_at,
                       NULL AS reply_count, NULL AS view_count, p.is_deleted AS post_deleted
                FROM forum_replies r
                JOIN forum_posts p ON p.id = r.post_id
                WHERE r.author_id = ? AND r.is_deleted = 0
            ) AS feed
            WHERE post_deleted = 0
            ORDER BY created_at DESC, id DESC
            LIMIT ? OFFSET ?");
        if ($stmt) {
            $stmt->bind_param('iiii', $id, $id, $pageSize, $offset);
        }
    }

    if (!$stmt) {
        error_log('user feed prepare failed: ' . $forum->error);
        forum_json(['message' => '查询失败，请稍后重试'], 500);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['post_id'] = (int)$row['post_id'];
        $row['reply_count'] = $row['reply_count'] === null ? null : (int)$row['reply_count'];
        $row['view_count'] = $row['view_count'] === null ? null : (int)$row['view_count'];
        $items[] = $row;
    }
    $stmt->close();

    $response['tab'] = $tab;
    $response['page'] = $page;
    $response['pageSize'] = $pageSize;
    $response['total'] = $total;
    $response['totalPages'] = max(1, (int)ceil($total / $pageSize));
    $response['items'] = $items;
}

forum_json($response);
