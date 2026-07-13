<?php

require_once 'config.php';

//统一时区
date_default_timezone_set('Asia/Shanghai');

//一段神秘的代码
//header('Access-Control-Allow-Origin: *');
//header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
//header('Access-Control-Allow-Headers: Content-Type');

// 数据库连接
$conn = new mysqli($config['messages_db']['host'], $config['messages_db']['username'], $config['messages_db']['password'], $config['messages_db']['database']);

// 检查连接
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "数据库连接失败: " . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");

// 处理请求
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 获取所有留言，按时间倒序
    $sql = "SELECT id, title, author, content, color, created_at FROM messages ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $messages = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
    echo json_encode($messages);

} elseif ($method === 'POST') {
    // 接收 JSON 数据
    $input = json_decode(file_get_contents('php://input'), true);
    $author = trim($input['author'] ?? '');
    $content = trim($input['content'] ?? '');

    // 验证
    if ($author === '' || $content === '') {
        http_response_code(400);
        echo json_encode(["message" => "姓名和内容不能为空"]);
        exit;
    }
    if (mb_strlen($author) > 30) {
        http_response_code(400);
        echo json_encode(["message" => "姓名不能超过30个字符"]);
        exit;
    }
    if (mb_strlen($content) > 500) {
        http_response_code(400);
        echo json_encode(["message" => "留言内容不能超过500个字符"]);
        exit;
    }

    // 插入数据库（使用预处理语句防止 SQL 注入）
    $title = trim($input['title'] ?? '');
    $color = trim($input['color'] ?? '');
    // 验证 color 格式（只允许 # 开头 + 6位十六进制，或为空）
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '';   // 不合法则忽略
    }

    $stmt = $conn->prepare("INSERT INTO messages (title, author, content, color, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $title, $author, $content, $color);
        if ($stmt->execute()) {
            echo json_encode(["message" => "success"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "写入失败: " . $stmt->error]);
        }
        $stmt->close();

    } else {
        http_response_code(405);
        echo json_encode(["message" => "不支持的请求方法"]);
    }

    $conn->close();