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
    // 注意：signature 已弃用（与 quote 重复，列可能已被删掉），别再往这里加
    $idList = implode(',', $ids);
    $result = $conn->query("SELECT id, username, nickname, avatar, title, is_admin
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

/**
 * 浏览量去重用的访客标识（未登录时按 IP 归并）。
 *
 * 为什么不用 $_SESSION：线上实测这台主机的 PHP session 数据不稳定
 * （带着固定 PHPSESSID 再请求时服务端不再下发 Set-Cookie，说明 session 是认的，
 *  但写进 $_SESSION 的数组下一次请求就消失了），所以改用登录限速、发帖限速
 * 同一套「服务器文件」思路：与 cookie 无关，清 cookie、换浏览器都绕不过。
 */
function forum_viewer_key()
{
    $ip = '';
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', (string)$_SERVER[$key])[0]);
            break;
        }
    }
    if ($ip === '') {
        $ip = 'unknown';
    }
    return substr(hash('sha256', 'boya-view|' . $ip), 0, 32);
}

/**
 * 浏览量去重：登录用户按账号算（每人每帖 30 天内一次），游客按 IP 算（6 小时窗口）。
 * 返回这次是否计入。
 */
function forum_view_hit($postId, $viewerId = 0)
{
    $postId = (int)$postId;
    $viewerId = (int)$viewerId;
    if ($postId <= 0) {
        return false;
    }
    // 登录用户窗口 30 天（同一账号对同一篇帖子一个月内只算一次浏览）；
    // 游客沿用 6 小时 IP 窗口。
    $windowSeconds = $viewerId > 0 ? 30 * 86400 : 6 * 3600;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/view-seen.json';

    $data = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    $now = time();
    // 顺手清掉过期记录：登录用户按 30 天、游客按 6 小时，文件不会无限增长
    foreach ($data as $key => $seenAt) {
        $window = strpos($key, '|ip:') !== false ? 6 * 3600 : 30 * 86400;
        if (($now - (int)$seenAt) > $window) {
            unset($data[$key]);
        }
    }

    $entryKey = $postId . '|' . ($viewerId > 0 ? 'member:' . $viewerId : 'ip:' . forum_viewer_key());
    $counted = !isset($data[$entryKey]);
    if ($counted) {
        $data[$entryKey] = $now;
    }
    if (count($data) > 5000) {
        $data = array_slice($data, -5000, null, true);
    }
    if (!@file_put_contents($file, json_encode($data), LOCK_EX)) {
        error_log('forum view-seen write failed: ' . $file);
    }
    return $counted;
}
