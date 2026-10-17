<?php
// 当前登录状态：给留言板顶部状态条、发帖/回复框提供用户名与 CSRF token，
// 也给右上角导航头像提供头像与姓氏（name 的第一个字做无头像占位）。
require_once __DIR__ . '/../inc/member.php';

$memberId = member_id();
$payload = [
    'ok' => true,
    'loggedIn' => $memberId > 0,
    'uid' => 0,
    'username' => '',
    'hasUsername' => false,
    'name' => '',            // 真实姓名：只用于头像占位字，页面只取首字符展示
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
        $stmt = $conn->prepare('SELECT id, name, username, nickname, avatar, title, is_admin FROM classmates WHERE id = ? LIMIT 1');
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
                $payload['name'] = (string)($row['name'] ?? '');
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
if (boya_is_admin()) {
    $payload['isAdmin'] = true;
    $payload['admin_csrf_token'] = (string)($_SESSION['admin_csrf_token'] ?? '');
}

// boya_json() 会补 Content-Type 与 no-store（以前这里手写了一遍，别的接口又是另一套）
boya_json($payload);
