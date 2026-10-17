<?php
// 会话与 CSRF：全站只有这一套实现。
//
// 以前 _member.php / admin-files/_auth.php / admin-files/login.php 各写一遍
// session_set_cookie_params + session_start，参数还得三处同步改。现在统一走这里。
//
// 两套「身份」共用同一个 PHP session（站点只有一个域名，没必要开两个 session 名）：
//   - member：登录的同学，$_SESSION['classmate_id' / 'classmate_name' / 'classmate_username']
//   - admin ：config 里的管理员（后台），$_SESSION['admin_logged_in']
// CSRF token 按作用域分开存，互不干扰：classmate_csrf_token / admin_csrf_token。

if (!function_exists('boya_session_start')) {
    /** 幂等启动会话（已经启动就什么都不做）。 */
    function boya_session_start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
    }
}

if (!function_exists('boya_csrf_session_key')) {
    function boya_csrf_session_key($scope)
    {
        return ($scope === 'admin' ? 'admin' : 'classmate') . '_csrf_token';
    }
}

if (!function_exists('boya_csrf_token')) {
    /** 取（必要时生成）某个作用域的 CSRF token。 */
    function boya_csrf_token($scope = 'classmate')
    {
        boya_session_start();
        $key = boya_csrf_session_key($scope);
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[$key];
    }
}

if (!function_exists('boya_csrf_verify')) {
    /** 校验某个作用域的 CSRF token（用 hash_equals，避免时序泄漏）。 */
    function boya_csrf_verify($scope, $token)
    {
        boya_session_start();
        $key = boya_csrf_session_key($scope);
        return is_string($token)
            && $token !== ''
            && !empty($_SESSION[$key])
            && hash_equals((string)$_SESSION[$key], $token);
    }
}

if (!function_exists('boya_csrf_rotate')) {
    /** 登录成功后换一个新的 token。 */
    function boya_csrf_rotate($scope = 'classmate')
    {
        boya_session_start();
        $_SESSION[boya_csrf_session_key($scope)] = bin2hex(random_bytes(32));
        return (string)$_SESSION[boya_csrf_session_key($scope)];
    }
}

if (!function_exists('boya_session_forget')) {
    /** 清掉某个作用域在会话里的所有痕迹（退出登录用）。 */
    function boya_session_forget(array $keys)
    {
        boya_session_start();
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }
}

if (!function_exists('boya_is_admin')) {
    /** 当前访客是不是 config 管理员（后台登录过）。 */
    function boya_is_admin()
    {
        boya_session_start();
        return !empty($_SESSION['admin_logged_in']);
    }
}
