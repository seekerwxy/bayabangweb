<?php

require_once 'config.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Shanghai');

$conn = new mysqli($config['classmates_db']['host'], $config['classmates_db']['username'], $config['classmates_db']['password'], $config['classmates_db']['database']);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "数据库连接失败"]);
    exit;
}
$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// 身份验证（明文密码比较）
function authenticate($conn, $name, $password) {
    $stmt = $conn->prepare("SELECT id, password FROM classmates WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['password'] === $password) {
            return $row['id'];
        }
    }
    return false;
}

// GET：获取所有同学公开信息（包括生日）
if ($method === 'GET') {
    $sql = "SELECT id, name, quote, avatar, birthday FROM classmates ORDER BY id";
    $result = $conn->query($sql);
    $classmates = [];
    while ($row = $result->fetch_assoc()) {
        $classmates[] = $row;
    }
    echo json_encode($classmates);
    exit;
}

// POST
if ($method === 'POST') {
    $action = $input['action'] ?? '';

    // 登录
    if ($action === 'login') {
        $name = trim($input['name'] ?? '');
        $password = trim($input['password'] ?? '');
        if (empty($name) || empty($password)) {
            http_response_code(400);
            echo json_encode(["message" => "姓名和密码不能为空"]);
            exit;
        }
        $userId = authenticate($conn, $name, $password);
        if ($userId) {
            $stmt = $conn->prepare("SELECT name, quote, avatar, birthday FROM classmates WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            // 格式化生日，方便前端使用
            if ($data['birthday']) {
                $data['birthday'] = date('Y-m-d', strtotime($data['birthday']));
            }
            echo json_encode(["message" => "success", "data" => $data]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "姓名或密码错误"]);
        }
        exit;
    }

    // 更新个人信息（座右铭、头像、生日）
    if ($action === 'update') {
        $name = trim($input['name'] ?? '');
        $password = trim($input['password'] ?? '');
        $quote = trim($input['quote'] ?? '');
        $avatar = trim($input['avatar'] ?? '');
        $birthday = trim($input['birthday'] ?? '');  // 接收生日，格式 YYYY-MM-DD

        if (empty($name) || empty($password)) {
            http_response_code(400);
            echo json_encode(["message" => "姓名和密码不能为空"]);
            exit;
        }
        $userId = authenticate($conn, $name, $password);
        if (!$userId) {
            http_response_code(401);
            echo json_encode(["message" => "身份验证失败，无权修改"]);
            exit;
        }

        // 验证生日格式（只允许 YYYY-MM-DD 或空）
        if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            http_response_code(400);
            echo json_encode(["message" => "生日格式不正确"]);
            exit;
        }
        // 如果生日为空字符串，存入 NULL
        if ($birthday === '') {
            $birthday = null;
        }

        $stmt = $conn->prepare("UPDATE classmates SET quote = ?, avatar = ?, birthday = ? WHERE id = ?");
        $stmt->bind_param("sssi", $quote, $avatar, $birthday, $userId);
        if ($stmt->execute()) {
            echo json_encode(["message" => "更新成功"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "更新失败"]);
        }
        exit;
    }

    // 修改密码
    if ($action === 'change_password') {
        $name = trim($input['name'] ?? '');
        $oldPassword = trim($input['old_password'] ?? '');
        $newPassword = trim($input['new_password'] ?? '');

        if (empty($name) || empty($oldPassword) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(["message" => "所有密码字段都不能为空"]);
            exit;
        }
        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(["message" => "新密码长度不能少于6位"]);
            exit;
        }

        $userId = authenticate($conn, $name, $oldPassword);
        if (!$userId) {
            http_response_code(401);
            echo json_encode(["message" => "旧密码错误，身份验证失败"]);
            exit;
        }

        $stmt = $conn->prepare("UPDATE classmates SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newPassword, $userId);
        if ($stmt->execute()) {
            echo json_encode(["message" => "密码修改成功"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "密码更新失败"]);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(["message" => "无效操作"]);
}

$conn->close();