<?php
// 同学录接口：读取同学列表 / 单个同学资料，以及登录、改资料、改口令、设用户名、退出。
//
// 结构说明（重构后）：本文件只做「路由 + 每个动作一个小函数」。
//   - 会话 / CSRF / 库连接：inc/member.php
//   - 头像文件规则与旧文件清理：inc/avatars.php
//   - JSON 出口：inc/http.php 的 boya_json / boya_json_error
//
// 兼容性要求（前端 edit-profile.php 与 js/forum.js 直接判断这些字符串，
// 所以 message 文案、字段名都不能改）：
//   登录成功 → message = 'success'；改资料 → '更新成功'；
//   改口令 → '密码修改成功'；设用户名 → '用户名已保存'；退出 → '已退出'。

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/photo.php';
require_once __DIR__ . '/../inc/avatars.php';
require_once __DIR__ . '/../inc/login_guard.php';
require_once __DIR__ . '/../inc/audit.php';

// ---------------------------------------------------------------- 公共小工具

function classmate_csrf_token()
{
    return member_csrf_token();
}

function require_classmate_login()
{
    if (member_id() <= 0) {
        boya_json_error('请先登录', 401);
    }
}

function require_classmate_csrf($input)
{
    if (!member_verify_csrf($input['csrf_token'] ?? '')) {
        boya_json_error('CSRF验证失败', 403);
    }
}

/**
 * 校验账号口令。$login 可以是论坛用户名，也可以是真实姓名（老同学继续用真名登录）。
 * 返回 ['id' => int, 'name' => 真实姓名, 'username' => 论坛用户名, 'upgrade' => bool]
 */
function authenticate($conn, $login, $password)
{
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

function upgrade_password($conn, $id, $password)
{
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

function profile_select_columns()
{
    // 注意：signature 已弃用（与 quote 重复），不要再往 SELECT 里加，否则列被删掉后查询会报错
    return 'id, name, username, nickname, gender, hometown, hobbies, skills, contact_info, birthday, quote, avatar,
            title, is_admin, created_at, last_active_at';
}

function format_birthday($row)
{
    if (!empty($row['birthday'])) {
        $ts = strtotime($row['birthday']);
        if ($ts !== false) {
            $row['birthday'] = date('Y-m-d', $ts);
        }
    }
    return $row;
}

/** 按 id 取一整行资料（含生日格式化），找不到返回 null。 */
function classmate_profile_row(mysqli $conn, $id)
{
    $stmt = $conn->prepare('SELECT ' . profile_select_columns() . ' FROM classmates WHERE id = ?');
    if (!$stmt) {
        boya_json_error('查询失败', 500);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $data ? format_birthday($data) : null;
}

// ---------------------------------------------------------------- 各动作

/** GET：同学列表（前端 classmates.php 直接当数组用）。 */
function classmate_action_list(mysqli $conn)
{
    $result = $conn->query('SELECT id, name, nickname, quote, avatar, birthday FROM classmates ORDER BY id');
    if (!$result) {
        error_log('classmates list query failed: ' . $conn->error);
        boya_json_error('查询失败', 500);
    }
    $classmates = [];
    while ($row = $result->fetch_assoc()) {
        $classmates[] = $row;
    }
    // 裸数组：boya_json() 只给关联数组补 ok，所以形状与原来一致
    boya_json($classmates);
}

/** GET?id=N：单个同学的资料。 */
function classmate_action_detail(mysqli $conn, $id)
{
    // 联系方式对游客可见（需求变更），本人和访客用同一组字段
    $data = classmate_profile_row($conn, $id);
    if (!$data) {
        boya_json_error('未找到该同学', 404);
    }
    boya_json($data);
}

/** POST action=login：用用户名或真实姓名 + 口令登录。 */
function classmate_action_login(mysqli $conn, array $input)
{
    $name = trim($input['name'] ?? '');
    $password = (string)($input['password'] ?? '');

    if ($name === '' || $password === '') {
        boya_json_error('姓名和密码不能为空', 400);
    }

    // 服务端限速：按姓名计数，换浏览器 / 清 cookie / 伪造 IP 头都绕不过
    $guardKey = mb_strtolower($name);
    $blockedSeconds = boya_login_guard_blocked_seconds('classmate', $guardKey);
    if ($blockedSeconds > 0) {
        boya_json_error('尝试次数过多，请 ' . max(1, (int)ceil($blockedSeconds / 60)) . ' 分钟后再试', 429);
    }

    $auth = authenticate($conn, $name, $password);
    if (!$auth) {
        boya_login_guard_fail('classmate', $guardKey);
        boya_login_guard_global_fail('classmate');
        boya_audit('classmate-login-fail', 'name=' . $name, 'guest');
        boya_json_error('姓名或密码错误', 401);
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

    boya_json([
        'message' => 'success',
        'data' => classmate_profile_row($conn, $auth['id']),
        'uid' => $auth['id'],
        'username' => $auth['username'],
        'hasUsername' => trim((string)$auth['username']) !== '',
        'csrf_token' => classmate_csrf_token(),
    ]);
}

/** POST action=update：保存个人资料（含头像）。 */
function classmate_action_update(mysqli $conn, array $input)
{
    require_classmate_login();
    require_classmate_csrf($input);

    $quote = trim((string)($input['quote'] ?? ''));
    // 兼容旧的 signature 入参（老页面缓存里可能还在发这个字段）；两者只保留一份
    if ($quote === '') {
        $quote = trim((string)($input['signature'] ?? ''));
    }
    $quote = mb_substr($quote, 0, 200);
    $avatar = boya_normalize_photo($input['avatar'] ?? '');
    $birthday = trim((string)($input['birthday'] ?? ''));
    $nickname = trim((string)($input['nickname'] ?? ''));
    $gender = trim((string)($input['gender'] ?? '其他'));
    $hometown = trim((string)($input['hometown'] ?? ''));
    $hobbies = trim((string)($input['hobbies'] ?? ''));
    $skills = trim((string)($input['skills'] ?? ''));
    $contact_info = trim((string)($input['contact_info'] ?? ''));

    if (!in_array($gender, ['男', '女', '其他'], true)) {
        $gender = '其他';
    }
    if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
        boya_json_error('生日格式不正确', 400);
    }
    if ($birthday === '') {
        $birthday = null;
    }
    // 防呆：头像必须是服务器上真实存在的文件（外链不检查），避免写入页面地址之类的脏值
    if ($avatar !== '' && !boya_photo_exists($avatar)) {
        boya_json_error('头像文件不存在（可能上传未完成），请重新上传头像', 400);
    }

    // 先取出旧头像，更新成功后删掉旧文件（只删本站生成的文件名）
    $oldAvatar = '';
    $stmtOld = $conn->prepare('SELECT avatar FROM classmates WHERE id = ?');
    if ($stmtOld) {
        $memberId = member_id();
        $stmtOld->bind_param('i', $memberId);
        $stmtOld->execute();
        $oldAvatar = (string)($stmtOld->get_result()->fetch_assoc()['avatar'] ?? '');
        $stmtOld->close();
    }

    $memberId = member_id();
    $stmt = $conn->prepare(
        'UPDATE classmates SET
            quote = ?, avatar = ?, birthday = ?,
            nickname = ?, gender = ?, hometown = ?,
            hobbies = ?, skills = ?, contact_info = ?
         WHERE id = ?'
    );
    if (!$stmt) {
        boya_json_error('更新失败', 500);
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
        $memberId
    );
    if ($stmt->execute()) {
        $stmt->close();
        boya_remove_old_avatar($oldAvatar, $avatar);
        boya_audit('classmate-profile-update', 'avatar=' . ($avatar !== '' ? $avatar : '(空)'));
        boya_json(['message' => '更新成功']);
    }
    error_log('classmate update failed: ' . $stmt->error);
    boya_json_error('更新失败', 500);
}

/** POST action=change_password：改自己的口令。 */
function classmate_action_change_password(mysqli $conn, array $input)
{
    require_classmate_login();
    require_classmate_csrf($input);

    $oldPassword = (string)($input['old_password'] ?? '');
    $newPassword = (string)($input['new_password'] ?? '');

    if ($oldPassword === '' || $newPassword === '') {
        boya_json_error('所有密码字段都不能为空', 400);
    }
    if (strlen($newPassword) < 6) {
        boya_json_error('新密码长度不能少于6位', 400);
    }

    $auth = authenticate($conn, $_SESSION['classmate_name'] ?? '', $oldPassword);
    if (!$auth) {
        boya_json_error('旧密码错误，身份验证失败', 401);
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE classmates SET password = ? WHERE id = ?');
    if (!$stmt) {
        boya_json_error('密码更新失败', 500);
    }
    $memberId = member_id();
    $stmt->bind_param('si', $hash, $memberId);
    if ($stmt->execute()) {
        $stmt->close();
        boya_json(['message' => '密码修改成功']);
    }
    error_log('classmate password update failed: ' . $stmt->error);
    boya_json_error('密码更新失败', 500);
}

/** POST action=set_username：设置 / 修改论坛用户名（登录名 + 论坛显示名）。 */
function classmate_action_set_username(mysqli $conn, array $input)
{
    require_classmate_login();
    require_classmate_csrf($input);

    $username = trim((string)($input['username'] ?? ''));

    if (!member_username_valid($username)) {
        boya_json_error('用户名只能是 3-20 位的字母、数字、下划线或短横线', 400);
    }
    if (member_username_reserved($username)) {
        boya_json_error('这个用户名是保留词，换一个吧', 400);
    }

    $memberId = member_id();

    // 不能和任何同学的真实姓名重复（防止拿别人真名当马甲）
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE name = ? AND id <> ?');
    if (!$stmt) {
        boya_json_error('保存失败，请稍后重试', 500);
    }
    $stmt->bind_param('si', $username, $memberId);
    $stmt->execute();
    $nameTaken = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
    $stmt->close();
    if ($nameTaken) {
        boya_json_error('这个用户名和同学的真实姓名重复，换一个吧', 400);
    }

    // 唯一性
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM classmates WHERE username = ? AND id <> ?');
    if (!$stmt) {
        boya_json_error('保存失败，请稍后重试', 500);
    }
    $stmt->bind_param('si', $username, $memberId);
    $stmt->execute();
    $taken = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0) > 0;
    $stmt->close();
    if ($taken) {
        boya_json_error('这个用户名已经被占用了', 400);
    }

    // 改名频率：首次设置不限，之后 7 天一次
    $stmt = $conn->prepare('SELECT username, username_changed_at FROM classmates WHERE id = ? LIMIT 1');
    if (!$stmt) {
        boya_json_error('保存失败，请稍后重试', 500);
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
            boya_json_error('用户名 7 天内只能改一次，下次可改时间：' . date('Y-m-d H:i', $nextAllowed), 400);
        }
    }

    $stmt = $conn->prepare('UPDATE classmates SET username = ?, username_changed_at = ? WHERE id = ?');
    if (!$stmt) {
        boya_json_error('保存失败，请稍后重试', 500);
    }
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param('ssi', $username, $now, $memberId);
    $saved = $stmt->execute();
    $stmt->close();

    if (!$saved) {
        boya_json_error('保存失败，请稍后重试', 500);
    }

    $_SESSION['classmate_username'] = $username;
    boya_audit('classmate-username-set', 'username=' . $username);
    boya_json(['message' => '用户名已保存', 'username' => $username]);
}

/** POST action=logout：退出同学录登录（管理员登录不受影响）。 */
function classmate_action_logout()
{
    unset($_SESSION['classmate_id'], $_SESSION['classmate_name'], $_SESSION['classmate_username']);
    boya_json(['message' => '已退出']);
}

// ---------------------------------------------------------------- 路由

$conn = member_conn();
if (!$conn) {
    boya_json_error('数据库连接失败', 500);
}

$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = boya_json_input();

if ($method === 'GET') {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        classmate_action_detail($conn, (int)$_GET['id']);
    }
    classmate_action_list($conn);
}

if ($method === 'POST') {
    $action = (string)($input['action'] ?? '');

    switch ($action) {
        case 'login':
            classmate_action_login($conn, $input);
            break;
        case 'update':
            classmate_action_update($conn, $input);
            break;
        case 'change_password':
            classmate_action_change_password($conn, $input);
            break;
        case 'set_username':
            classmate_action_set_username($conn, $input);
            break;
        case 'logout':
            classmate_action_logout();
            break;
    }

    boya_json_error('无效操作', 400);
}

boya_json_error('不支持的请求方法', 405);
