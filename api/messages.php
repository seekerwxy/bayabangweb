<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

function messages_response($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = new mysqli(
    $config['messages_db']['host'],
    $config['messages_db']['username'],
    $config['messages_db']['password'],
    $config['messages_db']['database']
);

if ($conn->connect_error) {
    error_log('messages DB connect failed: ' . $conn->connect_error);
    messages_response(500, ['message' => '数据库连接失败']);
}
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = $conn->query('SELECT id, title, author, content, color, created_at FROM messages ORDER BY created_at DESC');
    if (!$result) {
        error_log('messages list query failed: ' . $conn->error);
        messages_response(500, ['message' => '查询失败']);
    }
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode($messages, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $author = trim($input['author'] ?? '');
    $content = trim($input['content'] ?? '');
    $title = trim($input['title'] ?? '');
    $color = trim($input['color'] ?? '');

    if ($author === '' || $content === '') {
        messages_response(400, ['message' => '姓名和内容不能为空']);
    }
    if (mb_strlen($author) > 30) {
        messages_response(400, ['message' => '姓名不能超过30个字符']);
    }
    if (mb_strlen($title) > 100) {
        messages_response(400, ['message' => '标题不能超过100个字符']);
    }
    if (mb_strlen($content) > 2000) {
        messages_response(400, ['message' => '留言内容不能超过2000个字符']);
    }
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '';
    }

    $stmt = $conn->prepare('INSERT INTO messages (title, author, content, color, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        error_log('messages insert prepare failed: ' . $conn->error);
        messages_response(500, ['message' => '写入失败']);
    }
    $stmt->bind_param('ssss', $title, $author, $content, $color);
    if ($stmt->execute()) {
        $stmt->close();
        messages_response(200, ['message' => 'success']);
    }
    error_log('messages insert failed: ' . $stmt->error);
    $stmt->close();
    messages_response(500, ['message' => '写入失败']);
}

messages_response(405, ['message' => '不支持的请求方法']);
