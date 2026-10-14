<?php
// 当前登录状态：给留言板顶部状态条、发帖/回复框提供用户名与 CSRF token。
require_once __DIR__ . '/../_member.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$memberId = member_id();
$payload = [
    'ok' => true,
    'loggedIn' => $memberId > 0,
    'uid' => 0,
    'username' => '',
    'hasUsername' => false,
    'nickname' => '',
    'avatar' => '',
    'title' => '',
    'isAdmin' => false,
    'csrf_token' => member_csrf_token(),
    'admin_csrf_token' => '',
];

if ($memberId > 0) {
    $conn = member_conn();
    if ($conn) {
        $stmt = $conn->prepare('SELECT id, username, nickname, avatar, title, is_admin FROM classmates WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $memberId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $username = (string)($row['username'] ?? '');
                $payload['uid'] = (int)$row['id'];
                $payload['username'] = $username;
                $payload['hasUsername'] = $username !== '';
                $payload['nickname'] = (string)($row['nickname'] ?? '');
                $payload['avatar'] = (string)($row['avatar'] ?? '');
                $payload['title'] = (string)($row['title'] ?? '');
                $payload['isAdmin'] = (int)($row['is_admin'] ?? 0) === 1;
                // 顺带把会话里的用户名同步一下，供页面直接读
                $_SESSION['classmate_username'] = $username;
            }
        }
    }
}

// config 里的管理员（后台登录过）也能在论坛页面上直接管理
if (!empty($_SESSION['admin_logged_in'])) {
    $payload['isAdmin'] = true;
    $payload['admin_csrf_token'] = (string)($_SESSION['admin_csrf_token'] ?? '');
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
