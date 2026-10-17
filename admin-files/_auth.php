<?php
// 后台页面的「守卫」：没登录就跳登录页（接口调用则返回 401 JSON），并处理退出登录。
//
// 页面（admin-files/*.php）与后台接口（api/admin-*.php，会先 define BOYA_ADMIN_API）
// 都 require 这个文件，行为与重构前完全一致。

require_once __DIR__ . '/../inc/admin.php';

$boyaAdminIsApi = isset($_GET['api']) || (defined('BOYA_ADMIN_API') && BOYA_ADMIN_API);

// 退出登录：必须是 POST + 正确的 CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['logout'] ?? '') === '1'
    && admin_verify_csrf($_POST['csrf_token'] ?? '')) {
    boya_admin_logout();
    if ($boyaAdminIsApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => '已退出登录'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: login.php');
    exit;
}

if (!boya_is_admin()) {
    if ($boyaAdminIsApi) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: login.php');
    exit;
}
