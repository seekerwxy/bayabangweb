<?php
// 同学账号（classmate）会话、CSRF 与同学录数据库连接的公共实现。
// 留言板、资料编辑、头像上传、个人主页都从这里取。
//
// 本文件由原来的根目录 _member.php 搬进 inc/，并把手写的 session 引导与
// new mysqli 换成 inc/session.php / inc/db.php 里的统一实现。

require_once __DIR__ . '/bootstrap.php';

// 保留原行为：任何 include 了本文件的请求（页面或接口）都会启动会话。
boya_session_start();

/** 当前登录的同学 id，未登录返回 0。 */
function member_id()
{
    boya_session_start();
    return (int)($_SESSION['classmate_id'] ?? 0);
}

function member_csrf_token()
{
    return boya_csrf_token('classmate');
}

function member_verify_csrf($token)
{
    return boya_csrf_verify('classmate', $token);
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
 * 同学录表的结构同步：补齐论坛需要的字段、清掉已弃用字段。
 *
 * 返回 true 表示「所有想要的字段都在」，于是 inc/db.php 记下版本、以后不再每次请求都跑。
 * 字段清单：
 *   username（登录名 + 论坛显示名，唯一）
 *   username_changed_at（改名时间，用于 7 天限一次）
 *   created_at / last_active_at / title / is_admin
 */
function boya_schema_classmates(mysqli $conn)
{
    // 已弃用字段：资料上的「个性签名」和「座右铭」重复，统一只保留 quote。
    // 这里只在 signature 确实存在、且 quote 是空的时候把内容搬过去，再删列，
    // 于是线上数据不会凭空丢失，多次执行也安全（列没了就什么都不做）。
    $wanted = [
        'username' => 'ADD COLUMN `username` varchar(30) DEFAULT NULL, ADD UNIQUE KEY `uk_username` (`username`)',
        'username_changed_at' => 'ADD COLUMN `username_changed_at` datetime DEFAULT NULL',
        'created_at' => 'ADD COLUMN `created_at` datetime DEFAULT NULL',
        'last_active_at' => 'ADD COLUMN `last_active_at` datetime DEFAULT NULL',
        'title' => 'ADD COLUMN `title` varchar(20) DEFAULT NULL',
        'is_admin' => 'ADD COLUMN `is_admin` tinyint(1) NOT NULL DEFAULT 0',
    ];
    $deprecated = ['signature' => 'quote'];

    $existing = [];
    $res = $conn->query('SHOW COLUMNS FROM `classmates`');
    if (!$res) {
        error_log('member schema: SHOW COLUMNS failed: ' . $conn->error);
        return false;
    }
    while ($row = $res->fetch_assoc()) {
        $existing[(string)$row['Field']] = true;
    }
    if (!$existing) {
        return false;
    }

    $hadCreatedAt = isset($existing['created_at']);
    $ok = true;

    foreach ($wanted as $column => $clause) {
        if (isset($existing[$column])) {
            continue;
        }
        if ($conn->query('ALTER TABLE `classmates` ' . $clause)) {
            $existing[$column] = true;
        } else {
            error_log('member column ' . $column . ' add failed: ' . $conn->error);
            $ok = false;
        }
    }

    // 老记录补一个加入时间，免得个人主页显示空白
    if (!$hadCreatedAt) {
        $conn->query('UPDATE `classmates` SET created_at = NOW() WHERE created_at IS NULL');
    }

    // 清理已弃用字段（先搬运、再删列；quote 非空时以 quote 为准）。
    // 删列失败只写日志、不影响站点，因为代码里已经不再读写这一列了。
    foreach ($deprecated as $column => $target) {
        if (!isset($existing[$column])) {
            continue;
        }
        $conn->query('UPDATE `classmates` SET `' . $target . '` = `' . $column . '`
                      WHERE `' . $target . '` IS NULL OR `' . $target . '` = \'\'');
        if ($conn->query('ALTER TABLE `classmates` DROP COLUMN `' . $column . '`')) {
            error_log('member deprecated column dropped: ' . $column . ' -> ' . $target);
        } else {
            error_log('member deprecated column drop failed: ' . $column . ' ' . $conn->error);
        }
    }

    return $ok;
}

/**
 * 连接同学录数据库，并保证论坛需要的字段存在。
 * 连接失败返回 null，调用方自行降级。
 */
function member_conn()
{
    static $schemaChecked = false;

    $conn = boya_db('classmates');
    if (!$conn) {
        return null;
    }

    if (!$schemaChecked) {
        $schemaChecked = true;   // 每个请求最多检查一次
        boya_schema_once('classmates-columns', BOYA_SCHEMA_CLASSMATES, function () use ($conn) {
            return boya_schema_classmates($conn);
        });
    }

    return $conn;
}

/** 更新"最后活跃"时间：只在登录 / 发帖 / 回复时调用，不每次浏览都写库。 */
function member_touch_active($memberId)
{
    $conn = member_conn();
    if (!$conn || (int)$memberId <= 0) {
        return;
    }
    $conn->query('UPDATE `classmates` SET last_active_at = NOW() WHERE id = ' . (int)$memberId);
}
