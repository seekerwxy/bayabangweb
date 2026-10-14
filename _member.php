<?php
// 同学账号（classmate）会话、CSRF 与同学录数据库连接的公共实现。
// 留言板、资料编辑、头像上传都从这里取，避免各处再写一套 session。

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** 当前登录的同学 id，未登录返回 0。 */
function member_id()
{
    return (int)($_SESSION['classmate_id'] ?? 0);
}

/** 当前登录同学的真实姓名（只用于会话标识与审计，论坛不展示）。 */
function member_real_name()
{
    return (string)($_SESSION['classmate_name'] ?? '');
}

/** 当前登录同学的论坛用户名（可能还没设置）。 */
function member_username()
{
    return (string)($_SESSION['classmate_username'] ?? '');
}

function member_csrf_token()
{
    if (empty($_SESSION['classmate_csrf_token'])) {
        $_SESSION['classmate_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['classmate_csrf_token'];
}

function member_verify_csrf($token)
{
    return is_string($token)
        && !empty($_SESSION['classmate_csrf_token'])
        && hash_equals($_SESSION['classmate_csrf_token'], $token);
}

/** 论坛上的展示名：用户名优先，没设置就用占位（绝不显示真实姓名）。 */
function member_display_name($username, $memberId)
{
    $name = trim((string)$username);
    return $name !== '' ? $name : ('同学#' . (int)$memberId);
}

/** 用户名格式：3-20 位，字母 / 数字 / 下划线 / 短横线。 */
function member_username_valid($username)
{
    return preg_match('/^[A-Za-z0-9_-]{3,20}$/', (string)$username) === 1;
}

/** 保留用户名：防止冒充官方角色。 */
function member_username_reserved($username)
{
    $reserved = [
        'admin', 'administrator', 'root', 'system', 'teacher', 'moderator', 'guest',
        'boya', 'bayabang', 'baya', 'official', 'support',
        '管理员', '老师', '班主任', '校长', '同学', '匿名', '历史留言', '官方',
    ];
    return in_array(mb_strtolower(trim((string)$username)), $reserved, true);
}

/**
 * 连接同学录数据库，并保证论坛需要的字段存在：
 *   username（登录名 + 论坛显示名，唯一）
 *   username_changed_at（改名时间，用于 7 天限一次）
 * 连接失败返回 null，调用方自行降级。
 */
function member_conn()
{
    global $config;

    static $conn = null;
    static $checked = false;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(
        $config['classmates_db']['host'],
        $config['classmates_db']['username'],
        $config['classmates_db']['password'],
        $config['classmates_db']['database']
    );
    if ($conn->connect_error) {
        error_log('member DB connect failed: ' . $conn->connect_error);
        $conn = null;
        return null;
    }
    $conn->set_charset('utf8mb4');

    // 自动补齐字段（每个请求只查一次）
    if (!$checked) {
        $checked = true;
        $res = $conn->query("SHOW COLUMNS FROM `classmates` LIKE 'username'");
        if ($res && (int)$res->num_rows === 0) {
            $conn->query("ALTER TABLE `classmates`
                ADD COLUMN `username` varchar(30) DEFAULT NULL,
                ADD COLUMN `username_changed_at` datetime DEFAULT NULL,
                ADD UNIQUE KEY `uk_username` (`username`)");
        }
    }

    return $conn;
}
