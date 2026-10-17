<?php
// 后台（config 管理员）身份的公共实现。
//
// 以前分散在两处：admin-files/_auth.php 定义 admin_csrf_token / admin_verify_csrf，
// admin-files/login.php 和 admin-files/admin-security.php 各自又定义了一遍
// boya_admin_password_is_hash。现在集中到这里，_auth.php 只保留「守卫页面」这点逻辑。

require_once __DIR__ . '/bootstrap.php';

/** 管理员口令允许是 password_hash() 生成的 hash，也兼容旧的明文写法。 */
function boya_admin_password_is_hash($value)
{
    return is_string($value) && preg_match('/^\$(2[aby]|argon2)/', $value) === 1;
}

/** 校验用户输入的管理员口令（hash 与明文两种存法都支持）。 */
function boya_admin_password_verify($input, $stored)
{
    if (!is_string($stored) || $stored === '') {
        return false;
    }
    return boya_admin_password_is_hash($stored)
        ? password_verify((string)$input, $stored)
        : hash_equals($stored, (string)$input);
}

function admin_csrf_token()
{
    return boya_csrf_token('admin');
}

function admin_verify_csrf($token)
{
    return boya_csrf_verify('admin', $token);
}

/** 登录成功：换 session id、置标记、换 token。 */
function boya_admin_login_success()
{
    boya_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    boya_csrf_rotate('admin');
    unset($_SESSION['admin_login_failures'], $_SESSION['admin_login_locked_until']);
}

/** 退出登录：清空整个会话（管理员和同学身份一起清）。 */
function boya_admin_logout()
{
    boya_session_start();
    $_SESSION = [];
    session_destroy();
}
