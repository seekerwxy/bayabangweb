<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

$db_host = $config['messages_db']['host'];
$db_user = $config['messages_db']['username'];
$db_pass = $config['messages_db']['password'];
$db_name = $config['messages_db']['database'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log('memories DB connect failed: ' . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['error' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
    exit;
}
$conn->set_charset("utf8mb4");

// 只支持 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => '方法不允许']);
    exit;
}

$result = $conn->query("SELECT * FROM timeline_events ORDER BY display_order ASC, id ASC");
if (!$result) {
    error_log('timeline query failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => '查询失败'], JSON_UNESCAPED_UNICODE);
    exit;
}
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
echo json_encode($events, JSON_UNESCAPED_UNICODE);
