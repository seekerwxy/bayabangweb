<?php

require_once __DIR__ . '/../_member.php';   // 会话 / CSRF / 同学录库连接（含 username 字段自动补齐）
require_once __DIR__ . '/../_photo.php';
require_once __DIR__ . '/../_login_guard.php';
require_once __DIR__ . '/../_audit.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

function json_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// 会话与 CSRF 统一实现在 _member.php，这里保留同名薄封装，调用处不用改。
function classmate_csrf_token() {
    return member_csrf_token();
}

function verify_classmate_csrf($token) {
    return member_verify_csrf($token);
}

function require_classmate_login() {
    if (member_id() <= 0) {
        json_response(401, ['message' => '请先登录']);
    }
}

function require_classmate_csrf($input) {
    if (!member_verify_csrf($input['csrf_token'] ?? '')) {
        json_response(403, ['message' => 'CSRF验证失败']);
    }
}

/**
 * 删除被替换掉的头像文件，避免上传目录无限膨胀。
 *
 * 只处理两个头像目录（新目录 /uploads/classmates/ 与旧目录 /api/photos/classmates/）
 * 下的「本站生成的文件名（十六进制 + 图片扩展名）」，其他路径、其他文件名一律不碰。
 * 十六进制段不再限制为 32 位：早期版本用 uniqid() 生成 avatar_<13位hex>.jpeg，
 * 老规则匹配不到，导致这些旧头像永远删不掉、越积越多。
 */
function boya_avatar_file_name($name)
{
    // avatar_ 前缀（旧版）或有 / 无前缀的十六进制名（新版 32 位），扩展名与上传白名单一致
    return preg_match('/^(?:avatar_)?[0-9a-f]{8,64}\.(?:jpg|jpeg|png|webp)$/i', (string)$name) === 1;
}

function boya_remove_old_avatar($oldAvatar, $newAvatar)
{
    $old = (string)$oldAvatar;
    if ($old === '' || $old === (string)$newAvatar) {
        return;
    }
    // 只认这两个目录，并且只按「文件名」判断，不做任何路径层面的猜测
    if (!preg_match('#^/(uploads|api/photos)/classmates/([^/]+)$#', $old, $matches)) {
        return;
    }
    if (!boya_avatar_file_name($matches[2])) {
        return;
    }

    $path = dirname(__DIR__) . '/' . $matches[1] . '/classmates/' . $matches[2];
    if (is_file($path) && !@unlink($path)) {
        error_log('old avatar delete failed: ' . $path);
    }
}

/**
 * 校验账号口令。$login 可以是论坛用户名，也可以是真实姓名（老同学继续用真名登录）。
 * 返回 ['id' => int, 'name' => 真实姓名, 'username' => 论坛用户名, 'upgrade' => bool]
 */
function authenticate($conn, $login, $password) {
    $login = trim((string)$login);
    if ($login === '') {
        return false;
    }

    $row = null;
    foreach (['username', 'name'] as $column) {
        $stmt = $conn->prepare('SELECT id, name, username, password FROM classmates WHERE ' . $column . ' = ? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($found) {
            $row = $found;
            break;
        }
    }
    if (!$row) {
        return false;
    }

    $stored = (string)($row['password'] ?? '');
    $isHashed = preg_match('/^\$2[aby]\$/', $stored) === 1 || strncmp($stored, '$argon2', 7) === 0;

    $ok = password_verify($password, $stored);
    if (!$ok && !$isHashed) {
        $ok = hash_equals($stored, $password);
    }
    if (!$ok) {
        return false;
    }

    return [
        'id' => (int)$row['id'],
        'name' => (string)($row['name'] ?? ''),
        'username' => (string)($row['username'] ?? ''),
        'upgrade' => !$isHashed,
    ];
}

function upgrade_password($conn, $id, $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE classmates SET password = ? WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $hash, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function profile_select_columns() {
    // 注意：signature 已弃用（与 quote 重复），不要再往 SELECT 里加，否则列被删掉后查询会报错
    return 'id, name, username, nickname, gender, hometown, hobbies, skills, contact_info, birthday, quote, avatar,
            title, is_admin, created_at, last_active_at';
}

function format_birthday($row) {
    if (!empty($row['birthday'])) {
        $ts = strtotime($row['birthday']);
        if ($ts !== false) {
            $row['birthday'] = date('Y-m-d', $ts);
        }
    }
    return $row;
}

$conn = member_conn();
if (!$conn) {
    json_response(500, ['message' => '数据库连接失败']);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

if ($method === 'GET') {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = intval($_GET['id']);
        // 联系方式对游客可见（需求变更），本人和访客用同一组字段
        $columns = profile_select_columns();
        $stmt = $conn->prepare('SELECT ' . $columns . ' FROM classmates WHERE id = ?');
        if (!$stmt) {
            json_response(500, ['message' => '查询失败']);
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($data) {
            echo json_encode(format_birthday($data), JSON_UNESCAPED_UNICODE);
        } else {
            json_response(404, ['message' => '未找到该同学']);
        }
        exit;
    }

    $result = $conn->query('SELECT id, name, nickname, quote, avatar, birthday FROM classmates ORDER BY id');
    if (!$result) {
        error_log('classmates list query failed: ' . $conn->error);
        json_response(500, ['message' => '查询失败']);
    }
    $classmates = [];
    while ($row = $result->fetch_assoc()) {
        $classmates[] = $row;
    }
    echo json_encode($classmates, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $action = $input['action'] ?? '';

    if ($action === 'login') {
        $name = trim($input['name'] ?? '');
        $password = (string)($input['password'] ?? '');

        if ($name === '' || $password === '') {
            json_response(400, ['message' => '姓名和密码不能为空']);
        }

        // 服务端限速：按姓名计数，换浏览器 / 清 cookie / 伪造 IP 头都绕不过
        $guardKey = mb_strtolower($name);
        $blockedSeconds = boya_login_guard_blocked_seconds('classmate', $guardKey);
        if ($blockedSeconds > 0) {
            json_response(429, ['message' => '尝试次数过多，请 ' . max(1, (int)ceil($blockedSeconds / 60)) . ' 分钟后再试']);
        }

        $auth = authenticate($conn, $name, $password);
        if (!$auth) {
            boya_login_guard_fail('classmate', $guardKey);
            boya_login_guard_global_fail('classmate');
            boya_audit('classmate-login-fail', 'name=' . $name, 'guest');
            json_response(401, ['message' => '姓名或密码错误']);
        }

        if ($auth['upgrade']) {
            upgrade_password($conn, $auth['id'], $password);
        }
        boya_login_guard_clear('classmate', $guardKey);

        session_regenerate_id(true);
        $_SESSION['classmate_id'] = $auth['id'];
        $_SESSION['classmate_name'] = $auth['name'];          // 真实姓名：只用于会话标识与审计
        $_SESSION['classmate_username'] = $auth['username'];  // 论坛显示名（可能还没设置）
        boya_audit('classmate-login-ok', 'id=' . $auth['id']);
        member_touch_active($auth['id']);                     // 记录"最后活跃"

        $stmt = $conn->prepare('SELECT ' . profile_select_columns() . ' FROM classmates WHERE id = ?');
        if (!$stmt) {
            json_response(500, ['message' => '查询失败']);
        }
        $stmt->bind_param('i', $auth['id']);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        json_response(200, [
            'message' => 'success',
            'data' => $data ? format_birthday($data) : null,
            'uid' => $auth['id'],
            'username' => $auth['username'],
            'hasUsername' => trim($auth['username']) !== '',
            'csrf_token' => classmate_csrf_token()
        ]);
    }

    if ($action === 'update') {
        require_classmate_login();
        require_classmate_csrf($input);

        $quote = trim((string)($input['quote'] ?? ''));
        // 兼容旧的 signature 入参（老页面缓存里可能还在发这个字段）；两者只保留一份
        if ($quote === '') {
            $quote = trim((string)($input['signature'] ?? ''));
        }
        $quote = mb_substr($quote, 0, 200);
        $avatar = boya_normalize_photo($input['avatar'] ?? '');
        $birthday = trim($input['birthday'] ?? '');
        $nickname = trim($input['nickname'] ?? '');
        $gender = trim($input['gender'] ?? '其他');
        $hometown = trim($input['hometown'] ?? '');
        $hobbies = trim($input['hobbies'] ?? '');
        $skills = trim($input['skills'] ?? '');
        $contact_info = trim($input['contact_info'] ?? '');

        if (!in_array($gender, ['男', '女', '其他'], true)) {
            $gender = '其他';
        }
        if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            json_response(400, ['message' => '生日格式不正确']);
        }
        if ($birthday === '') {
            $birthday = null;
        }
        // 防呆：头像必须是服务器上真实存在的文件（外链不检查），避免写入页面地址之类的脏值
        if ($avatar !== '' && !boya_photo_exists($avatar)) {
            json_response(400, ['message' => '头像文件不存在（可能上传未完成），请重新上传头像']);
        }

        // 先取出旧头像，更新成功后删掉旧文件（只删本站生成的文件名）
        $oldAvatar = '';
        $stmtOld = $conn->prepare('SELECT avatar FROM classmates WHERE id = ?');
        if ($stmtOld) {
            $stmtOld->bind_param('i', $_SESSION['classmate_id']);
            $stmtOld->execute();
            $oldAvatar = (string)($stmtOld->get_result()->fetch_assoc()['avatar'] ?? '');
            $stmtOld->close();
        }

        $stmt = $conn->prepare(
            'UPDATE classmates SET
                quote = ?, avatar = ?, birthday = ?,
                nickname = ?, gender = ?, hometown = ?,
                hobbies = ?, skills = ?, contact_info = ?
             WHERE id = ?'
        );
        if (!$stmt) {
            json_response(500, ['message' => '更新失败']);
        }
        $stmt->bind_param(
            'sssssssssi',
            $quote,
            $avatar,
            $birthday,
            $nickname,
            $gender,
            $hometown,
            $hobbies,
            $skills,
            $contact_info,
            $_SESSION['classmate_id']
        );
        if ($stmt->execute()) {
            boya_remove_old_avatar($oldAvatar, $avatar);
            boya_audit('classmate-profile-update', 'avatar=' . ($avatar !== '' ? $avatar : '(空)'));
            json_response(200, ['message' => '更新成功']);
        }
        error_log('classmate update failed: ' . $stmt->error);
        json_response(500, ['message' => '更新失败']);
    }

    if ($action === 'change_password') {
        require_classmate_login();
        require_classmate_csrf($input);

        $oldPassword = (string)($input['old_password'] ?? '');
        $newPassword = (string)($input['new_password'] ?? '');

        if ($oldPassword === '' || $newPassword === '') {
            json_response(400, ['message' => '所有密码字段都不能为空']);
        }
        if (strlen($newPassword) < 6) {
            json_response(400, ['message' => '新密码长度不能少于6位']);
        }

        $auth = authenticate($conn, $_SESSION['classmate_name'] ?? '', $oldPassword);
        if (!$auth) {
            json_response(401, ['message' => '旧密码错误，身份验证失败']);
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE classmates SET password = ? WHERE id = ?');
        if (!$stmt) {
            json_response(500, ['message' => '密码更新失败']);
        }
        $stmt->bind_param('si', $hash, $_SESSION['classmate_id']);
        if ($stmt->execute()) {
            json_response(200, ['message' => '密码修改成功']);
        }
        error_log('classmate password update failed: ' . $stmt->error);
        json_response(500, ['message' => '密码更新失败']);
    }

    // 设置 / 修改论坛用户名（登录名 + 论坛显示名，与真实姓名分开）
    if ($action === 'set_username') {
        require_classmate_login();
        require_classmate_csrf($input);

        $username = trim((string)($input['username'] ?? ''));

        if (!member_username_valid($username)) {
            json_response(400, ['message' => '用户名只能是 3-20 位的字母、数字、下划线或短横线']);
        }
        if (member_username_reserved($username)) {
            json_response(400, ['message' => '这个用户名是保留词，换一个吧']);
        }

        $memberId = member_id();

        // 不能和任何同学的真实姓名重复（防止拿别人真名当马甲）
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE name = ? AND id <> ?');
        if (!$stmt) {
            json_response(500, ['message' => '保存失败，请稍后重试']);
        }
        $stmt->bind_param('si', $username, $memberId);
        $stmt->execute();
        $nameTaken = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $stmt->close();
        if ($nameTaken) {
            json_response(400, ['message' => '这个用户名和同学的真实姓名重复，换一个吧']);
        }

        // 唯一性
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE username = ? AND id <> ?');
        if (!$stmt) {
            json_response(500, ['message' => '保存失败，请稍后重试']);
        }
        $stmt->bind_param('si', $username, $memberId);
        $stmt->execute();
        $taken = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
        $stmt->close();
        if ($taken) {
            json_response(400, ['message' => '这个用户名已经被占用了']);
        }

        // 改名频率：首次设置不限，之后 7 天一次
        $stmt = $conn->prepare('SELECT username, username_changed_at FROM classmates WHERE id = ? LIMIT 1');
        if (!$stmt) {
            json_response(500, ['message' => '保存失败，请稍后重试']);
        }
        $stmt->bind_param('i', $memberId);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $oldUsername = (string)($current['username'] ?? '');
        $changedAt = (string)($current['username_changed_at'] ?? '');
        if ($oldUsername !== '' && $oldUsername !== $username && $changedAt !== '') {
            $nextAllowed = strtotime($changedAt) + 7 * 86400;
            if ($nextAllowed > time()) {
                json_response(400, ['message' => '用户名 7 天内只能改一次，下次可改时间：' . date('Y-m-d H:i', $nextAllowed)]);
            }
        }

        $stmt = $conn->prepare('UPDATE classmates SET username = ?, username_changed_at = ? WHERE id = ?');
        if (!$stmt) {
            json_response(500, ['message' => '保存失败，请稍后重试']);
        }
        $now = date('Y-m-d H:i:s');
        $stmt->bind_param('ssi', $username, $now, $memberId);
        $saved = $stmt->execute();
        $stmt->close();

        if (!$saved) {
            json_response(500, ['message' => '保存失败，请稍后重试']);
        }

        $_SESSION['classmate_username'] = $username;
        boya_audit('classmate-username-set', 'username=' . $username);
        json_response(200, ['message' => '用户名已保存', 'username' => $username]);
    }

    if ($action === 'logout') {
        unset($_SESSION['classmate_id'], $_SESSION['classmate_name'], $_SESSION['classmate_username']);
        json_response(200, ['message' => '已退出']);
    }

    json_response(400, ['message' => '无效操作']);
}

http_response_code(405);
echo json_encode(['message' => '不支持的请求方法'], JSON_UNESCAPED_UNICODE);
