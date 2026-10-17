<?php

require_once __DIR__ . '/../inc/admin.php';
require_once __DIR__ . '/../inc/theme.php';
require_once __DIR__ . '/../inc/login_guard.php';
require_once __DIR__ . '/../inc/audit.php';

boya_session_start();

if (boya_is_admin()) {
    header('Location: admin-list.php');
    exit;
}

// 表单里要带 CSRF token，所以进来就生成一个
admin_csrf_token();

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $validToken = admin_verify_csrf($token);

    // 服务端限速：计数存在服务器文件里，换浏览器 / 清 cookie / 伪造 IP 头都绕不过
    $blockedSeconds = boya_login_guard_blocked_seconds('admin', 'account');

    if (!$validToken) {
        $error = '表单已过期，请重试';
    } elseif ($blockedSeconds > 0) {
        $error = '尝试次数过多，请 ' . max(1, (int)ceil($blockedSeconds / 60)) . ' 分钟后再试';
    } else {
        $adminUsername = $config['admin_db']['username'] ?? '';
        $adminPassword = $config['admin_db']['password'] ?? '';

        $validUser = is_string($adminUsername) && $adminUsername !== ''
            && hash_equals($adminUsername, $username);

        $validPassword = boya_admin_password_verify($password, $adminPassword);

        if ($validUser && $validPassword) {
            boya_login_guard_clear('admin', 'account');
            boya_admin_login_success();
            boya_audit('admin-login-ok', 'username=' . $username, 'admin');
            header('Location: admin-list.php');
            exit;
        }

        $entry = boya_login_guard_fail('admin', 'account');
        boya_login_guard_global_fail('admin');
        boya_audit(
            'admin-login-fail',
            'username=' . $username . ' fails=' . (int)($entry['fails'] ?? 0),
            'guest'
        );
        $error = (int)($entry['until'] ?? 0) > time()
            ? '尝试次数过多，请 15 分钟后再试'
            : '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 | 温二外25级博雅班</title>
    <?= boya_theme_init_script() ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f4f5f7; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: #fff; width: 100%; max-width: 380px; border-radius: 8px; padding: 32px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        h1 { font-size: 1.2rem; font-weight: 600; margin-bottom: 20px; }
        label { display: block; font-size: .85rem; margin: 14px 0 6px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #d5d9de; border-radius: 6px; font-size: .95rem; }
        button { width: 100%; margin-top: 22px; padding: 10px; background: #111; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
        .error { margin-top: 14px; color: #b91c1c; font-size: .85rem; }
    </style>
    <link rel="stylesheet" href="<?= boya_asset('css/admin-dark.css') ?>">
</head>
<body>
    <form class="login-card" method="post" action="login.php">
        <h1>博雅班管理员登录</h1>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
        <label for="username">用户名</label>
        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required autocomplete="username">
        <label for="password">密码</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit">登录</button>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </form>
</body>
</html>
