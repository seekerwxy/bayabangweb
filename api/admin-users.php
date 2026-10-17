<?php
// 同学账号管理（仅 config 管理员）：
// GET  名单（分页 + 搜索 + 发帖/回复计数）
// POST set_title / set_admin / set_username / assign_missing / reset_password / add_member
define('BOYA_ADMIN_API', true);
require_once __DIR__ . '/../admin-files/_auth.php';   // config.php + session + 管理员校验 + admin CSRF
require_once __DIR__ . '/../inc/audit.php';
require_once __DIR__ . '/../inc/member.php';          // 用户名规则
require_once __DIR__ . '/../inc/forum.php';           // forum_conn / forum_json

$conn = member_conn();
if (!$conn) {
    forum_json(['message' => '数据库连接失败，请稍后重试'], 500);
}

// ---------------------------------------------------------------- 名单
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = 30;
    $offset = ($page - 1) * $pageSize;
    $q = trim((string)($_GET['q'] ?? ''));
    $like = '%' . $q . '%';
    $where = $q !== '' ? 'WHERE name LIKE ? OR username LIKE ? OR nickname LIKE ?' : '';

    $total = 0;
    if ($where === '') {
        $res = $conn->query('SELECT COUNT(*) AS c FROM classmates');
        if ($res) {
            $total = (int)($res->fetch_assoc()['c'] ?? 0);
        }
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates ' . $where);
        if ($stmt) {
            $stmt->bind_param('sss', $like, $like, $like);
            $stmt->execute();
            $total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }
    }

    $stmt = $conn->prepare('SELECT id, name, username, nickname, title, is_admin, avatar, created_at, last_active_at
                            FROM classmates ' . $where . '
                            ORDER BY id ASC LIMIT ? OFFSET ?');
    if (!$stmt) {
        error_log('admin users list prepare failed: ' . $conn->error);
        forum_json(['message' => '查询失败'], 500);
    }
    if ($where === '') {
        $stmt->bind_param('ii', $pageSize, $offset);
    } else {
        $stmt->bind_param('sssii', $like, $like, $like, $pageSize, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_admin'] = (int)$row['is_admin'];
        $users[] = $row;
    }
    $stmt->close();

    // 论坛统计（另一台库，所以单独查）
    $postCounts = [];
    $replyCounts = [];
    $forum = forum_conn();
    $res = $forum->query('SELECT author_id, COUNT(*) AS c FROM forum_posts WHERE is_deleted = 0 GROUP BY author_id');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $postCounts[(int)$row['author_id']] = (int)$row['c'];
        }
    }
    $res = $forum->query('SELECT author_id, COUNT(*) AS c FROM forum_replies WHERE is_deleted = 0 GROUP BY author_id');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $replyCounts[(int)$row['author_id']] = (int)$row['c'];
        }
    }
    foreach ($users as &$user) {
        $user['post_count'] = $postCounts[$user['id']] ?? 0;
        $user['reply_count'] = $replyCounts[$user['id']] ?? 0;
    }
    unset($user);

    forum_json([
        'ok' => true,
        'page' => $page,
        'pageSize' => $pageSize,
        'total' => $total,
        'totalPages' => max(1, (int)ceil($total / $pageSize)),
        'users' => $users,
    ]);
}

// ---------------------------------------------------------------- 动作
if (!admin_verify_csrf($_POST['csrf_token'] ?? '')) {
    forum_json(['message' => 'CSRF 校验失败，请刷新页面重试'], 403);
}

$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

// 设称号
if ($action === 'set_title') {
    if ($id <= 0) {
        forum_json(['message' => '缺少 id'], 400);
    }
    $title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 20);
    $stmt = $conn->prepare('UPDATE classmates SET title = ? WHERE id = ?');
    if (!$stmt) {
        forum_json(['message' => '保存失败'], 500);
    }
    $stmt->bind_param('si', $title, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        forum_json(['message' => '保存失败'], 500);
    }
    boya_audit('admin-user-title', 'id=' . $id . ' title=' . $title);
    forum_json(['ok' => true, 'message' => '称号已更新']);
}

// 设 / 取消论坛管理员
if ($action === 'set_admin') {
    if ($id <= 0) {
        forum_json(['message' => '缺少 id'], 400);
    }
    $isAdmin = (int)($_POST['is_admin'] ?? 0) === 1 ? 1 : 0;
    $stmt = $conn->prepare('UPDATE classmates SET is_admin = ? WHERE id = ?');
    if (!$stmt) {
        forum_json(['message' => '保存失败'], 500);
    }
    $stmt->bind_param('ii', $isAdmin, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        forum_json(['message' => '保存失败'], 500);
    }
    boya_audit('admin-user-admin', 'id=' . $id . ' is_admin=' . $isAdmin);
    forum_json(['ok' => true, 'message' => $isAdmin ? '已设为论坛管理员' : '已取消论坛管理员']);
}

// 指定用户名（含把空用户名补齐）
if ($action === 'set_username') {
    if ($id <= 0) {
        forum_json(['message' => '缺少 id'], 400);
    }
    $username = trim((string)($_POST['username'] ?? ''));
    if (!member_username_valid($username)) {
        forum_json(['message' => '用户名只能是 3-20 位的字母、数字、下划线或短横线'], 400);
    }
    if (member_username_reserved($username)) {
        forum_json(['message' => '这个用户名是保留词'], 400);
    }

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE name = ? AND id <> ?');
    if ($stmt) {
        $stmt->bind_param('si', $username, $id);
        $stmt->execute();
        $nameTaken = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $stmt->close();
        if ($nameTaken) {
            forum_json(['message' => '这个用户名和某位同学的真实姓名重复'], 400);
        }
    }
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE username = ? AND id <> ?');
    if ($stmt) {
        $stmt->bind_param('si', $username, $id);
        $stmt->execute();
        $taken = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $stmt->close();
        if ($taken) {
            forum_json(['message' => '这个用户名已经被占用'], 400);
        }
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('UPDATE classmates SET username = ?, username_changed_at = ? WHERE id = ?');
    if (!$stmt) {
        forum_json(['message' => '保存失败'], 500);
    }
    $stmt->bind_param('ssi', $username, $now, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        forum_json(['message' => '保存失败'], 500);
    }
    boya_audit('admin-user-username', 'id=' . $id . ' username=' . $username);
    forum_json(['ok' => true, 'message' => '用户名已设为 ' . $username]);
}

// 给所有还没设用户名的同学批量分配 boya001 这种
if ($action === 'assign_missing') {
    $res = $conn->query("SELECT id FROM classmates WHERE username IS NULL OR username = '' ORDER BY id");
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
    }

    $assigned = 0;
    $failed = 0;
    $now = date('Y-m-d H:i:s');
    $update = $conn->prepare('UPDATE classmates SET username = ?, username_changed_at = ? WHERE id = ?');
    $check = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE username = ?');

    foreach ($ids as $uid) {
        $final = 'boya' . str_pad((string)$uid, 3, '0', STR_PAD_LEFT);
        $suffix = 0;
        while ($check) {
            $check->bind_param('s', $final);
            $check->execute();
            $taken = (int)($check->get_result()->fetch_assoc()['c'] ?? 0) > 0;
            if (!$taken) {
                break;
            }
            $suffix++;
            $final = 'boya' . str_pad((string)$uid, 3, '0', STR_PAD_LEFT) . $suffix;
            if ($suffix > 50) {
                $final = '';
                break;
            }
        }
        if ($final === '' || !member_username_valid($final) || !$update) {
            $failed++;
            continue;
        }
        $update->bind_param('ssi', $final, $now, $uid);
        if ($update->execute()) {
            $assigned++;
        } else {
            $failed++;
        }
    }
    if ($update) {
        $update->close();
    }
    if ($check) {
        $check->close();
    }

    boya_audit('admin-user-assign-username', 'assigned=' . $assigned . ' failed=' . $failed);
    forum_json(['ok' => true, 'message' => '已为 ' . $assigned . ' 位同学分配用户名' . ($failed ? '，失败 ' . $failed . ' 位' : '')]);
}

// 重置口令（只在响应里显示一次，绝不写日志）
if ($action === 'reset_password') {
    if ($id <= 0) {
        forum_json(['message' => '缺少 id'], 400);
    }
    $plain = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE classmates SET password = ? WHERE id = ?');
    if (!$stmt) {
        forum_json(['message' => '重置失败'], 500);
    }
    $stmt->bind_param('si', $hash, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        forum_json(['message' => '重置失败'], 500);
    }
    boya_audit('admin-user-reset-password', 'id=' . $id);
    forum_json([
        'ok' => true,
        'password' => $plain,
        'message' => '口令已重置：' . $plain . '（只显示这一次，请立刻复制给同学）',
    ]);
}

// 新增同学
if ($action === 'add_member') {
    $name = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 30);
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '') {
        forum_json(['message' => '姓名不能为空'], 400);
    }
    if ($username !== '' && !member_username_valid($username)) {
        forum_json(['message' => '用户名只能是 3-20 位的字母、数字、下划线或短横线'], 400);
    }
    if ($password === '') {
        $password = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
    }

    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE name = ?');
    if ($stmt) {
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $exists = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $stmt->close();
        if ($exists) {
            forum_json(['message' => '已经有同名的同学了'], 400);
        }
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('INSERT INTO classmates (name, username, password, gender, created_at, last_active_at)
                            VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        error_log('admin user insert prepare failed: ' . $conn->error);
        forum_json(['message' => '添加失败，请稍后重试'], 500);
    }
    $usernameValue = $username === '' ? null : $username;
    $gender = '其他';
    $stmt->bind_param('ssssss', $name, $usernameValue, $hash, $gender, $now, $now);
    $ok = $stmt->execute();
    $newId = (int)$conn->insert_id;
    $stmt->close();
    if (!$ok) {
        error_log('admin user insert failed: ' . $conn->error);
        forum_json(['message' => '添加失败（可能是用户名重复），请检查后重试'], 500);
    }

    boya_audit('admin-user-add', 'id=' . $newId . ' name=' . $name);
    forum_json([
        'ok' => true,
        'id' => $newId,
        'password' => $password,
        'message' => '已添加 ' . $name . '（UID ' . $newId . '），初始口令：' . $password . '（只显示这一次）',
    ]);
}

// 一次性清理：把已弃用的 signature 合并进 quote，然后删列（幂等，可重复点）
// 说明：这几条 SQL 已按 MySQL/MariaDB 语法逐条核对过；为避免删列这类不可逆操作，
// 迁移失败时只记录日志，绝不吞掉错误继续跑。
if ($action === 'dedupe_profile_fields') {
    $res = $conn->query("SHOW COLUMNS FROM `classmates` LIKE 'signature'");
    if (!$res) {
        error_log('dedupe show columns failed: ' . $conn->error);
        forum_json(['message' => '读取表结构失败：' . $conn->error], 500);
    }
    if ($res->num_rows === 0) {
        forum_json(['ok' => true, 'message' => 'signature 字段已经不存在，无需清理']);
    }

    // 只搬「座右铭是空的」那些行，绝不覆盖同学自己填的座右铭
    $before = 0;
    $countRes = $conn->query("SELECT COUNT(*) AS c FROM `classmates`
                              WHERE (`quote` IS NULL OR `quote` = '')
                                AND `signature` IS NOT NULL AND `signature` <> ''");
    if ($countRes) {
        $before = (int)($countRes->fetch_assoc()['c'] ?? 0);
    }

    if (!$conn->query("UPDATE `classmates` SET `quote` = `signature`
                       WHERE (`quote` IS NULL OR `quote` = '')
                         AND `signature` IS NOT NULL AND `signature` <> ''")) {
        error_log('dedupe update quote failed: ' . $conn->error);
        forum_json(['message' => '搬运个性签名失败，未做任何删除：' . $conn->error], 500);
    }

    if (!$conn->query('ALTER TABLE `classmates` DROP COLUMN `signature`')) {
        error_log('dedupe drop signature failed: ' . $conn->error);
        // 搬运成功了、删列失败：数据没丢，可以直接重试
        forum_json(['message' => '已搬运 ' . $before . ' 条签名，但删除字段失败（数据没丢，可重试）：' . $conn->error], 500);
    }

    boya_audit('admin-user-dedupe-profile-fields', 'moved=' . $before);
    forum_json(['ok' => true, 'message' => '已把 ' . $before . ' 条个性签名并入座右铭，并删除 signature 字段']);
}

forum_json(['message' => '未知操作'], 400);
