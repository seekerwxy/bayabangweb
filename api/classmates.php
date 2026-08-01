<?php

require_once __DIR__ . '/../config.php';

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

function login_blocked() {
    $failures = $_SESSION['classmate_login_failures'] ?? 0;
    $lockedUntil = $_SESSION['classmate_login_locked_until'] ?? 0;
    if ($failures >= 5 && time() < $lockedUntil) {
        return true;
    }
    if ($lockedUntil > 0 && time() >= $lockedUntil) {
        $_SESSION['classmate_login_failures'] = 0;
        unset($_SESSION['classmate_login_locked_until']);
    }
    return false;
}

function record_login_failure() {
    $_SESSION['classmate_login_failures'] = ($_SESSION['classmate_login_failures'] ?? 0) + 1;
    if ($_SESSION['classmate_login_failures'] >= 5) {
        $_SESSION['classmate_login_locked_until'] = time() + 900;
    }
}

function clear_login_failures() {
    unset($_SESSION['classmate_login_failures'], $_SESSION['classmate_login_locked_until']);
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
        if (login_blocked()) {
            json_response(429, ['message' => '尝试次数过多，请稍后再试']);
        }

        $auth = authenticate($conn, $name, $password);
        if (!$auth) {
            record_login_failure();
            json_response(401, ['message' => '姓名或密码错误']);
        }

        if ($auth['upgrade']) {
            upgrade_password($conn, $auth['id'], $password);
        }
        clear_login_failures();

        session_regenerate_id(true);
        $_SESSION['classmate_id'] = $auth['id'];
        $_SESSION['classmate_name'] = $name;

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
        $avatar = trim($input['avatar'] ?? '');
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
