<?php
// 当前登录状态：给留言板顶部状态条、发帖/回复框提供用户名与 CSRF token。
require_once __DIR__ . '/../_member.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$memberId = member_id();
$payload = [
    'ok' => true,
    'loggedIn' => $memberId > 0,
    'username' => '',
    'hasUsername' => false,
    'avatar' => '',
    'csrf_token' => member_csrf_token(),
];

if ($memberId > 0) {
    $conn = member_conn();
    if ($conn) {
        $stmt = $conn->prepare('SELECT username, avatar FROM classmates WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $username = (string)($row['username'] ?? '');
                $payload['username'] = $username;
                $payload['hasUsername'] = $username !== '';
                $payload['avatar'] = (string)($row['avatar'] ?? '');
                // 顺带把会话里的用户名同步一下，供页面直接读
                $_SESSION['classmate_username'] = $username;
            }
        }
    }
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
