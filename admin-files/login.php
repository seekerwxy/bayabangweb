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

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin-list.php');
    exit;
}

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $failures = $_SESSION['admin_login_failures'] ?? 0;
    $lockedUntil = $_SESSION['admin_login_locked_until'] ?? 0;

    $validToken = is_string($token)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);

    if (!$validToken) {
        $error = '表单已过期，请重试';
    } elseif ($failures >= 5 && time() < $lockedUntil) {
        $error = '尝试次数过多，请15分钟后再试';
    } else {
        $adminUsername = $config['admin_db']['username'] ?? '';
        $adminPassword = $config['admin_db']['password'] ?? '';
        $validUser = is_string($adminUsername) && $adminUsername !== '' && hash_equals($adminUsername, $username);
        $validPassword = is_string($adminPassword) && $adminPassword !== ''
            && (password_verify($password, $adminPassword) || hash_equals($adminPassword, $password));

        if ($validUser && $validPassword) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            unset($_SESSION['admin_login_failures'], $_SESSION['admin_login_locked_until']);
            header('Location: admin-list.php');
            exit;
        }

        $_SESSION['admin_login_failures'] = $failures + 1;
        if ($_SESSION['admin_login_failures'] >= 5) {
            $_SESSION['admin_login_locked_until'] = time() + 900;
            $error = '尝试次数过多，请15分钟后再试';
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <script>
    (function () {
        try {
            var saved = localStorage.getItem('theme');
            var theme = (saved === 'dark' || saved === 'light')
                ? saved
                : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {}
    })();
    </script>
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
    <link rel="stylesheet" href="../css/admin-dark.css?v=<?= filemtime(__DIR__ . '/../css/admin-dark.css') ?>">
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
