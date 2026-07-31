<?php

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ]);
    session_start();
}

function admin_csrf_token() {
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf($token) {
    return is_string($token)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['logout'])
    && $_POST['logout'] === '1'
    && admin_verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['admin_logged_in'])) {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: login.php');
    exit;
}
