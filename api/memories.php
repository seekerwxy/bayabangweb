<?php
header('Content-Type: application/json');

require_once '../config.php';

$db_host = $config['messages_db']['host'];
$db_user = $config['messages_db']['username'];
$db_pass = $config['messages_db']['password'];
$db_name = $config['messages_db']['database'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => '数据库连接失败']);
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
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
echo json_encode($events);