<?php
// 留言板（帖子 + 回复）的公共实现：连库、自动建表、文本清洗、跨库取用户名/头像。
//
// 注意两个平台事实：
//   1. classmates 与 messages 是两个独立数据库、独立用户，**不能跨库 JOIN**，
//      所以帖子只存 author_id 数字，用户名/头像另外用 member_conn() 批量取；
//   2. 表都是 MyISAM（无事务），所以不存楼层号，渲染时按 id 顺序算。

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/_member.php';

date_default_timezone_set('Asia/Shanghai');

/** 统一 JSON 输出（并禁止缓存，避免 Cloudflare/浏览器缓存接口）。 */
function forum_json($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 当前时间字符串（与站点其它表一致，按东八区）。 */
function forum_now()
{
    return date('Y-m-d H:i:s');
}

/** 连接留言库，并保证论坛两张表存在。 */
function forum_conn()
{
    global $config;

    $conn = new mysqli(
        $config['messages_db']['host'],
        $config['messages_db']['username'],
        $config['messages_db']['password'],
        $config['messages_db']['database']
    );
    if ($conn->connect_error) {
        error_log('forum DB connect failed: ' . $conn->connect_error);
        forum_json(['message' => '数据库连接失败，请稍后重试'], 500);
    }
    $conn->set_charset('utf8mb4');

    $conn->query("CREATE TABLE IF NOT EXISTS `forum_posts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `author_id` int(11) NOT NULL DEFAULT 0,
        `author_name` varchar(30) NOT NULL DEFAULT '',
        `title` varchar(100) NOT NULL,
        `content` text NOT NULL,
        `created_at` datetime NOT NULL,
        `updated_at` datetime NOT NULL,
        `last_reply_at` datetime DEFAULT NULL,
        `reply_count` int(11) NOT NULL DEFAULT 0,
        `view_count` int(11) NOT NULL DEFAULT 0,
        `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
        `is_locked` tinyint(1) NOT NULL DEFAULT 0,
        `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_list` (`is_deleted`, `is_pinned`, `last_reply_at`),
        KEY `idx_author` (`author_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");

    $conn->query("CREATE TABLE IF NOT EXISTS `forum_replies` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `post_id` int(11) NOT NULL,
        `author_id` int(11) NOT NULL DEFAULT 0,
        `author_name` varchar(30) NOT NULL DEFAULT '',
        `content` text NOT NULL,
        `created_at` datetime NOT NULL,
        `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_post` (`post_id`, `id`),
        KEY `idx_author` (`author_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;");

    return $conn;
}

/**
 * 批量取同学的用户名与头像（跨库，所以单独一次查询）。
 * 返回 [id => ['username' => ..., 'avatar' => ...]]
 */
function forum_member_profiles(array $ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }

    $conn = member_conn();
    if (!$conn) {
        return [];
    }

    // id 全部经过 intval()，直接拼进 IN() 是安全的（也就避开了 bind_param 可变参数的坑）
    $idList = implode(',', $ids);
    $result = $conn->query("SELECT id, username, nickname, avatar, signature, title, is_admin
                            FROM classmates WHERE id IN ($idList)");
    if (!$result) {
        error_log('forum profile query failed: ' . $conn->error);
        return [];
    }

    $profiles = [];
    while ($row = $result->fetch_assoc()) {
        $profiles[(int)$row['id']] = [
            'username' => (string)($row['username'] ?? ''),
            'nickname' => (string)($row['nickname'] ?? ''),
            'avatar' => (string)($row['avatar'] ?? ''),
            'signature' => (string)($row['signature'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'is_admin' => (int)($row['is_admin'] ?? 0),
        ];
    }
    return $profiles;
}

/** 当前访问者有没有论坛管理权限：config 管理员，或被标为 is_admin 的同学。 */
function forum_can_moderate()
{
    if (!empty($_SESSION['admin_logged_in'])) {
        return true;
    }
    $memberId = member_id();
    if ($memberId <= 0) {
        return false;
    }
    $profiles = forum_member_profiles([$memberId]);
    return !empty($profiles[$memberId]['is_admin']);
}

/**
 * 校验 CSRF：同学会话或管理员会话，任一通过即可（两套登录共用同一个 PHP session）。
 */
function forum_csrf_ok($token)
{
    if (!is_string($token) || $token === '') {
        return false;
    }
    if (member_id() > 0 && member_verify_csrf($token)) {
        return true;
    }
    return !empty($_SESSION['admin_logged_in'])
        && !empty($_SESSION['admin_csrf_token'])
        && hash_equals((string)$_SESSION['admin_csrf_token'], $token);
}

/**
 * 危险片段检查（服务端第一道；前端仍然会走 marked + DOMPurify 第二道）。
 * 不用 strip_tags：那会把 "1<2>0" 这种正常文字一起吃掉。
 */
function forum_text_forbidden($text)
{
    $patterns = [
        '<script', '<iframe', '<object', '<embed', '<style', '<meta', '<link', '<base',
        '<form', '<input', '<svg', '<math',
        'javascript:', 'vbscript:', 'data:text/html',
        'onerror=', 'onload=', 'onclick=', 'onmouseover=', 'onfocus=', 'onmouseenter=', 'srcdoc=',
    ];
    $lower = mb_strtolower((string)$text);
    foreach ($patterns as $pattern) {
        if (strpos($lower, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * 清洗正文/标题：统一换行、去掉控制字符、限长。
 */
function forum_clean_text($text, $maxLength)
{
    $value = (string)$text;
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    $value = trim($value);
    return mb_substr($value, 0, max(1, (int)$maxLength));
}

/** 列表分页参数：返回 [page, pageSize, offset]。 */
function forum_pager($pageSize = 20)
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = max(1, (int)$pageSize);
    return [$page, $pageSize, ($page - 1) * $pageSize];
}
