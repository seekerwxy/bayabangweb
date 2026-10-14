<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../_photo.php';
require_once __DIR__ . '/../_login_guard.php';
require_once __DIR__ . '/../_audit.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

function json_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function classmate_csrf_token() {
    if (empty($_SESSION['classmate_csrf_token'])) {
        $_SESSION['classmate_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['classmate_csrf_token'];
}

function verify_classmate_csrf($token) {
    return is_string($token)
        && isset($_SESSION['classmate_csrf_token'])
        && hash_equals($_SESSION['classmate_csrf_token'], $token);
}

function require_classmate_login() {
    if (empty($_SESSION['classmate_id'])) {
        json_response(401, ['message' => '请先登录']);
    }
}

function require_classmate_csrf($input) {
    if (!verify_classmate_csrf($input['csrf_token'] ?? '')) {
        json_response(403, ['message' => 'CSRF验证失败']);
    }
}

/**
 * 删除被替换掉的头像文件，避免上传目录无限膨胀。
 * 只处理 /uploads/classmates/ 与旧的 /api/photos/classmates/ 下
 * 由本站生成的文件名（32 位十六进制 + 图片扩展名），其他路径一律不碰。
 */
function boya_remove_old_avatar($oldAvatar, $newAvatar)
{
    $old = (string)$oldAvatar;
    if ($old === '' || $old === (string)$newAvatar) {
        return;
    }
    if (!preg_match('#^/(uploads|api/photos)/classmates/[0-9a-f]{32}\.(jpg|png|webp)$#', $old)) {
        return;
    }
    $path = dirname(__DIR__) . $old;
    if (is_file($path)) {
        @unlink($path);
    }
}

function authenticate($conn, $name, $password) {
    $stmt = $conn->prepare('SELECT id, password FROM classmates WHERE name = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    $stored = $row['password'] ?? '';
    $isHashed = preg_match('/^\$2[aby]\$/', $stored) === 1 || strncmp($stored, '$argon2', 7) === 0;

    if (password_verify($password, $stored)) {
        return ['id' => (int)$row['id'], 'upgrade' => !$isHashed];
    }

    if (!$isHashed && hash_equals($stored, $password)) {
        return ['id' => (int)$row['id'], 'upgrade' => true];
    }

    return false;
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
    return 'id, name, nickname, gender, hometown, hobbies, skills, contact_info, birthday, quote, avatar';
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

$conn = new mysqli(
    $config['classmates_db']['host'],
    $config['classmates_db']['username'],
    $config['classmates_db']['password'],
    $config['classmates_db']['database']
);

if ($conn->connect_error) {
    error_log('classmates DB connect failed: ' . $conn->connect_error);
    json_response(500, ['message' => '数据库连接失败']);
}
$conn->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

if ($method === 'GET') {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = intval($_GET['id']);
        $isSelf = !empty($_SESSION['classmate_id']) && (int)$_SESSION['classmate_id'] === $id;
        $columns = $isSelf
            ? profile_select_columns()
            : 'id, name, nickname, gender, hometown, hobbies, skills, birthday, quote, avatar';
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
        $_SESSION['classmate_name'] = $name;
        boya_audit('classmate-login-ok', 'name=' . $name);

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
            'csrf_token' => classmate_csrf_token()
        ]);
    }

    if ($action === 'update') {
        require_classmate_login();
        require_classmate_csrf($input);

        $quote = trim($input['quote'] ?? '');
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

    if ($action === 'logout') {
        unset($_SESSION['classmate_id'], $_SESSION['classmate_name']);
        json_response(200, ['message' => '已退出']);
    }

    json_response(400, ['message' => '无效操作']);
}

http_response_code(405);
echo json_encode(['message' => '不支持的请求方法'], JSON_UNESCAPED_UNICODE);
